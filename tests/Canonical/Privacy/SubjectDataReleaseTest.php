<?php

declare(strict_types=1);

namespace Tests\Canonical\Privacy;

use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\ExportSubjectData;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Commands\TransitionConsent;
use App\Modules\Privacy\Domain\ExportApprovalChain;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\PrivacyExportRequest;
use App\Modules\Privacy\Queries\SubjectPrivacyQuery;
use App\Support\Authorization\Actor;
use App\Support\Authorization\PersonBranchScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical subject-data release boundaries.
 *
 * A release of personal data is the most consequential act in this domain, so
 * three things are proven here:
 *
 *   1. the staged organization-wide chain has ONE authority — the projection
 *      that offers a signature (`ExportApprovalChain`) and the command that
 *      signs it agree in every chain state, so an offered control is never a
 *      forged one and a refused one is never silently available;
 *   2. release evidence is recorded once and survives raw SQL: disclosures
 *      and export requests are append-only, an approver slot is written once,
 *      and an executed request stays closed;
 *   3. the subject dossier answers a subject access request from one read
 *      model — current use authority, complete history with withdrawal
 *      evidence, disclosures and the export chain — while never projecting a
 *      consent evidence locator.
 */
final class SubjectDataReleaseTest extends CanonicalTestCase
{
    private Actor $exporter;

