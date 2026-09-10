<?php

declare(strict_types=1);

namespace App\Modules\WorkManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Actionable coordination only; source-domain truth remains authoritative elsewhere. */
final class WorkItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'workflow_instance_id', 'kind', 'title', 'description',
        'source_type', 'source_id', 'action_key', 'source_version', 'organization_id', 'branch_id',
        'assigned_to', 'queue_key', 'priority', 'due_at', 'lifecycle_state',
        'sla_policy_key', 'sla_state', 'escalation_level', 'last_escalated_at',
        'claimed_at', 'completed_at', 'completed_by', 'created_by',
    ];

    protected $casts = [
        'due_at' => 'datetime', 'last_escalated_at' => 'datetime',
        'claimed_at' => 'datetime', 'completed_at' => 'datetime',
        'escalation_level' => 'integer',
    ];

    /** @return BelongsTo<WorkflowInstance, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    /** @return HasMany<WorkItemHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(WorkItemHistory::class, 'work_item_id')->orderBy('occurred_at');
    }
}
