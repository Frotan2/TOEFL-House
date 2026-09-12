<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Calendar\CalendarAuthority;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The test suite's single clock seam.
 *
 * Every test freezes the PHP-side clocks to the CURRENT Kabul civil day at
 * 12:00 Afghanistan Time (Asia/Kabul, fixed UTC+04:30, no DST — the same
 * zone CalendarAuthority uses). {@see TestCase::setUp()} applies the
 * seam per test; Laravel's test lifecycle clears Carbon test time during
 * teardown, so one test can never leak a frozen clock into another.
 *
 * Why noon AFT:
 *   - The application's single civil clock is the Kabul civil day
 *     (CalendarAuthority). The Postgres session stays UTC so timestamptz
 *     instants round-trip without offset shifts; append-day guards and
 *     projections resolve their civil day through the kabul_today() SQL
 *     helper (migration 000126), which converts the current instant to the
 *     Kabul wall-clock date at every wall-clock hour.
 *   - Kabul noon is 07:30 UTC, an instant on which the UTC and the Kabul civil
 *     dates coincide. Fixtures and commands that still read
 *     CarbonImmutable::today() in the default (UTC) PHP timezone therefore
 *     observe the exact same date as CalendarAuthority and kabul_today() — the
 *     bug class where one fact was stamped on the UTC day and a second fact on
 *     the already-advanced Kabul day (19:30–00:00 UTC window) cannot occur.
 *   - Timestamps become deterministic within the run instead of drifting with
 *     the real minute.
 *
 * Postgres has no supported per-session way to freeze CURRENT_DATE, so the
 * frozen day is intentionally the current REAL Kabul civil day: the database
 * guards (kabul_today()) and the frozen PHP clock then agree on the date
 * regardless of the hour the suite runs. Scheduled historical/future fixture
 * dates are always passed explicitly by the tests.
 */
final class DeterministicTestClock
{
    /** Kabul civil noon (12:00 AFT), the instant at which both civil dates coincide. */
    public const FROZEN_CIVIL_TIME = '12:00:00';

    public static function freeze(): CarbonImmutable
    {
        // Read the real civil day BEFORE freezing. At setUp the framework has
        // just cleared any inherited test time, so the authority reports the
        // actual current Kabul day.
        $civilDay = (new CalendarAuthority)->todayAsString();

        $frozen = CarbonImmutable::parse($civilDay.' '.self::FROZEN_CIVIL_TIME, CalendarAuthority::KABUL_TIMEZONE)
            ->setTimezone('UTC');

        Carbon::setTestNow($frozen);
        CarbonImmutable::setTestNow($frozen);

        return $frozen;
    }
}
