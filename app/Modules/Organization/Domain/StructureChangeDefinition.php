<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\BusinessRejection;
use App\Support\Errors\ValidationError;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable description of one proposed topology change: what it changes,
 * under which governing scope(s) the four-actor chain must hold, and the
 * deterministic identity used to refuse duplicate open proposals.
 *
 * It validates topology semantics for the operator at proposal time; the
 * canonical commands remain authoritative and re-validate every rule under
 * row locks at execution.
 */
final class StructureChangeDefinition
{
    public const ACTION_ACTIVATE = 'activate';

    public const ACTION_SUSPEND = 'suspend';

    public const ACTION_CLOSE = 'close';

    public const ACTION_REOPEN = 'reopen';

    public const TRANSITION_ACTIONS = [
        self::ACTION_ACTIVATE,
        self::ACTION_SUSPEND,
        self::ACTION_CLOSE,
        self::ACTION_REOPEN,
    ];

    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $changeType,
        public readonly string $unitType,
        public readonly ?string $organizationId,
        public readonly ?string $targetId,
        public readonly ?string $parentScopeType,
        public readonly ?string $parentScopeId,
        public readonly array $payload,
    ) {}

    /**
     * Rebuilds the validated definition of a persisted request. Stage
     * signatures re-validate topology from the stored, immutable payload so a
     * parent that changed state between proposal and execution fails closed.
     */
    public static function fromRecord(StructureChangeRequest $request): self
    {
        $payload = $request->payload ?? [];

        return match ($request->change_type) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => self::fromInput($request->change_type, [
                'name' => $payload['name'] ?? '',
            ]),
            StructureChangeRequest::TYPE_CREATE_CAMPUS => self::fromInput($request->change_type, [
                'organization_id' => $request->parent_scope_id,
                'name' => $payload['name'] ?? '',
            ]),
            StructureChangeRequest::TYPE_CREATE_BRANCH => self::fromInput($request->change_type, [
                'campus_id' => $request->parent_scope_id,
                'name' => $payload['name'] ?? '',
                'effective_from' => $payload['effective_from'] ?? '',
            ]),
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => self::fromInput($request->change_type, [
                'scope_type' => $request->parent_scope_type,
                'scope_id' => $request->parent_scope_id,
                'name' => $payload['name'] ?? '',
            ]),
            StructureChangeRequest::TYPE_RENAME => self::fromInput($request->change_type, [
                'unit_type' => $request->unit_type,
                'unit_id' => $request->target_id,
                'new_name' => $payload['new_name'] ?? '',
            ]),
            StructureChangeRequest::TYPE_TRANSITION => self::fromInput($request->change_type, [
                'unit_type' => $request->unit_type,
                'unit_id' => $request->target_id,
                'action' => $payload['action'] ?? '',
            ]),
            StructureChangeRequest::TYPE_TRANSFER => self::fromInput($request->change_type, [
                'branch_id' => $request->target_id,
                'campus_id' => $payload['campus_id'] ?? '',
                'effective_from' => $payload['effective_from'] ?? '',
            ]),
            default => throw ValidationError::forCode('organization.change_type_unknown', sprintf('unknown structure change type %s', $request->change_type)),
        };
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(string $changeType, array $input): self
    {
        return match ($changeType) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => self::createOrganization($input),
            StructureChangeRequest::TYPE_CREATE_CAMPUS => self::createCampus($input),
            StructureChangeRequest::TYPE_CREATE_BRANCH => self::createBranch($input),
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => self::createDepartment($input),
            StructureChangeRequest::TYPE_RENAME => self::rename($input),
            StructureChangeRequest::TYPE_TRANSITION => self::transition($input),
            StructureChangeRequest::TYPE_TRANSFER => self::transfer($input),
            default => throw ValidationError::forCode('organization.change_type_unknown', sprintf('unknown structure change type %s', $changeType)),
        };
    }

    /** @param array<string, mixed> $input */
    private static function createOrganization(array $input): self
    {
        $name = self::name($input, 'name');

        return new self(
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION,
            'organization',
            null,
            null,
            null,
            null,
            ['name' => $name],
        );
    }

    /** @param array<string, mixed> $input */
    private static function createCampus(array $input): self
    {
        $name = self::name($input, 'name');
        $organizationId = self::id($input, 'organization_id');
        /** @var Organization $organization */
        $organization = self::organization($organizationId);
        self::requireActive($organization->lifecycle_state, 'organization');

        return new self(
            StructureChangeRequest::TYPE_CREATE_CAMPUS,
            'campus',
            $organization->id,
            null,
            'organization',
            $organization->id,
            ['name' => $name],
        );
    }

    /** @param array<string, mixed> $input */
    private static function createBranch(array $input): self
    {
        $name = self::name($input, 'name');
        $campusId = self::id($input, 'campus_id');
        /** @var Campus $campus */
        $campus = Campus::query()->whereKey($campusId)->firstOrFail();
        self::requireActive($campus->lifecycle_state, 'campus');
        $effectiveFrom = self::day($input, 'effective_from');

        return new self(
            StructureChangeRequest::TYPE_CREATE_BRANCH,
            'branch',
            $campus->organization_id,
            null,
            'campus',
            $campus->id,
            ['name' => $name, 'effective_from' => $effectiveFrom],
        );
    }

    /** @param array<string, mixed> $input */
    private static function createDepartment(array $input): self
    {
        $name = self::name($input, 'name');
        $scopeType = (string) ($input['scope_type'] ?? '');
        if (! in_array($scopeType, ['organization', 'campus', 'branch'], true)) {
            throw ValidationError::forCode('department.scope_type_unknown', 'department must belong to an organization, campus or branch');
        }
        $scopeId = self::id($input, 'scope_id');
        $scope = match ($scopeType) {
            'organization' => (function () use ($scopeId): StructureScope {
                $organization = self::organization($scopeId);
                self::requireActive($organization->lifecycle_state, 'organization');

                return new StructureScope($organization->id);
            })(),
            'campus' => (function () use ($scopeId): StructureScope {
                /** @var Campus $campus */
                $campus = Campus::query()->whereKey($scopeId)->firstOrFail();
                self::requireActive($campus->lifecycle_state, 'campus');

                return new StructureScope($campus->organization_id, $campus->id);
            })(),
            'branch' => (function () use ($scopeId): StructureScope {
                /** @var Branch $branch */
                $branch = Branch::query()->whereKey($scopeId)->firstOrFail();
                self::requireActive($branch->lifecycle_state, 'branch');
                if ($branch->activeCampusAssignment() === null) {
                    throw BusinessRejection::forCode('department.branch_without_campus', 'branch has no effective campus attribution');
                }

                return $branch->structureScope();
            })(),
        };

        return new self(
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT,
            'department',
            $scope->organizationId,
            null,
            $scopeType,
            $scopeId,
            ['name' => $name],
        );
    }

    /** @param array<string, mixed> $input */
    private static function rename(array $input): self
    {
        $unitType = self::unitType($input);
        $unit = self::unit($unitType, self::id($input, 'unit_id'));
        $newName = self::name($input, 'new_name');
        if ($newName === self::normalizeName($unit->unitName())) {
            throw BusinessRejection::forCode('organization.rename_no_change', 'new name equals the current name');
        }

        return new self(
            StructureChangeRequest::TYPE_RENAME,
            $unitType,
            self::organizationIdOf($unit),
            $unit->unitId(),
            null,
            null,
            ['new_name' => $newName],
        );
    }

    /** @param array<string, mixed> $input */
    private static function transition(array $input): self
    {
        $unitType = self::unitType($input);
        $unit = self::unit($unitType, self::id($input, 'unit_id'));
        $action = (string) ($input['action'] ?? '');
        if (! in_array($action, self::TRANSITION_ACTIONS, true)) {
            throw ValidationError::forCode('organization.lifecycle_action_unknown', sprintf('unknown lifecycle action %s', $action));
        }

        return new self(
            StructureChangeRequest::TYPE_TRANSITION,
            $unitType,
            self::organizationIdOf($unit),
            $unit->unitId(),
            null,
            null,
            ['action' => $action],
        );
    }

    /** @param array<string, mixed> $input */
    private static function transfer(array $input): self
    {
        $branchId = self::id($input, 'branch_id');
        /** @var Branch $branch */
        $branch = Branch::query()->whereKey($branchId)->firstOrFail();
        self::requireActive($branch->lifecycle_state, 'branch');
        $campusId = self::id($input, 'campus_id');
        /** @var Campus $campus */
        $campus = Campus::query()->whereKey($campusId)->firstOrFail();
        self::requireActive($campus->lifecycle_state, 'campus');

        $assignment = $branch->activeCampusAssignment();
        if ($assignment === null) {
            throw BusinessRejection::forCode('organization.branch_without_campus', 'branch has no open campus attribution');
        }
        if ($assignment->campus_id === $campus->id) {
            throw BusinessRejection::forCode('organization.transfer_same_campus', 'branch is already attributed to this campus');
        }

        return new self(
            StructureChangeRequest::TYPE_TRANSFER,
            'branch',
            $branch->structureScope()->organizationId,
            $branch->id,
            null,
            null,
            ['campus_id' => $campus->id, 'effective_from' => self::day($input, 'effective_from')],
        );
    }

    /**
     * The scope a command must authorize against for a change to an existing
     * unit. A DRAFT organization is pre-operational: it has no assigned
     * governors yet, so — exactly as for its creation — organization-root
     * platform authority governs it (null scope). Everything else resolves
     * through the unit's own structural scope.
     */
    public static function authorityScope(StructureUnit&Model $unit, bool $allowInactive): ?StructureScope
    {
        if ($unit instanceof Organization && $unit->lifecycleState() === OrganizationLifecycle::STATE_DRAFT) {
            return null;
        }
        $scope = $unit->structureScope();

        return $allowInactive ? $scope->withInactiveLifecycleAccess() : $scope;
    }

    /**
     * Scopes under which every participant of the governance chain must hold
     * the stage capability. A cross-organization branch transfer requires
     * authority on BOTH the source and destination structures.
     *
     * @return list<StructureScope|null>
     */
    public function governingScopes(): array
    {
        return match ($this->changeType) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => [null],
            StructureChangeRequest::TYPE_CREATE_CAMPUS => [new StructureScope((string) $this->organizationId)],
            StructureChangeRequest::TYPE_CREATE_BRANCH => self::campusScope((string) $this->parentScopeId),
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => [
                (new ResolvesStructureScope)->forDepartment(
                    (string) $this->parentScopeType,
                    (string) $this->parentScopeId,
                ),
            ],
            StructureChangeRequest::TYPE_RENAME => [self::authorityScope($this->targetUnit(), false)],
            StructureChangeRequest::TYPE_TRANSITION => [self::authorityScope($this->targetUnit(), true)],
            StructureChangeRequest::TYPE_TRANSFER => $this->transferScopes(),
            default => throw ValidationError::forCode('organization.change_type_unknown', $this->changeType),
        };
    }

    /** @return list<StructureScope> */
    private function transferScopes(): array
    {
        $scopes = [$this->targetUnit()->structureScope()];
        foreach (self::campusScope((string) $this->payload['campus_id']) as $destination) {
            $scopes[] = $destination;
        }
        $unique = [];
        foreach ($scopes as $scope) {
            $key = implode('|', [$scope->organizationId, $scope->campusId ?? '', $scope->branchId ?? '', $scope->departmentId ?? '']);
            $unique[$key] = $scope;
        }

        return array_values($unique);
    }

    /**
     * Scope used to place/filter the request in an operator's organization
     * view: the source organization for transfers, the resolved owner
     * organization otherwise; null only when creating an organization.
     */
    public function visibilityScope(): ?StructureScope
    {
        if ($this->organizationId === null) {
            return null;
        }

        return new StructureScope($this->organizationId);
    }

    public function targetUnit(): StructureUnit&Model
    {
        if ($this->targetId === null) {
            throw BusinessRejection::forCode('organization.change_without_target', 'this change does not target an existing unit');
        }

        return self::unit($this->unitType, $this->targetId);
    }

    public function destinationCampus(): Campus
    {
        /** @var Campus $campus */
        $campus = Campus::query()->whereKey((string) ($this->payload['campus_id'] ?? ''))->firstOrFail();

        return $campus;
    }

    public function effectiveFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse((string) ($this->payload['effective_from'] ?? ''))->startOfDay();
    }

    public function changeKey(): string
    {
        $parts = match ($this->changeType) {
            StructureChangeRequest::TYPE_CREATE_ORGANIZATION => [
                'create', 'organization', $this->payload['name'],
            ],
            StructureChangeRequest::TYPE_CREATE_CAMPUS => [
                'create', 'campus', 'organization', $this->parentScopeId, $this->payload['name'],
            ],
            StructureChangeRequest::TYPE_CREATE_BRANCH => [
                'create', 'branch', 'campus', $this->parentScopeId, $this->payload['name'],
            ],
            StructureChangeRequest::TYPE_CREATE_DEPARTMENT => [
                'create', 'department', $this->parentScopeType, $this->parentScopeId, $this->payload['name'],
            ],
            StructureChangeRequest::TYPE_RENAME => [
                'rename', $this->unitType, $this->targetId, $this->payload['new_name'],
            ],
            StructureChangeRequest::TYPE_TRANSITION => [
                'transition', $this->unitType, $this->targetId, $this->payload['action'],
            ],
            StructureChangeRequest::TYPE_TRANSFER => [
                'transfer', 'branch', $this->targetId, 'campus', $this->payload['campus_id'], $this->payload['effective_from'],
            ],
            default => throw ValidationError::forCode('organization.change_type_unknown', $this->changeType),
        };

        return implode(':', array_map(static fn ($part): string => self::normalizeName((string) $part), $parts));
    }

    /** @return array<string, mixed> */
    public function toRecord(): array
    {
        return [
            'change_type' => $this->changeType,
            'unit_type' => $this->unitType,
            'organization_id' => $this->organizationId,
            'target_id' => $this->targetId,
            'parent_scope_type' => $this->parentScopeType,
            'parent_scope_id' => $this->parentScopeId,
            'change_key' => $this->changeKey(),
            // The Eloquent model casts payload/result to jsonb; store arrays,
            // never pre-encoded strings (double encoding).
            'payload' => $this->payload,
        ];
    }

    public static function normalizeName(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /** @return list<StructureScope> */
    private static function campusScope(string $campusId): array
    {
        /** @var Campus $campus */
        $campus = Campus::query()->whereKey($campusId)->firstOrFail();

        return [new StructureScope($campus->organization_id, $campus->id)];
    }

    private static function organization(string $id): Organization
    {
        /** @var Organization $organization */
        $organization = Organization::query()->whereKey($id)->firstOrFail();

        return $organization;
    }

    private static function organizationIdOf(StructureUnit&Model $unit): ?string
    {
        $scope = $unit->structureScope();
        if ($scope->organizationId === '') {
            return null;
        }

        return $scope->organizationId;
    }

    private static function unit(string $unitType, string $unitId): StructureUnit&Model
    {
        $unit = match ($unitType) {
            'organization' => Organization::query()->whereKey($unitId)->first(),
            'campus' => Campus::query()->whereKey($unitId)->first(),
            'branch' => Branch::query()->whereKey($unitId)->first(),
            'department' => Department::query()->whereKey($unitId)->first(),
            default => throw ValidationError::forCode('organization.unit_type_unknown', sprintf('unknown structure unit type %s', $unitType)),
        };
        if ($unit === null) {
            throw ValidationError::forCode('organization.unit_not_found', sprintf('%s %s does not exist', $unitType, $unitId));
        }

        /** @var StructureUnit&Model $unit */
        return $unit;
    }

    /** @param array<string, mixed> $input */
    private static function unitType(array $input): string
    {
        $unitType = (string) ($input['unit_type'] ?? '');
        if (! in_array($unitType, ['organization', 'campus', 'branch', 'department'], true)) {
            throw ValidationError::forCode('organization.unit_type_unknown', 'unit_type must be organization, campus, branch or department');
        }

        return $unitType;
    }

    /** @param array<string, mixed> $input */
    private static function name(array $input, string $key): string
    {
        $name = trim((string) ($input[$key] ?? ''));
        if ($name === '') {
            throw ValidationError::forCode('organization.name_required', sprintf('%s is required', $key));
        }

        return $name;
    }

    /** @param array<string, mixed> $input */
    private static function id(array $input, string $key): string
    {
        $id = trim((string) ($input[$key] ?? ''));
        if ($id === '') {
            throw ValidationError::forCode('organization.id_required', sprintf('%s is required', $key));
        }

        return $id;
    }

    /** @param array<string, mixed> $input */
    private static function day(array $input, string $key): string
    {
        $day = trim((string) ($input[$key] ?? ''));
        if ($day === '') {
            throw ValidationError::forCode('organization.date_required', sprintf('%s is required', $key));
        }
        $parsed = CarbonImmutable::parse($day);

        return $parsed->startOfDay()->toDateString();
    }

    private static function requireActive(string $lifecycleState, string $unitType): void
    {
        if ($lifecycleState !== OrganizationLifecycle::STATE_ACTIVE) {
            throw BusinessRejection::forCode(
                'organization.parent_not_active',
                sprintf('%s owning the new unit must be active', $unitType),
            );
        }
    }
}
