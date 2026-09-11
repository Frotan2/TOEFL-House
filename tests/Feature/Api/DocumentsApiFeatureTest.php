<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Documents\Commands\DefineDocumentClassification;
use App\Modules\Documents\Commands\RegisterDocument;
use App\Modules\Documents\Models\Document;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Contract coverage for the canonical Documents React transport. The API
 * exposes a safe read projection and delegates every mutation to the mature
 * Documents commands; it must never become a parallel authority model.
 */
final class DocumentsApiFeatureTest extends TestCase
{
    use BuildsActors;

    private string $branchA;

    private string $branchB;

    private string $classificationId;

    private string $subjectA;

    private string $subjectB;

    private string $documentA;

    private string $documentB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureBootstrapAuthority();

        $this->branchA = RandomIdentifier::new();
        $this->branchB = RandomIdentifier::new();
        $this->newBranch($this->branchA, 'Documents Scope A');
        $this->newBranch($this->branchB, 'Documents Scope B');

        $classifier = $this->documentsOfficer('documents-api-classifier');
        $this->classificationId = app(DefineDocumentClassification::class)->defineClassification(
            $classifier,
            'identity-evidence',
            'Identity',
            'restricted',
            'documents-api-classification',
        )['classification_id'];

        $this->subjectA = 'documents-api-subject-a';
        $this->subjectB = 'documents-api-subject-b';
        $this->personWithAuthority($this->subjectA, [], $this->branchA);
        $this->personWithAuthority($this->subjectB, [], $this->branchB);

        $registrarA = $this->actorWithoutAnyCapability('documents-api-registrar-a');
        $registrarB = $this->actorWithoutAnyCapability('documents-api-registrar-b');
        $this->grantScopeAuthority($registrarA->actorId, ['documents.register'], 'branch', $this->branchA);
        $this->grantScopeAuthority($registrarB->actorId, ['documents.register'], 'branch', $this->branchB);
        $this->documentA = app(RegisterDocument::class)->register(
            $registrarA,
            $this->subjectA,
            $this->classificationId,
            'Scope A identity evidence',
            'safe-hash-a',
            'storage/private/scope-a',
            'documents-api-register-a',
        )['document_id'];
        $this->documentB = app(RegisterDocument::class)->register(
            $registrarB,
            $this->subjectB,
            $this->classificationId,
            'Scope B identity evidence',
            'safe-hash-b',
            'storage/private/scope-b',
            'documents-api-register-b',
        )['document_id'];

