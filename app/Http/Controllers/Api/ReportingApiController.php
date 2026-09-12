<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Organization\Models\Branch;
use App\Modules\Reporting\Commands\MaintainDashboard;
use App\Modules\Reporting\Commands\RunReport;
use App\Modules\Reporting\Domain\MetricCatalog;
use App\Modules\Reporting\Models\Dashboard;
use App\Modules\Reporting\Models\MetricDefinition;
use App\Modules\Reporting\Models\ReportRun;
use App\Modules\Students\Models\Student;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** JSON interface for source-linked reporting and dashboard projections. */
final class ReportingApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $this->requireOrganizationRead('reporting.run', 'reporting.api.workspace');

        $metrics = $this->resolvedMetrics();
        $metricIds = $metrics->pluck('id')->all();
        $dashboardOrganizationIds = $this->authorizedOrganizations('reporting.dashboard');
        $runOrganizationIds = $this->authorizedOrganizations('reporting.run');
        $branchOrganizations = [];

        foreach (Branch::query()->whereIn('id', $this->authorizedBranches('reporting.run'))->get() as $branch) {
            try {
                $scope = $branch->structureScope();
            } catch (ModelNotFoundException) {
                continue;
            }
            $organizationId = trim((string) $scope->organizationId);
            if ($organizationId !== '') {
                $branchOrganizations[(string) $branch->id] = $organizationId;
            }
        }

        $studentOrganizations = Student::query()
            ->where(function ($query) use ($branchOrganizations): void {
                $branchIds = array_keys($branchOrganizations);
                if ($branchIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }
                $query->whereIn('current_home_branch_id', $branchIds)
                    ->orWhere(function ($query) use ($branchIds): void {
                        $query->whereNull('current_home_branch_id')->whereIn('originating_branch_id', $branchIds);
                    });
            })
            ->get(['id', 'current_home_branch_id', 'originating_branch_id'])
            ->mapWithKeys(static function (Student $student) use ($branchOrganizations): array {
                $branchId = trim((string) ($student->current_home_branch_id ?: $student->originating_branch_id));

                return isset($branchOrganizations[$branchId]) ? [(string) $student->id => $branchOrganizations[$branchId]] : [];
            })
            ->all();

        $classOrganizations = ClassModel::query()
            ->whereIn('branch_id', array_keys($branchOrganizations))
            ->get(['id', 'branch_id'])
            ->mapWithKeys(static function (ClassModel $class) use ($branchOrganizations): array {
                $branchId = trim((string) $class->branch_id);

                return isset($branchOrganizations[$branchId]) ? [(string) $class->id => $branchOrganizations[$branchId]] : [];
            })
            ->all();

        $scopedOrganizations = [
            'branch' => $branchOrganizations,
            'student' => $studentOrganizations,
            'class' => $classOrganizations,
        ];

        $runs = ReportRun::query()
            ->leftJoin('metric_versions as mv', 'mv.id', '=', 'report_runs.metric_version_id')
            ->leftJoin('metric_definitions as md', 'md.id', '=', 'mv.metric_id')
            ->where(function ($visible) use ($scopedOrganizations, $runOrganizationIds): void {
                $firstScope = true;
                foreach ($scopedOrganizations as $scopeType => $targets) {
                    foreach ($targets as $scopeId => $organizationId) {
                        $method = $firstScope ? 'where' : 'orWhere';
                        $visible->{$method}(function ($pair) use ($scopeType, $scopeId, $organizationId): void {
                            $pair->where('report_runs.scope_type', $scopeType)
                                ->where('report_runs.scope_id', $scopeId)
                                ->where('report_runs.organization_id', $organizationId);
                        });
                        $firstScope = false;
                    }
                }
                if ($runOrganizationIds !== []) {
                    $method = $firstScope ? 'where' : 'orWhere';
                    $visible->{$method}(function ($fund) use ($runOrganizationIds): void {
                        $fund->where('report_runs.scope_type', 'fund')
                            ->whereIn('report_runs.organization_id', $runOrganizationIds)
                            ->whereExists(function ($source): void {
                                $source->selectRaw('1')
                                    ->from('funding_sources as fs')
                                    ->whereColumn('fs.id', 'report_runs.scope_id')
                                    ->whereColumn('fs.organization_id', 'report_runs.organization_id');
                            });
                    });
                    $firstScope = false;
                }
                if ($firstScope) {
                    $visible->whereRaw('1 = 0');
                }
            })
            ->whereIn('md.id', $metricIds)
            ->select('report_runs.*', 'md.key as metric_key', 'md.name as metric_name')
            ->orderByDesc('report_runs.created_at')
            ->limit(200)
            ->get();

        $dashboards = Dashboard::query()
            ->whereIn('organization_id', $dashboardOrganizationIds)
            ->orderBy('name')
            ->get();

        // Authoritative period selectors — no free-text period keys
        $academicPeriods = DB::table('academic_periods')
            ->select(['id', 'name', 'starts_on', 'ends_on', 'lifecycle_state'])
            ->orderBy('starts_on')
            ->limit(100)
            ->get();
        // financial_periods/payroll_periods (migrations 000051/000058) are
        // keyed periods bounded by date_from/date_to — only academic_periods
        // has name/starts_on/ends_on. Selecting those columns here 500s with
        // UndefinedColumn; the option value is still period_key, resolved by
        // MetricCatalog::resolvePeriod when a report is run.
        $financialPeriods = DB::table('financial_periods')
            ->select(['id', 'period_key', 'date_from', 'date_to', 'lifecycle_state'])
            ->orderBy('date_from')
            ->limit(100)
            ->get();
        $payrollPeriods = DB::table('payroll_periods')
            ->select(['id', 'period_key', 'date_from', 'date_to', 'lifecycle_state'])
            ->orderBy('date_from')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'metrics' => $metrics->map(static fn (MetricDefinition $metric): array => [
                    'id' => (string) $metric->id,
                    'key' => (string) $metric->key,
                    'name' => (string) $metric->name,
                    'current_version' => (int) $metric->current_version,
                    'period_authority' => $metric->period_authority ?? null,
                ])->values()->all(),
                'runs' => $runs->map(static fn (ReportRun $run): array => [
                    'id' => (string) $run->id,
                    'metric_key' => $run->metric_key,
                    'metric_name' => $run->metric_name,
                    'period_key' => (string) $run->period_key,
                    'scope_type' => (string) $run->scope_type,
                    'scope_id' => $run->scope_id !== null ? (string) $run->scope_id : null,
                    'organization_id' => $run->organization_id !== null ? (string) $run->organization_id : null,
                    'result' => $run->completeness === 'complete' ? $run->result : null,
                    'completeness' => $run->completeness,
                    'reproducibility_hash' => (string) $run->reproducibility_hash,
                    'created_at' => $run->created_at?->toISOString(),
                    'evidence_status' => $run->completeness === 'complete'
                        ? 'complete'
                        : ($run->completeness === 'incomplete' ? 'incomplete' : 'historic_unclassified'),
                ])->values()->all(),
                'dashboards' => $dashboards->map(static fn (Dashboard $dashboard): array => [
                    'id' => (string) $dashboard->id,
                    'name' => (string) $dashboard->name,
                    'organization_id' => (string) $dashboard->organization_id,
                ])->values()->all(),
                'dashboard_organizations' => array_values($dashboardOrganizationIds),
                'periods' => [
                    'academic' => $academicPeriods,
                    'financial' => $financialPeriods,
                    'payroll' => $payrollPeriods,
                ],
            ],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $input = $request->validate([
            'metric_key' => ['required', 'string', 'max:120'],
            'period_key' => ['required', 'string', 'max:60'],
            'scope_type' => ['required', 'string', 'max:40'],
            'scope_id' => ['nullable', 'string'],
        ]);

        $result = app(RunReport::class)->run(
            $this->actor(),
            $input['metric_key'],
            $input['period_key'],
            $input['scope_type'],
            $input['scope_id'] !== null && $input['scope_id'] !== '' ? $input['scope_id'] : null,
            [],
            $this->idempotencyKey('reporting.run'),
        );

        return response()->json(['status' => 'recorded', 'result' => $result], 201);
    }

    public function createDashboard(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'organization_id' => ['nullable', 'string'],
        ]);

        $result = app(MaintainDashboard::class)->create(
            $this->actor(),
            $input['name'],
            $this->idempotencyKey('reporting.dashboard'),
            $input['organization_id'] ?? null,
        );

        return response()->json(['status' => 'created', 'result' => $result], 201);
    }

    public function pinDashboard(Request $request, string $dashboardId): JsonResponse
    {
        $input = $request->validate([
            'metric_key' => ['required', 'string', 'max:120'],
            'period_key' => ['required', 'string', 'max:60'],
            'scope_type' => ['required', 'string', 'max:40', 'not_in:global'],
            'scope_id' => ['nullable', 'string'],
        ]);

        $result = app(MaintainDashboard::class)->pin(
            $this->actor(),
            Dashboard::query()->findOrFail($dashboardId),
            $input['metric_key'],
            $input['period_key'],
            $input['scope_type'],
            $input['scope_id'] !== null && $input['scope_id'] !== '' ? $input['scope_id'] : null,
            $this->idempotencyKey('reporting.pin'),
        );

        return response()->json(['status' => 'pinned', 'result' => $result]);
    }

    /** @return Collection<int, MetricDefinition> */
    private function resolvedMetrics(): Collection
    {
        return MetricDefinition::query()->orderBy('key')->get()->filter(static function (MetricDefinition $metric): bool {
            try {
                MetricCatalog::assertDefinitionLineage($metric, MetricCatalog::entry((string) $metric->key));

                return true;
            } catch (BusinessRejection) {
                return false;
            }
        })->values();
    }
}
