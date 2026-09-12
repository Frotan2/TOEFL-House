<?php

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\Program;
use App\Modules\Finance\Commands\MaintainFinancialPeriod;
use App\Modules\Payroll\Commands\MaintainPayrollPeriod;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * HTTP contract for GET /api/v1/reporting/workspace.
 *
 * Regression for a hard 500: the workspace projected financial/payroll
 * periods selecting name/starts_on/ends_on, columns that exist only on
 * academic_periods. The canonical Finance/Payroll period schema is
 * period_key + date_from/date_to (migrations 000051/000058), which is also
 * what the frontend period selector binds to. These tests prove the
 * authorization boundary (real session middleware + AccessDecision) and
 * the period projection over the real HTTP stack.
 */
final class ReportingWorkspaceApiTest extends TestCase
{
    use BuildsActors;

    private function openFinancialPeriod(string $key, string $from, string $to, string $idempotencyKey): void
    {
        $clerk = $this->grantedActor('rw-fin-clerk', ['finance.period']);
        app(MaintainFinancialPeriod::class)->open($clerk, $key, $from, $to, $idempotencyKey);
    }

    private function openPayrollPeriod(string $key, string $from, string $to, string $idempotencyKey): void
    {
        $clerk = $this->grantedActor('rw-pay-clerk', ['payroll.period']);
        app(MaintainPayrollPeriod::class)->open($clerk, $key, $from, $to, $idempotencyKey);
    }

    private function publishAcademicPeriod(): void
    {
        $officer = $this->academicOfficer('rw-acad-officer');
        $program = app(MaintainAcademicStructure::class)->defineProgram($officer, 'Reporting API Program', 'rw-prog');
        $version = app(MaintainAcademicStructure::class)->publishVersion(
            $officer,
            Program::query()->findOrFail($program['program_id']),
            'v1',
            'rw-ver',
        );
        $period = app(MaintainAcademicStructure::class)->definePeriod(
            $officer,
            'Reporting API Term',
            new CarbonImmutable('2026-09-01'),
            new CarbonImmutable('2026-12-31'),
            'rw-period',
        );
        app(MaintainAcademicStructure::class)->transitionPeriod(
            $officer,
            AcademicPeriod::query()->findOrFail($period['period_id']),
            'published',
            'rw-period-pub',
        );
        // Version is published so it cannot be mistaken for an unused local.
        $this->assertNotEmpty($version['version_id']);
    }

    public function test_unauthenticated_request_is_refused_by_the_session_guard(): void
    {
        $this->getJson('/api/v1/reporting/workspace')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'authentication_required');
    }

    public function test_authenticated_actor_without_reporting_run_is_denied_by_access_decision(): void
    {
        // An active employee account with NO reporting capability: the
        // request passes authentication but must fail the canonical
        // organization-read gate, never reach the period queries.
        $person = $this->personWithAuthority('rw-no-grant', []);

        $this->actingAs($this->userForActor($person))
            ->getJson('/api/v1/reporting/workspace')
            ->assertForbidden()
            ->assertJsonPath('category', 'authorization')
            ->assertJsonPath('error', 'api.organization_read_denied');
    }

    public function test_authorized_workspace_projects_canonical_period_columns(): void
    {
        $this->openFinancialPeriod('2026-11', '2026-11-01', '2026-11-30', 'rw-fin-1');
        $this->openFinancialPeriod('2026-10', '2026-10-01', '2026-10-31', 'rw-fin-2');
        $this->openPayrollPeriod('2026-11-P', '2026-11-01', '2026-11-30', 'rw-pay-1');
        $this->publishAcademicPeriod();

        $person = $this->personWithAuthority('rw-analyst', ['reporting.run']);
        $response = $this->actingAs($this->userForActor($person))
            ->getJson('/api/v1/reporting/workspace');

        $response->assertOk();
        $data = $response->json('data');

        // The envelope the React console binds to is unchanged.
        foreach (['metrics', 'runs', 'dashboards', 'dashboard_organizations', 'periods'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        foreach (['academic', 'financial', 'payroll'] as $authority) {
            $this->assertArrayHasKey($authority, $data['periods']);
        }

        // Finance/Payroll periods carry the canonical schema only — no
        // hallucinated academic columns and no compatibility aliases.
        $financial = collect($data['periods']['financial'])->keyBy('period_key');
        $this->assertTrue($financial->has('2026-10') && $financial->has('2026-11'));
        $november = $financial->get('2026-11');
        $this->assertSame('2026-11-01', $november['date_from']);
        $this->assertSame('2026-11-30', $november['date_to']);
        $this->assertSame('open', $november['lifecycle_state']);
        $this->assertArrayNotHasKey('starts_on', $november);
        $this->assertArrayNotHasKey('ends_on', $november);
        $this->assertArrayNotHasKey('name', $november);

        $payroll = collect($data['periods']['payroll'])->keyBy('period_key');
        $payNovember = $payroll->get('2026-11-P');
        $this->assertSame('2026-11-01', $payNovember['date_from']);
        $this->assertSame('2026-11-30', $payNovember['date_to']);
        $this->assertArrayNotHasKey('starts_on', $payNovember);
        $this->assertArrayNotHasKey('ends_on', $payNovember);

        // date_from ordering: the October key precedes the November key.
        $this->assertSame(['2026-10', '2026-11'], array_column($data['periods']['financial'], 'period_key'));

        // Academic selector is untouched and keeps its real columns.
        $academic = $data['periods']['academic'][0];
        $this->assertSame('Reporting API Term', $academic['name']);
        $this->assertSame('2026-09-01', $academic['starts_on']);
        $this->assertSame('2026-12-31', $academic['ends_on']);
        $this->assertArrayNotHasKey('date_from', $academic);

        // The selector value for a financial/payroll metric is period_key,
        // exactly what MetricCatalog::resolvePeriod() looks up under the
        // financial_period/payroll_period authorities.
        $this->assertContains('2026-11', array_column($data['periods']['financial'], 'period_key'));
        $this->assertContains('2026-11-P', array_column($data['periods']['payroll'], 'period_key'));
    }
}