    private string $subjectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exporter = $this->actorWith('canon-priv-exporter', [
            'privacy.define_purpose', 'privacy.consent', 'privacy.disclose', 'privacy.export',
        ]);
        $this->subjectId = 'canon-priv-release-subject';
        $this->personWithAuthority($this->subjectId, [], $this->sharedBranchId());
    }

    private function purpose(string $name, string $channel = 'email'): string
    {
        return app(DefineConsentPurpose::class)
            ->define($this->exporter, $name, $channel, 'canonical-outreach', 'canon-priv-purpose-'.$name)['purpose_id'];
    }

    /**
     * One active consent for its own purpose: the one-open-consent boundary
     * forbids two open consents for the same subject and purpose, so every
     * consent in a fixture set needs a purpose of its own.
     */
    private function activeConsent(string $purposeName, string $key, ?CarbonImmutable $effectiveFrom = null, ?CarbonImmutable $effectiveTo = null): Consent
    {
        $consentId = app(RecordConsent::class)->record(
            $this->exporter,
            $this->subjectId,
            $this->purpose($purposeName),
            'evidence/canonical/'.$key,
            ($effectiveFrom ?? CarbonImmutable::now()->subMonths(2))->startOfDay(),
            $effectiveTo,
            $key,
        )['consent_id'];

        $transition = app(TransitionConsent::class);
        $consent = Consent::query()->findOrFail($consentId);
        $transition->submit($this->exporter, $consent, $key.'-submit');
        $transition->verify($this->exporter, $consent, $key.'-verify');
        $transition->activate($this->exporter, $consent, $key.'-activate');

        return Consent::query()->findOrFail($consentId);
    }

    private function request(string $key, string $purpose = 'canonical organization audit'): PrivacyExportRequest
    {
        // The organization of a bulk request is the subject's own, resolved by
        // the production scope resolver exactly as the API resolves it.
        $organizationId = PersonBranchScope::resolve($this->subjectId)->organizationId;
        $requestId = app(ExportSubjectData::class)
            ->request($this->exporter, $this->subjectId, $purpose, $organizationId, $key)['request_id'];

        return PrivacyExportRequest::query()->findOrFail($requestId);
    }

    /** @param callable(): mixed $statement */
    private function attack(string $savepoint, callable $statement, string $expectedFragment, string $message): void
    {
        DB::statement('SAVEPOINT '.$savepoint);
        try {
            $statement();
            DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
            $this->fail($message);
        } catch (QueryException $exception) {
            DB::statement('ROLLBACK TO SAVEPOINT '.$savepoint);
            $this->assertStringContainsString($expectedFragment, $exception->getMessage());
        }
    }

    public function test_the_staged_chain_has_one_authority_for_signatures_and_execution(): void
    {
        $command = app(ExportSubjectData::class);
        $approverOne = $this->actorWithStructureCapabilities('canon-priv-approver-1', ['privacy.approve_bulk_export']);
        $approverTwo = $this->actorWithStructureCapabilities('canon-priv-approver-2', ['privacy.approve_bulk_export']);
        $request = $this->request('canon-priv-chain-1');

        // Requested and unsigned: the chain offers a first signature and
        // refuses execution — and the command does exactly the same.
        $this->assertTrue(ExportApprovalChain::acceptsSignature('requested', null, $approverOne->actorId));
        $this->assertFalse(ExportApprovalChain::allowsExecution('requested'));
        $this->assertSame('requested', $command->approve($approverOne, $request, 'canon-priv-chain-2')['lifecycle_state']);
        try {
            $command->execute($this->exporter, $request, 'canon-priv-chain-3');
            $this->fail('an unsigned chain must not execute');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.export_request_state', $rejection->errorCode());
        }

        // Requested and singly signed: the same actor is refused by both the
        // projection and the command; a distinct actor is accepted by both.
        $signed = PrivacyExportRequest::query()->findOrFail($request->id);
        $this->assertFalse(ExportApprovalChain::acceptsSignature('requested', $signed->approver_one_id, $approverOne->actorId));
        $this->assertTrue(ExportApprovalChain::acceptsSignature('requested', $signed->approver_one_id, $approverTwo->actorId));
        try {
            $command->approve($approverOne, $signed, 'canon-priv-chain-4');
            $this->fail('one actor must not supply both signatures');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('privacy.bulk_export_single_actor', $denial->errorCode());
        }
        $this->assertSame('approved', $command->approve($approverTwo, $signed, 'canon-priv-chain-5')['lifecycle_state']);

        // Approved: no further signature is offered or accepted, and execution
        // becomes legal for both.
        $approved = PrivacyExportRequest::query()->findOrFail($request->id);
        $this->assertFalse(ExportApprovalChain::acceptsSignature('approved', $approved->approver_one_id, $approverTwo->actorId));
        $this->assertTrue(ExportApprovalChain::allowsExecution('approved'));
        try {
            $command->approve($approverTwo, $approved, 'canon-priv-chain-6');
            $this->fail('an approved request accepts no further signature');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.export_request_state', $rejection->errorCode());
        }

        $executed = $command->execute($this->exporter, $approved, 'canon-priv-chain-7');
        $this->assertDatabaseHas('privacy_export_requests', [
            'id' => $request->id,
            'lifecycle_state' => 'exported',
            'exported_by' => $this->exporter->actorId,
            'disclosure_id' => $executed['disclosure_id'],
        ]);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.export.execute',
            'actor_id' => $this->exporter->actorId,
            'target_id' => $executed['disclosure_id'],
        ]);

        // Exported: the chain is closed, offers nothing, and the command
        // refuses a second execution and a third signature alike.
        $this->assertTrue(ExportApprovalChain::isClosed('exported'));
        $this->assertFalse(ExportApprovalChain::acceptsSignature('exported', $approved->approver_one_id, $approverOne->actorId));
        $this->assertFalse(ExportApprovalChain::allowsExecution('exported'));
        try {
            $command->execute($this->exporter, PrivacyExportRequest::query()->findOrFail($request->id), 'canon-priv-chain-8');
            $this->fail('an executed request must never release twice');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('privacy.export_request_state', $rejection->errorCode());
        }

        $profile = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId);
        $this->assertCount(1, $profile['export_requests']);
        $this->assertTrue($profile['export_requests'][0]['closed'], 'the dossier reports the same closed chain the command signed');
        $this->assertSame($executed['disclosure_id'], $profile['export_requests'][0]['disclosure_id']);
    }

    public function test_release_evidence_is_recorded_once_and_survives_raw_rewrite_attempts(): void
    {
        $consent = $this->activeConsent('canon-release-current', 'canon-priv-release-1');
        app(RecordDisclosure::class)->disclose(
            $this->exporter, $this->subjectId, 'Ministry of Education', 'statutory-reporting', 'privacy.disclose',
            'subject', $this->subjectId, 'academic-records', 'canon-priv-release-2',
        );

        $export = app(ExportSubjectData::class)->export(
            $this->exporter, $this->subjectId, 'subject-data-request', 'subject', $this->subjectId, 'canon-priv-release-3',
        );
        $replay = app(ExportSubjectData::class)->export(
            $this->exporter, $this->subjectId, 'subject-data-request', 'subject', $this->subjectId, 'canon-priv-release-3',
        );

        // A replayed export is the same release, not a second one.
        $this->assertSame($export['disclosure_id'], $replay['disclosure_id']);
        $this->assertSame($export['correlation_id'], $replay['correlation_id']);
        $this->assertSame(1, DB::table('disclosures')->where('purpose', 'subject-data-request')->count());

        // The released dataset is derived, never mutated, and carries no
        // consent evidence locator. It reports the releases that existed when
        // it was derived; its own disclosure is recorded afterwards.
        $this->assertSame($this->subjectId, $export['dataset']['subject']['person_id']);
        $this->assertCount(1, $export['dataset']['consents']);
        $this->assertSame($consent->id, $export['dataset']['consents'][0]['consent_id']);
        $this->assertCount(1, $export['dataset']['disclosures']);
        $this->assertSame('statutory-reporting', $export['dataset']['disclosures'][0]['purpose']);
        $this->assertNoKeyAnywhere('evidence_ref', $export['dataset']);

        $this->attack('canon_priv_release_1', fn () => DB::statement("UPDATE disclosures SET recipient = 'Someone Else' WHERE id = ?", [$export['disclosure_id']]),
            'privacy evidence is append-only', 'a recorded disclosure must never be rewritten');
        $this->attack('canon_priv_release_2', fn () => DB::statement('DELETE FROM disclosures WHERE id = ?', [$export['disclosure_id']]),
            'privacy evidence is append-only', 'a recorded disclosure must never be deleted');

        // The staged chain: an approver slot is written once, and an executed
        // request stays closed and undeletable.
        $approverOne = $this->actorWithStructureCapabilities('canon-priv-release-approver-1', ['privacy.approve_bulk_export']);
        $approverTwo = $this->actorWithStructureCapabilities('canon-priv-release-approver-2', ['privacy.approve_bulk_export']);
        $command = app(ExportSubjectData::class);
        $request = $this->request('canon-priv-release-4');
        $command->approve($approverOne, $request, 'canon-priv-release-5');

        $this->attack(
            'canon_priv_release_3',
            fn () => DB::statement(
                "UPDATE privacy_export_requests SET approver_one_id = ?, approver_two_id = ?, lifecycle_state = 'approved', updated_at = NOW() WHERE id = ?",
                [$approverTwo->actorId, $approverOne->actorId, $request->id],
            ),
            'written once',
            'an approver slot must never be reassigned',
        );

        $command->approve($approverTwo, PrivacyExportRequest::query()->findOrFail($request->id), 'canon-priv-release-6');
        $executed = $command->execute($this->exporter, PrivacyExportRequest::query()->findOrFail($request->id), 'canon-priv-release-7');

        $this->attack('canon_priv_release_4', fn () => DB::statement("UPDATE privacy_export_requests SET purpose = 'rewritten after release', updated_at = NOW() WHERE id = ?", [$request->id]),
            'an executed bulk export request is closed', 'an executed export request must stay closed');
        $this->attack('canon_priv_release_5', fn () => DB::statement('DELETE FROM privacy_export_requests WHERE id = ?', [$request->id]),
            'cannot be deleted', 'an export request must never be deleted');

        $this->assertDatabaseHas('disclosures', [
            'id' => $export['disclosure_id'],
            'subject_person_id' => $this->subjectId,
            'purpose' => 'subject-data-request',
            'disclosed_category' => 'subject-data-export',
            'scope_type' => 'subject',
            'scope_id' => $this->subjectId,
        ]);
        $this->assertDatabaseHas('privacy_export_requests', [
            'id' => $request->id,
            'lifecycle_state' => 'exported',
            'purpose' => 'canonical organization audit',
            'approver_one_id' => $approverOne->actorId,
            'approver_two_id' => $approverTwo->actorId,
            'disclosure_id' => $executed['disclosure_id'],
        ]);
        $this->assertSame('active', Consent::query()->findOrFail($consent->id)->lifecycle_state, 'a release never mutates the consent it reports on');
    }

    public function test_the_dossier_answers_a_subject_access_request_from_one_read_model(): void
    {
        $current = $this->activeConsent('canon-dossier-current', 'canon-priv-dossier-1');
        $lapsed = $this->activeConsent('canon-dossier-lapsed', 'canon-priv-dossier-2', null, CarbonImmutable::now()->subMonth()->startOfDay());
        app(TransitionConsent::class)->expire($this->exporter, $lapsed, 'canon-priv-dossier-3');

        // A withdrawn consent keeps its withdrawal evidence in the history.
        $withdrawn = $this->activeConsent('canon-dossier-withdrawn', 'canon-priv-dossier-4', CarbonImmutable::now()->subMonths(3)->startOfDay());
        app(TransitionConsent::class)->revoke(
            new Actor($this->subjectId, 'Subject'), $withdrawn, 'all-channels', 'immediate-cessation', 'canon-priv-dossier-5',
        );

        // A future window is recorded now and becomes current use authority on
        // its own first day — the projection reads the window, never a guess.
        $upcoming = $this->activeConsent(
            'canon-dossier-upcoming',
            'canon-priv-dossier-6',
            CarbonImmutable::now()->addMonth()->startOfDay(),
            CarbonImmutable::now()->addMonths(3)->startOfDay(),
        );

        app(ExportSubjectData::class)->export(
            $this->exporter, $this->subjectId, 'subject-data-request', 'subject', $this->subjectId, 'canon-priv-dossier-7',
        );

        $profile = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId);

        // Current use authority is exactly one consent: the expired, the
        // revoked and the not-yet-started ones do not count, and none of them
        // was erased to achieve that.
        $this->assertCount(1, $profile['consents']);
        $this->assertSame($current->id, $profile['consents'][0]['consent_id']);
        $this->assertSame('active', $profile['consents'][0]['lifecycle_state']);
        $this->assertSame($this->exporter->actorId, $profile['consents'][0]['recorded_by']);

        // The complete history answers "what was ever recorded, and what
        // happened to it" without a second read model.
        $this->assertCount(4, $profile['consent_history']);
        $historyStates = array_column($profile['consent_history'], 'lifecycle_state');
        sort($historyStates);
        $this->assertSame(['active', 'active', 'expired', 'revoked'], $historyStates);

        $withdrawnRow = collect($profile['consent_history'])->firstWhere('consent_id', $withdrawn->id);
        $this->assertIsArray($withdrawnRow);
        $this->assertCount(1, $withdrawnRow['revocations']);
        $this->assertSame($this->subjectId, $withdrawnRow['revocations'][0]['revoked_by']);
        $this->assertSame('all-channels', $withdrawnRow['revocations'][0]['scope']);
        $this->assertSame('immediate-cessation', $withdrawnRow['revocations'][0]['effect']);

        $this->assertCount(1, $profile['disclosures']);
        $this->assertSame('subject-data-request', $profile['disclosures'][0]['purpose']);
        $this->assertSame('subject:'.$this->subjectId, $profile['disclosures'][0]['scope']);

        // Consent evidence locators are write-only across every read model:
        // an officer answers a subject from this projection, and it must never
        // become a retrieval map for consent artifacts.
        // The erasure doctrine is stated by the projection layer that composes
        // the HTTP dossier (asserted in tests/Feature/Api/PrivacyApiFeatureTest);
        // this read model's own contract is that it never carries a locator.
        $this->assertNoKeyAnywhere('evidence_ref', $profile);

        // The as-of day is a projection parameter over the recorded windows,
        // not a second authority: on the upcoming consent's first day it
        // carries use authority, and a consent closed since is not resurrected
        // by reading an earlier day.
        $later = (new SubjectPrivacyQuery)->subjectProfile($this->subjectId, CarbonImmutable::now()->addMonths(2)->startOfDay());
        $laterIds = array_column($later['consents'], 'consent_id');
        $this->assertContains($upcoming->id, $laterIds);
        $this->assertContains($current->id, $laterIds, 'an open-ended active consent still covers a later day');
        $this->assertNotContains($lapsed->id, $laterIds, 'an expired consent stays closed');
        $this->assertNotContains($withdrawn->id, $laterIds, 'a withdrawn consent stays withdrawn');
    }

    /**
     * @param  array<array-key, mixed>|string|null  $structure
     */
    private function assertNoKeyAnywhere(string $key, mixed $structure, string $path = 'projection'): void
    {
        if (! is_array($structure)) {
            return;
        }

        foreach ($structure as $index => $value) {
            if ((string) $index === $key) {
                $this->fail(sprintf('the projection must never expose %s (found at %s.%s)', $key, $path, (string) $index));
            }
            $this->assertNoKeyAnywhere($key, $value, $path.'.'.$index);
        }
    }
}
