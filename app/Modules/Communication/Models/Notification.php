<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Actor-specific notification projection. It informs a recipient but never
 * owns the source task, approval, exception, permission, or domain state.
 *
 * @property string $id
 * @property string $event_id
 * @property string $recipient_actor_id
 * @property string $source_type
 * @property string $source_id
 * @property string $dedupe_key
 * @property string $title
 * @property string|null $body_ref
 * @property string $severity
 * @property string $scope_type
 * @property string|null $organization_id
 * @property string|null $branch_id
 * @property string $lifecycle_state
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 * @property Carbon|null $expires_at
 */
final class Notification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'event_id', 'recipient_actor_id', 'source_type', 'source_id',
        'dedupe_key', 'title', 'body_ref', 'severity', 'scope_type', 'organization_id', 'branch_id',
        'lifecycle_state', 'read_at', 'dismissed_at', 'expires_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