        $viewer = $this->actorWithoutAnyCapability('documents-api-viewer');
        $this->grantScopeAuthority($viewer->actorId, ['documents.verify'], 'branch', $this->branchA);
        $this->createAccount($viewer->actorId, 'documents-api-viewer');
    }

    private function newBranch(string $id, string $name): void
    {
        Branch::query()->create([
            'id' => $id,
            'name' => $name,
            'lifecycle_state' => 'active',
        ]);
        $this->attachBranchToBootstrapOrganization($id);
    }

    private function createAccount(string $personId, string $username): void
    {
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make('documents-api-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    private function signIn(string $username): void
    {
        $this->post('/login', [
            'username' => $username,
            'password' => 'documents-api-password-1',
        ])->assertRedirect('/');
    }

    public function test_workspace_unions_concrete_capability_scope_without_exposing_storage_references(): void
    {
        $this->signIn('documents-api-viewer');

        $response = $this->getJson('/api/v1/documents')->assertOk();
        $payload = $response->json('data');
        $documentIds = array_map(static fn (array $document): string => (string) $document['id'], $payload['documents']);
        $personIds = array_map(static fn (array $person): string => (string) $person['id'], $payload['people']);

        $this->assertSame([$this->branchA], $payload['scope']['branch_ids']);
        $this->assertContains($this->documentA, $documentIds);
        $this->assertNotContains($this->documentB, $documentIds);
        $this->assertContains($this->subjectA, $personIds);
        $this->assertNotContains($this->subjectB, $personIds);
        $this->assertFalse($payload['available_actions']['register']);
        $this->assertTrue($payload['available_actions']['verify']);
        $this->assertFalse($payload['documents'][0]['available_actions']['submit']);
        $this->assertTrue($payload['documents'][0]['available_actions']['verify']);
        $this->assertSame('server_access_decision', $payload['policy']['authority']);
        $this->assertStringNotContainsString('storage/private/', $response->getContent());
        $this->assertStringNotContainsString('storage_ref', $response->getContent());
        $this->assertStringNotContainsString('safe-hash-a', $response->getContent());
    }

    public function test_workspace_unions_different_capabilities_and_projects_affordances_per_record(): void
    {
        $actor = $this->actorWithoutAnyCapability('documents-api-union-actor');
        $this->grantScopeAuthority($actor->actorId, ['documents.register'], 'branch', $this->branchA);
        $this->grantScopeAuthority($actor->actorId, ['documents.retention'], 'branch', $this->branchB);
        $this->createAccount($actor->actorId, 'documents-api-union-actor');
        $this->signIn('documents-api-union-actor');

        $payload = $this->getJson('/api/v1/documents')->assertOk()->json('data');
        /** @var list<array{id: string, available_actions: array{submit: bool, verify: bool, retention: bool}}> $visibleDocuments */
        $visibleDocuments = $payload['documents'];
        $documents = [];
        foreach ($visibleDocuments as $document) {
            $documents[$document['id']] = $document;
        }
        $expectedBranches = [$this->branchA, $this->branchB];
        sort($expectedBranches);

        $this->assertSame($expectedBranches, $payload['scope']['branch_ids']);
        $this->assertTrue($payload['available_actions']['register']);
        $this->assertTrue($payload['available_actions']['retention']);
        $this->assertTrue($documents[$this->documentA]['available_actions']['submit']);
        $this->assertFalse($documents[$this->documentA]['available_actions']['retention']);
        $this->assertFalse($documents[$this->documentB]['available_actions']['submit']);
        $this->assertTrue($documents[$this->documentB]['available_actions']['retention']);
    }

    public function test_history_is_scoped_and_projects_only_safe_append_only_evidence(): void
    {
        $this->signIn('documents-api-viewer');

        $response = $this->getJson('/api/v1/documents/'.$this->documentA.'/history')
            ->assertOk()
            ->assertJsonPath('data.document_id', $this->documentA)
            ->assertJsonPath('data.versions.0.content_hash', 'safe-hash-a');
        $this->assertStringNotContainsString('storage/private/', $response->getContent());
        $this->assertStringNotContainsString('storage_ref', $response->getContent());

        $this->getJson('/api/v1/documents/'.$this->documentB.'/history')->assertForbidden()
            ->assertJsonPath('category', 'authorization');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'documents.api.history.denied',
            'actor_id' => 'documents-api-viewer',
            'target_id' => $this->documentB,
        ]);
    }

    public function test_workspace_read_visibility_never_grants_a_register_only_lifecycle_command(): void
    {
        $this->signIn('documents-api-viewer');

        // The viewer can see the draft via documents.verify scope, but only a
        // registrar may append the next version. The controller deliberately
        // does not turn projection visibility into mutation authority.
        $this->postJson('/api/v1/documents/'.$this->documentA.'/submit', [
            'content_hash' => 'viewer-attempt-hash',
            'storage_ref' => 'storage/private/viewer-attempt',
        ], ['Idempotency-Key' => 'documents-api-viewer-submit'])
            ->assertForbidden()
            ->assertJsonPath('error', 'documents.submit_denied');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'documents.submit.denied',
            'actor_id' => 'documents-api-viewer',
            'target_id' => $this->documentA,
        ]);
        $this->assertSame(1, Document::query()->findOrFail($this->documentA)->versions()->count());
    }

    public function test_registration_api_delegates_to_the_idempotent_canonical_command(): void
    {
        $registrar = $this->actorWithoutAnyCapability('documents-api-register-user');
        $this->grantScopeAuthority($registrar->actorId, ['documents.register'], 'branch', $this->branchA);
        $this->createAccount($registrar->actorId, 'documents-api-register-user');
        $this->signIn('documents-api-register-user');

        $payload = [
            'subject_person_id' => $this->subjectA,
            'classification_id' => $this->classificationId,
            'title' => 'API registered identity evidence',
            'content_hash' => 'api-register-hash',
            'storage_ref' => 'storage/private/api-register',
        ];
        $key = 'documents-api-register-replay';
        $first = $this->postJson('/api/v1/documents', $payload, ['Idempotency-Key' => $key]);
        $first->assertCreated()->assertJsonStructure(['data' => ['document_id', 'version_no', 'correlation_id']]);
        $documentId = (string) $first->json('data.document_id');

        $this->postJson('/api/v1/documents', $payload, ['Idempotency-Key' => $key])->assertSuccessful()
            ->assertJsonPath('data.document_id', $documentId);
        $this->assertSame(1, Document::query()->where('title', 'API registered identity evidence')->count());
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'documents.register',
            'target_id' => $documentId,
            'actor_id' => $registrar->actorId,
        ]);
    }

    public function test_lifecycle_api_adapters_delegate_to_the_canonical_state_machine(): void
    {
        $this->createAccount('documents-api-registrar-a', 'documents-api-lifecycle-registrar');
        $this->signIn('documents-api-lifecycle-registrar');

        $this->postJson('/api/v1/documents/'.$this->documentA.'/submit', [
            'content_hash' => 'lifecycle-version-two',
            'storage_ref' => 'storage/private/lifecycle-version-two',
        ], ['Idempotency-Key' => 'documents-api-lifecycle-submit'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle_state', 'submitted')
            ->assertJsonPath('data.version_no', 2);

        $this->signIn('documents-api-viewer');
        $this->postJson('/api/v1/documents/'.$this->documentA.'/verify', [
            'result' => 'pass',
            'reason' => 'Document evidence matches the verified subject.',
        ], ['Idempotency-Key' => 'documents-api-lifecycle-verify'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle_state', 'verified');
        $this->postJson('/api/v1/documents/'.$this->documentA.'/activate', [], ['Idempotency-Key' => 'documents-api-lifecycle-activate'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle_state', 'active');
        $this->postJson('/api/v1/documents/'.$this->documentA.'/expire', [], ['Idempotency-Key' => 'documents-api-lifecycle-expire'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle_state', 'expired');
        $this->postJson('/api/v1/documents/'.$this->documentA.'/archive', [], ['Idempotency-Key' => 'documents-api-lifecycle-archive'])
            ->assertOk()
            ->assertJsonPath('data.lifecycle_state', 'archived');

        $this->assertDatabaseHas('document_verifications', [
            'document_id' => $this->documentA,
            'version_no' => 2,
            'verifier_person_id' => 'documents-api-viewer',
            'result' => 'pass',
        ]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'documents.archive', 'target_id' => $this->documentA]);
    }

    public function test_organization_scoped_classifier_gets_catalog_only_not_branch_people_or_documents(): void
    {
        $classifier = $this->actorWithoutAnyCapability('documents-api-catalog-classifier');
        $this->grantScopeAuthority(
            $classifier->actorId,
            ['documents.classify'],
            'organization',
            $this->organizationIdFromScopeKey('documents-api-catalog'),
        );
        $this->createAccount($classifier->actorId, 'documents-api-catalog-classifier');
        $this->signIn('documents-api-catalog-classifier');

        $payload = $this->getJson('/api/v1/documents')->assertOk()->json('data');

        $this->assertSame([], $payload['scope']['branch_ids']);
        $this->assertTrue($payload['available_actions']['classify']);
        $this->assertFalse($payload['available_actions']['register']);
        $this->assertSame([], $payload['people']);
        $this->assertSame([], $payload['documents']);
        $this->assertNotEmpty($payload['classifications']);
    }

    public function test_no_capability_scope_fails_closed_and_records_the_read_denial(): void
    {
        $nobody = $this->actorWithoutAnyCapability('documents-api-denied');
        $this->createAccount($nobody->actorId, 'documents-api-denied');
        $this->signIn('documents-api-denied');

        $this->getJson('/api/v1/documents')->assertForbidden()
            ->assertJsonPath('error', 'api.organization_read_denied')
            ->assertJsonPath('category', 'authorization');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'documents.api.workspace.denied',
            'actor_id' => 'documents-api-denied',
            'target_type' => 'documents_workspace',
        ]);
    }
}
