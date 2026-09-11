<?php

declare(strict_types=1);

namespace App\Modules\Organization\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Organization\Domain\StructureChangeDefinition;
use App\Modules\Organization\Domain\StructureUnit;
use App\Modules\Organization\Domain\UniqueTopologyName;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\Department;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\StructureDecision;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Renaming is a material structure decision: the name before and after stays
 * in the audit evidence and historical attribution is never rewritten.
 */
final class RenameStructureUnit
{
    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
        private readonly UniqueTopologyName $uniqueNames,
    ) {}

    /** @return array{id: string, unit_type: string, name: string, correlation_id: string} */
    public function rename(Model&StructureUnit $unit, string $newName, StructureDecision $decision, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', [
            'organization.structure.rename',
            $unit->unitType(),
            $unit->unitId(),
            $newName,
            implode(',', $decision->participantIds()),
        ]));

        try {
            return $this->idempotency->execute('organization.structure.rename', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($unit, $newName, $decision): array {
                    $decision->authorize($this->access, StructureChangeDefinition::authorityScope($unit, false));

                    /** @var Model&StructureUnit $locked */
                    $locked = $unit::query()->whereKey($unit->unitId())->lockForUpdate()->firstOrFail();
                    $beforeName = $locked->unitName();
                    if ($beforeName === $newName) {
                        throw BusinessRejection::forCode('organization.rename_no_change', 'new name equals the current name');
                    }
                    $this->requireRenameAvailable($locked, $newName);

                    $locked->forceFill(['name' => $newName]);
                    try {
                        $locked->save();
                    } catch (UniqueConstraintViolationException) {
                        throw BusinessRejection::forCode(
                            'organization.structure.duplicate',
                            sprintf('a %s with this name already exists in the chosen scope', $locked->unitType()),
                        );
                    }
                    $event = $this->audit->record(
                        $decision->initiator->actorId,
                        'organization.structure.rename',
                        $locked->unitType(),
                        $locked->unitId(),
                        ['name' => $beforeName],
                        ['name' => $newName],
                    );

                    return [
                        'id' => $locked->unitId(),
                        'unit_type' => $locked->unitType(),
                        'name' => $newName,
                        'correlation_id' => $event->correlation_id,
                    ];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $decision->initiator, 'organization.structure.rename', $unit->unitType(), $unit->unitId());
        }
    }

    private function requireRenameAvailable(Model&StructureUnit $locked, string $newName): void
    {
        if ($locked instanceof Campus) {
            $this->uniqueNames->requireAvailable('campus', $newName, 'organization', $locked->organization_id, $locked->id);

            return;
        }
        if ($locked instanceof Department) {
            $this->uniqueNames->requireAvailable('department', $newName, $locked->scope_type, $locked->scope_id, $locked->id);

            return;
        }
        $unitType = $locked->unitType();
        if ($unitType === 'branch' || $unitType === 'organization') {
            $this->uniqueNames->requireAvailable($unitType, $newName, null, null, $locked->unitId());
        }
    }
}
