<?php

declare(strict_types=1);

namespace App\Modules\Organization\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Domain\OrganizationLifecycle;
use App\Modules\Organization\Domain\StructureChangeDefinition;
use App\Modules\Organization\Domain\UniqueTopologyName;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureDecision;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Errors\ValidationError;
use App\Support\Idempotency\IdempotentExecution;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Staged four-actor governance for topology changes. The synchronous
 * StructureDecision chain (initiator, distinct reviewer, two distinct
 * owners) cannot exist in one authenticated session, so every change is
 * captured as an append-only StructureChangeRequest and signed across
 * distinct sessions:
 *
 *   propose (initiate) -> review (review) -> owner signature x2 (approve),
 *   the second owner signature executing the canonical command in the same
 *   owning transaction. Rejection and withdrawal are terminal, auditable
 *   paths that never write a topology fact.
 *
 * The CreateStructureUnit / RenameStructureUnit / TransitionStructureUnit /
 * TransferBranchToCampus commands remain the business authority at
 * execution; this workflow only stages the required signatures and scope
 * evidence.
 */
final class GovernStructureChange
{
    public const OPERATION_PROPOSE = 'organization.structure.change.propose';

    public const OPERATION_REVIEW = 'organization.structure.change.review';

    public const OPERATION_APPROVE = 'organization.structure.change.approve';

    public const OPERATION_REJECT = 'organization.structure.change.reject';

