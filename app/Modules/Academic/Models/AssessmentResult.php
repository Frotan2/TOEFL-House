<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Support\Errors\AuthorizationDenied;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Calculated result of one attempt through the ordered review chain; a
 * correction appends a new result row referencing the one it corrects.
 *
 * @property string $id
 * @property string $attempt_id
 * @property string $score
 * @property string $lifecycle_state
 * @property string|null $corrects_id
 * @property string|null $correction_reason
 */
final class AssessmentResult extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'attempt_id', 'score', 'lifecycle_state', 'corrects_id', 'correction_reason', 'scored_by'];

    protected static function booted(): void
    {
        self::updating(function (self $result): void {
            if ($result->getOriginal('lifecycle_state') !== 'approved' || $result->lifecycle_state !== 'released') {
                return;
            }

            $releaser = trim((string) $result->released_by);
            $approver = trim((string) $result->approved_by);
            $moderator = trim((string) $result->moderated_by);

            if ($releaser === '') {
                throw AuthorizationDenied::forCode(
                    'academic.release_signer_required',
                    'a released assessment result requires attributable release signer provenance',
                );
            }
            if ($releaser === $approver || $releaser === $moderator) {
                throw AuthorizationDenied::forCode(
                    'academic.release_not_independent',
                    'the assessment releaser must differ from both the approver and moderator',
                );
            }
        });
    }

    /** @return BelongsTo<AssessmentAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AssessmentAttempt::class);
    }
}
