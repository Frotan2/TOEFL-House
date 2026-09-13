<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Hr\Commands\MaintainContract;
use App\Modules\Hr\Commands\MaintainContractVersion;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Commands\MaintainLeave;
use App\Modules\Hr\Commands\MaintainScale;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\ContractVersion;
use App\Modules\Hr\Models\Employment;
use App\Modules\Hr\Models\Leave;
use App\Modules\Hr\Models\Scale;
use App\Modules\Identity\Models\Person;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * HR API transport contract: authentication gate, structured responses,
 * domain error mapping, authorization enforcement, and idempotency for
 * the People & HR command surface.
 *
 * The JSON API delegates to the same authoritative commands as the web
 * console. These tests verify the HTTP transport layer without
 * reimplementing business rules the commands own.
 */
final class HrApiFeatureTest extends TestCase
{
    use BuildsActors;

    private string $personId = 'hrapi-teacher-1';

    private string $employmentId = '';

    private function setUpEmployment(): void
    {
        $this->personWithAuthority($this->personId, []);

        $manager = $this->grantedActor('hrapi-mgr-1', ['hr.employ', 'hr.contract', 'hr.terminate', 'access.assign_position']);
        $employment = app(MaintainEmployment::class)->employ($manager, $this->personId, 'hrapi-emp-1');
        $this->employmentId = $employment['employment_id'];

        $contract = app(MaintainContract::class)->draft(
            $manager,
            Employment::query()->findOrFail($this->employmentId),
            'full-time EFL instructor, 40h/week',
            '2026-09-01',
            'hrapi-con-1',
        );
        app(MaintainContract::class)->sign(
            $manager,
            Contract::query()->findOrFail($contract['contract_id']),
            'signed/hrapi-con-1.pdf',
            'hrapi-con-2',
        );
        app(MaintainEmployment::class)->hire(
            $manager,
            Employment::query()->findOrFail($this->employmentId),
            '2026-09-01',
            'hrapi-emp-2',
        );
    }

