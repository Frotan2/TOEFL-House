<?php

declare(strict_types=1);

namespace App\Modules\Documents\Commands;

use App\Modules\Audit\AttemptedOperation;
use App\Modules\Audit\AuditRecorder;
use App\Modules\Documents\Domain\DocumentLifecycle;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentVerification;
use App\Modules\Documents\Models\DocumentVersion;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\Actor;
use App\Support\Authorization\PersonBranchScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Idempotency\IdempotentExecution;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;

/**
 * Explicit document lifecycle transitions. Registration and version
 * submission are both registrar/uploader acts under the single
 * {@see RegisterDocument::CAPABILITY} authority; the audit operation and
 * denial code for the submission act remain documents.submit*. Verification
 * is an independent authority (documents.verify): it records the verifier,
 * result, and reason as append-only evidence, the verifier may not be the
 * uploader of the version under review, and only a passing verification moves
 * the document forward. A failed verification leaves the document rejected
 * until the registrar submits a new version.
 */
final class TransitionDocument
{
    public const CAPABILITY = 'documents.verify';

    public function __construct(
        private readonly AccessDecision $access,
        private readonly IdempotentExecution $idempotency,
        private readonly AuditRecorder $audit,
        private readonly AttemptedOperation $attemptedOperation,
    ) {}

