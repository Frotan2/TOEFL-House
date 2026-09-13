<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Modules\Privacy\Commands\DefineConsentPurpose;
use App\Modules\Privacy\Commands\RecordConsent;
use App\Modules\Privacy\Commands\TransitionConsent;
use App\Modules\Privacy\Models\Consent;
use App\Support\Authorization\Actor;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Contract coverage for the canonical Privacy & Consent React transport.
 *
 * The API exposes a server-scoped read projection and delegates every
 * mutation to the mature privacy commands; it must never become a parallel
 * authority model. These tests therefore assert three things at the HTTP
 * boundary: the projection is scoped and affordance-driven, consent evidence
 * locators are never serialized, and each mutation returns the owning
 * command's outcome — including its denials, business rejections and
 * idempotent replays.
 */
final class PrivacyApiFeatureTest extends TestCase
{
    use BuildsActors;

    private const PASSWORD = 'privacy-api-password-1';

    private string $branchA;

    private string $branchB;

    private string $subjectA;

    private string $subjectB;

    private string $officerA;

    private string $officerB;

    private string $approverOne;

    private string $approverTwo;

    private string $exporterOrg;

    private string $purposeId;

    private string $consentA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureBootstrapAuthority();

        $this->branchA = RandomIdentifier::new();
        $this->branchB = RandomIdentifier::new();
        $this->newBranch($this->branchA, 'Privacy Scope A');
        $this->newBranch($this->branchB, 'Privacy Scope B');

        $this->subjectA = 'privacy-api-subject-a';
        $this->subjectB = 'privacy-api-subject-b';
        $this->personWithAuthority($this->subjectA, [], $this->branchA);
        $this->personWithAuthority($this->subjectB, [], $this->branchB);

        // Branch-scoped privacy officers: the read union and every mutation
        // must stay inside their own branch.
        $this->officerA = 'privacy-api-officer-a';
        $this->officerB = 'privacy-api-officer-b';
        $this->grantScopeAuthority($this->officerA, ['privacy.consent', 'privacy.disclose', 'privacy.export'], 'branch', $this->branchA);
        $this->grantScopeAuthority($this->officerB, ['privacy.consent', 'privacy.disclose', 'privacy.export'], 'branch', $this->branchB);

        // Organization-rooted bulk-export approvers, each with their own
        // account so the two signatures come from two sessions.
        $this->approverOne = 'privacy-api-approver-1';
        $this->approverTwo = 'privacy-api-approver-2';
        $this->grantScopeAuthority($this->approverOne, ['privacy.approve_bulk_export'], 'organization', $this->bootstrapOrganizationId);
        $this->grantScopeAuthority($this->approverTwo, ['privacy.approve_bulk_export'], 'organization', $this->bootstrapOrganizationId);

        // Executing an organization-wide release is an organization-scoped act:
        // a branch-scoped exporter may request it but never release it.
        $this->exporterOrg = 'privacy-api-exporter-org';
        $this->grantScopeAuthority($this->exporterOrg, ['privacy.export'], 'organization', $this->bootstrapOrganizationId);

        foreach ([$this->officerA, $this->officerB, $this->approverOne, $this->approverTwo, $this->exporterOrg, $this->subjectA] as $personId) {
            $this->createAccount($personId, $personId);
        }

        // Purpose definition is organization-rooted catalog authority, which a
        // branch-scoped officer deliberately does not hold by default.
        $this->purposeId = $this->definePurpose('privacy-api-purpose');

