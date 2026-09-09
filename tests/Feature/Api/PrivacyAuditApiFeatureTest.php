<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Identity\Models\UserAccount;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class PrivacyAuditApiFeatureTest extends TestCase
{
    use BuildsActors;

    protected function setUp(): void
    {
        parent::setUp();
        $actor = $this->privacyOfficer('governance-api-actor');
        $this->grantScopeAuthority($actor->actorId, ['privacy.disclose', 'governance.config'], 'branch', $this->bootstrapBranchId());
        $this->createAccount($actor->actorId, 'governance-api');
    }

    private function createAccount(string $personId, string $username): void
    {
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make('governance-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
    }

    private function signIn(string $username = 'governance-api'): void
    {
        $this->post('/login', ['username' => $username, 'password' => 'governance-password-1'])->assertRedirect('/');
    }

    public function test_privacy_workspace_is_server_scoped(): void
    {
        $this->signIn();
        $payload = $this->getJson('/api/v1/privacy/workspace')->assertOk()->json('data');
        $this->assertContains($this->bootstrapBranchId(), $payload['scope']['branch_ids']);
        $this->assertSame('server_access_decision', $payload['policy']['authority']);
    }

    public function test_audit_workspace_is_server_scoped_and_immutable_by_contract(): void
    {
        $this->signIn();
        $payload = $this->getJson('/api/v1/audit/workspace')->assertOk()->json('data');
        $this->assertContains($this->bootstrapBranchId(), $payload['scope']['branch_ids']);
        foreach ($payload['events'] as $event) {
            $this->assertSame('immutable_append_only', $event['evidence_policy']);
        }
    }

    public function test_privacy_and_audit_workspaces_fail_closed_without_read_authority(): void
    {
        $actor = $this->actorWithoutAnyCapability('governance-api-denied');
        $this->createAccount($actor->actorId, 'governance-api-denied');
        $this->signIn('governance-api-denied');

        $this->getJson('/api/v1/privacy/workspace')->assertForbidden();
        $this->getJson('/api/v1/audit/workspace')->assertForbidden();
    }

    public function test_privacy_subject_rejects_invalid_as_of_filter(): void
    {
        $this->signIn();
        $payload = $this->getJson('/api/v1/privacy/workspace')->assertOk()->json('data');
        $subjectId = $payload['people'][0]['id'] ?? null;
        $this->assertNotNull($subjectId);

        $this->getJson('/api/v1/privacy/subjects/'.$subjectId.'?as_of=not-a-date')->assertStatus(422);
    }
}
