<?php

declare(strict_types=1);

namespace Tests\Canonical\Documents;

use App\Modules\Documents\Commands\DefineDocumentClassification;
use App\Modules\Documents\Commands\RegisterDocument;
use App\Modules\Documents\Commands\TransitionDocument;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Queries\DocumentHistoryQuery;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical document evidence lifecycle: registration as draft with an
 * immutable first version, submission as an uploader act, verification as an
 * independent authority with uploader/verifier separation of duties, and the
 * forward-only state machine (draft → submitted → verified|rejected →
 * active → expired → archived).
 *
 * Every fixture is produced by the production commands; every assertion is
 * against persisted state (rows, audit events, transactional outbox).
 */
final class DocumentEvidenceLifecycleTest extends CanonicalTestCase
{
    private Actor $classifier;

    private string $classificationId;

    private string $subjectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = $this->actorWith('canon-doc-classifier', ['documents.classify']);
        $this->classificationId = app(DefineDocumentClassification::class)->defineClassification(
            $this->classifier, 'canon-identity', 'Identity', 'restricted', 'canon-doc-class-1'
        )['classification_id'];
        $this->subjectId = 'canon-doc-subject';
        $this->personWithAuthority($this->subjectId, [], $this->sharedBranchId());
    }

    private function registrar(string $id = 'canon-doc-registrar'): Actor
    {
        return $this->actorWith($id, ['documents.register']);
    }

    private function verifier(string $id = 'canon-doc-verifier'): Actor
    {
        return $this->actorWith($id, ['documents.verify']);
    }

    private function register(Actor $registrar, string $key): Document
    {
        $documentId = app(RegisterDocument::class)->register(
            $registrar, $this->subjectId, $this->classificationId, 'Canonical identity evidence', 'hash-'.$key, 'storage/canonical/'.$key, $key
        )['document_id'];

        return Document::query()->findOrFail($documentId);
    }

    /** @return array{document_id: string, version_no: int, lifecycle_state: string, correlation_id: string} */
    private function submit(Actor $registrar, Document $document, string $key): array
    {
        return app(TransitionDocument::class)->submit($registrar, $document, 'hash-'.$key, 'storage/canonical/'.$key, $key);
    }

    private function fresh(Document $document): Document
    {
        return Document::query()->findOrFail($document->id);
    }

    public function test_a_document_progresses_register_submit_verify_activate_with_persisted_evidence(): void
    {
        $registrar = $this->registrar();
        $registered = app(RegisterDocument::class)->register(
            $registrar, $this->subjectId, $this->classificationId, 'Canonical identity evidence', 'hash-v1', 'storage/canonical/v1', 'canon-doc-register-1'
        );
        $document = Document::query()->findOrFail($registered['document_id']);

        $this->assertSame(1, $registered['version_no']);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id, 'subject_person_id' => $this->subjectId,
            'classification_id' => $this->classificationId, 'lifecycle_state' => 'draft',
        ]);
        $this->assertDatabaseHas('document_versions', [
            'document_id' => $document->id, 'version_no' => 1,
            'content_hash' => 'hash-v1', 'storage_ref' => 'storage/canonical/v1', 'uploaded_by' => 'canon-doc-registrar',
        ]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.register', 'target_id' => $document->id, 'actor_id' => 'canon-doc-registrar']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $registered['correlation_id']]);

        $submitted = $this->submit($registrar, $document, 'canon-doc-submit-1');
        $this->assertSame(2, $submitted['version_no']);
        $this->assertSame('submitted', $submitted['lifecycle_state']);
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version_no' => 2, 'uploaded_by' => 'canon-doc-registrar']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.submit', 'target_id' => $document->id]);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $submitted['correlation_id']]);

        $verifier = $this->verifier();
        $verified = app(TransitionDocument::class)->verify($verifier, $this->fresh($document), true, 'Evidence matches the verified subject.', 'canon-doc-verify-1');
        $this->assertSame('verified', $verified['lifecycle_state']);
        $this->assertSame(2, $verified['version_no'], 'the verdict names the version under review');
        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $document->id, 'version_no' => 2, 'verifier_person_id' => 'canon-doc-verifier',
            'result' => 'pass', 'reason' => 'Evidence matches the verified subject.',
        ]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.verify', 'target_id' => $document->id, 'actor_id' => 'canon-doc-verifier']);

        $activated = app(TransitionDocument::class)->activate($verifier, $this->fresh($document), 'canon-doc-activate-1');
        $this->assertSame('active', $activated['lifecycle_state']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'active']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.activate', 'target_id' => $document->id]);

        $history = app(DocumentHistoryQuery::class)->documentHistory($document->id);
        $this->assertSame('active', $history['lifecycle_state']);
        $this->assertCount(2, $history['versions']);
        $this->assertSame([['verifier' => 'canon-doc-verifier', 'result' => 'pass', 'reason' => 'Evidence matches the verified subject.', 'at' => $history['versions'][1]['verifications'][0]['at']]],
            $history['versions'][1]['verifications']);
        $this->assertSame([], $history['versions'][0]['verifications']);
    }

    public function test_the_version_uploader_can_never_verify_their_own_version(): void
    {
        // One actor holding BOTH authorities still may not verify its own
        // upload: separation of duties is about the act, not the capability.
        $uploaderVerifier = $this->actorWith('canon-doc-both', ['documents.register', 'documents.verify']);
        $document = $this->register($uploaderVerifier, 'canon-doc-sod-1');
        $this->submit($uploaderVerifier, $document, 'canon-doc-sod-2');

        try {
            app(TransitionDocument::class)->verify($uploaderVerifier, $this->fresh($document), true, 'Self-serving verdict.', 'canon-doc-sod-3');
            $this->fail('the uploader of the version under review must never verify it');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('documents.verifier_is_uploader', $rejection->errorCode());
        }

        $this->assertSame(0, DB::table('document_verifications')->where('document_id', $document->id)->count());
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'submitted']);

        // An independent verifier with the same capability passes normally.
        $independent = $this->verifier('canon-doc-independent');
        $verified = app(TransitionDocument::class)->verify($independent, $this->fresh($document), true, 'Independent verdict.', 'canon-doc-sod-4');
        $this->assertSame('verified', $verified['lifecycle_state']);
    }

    public function test_a_failed_verdict_rejects_and_only_a_new_version_returns_to_review(): void
    {
        $registrar = $this->registrar('canon-doc-registrar-2');
        $verifier = $this->verifier('canon-doc-verifier-2');
        $document = $this->register($registrar, 'canon-doc-fail-1');
        $this->submit($registrar, $document, 'canon-doc-fail-2');

        $rejected = app(TransitionDocument::class)->verify($verifier, $this->fresh($document), false, 'Identity page is illegible.', 'canon-doc-fail-3');
        $this->assertSame('rejected', $rejected['lifecycle_state']);
        $this->assertDatabaseHas('document_verifications', ['document_id' => $document->id, 'version_no' => 2, 'result' => 'fail', 'reason' => 'Identity page is illegible.']);

        // A rejected document cannot be verified again or pushed forward;
        // correction happens exclusively by appending a new version.
        try {
            app(TransitionDocument::class)->verify($verifier, $this->fresh($document), true, 'Changing our minds.', 'canon-doc-fail-4');
            $this->fail('a rejected document must not accept a second verdict for the same version');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('documents.verify_wrong_state', $rejection->errorCode());
        }
        try {
            app(TransitionDocument::class)->activate($verifier, $this->fresh($document), 'canon-doc-fail-5');
            $this->fail('a rejected document must not activate');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('documents.transition_forbidden', $rejection->errorCode());
        }

        $resubmitted = $this->submit($registrar, $this->fresh($document), 'canon-doc-fail-6');
        $this->assertSame(3, $resubmitted['version_no']);
        $this->assertSame('submitted', $resubmitted['lifecycle_state']);

        $passed = app(TransitionDocument::class)->verify($verifier, $this->fresh($document), true, 'Legible replacement page.', 'canon-doc-fail-7');
        $this->assertSame('verified', $passed['lifecycle_state']);
        $this->assertSame(3, $passed['version_no']);

        // The complete evidence trail survived: three versions, one failed
        // verdict against version 2, one passing verdict against version 3.
        $this->assertSame(3, DB::table('document_versions')->where('document_id', $document->id)->count());
        $this->assertSame(2, DB::table('document_verifications')->where('document_id', $document->id)->count());
        $this->assertDatabaseHas('document_verifications', ['document_id' => $document->id, 'version_no' => 2, 'result' => 'fail']);
        $this->assertDatabaseHas('document_verifications', ['document_id' => $document->id, 'version_no' => 3, 'result' => 'pass']);
    }

    public function test_the_state_machine_refuses_every_illegal_transition(): void
    {
        $registrar = $this->registrar('canon-doc-registrar-3');
        $verifier = $this->verifier('canon-doc-verifier-3');
        $transition = app(TransitionDocument::class);

        $refuse = function (callable $action, string $expectedCode, string $message): void {
            try {
                $action();
                $this->fail($message);
            } catch (BusinessRejection $rejection) {
                $this->assertSame($expectedCode, $rejection->errorCode(), $message);
            }
        };

        // draft: only submission is legal.
        $draft = $this->register($registrar, 'canon-doc-matrix-1');
        $refuse(fn () => $transition->verify($verifier, $this->fresh($draft), true, 'Premature verdict.', 'canon-doc-matrix-v1'), 'documents.verify_wrong_state', 'draft must not accept a verdict');
        $refuse(fn () => $transition->activate($verifier, $this->fresh($draft), 'canon-doc-matrix-a1'), 'documents.transition_forbidden', 'draft must not activate');
        $refuse(fn () => $transition->expire($verifier, $this->fresh($draft), 'canon-doc-matrix-e1'), 'documents.transition_forbidden', 'draft must not expire');
        $refuse(fn () => $transition->archive($verifier, $this->fresh($draft), 'canon-doc-matrix-r1'), 'documents.transition_forbidden', 'draft must not archive');

        // submitted: no forward jump past the verdict.
        $this->submit($registrar, $draft, 'canon-doc-matrix-2');
        $refuse(fn () => $transition->activate($verifier, $this->fresh($draft), 'canon-doc-matrix-a2'), 'documents.transition_forbidden', 'submitted must not skip verification');
        $refuse(fn () => $transition->expire($verifier, $this->fresh($draft), 'canon-doc-matrix-e2'), 'documents.transition_forbidden', 'submitted must not expire');
        $refuse(fn () => $transition->archive($verifier, $this->fresh($draft), 'canon-doc-matrix-r2'), 'documents.transition_forbidden', 'submitted must not archive');

        // verified: only activation; no new version, no shortcut to expiry.
        $transition->verify($verifier, $this->fresh($draft), true, 'Matrix verdict.', 'canon-doc-matrix-3');
        $refuse(fn () => $this->submit($registrar, $this->fresh($draft), 'canon-doc-matrix-4'), 'documents.transition_forbidden', 'verified must not accept a new version');
        $refuse(fn () => $transition->expire($verifier, $this->fresh($draft), 'canon-doc-matrix-e3'), 'documents.transition_forbidden', 'verified must not expire before activation');
        $refuse(fn () => $transition->archive($verifier, $this->fresh($draft), 'canon-doc-matrix-r3'), 'documents.transition_forbidden', 'verified must not archive before activation');

        // active: no re-submission, no second verdict, no re-activation.
        $transition->activate($verifier, $this->fresh($draft), 'canon-doc-matrix-5');
        $refuse(fn () => $this->submit($registrar, $this->fresh($draft), 'canon-doc-matrix-6'), 'documents.transition_forbidden', 'active must not accept a new version');
        $refuse(fn () => $transition->verify($verifier, $this->fresh($draft), true, 'Late verdict.', 'canon-doc-matrix-v2'), 'documents.verify_wrong_state', 'active must not accept a verdict');
        $refuse(fn () => $transition->activate($verifier, $this->fresh($draft), 'canon-doc-matrix-a3'), 'documents.transition_forbidden', 'active must not re-activate');

        // archived is terminal for every verb.
        $transition->archive($verifier, $this->fresh($draft), 'canon-doc-matrix-7');
        $refuse(fn () => $this->submit($registrar, $this->fresh($draft), 'canon-doc-matrix-8'), 'documents.transition_forbidden', 'archived must not accept a new version');
        $refuse(fn () => $transition->verify($verifier, $this->fresh($draft), true, 'Posthumous verdict.', 'canon-doc-matrix-v3'), 'documents.verify_wrong_state', 'archived must not accept a verdict');
        $refuse(fn () => $transition->activate($verifier, $this->fresh($draft), 'canon-doc-matrix-a4'), 'documents.transition_forbidden', 'archived must not activate');
        $refuse(fn () => $transition->expire($verifier, $this->fresh($draft), 'canon-doc-matrix-e4'), 'documents.transition_forbidden', 'archived must not expire');
        $refuse(fn () => $transition->archive($verifier, $this->fresh($draft), 'canon-doc-matrix-r4'), 'documents.transition_forbidden', 'archived must not re-archive');
        $this->assertDatabaseHas('documents', ['id' => $draft->id, 'lifecycle_state' => 'archived']);
    }

    public function test_unauthorized_acts_are_denied_and_audited_without_moving_any_state(): void
    {
        $nobody = $this->actorWithoutAnyCapability('canon-doc-nobody');

        try {
            app(RegisterDocument::class)->register($nobody, $this->subjectId, $this->classificationId, 'Stolen evidence', 'hash-x', 'storage/x', 'canon-doc-deny-1');
            $this->fail('registration without authority must be denied');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('documents.register_denied', $denial->errorCode());
        }
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.register.denied', 'actor_id' => 'canon-doc-nobody']);
        $this->assertSame(0, Document::query()->where('title', 'Stolen evidence')->count());

        // A register-only actor may append versions but never verdicts.
        $registrar = $this->registrar('canon-doc-registrar-4');
        $document = $this->register($registrar, 'canon-doc-deny-2');
        $this->submit($registrar, $document, 'canon-doc-deny-3');
        try {
            app(TransitionDocument::class)->verify($registrar, $this->fresh($document), true, 'Verdict beyond authority.', 'canon-doc-deny-4');
            $this->fail('verification without the verify capability must be denied');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('documents.verify_denied', $denial->errorCode());
        }
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.verify.denied', 'actor_id' => 'canon-doc-registrar-4']);
        $this->assertSame(0, DB::table('document_verifications')->where('document_id', $document->id)->count());
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'submitted']);
    }
}
