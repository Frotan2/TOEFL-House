<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Calendar\CalendarAuthority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Calendar/Date-Time authority API — single canonical source for Kabul/Shamsi business dates.
 *
 * - Gregorian storage is canonical (UTC midnight)
 * - Shamsi is derived via ratified version-1 series
 * - Kabul AFT UTC+04:30 is fixed, no DST
 * - No business logic calculates dates via browser UTC — React consumes these values
 * - Reporting uses canonical period selector, not free-text
 */
final class CalendarApiController extends Controller
{
    public function __construct(private readonly CalendarAuthority $calendar) {}

    public function today(): JsonResponse
    {
        $payload = $this->calendar->currentBusinessDatePayload();

        return response()->json(['data' => $payload]);
    }

    public function convert(Request $request): JsonResponse
    {
        $request->validate([
            'gregorian' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'shamsi' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            'version' => ['nullable', 'string', 'max:32'],
        ]);

        $version = $request->input('version');
        $gregorian = $request->input('gregorian');
        $shamsi = $request->input('shamsi');

        if ($gregorian !== null && $gregorian !== '') {
            $g = $this->calendar->parseGregorianDate((string) $gregorian);
            $data = $this->calendar->toApiPayload($g, $version !== null && $version !== '' ? (string) $version : null);
            return response()->json(['data' => $data]);
        }

        if ($shamsi !== null && $shamsi !== '') {
            $s = $this->calendar->parseShamsiDate((string) $shamsi, $version !== null && $version !== '' ? (string) $version : null);
            $gString = $this->calendar->toGregorian($s, $version !== null && $version !== '' ? (string) $version : null);
            $data = $this->calendar->toApiPayload($gString, $version !== null && $version !== '' ? (string) $version : null);
            return response()->json(['data' => $data]);
        }

        return response()->json(['error' => 'Provide gregorian or shamsi YYYY-MM-DD'], 422);
    }

    public function periods(Request $request): JsonResponse
    {
        $request->validate([
            'version' => ['nullable', 'string', 'max:32'],
        ]);

        $version = $request->input('version') !== null && $request->input('version') !== '' ? (string) $request->input('version') : null;
        $todayShamsi = $this->calendar->todayShamsi($version);

        // Shamsi year/month details
        $shamsiYear = $this->calendar->shamsiYearPeriod($todayShamsi->year, $version);
        $shamsiMonths = array_map(fn ($m) => $this->calendar->shamsiMonthPeriod($todayShamsi->year, $m, $version), range(1, 12));

        // Canonical DB periods
        $academic = DB::table('academic_periods')->select(['id', 'name', 'starts_on', 'ends_on', 'lifecycle_state'])->orderBy('starts_on')->limit(200)->get();
        $financial = DB::table('financial_periods')->select(['id', 'period_key', 'name', 'starts_on', 'ends_on', 'lifecycle_state'])->orderBy('starts_on')->limit(200)->get();
        $payroll = DB::table('payroll_periods')->select(['id', 'period_key', 'name', 'starts_on', 'ends_on', 'lifecycle_state'])->orderBy('starts_on')->limit(200)->get();

        return response()->json(['data' => [
            'today' => $this->calendar->currentBusinessDatePayload($version),
            'shamsi_year' => $shamsiYear,
            'shamsi_months' => $shamsiMonths,
            'academic_periods' => $academic,
            'financial_periods' => $financial,
            'payroll_periods' => $payroll,
            'kabul_timezone' => CalendarAuthority::KABUL_TIMEZONE,
            'kabul_offset_minutes' => CalendarAuthority::KABUL_OFFSET_MINUTES,
            'version' => $version ?? CalendarAuthority::DEFAULT_VERSION_ID,
        ]]);
    }

    public function boundaries(Request $request): JsonResponse
    {
        $request->validate([
            'shamsi_year' => ['nullable', 'integer', 'min:1300, max:1500'],
            'year' => ['nullable', 'integer', 'min:1300, max:1500'],
            'shamsi_month' => ['nullable', 'integer', 'min:1, max:12'],
            'month' => ['nullable', 'integer', 'min:1, max:12'],
            'version' => ['nullable', 'string', 'max:32'],
        ]);

        $year = $request->input('shamsi_year') ?? $request->input('year') ?? $this->calendar->todayShamsi()->year;
        $month = $request->input('shamsi_month') ?? $request->input('month');
        $version = $request->input('version') !== null && $request->input('version') !== '' ? (string) $request->input('version') : null;

        $year = (int) $year;

        if ($month !== null && $month !== '') {
            $data = $this->calendar->shamsiMonthPeriod($year, (int) $month, $version);
            return response()->json(['data' => [
                'year_boundaries' => $this->calendar->shamsiYearPeriod($year, $version),
                'month_boundaries' => $data,
                'kabul_timezone' => CalendarAuthority::KABUL_TIMEZONE,
                'kabul_offset_minutes' => CalendarAuthority::KABUL_OFFSET_MINUTES,
                'version' => $version ?? CalendarAuthority::DEFAULT_VERSION_ID,
            ]]);
        }

        $data = $this->calendar->shamsiYearPeriod($year, $version);
        $yearInfo = $this->calendar->yearInfo($year, $version);
        return response()->json(['data' => [
            'year_boundaries' => $data,
            'month_boundaries' => null,
            'leap' => $yearInfo->leap,
            'kabul_timezone' => CalendarAuthority::KABUL_TIMEZONE,
            'kabul_offset_minutes' => CalendarAuthority::KABUL_OFFSET_MINUTES,
            'version' => $version ?? CalendarAuthority::DEFAULT_VERSION_ID,
        ]]);
    }
}