    private function signInAs(string $personId, string $username): void
    {
        $existing = \App\Modules\Identity\Models\UserAccount::query()
            ->where('person_id', $personId)
            ->where('account_state', \App\Modules\Identity\Models\UserAccount::STATE_ACTIVE)
            ->first();
        if ($existing) {
            $this->post('/login', ['username' => $existing->username, 'password' => 'employee-password-1'])->assertRedirect('/');

            return;
        }
        \App\Modules\Identity\Models\UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => \Illuminate\Support\Facades\Hash::make('employee-password-1'),
            'account_state' => \App\Modules\Identity\Models\UserAccount::STATE_ACTIVE,
        ]);
        $this->post('/login', ['username' => $username, 'password' => 'employee-password-1'])->assertRedirect('/');
    }

    public function test_hr_workspace_requires_authentication(): void
    {
        $this->getJson('/api/v1/hr/workspace')
            ->assertUnauthorized();
    }

    public function test_hr_workspace_returns_documented_shape(): void
    {
        $this->setUpEmployment();
        $mgr = $this->grantedActor('hrapi-mgr-1', ['hr.employ']);
        $this->signInAs($mgr->actorId, 'hrapi.mgr.1');

        $this->getJson('/api/v1/hr/workspace')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'people',
                    'employments',
                    'leaves',
                    'contracts',
                    'contract_versions',
                    'scales',
                    'capabilities' => ['employ', 'contract'],
                ],
            ]);
    }

    public function test_employ_endpoint_creates_candidate_and_returns_201(): void
    {
        $mgr = $this->grantedActor('hrapi-emp-mgr', ['hr.employ']);
        $person = $this->personWithAuthority('hrapi-emp-person', []);
        $this->signInAs($mgr->actorId, 'hrapi.emp.mgr');

        $this->postJson('/api/v1/hr/employ', ['person_id' => $person->id], ['Idempotency-Key' => 'hrapi-employ-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'candidate_created')
            ->assertJsonStructure(['result' => ['employment_id', 'correlation_id']]);

        $this->assertDatabaseHas('employments', ['person_id' => $person->id, 'lifecycle_state' => 'candidate']);
    }

    public function test_employ_unauthenticated_is_refused(): void
    {
        $this->postJson('/api/v1/hr/employ', ['person_id' => 'any-id'])
            ->assertUnauthorized();
    }

    public function test_employ_unauthorized_actor_is_forbidden(): void
    {
        $nobody = $this->grantedActor('hrapi-nobody', []);
        $person = $this->personWithAuthority('hrapi-denied-person', []);
        $this->signInAs($nobody->actorId, 'hrapi.nobody');

        $this->postJson('/api/v1/hr/employ', ['person_id' => $person->id], ['Idempotency-Key' => 'hrapi-employ-denied'])
            ->assertForbidden()
            ->assertJsonPath('category', 'authorization');

        $this->assertDatabaseMissing('employments', ['person_id' => $person->id]);
    }

    public function test_employment_transition_hire_endpoint(): void
    {
        $this->setUpEmployment();
        $mgr = $this->grantedActor('hrapi-mgr-1', ['hr.employ', 'hr.contract', 'hr.terminate']);
        $this->signInAs($mgr->actorId, 'hrapi.mgr.1');

        $this->postJson('/api/v1/hr/employments/'.$this->employmentId.'/hire', [
            'effective_from' => '2026-09-01',
        ], ['Idempotency-Key' => 'hrapi-hire-001'])
            ->assertOk()
            ->assertJsonPath('status', 'hire_recorded');
    }

    public function test_employment_transition_terminate_with_reason(): void
    {
        $this->setUpEmployment();
        $mgr = $this->grantedActor('hrapi-mgr-1', ['hr.employ', 'hr.terminate', 'access.assign_position']);
        $this->signInAs($mgr->actorId, 'hrapi.mgr.1');

        $this->postJson('/api/v1/hr/employments/'.$this->employmentId.'/terminate', [
            'effective_from' => '2026-10-01',
            'reason' => 'end of contract term',
        ], ['Idempotency-Key' => 'hrapi-term-001'])
            ->assertOk()
            ->assertJsonPath('status', 'terminate_recorded');

        $this->assertDatabaseHas('employments', ['id' => $this->employmentId, 'lifecycle_state' => 'terminated']);
    }

    public function test_leave_request_and_decide_lifecycle(): void
    {
        $this->setUpEmployment();
        $requester = $this->grantedActor('hrapi-leave-req', ['hr.leave_request']);
        $decider = $this->grantedActor('hrapi-leave-dec', ['hr.leave_approve']);
        $this->signInAs($requester->actorId, 'hrapi.leave.req');

        // Request leave
        $response = $this->postJson('/api/v1/hr/employments/'.$this->employmentId.'/leave', [
            'category' => 'annual',
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-10',
            'reason' => 'family vacation',
        ], ['Idempotency-Key' => 'hrapi-leave-req-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'requested');
        $leaveId = $response->json('result.leave_id');

        // Sign in as decider and approve
        $this->signInAs($decider->actorId, 'hrapi.leave.dec');
        $this->postJson('/api/v1/hr/leaves/'.$leaveId.'/decide', [
            'decision' => 'approve',
        ], ['Idempotency-Key' => 'hrapi-leave-dec-001'])
            ->assertOk()
            ->assertJsonPath('status', 'decided');

        $this->assertDatabaseHas('leaves', ['id' => $leaveId, 'lifecycle_state' => 'approved', 'decided_by' => $decider->actorId]);
    }

    public function test_leave_cancel_after_approval(): void
    {
        $this->setUpEmployment();
        $requester = $this->grantedActor('hrapi-cancel-req', ['hr.leave_request', 'hr.leave_approve']);
        $decider = $this->grantedActor('hrapi-cancel-dec', ['hr.leave_approve']);

        $employment = Employment::query()->findOrFail($this->employmentId);
        $leaveResult = app(MaintainLeave::class)->request($requester, $employment, 'annual', '2026-12-01', '2026-12-10', 'planned', 'hrapi-cancel-1');
        app(MaintainLeave::class)->decide($decider, Leave::query()->findOrFail($leaveResult['leave_id']), true, 'hrapi-cancel-2');

        $this->signInAs($requester->actorId, 'hrapi.cancel.req');
        $this->postJson('/api/v1/hr/leaves/'.$leaveResult['leave_id'].'/cancel', [], ['Idempotency-Key' => 'hrapi-cancel-3'])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('leaves', ['id' => $leaveResult['leave_id'], 'lifecycle_state' => 'cancelled']);
    }

    public function test_contract_version_prepare_submit_approve_lifecycle(): void
    {
        $this->setUpEmployment();
        $fm = $this->grantedActor('hrapi-fm-1', ['hr.contract.prepare']);
        $gm = $this->grantedActor('hrapi-gm-1', ['hr.contract.approve']);
        $this->signInAs($fm->actorId, 'hrapi.fm.1');

        // Prepare
        $prepared = $this->postJson('/api/v1/hr/contract-versions', [
            'employment_id' => $this->employmentId,
            'terms_ref' => 'contract/terms-api.pdf',
            'effective_from' => '2026-09-01',
        ], ['Idempotency-Key' => 'hrapi-ver-prep-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'prepared');
        $versionId = $prepared->json('result.version_id');

        // Add rule
        $this->postJson('/api/v1/hr/contract-versions/'.$versionId.'/rules', [
            'method' => 'fixed_monthly',
            'rate' => '20000.00',
        ], ['Idempotency-Key' => 'hrapi-ver-rule-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'rule_added');

        // Submit
        $this->postJson('/api/v1/hr/contract-versions/'.$versionId.'/submit', [], ['Idempotency-Key' => 'hrapi-ver-sub-001'])
            ->assertOk()
            ->assertJsonPath('status', 'submit_recorded');

        // Approve as GM
        $this->signInAs($gm->actorId, 'hrapi.gm.1');
        $this->postJson('/api/v1/hr/contract-versions/'.$versionId.'/approve', [], ['Idempotency-Key' => 'hrapi-ver-appr-001'])
            ->assertOk()
            ->assertJsonPath('status', 'approve_recorded');

        $this->assertDatabaseHas('contract_versions', ['id' => $versionId, 'approved_by' => $gm->actorId]);
    }

    public function test_discard_rule_endpoint(): void
    {
        $this->setUpEmployment();
        $fm = $this->grantedActor('hrapi-discard-fm', ['hr.contract.prepare']);
        $employment = Employment::query()->findOrFail($this->employmentId);
        $prepared = app(MaintainContractVersion::class)->prepare($fm, $employment, 'terms-discard.pdf', null, '2026-09-01', null, 'hrapi-discard-1');
        $version = ContractVersion::query()->findOrFail($prepared['version_id']);
        $ruleResult = app(MaintainContractVersion::class)->addRule($fm, $version, 'fixed_monthly', '10000.00', null, null, null, 'hrapi-discard-rule-1');

        $this->signInAs($fm->actorId, 'hrapi.discard.fm');
        $this->postJson('/api/v1/hr/contract-versions/rules/'.$ruleResult['rule_id'].'/discard', [], ['Idempotency-Key' => 'hrapi-discard-2'])
            ->assertOk()
            ->assertJsonPath('status', 'rule_discarded');

        $this->assertDatabaseMissing('compensation_rules', ['id' => $ruleResult['rule_id']]);
    }

    public function test_scale_register_and_retire_lifecycle(): void
    {
        $registrar = $this->grantedActor('hrapi-scale-1', ['hr.scale']);
        $this->signInAs($registrar->actorId, 'hrapi.scale.1');

        // Register
        $registered = $this->postJson('/api/v1/hr/scales', [
            'key' => 'S5',
            'name' => 'Master',
            'rank_order' => 5,
        ], ['Idempotency-Key' => 'hrapi-scale-reg-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'registered');
        $scaleId = $registered->json('result.scale_id');

        $this->assertDatabaseHas('scales', ['id' => $scaleId, 'key' => 'S5', 'lifecycle_state' => 'active']);

        // Retire
        $this->postJson('/api/v1/hr/scales/'.$scaleId.'/retire', [], ['Idempotency-Key' => 'hrapi-scale-ret-001'])
            ->assertOk()
            ->assertJsonPath('status', 'retired');

        $this->assertDatabaseHas('scales', ['id' => $scaleId, 'lifecycle_state' => 'retired']);
    }

    public function test_contract_draft_sign_close_lifecycle(): void
    {
        $this->setUpEmployment();
        $mgr = $this->grantedActor('hrapi-con-mgr', ['hr.contract', 'hr.employ']);
        $this->signInAs($mgr->actorId, 'hrapi.con.mgr');

        // Close existing active contract first (from setUp) by terminating the employment
        // and re-creating a fresh employment for this test
        $this->personWithAuthority('hrapi-con-person', []);
        $freshEmployment = app(MaintainEmployment::class)->employ($mgr, 'hrapi-con-person', 'hrapi-con-emp-1');

        // Draft
        $drafted = $this->postJson('/api/v1/hr/contracts', [
            'employment_id' => $freshEmployment['employment_id'],
            'terms_summary' => 'Part-time instructor, 20h/week',
            'effective_from' => '2026-10-01',
        ], ['Idempotency-Key' => 'hrapi-con-draft-001'])
            ->assertCreated()
            ->assertJsonPath('status', 'drafted');
        $contractId = $drafted->json('result.contract_id');

        // Sign
        $this->postJson('/api/v1/hr/contracts/'.$contractId.'/sign', [
            'signed_ref' => 'signed/hrapi-con.pdf',
        ], ['Idempotency-Key' => 'hrapi-con-sign-001'])
            ->assertOk()
            ->assertJsonPath('status', 'signed');

        $this->assertDatabaseHas('contracts', ['id' => $contractId, 'lifecycle_state' => 'active']);

        // Close
        $this->postJson('/api/v1/hr/contracts/'.$contractId.'/close', [
            'effective_to' => '2026-12-31',
        ], ['Idempotency-Key' => 'hrapi-con-close-001'])
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('contracts', ['id' => $contractId, 'lifecycle_state' => 'closed']);
    }

    public function test_domain_rejection_maps_to_structured_json_not_500(): void
    {
        $mgr = $this->grantedActor('hrapi-reject-mgr', ['hr.employ']);
        $this->personWithAuthority('hrapi-reject-person', []);

        // Create employment to get a candidate
        $employment = app(MaintainEmployment::class)->employ($mgr, 'hrapi-reject-person', 'hrapi-reject-1');

        $this->signInAs($mgr->actorId, 'hrapi.reject.mgr');

        // Attempt hire without contract (should be rejected by domain, not crash)
        $this->postJson('/api/v1/hr/employments/'.$employment['employment_id'].'/hire', [
            'effective_from' => '2026-09-01',
        ], ['Idempotency-Key' => 'hrapi-reject-hire-001'])
            ->assertStatus(422);
    }

    public function test_idempotency_key_is_honored_for_employ(): void
    {
        $mgr = $this->grantedActor('hrapi-idem-mgr', ['hr.employ']);
        $person = $this->personWithAuthority('hrapi-idem-person', []);
        $this->signInAs($mgr->actorId, 'hrapi.idem.mgr');

        $key = 'hrapi-idem-key-001';
        $payload = ['person_id' => $person->id];

        $first = $this->postJson('/api/v1/hr/employ', $payload, ['Idempotency-Key' => $key]);
        $first->assertCreated();

        // Replay with same key returns same result without duplicating
        $second = $this->postJson('/api/v1/hr/employ', $payload, ['Idempotency-Key' => $key]);
        $second->assertSuccessful();

        $this->assertSame(1, Employment::query()->where('person_id', $person->id)->count());
    }

    public function test_unknown_employment_action_returns_404(): void
    {
        $this->setUpEmployment();
        $mgr = $this->grantedActor('hrapi-mgr-1', ['hr.employ']);
        $this->signInAs($mgr->actorId, 'hrapi.mgr.1');

        $this->postJson('/api/v1/hr/employments/'.$this->employmentId.'/nonexistent', [
            'effective_from' => '2026-09-01',
        ])->assertNotFound();
    }

    public function test_unknown_version_action_returns_404(): void
    {
        $this->setUpEmployment();
        $fm = $this->grantedActor('hrapi-mgr-1', ['hr.contract.prepare']);
        $employment = Employment::query()->findOrFail($this->employmentId);
        $prepared = app(MaintainContractVersion::class)->prepare($fm, $employment, 'terms-404.pdf', null, '2026-09-01', null, 'hrapi-404-1');

        $this->signInAs($fm->actorId, 'hrapi.mgr.1');
        $this->postJson('/api/v1/hr/contract-versions/'.$prepared['version_id'].'/nonexistent')
            ->assertNotFound();
    }

    public function test_version_withdraw_before_approval(): void
    {
        $this->setUpEmployment();
        $fm = $this->grantedActor('hrapi-wd-fm', ['hr.contract.prepare']);
        $employment = Employment::query()->findOrFail($this->employmentId);
        $prepared = app(MaintainContractVersion::class)->prepare($fm, $employment, 'terms-wd.pdf', null, '2026-09-01', null, 'hrapi-wd-1');
        $version = ContractVersion::query()->findOrFail($prepared['version_id']);
        app(MaintainContractVersion::class)->addRule($fm, $version, 'fixed_monthly', '10000.00', null, null, null, 'hrapi-wd-rule');

        $this->signInAs($fm->actorId, 'hrapi.wd.fm');
        $this->postJson('/api/v1/hr/contract-versions/'.$prepared['version_id'].'/withdraw', [], ['Idempotency-Key' => 'hrapi-wd-2'])
            ->assertOk()
            ->assertJsonPath('status', 'withdraw_recorded');

        $this->assertDatabaseHas('contract_versions', ['id' => $prepared['version_id'], 'lifecycle_state' => 'withdrawn']);
    }
}
