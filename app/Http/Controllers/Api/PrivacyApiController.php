<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Person;
use App\Modules\Privacy\Models\Consent;
use App\Modules\Privacy\Models\ConsentPurpose;
use App\Modules\Privacy\Models\ConsentRevocation;
use App\Modules\Privacy\Models\Disclosure;
use App\Modules\Privacy\Models\PrivacyExportRequest;
use App\Modules\Privacy\Queries\SubjectPrivacyQuery;
use App\Support\Authorization\PersonBranchScope;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Canonical server-scoped privacy evidence read surface. */
final class PrivacyApiController extends Controller
{
    public function workspace(): JsonResponse
    {
        $this->requireOrganizationRead('privacy.disclose', 'privacy.api.workspace');
        $branchIds = $this->authorizedBranches('privacy.disclose');
        $people = Person::query()
            ->where('verification_state', 'verified')
            ->whereIn('home_branch_id', $branchIds)
            ->orderBy('legal_name')
            ->limit(300)
            ->get(['id', 'legal_name', 'home_branch_id']);
        $personIds = $people->pluck('id')->all();
        $consentIds = Consent::query()->whereIn('subject_person_id', $personIds)->select('id');

        return response()->json(['data' => [
            'scope' => ['branch_ids' => array_values($branchIds)],
            'people' => $people->map(static fn (Person $person): array => [
                'id' => (string) $person->id,
                'legal_name' => (string) $person->legal_name,
                'branch_id' => (string) $person->home_branch_id,
            ])->values()->all(),
            'purposes' => ConsentPurpose::query()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name', 'channel', 'category']),
            'consents' => Consent::query()
                ->whereIn('subject_person_id', $personIds)
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'subject_person_id', 'purpose_id', 'lifecycle_state', 'effective_from', 'effective_to', 'evidence_ref']),
            'revocations' => ConsentRevocation::query()
                ->whereIn('consent_id', $consentIds)
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'consent_id', 'revoked_by', 'scope', 'effect', 'created_at']),
            'disclosures' => Disclosure::query()
                ->whereIn('subject_person_id', $personIds)
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'subject_person_id', 'recipient', 'purpose', 'authority', 'scope_type', 'scope_id', 'disclosed_category', 'disclosed_by', 'created_at']),
            'export_requests' => PrivacyExportRequest::query()
                ->whereIn('subject_person_id', $personIds)
                ->orderByDesc('id')
                ->limit(300)
                ->get(),
            'policy' => [
                'authority' => 'server_access_decision',
                'history' => 'append_only_evidence',
                'correction' => 'lifecycle_or_new_evidence_only',
            ],
        ]]);
    }

    public function subject(string $subjectId, Request $request): JsonResponse
    {
        $scope = PersonBranchScope::resolve($subjectId);
        $this->requireBranchCapability('privacy.disclose', $scope->branchId, 'privacy.api.subject', 'person', $subjectId);
        $asOf = $request->query('as_of');
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
            ],
        ]]);
    }
}
