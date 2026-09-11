<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\OperatesStructure;
use Tests\TestCase;

/**
 * HTTP transport contract for ORG-OPS-01: the React console reaches the
 * staged four-actor governance workflow only through /api/v1 with server
 * sessions. These tests prove (as non-Owner, capability-scoped operators):
 *   - the full propose -> review -> two-owner-signature lifecycle executes
 *     canonical commands through the transport;
 *   - separation of duties, scope and lifecycle are enforced server-side;
 *   - the projection leaks neither cross-scope proposals nor unauthorized
 *     actions;
 *   - retries with one Idempotency-Key create exactly one proposal;
 *   - the page gate admits only governance-capable operators.
 */
final class StructureChangeApiFeatureTest extends TestCase
{
    use BuildsActors;
    use OperatesStructure;

    private const PASSWORD = 'org-api-password-1';

    protected function setUp(): void
    {
        parent::setUp();

        // The four distinct governance actors (none is the bootstrap Owner).
        $this->structureDecisionForGlobalActors();
        $this->accountFor('gm-1', 'org-api-gm');
        $this->accountFor('mgr-1', 'org-api-mgr');
        $this->accountFor('owner-1', 'org-api-owner-1');
        $this->accountFor('owner-2', 'org-api-owner-2');

        $this->actorWithoutAnyCapability('org-api-nobody');
        $this->accountFor('org-api-nobody', 'org-api-nobody');
    }

    public function test_unauthenticated_and_unauthorized_clients_are_fail_closed(): void
    {
        $this->getJson('/api/v1/organization/workspace')->assertStatus(401);
        $this->get('/organization')->assertRedirect(route('login'));

        $this->signIn('org-api-nobody');
        $this->getJson('/api/v1/organization/workspace')->assertStatus(403)->assertJsonPath('error', 'api.organization_read_denied');
        $this->propose([
            'change_type' => 'create_organization',
            'name' => 'Denied Root',
        ])->assertStatus(403);
        $this->get('/organization')->assertRedirect(route('home'));
    }

