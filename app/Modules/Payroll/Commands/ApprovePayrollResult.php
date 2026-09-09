<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Hr\Models\Employment;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Modules\Payroll\Domain\PayrollLifecycle;
use App\Modules\Payroll\Models\PayrollCalculation;
use App\Modules\Payroll\Models\PayrollResult;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;

/**
 * Approves immutable Payroll payable evidence. Payroll does not create,
 * correct, reverse, or post monetary facts; Finance is the sole authority for
 * those operations.
 */
final class ApprovePayrollResult
{
    public const CAPABILITY_APPROVE = 'payroll.approve';

    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
    ) {}

    /** @return array{result_id: string, correlation_id: string} */
    public function approve(Actor $approver, PayrollCalculation $calculation, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', ['payroll.result.approve', $calculation->id, $approver->actorId]));

        try {
            return $this->idempotency->execute('payroll.result.approve', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($approver, $calculation): array {
                    /** @var PayrollCalculation $locked */
                    $locked = PayrollCalculation::query()->whereKey($calculation->id)->lockForUpdate()->firstOrFail();
                    if ($locked->lifecycle_state !== PayrollLifecycle::CALC_PREPARED) {
                        throw BusinessRejection::forCode('payroll.calculation_not_prepared', 'only a prepared calculation can be approved');
                    }
                    if (trim((string) $locked->prepared_by) === $approver->actorId) {
                        throw AuthorizationDenied::forCode('payroll.approval_not_independent', 'the approver must differ from the preparer');
                    }
                    /** @var Employment $employment */
                    $employment = Employment::query()->findOrFail($locked->employment_id);
                    if (trim((string) $employment->person_id) === $approver->actorId) {
                        throw AuthorizationDenied::forCode('payroll.beneficiary', 'the beneficiary may never approve their own payroll');
                    }
                    /** @var Person|null $person */
                    $person = Person::query()->whereKey($employment->person_id)->first();
                    $originatingBranchId = $person !== null ? trim((string) ($person->home_branch_id ?? '')) : '';
                    $branch = $originatingBranchId === '' ? null : Branch::query()->whereKey($originatingBranchId)->first();
                    if ($branch === null || $branch->lifecycle_state !== 'active' || $branch->structureScope()->organizationId === '') {
                        throw BusinessRejection::forCode('payroll.branch_provenance_missing', 'an approved Payroll result requires an active employee home branch snapshot with organization provenance');
                    }
                    $scope = $branch->structureScope();
                    $this->require($approver, self::CAPABILITY_APPROVE, $scope);

                    $result = PayrollResult::query()->create([
                        'id' => RandomIdentifier::new(),
                        'calculation_id' => $locked->id,
                        'period_id' => $locked->period_id,
                        'employment_id' => $locked->employment_id,
                        'originating_branch_id' => $branch->id,
                        'amount' => $locked->base_amount,
                        'lifecycle_state' => 'approved',
                        'approved_by' => $approver->actorId,
                    ]);
                    $locked->forceFill(['lifecycle_state' => PayrollLifecycle::CALC_RESULTED]);
                    $locked->save();
                    $event = $this->audit->record($approver->actorId, 'payroll.result.approve', 'payroll_result', $result->id, null, [
                        'calculation_id' => $locked->id, 'amount' => $result->amount, 'originating_branch_id' => $branch->id,
                        'branch_id' => $branch->id, 'organization_id' => $scope->organizationId,
                        'monetary_authority' => 'finance',
                    ]);

                    return ['result_id' => $result->id, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $approver, 'payroll.result.approve', 'payroll_result', $calculation->id);
        }
    }

    private function require(Actor $actor, string $capability, ?StructureScope $scope = null): void
    {
        $outcome = $this->access->decide($actor, $capability, $scope);
        if (! $outcome->allowed) {
            throw AuthorizationDenied::forCode('payroll.approve_denied', $outcome->reason);
        }
    }
}
