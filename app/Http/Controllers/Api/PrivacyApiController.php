<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Audit\AttemptedOperation;
use App\Modules\Identity\Models\Person;
use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\ExportSubjectData;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\RecordDisclosure;
use App\Modules\Privacy\Commands\TransitionConsent;
use App\Modules\Privacy\Domain\ConsentLifecycle;
use App\Modules\Privacy\Domain\ExportApprovalChain;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\ConsentPurpose;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Modules\Privacy\Models\PrivacyExportRequest;
use App\Modules\Privacy\Queries\SubjectPrivacyQuery;
use App\Support\Authorization\AccessDecision;
use App\Support\Authorization\PersonBranchScope;
use App\Support\Errors\AuthorizationDenied;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical JSON transport for the Privacy & Consent workspace.
 *
 * Read visibility is a server-computed union of the concrete privacy
 * capabilities: a consent officer, a disclosure officer, an exporter or a
 * bulk-export approver sees the subjects inside their own authorized
 * branches, and a caller with none of them is denied with an audited
 * attempt. A branch hint is never authority — every mutation re-enters the
 * owning command, which re-resolves authority, subject provenance,
 * lifecycle legality, separation of duties, idempotency and audit evidence.
 *
 * The projection is deliberately minimal: consent evidence locators stay out
 * of the broad list and are only returned by the separately authorized
 * per-subject dossier, exactly as document storage references stay out of
 * the Documents workspace.
 */
final class PrivacyApiController extends Controller
{
    /** @var list<string> */
    private const READ_CAPABILITIES = [
        RecordConsent::CAPABILITY,
        RecordDisclosure::CAPABILITY,
        ExportSubjectData::CAPABILITY,
        ExportSubjectData::CAPABILITY_BULK_APPROVE,
    ];

    public function workspace(): JsonResponse
    {
        $consentBranches = $this->authorizedBranches(RecordConsent::CAPABILITY);
        $disclosureBranches = $this->authorizedBranches(RecordDisclosure::CAPABILITY);
        $exportBranches = $this->authorizedBranches(ExportSubjectData::CAPABILITY);
        $approvalBranches = $this->authorizedBranches(ExportSubjectData::CAPABILITY_BULK_APPROVE);
        $branchIds = array_values(array_unique(array_merge(
            $consentBranches,
            $disclosureBranches,
            $exportBranches,
            $approvalBranches,
        ), SORT_STRING));
        sort($branchIds);

        // Purpose definition is an organization-rooted catalog operation. A
        // purpose definer with no branch-facing privacy work may still load
        // the catalog, but every other caller needs at least one concrete
        // scoped privacy capability before any subject is exposed.
        $canDefinePurpose = app(AccessDecision::class)->decide(
            $this->actor(),
            DefineConsentPurpose::CAPABILITY,
            null,
        )->allowed;
        if ($branchIds === [] && ! $canDefinePurpose) {
            $this->denyWorkspaceRead();
        }

        $approverOrganizations = $this->authorizedOrganizations(ExportSubjectData::CAPABILITY_BULK_APPROVE);
        $exportOrganizations = $this->authorizedOrganizations(ExportSubjectData::CAPABILITY);
        $actorId = $this->actor()->actorId;

        $people = Person::query()
            ->where('verification_state', Person::VERIFICATION_VERIFIED)
            ->whereIn('home_branch_id', $branchIds)
            ->orderBy('legal_name')
            ->limit(300)
            ->get(['id', 'legal_name', 'home_branch_id']);
        $personIds = $people->pluck('id')->map(static fn (mixed $id): string => self::identifier($id))->all();

        $consents = Consent::query()
            ->whereIn('subject_person_id', $personIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'subject_person_id', 'purpose_id', 'lifecycle_state', 'effective_from', 'effective_to', 'created_at', 'updated_at']);
        $consentIds = $consents->pluck('id')->map(static fn (mixed $id): string => self::identifier($id))->all();

