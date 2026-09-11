<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Modules\Calendar\CalendarAuthority;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class CalendarApiContractTest extends TestCase
{
    use BuildsActors;

    public function test_today_returns_authoritative_kabul_payload(): void
    {
        $actor = $this->personWithAuthority('cal-today-1', ['academic.structure']);
        $response = $this->actingAs($this->userForActor($actor))->getJson('/api/v1/calendar/today');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['gregorian']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $data['shamsi']);
        $this->assertSame(270, $data['kabul_offset_minutes']);
        $this->assertSame('Asia/Kabul', $data['kabul_timezone']);
        $this->assertSame('v1', $data['version']);
        $this->assertArrayHasKey('utc_now', $data);
        $this->assertArrayHasKey('kabul_now', $data);
    }

    public function test_convert_gregorian_to_shamsi(): void
    {
        $actor = $this->personWithAuthority('cal-convert-g-1', ['academic.structure']);
        $response = $this->actingAs($this->userForActor($actor))->getJson('/api/v1/calendar/convert?gregorian=2026-09-03');
        $response->assertOk();
        $this->assertSame('2026-09-03', $response->json('data.gregorian'));
        $this->assertSame('1405-06-12', $response->json('data.shamsi'));
        $this->assertSame(1405, $response->json('data.shamsi_year'));
    }

    public function test_convert_shamsi_to_gregorian(): void
    {
        $actor = $this->personWithAuthority('cal-convert-s-1', ['academic.structure']);
        $response = $this->actingAs($this->userForActor($actor))->getJson('/api/v1/calendar/convert?shamsi=1405-06-12');
        $response->assertOk();
        $this->assertSame('2026-09-03', $response->json('data.gregorian'));
        $this->assertSame('1405-06-12', $response->json('data.shamsi'));
    }

    public function test_periods_returns_canonical_selectors(): void
    {
        $actor = $this->personWithAuthority('cal-periods-1', ['academic.structure']);
        $response = $this->actingAs($this->userForActor($actor))->getJson('/api/v1/calendar/periods');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertArrayHasKey('academic_periods', $data);
        $this->assertArrayHasKey('financial_periods', $data);
        $this->assertArrayHasKey('payroll_periods', $data);
        $this->assertArrayHasKey('shamsi_year', $data);
        $this->assertArrayHasKey('kabul_timezone', $data);
        $this->assertSame('Asia/Kabul', $data['kabul_timezone']);
        $this->assertSame(270, $data['kabul_offset_minutes']);
    }

    public function test_boundaries_returns_year_month_boundaries(): void
    {
        $actor = $this->personWithAuthority('cal-bound-1', ['academic.structure']);
        $response = $this->actingAs($this->userForActor($actor))->getJson('/api/v1/calendar/boundaries?shamsi_year=1404&shamsi_month=12');
        $response->assertOk();
        $data = $response->json('data');
        $this->assertSame('2025-03-21', $data['year_boundaries']['starts_on']);
        $this->assertSame('2026-03-21', $data['year_boundaries']['starts_on_exclusive_end']);
        $this->assertSame(29, $data['month_boundaries']['length']); // 1404 common year Hut 29
        $this->assertSame('Asia/Kabul', $data['kabul_timezone']);
    }

    public function test_kabul_midnight_and_rollover_detection(): void
    {
        $authority = new CalendarAuthority();
        $kabulMidnight = \Carbon\CarbonImmutable::parse('2026-09-02 19:30:00', 'UTC');
        $this->assertTrue($authority->isKabulMidnight($kabulMidnight));
        $this->assertTrue($authority->isUtcMidnightRolloverRisk($kabulMidnight));

        $edge = \Carbon\CarbonImmutable::parse('2026-09-02 20:00:00', 'UTC'); // 00:30 Kabul next day
        $this->assertTrue($authority->isUtcMidnightRolloverRisk($edge));
        $this->assertSame('2026-09-02', $edge->toDateString());
        $this->assertSame('2026-09-03', $authority->gregorianCivilDayFromInstant($edge)->toDateString());
    }

}
