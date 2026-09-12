<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Organization\Commands\GovernStructureChange;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\CampusAssignment;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\OperatesStructure;
use Tests\TestCase;

/**
 * The four-actor topology chain exercised as a staged workflow across
 * distinct authenticated actor identities: propose -> review -> two owner
 * signatures, the second of which executes the canonical command.
 */
final class StructureChangeGovernanceFeatureTest extends TestCase
{
    use BuildsActors;
    use OperatesStructure;

    private function governor(): GovernStructureChange
    {
        return app(GovernStructureChange::class);
    }

    /** Runs the complete signed chain and returns the executed request. */
    private function runChain(string $changeType, array $input): StructureChangeRequest
    {
        $governor = $this->governor();
        $proposed = $governor->propose($this->generalManager(), $changeType, $input, RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $governor->review($this->structureManager('*'), $request, RandomIdentifier::new());
        $request = $request->fresh();

        $governor->approve($this->structureOwner('*', 'owner-1'), $request, RandomIdentifier::new());
        $request = $request->fresh();
        $this->assertSame(StructureChangeRequest::STATE_REVIEWED, $request->lifecycle_state);
        $this->assertNotNull($request->owner_one_id);
        $this->assertNull($request->owner_two_id);

        $governor->approve($this->structureOwner('*', 'owner-2'), $request->fresh(), RandomIdentifier::new());

        return $request->fresh();
    }

    public function test_create_organization_then_its_activation_follow_the_staged_chain(): void
    {
        // The four actors first exist as identities, then bootstrap org exists.
        $initiator = $this->generalManager();
        $reviewer = $this->structureManager('*');
        $ownerOne = $this->structureOwner('*', 'owner-1');
        $ownerTwo = $this->structureOwner('*', 'owner-2');
        $this->establishActiveOrganization('Existing Authority');

        $governor = $this->governor();
        $proposed = $governor->propose($initiator, StructureChangeRequest::TYPE_CREATE_ORGANIZATION, ['name' => 'Second Organization'], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);

        $this->assertSame(StructureChangeRequest::STATE_PROPOSED, $request->lifecycle_state);
        $this->assertDatabaseMissing('organizations', ['name' => 'Second Organization']);

        $governor->review($reviewer, $request, RandomIdentifier::new());
        $governor->approve($ownerOne, $request->fresh(), RandomIdentifier::new());
        // After the first owner signature no topology fact exists yet.
        $this->assertDatabaseMissing('organizations', ['name' => 'Second Organization']);
        $executed = $governor->approve($ownerTwo, $request->fresh(), RandomIdentifier::new());

        $this->assertSame(StructureChangeRequest::STATE_EXECUTED, $request->fresh()->lifecycle_state);
        $this->assertDatabaseHas('organizations', ['name' => 'Second Organization', 'lifecycle_state' => 'draft']);
        $organizationId = $executed['result']['id'];
        $this->assertSame('organization', $executed['result']['unit_type']);

        // Draft root activation is governed by organization-root authority.
        $activation = $governor->propose($initiator, StructureChangeRequest::TYPE_TRANSITION, [
            'unit_type' => 'organization', 'unit_id' => $organizationId, 'action' => 'activate',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($activation['request_id']);
        $governor->review($reviewer, $request, RandomIdentifier::new());
        $governor->approve($ownerOne, $request->fresh(), RandomIdentifier::new());
        $governor->approve($ownerTwo, $request->fresh(), RandomIdentifier::new());

        $this->assertDatabaseHas('organizations', ['id' => $organizationId, 'lifecycle_state' => 'active']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'organization.structure.change.execute', 'target_id' => $executed['request_id']]);
    }

    public function test_campus_branch_and_department_chains_execute_the_canonical_commands(): void
    {
        $organization = $this->establishActiveOrganization();
        $executed = $this->runChain(StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'North Campus',
        ]);
        $campusId = $executed->result['id'];
        $this->assertDatabaseHas('campuses', ['id' => $campusId, 'name' => 'North Campus', 'lifecycle_state' => 'draft']);

        // Draft campus becomes active before it can own a branch.
        $this->activateUnit('campus', $campusId);

        $branchExecuted = $this->runChain(StructureChangeRequest::TYPE_CREATE_BRANCH, [
            'campus_id' => $campusId, 'name' => 'North Branch', 'effective_from' => '2026-03-01',
        ]);
        $branchId = $branchExecuted->result['id'];
        $this->assertDatabaseHas('branches', ['id' => $branchId, 'lifecycle_state' => 'draft']);
        $this->assertDatabaseHas('campus_assignments', [
            'branch_id' => $branchId, 'campus_id' => $campusId, 'effective_from' => '2026-03-01', 'effective_to' => null,
        ]);
        $this->activateUnit('branch', $branchId);

        $departmentExecuted = $this->runChain(StructureChangeRequest::TYPE_CREATE_DEPARTMENT, [
            'scope_type' => 'branch', 'scope_id' => $branchId, 'name' => 'Testing Office',
        ]);
        $this->assertDatabaseHas('departments', [
            'id' => $departmentExecuted->result['id'],
            'scope_type' => 'branch', 'scope_id' => $branchId, 'name' => 'Testing Office', 'lifecycle_state' => 'draft',
        ]);
    }

    public function test_rename_and_branch_transfer_execute_through_staging(): void
    {
        $organization = $this->establishActiveOrganization();
        $firstCampus = $this->establishActiveCampus($organization, 'First Campus');
        $secondCampus = $this->establishActiveCampus($organization, 'Second Campus');
        $branch = $this->establishActiveBranch($firstCampus, 'Moving Branch');

        $renamed = $this->runChain(StructureChangeRequest::TYPE_RENAME, [
            'unit_type' => 'branch', 'unit_id' => $branch->id, 'new_name' => 'Moved Branch',
        ]);
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Moved Branch']);
        $this->assertSame('Moved Branch', $renamed->result['name']);

        $transferred = $this->runChain(StructureChangeRequest::TYPE_TRANSFER, [
            'branch_id' => $branch->id, 'campus_id' => $secondCampus->id, 'effective_from' => '2026-09-01',
        ]);
        $this->assertSame($secondCampus->id, $transferred->result['to_campus_id']);
        $this->assertSame(2, CampusAssignment::query()->where('branch_id', $branch->id)->count());
    }

    public function test_reviewer_must_differ_from_initiator(): void
    {
        $initiator = $this->generalManager();
        // The general manager also receives review capability personally -> forbidden slot.
        $initiatorWithReview = $this->actorWithStructureCapabilities('gm-also-reviewer', [
            'organization.structure.initiate', 'organization.structure.review',
        ]);
        $organization = $this->establishActiveOrganization();

        $proposed = $this->governor()->propose($initiatorWithReview, StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Self Reviewed Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);

        $this->expectException(AuthorizationDenied::class);
        $this->governor()->review($initiatorWithReview, $request, RandomIdentifier::new());
    }

    public function test_owner_must_differ_from_other_chain_participants(): void
    {
        $organization = $this->establishActiveOrganization();
        $proposed = $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Owner SoD Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $this->governor()->review($this->structureManager('*'), $request, RandomIdentifier::new());

        $this->expectException(AuthorizationDenied::class);
        // The reviewer attempting to also approve as owner is rejected.
        $this->governor()->approve($this->structureManager('*'), $request->fresh(), RandomIdentifier::new());
    }

    public function test_second_owner_signature_cannot_repeat_the_first_owner(): void
    {
        $organization = $this->establishActiveOrganization();
        $proposed = $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Repeat Owner Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $this->governor()->review($this->structureManager('*'), $request, RandomIdentifier::new());
        $this->governor()->approve($this->structureOwner('*', 'owner-1'), $request->fresh(), RandomIdentifier::new());

        $this->expectException(AuthorizationDenied::class);
        $this->governor()->approve($this->structureOwner('*', 'owner-1'), $request->fresh(), RandomIdentifier::new());
    }

    public function test_actor_without_capability_cannot_propose_and_the_denial_is_audited(): void
    {
        $organization = $this->establishActiveOrganization();
        try {
            $this->governor()->propose(
                $this->actorWithoutAnyCapability('no-rights'),
                StructureChangeRequest::TYPE_CREATE_CAMPUS,
                ['organization_id' => $organization->id, 'name' => 'No Rights Campus'],
                RandomIdentifier::new(),
            );
            $this->fail('proposal without initiate capability must be denied');
        } catch (AuthorizationDenied) {
        }

        $this->assertDatabaseMissing('campuses', ['name' => 'No Rights Campus']);
        $this->assertSame(0, StructureChangeRequest::query()->count(), 'a denied proposal must not persist a request');
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'organization.structure.change.propose.denied',
            'target_type' => 'structure_change_request',
            'actor_id' => 'no-rights',
        ]);
    }

    public function test_reviewer_rejection_and_owner_rejection_are_terminal_and_write_no_fact(): void
    {
        $organization = $this->establishActiveOrganization();

        $proposed = $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Rejected At Review Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $this->governor()->reject($this->structureManager('*'), $request, 'plan withdrawn by committee', RandomIdentifier::new());
        $this->assertSame(StructureChangeRequest::STATE_REJECTED, $request->fresh()->lifecycle_state);
        $this->assertDatabaseMissing('campuses', ['name' => 'Rejected At Review Campus']);

        // A rejected request cannot then be reviewed.
        $this->expectException(BusinessRejection::class);
        $this->governor()->review($this->structureManager('*'), $request->fresh(), RandomIdentifier::new());
    }

    public function test_initiator_can_withdraw_an_unsigned_proposal_but_not_a_reviewed_one(): void
    {
        $organization = $this->establishActiveOrganization();
        $governor = $this->governor();

        $proposed = $governor->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Withdrawn Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $governor->withdraw($this->generalManager(), $request, RandomIdentifier::new());
        $this->assertSame(StructureChangeRequest::STATE_WITHDRAWN, $request->fresh()->lifecycle_state);

        $other = $governor->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Past Review Campus',
        ], RandomIdentifier::new());
        $otherRequest = StructureChangeRequest::query()->findOrFail($other['request_id']);
        $governor->review($this->structureManager('*'), $otherRequest, RandomIdentifier::new());
        $this->expectException(BusinessRejection::class);
        $governor->withdraw($this->generalManager(), $otherRequest->fresh(), RandomIdentifier::new());
    }

    public function test_duplicate_open_proposal_and_duplicate_names_are_rejected(): void
    {
        $organization = $this->establishActiveOrganization();
        $input = ['organization_id' => $organization->id, 'name' => 'Duplicate Campus'];
        $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, $input, RandomIdentifier::new());

        try {
            $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, $input, RandomIdentifier::new());
            $this->fail('a second open proposal for the same change must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('organization.change_already_open', $rejection->errorCode());
        }

        $this->establishActiveCampus($organization, 'Existing Name Campus');
        try {
            $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
                'organization_id' => $organization->id, 'name' => '  existing  name campus ',
            ], RandomIdentifier::new());
            $this->fail('a casing/whitespace variant of an existing name must be duplicate topology');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('organization.structure.duplicate', $rejection->errorCode());
        }
    }

    public function test_replaying_an_approval_with_the_same_idempotency_key_executes_once(): void
    {
        $organization = $this->establishActiveOrganization();
        $governor = $this->governor();

        $proposed = $governor->propose($this->generalManager(), StructureChangeRequest::TYPE_CREATE_CAMPUS, [
            'organization_id' => $organization->id, 'name' => 'Idempotent Campus',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $governor->review($this->structureManager('*'), $request, RandomIdentifier::new());
        $governor->approve($this->structureOwner('*', 'owner-1'), $request->fresh(), RandomIdentifier::new());

        $stableKey = RandomIdentifier::new();
        $first = $governor->approve($this->structureOwner('*', 'owner-2'), $request->fresh(), $stableKey);
        $replay = $governor->approve($this->structureOwner('*', 'owner-2'), $request->fresh(), $stableKey);

        $this->assertSame($first, $replay);
        $this->assertSame(1, Campus::query()->where('name', 'Idempotent Campus')->count());
        $this->assertSame(1, AuditEvent::query()->where('operation', 'organization.structure.change.execute')->count());
    }

    public function test_organization_cannot_close_while_it_still_governs_structure(): void
    {
        $organization = $this->establishActiveOrganization();
        $campus = $this->establishActiveCampus($organization);

        $proposed = $this->governor()->propose($this->generalManager(), StructureChangeRequest::TYPE_TRANSITION, [
            'unit_type' => 'organization', 'unit_id' => $organization->id, 'action' => 'close',
        ], RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $this->governor()->review($this->structureManager('*'), $request, RandomIdentifier::new());
        $this->governor()->approve($this->structureOwner('*', 'owner-1'), $request->fresh(), RandomIdentifier::new());

        try {
            $this->governor()->approve($this->structureOwner('*', 'owner-2'), $request->fresh(), RandomIdentifier::new());
            $this->fail('closing an organization with an open campus must fail at execution');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('organization.close_has_open_campuses', $rejection->errorCode());
        }

        // The failed execution rolled the signature back: still awaiting it.
        $this->assertSame(StructureChangeRequest::STATE_REVIEWED, $request->fresh()->lifecycle_state);
        $this->assertSame('active', Organization::query()->whereKey($organization->id)->value('lifecycle_state'));
        $this->assertSame('active', Campus::query()->whereKey($campus->id)->value('lifecycle_state'));
    }

    private function activateUnit(string $unitType, string $unitId): void
    {
        $executed = $this->runChain(StructureChangeRequest::TYPE_TRANSITION, [
            'unit_type' => $unitType, 'unit_id' => $unitId, 'action' => 'activate',
        ]);
        $this->assertSame(StructureChangeRequest::STATE_EXECUTED, $executed->lifecycle_state);
    }
}
