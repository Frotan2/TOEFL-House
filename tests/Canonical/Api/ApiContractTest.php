<?php

declare(strict_types=1);

namespace Tests\Canonical\Api;

use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Canonical\CanonicalTestCase;

/**
 * API transport contract.
 *
 * The canonical API is versioned at `/api/v1`. These tests assert the
 * behaviours a frontend actually depends on — status code, response shape and
 * the authentication boundary — rather than only that a route returns 200.
 *
 * A missing route is a real defect class here: `/api/v1/payroll/workspace`
 * shipped unregistered while its controller method existed, and the console
 * loaded with a 404. That escaped every check that stopped at the page status.
 */
final class ApiContractTest extends CanonicalTestCase
{
    /** Endpoints every authenticated console calls on mount. */
    private const WORKSPACE_ENDPOINTS = [
        '/api/v1/me',
        '/api/v1/academic/workspace',
        '/api/v1/payroll/workspace',
        '/api/v1/crm/visitors',
        '/api/v1/crm/branches',
        '/api/v1/placement/profiles',
        '/api/v1/notifications',
    ];

    private function signIn(): void
    {
        $person = Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => 'API Contract Probe',
            'date_of_birth' => '1985-01-01',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'canon-api-probe',
            'identity_evidence_ref' => 'evidence/canon-api-probe',
            'verified_by' => 'canon-api-verifier',
            'verified_at' => now()->toDateTimeString(),
            'home_branch_id' => $this->sharedBranchId(),
        ]);

        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'username' => 'canon.api.probe',
            'password_hash' => Hash::make('canon-api-pw-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        $this->post('/login', [
            'username' => 'canon.api.probe',
            'password' => 'canon-api-pw-1',
        ])->assertRedirect('/');
    }

    public function test_every_workspace_endpoint_is_registered(): void
    {
        $this->signIn();

        $missing = [];
        foreach (self::WORKSPACE_ENDPOINTS as $endpoint) {
            if ($this->getJson($endpoint)->getStatusCode() === 404) {
                $missing[] = $endpoint;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'These endpoints are called by a console on mount but are not routed: '
            .implode(', ', $missing)
        );
    }

    public function test_unauthenticated_reads_are_refused(): void
    {
        foreach (self::WORKSPACE_ENDPOINTS as $endpoint) {
            $response = $this->getJson($endpoint);

            $this->assertSame(
                401,
                $response->getStatusCode(),
                "{$endpoint} must refuse an unauthenticated read, got {$response->getStatusCode()}."
            );
        }
    }

    public function test_the_identity_endpoint_returns_the_documented_shape(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['username', 'person_id', 'display_name']])
            ->assertJsonPath('data.username', 'canon.api.probe');
    }

    public function test_an_unknown_versioned_route_is_not_found(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/definitely-not-a-real-endpoint')->assertNotFound();
    }

    public function test_unversioned_api_paths_are_not_served(): void
    {
        $this->signIn();

        // The API is versioned. An unversioned path must not silently work:
        // the E2E journey scripts drifted onto /api/... and returned 404s that
        // were invisible until they were executed.
        $this->getJson('/api/me')->assertNotFound();
    }
}
