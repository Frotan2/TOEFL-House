<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AttemptedOperation;
use App\Modules\Documents\Commands\DecideRetention;
use App\Modules\Documents\Commands\DefineDocumentClassification;
use App\Modules\Documents\Commands\RegisterDocument;
use App\Modules\Documents\Commands\TransitionDocument;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentClassification;
use App\Modules\Documents\Models\RetentionDecision;
use App\Modules\Documents\Models\RetentionRule;
use App\Modules\Documents\Queries\DocumentHistoryQuery;
use App\Modules\Identity\Models\Person;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\PersonBranchScope;
use App\Support\Errors\AuthorizationDenied;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical JSON transport for the Documents & Evidence workspace.
 *
 * Read visibility is a server-computed union of the concrete document
 * capabilities. A registrar, verifier, or retention officer can therefore
 * see documents in their own authorized branches without treating a branch
 * hint as authority. Each command remains responsible for its own final
 * authorization, lifecycle, separation-of-duties, idempotency and audit
 * decisions.
 */
final class DocumentsApiController extends Controller
{
    /** @var list<string> */
    private const READ_CAPABILITIES = [
        RegisterDocument::CAPABILITY,
        TransitionDocument::CAPABILITY,
        DecideRetention::CAPABILITY,
    ];

    public function workspace(): JsonResponse
    {
        $registrationBranches = $this->authorizedBranches(RegisterDocument::CAPABILITY);
        $verificationBranches = $this->authorizedBranches(TransitionDocument::CAPABILITY);
        $retentionBranches = $this->authorizedBranches(DecideRetention::CAPABILITY);
        $branchIds = array_values(array_unique(array_merge(
            $registrationBranches,
            $verificationBranches,
            $retentionBranches,
        ), SORT_STRING));
        sort($branchIds);

        $canClassify = app(AccessDecision::class)->decide(
            $this->actor(),
            DefineDocumentClassification::CAPABILITY,
            null,
        )->allowed;

        // Classification is an organization-scoped catalog operation. A
        // classifier with no branch-facing document work may still load the
        // catalog, but every other caller needs at least one concrete scoped
        // document capability before the response can expose any subjects.
        if ($branchIds === [] && ! $canClassify) {
            $this->denyWorkspaceRead();
        }

        $people = Person::query()
            ->where('verification_state', Person::VERIFICATION_VERIFIED)
            ->whereIn('home_branch_id', $branchIds)
            ->orderBy('legal_name')
            ->limit(300)
            ->get(['id', 'legal_name', 'home_branch_id']);
        $personIds = $people->pluck('id')->all();

        $documents = Document::query()
            ->whereIn('subject_person_id', $personIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'subject_person_id', 'classification_id', 'title', 'lifecycle_state', 'created_at', 'updated_at']);
        $documentIds = $documents->pluck('id')->all();

        $personBranchIds = $people->mapWithKeys(static fn (Person $person): array => [
            (string) $person->id => (string) $person->home_branch_id,
        ])->all();
        $retentionDecisions = RetentionDecision::query()
            ->whereIn('document_id', $documentIds)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get(['id', 'document_id', 'rule_id', 'action', 'basis', 'decided_by', 'created_at']);