        return response()->json(['data' => [
            'scope' => ['branch_ids' => $branchIds],
            // Server-produced affordances only. They keep irrelevant or
            // illegal controls out of the operator's way; command handlers
            // always re-resolve authority against the authoritative target.
            'available_actions' => [
                'define_purpose' => $canDefinePurpose,
                'consent' => $consentBranches !== [],
                'disclose' => $disclosureBranches !== [],
                'export' => $exportBranches !== [],
                'approve_bulk_export' => $approverOrganizations !== [],
            ],
            'actor' => ['person_id' => $actorId],
            'people' => $people->map(static fn (Person $person): array => [
                'id' => self::identifier($person->id),
                'legal_name' => (string) $person->legal_name,
                'branch_id' => self::identifier($person->home_branch_id),
            ])->values()->all(),
            'purposes' => ConsentPurpose::query()
                ->orderBy('name')
                ->orderBy('channel')
                ->limit(200)
                ->get(['id', 'name', 'channel', 'category'])
                ->map(static fn (ConsentPurpose $purpose): array => [
                    'id' => self::identifier($purpose->id),
                    'name' => (string) $purpose->name,
                    'channel' => (string) $purpose->channel,
                    'category' => (string) $purpose->category,
                ])->values()->all(),
            'consents' => $consents->map(function (Consent $consent) use ($consentBranches, $actorId): array {
                $subjectId = self::identifier($consent->subject_person_id);
                $state = (string) $consent->lifecycle_state;

                return [
                    'id' => self::identifier($consent->id),
                    'subject_person_id' => $subjectId,
                    'purpose_id' => self::identifier($consent->purpose_id),
                    'lifecycle_state' => $state,
                    'effective_from' => self::day($consent->effective_from),
                    'effective_to' => $consent->effective_to === null ? null : self::day($consent->effective_to),
                    // Consent evidence locators are write-only for the broad
                    // projection: an officer reviews them in the separately
                    // authorized subject dossier, never in a list view.
                    'available_actions' => self::consentAffordances(
                        $state,
                        $consentBranches !== [],
                        $subjectId === $actorId,
                    ),
                    'created_at' => $consent->created_at?->toISOString(),
                    'updated_at' => $consent->updated_at?->toISOString(),
                ];
            })->values()->all(),
            'revocations' => ConsentRevocation::query()
                ->whereIn('consent_id', $consentIds)
                ->orderByDesc('created_at')
                ->limit(300)
                ->get(['id', 'consent_id', 'revoked_by', 'scope', 'effect', 'created_at'])
                ->map(static fn (ConsentRevocation $revocation): array => [
                    'id' => self::identifier($revocation->id),
                    'consent_id' => self::identifier($revocation->consent_id),
                    'revoked_by' => self::identifier($revocation->revoked_by),
                    'scope' => (string) $revocation->scope,
                    'effect' => (string) $revocation->effect,
                    'created_at' => $revocation->created_at?->toISOString(),
                ])->values()->all(),
            'disclosures' => Disclosure::query()
                ->whereIn('subject_person_id', $personIds)
                ->orderByDesc('created_at')
                ->limit(300)
                ->get(['id', 'subject_person_id', 'recipient', 'purpose', 'authority', 'scope_type', 'scope_id', 'disclosed_category', 'disclosed_by', 'created_at'])
                ->map(static fn (Disclosure $disclosure): array => [
                    'id' => self::identifier($disclosure->id),
                    'subject_person_id' => self::identifier($disclosure->subject_person_id),
                    'recipient' => (string) $disclosure->recipient,
                    'purpose' => (string) $disclosure->purpose,
                    'authority' => (string) $disclosure->authority,
                    'scope' => $disclosure->scope_type.':'.self::identifier($disclosure->scope_id),
                    'disclosed_category' => (string) $disclosure->disclosed_category,
                    'disclosed_by' => self::identifier($disclosure->disclosed_by),
                    'created_at' => $disclosure->created_at?->toISOString(),
                ])->values()->all(),
            'export_requests' => PrivacyExportRequest::query()
                ->whereIn('subject_person_id', $personIds)
                ->orderByDesc('created_at')
                ->limit(300)
                ->get(['id', 'subject_person_id', 'purpose', 'organization_id', 'lifecycle_state', 'requested_by', 'approver_one_id', 'approver_two_id', 'exported_by', 'disclosure_id', 'created_at', 'updated_at'])
                ->map(static fn (PrivacyExportRequest $request): array => [
                    'id' => self::identifier($request->id),
                    'subject_person_id' => self::identifier($request->subject_person_id),
                    'purpose' => (string) $request->purpose,
                    'organization_id' => self::identifier($request->organization_id),
                    'lifecycle_state' => (string) $request->lifecycle_state,
                    'requested_by' => self::identifier($request->requested_by),
                    'approver_one_id' => $request->approver_one_id === null ? null : self::identifier($request->approver_one_id),
                    'approver_two_id' => $request->approver_two_id === null ? null : self::identifier($request->approver_two_id),
                    'exported_by' => $request->exported_by === null ? null : self::identifier($request->exported_by),
                    'disclosure_id' => $request->disclosure_id === null ? null : self::identifier($request->disclosure_id),
                    // The staged chain is projected from the same registry the
                    // command signs against: an actor who already signed first
                    // is never offered the second signature.
                    'available_actions' => [
                        'approve' => in_array(self::identifier($request->organization_id), $approverOrganizations, true)
                            && ExportApprovalChain::acceptsSignature((string) $request->lifecycle_state, $request->approver_one_id, $actorId),
                        'execute' => in_array(self::identifier($request->organization_id), $exportOrganizations, true)
                            && ExportApprovalChain::allowsExecution((string) $request->lifecycle_state),
                    ],
                    'created_at' => $request->created_at?->toISOString(),
                    'updated_at' => $request->updated_at?->toISOString(),
                ])->values()->all(),
            'policy' => [
                'authority' => 'server_access_decision',
                'history' => 'append_only_evidence',
                'correction' => 'lifecycle_or_new_evidence_only',
                'erasure' => 'revocation_expiry_and_archive_never_delete',
            ],
        ]]);
    }

    public function subject(string $subjectId, Request $request): JsonResponse
    {
        $scope = PersonBranchScope::resolve($subjectId);
        $this->requireSubjectRead($subjectId, $scope->branchId);
        $validated = $request->validate(['as_of' => ['nullable', 'date']]);
        $asOf = $validated['as_of'] ?? null;
        $date = is_string($asOf) && $asOf !== '' ? CarbonImmutable::parse($asOf) : null;

        return response()->json(['data' => app(SubjectPrivacyQuery::class)->subjectProfile($subjectId, $date) + [
            'provenance' => [
                'organization_id' => $scope->organizationId,
                'campus_id' => $scope->campusId,
                'branch_id' => $scope->branchId,
                'department_id' => $scope->departmentId,
            ],
            'policy' => [
                'authority' => 'server_access_decision',
                'history' => 'append_only_evidence',
                'correction' => 'lifecycle_or_new_evidence_only',
                'erasure' => 'revocation_expiry_and_archive_never_delete',
            ],
        ]]);
    }

    public function definePurpose(Request $request): JsonResponse
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'channel' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
        ]);

        return response()->json(['data' => app(DefineConsentPurpose::class)->define(
            $this->actor(),
            $input['name'],
            $input['channel'],
            $input['category'],
            $this->idempotencyKey('privacy.purpose.define'),
        )], 201);
    }

    public function recordConsent(Request $request): JsonResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string', 'max:36'],
            'purpose_id' => ['required', 'string', 'max:36'],
            'evidence_ref' => ['required', 'string', 'max:500'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
        ]);

        return response()->json(['data' => app(RecordConsent::class)->record(
            $this->actor(),
            $input['subject_person_id'],
            $input['purpose_id'],
            $input['evidence_ref'],
            CarbonImmutable::parse($input['effective_from']),
            (($input['effective_to'] ?? '') !== '') ? CarbonImmutable::parse($input['effective_to']) : null,
            $this->idempotencyKey('privacy.consent.record'),
        )], 201);
    }

    public function submitConsent(string $consentId): JsonResponse
    {
        return $this->consentTransition($consentId, 'submit', fn (TransitionConsent $command, Consent $consent, string $key): array => $command->submit($this->actor(), $consent, $key));
    }

    public function verifyConsent(string $consentId): JsonResponse
    {
        return $this->consentTransition($consentId, 'verify', fn (TransitionConsent $command, Consent $consent, string $key): array => $command->verify($this->actor(), $consent, $key));
    }

    public function activateConsent(string $consentId): JsonResponse
    {
        return $this->consentTransition($consentId, 'activate', fn (TransitionConsent $command, Consent $consent, string $key): array => $command->activate($this->actor(), $consent, $key));
    }

    public function expireConsent(string $consentId): JsonResponse
    {
        return $this->consentTransition($consentId, 'expire', fn (TransitionConsent $command, Consent $consent, string $key): array => $command->expire($this->actor(), $consent, $key));
    }

    public function archiveConsent(string $consentId): JsonResponse
    {
        return $this->consentTransition($consentId, 'archive', fn (TransitionConsent $command, Consent $consent, string $key): array => $command->archive($this->actor(), $consent, $key));
    }

    public function revokeConsent(Request $request, string $consentId): JsonResponse
    {
        $input = $request->validate([
            'scope' => ['required', 'string', 'max:200'],
            'effect' => ['required', 'string', 'max:200'],
        ]);

        return $this->consentTransition(
            $consentId,
            'revoke',
            fn (TransitionConsent $command, Consent $consent, string $key): array => $command->revoke($this->actor(), $consent, $input['scope'], $input['effect'], $key),
        );
    }

    public function recordDisclosure(Request $request): JsonResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string', 'max:36'],
            'recipient' => ['required', 'string', 'max:200'],
            'purpose' => ['required', 'string', 'max:500'],
            'authority' => ['required', 'string', 'max:120'],
            'scope_type' => ['required', 'in:organization,campus,branch,department,subject'],
            'scope_id' => ['required', 'string', 'max:36'],
            'disclosed_category' => ['required', 'string', 'max:200'],
        ]);

        return response()->json(['data' => app(RecordDisclosure::class)->disclose(
            $this->actor(),
            $input['subject_person_id'],
            $input['recipient'],
            $input['purpose'],
            $input['authority'],
            $input['scope_type'],
            $input['scope_id'],
            $input['disclosed_category'],
            $this->idempotencyKey('privacy.disclose'),
        )], 201);
    }

    public function exportSubject(Request $request): JsonResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string', 'max:36'],
            'purpose' => ['required', 'string', 'max:500'],
            'scope_type' => ['required', 'in:campus,branch,department,subject'],
            'scope_id' => ['required', 'string', 'max:36'],
        ]);

        return response()->json(['data' => app(ExportSubjectData::class)->export(
            $this->actor(),
            $input['subject_person_id'],
            $input['purpose'],
            $input['scope_type'],
            $input['scope_id'],
            $this->idempotencyKey('privacy.export'),
        )], 201);
    }

    /**
     * An organization-wide export is requested against the subject's own
     * server-resolved organization. The browser never supplies an
     * organization id, so it cannot aim a bulk request at another tenant;
     * the command re-verifies the match anyway.
     */
    public function requestExport(Request $request): JsonResponse
    {
        $input = $request->validate([
            'subject_person_id' => ['required', 'string', 'max:36'],
            'purpose' => ['required', 'string', 'max:500'],
        ]);
        $organizationId = PersonBranchScope::resolve($input['subject_person_id'])->organizationId;

        return response()->json(['data' => app(ExportSubjectData::class)->request(
            $this->actor(),
            $input['subject_person_id'],
            $input['purpose'],
            $organizationId,
            $this->idempotencyKey('privacy.export.request'),
        )], 201);
    }

    public function approveExport(string $requestId): JsonResponse
    {
        return response()->json(['data' => app(ExportSubjectData::class)->approve(
            $this->actor(),
            PrivacyExportRequest::query()->findOrFail($requestId),
            $this->idempotencyKey('privacy.export.approve'),
        )]);
    }

    public function executeExport(string $requestId): JsonResponse
    {
        return response()->json(['data' => app(ExportSubjectData::class)->execute(
            $this->actor(),
            PrivacyExportRequest::query()->findOrFail($requestId),
            $this->idempotencyKey('privacy.export.execute'),
        )]);
    }

    /**
     * Consent row affordances = granted scope ∧ the one lifecycle table the
     * commands enforce. Submit and revoke are additionally the subject's own
     * acts, which the command permits without a staff capability; every other
     * verb needs `privacy.consent` inside the subject's branch. The browser
     * renders these flags verbatim and re-derives no lifecycle rule.
     *
     * @return array{submit: bool, verify: bool, activate: bool, expire: bool, revoke: bool, archive: bool}
     */
    private static function consentAffordances(string $state, bool $consentScope, bool $isSubject): array
    {
        $ownAct = $consentScope || $isSubject;

        return [
            'submit' => $ownAct && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_SUBMITTED),
            'verify' => $consentScope && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_VERIFIED),
            'activate' => $consentScope && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_ACTIVE),
            'expire' => $consentScope && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_EXPIRED),
            'revoke' => $ownAct && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_REVOKED),
            'archive' => $consentScope && ConsentLifecycle::allowsTransition($state, ConsentLifecycle::STATE_ARCHIVED),
        ];
    }

    /**
     * @param  \Closure(TransitionConsent, Consent, string): array{consent_id: string, lifecycle_state: string, correlation_id: string}  $transition
     */
    private function consentTransition(string $consentId, string $verb, Closure $transition): JsonResponse
    {
        return response()->json(['data' => $transition(
            app(TransitionConsent::class),
            Consent::query()->findOrFail($consentId),
            $this->idempotencyKey('privacy.consent.'.$verb),
        )]);
    }

    private static function identifier(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function day(mixed $value): string
    {
        return substr(trim((string) $value), 0, 10);
    }

    private function requireSubjectRead(string $subjectId, ?string $branchId): void
    {
        foreach (self::READ_CAPABILITIES as $capability) {
            if ($this->branchCapabilityAllowed($capability, $branchId)) {
                return;
            }
        }

        app(AttemptedOperation::class)->deniedByActor(
            AuthorizationDenied::forCode('api.read_denied', 'this subject is outside your authorized privacy capability scope'),
            $this->actor(),
            'privacy.api.subject',
            'person',
            $subjectId,
        );
    }

    private function denyWorkspaceRead(): never
    {
        app(AttemptedOperation::class)->deniedByActor(
            AuthorizationDenied::forCode('api.organization_read_denied', 'no effective privacy capability scope is available for this workspace'),
            $this->actor(),
            'privacy.api.workspace',
            'privacy_workspace',
            'index',
        );
    }
}