        $officer = new Actor($this->officerA, 'Privacy Officer A');
        $this->consentA = app(RecordConsent::class)->record(
            $officer,
            $this->subjectA,
            $this->purposeId,
            'evidence/privacy-api-consent-a',
            CarbonImmutable::now()->subMonth()->startOfDay(),
            null,
            'privacy-api-consent-record-1',
        )['consent_id'];
    }

    private function definePurpose(string $name, string $channel = 'email', string $category = 'communication'): string
    {
        $definer = $this->actorWithStructureCapabilities('privacy-api-definer', ['privacy.define_purpose']);

        return app(DefineConsentPurpose::class)
            ->define($definer, $name, $channel, $category, 'privacy-api-purpose-'.$name)['purpose_id'];
    }

    private function newBranch(string $id, string $name): void
    {
        Branch::query()->create(['id' => $id, 'name' => $name, 'lifecycle_state' => 'active']);
        $this->attachBranchToBootstrapOrganization($id);
    }

    private function createAccount(string $personId, string $username): void
    {
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make(self::PASSWORD),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    private function signIn(string $username): void
    {
        $this->post('/login', ['username' => $username, 'password' => self::PASSWORD])->assertRedirect('/');
    }

    private function signOut(): void
    {
        $this->post('/logout')->assertRedirect('/login');
    }

    public function test_workspace_is_branch_scoped_and_projects_server_affordances_only(): void
    {
        $this->signIn($this->officerA);

        $response = $this->getJson('/api/v1/privacy/workspace')->assertOk();
        $payload = $response->json('data');

        // The read union is the officer's own branch: branch B and its subject
        // are invisible, and no capability is inferred from the URL.
        $this->assertSame([$this->branchA], $payload['scope']['branch_ids']);
        $this->assertSame($this->officerA, $payload['actor']['person_id']);
        $this->assertSame([$this->subjectA], array_column($payload['people'], 'id'));
        $this->assertSame([['id' => $this->purposeId, 'name' => 'privacy-api-purpose', 'channel' => 'email', 'category' => 'communication']], $payload['purposes']);

        $this->assertSame([
            'define_purpose' => false,
            'consent' => true,
            'disclose' => true,
            'export' => true,
            'approve_bulk_export' => false,
        ], $payload['available_actions']);

        $row = collect($payload['consents'])->firstWhere('id', $this->consentA);
        $this->assertIsArray($row);
        $this->assertSame('draft', $row['lifecycle_state']);
        // A draft may be submitted and nothing else: the matrix is the
        // server's lifecycle table intersected with the officer's scope.
        $this->assertSame([
            'submit' => true,
            'verify' => false,
            'activate' => false,
            'expire' => false,
            'revoke' => false,
            'archive' => false,
        ], $row['available_actions']);

        // Consent evidence locators are write-only across every read model.
        $this->assertArrayNotHasKey('evidence_ref', $row);
        $this->assertStringNotContainsString('evidence/privacy-api-consent-a', $response->getContent());
        $this->assertSame('revocation_expiry_and_archive_never_delete', $payload['policy']['erasure']);
    }

    public function test_purpose_definition_needs_organization_rooted_authority_and_replays_idempotently(): void
    {
        $this->signIn($this->officerA);
        $payload = ['name' => 'privacy-api-marketing', 'channel' => 'email', 'category' => 'marketing'];

        $this->postJson('/api/v1/privacy/purposes', $payload, ['Idempotency-Key' => 'privacy-api-purpose-denied'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.purpose_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.purpose.define.denied',
            'actor_id' => $this->officerA,
        ]);
        $this->assertDatabaseMissing('consent_purposes', ['name' => 'privacy-api-marketing']);

        // Organization-rooted catalog authority is granted, then the same
        // request is accepted, replayed and finally refused on a conflict.
        $this->grantScopeAuthority($this->officerA, ['privacy.define_purpose'], 'organization', $this->bootstrapOrganizationId);

        $first = $this->postJson('/api/v1/privacy/purposes', $payload, ['Idempotency-Key' => 'privacy-api-purpose-define'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['purpose_id', 'correlation_id']]);
        $purposeId = (string) $first->json('data.purpose_id');

        $this->postJson('/api/v1/privacy/purposes', $payload, ['Idempotency-Key' => 'privacy-api-purpose-define'])
            ->assertSuccessful()
            ->assertJsonPath('data.purpose_id', $purposeId);
        $this->assertSame(1, DB::table('consent_purposes')->where('name', 'privacy-api-marketing')->count());

        $this->postJson('/api/v1/privacy/purposes', ['name' => 'privacy-api-other', 'channel' => 'sms', 'category' => 'marketing'], ['Idempotency-Key' => 'privacy-api-purpose-define'])
            ->assertStatus(409);
        $this->assertDatabaseMissing('consent_purposes', ['name' => 'privacy-api-other']);

        // Communication and marketing purposes stay separate definitions, and
        // the catalog key is name + channel: re-defining it under a different
        // category is refused as a business fact rather than as a database
        // fault, so the operator is told the definition already exists.
        $this->postJson('/api/v1/privacy/purposes', ['name' => 'privacy-api-marketing', 'channel' => 'email', 'category' => 'communication'], ['Idempotency-Key' => 'privacy-api-purpose-duplicate'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.purpose_duplicate');
        $this->assertSame(1, DB::table('consent_purposes')->where('name', 'privacy-api-marketing')->count());
        $this->assertDatabaseHas('consent_purposes', ['name' => 'privacy-api-marketing', 'category' => 'marketing']);
    }

    public function test_consent_lifecycle_endpoints_delegate_to_the_canonical_state_machine(): void
    {
        $this->signIn($this->officerA);

        // A purpose of its own: one open consent per subject and purpose is a
        // database boundary, and setUp already holds an open consent for the
        // fixture purpose.
        $lifecyclePurposeId = $this->definePurpose('privacy-api-lifecycle-purpose', 'sms');

        $recorded = $this->postJson('/api/v1/privacy/consents', [
            'subject_person_id' => $this->subjectA,
            'purpose_id' => $lifecyclePurposeId,
            'evidence_ref' => 'evidence/privacy-api-lifecycle',
            'effective_from' => CarbonImmutable::now()->subMonth()->toDateString(),
            'effective_to' => null,
        ], ['Idempotency-Key' => 'privacy-api-consent-lifecycle-1'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['consent_id', 'correlation_id']]);
        $consentId = (string) $recorded->json('data.consent_id');

        // A replay of the same key is the same consent, not a second one.
        $this->postJson('/api/v1/privacy/consents', [
            'subject_person_id' => $this->subjectA,
            'purpose_id' => $lifecyclePurposeId,
            'evidence_ref' => 'evidence/privacy-api-lifecycle',
            'effective_from' => CarbonImmutable::now()->subMonth()->toDateString(),
            'effective_to' => null,
        ], ['Idempotency-Key' => 'privacy-api-consent-lifecycle-1'])
            ->assertSuccessful()
            ->assertJsonPath('data.consent_id', $consentId);
        $this->assertSame(1, Consent::query()->where('evidence_ref', 'evidence/privacy-api-lifecycle')->count());

        // The chain advances one canonical step at a time.
        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/submit', [], ['Idempotency-Key' => 'privacy-api-consent-submit'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'submitted');
        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/verify', [], ['Idempotency-Key' => 'privacy-api-consent-verify'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'verified');
        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/activate', [], ['Idempotency-Key' => 'privacy-api-consent-activate'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'active');

        $active = $this->getJson('/api/v1/privacy/workspace')->assertOk()->json('data');
        $activeRow = collect($active['consents'])->firstWhere('id', $consentId);
        $this->assertIsArray($activeRow);
        $this->assertSame([
            'submit' => false,
            'verify' => false,
            'activate' => false,
            'expire' => true,
            'revoke' => true,
            'archive' => true,
        ], $activeRow['available_actions']);

        // Expiry follows the recorded window: an open-ended consent has
        // nothing to lapse, so ending its use early is a revocation.
        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/expire', [], ['Idempotency-Key' => 'privacy-api-consent-expire'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.consent_expiry_not_due');

        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/revoke', [
            'scope' => 'all-channels',
            'effect' => 'immediate-cessation',
        ], ['Idempotency-Key' => 'privacy-api-consent-revoke'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'revoked');
        $this->assertDatabaseHas('consent_revocations', [
            'consent_id' => $consentId,
            'revoked_by' => $this->officerA,
            'scope' => 'all-channels',
            'effect' => 'immediate-cessation',
        ]);

        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/archive', [], ['Idempotency-Key' => 'privacy-api-consent-archive'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'archived');

        // An archived consent is terminal: no verb resurrects it, and the
        // recorded evidence survives every transition.
        $this->postJson('/api/v1/privacy/consents/'.$consentId.'/submit', [], ['Idempotency-Key' => 'privacy-api-consent-resubmit'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.consent_transition_forbidden');
        $this->assertDatabaseHas('consents', [
            'id' => $consentId,
            'lifecycle_state' => 'archived',
            'evidence_ref' => 'evidence/privacy-api-lifecycle',
            'recorded_by' => $this->officerA,
        ]);
        foreach (['record', 'submit', 'verify', 'activate', 'revoke', 'archive'] as $verb) {
            $this->assertDatabaseHas('audit_events', [
                'operation' => 'privacy.consent.'.$verb,
                'target_type' => 'consent',
                'target_id' => $consentId,
                'actor_id' => $this->officerA,
            ]);
        }
    }

    public function test_a_subject_may_drive_their_own_consent_but_not_verify_it(): void
    {
        $this->signIn($this->subjectA);

        // Submitting and withdrawing are the subject's own acts and need no
        // staff capability; verification is a staff judgment and is refused.
        $this->postJson('/api/v1/privacy/consents/'.$this->consentA.'/submit', [], ['Idempotency-Key' => 'privacy-api-subject-submit'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'submitted');
        $this->postJson('/api/v1/privacy/consents/'.$this->consentA.'/verify', [], ['Idempotency-Key' => 'privacy-api-subject-verify'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.consent_transition_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.consent.verify.denied',
            'actor_id' => $this->subjectA,
            'target_id' => $this->consentA,
        ]);
        $this->assertSame('submitted', Consent::query()->findOrFail($this->consentA)->lifecycle_state);
    }

    public function test_a_branch_scoped_officer_cannot_read_or_write_another_branch_subject(): void
    {
        $this->signIn($this->officerA);

        $this->postJson('/api/v1/privacy/consents', [
            'subject_person_id' => $this->subjectB,
            'purpose_id' => $this->purposeId,
            'evidence_ref' => 'evidence/privacy-api-cross-branch',
            'effective_from' => CarbonImmutable::now()->subMonth()->toDateString(),
        ], ['Idempotency-Key' => 'privacy-api-cross-branch-consent'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.consent_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.consent.record.denied',
            'actor_id' => $this->officerA,
            'target_id' => $this->subjectB,
        ]);
        $this->assertDatabaseMissing('consents', ['subject_person_id' => $this->subjectB]);

        $this->getJson('/api/v1/privacy/subjects/'.$this->subjectB)
            ->assertForbidden()
            ->assertJsonPath('error', 'api.read_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.api.subject.denied',
            'actor_id' => $this->officerA,
            'target_id' => $this->subjectB,
        ]);

        // The subject's own branch officer reads the same dossier without
        // friction: the boundary is scope, not a blanket denial.
        $this->signOut();
        $this->signIn($this->officerB);
        $this->getJson('/api/v1/privacy/subjects/'.$this->subjectB)
            ->assertOk()
            ->assertJsonPath('data.subject_person_id', $this->subjectB)
            ->assertJsonPath('data.provenance.branch_id', $this->branchB);
    }

    public function test_the_dossier_is_a_separately_authorized_read_without_evidence_locators(): void
    {
        $this->signIn($this->officerA);
        app(TransitionConsent::class)->submit(
            new Actor($this->officerA, 'Privacy Officer A'),
            Consent::query()->findOrFail($this->consentA),
            'privacy-api-dossier-submit',
        );

        $response = $this->getJson('/api/v1/privacy/subjects/'.$this->subjectA)->assertOk();
        $payload = $response->json('data');

        $this->assertSame($this->subjectA, $payload['subject_person_id']);
        $this->assertSame(CarbonImmutable::now()->toDateString(), $payload['as_of']);
        $this->assertSame([], $payload['consents'], 'a submitted consent is not yet current use authority');
        $this->assertCount(1, $payload['consent_history']);
        $this->assertSame('submitted', $payload['consent_history'][0]['lifecycle_state']);
        $this->assertSame($this->officerA, $payload['consent_history'][0]['recorded_by']);
        $this->assertSame($this->branchA, $payload['provenance']['branch_id']);
        $this->assertSame($this->bootstrapOrganizationId, $payload['provenance']['organization_id']);
        // The erasure doctrine travels with the dossier: nothing here deletes.
        $this->assertSame('revocation_expiry_and_archive_never_delete', $payload['policy']['erasure']);

        // Write-only doctrine at the serialization boundary: no read model may
        // turn a dossier into a retrieval map for consent artifacts.
        $this->assertStringNotContainsString('evidence_ref', $response->getContent());
        $this->assertStringNotContainsString('evidence/privacy-api-consent-a', $response->getContent());

        // The as-of day is a projection parameter over the recorded window.
        $this->getJson('/api/v1/privacy/subjects/'.$this->subjectA.'?as_of='.CarbonImmutable::now()->subYears(2)->toDateString())
            ->assertOk()
            ->assertJsonPath('data.as_of', CarbonImmutable::now()->subYears(2)->toDateString())
            ->assertJsonPath('data.consent_history.0.lifecycle_state', 'submitted');
    }

    public function test_disclosure_and_export_endpoints_record_immutable_release_evidence(): void
    {
        $this->signIn($this->officerA);

        $disclosure = [
            'subject_person_id' => $this->subjectA,
            'recipient' => 'Ministry of Education',
            'purpose' => 'statutory-reporting',
            'authority' => 'privacy.disclose',
            'scope_type' => 'subject',
            'scope_id' => $this->subjectA,
            'disclosed_category' => 'academic-records',
        ];
        $this->postJson('/api/v1/privacy/disclosures', ['subject_person_id' => $this->subjectA, 'recipient' => ''], ['Idempotency-Key' => 'privacy-api-disclosure-invalid'])
            ->assertStatus(422);

        $first = $this->postJson('/api/v1/privacy/disclosures', $disclosure, ['Idempotency-Key' => 'privacy-api-disclosure-1'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['disclosure_id', 'correlation_id']]);
        $disclosureId = (string) $first->json('data.disclosure_id');
        $this->postJson('/api/v1/privacy/disclosures', $disclosure, ['Idempotency-Key' => 'privacy-api-disclosure-1'])
            ->assertSuccessful()
            ->assertJsonPath('data.disclosure_id', $disclosureId);
        $this->assertSame(1, DB::table('disclosures')->where('recipient', 'Ministry of Education')->count());

        // A declared scope outside the subject's own provenance is refused,
        // whatever capability the caller holds.
        $this->postJson('/api/v1/privacy/disclosures', array_merge($disclosure, [
            'scope_type' => 'branch',
            'scope_id' => $this->branchB,
        ]), ['Idempotency-Key' => 'privacy-api-disclosure-scope'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.scope_mismatch');

        // A direct export covers one subject inside a non-organization scope.
        $export = $this->postJson('/api/v1/privacy/exports', [
            'subject_person_id' => $this->subjectA,
            'purpose' => 'subject-data-request',
            'scope_type' => 'subject',
            'scope_id' => $this->subjectA,
        ], ['Idempotency-Key' => 'privacy-api-export-1'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['export_id', 'disclosure_id', 'dataset', 'correlation_id']]);
        $this->assertSame($this->subjectA, $export->json('data.dataset.subject.person_id'));
        $this->assertCount(1, $export->json('data.dataset.consents'));
        $this->assertStringNotContainsString('evidence/privacy-api-consent-a', $export->getContent(), 'a released dataset never carries a consent evidence locator');

        // An organization-wide release is not available through the direct
        // endpoint: it must go through the staged approval chain.
        $this->postJson('/api/v1/privacy/exports', [
            'subject_person_id' => $this->subjectA,
            'purpose' => 'organization-wide audit',
            'scope_type' => 'organization',
            'scope_id' => $this->bootstrapOrganizationId,
        ], ['Idempotency-Key' => 'privacy-api-export-bulk-direct'])
            ->assertStatus(422);
    }

    public function test_the_bulk_export_chain_resolves_the_organization_server_side_and_needs_two_sessions(): void
    {
        $this->signIn($this->officerA);

        // The browser supplies a subject and a purpose only; the organization
        // is resolved from the subject's own provenance, so a caller cannot
        // aim a bulk request at another tenant.
        $requested = $this->postJson('/api/v1/privacy/exports/bulk', [
            'subject_person_id' => $this->subjectA,
            'purpose' => 'canonical organization audit',
        ], ['Idempotency-Key' => 'privacy-api-bulk-request'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['request_id', 'correlation_id']]);
        $requestId = (string) $requested->json('data.request_id');
        $this->assertDatabaseHas('privacy_export_requests', [
            'id' => $requestId,
            'subject_person_id' => $this->subjectA,
            'organization_id' => $this->bootstrapOrganizationId,
            'lifecycle_state' => 'requested',
            'requested_by' => $this->officerA,
        ]);

        // Execution is decided at organization scope, so the "too early" probe
        // must come from an actor that actually holds it. Authorization precedes
        // chain state: a caller without organization-wide export authority is
        // refused outright and learns nothing about the request's progress, so
        // the state rejection below is only reachable by an authorized exporter.
        $this->signOut();
        $this->signIn($this->exporterOrg);
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/execute', [], ['Idempotency-Key' => 'privacy-api-bulk-early-execute'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.export_request_state');

        // First signature, in its own session.
        $this->signOut();
        $this->signIn($this->approverOne);
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/approve', [], ['Idempotency-Key' => 'privacy-api-bulk-approve-1'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'requested');
        $this->assertDatabaseHas('privacy_export_requests', ['id' => $requestId, 'approver_one_id' => $this->approverOne]);

        // The same actor may not supply the second signature.
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/approve', [], ['Idempotency-Key' => 'privacy-api-bulk-approve-2'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.bulk_export_single_actor');

        // A distinct approver closes the chain in a second session.
        $this->signOut();
        $this->signIn($this->approverTwo);
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/approve', [], ['Idempotency-Key' => 'privacy-api-bulk-approve-3'])
            ->assertOk()->assertJsonPath('data.lifecycle_state', 'approved');
        $this->assertDatabaseHas('privacy_export_requests', [
            'id' => $requestId,
            'lifecycle_state' => 'approved',
            'approver_one_id' => $this->approverOne,
            'approver_two_id' => $this->approverTwo,
        ]);

        // An approver holds no export capability: execution needs an exporter.
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/execute', [], ['Idempotency-Key' => 'privacy-api-bulk-approver-execute'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.export_denied');

        // Nor may the branch-scoped officer who requested it: an
        // organization-wide release is decided at the organization scope, so
        // requesting one is not authority to release one.
        $this->signOut();
        $this->signIn($this->officerA);
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/execute', [], ['Idempotency-Key' => 'privacy-api-bulk-officer-execute'])
            ->assertForbidden()
            ->assertJsonPath('error', 'privacy.export_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'privacy.export.execute.denied',
            'actor_id' => $this->officerA,
            'target_id' => $requestId,
        ]);
        $this->assertDatabaseHas('privacy_export_requests', ['id' => $requestId, 'lifecycle_state' => 'approved']);

        $this->signOut();
        $this->signIn($this->exporterOrg);
        $executed = $this->postJson('/api/v1/privacy/exports/'.$requestId.'/execute', [], ['Idempotency-Key' => 'privacy-api-bulk-execute'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['export_id', 'disclosure_id', 'dataset', 'correlation_id']]);
        $this->assertDatabaseHas('privacy_export_requests', [
            'id' => $requestId,
            'lifecycle_state' => 'exported',
            'exported_by' => $this->exporterOrg,
            'disclosure_id' => (string) $executed->json('data.disclosure_id'),
        ]);
        $this->assertDatabaseHas('disclosures', [
            'id' => (string) $executed->json('data.disclosure_id'),
            'scope_type' => 'organization',
            'scope_id' => $this->bootstrapOrganizationId,
            'disclosed_category' => 'subject-data-export',
        ]);

        // An executed request is closed: it never releases twice and the
        // projection stops offering any control for it.
        $this->postJson('/api/v1/privacy/exports/'.$requestId.'/execute', [], ['Idempotency-Key' => 'privacy-api-bulk-execute-again'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'privacy.export_request_state');

        // The requester reads the closed chain in its own branch scope.
        $this->signOut();
        $this->signIn($this->officerA);
        $workspace = $this->getJson('/api/v1/privacy/workspace')->assertOk()->json('data');
        $row = collect($workspace['export_requests'])->firstWhere('id', $requestId);
        $this->assertIsArray($row);
        $this->assertSame('exported', $row['lifecycle_state']);
        $this->assertSame(['approve' => false, 'execute' => false], $row['available_actions']);
    }
}