        return response()->json(['data' => [
            'scope' => ['branch_ids' => $branchIds],
            // These are server-produced affordances only. The browser uses
            // them to avoid offering irrelevant controls; command handlers
            // always re-resolve authority against the authoritative target.
            'available_actions' => [
                'classify' => $canClassify,
                'register' => $registrationBranches !== [],
                'verify' => $verificationBranches !== [],
                'retention' => $retentionBranches !== [],
            ],
            'people' => $people->map(static fn (Person $person): array => [
                'id' => self::identifier($person->id),
                'legal_name' => (string) $person->legal_name,
                'branch_id' => self::identifier($person->home_branch_id),
            ])->values()->all(),
            'classifications' => DocumentClassification::query()
                ->orderBy('category')
                ->limit(200)
                ->get(['id', 'category', 'owner_module', 'access_class'])
                ->map(static fn (DocumentClassification $classification): array => [
                    'id' => self::identifier($classification->id),
                    'category' => (string) $classification->category,
                    'owner_module' => (string) $classification->owner_module,
                    'access_class' => (string) $classification->access_class,
                ])->values()->all(),
            'retention_rules' => RetentionRule::query()
                ->orderBy('category')
                ->limit(200)
                ->get(['id', 'category', 'retention_days', 'legal_basis', 'operational_basis'])
                ->map(static fn (RetentionRule $rule): array => [
                    'id' => self::identifier($rule->id),
                    'category' => (string) $rule->category,
                    'retention_days' => (int) $rule->retention_days,
                    'legal_basis' => (string) $rule->legal_basis,
                    'operational_basis' => $rule->operational_basis === null ? null : (string) $rule->operational_basis,
                ])->values()->all(),
            'documents' => $documents->map(static function (Document $document) use ($personBranchIds, $registrationBranches, $verificationBranches, $retentionBranches): array {
                $branchId = $personBranchIds[(string) $document->subject_person_id] ?? null;

                return [
                    'id' => self::identifier($document->id),
                    'subject_person_id' => self::identifier($document->subject_person_id),
                    'classification_id' => self::identifier($document->classification_id),
                    'title' => (string) $document->title,
                    'lifecycle_state' => (string) $document->lifecycle_state,
                    // Per-record affordances prevent a capability in branch A
                    // from appearing as a usable action for a record in branch B.
                    // They remain UX hints; each command checks again when run.
                    'available_actions' => [
                        'submit' => $branchId !== null && in_array($branchId, $registrationBranches, true),
                        'verify' => $branchId !== null && in_array($branchId, $verificationBranches, true),
                        'retention' => $branchId !== null && in_array($branchId, $retentionBranches, true),
                    ],
                    'created_at' => $document->created_at?->toISOString(),
                    'updated_at' => $document->updated_at?->toISOString(),
                ];
            })->values()->all(),
            // Storage references, content fingerprints, and verification
            // rationales intentionally stay out of the broad workspace
            // projection. An operator must request the separately authorized,
            // immutable history of an individual document to review evidence.
            'retention_decisions' => $retentionDecisions->map(static fn (RetentionDecision $decision): array => [
                'id' => self::identifier($decision->id),
                'document_id' => self::identifier($decision->document_id),
                'rule_id' => self::identifier($decision->rule_id),
                'action' => (string) $decision->action,
                'basis' => (string) $decision->basis,
                'decided_by' => self::identifier($decision->decided_by),
                'created_at' => $decision->created_at?->toISOString(),
            ])->values()->all(),
            'policy' => [
                'authority' => 'server_access_decision',
                'history' => 'append_only_versions_verifications_and_retention',
                'correction' => 'new_version_or_lifecycle_transition_only',
            ],
        ]]);
    }

    public function history(string $documentId): JsonResponse
    {
        /** @var Document $document */
        $document = Document::query()->findOrFail($documentId);
        $this->requireDocumentRead($document, 'documents.api.history');

        return response()->json(['data' => app(DocumentHistoryQuery::class)->documentHistory($document->id) + [
            'policy' => [
                'authority' => 'server_access_decision',
                'history' => 'immutable_append_only',
                'correction' => 'new_version_or_lifecycle_transition_only',
            ],
        ]]);
    }

    public function defineClassification(Request $request): JsonResponse
    {
        $input = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'owner_module' => ['required', 'string', 'max:120'],
            'access_class' => ['required', 'string', 'in:public,internal,confidential,restricted'],
        ]);

        return response()->json(['data' => app(DefineDocumentClassification::class)->defineClassification(
            $this->actor(),
            $input['category'],
            $input['owner_module'],
            $input['access_class'],
            $this->idempotencyKey('documents.classification.define'),
        )], 201);
    }

    public function defineRetentionRule(Request $request): JsonResponse
    {
        $input = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'retention_days' => ['required', 'integer', 'gt:0'],
            'legal_basis' => ['required', 'string', 'max:500'],
            'operational_basis' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['data' => app(DefineDocumentClassification::class)->defineRetentionRule(
            $this->actor(),
            $input['category'],
            (int) $input['retention_days'],
            $input['legal_basis'],
            (($input['operational_basis'] ?? '') !== '') ? $input['operational_basis'] : null,
            $this->idempotencyKey('documents.retention.rule'),
        )], 201);
    }

    public function register(Request $request): JsonResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string', 'max:36'],
            'classification_id' => ['required', 'string', 'max:36'],
            'title' => ['required', 'string', 'max:255'],
            'content_hash' => ['required', 'string', 'max:255'],
            'storage_ref' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(['data' => app(RegisterDocument::class)->register(
            $this->actor(),
            $input['subject_person_id'],
            $input['classification_id'],
            $input['title'],
            $input['content_hash'],
            $input['storage_ref'],
            $this->idempotencyKey('documents.register'),
        )], 201);
    }

    public function submit(Request $request, string $documentId): JsonResponse
    {
        $input = $request->validate([
            'content_hash' => ['required', 'string', 'max:255'],
            'storage_ref' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(['data' => app(TransitionDocument::class)->submit(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $input['content_hash'],
            $input['storage_ref'],
            $this->idempotencyKey('documents.submit'),
        )]);
    }

    public function verify(Request $request, string $documentId): JsonResponse
    {
        $input = $request->validate([
            'result' => ['required', 'in:pass,fail'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(['data' => app(TransitionDocument::class)->verify(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $input['result'] === 'pass',
            $input['reason'],
            $this->idempotencyKey('documents.verify'),
        )]);
    }

    public function activate(string $documentId): JsonResponse
    {
        return response()->json(['data' => app(TransitionDocument::class)->activate(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $this->idempotencyKey('documents.activate'),
        )]);
    }

    public function expire(string $documentId): JsonResponse
    {
        return response()->json(['data' => app(TransitionDocument::class)->expire(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $this->idempotencyKey('documents.expire'),
        )]);
    }

    public function archive(string $documentId): JsonResponse
    {
        return response()->json(['data' => app(TransitionDocument::class)->archive(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $this->idempotencyKey('documents.archive'),
        )]);
    }

    public function decideRetention(string $documentId): JsonResponse
    {
        return response()->json(['data' => app(DecideRetention::class)->decide(
            $this->actor(),
            Document::query()->findOrFail($documentId),
            $this->idempotencyKey('documents.retention.decide'),
        )]);
    }

    private static function identifier(mixed $value): string
    {
        return trim((string) $value);
    }

    private function requireDocumentRead(Document $document, string $operation): void
    {
        $scope = PersonBranchScope::resolve($document->subject_person_id);
        foreach (self::READ_CAPABILITIES as $capability) {
            if (app(AccessDecision::class)->decide($this->actor(), $capability, $scope)->allowed) {
                return;
            }
        }

        app(AttemptedOperation::class)->deniedByActor(
            AuthorizationDenied::forCode('api.read_denied', 'this document is outside your authorized document capability scope'),
            $this->actor(),
            $operation,
            'document',
            $document->id,
        );
    }

    private function denyWorkspaceRead(): never
    {
        app(AttemptedOperation::class)->deniedByActor(
            AuthorizationDenied::forCode('api.organization_read_denied', 'no effective document capability scope is available for this workspace'),
            $this->actor(),
            'documents.api.workspace',
            'documents_workspace',
            'index',
        );
    }
}
