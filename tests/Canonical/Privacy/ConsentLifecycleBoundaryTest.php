<?php

declare(strict_types=1);

namespace Tests\Canonical\Privacy;

use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\TransitionConsent;
use App\Modules\Privacy\Domain\ConsentLifecycle;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Queries\SubjectPrivacyQuery;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical consent lifecycle boundaries.
 *
 * Three things are proven here and nowhere else:
 *
 *   1. expiry is the passage of the recorded window under the calendar
 *      authority — never a second revocation and never available for an
 *      open-ended consent;
 *   2. consent facts survive raw SQL: a forged birth state, a rewritten
 *      evidence locator, a skipped or reversed transition and a deletion are
 *      all rejected by `consents_guard_trigger`;
 *   3. the durable trigger and `ConsentLifecycle` agree on every state pair,
 *      so the two expressions of the one transition table cannot drift.
 *
 * Every fixture is produced by the production commands; every assertion is
 * against persisted state.
 */
final class ConsentLifecycleBoundaryTest extends CanonicalTestCase
{
    private Actor $officer;

    private string $subjectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = $this->actorWith('canon-priv-officer', [
            'privacy.define_purpose', 'privacy.consent', 'privacy.disclose', 'privacy.export',
        ]);
        $this->subjectId = 'canon-priv-subject';
        $this->personWithAuthority($this->subjectId, [], $this->sharedBranchId());
    }

    private function purpose(string $name, string $channel = 'email'): string
    {
        return app(DefineConsentPurpose::class)
            ->define($this->officer, $name, $channel, 'canonical-outreach', 'canon-purpose-'.$name)['purpose_id'];
    }

    private function record(string $purposeId, ?CarbonImmutable $effectiveTo, string $key): Consent
    {
        $consentId = app(RecordConsent::class)->record(
            $this->officer,
            $this->subjectId,
            $purposeId,
            'evidence/canonical/'.$key,
            CarbonImmutable::now()->subMonths(2)->startOfDay(),
            $effectiveTo,
            $key,
        )['consent_id'];

        return Consent::query()->findOrFail($consentId);
    }

    private function activate(Consent $consent, string $key): Consent
    {
        $transition = app(TransitionConsent::class);
        $transition->submit($this->officer, $consent, $key.'-submit');
        $transition->verify($this->officer, $consent, $key.'-verify');
        $transition->activate($this->officer, $consent, $key.'-activate');

        return Consent::query()->findOrFail($consent->id);
    }

    public function test_expiry_follows_the_recorded_window_and_is_never_a_rewrite(): void
    {
        $transition = app(TransitionConsent::class);

        // An open-ended consent has nothing to lapse: ending its use early is
        // a revocation, and the domain refuses to disguise one as expiry.
        $openEnded = $this->activate($this->record($this->purpose('canon-open-ended'), null, 'canon-priv-open'), 'canon-priv-open');
        try {
            $transition->expire($this->officer, $openEnded, 'canon-priv-open-expire');
            $this->fail('an open-ended consent must not expire');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.consent_expiry_not_due', $rejection->errorCode());
        }
        $this->assertSame('active', Consent::query()->findOrFail($openEnded->id)->lifecycle_state);

        // A consent still inside its window is current use authority and may
        // not be closed as expired before the window passes.
        $inWindow = $this->activate(
            $this->record($this->purpose('canon-in-window'), CarbonImmutable::now()->addYear()->startOfDay(), 'canon-priv-window'),
            'canon-priv-window',
        );
        try {
            $transition->expire($this->officer, $inWindow, 'canon-priv-window-expire');
            $this->fail('a consent inside its window must not expire');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.consent_expiry_not_due', $rejection->errorCode());
        }

        $profile = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId);
        $this->assertCount(2, $profile['consents'], 'both active consents are current use authority');

        // Once the recorded window has passed, expiry closes the consent,
        // stops future use and retains the record for archival.
        $lapsed = $this->activate(
            $this->record($this->purpose('canon-lapsed'), CarbonImmutable::now()->subMonth()->startOfDay(), 'canon-priv-lapsed'),
            'canon-priv-lapsed',
        );
        $expired = $transition->expire($this->officer, $lapsed, 'canon-priv-lapsed-expire');
        $this->assertSame('expired', $expired['lifecycle_state']);
        $this->assertDatabaseHas('consents', ['id' => $lapsed->id, 'lifecycle_state' => 'expired']);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.consent.expire',
            'target_type' => 'consent',
            'target_id' => $lapsed->id,
            'actor_id' => $this->officer->actorId,
        ]);

        $afterExpiry = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId);
        $this->assertCount(2, $afterExpiry['consents'], 'an expired consent is no longer current use authority');
        $this->assertCount(3, $afterExpiry['consent_history'], 'expiry retains the record in consent history');

        // Expiry is reversible in no direction except the archive: the record
        // is closed, retained, and finally archived without being erased.
        try {
            $transition->activate($this->officer, Consent::query()->findOrFail($lapsed->id), 'canon-priv-lapsed-reactivate');
            $this->fail('an expired consent must not reactivate');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.consent_transition_forbidden', $rejection->errorCode());
        }

        $transition->archive($this->officer, Consent::query()->findOrFail($lapsed->id), 'canon-priv-lapsed-archive');
        $this->assertDatabaseHas('consents', ['id' => $lapsed->id, 'lifecycle_state' => 'archived']);
        $this->assertDatabaseHas('consents', ['id' => $lapsed->id, 'evidence_ref' => 'evidence/canonical/canon-priv-lapsed']);

        // The subject's own withdrawal of the open-ended consent stays
        // attributable as a revocation, with its own append-only evidence.
        $transition->revoke(new Actor($this->subjectId, 'Subject'), Consent::query()->findOrFail($openEnded->id), 'all-channels', 'immediate-cessation', 'canon-priv-open-revoke');
        $this->assertDatabaseHas('consents', ['id' => $openEnded->id, 'lifecycle_state' => 'revoked']);
        $this->assertDatabaseHas('consent_revocations', ['consent_id' => $openEnded->id, 'scope' => 'all-channels', 'effect' => 'immediate-cessation']);

        $final = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId);
        $this->assertCount(1, $final['consents'], 'only the in-window active consent carries current use authority');
        $this->assertCount(3, $final['consent_history'], 'no consent was erased on the way');
    }

    public function test_consent_facts_survive_raw_rewrite_attempts(): void
    {
        $consent = $this->activate($this->record($this->purpose('canon-raw-boundary'), null, 'canon-priv-raw'), 'canon-priv-raw');

        // A rejected raw statement aborts PostgreSQL's enclosing transaction,
        // so every attack runs inside its own savepoint and the rejection is
        // rolled back to it — each guard faces a clean transaction.
        $attack = function (string $savepoint, callable $statement, string $expectedFragment, string $message): void {
            DB::statement('SAVEPOINT '.$savepoint);
            try {
                $statement();
                DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
                $this->fail($message);
            } catch (QueryException $exception) {
                DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
                $this->assertStringContainsString($expectedFragment, $exception->getMessage());
            }
        };

        // Verification and activation are separate attributable acts: no
        // writer may insert an already-active consent.
        $attack(
            'canon_priv_raw_1',
            fn () => DB::statement(
                "INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
                 VALUES (?, ?, ?, 'active', CURRENT_DATE, NULL, 'evidence/forged', ?, NOW(), NOW())",
                ['00000000-0000-4000-8000-0000000c0001', $this->subjectId, $consent->purpose_id, $this->officer->actorId],
            ),
            'a consent is born draft',
            'a forged active consent must be rejected at the database boundary',
        );

        // Consent without its evidence locator is not consent.
        $attack(
            'canon_priv_raw_2',
            fn () => DB::statement(
                "INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
                 VALUES (?, ?, ?, 'draft', CURRENT_DATE, NULL, '   ', ?, NOW(), NOW())",
                ['00000000-0000-4000-8000-0000000c0002', $this->subjectId, $consent->purpose_id, $this->officer->actorId],
            ),
            'a consent requires its evidence reference',
            'a consent without evidence must be rejected at the database boundary',
        );

        // Recorded facts are write-once: a corrected consent is a new consent.
        $attack('canon_priv_raw_3', fn () => DB::statement("UPDATE consents SET evidence_ref = 'evidence/tampered' WHERE id = ?", [$consent->id]),
            'write-once', 'consent evidence must be write-once even against raw SQL');
        $attack('canon_priv_raw_4', fn () => DB::statement('UPDATE consents SET subject_person_id = ? WHERE id = ?', [$this->officer->actorId, $consent->id]),
            'write-once', 'the consent subject must be write-once even against raw SQL');
        $attack('canon_priv_raw_5', fn () => DB::statement('UPDATE consents SET effective_from = CURRENT_DATE WHERE id = ?', [$consent->id]),
            'write-once', 'the consent window must be write-once even against raw SQL');

        // The lifecycle moves forward only, one canonical step at a time.
        $attack('canon_priv_raw_6', fn () => DB::statement("UPDATE consents SET lifecycle_state = 'draft', updated_at = NOW() WHERE id = ?", [$consent->id]),
            'a consent moves only forward', 'an active consent must never return to draft');
        $attack('canon_priv_raw_7', fn () => DB::statement("UPDATE consents SET lifecycle_state = 'submitted', updated_at = NOW() WHERE id = ?", [$consent->id]),
            'a consent moves only forward', 'an active consent must never return to submitted');

        // Ending use is a revocation, an expiry or an archive — never erasure.
        $attack('canon_priv_raw_8', fn () => DB::statement('DELETE FROM consents WHERE id = ?', [$consent->id]),
            'cannot be deleted', 'consent evidence must never be deleted');

        $this->assertDatabaseHas('consents', [
            'id' => $consent->id,
            'lifecycle_state' => 'active',
            'evidence_ref' => 'evidence/canonical/canon-priv-raw',
            'subject_person_id' => $this->subjectId,
        ]);
        $this->assertSame(1, DB::table('consents')->where('subject_person_id', $this->subjectId)->count());
    }

    public function test_the_trigger_and_the_lifecycle_registry_agree_on_every_state_pair(): void
    {
        // The trigger restates the transition table in SQL so that it holds
        // for writers that bypass PHP entirely. This proves the two
        // expressions of the one authority still say the same thing about
        // every ordered state pair, so neither can drift unnoticed.
        $paths = [
            ConsentLifecycle::STATE_DRAFT => [],
            ConsentLifecycle::STATE_SUBMITTED => ['submitted'],
            ConsentLifecycle::STATE_VERIFIED => ['submitted', 'verified'],
            ConsentLifecycle::STATE_ACTIVE => ['submitted', 'verified', 'active'],
            ConsentLifecycle::STATE_EXPIRED => ['submitted', 'verified', 'active', 'expired'],
            ConsentLifecycle::STATE_REVOKED => ['submitted', 'verified', 'active', 'revoked'],
            ConsentLifecycle::STATE_ARCHIVED => ['submitted', 'verified', 'active', 'expired', 'archived'],
        ];

        foreach (ConsentLifecycle::states() as $from) {
            // One purpose per state keeps the one-open-consent boundary out of
            // the way of a matrix that must judge transitions only.
            $consent = $this->record($this->purpose('canon-matrix-'.$from), null, 'canon-priv-matrix-'.$from);
            foreach ($paths[$from] as $step) {
                DB::statement('UPDATE consents SET lifecycle_state = ?, updated_at = NOW() WHERE id = ?', [$step, $consent->id]);
            }
            $this->assertSame($from, Consent::query()->findOrFail($consent->id)->lifecycle_state);

            foreach (ConsentLifecycle::states() as $to) {
                DB::statement('SAVEPOINT canon_priv_matrix');
                $accepted = true;
                try {
                    DB::statement('UPDATE consents SET lifecycle_state = ?, updated_at = NOW() WHERE id = ?', [$to, $consent->id]);
                } catch (QueryException) {
                    $accepted = false;
                }
                DB::statement('ROLLBACK TO SAVEPOINT canon_priv_matrix');

                $this->assertSame(
                    ConsentLifecycle::allowsTransition($from, $to),
                    $accepted,
                    sprintf('the consents guard and ConsentLifecycle disagree about %s -> %s', $from, $to),
                );
            }

            // The matrix left the row exactly where it started.
            $this->assertSame($from, Consent::query()->findOrFail($consent->id)->lifecycle_state);
        }
    }
}
