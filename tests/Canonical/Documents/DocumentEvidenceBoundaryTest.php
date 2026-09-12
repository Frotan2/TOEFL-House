<?php

declare(strict_types=1);

namespace Tests\Canonical\Documents;

use App\Modules\Documents\Commands\DecideRetention;
use App\Modules\Documents\Commands\DefineDocumentClassification;
use App\Modules\Documents\Commands\RegisterDocument;
use App\Modules\Documents\Commands\TransitionDocument;
use App\Modules\Documents\Models\Document;
use App\Support\Authorization\Actor;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical document evidence boundaries: append-only persistence against
 * raw SQL, the one-verdict-per-version uniqueness boundary, idempotent
 * registration under replay and payload conflict, and retention decisions
 * that follow the category rule while never violating the lifecycle.
 *
 * Every fixture is produced by the production commands; every assertion is
 * against persisted state (rows, audit events, transactional outbox).
 */
final class DocumentEvidenceBoundaryTest extends CanonicalTestCase
{
    private Actor $officer;

    private string $classificationId;

    private string $subjectId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->officer = $this->actorWith('canon-doc-officer', ['documents.classify', 'documents.register', 'documents.verify', 'documents.retention']);
        $this->classificationId = app(DefineDocumentClassification::class)->defineClassification(
            $this->officer, 'canon-boundary', 'Identity', 'restricted', 'canon-doc-boundary-class-1'
        )['classification_id'];
        $this->subjectId = 'canon-doc-boundary-subject';
        $this->personWithAuthority($this->subjectId, [], $this->sharedBranchId());
    }

    private function register(Actor $registrar, string $key): Document
    {
        $documentId = app(RegisterDocument::class)->register(
            $registrar, $this->subjectId, $this->classificationId, 'Canonical boundary evidence', 'hash-'.$key, 'storage/canonical/'.$key, $key
        )['document_id'];

        return Document::query()->findOrFail($documentId);
    }

    private function submitted(Actor $registrar, string $key): Document
    {
        $document = $this->register($registrar, $key.'-register');
        app(TransitionDocument::class)->submit($registrar, $document, 'hash-'.$key.'-v2', 'storage/canonical/'.$key.'-v2', $key.'-submit');

        return Document::query()->findOrFail($document->id);
    }

    public function test_evidence_rows_survive_raw_rewrite_attempts(): void
    {
        $registrar = $this->actorWith('canon-doc-boundary-registrar', ['documents.register']);
        $document = $this->submitted($registrar, 'canon-doc-raw-1');
        app(TransitionDocument::class)->verify($this->officer, $document, true, 'Boundary verdict.', 'canon-doc-raw-2');

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

        // Versions are immutable: correction is a new version, never an edit.
        $attack('canon_raw_1', fn () => DB::statement("UPDATE document_versions SET content_hash = 'tampered' WHERE document_id = ?", [$document->id]),
            'document versions are immutable', 'document versions must be immutable even against raw SQL');
        $attack('canon_raw_2', fn () => DB::statement('DELETE FROM document_versions WHERE document_id = ?', [$document->id]),
            'document versions are immutable', 'document versions must never be deleted');

        // Verdicts are append-only evidence.
        $attack('canon_raw_3', fn () => DB::statement("UPDATE document_verifications SET result = 'fail' WHERE document_id = ?", [$document->id]),
            'document_verifications is append-only', 'verification verdicts must be append-only');

        // Retention decisions are append-only outcomes.
        app(DefineDocumentClassification::class)->defineRetentionRule($this->officer, 'canon-boundary', 365, 'canonical-statute', null, 'canon-doc-raw-rule');
        $decision = app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-raw-3');
        $attack('canon_raw_4', fn () => DB::statement("UPDATE retention_decisions SET action = 'archive' WHERE id = ?", [$decision['decision_id']]),
            'retention_decisions is append-only', 'retention decisions must be append-only');
        $attack('canon_raw_5', fn () => DB::statement('DELETE FROM retention_decisions WHERE id = ?', [$decision['decision_id']]),
            'retention_decisions is append-only', 'retention decisions must never be deleted');

        // The recorded evidence is unchanged after every attack.
        $this->assertSame(2, DB::table('document_versions')->where('document_id', $document->id)->count());
        $this->assertDatabaseHas('document_versions', ['document_id' => $document->id, 'version_no' => 1, 'content_hash' => 'hash-canon-doc-raw-1-register']);
        $this->assertDatabaseHas('document_verifications', ['document_id' => $document->id, 'result' => 'pass']);
        $this->assertDatabaseHas('retention_decisions', ['id' => $decision['decision_id'], 'action' => 'retain']);
    }

    public function test_the_verdict_uniqueness_boundary_rejects_a_contradictory_second_verdict(): void
    {
        $registrar = $this->actorWith('canon-doc-boundary-registrar-2', ['documents.register']);
        $document = $this->submitted($registrar, 'canon-doc-unique-1');
        $verdict = app(TransitionDocument::class)->verify($this->officer, $document, true, 'Recorded verdict.', 'canon-doc-unique-2');

        // The command layer cannot produce a second verdict for the same
        // version (the state moved); an out-of-band writer meets the named
        // unique index instead of silently storing a contradiction.
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('document_verifications')->insert([
            'id' => RandomIdentifier::new(),
            'document_id' => $document->id,
            'version_no' => $verdict['version_no'],
            'verifier_person_id' => 'canon-doc-officer',
            'result' => 'fail',
            'reason' => 'contradictory out-of-band verdict',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_registration_replays_idempotently_and_rejects_payload_conflicts(): void
    {
        $registrar = $this->actorWith('canon-doc-boundary-registrar-3', ['documents.register']);
        $payload = ['subject_person_id' => $this->subjectId, 'classification_id' => $this->classificationId, 'title' => 'Replayed evidence', 'content_hash' => 'hash-replay', 'storage_ref' => 'storage/replay'];
        $first = app(RegisterDocument::class)->register($registrar, $payload['subject_person_id'], $payload['classification_id'], $payload['title'], $payload['content_hash'], $payload['storage_ref'], 'canon-doc-replay-key');
        $replay = app(RegisterDocument::class)->register($registrar, $payload['subject_person_id'], $payload['classification_id'], $payload['title'], $payload['content_hash'], $payload['storage_ref'], 'canon-doc-replay-key');

        $this->assertSame($first['document_id'], $replay['document_id']);
        $this->assertSame($first['correlation_id'], $replay['correlation_id']);
        $this->assertSame(1, Document::query()->where('title', 'Replayed evidence')->count());
        $this->assertSame(1, DB::table('document_versions')->where('document_id', $first['document_id'])->count());

        // The same key with a different payload is a conflict, never a merge.
        try {
            app(RegisterDocument::class)->register($registrar, $payload['subject_person_id'], $payload['classification_id'], 'A different document', $payload['content_hash'], $payload['storage_ref'], 'canon-doc-replay-key');
            $this->fail('reusing an idempotency key with a different payload must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('idempotency.conflicting_payload', $rejection->errorCode());
        }
        $this->assertSame(0, Document::query()->where('title', 'A different document')->count());
    }

    public function test_retention_follows_the_category_rule_and_archives_only_from_a_legal_state(): void
    {
        $registrar = $this->actorWith('canon-doc-boundary-registrar-4', ['documents.register']);
        $document = $this->submitted($registrar, 'canon-doc-retention-1');
        app(TransitionDocument::class)->verify($this->officer, $document, true, 'Retention verdict.', 'canon-doc-retention-2');
        app(TransitionDocument::class)->activate($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-retention-3');

        // Without a rule the decision fails closed.
        try {
            app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-retention-4');
            $this->fail('retention without a category rule must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('documents.retention_rule_missing', $rejection->errorCode());
        }
        $this->assertSame(0, DB::table('retention_decisions')->where('document_id', $document->id)->count());

        app(DefineDocumentClassification::class)->defineRetentionRule($this->officer, 'canon-boundary', 365, 'canonical-statute', 'operational hold', 'canon-doc-retention-rule');

        // Before the due date the decision retains and changes nothing.
        $early = app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-retention-5');
        $this->assertSame('retain', $early['action']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'active']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.retention.decide', 'target_id' => $early['decision_id']]);

        // After the due date the decision archives through the lifecycle.
        DB::statement('UPDATE documents SET created_at = ? WHERE id = ?', ['2020-01-01 10:00:00', $document->id]);
        $late = app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-retention-6');
        $this->assertSame('archive', $late['action']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'archived']);
        $this->assertDatabaseHas('retention_decisions', ['document_id' => $document->id, 'action' => 'retain', 'basis' => 'canonical-statute']);
        $this->assertDatabaseHas('retention_decisions', ['document_id' => $document->id, 'action' => 'archive', 'decided_by' => 'canon-doc-officer']);

        // Deciding again on the archived document appends an honest decision
        // without attempting an illegal transition.
        $again = app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($document->id), 'canon-doc-retention-7');
        $this->assertSame('archive', $again['action']);
        $this->assertSame(3, DB::table('retention_decisions')->where('document_id', $document->id)->count());
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'lifecycle_state' => 'archived']);
    }

    public function test_a_due_retention_decision_cannot_archive_a_non_archivable_state(): void
    {
        $registrar = $this->actorWith('canon-doc-boundary-registrar-5', ['documents.register']);
        app(DefineDocumentClassification::class)->defineRetentionRule($this->officer, 'canon-boundary', 30, 'canonical-statute', null, 'canon-doc-draft-rule');
        $draft = $this->register($registrar, 'canon-doc-draft-1');
        DB::statement('UPDATE documents SET created_at = ? WHERE id = ?', ['2020-01-01 10:00:00', $draft->id]);

        // The rule says archive; the lifecycle says a draft may not be
        // archived. The lifecycle wins and the whole decision rolls back:
        // no half-recorded outcome survives.
        try {
            app(DecideRetention::class)->decide($this->officer, Document::query()->findOrFail($draft->id), 'canon-doc-draft-2');
            $this->fail('a due retention decision must not archive a draft');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('documents.transition_forbidden', $rejection->errorCode());
        }
        $this->assertSame(0, DB::table('retention_decisions')->where('document_id', $draft->id)->count());
        $this->assertDatabaseHas('documents', ['id' => $draft->id, 'lifecycle_state' => 'draft']);
    }
}