    public function test_full_lifecycle_runs_across_four_distinct_sessions_and_retirement_is_bottom_up(): void
    {
        // 1. Initiator proposes a campus under the bootstrap organization.
        $this->signIn('org-api-gm');
        $propose = $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => $this->bootstrapOrganizationId,
            'name' => 'API Governance Campus',
        ])->assertCreated()->json();
        $campusRequestId = (string) $propose['request_id'];
        $this->assertSame('proposed', $propose['lifecycle_state']);

        // The initiator cannot review their own proposal (separation of duties).
        $this->postJson("/api/v1/organization/changes/{$campusRequestId}/review")
            ->assertStatus(403)
            ->assertJsonPath('error', 'organization.structure.single_actor');

        // Projection affordances for the initiator: withdraw yes, review no.
        $initiatorWorkspace = $this->getJson('/api/v1/organization/workspace')->assertOk()->json();
        $initiatorRequest = $this->requestIn($initiatorWorkspace['change_requests'], $campusRequestId);
        $this->assertTrue($initiatorRequest['available_actions']['withdraw']);
        $this->assertFalse($initiatorRequest['available_actions']['review']);
        $this->assertTrue($initiatorWorkspace['available_actions']['create_organization']);

        // 2. Distinct reviewer records the independent review.
        $this->signIn('org-api-mgr');
        $reviewerWorkspace = $this->getJson('/api/v1/organization/workspace')->json();
        $reviewerRequest = $this->requestIn($reviewerWorkspace['change_requests'], $campusRequestId);
        $this->assertTrue($reviewerRequest['available_actions']['review']);
        $this->postJson("/api/v1/organization/changes/{$campusRequestId}/review")
            ->assertOk()
            ->assertJsonPath('lifecycle_state', 'reviewed');
        // A reviewer cannot then become an approving owner.
        $this->postJson("/api/v1/organization/changes/{$campusRequestId}/approve")
            ->assertStatus(403)
            ->assertJsonPath('error', 'organization.structure.single_actor');

        // 3. Two distinct owners sign; the second signature executes.
        $this->signIn('org-api-owner-1');
        $this->postJson("/api/v1/organization/changes/{$campusRequestId}/approve")
            ->assertOk()
            ->assertJsonPath('lifecycle_state', 'reviewed');
        // The same owner cannot fill both slots.
        $this->postJson("/api/v1/organization/changes/{$campusRequestId}/approve")
            ->assertStatus(403)
            ->assertJsonPath('error', 'organization.structure.single_actor');

        $this->signIn('org-api-owner-2');
        $executed = $this->postJson("/api/v1/organization/changes/{$campusRequestId}/approve")
            ->assertCreated()
            ->json();
        $this->assertSame('executed', $executed['lifecycle_state']);
        $campusId = (string) $executed['result']['id'];
        $this->assertSame('draft', Campus::query()->findOrFail($campusId)->lifecycle_state);

        // 4. Activate the campus, then create and activate a branch + department.
        $this->chain($this->transitionProposal('campus', $campusId, 'activate'));
        $this->assertSame('active', Campus::query()->findOrFail($campusId)->lifecycle_state);

        $branchId = $this->chain([
            'change_type' => 'create_branch',
            'campus_id' => $campusId,
            'name' => 'API Governance Branch',
            'effective_from' => '2026-09-11',
        ])['result']['id'];
        $this->chain($this->transitionProposal('branch', $branchId, 'activate'));

        $departmentId = $this->chain([
            'change_type' => 'create_department',
            'scope_type' => 'branch',
            'scope_id' => $branchId,
            'name' => 'API Governance Department',
        ])['result']['id'];
        $this->chain($this->transitionProposal('department', $departmentId, 'activate'));

        // 5. Closing the campus while the branch is open is refused at
        //    execution (second owner signature): closure is bottom-up. The
        //    failed execution rolls the second signature back, leaving the
        //    request parked with one owner signature.
        $this->signIn('org-api-gm');
        $closeCampusId = $this->propose($this->transitionProposal('campus', $campusId, 'close'))
            ->assertCreated()->json('request_id');
        $this->signIn('org-api-mgr');
        $this->postJson("/api/v1/organization/changes/{$closeCampusId}/review")->assertOk();
        $this->signIn('org-api-owner-1');
        $this->postJson("/api/v1/organization/changes/{$closeCampusId}/approve")->assertOk();
        $this->signIn('org-api-owner-2');
        $this->postJson("/api/v1/organization/changes/{$closeCampusId}/approve")
            ->assertStatus(409)
            ->assertJsonPath('error', 'organization.close_has_open_branches');
        $parkedRequest = StructureChangeRequest::query()->findOrFail($closeCampusId);
        $this->assertSame('reviewed', $parkedRequest->lifecycle_state);
        $this->assertNotNull($parkedRequest->owner_one_id);
        $this->assertNull($parkedRequest->owner_two_id);
        $this->assertSame('active', Campus::query()->findOrFail($campusId)->lifecycle_state);

        // 6. Bottom-up retirement: department, branch, then the parked campus
        //    request is completed by the second owner and executes.
        $this->chain($this->transitionProposal('department', $departmentId, 'close'));
        $this->chain($this->transitionProposal('branch', $branchId, 'close'));
        $this->signIn('org-api-owner-2');
        $this->postJson("/api/v1/organization/changes/{$closeCampusId}/approve")
            ->assertCreated()
            ->assertJsonPath('lifecycle_state', 'executed');
        $this->assertSame('closed', Campus::query()->findOrFail($campusId)->lifecycle_state);
    }

    public function test_proposal_retries_with_one_idempotency_key_create_one_request(): void
    {
        $this->signIn('org-api-gm');
        $payload = [
            'change_type' => 'create_campus',
            'organization_id' => $this->bootstrapOrganizationId,
            'name' => 'Idempotent API Campus',
        ];
        $key = 'org-api-idem-campus-0001';

        $first = $this->postJson('/api/v1/organization/changes', $payload, ['Idempotency-Key' => $key])->assertCreated()->json();
        $second = $this->postJson('/api/v1/organization/changes', $payload, ['Idempotency-Key' => $key])->assertSuccessful()->json();

        $this->assertSame($first['request_id'], $second['request_id']);
        $this->assertSame(1, StructureChangeRequest::query()
            ->where('change_type', 'create_campus')
            ->where('payload->name', 'Idempotent API Campus')
            ->count());
    }

    public function test_withdraw_and_reject_are_reached_through_the_transport(): void
    {
        $this->signIn('org-api-gm');
        $requestId = $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => $this->bootstrapOrganizationId,
            'name' => 'Withdrawn API Campus',
        ])->json('request_id');
        $this->postJson("/api/v1/organization/changes/{$requestId}/withdraw")
            ->assertOk()
            ->assertJsonPath('lifecycle_state', 'withdrawn');

        // The same change may be proposed again once the prior request is terminal.
        $replacementId = $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => $this->bootstrapOrganizationId,
            'name' => 'Withdrawn API Campus',
        ])->assertCreated()->json('request_id');

        $this->signIn('org-api-mgr');
        $this->postJson("/api/v1/organization/changes/{$replacementId}/reject", ['reason' => 'no'])
            ->assertStatus(422);
        $this->postJson("/api/v1/organization/changes/{$replacementId}/reject", ['reason' => 'campus not in this years plan'])
            ->assertOk()
            ->assertJsonPath('lifecycle_state', 'rejected');
    }

    public function test_validation_errors_are_transport_contracts(): void
    {
        $this->signIn('org-api-gm');
        $this->propose(['change_type' => 'create_campus'])->assertStatus(422);
        $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => RandomIdentifier::new(),
            'name' => 'Nowhere Campus',
        ])->assertStatus(404);
        $this->propose([
            'change_type' => 'transition_unit',
            'unit_type' => 'organization',
            'unit_id' => RandomIdentifier::new(),
            'action' => 'activate',
        ])->assertStatus(422);
    }

    public function test_cross_organization_actors_cannot_see_or_sign_foreign_proposals(): void
    {
        // A sibling organization built through the canonical commands but
        // with NO grants for the bootstrap governance actors on its scope.
        $decision = $this->structureDecisionForGlobalActors();
        $created = $this->createCommand()->createOrganization($decision, 'Sibling Org', RandomIdentifier::new());
        $sibling = \App\Modules\Organization\Models\Organization::query()->findOrFail($created['id']);
        $this->transitionCommand()->activate($sibling->fresh(), $decision, RandomIdentifier::new());

        foreach ([
            'sib-init' => ['organization.structure.initiate'],
            'sib-rev' => ['organization.structure.review'],
            'sib-owner-1' => ['organization.structure.approve'],
            'sib-owner-2' => ['organization.structure.approve'],
        ] as $actorId => $capabilities) {
            $this->personWithAuthority($actorId, []);
            $this->grantScopeAuthority($actorId, $capabilities, 'organization', $sibling->id);
            $this->accountFor($actorId, 'org-api-'.$actorId);
        }

        // A bootstrap-scoped proposal and a sibling-scoped proposal coexist.
        $this->signIn('org-api-gm');
        $bootstrapRequestId = $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => $this->bootstrapOrganizationId,
            'name' => 'Bootstrap Side Campus',
        ])->json('request_id');

        $this->signIn('org-api-sib-init');
        $siblingRequestId = $this->propose([
            'change_type' => 'create_campus',
            'organization_id' => $sibling->id,
            'name' => 'Sibling Side Campus',
        ])->assertCreated()->json('request_id');

        // The bootstrap reviewer cannot touch the sibling proposal...
        $this->signIn('org-api-mgr');
        $mgrWorkspace = $this->getJson('/api/v1/organization/workspace')->json();
        $mgrIds = array_column($mgrWorkspace['change_requests'], 'id');
        $this->assertContains($bootstrapRequestId, $mgrIds);
        $this->assertNotContains($siblingRequestId, $mgrIds);
        $this->postJson("/api/v1/organization/changes/{$siblingRequestId}/review")->assertStatus(403);

        // ...and the sibling reviewer cannot see or sign the bootstrap one.
        $this->signIn('org-api-sib-rev');
        $sibWorkspace = $this->getJson('/api/v1/organization/workspace')->json();
        $sibIds = array_column($sibWorkspace['change_requests'], 'id');
        $this->assertContains($siblingRequestId, $sibIds);
        $this->assertNotContains($bootstrapRequestId, $sibIds);
        $this->postJson("/api/v1/organization/changes/{$bootstrapRequestId}/review")->assertStatus(403);
        $this->postJson("/api/v1/organization/changes/{$siblingRequestId}/review")->assertOk();

        $this->signIn('org-api-sib-owner-1');
        $this->postJson("/api/v1/organization/changes/{$siblingRequestId}/approve")->assertOk();
        $this->signIn('org-api-sib-owner-2');
        $this->postJson("/api/v1/organization/changes/{$siblingRequestId}/approve")->assertCreated();

        $this->assertSame(
            1,
            Campus::query()->where('organization_id', $sibling->id)->where('name', 'Sibling Side Campus')->count(),
        );
    }

    /** Posts a proposal with a fresh client-generated idempotency key. */
    private function propose(array $body): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/organization/changes', $body, ['Idempotency-Key' => RandomIdentifier::new()]);
    }

    /** Posts a proposal and walks all four signatures, returning the final outcome. */
    private function chain(array $proposalBody): array
    {
        $this->signIn('org-api-gm');
        $outcome = $this->propose($proposalBody)->assertCreated()->json();
        $requestId = (string) $outcome['request_id'];

        $this->signIn('org-api-mgr');
        $this->postJson("/api/v1/organization/changes/{$requestId}/review")->assertOk();
        $this->signIn('org-api-owner-1');
        $this->postJson("/api/v1/organization/changes/{$requestId}/approve")->assertOk();
        $this->signIn('org-api-owner-2');

        return $this->postJson("/api/v1/organization/changes/{$requestId}/approve")->assertCreated()->json();
    }

    private function transitionProposal(string $unitType, string $unitId, string $action): array
    {
        return [
            'change_type' => 'transition_unit',
            'unit_type' => $unitType,
            'unit_id' => $unitId,
            'action' => $action,
        ];
    }

    /** @param array<int, array<string, mixed>> $requests */
    private function requestIn(array $requests, string $id): array
    {
        foreach ($requests as $request) {
            if ($request['id'] === $id) {
                return $request;
            }
        }
        $this->fail("proposal {$id} was not present in the workspace projection");
    }

    private function accountFor(string $personId, string $username): void
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
        // The four-actor chain is driven across distinct sessions, so the
        // test legitimately re-uses the same accounts many times within a
        // minute. The per-account brute-force limiter protects production;
        // reset its window between simulated sessions instead of weakening it.
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        // ThrottleRequests hashes the named-limiter key: md5(name . limitKey),
        // where the login limit key is "login|ip|username".
        foreach (['127.0.0.1', '::1', ''] as $ip) {
            $limiter->clear(md5('login'.'login|'.$ip.'|'.mb_strtolower($username)));
        }
        $this->post('/logout');
        $this->post('/login', ['username' => $username, 'password' => self::PASSWORD])->assertRedirect('/');
    }
}
