<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Hr\Commands\MaintainContract;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employment;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\WorkManagement\Models\WorkItem;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * The Work Management endpoints are mutation transports even when a malformed
 * payload would later be rejected by a command. Validate shape and bounded
 * free text before resolving a target or building idempotency/audit metadata.
 */
final class WorkManagementApiInputValidationTest extends TestCase
{
    use BuildsActors;

    protected function setUp(): void
    {
        parent::setUp();

        $person = $this->personWithAuthority('work-api-input-actor', ['workflow.work', 'workflow.queue.manage']);
        // A candidate cannot use authority of their own before hire. Use
        // separately authorized HR actors so this fixture follows that
        // production boundary rather than inserting an active employment.
        $hrOfficer = $this->grantedActor('work-api-input-hr', ['hr.employ']);
        $contractOfficer = $this->grantedActor('work-api-input-contract', ['hr.contract']);
        $employment = app(MaintainEmployment::class)->employ($hrOfficer, $person->id, 'work-api-input-employ');
        $employmentModel = Employment::query()->findOrFail($employment['employment_id']);
        $contract = app(MaintainContract::class)->draft(
            $contractOfficer,
            $employmentModel,
            'Work Management API input validation fixture terms',
            now()->toDateString(),
            'work-api-input-contract-draft',
        );
        app(MaintainContract::class)->sign(
            $contractOfficer,
            Contract::query()->findOrFail($contract['contract_id']),
            'evidence/work-api-input/contract',
            'work-api-input-contract-sign',
        );
        app(MaintainEmployment::class)->hire(
            $hrOfficer,
            Employment::query()->findOrFail($employment['employment_id']),
            now()->toDateString(),
            'work-api-input-hire',
        );
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'username' => 'work-api-input',
            'password_hash' => Hash::make('work-api-input-password'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
        $this->post('/login', [
            'username' => 'work-api-input',
            'password' => 'work-api-input-password',
        ])->assertRedirect('/');
    }

    public function test_transition_rejects_malformed_or_unbounded_payloads_before_target_lookup(): void
    {
        $this->postJson('/api/v1/work-items/not-a-real-work-item/transition', [
            'to_state' => ['claimed'],
            'note' => str_repeat('n', 1001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_state', 'note']);
    }

    public function test_transition_accepts_a_valid_bounded_note_and_records_it_as_history(): void
    {
        $item = WorkItem::query()->create([
            'id' => RandomIdentifier::new(),
            'kind' => 'task',
            'title' => 'Input validation fixture',
            'source_type' => 'test_fixture',
            'source_id' => 'work-api-input-source',
            'action_key' => 'test.fixture',
            'organization_id' => $this->organizationIdFromScopeKey('bootstrap'),
            'branch_id' => $this->bootstrapBranchId(),
            'assigned_to' => 'work-api-input-actor',
            'priority' => 100,
            'lifecycle_state' => 'open',
            'created_by' => 'work-api-input-actor',
        ]);

        $this->postJson('/api/v1/work-items/'.$item->id.'/transition', [
            'to_state' => 'claimed',
            'note' => 'Reviewed by the queue owner.',
        ], ['Idempotency-Key' => 'work-api-valid-transition-1'])
            ->assertOk()
            ->assertJsonPath('data.work_item_id', $item->id)
            ->assertJsonPath('data.lifecycle_state', 'claimed');

        $this->assertDatabaseHas('work_items', ['id' => $item->id, 'lifecycle_state' => 'claimed']);
        $this->assertDatabaseHas('work_item_history', [
            'work_item_id' => $item->id,
            'event_type' => 'work_item.claimed',
        ]);
    }

    public function test_queue_membership_grant_accepts_valid_bounded_identifiers(): void
    {
        $branchId = $this->bootstrapBranchId();
        $organizationId = $this->organizationIdFromScopeKey('bootstrap');

        $this->postJson('/api/v1/work-queues/memberships', [
            'actor_id' => 'work-api-input-actor',
            'queue_key' => 'academic.appeal',
            'branch_id' => $branchId,
            'organization_id' => $organizationId,
        ], ['Idempotency-Key' => 'work-api-valid-membership-1'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['membership_id', 'correlation_id']]);

        $this->assertDatabaseHas('work_queue_memberships', [
            'actor_id' => 'work-api-input-actor',
            'queue_key' => 'academic.appeal',
            'organization_id' => $organizationId,
            'branch_id' => $branchId,
            'lifecycle_state' => 'active',
        ]);
    }

    public function test_queue_membership_grant_requires_bounded_scalar_identifiers(): void
    {
        $this->postJson('/api/v1/work-queues/memberships', [
            'actor_id' => str_repeat('a', 37),
            'queue_key' => str_repeat('q', 101),
            'branch_id' => ['not-a-scalar'],
            'organization_id' => str_repeat('o', 37),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['actor_id', 'queue_key', 'branch_id', 'organization_id']);
    }
}
