<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-recipient notification projection state; never acknowledges source work.
 *
 * @property string $id
 * @property string $notification_id
 * @property string $recipient_actor_id
 * @property 'unread'|'read'|'dismissed' $lifecycle_state
 * @property Carbon|null $read_at
 * @property Carbon|null $dismissed_at
 */
final class NotificationRecipientState extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'notification_id', 'recipient_actor_id', 'lifecycle_state', 'read_at', 'dismissed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /** @return BelongsTo<Notification, $this> */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }
}