    /** @return array{document_id: string, version_no: int, lifecycle_state: string, correlation_id: string} */
    public function submit(Actor $actor, Document $document, string $contentHash, string $storageRef, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', ['documents.submit', $document->id, trim($contentHash), trim($storageRef), $actor->actorId]));

        try {
            return $this->idempotency->execute('documents.submit', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($actor, $document, $contentHash, $storageRef): array {
                    /** @var Document $locked */
                    $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                    $scope = PersonBranchScope::resolve($locked->subject_person_id);
                    // Submitting a version is the registrar/uploader act of
                    // appending evidence: the same authority that registers
                    // version 1 (documents.register) authorizes later
                    // versions. The verifier capability stays independent
                    // (documents.verify); the uploader-vs-verifier separation
                    // of duties is enforced when a version is verified. The
                    // operation keeps its own audit namespace and denial code
                    // (documents.submit*) so evidence records name the act.
                    $outcome = $this->access->decide($actor, RegisterDocument::CAPABILITY, $scope);
                    if (! $outcome->allowed) {
                        throw AuthorizationDenied::forCode('documents.submit_denied', $outcome->reason);
                    }
                    DocumentLifecycle::requireTransition($locked->lifecycle_state, DocumentLifecycle::STATE_SUBMITTED);
                    if (trim($contentHash) === '' || trim($storageRef) === '') {
                        throw BusinessRejection::forCode('documents.content_missing', 'a submitted document version requires content hash and storage reference');
                    }

                    /** @var int $maxVersion */
                    $maxVersion = (int) DocumentVersion::query()->where('document_id', $locked->id)->max('version_no');
                    $versionNo = $maxVersion + 1;
                    DocumentVersion::query()->create([
                        'id' => RandomIdentifier::new(),
                        'document_id' => $locked->id,
                        'version_no' => $versionNo,
                        'content_hash' => trim($contentHash),
                        'storage_ref' => trim($storageRef),
                        'uploaded_by' => $actor->actorId,
                    ]);

                    $before = ['lifecycle_state' => $locked->lifecycle_state];
                    $locked->forceFill(['lifecycle_state' => DocumentLifecycle::STATE_SUBMITTED]);
                    $locked->save();

                    $event = $this->audit->record($actor->actorId, 'documents.submit', 'document', $locked->id, $before, [
                        'lifecycle_state' => DocumentLifecycle::STATE_SUBMITTED, 'version_no' => $versionNo,
                        'branch_id' => $scope->branchId, 'organization_id' => $scope->organizationId,
                    ]);

                    return ['document_id' => $locked->id, 'version_no' => $versionNo, 'lifecycle_state' => DocumentLifecycle::STATE_SUBMITTED, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, 'documents.submit', 'document', $document->id);
        }
    }

    /** @return array{document_id: string, version_no: int, lifecycle_state: string, correlation_id: string} */
    public function verify(Actor $verifier, Document $document, bool $passes, string $reason, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', ['documents.verify', $document->id, $passes ? 'pass' : 'fail', trim($reason), $verifier->actorId]));

        try {
            return $this->idempotency->execute('documents.verify', $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($verifier, $document, $passes, $reason): array {
                    /** @var Document $locked */
                    $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                    $scope = PersonBranchScope::resolve($locked->subject_person_id);
                    $outcome = $this->access->decide($verifier, self::CAPABILITY, $scope);
                    if (! $outcome->allowed) {
                        throw AuthorizationDenied::forCode('documents.verify_denied', $outcome->reason);
                    }
                    if ($locked->lifecycle_state !== DocumentLifecycle::STATE_SUBMITTED) {
                        throw BusinessRejection::forCode('documents.verify_wrong_state', sprintf('only a submitted document can be verified, state is %s', $locked->lifecycle_state));
                    }

                    /** @var DocumentVersion $currentVersion */
                    $currentVersion = DocumentVersion::query()->where('document_id', $locked->id)
                        ->orderByDesc('version_no')->firstOrFail();
                    if (trim((string) $currentVersion->uploaded_by) === $verifier->actorId) {
                        throw BusinessRejection::forCode('documents.verifier_is_uploader', 'the verifier may not be the uploader of the version under review');
                    }
                    if (trim($reason) === '') {
                        throw BusinessRejection::forCode('documents.verify_reason_missing', 'verification requires a reason');
                    }

                    DocumentVerification::query()->create([
                        'id' => RandomIdentifier::new(),
                        'document_id' => $locked->id,
                        'version_no' => (int) $currentVersion->version_no,
                        'verifier_person_id' => $verifier->actorId,
                        'result' => $passes ? 'pass' : 'fail',
                        'reason' => trim($reason),
                    ]);

                    $toState = $passes ? DocumentLifecycle::STATE_VERIFIED : DocumentLifecycle::STATE_REJECTED;
                    $before = ['lifecycle_state' => $locked->lifecycle_state];
                    $locked->forceFill(['lifecycle_state' => $toState]);
                    $locked->save();

                    $event = $this->audit->record($verifier->actorId, 'documents.verify', 'document', $locked->id, $before, [
                        'lifecycle_state' => $toState,
                        'version_no' => (int) $currentVersion->version_no,
                        'result' => $passes ? 'pass' : 'fail',
                        'reason' => trim($reason),
                        'branch_id' => $scope->branchId, 'organization_id' => $scope->organizationId,
                    ]);

                    return ['document_id' => $locked->id, 'version_no' => (int) $currentVersion->version_no, 'lifecycle_state' => $toState, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $verifier, 'documents.verify', 'document', $document->id);
        }
    }

    /** @return array{document_id: string, lifecycle_state: string, correlation_id: string} */
    public function activate(Actor $actor, Document $document, string $idempotencyKey): array
    {
        return $this->simple($actor, $document, DocumentLifecycle::STATE_ACTIVE, 'activate', $idempotencyKey);
    }

    /** @return array{document_id: string, lifecycle_state: string, correlation_id: string} */
    public function expire(Actor $actor, Document $document, string $idempotencyKey): array
    {
        return $this->simple($actor, $document, DocumentLifecycle::STATE_EXPIRED, 'expire', $idempotencyKey);
    }

    /** @return array{document_id: string, lifecycle_state: string, correlation_id: string} */
    public function archive(Actor $actor, Document $document, string $idempotencyKey): array
    {
        return $this->simple($actor, $document, DocumentLifecycle::STATE_ARCHIVED, 'archive', $idempotencyKey);
    }

    /** @return array{document_id: string, lifecycle_state: string, correlation_id: string} */
    private function simple(Actor $actor, Document $document, string $toState, string $verb, string $idempotencyKey): array
    {
        $payload = hash('sha256', implode('|', ['documents.'.$verb, $document->id, $toState, $actor->actorId]));

        try {
            return $this->idempotency->execute('documents.'.$verb, $idempotencyKey, $payload,
                fn (): array => DB::transaction(function () use ($actor, $document, $toState, $verb): array {
                    /** @var Document $locked */
                    $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                    $scope = PersonBranchScope::resolve($locked->subject_person_id);
                    $outcome = $this->access->decide($actor, self::CAPABILITY, $scope);
                    if (! $outcome->allowed) {
                        throw AuthorizationDenied::forCode('documents.transition_denied', $outcome->reason);
                    }
                    DocumentLifecycle::requireTransition($locked->lifecycle_state, $toState);

                    $before = ['lifecycle_state' => $locked->lifecycle_state];
                    $locked->forceFill(['lifecycle_state' => $toState]);
                    $locked->save();

                    $event = $this->audit->record($actor->actorId, 'documents.'.$verb, 'document', $locked->id, $before, ['lifecycle_state' => $toState, 'branch_id' => $scope->branchId, 'organization_id' => $scope->organizationId]);

                    return ['document_id' => $locked->id, 'lifecycle_state' => $toState, 'correlation_id' => $event->correlation_id];
                }),
            );
        } catch (AuthorizationDenied $denial) {
            $this->attemptedOperation->deniedByActor($denial, $actor, 'documents.'.$verb, 'document', $document->id);
        }
    }
}
