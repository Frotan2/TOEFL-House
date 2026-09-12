<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Staged, signed governance record for one topology change. The four-actor
 * separation-of-duties chain is captured across distinct authenticated
 * sessions: proposed -> reviewed -> executed (or rejected/withdrawn). Rows
 * are append-only facts: the database guard trigger forbids deletion and
 * permits only signature/state evolution.
 *
 * @property string $id
 * @property string $change_type
 * @property string $unit_type
 * @property string|null $organization_id
 * @property string|null $target_id
 * @property string|null $parent_scope_type
 * @property string|null $parent_scope_id
 * @property string $change_key
 * @property array<string, mixed> $payload
 * @property string $lifecycle_state
 * @property string $proposed_by
 * @property string|null $reviewed_by
 * @property string|null $owner_one_id
 * @property string|null $owner_two_id
 * @property string|null $closed_by
 * @property string|null $closure_reason
 * @property array<string, mixed>|null $result
 */
final class StructureChangeRequest extends Model
{
    public const STATE_PROPOSED = 'proposed';

    public const STATE_REVIEWED = 'reviewed';

    public const STATE_EXECUTED = 'executed';

    public const STATE_REJECTED = 'rejected';

    public const STATE_WITHDRAWN = 'withdrawn';

    public const TYPE_CREATE_ORGANIZATION = 'create_organization';

    public const TYPE_CREATE_CAMPUS = 'create_campus';

    public const TYPE_CREATE_BRANCH = 'create_branch';

    public const TYPE_CREATE_DEPARTMENT = 'create_department';

    public const TYPE_RENAME = 'rename_unit';

    public const TYPE_TRANSITION = 'transition_unit';

    public const TYPE_TRANSFER = 'transfer_branch';

    public const OPEN_STATES = [self::STATE_PROPOSED, self::STATE_REVIEWED];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'change_type', 'unit_type', 'organization_id', 'target_id',
        'parent_scope_type', 'parent_scope_id', 'change_key', 'payload',
        'lifecycle_state', 'proposed_by', 'reviewed_by', 'owner_one_id',
        'owner_two_id', 'closed_by', 'closure_reason', 'result',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
    ];

    public function isOpen(): bool
    {
        return in_array($this->lifecycle_state, self::OPEN_STATES, true);
    }

    /** Human-readable description of the staged change for operator surfaces. */
    public function description(): string
    {
        $unit = ucfirst($this->unit_type);
        $name = (string) ($this->payload['name'] ?? $this->payload['new_name'] ?? '');

        return match ($this->change_type) {
            self::TYPE_CREATE_ORGANIZATION => sprintf('Create organization "%s"', $name),
            self::TYPE_CREATE_CAMPUS => sprintf('Create campus "%s"', $name),
            self::TYPE_CREATE_BRANCH => sprintf('Create branch "%s"', $name),
            self::TYPE_CREATE_DEPARTMENT => sprintf('Create department "%s"', $name),
            self::TYPE_RENAME => sprintf('Rename %s to "%s"', strtolower($unit), $name),
            self::TYPE_TRANSITION => sprintf('%s %s', ucfirst((string) $this->payload['action']), strtolower($unit)),
            self::TYPE_TRANSFER => sprintf(
                'Transfer branch to campus, effective %s',
                (string) ($this->payload['effective_from'] ?? ''),
            ),
            default => $this->change_type,
        };
    }
}