    public const OPERATION_WITHDRAW = 'organization.structure.change.withdraw';

    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
        private readonly CreateStructureUnit $createCommand,
        private readonly RenameStructureUnit $renameCommand,
        private readonly TransitionStructureUnit $transitionCommand,
        private readonly TransferBranchToCampus $transferCommand,
        private readonly UniqueTopologyName $uniqueNames,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{request_id: string, lifecycle_state: string, correlation_id: string}
     */
    public function propose(Actor $initiator, string $changeType, array $input, string $idempotencyKey): array
    {
        $definition = StructureChangeDefinition::fromInput($changeType, $input);

        try {
            return $this->idempotency->execute(
                self::OPERATION_PROPOSE,
                $idempotencyKey,
                $this->payloadHash(self::OPERATION_PROPOSE, $definition->changeKey(), $initiator->actorId),
                fn (): array => DB::transaction(function () use ($initiator, $definition): array {
                    $this->requireCapability($initiator, StructureDecision::CAPABILITY_INITIATE, $definition, 'organization.structure.initiator_denied');
                    $this->requireNoDuplicate($definition);

                    if (StructureChangeRequest::query()
                        ->where('change_key', $definition->changeKey())
                        ->whereIn('lifecycle_state', StructureChangeRequest::OPEN_STATES)
                        ->lockForUpdate()
                        ->exists()) {
                        throw BusinessRejection::forCode('organization.change_already_open', 'an open proposal already exists for this change');
                    }

                    $request = new StructureChangeRequest;
                    $request->forceFill(array_merge($definition->toRecord(), [
                        'id' => RandomIdentifier::new(),
                        'lifecycle_state' => StructureChangeRequest::STATE_PROPOSED,
                        'proposed_by' => $initiator->actorId,
                    ]));
                    try {
                        $request->save();
                    } catch (UniqueConstraintViolationException) {
                        throw BusinessRejection::forCode('organization.change_already_open', 'an open proposal already exists for this change');
                    }

                    $event = $this->audit->record(
                        $initiator->actorId,
                        self::OPERATION_PROPOSE,
                        'structure_change_request',
                        $request->id,
                        null,
                        $this->proposalEvidence($request, $definition),
                    );

                    return $this->requestOutcome($request, $event->correlation_id);
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $initiator, self::OPERATION_PROPOSE, 'structure_change_request', '');
        }
    }

    /** @return array{request_id: string, lifecycle_state: string, correlation_id: string} */
    public function review(Actor $reviewer, StructureChangeRequest $request, string $idempotencyKey): array
    {
        try {
            return $this->idempotency->execute(
                self::OPERATION_REVIEW,
                $idempotencyKey,
                $this->payloadHash(self::OPERATION_REVIEW, $request->id, $reviewer->actorId),
                fn (): array => DB::transaction(function () use ($reviewer, $request): array {
                    /** @var StructureChangeRequest $locked */
                    $locked = StructureChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                    $definition = StructureChangeDefinition::fromRecord($locked);
                    if ($locked->lifecycle_state !== StructureChangeRequest::STATE_PROPOSED) {
                        throw BusinessRejection::forCode('organization.change_state', sprintf('the proposal is %s; only proposed changes accept review', $locked->lifecycle_state));
                    }
                    if (trim((string) $locked->proposed_by) === $reviewer->actorId) {
                        throw AuthorizationDenied::forCode('organization.structure.single_actor', 'the reviewer must differ from the initiator');
                    }
                    $this->requireCapability($reviewer, StructureDecision::CAPABILITY_REVIEW, $definition, 'organization.structure.reviewer_denied');

                    $locked->forceFill([
                        'lifecycle_state' => StructureChangeRequest::STATE_REVIEWED,
                        'reviewed_by' => $reviewer->actorId,
                    ]);
                    $locked->save();
                    $event = $this->audit->record(
                        $reviewer->actorId,
                        self::OPERATION_REVIEW,
                        'structure_change_request',
                        $locked->id,
                        ['lifecycle_state' => StructureChangeRequest::STATE_PROPOSED],
                        ['lifecycle_state' => StructureChangeRequest::STATE_REVIEWED, 'reviewed_by' => $reviewer->actorId],
                    );

                    return $this->requestOutcome($locked, $event->correlation_id);
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $reviewer, self::OPERATION_REVIEW, 'structure_change_request', $request->id);
        }
    }

    /**
     * Owner signature. The first distinct owner records the slot while the
     * request stays reviewed; the second distinct owner executes the
     * canonical topology command and closes the request as executed.
     *
     * @return array{request_id: string, lifecycle_state: string, correlation_id: string, result?: array<string, mixed>}
     */
    public function approve(Actor $owner, StructureChangeRequest $request, string $idempotencyKey): array
    {
        try {
            return $this->idempotency->execute(
                self::OPERATION_APPROVE,
                $idempotencyKey,
                $this->payloadHash(self::OPERATION_APPROVE, $request->id.'|'.($request->owner_one_id === null ? '1' : '2'), $owner->actorId),
                fn (): array => DB::transaction(function () use ($owner, $request): array {
                    /** @var StructureChangeRequest $locked */
                    $locked = StructureChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                    $definition = StructureChangeDefinition::fromRecord($locked);
                    if ($locked->lifecycle_state !== StructureChangeRequest::STATE_REVIEWED) {
                        throw BusinessRejection::forCode('organization.change_state', sprintf('the proposal must be reviewed before owner approval; it is %s', $locked->lifecycle_state));
                    }
                    foreach ([$locked->proposed_by, $locked->reviewed_by, $locked->owner_one_id] as $existing) {
                        if ($existing !== null && trim((string) $existing) === $owner->actorId) {
                            throw AuthorizationDenied::forCode('organization.structure.single_actor', 'owner approval must come from a distinct actor outside the initiator, reviewer and first owner');
                        }
                    }
                    $this->requireCapability($owner, StructureDecision::CAPABILITY_APPROVE, $definition, 'organization.structure.owner_denied');

                    if ($locked->owner_one_id === null) {
                        $locked->forceFill(['owner_one_id' => $owner->actorId]);
                        $locked->save();
                        $event = $this->audit->record(
                            $owner->actorId,
                            self::OPERATION_APPROVE,
                            'structure_change_request',
                            $locked->id,
                            null,
                            ['slot' => 'owner_one', 'owner_one_id' => $owner->actorId],
                        );

                        return $this->requestOutcome($locked, $event->correlation_id);
                    }

                    $result = $this->execute($locked, $definition, $owner);
                    $locked->forceFill([
                        'owner_two_id' => $owner->actorId,
                        'lifecycle_state' => StructureChangeRequest::STATE_EXECUTED,
                        'result' => $result,
                    ]);
                    $locked->save();
                    $event = $this->audit->record(
                        $owner->actorId,
                        'organization.structure.change.execute',
                        'structure_change_request',
                        $locked->id,
                        ['lifecycle_state' => StructureChangeRequest::STATE_REVIEWED],
                        ['lifecycle_state' => StructureChangeRequest::STATE_EXECUTED, 'owner_two_id' => $owner->actorId, 'result' => $result],
                    );

                    return array_merge($this->requestOutcome($locked, $event->correlation_id), ['result' => $result]);
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $owner, self::OPERATION_APPROVE, 'structure_change_request', $request->id);
        }
    }

    /** @return array{request_id: string, lifecycle_state: string, correlation_id: string} */
    public function reject(Actor $actor, StructureChangeRequest $request, ?string $reason, string $idempotencyKey): array
    {
        try {
            return $this->idempotency->execute(
                self::OPERATION_REJECT,
                $idempotencyKey,
                $this->payloadHash(self::OPERATION_REJECT, $request->id, $actor->actorId),
                fn (): array => DB::transaction(function () use ($actor, $request, $reason): array {
                    /** @var StructureChangeRequest $locked */
                    $locked = StructureChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                    $definition = StructureChangeDefinition::fromRecord($locked);
                    $trimmedReason = trim((string) $reason);
                    if ($trimmedReason === '') {
                        throw ValidationError::forCode('organization.change_reason_required', 'a rejection reason is required');
                    }

                    if ($locked->lifecycle_state === StructureChangeRequest::STATE_PROPOSED) {
                        if (trim((string) $locked->proposed_by) === $actor->actorId) {
                            throw AuthorizationDenied::forCode('organization.structure.single_actor', 'the initiator cannot also reject their own proposal; withdraw it instead');
                        }
                        $this->requireCapability($actor, StructureDecision::CAPABILITY_REVIEW, $definition, 'organization.structure.reviewer_denied');
                    } elseif ($locked->lifecycle_state === StructureChangeRequest::STATE_REVIEWED) {
                        foreach ([$locked->proposed_by, $locked->reviewed_by] as $existing) {
                            if (trim((string) $existing) === $actor->actorId) {
                                throw AuthorizationDenied::forCode('organization.structure.single_actor', 'an owner rejecting a reviewed proposal must differ from the initiator and reviewer');
                            }
                        }
                        $this->requireCapability($actor, StructureDecision::CAPABILITY_APPROVE, $definition, 'organization.structure.owner_denied');
                    } else {
                        throw BusinessRejection::forCode('organization.change_state', sprintf('only open proposals can be rejected; it is %s', $locked->lifecycle_state));
                    }

                    $locked->forceFill([
                        'lifecycle_state' => StructureChangeRequest::STATE_REJECTED,
                        'closed_by' => $actor->actorId,
                        'closure_reason' => $trimmedReason,
                    ]);
                    $locked->save();
                    $event = $this->audit->record(
                        $actor->actorId,
                        self::OPERATION_REJECT,
                        'structure_change_request',
                        $locked->id,
                        null,
                        ['lifecycle_state' => StructureChangeRequest::STATE_REJECTED, 'closed_by' => $actor->actorId, 'reason' => $trimmedReason],
                    );

                    return $this->requestOutcome($locked, $event->correlation_id);
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, self::OPERATION_REJECT, 'structure_change_request', $request->id);
        }
    }

    /** @return array{request_id: string, lifecycle_state: string, correlation_id: string} */
    public function withdraw(Actor $initiator, StructureChangeRequest $request, string $idempotencyKey): array
    {
        try {
            return $this->idempotency->execute(
                self::OPERATION_WITHDRAW,
                $idempotencyKey,
                $this->payloadHash(self::OPERATION_WITHDRAW, $request->id, $initiator->actorId),
                fn (): array => DB::transaction(function () use ($initiator, $request): array {
                    /** @var StructureChangeRequest $locked */
                    $locked = StructureChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
                    if (trim((string) $locked->proposed_by) !== $initiator->actorId) {
                        throw AuthorizationDenied::forCode('organization.change_withdraw_denied', 'only the initiator may withdraw a proposal');
                    }
                    if ($locked->lifecycle_state !== StructureChangeRequest::STATE_PROPOSED) {
                        throw BusinessRejection::forCode('organization.change_state', sprintf('a reviewed proposal cannot be withdrawn; it is %s', $locked->lifecycle_state));
                    }

                    $locked->forceFill([
                        'lifecycle_state' => StructureChangeRequest::STATE_WITHDRAWN,
                        'closed_by' => $initiator->actorId,
                    ]);
                    $locked->save();
                    $event = $this->audit->record(
                        $initiator->actorId,
                        self::OPERATION_WITHDRAW,
                        'structure_change_request',
                        $locked->id,
                        null,
                        ['lifecycle_state' => StructureChangeRequest::STATE_WITHDRAWN],
                    );

                    return $this->requestOutcome($locked, $event->correlation_id);
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $initiator, self::OPERATION_WITHDRAW, 'structure_change_request', $request->id);
        }
    }

    /**
     * Executes the canonical topology command with the four signed actors.
     * Every command re-authorizes and re-validates under its own row locks;
     * a failure rolls the signature and every partial fact back together.
     *
     * @return array<string, mixed>
     */
    private function execute(StructureChangeRequest $request, StructureChangeDefinition $definition, Actor $secondOwner): array
    {
        $decision = new StructureDecision(
            $this->actorFor(trim((string) $request->proposed_by)),
            $this->actorFor(trim((string) $request->reviewed_by)),
            [$this->actorFor(trim((string) $request->owner_one_id)), $secondOwner],
        );
        $derivedKey = 'structure-change:'.$request->id;

        $outcome = match ($request->change_type) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => $this->createCommand->createOrganization($decision, (string) $definition->payload['name'], $derivedKey),
            StructureChangeRequest::TYPE_CREATE_CAMPUS => $this->createCommand->createCampus($decision, (string) $request->parent_scope_id, (string) $definition->payload['name'], $derivedKey),
            StructureChangeRequest::TYPE_CREATE_BRANCH => $this->createCommand->createBranch($decision, (string) $request->parent_scope_id, (string) $definition->payload['name'], CarbonImmutable::parse((string) $definition->payload['effective_from']), $derivedKey),
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => $this->createCommand->createDepartment($decision, (string) $request->parent_scope_type, (string) $request->parent_scope_id, (string) $definition->payload['name'], $derivedKey),
            StructureChangeRequest::TYPE_RENAME => $this->renameCommand->rename($definition->targetUnit(), (string) $definition->payload['new_name'], $decision, $derivedKey),
            StructureChangeRequest::TYPE_TRANSITION => $this->executeTransition($definition, $decision, $derivedKey),
            StructureChangeRequest::TYPE_TRANSFER => $this->transferCommand->transfer(
                $this->branch($request->target_id),
                $definition->destinationCampus(),
                $definition->effectiveFrom(),
                $decision,
                $derivedKey,
            ),
            default => throw ValidationError::forCode('organization.change_type_unknown', $request->change_type),
        };

        return $outcome;
    }

    /**
     * @return array<string, mixed>
     */
    private function executeTransition(StructureChangeDefinition $definition, StructureDecision $decision, string $derivedKey): array
    {
        $unit = $definition->targetUnit();
        $action = (string) $definition->payload['action'];

        return match ($action) {
            StructureChangeDefinition::ACTION_ACTIVATE => $this->transitionCommand->activate($unit, $decision, $derivedKey),
            StructureChangeDefinition::ACTION_SUSPEND => $this->transitionCommand->suspend($unit, $decision, $derivedKey),
            StructureChangeDefinition::ACTION_CLOSE => $this->transitionCommand->close($unit, $decision, $derivedKey),
            StructureChangeDefinition::ACTION_REOPEN => $this->transitionCommand->reopen($unit, $decision, $derivedKey),
            default => throw ValidationError::forCode('organization.lifecycle_action_unknown', sprintf('unknown lifecycle action %s', $action)),
        };
    }

    /**
     * Audit evidence for a proposal. The change identity is recorded in
     * full, but an organization_id that names a not-yet-active unit (a draft
     * root being activated) is degraded to unknown provenance: the outbox
     * guard only carries active-organization context, and the request row
     * itself remains the complete, immutable source record.
     *
     * @return array<string, mixed>
     */
    private function proposalEvidence(StructureChangeRequest $request, StructureChangeDefinition $definition): array
    {
        $evidence = $definition->toRecord();
        if ($evidence['organization_id'] !== null
            && ! Organization::query()->whereKey($evidence['organization_id'])->where('lifecycle_state', OrganizationLifecycle::STATE_ACTIVE)->exists()) {
            $evidence['organization_id'] = null;
        }

        return array_merge($evidence, ['description' => $request->description()]);
    }

    /**
     * Stage capability check across EVERY governing scope. Null scope is the
     * organization-root governance gate (creating a new organization);
     * cross-organization transfers must be authorized on both structures.
     */
    private function requireCapability(Actor $actor, string $capability, StructureChangeDefinition $definition, string $denialCode): void
    {
        foreach ($definition->governingScopes() as $scope) {
            $outcome = $this->access->decide($actor, $capability, $scope);
            if (! $outcome->allowed) {
                throw AuthorizationDenied::forCode($denialCode, $outcome->reason);
            }
        }
    }

    /**
     * Friendly early duplicate-topology refusal; the canonical command
     * re-checks the same rule under row locks at execution and the database
     * unique indexes remain the hard authority.
     */
    private function requireNoDuplicate(StructureChangeDefinition $definition): void
    {
        if ($definition->changeType === StructureChangeRequest::TYPE_TRANSITION
            || $definition->changeType === StructureChangeRequest::TYPE_TRANSFER) {
            return;
        }

        if ($definition->changeType === StructureChangeRequest::TYPE_CREATE_ORGANIZATION) {
            $this->uniqueNames->requireAvailable('organization', (string) $definition->payload['name']);

            return;
        }
        if ($definition->changeType === StructureChangeRequest::TYPE_CREATE_CAMPUS) {
            $this->uniqueNames->requireAvailable('campus', (string) $definition->payload['name'], 'organization', $definition->organizationId);

            return;
        }
        if ($definition->changeType === StructureChangeRequest::TYPE_CREATE_BRANCH) {
            $this->uniqueNames->requireAvailable('branch', (string) $definition->payload['name']);

            return;
        }
        if ($definition->changeType === StructureChangeRequest::TYPE_CREATE_DEPARTMENT) {
            $this->uniqueNames->requireAvailable('department', (string) $definition->payload['name'], $definition->parentScopeType, $definition->parentScopeId);

            return;
        }

        // Rename: scope context comes from the existing target unit.
        $unit = $definition->targetUnit();
        if ($unit instanceof Campus) {
            $this->uniqueNames->requireAvailable('campus', (string) $definition->payload['new_name'], 'organization', $unit->organization_id, $unit->id);

            return;
        }
        if ($unit instanceof Department) {
            $this->uniqueNames->requireAvailable('department', (string) $definition->payload['new_name'], $unit->scope_type, $unit->scope_id, $unit->id);

            return;
        }
        $this->uniqueNames->requireAvailable($unit->unitType(), (string) $definition->payload['new_name'], null, null, $unit->unitId());
    }

    private function payloadHash(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    /** @return array{request_id: string, lifecycle_state: string, correlation_id: string} */
    private function requestOutcome(StructureChangeRequest $request, string $correlationId): array
    {
        return [
            'request_id' => $request->id,
            'lifecycle_state' => $request->lifecycle_state,
            'correlation_id' => $correlationId,
        ];
    }

    private function actorFor(string $actorId): Actor
    {
        /** @var Person|null $person */
        $person = Person::query()->whereKey($actorId)->first();

        return new Actor($actorId, $person === null ? $actorId : $person->legal_name);
    }

    private function branch(?string $branchId): Branch
    {
        /** @var Branch $branch */
        $branch = Branch::query()->whereKey($branchId)->firstOrFail();

        return $branch;
    }
}
