<?php

declare(strict_types=1);

namespace App\Modules\Privacy\Models;

use App\Modules\Privacy\Domain\ConsentLifecycle;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subject authorization for one purpose, effective-dated with evidence.
 * Expiry and revocation stop future use; the record is never erased.
 *
 * The model expresses the same boundary as `consents_guard_trigger`
 * (2026_09_13_000211) and consults `ConsentLifecycle` for it, so a writer
 * that bypasses `RecordConsent`/`TransitionConsent` receives a domain
 * rejection instead of a raw database error — and a writer that bypasses the
 * model entirely is still stopped by the trigger:
 *
 *   - a consent is born `draft` with a non-empty evidence locator;
 *   - its subject, purpose, evidence locator, effective window and recorder
 *     are write-once (a corrected consent is a new consent);
 *   - only the lifecycle state moves, and only forward through the canonical
 *     chain;
 *   - it is never deleted: ending use is a revocation, an expiry or an
 *     archive, all of which retain the record.
 *
 * @property string $id
 * @property string $subject_person_id
 * @property string $purpose_id
 * @property string $lifecycle_state
 * @property string $effective_from
 * @property string|null $effective_to
 * @property string $evidence_ref
 * @property string $recorded_by
 */
final class Consent extends Model
{
    /** Facts recorded once, at capture. None of them is a lifecycle field. */
    private const WRITE_ONCE = [
        'subject_person_id',
        'purpose_id',
        'evidence_ref',
        'effective_from',
        'effective_to',
        'recorded_by',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'subject_person_id', 'purpose_id', 'lifecycle_state', 'effective_from', 'effective_to', 'evidence_ref', 'recorded_by'];

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            $this->requireWriteOnceFacts();
            $this->requireForwardTransition();
        } else {
            $this->requireBornDraft();
        }

        return parent::save($options);
    }

    public function delete(): bool
    {
        throw BusinessRejection::forCode('privacy.consent_immutable', 'consent evidence is retained; ending use is a revocation, an expiry or an archive');
    }

    public function forceDelete(): bool
    {
        throw BusinessRejection::forCode('privacy.consent_immutable', 'consent evidence is retained; ending use is a revocation, an expiry or an archive');
    }

    /** @return BelongsTo<ConsentPurpose, $this> */
    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class);
    }

    private function requireBornDraft(): void
    {
        if ((string) $this->lifecycle_state !== ConsentLifecycle::STATE_DRAFT) {
            throw BusinessRejection::forCode(
                'privacy.consent_state_forbidden',
                'a consent is born draft; verification and activation are separate attributable acts',
            );
        }
        if (trim((string) $this->evidence_ref) === '') {
            throw BusinessRejection::forCode('privacy.consent_evidence_missing', 'consent requires evidence');
        }
    }

    private function requireWriteOnceFacts(): void
    {
        foreach (self::WRITE_ONCE as $field) {
            if (! $this->isDirty($field)) {
                continue;
            }
            if ($this->sameFact($this->getOriginal($field), $this->getAttribute($field))) {
                continue;
            }

            throw BusinessRejection::forCode(
                'privacy.consent_immutable',
                'only the lifecycle state may change on a consent; its subject, purpose, evidence and effective window are write-once',
            );
        }
    }

    private function requireForwardTransition(): void
    {
        if (! $this->isDirty('lifecycle_state')) {
            return;
        }

        ConsentLifecycle::requireTransition(
            (string) $this->getOriginal('lifecycle_state'),
            (string) $this->getAttribute('lifecycle_state'),
        );
    }

    /**
     * Date and identifier columns can be re-read with padding or as null; only
     * a genuinely different fact is a rewrite.
     */
    private function sameFact(mixed $original, mixed $current): bool
    {
        $normalize = static fn (mixed $value): string => trim((string) ($value ?? ''));

        return $normalize($original) === $normalize($current);
    }
}
