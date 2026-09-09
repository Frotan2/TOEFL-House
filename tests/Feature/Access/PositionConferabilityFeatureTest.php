<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\AccessResolution;
use App\Modules\Access\Commands\AssignPosition;
use App\Modules\Access\Commands\DefineAccessPolicy;
use App\Modules\Access\Commands\TransitionPositionAssignment;
use App\Modules\Access\Models\Position;
use App\Modules\Access\Models\PositionAssignment;
use App\Modules\Access\Models\Role;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureScope;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\OperatesStructure;
use Tests\TestCase;

/**
 * Privilege-escalation and stale-authority coverage for position-based access.
 * Assignment and activation must never confer capabilities the acting person
 * could not otherwise exercise, except for an explicitly authorized access
 * policy administrator.
 */
final class PositionConferabilityFeatureTest extends TestCase
{
    use BuildsActors;
    use OperatesStructure;

    public function test_limited_assigner_cannot_assign_a_position_that_confers_unheld_capability(): void
    {
        $policyAdmin = $this->accessAdministrator('conf-policy-admin-1');
        $organizationId = $this->bootstrapOrganizationId;
        $this->personWithAuthority('conf-target-1', []);
        $this->personWithAuthority('conf-limited-1', ['access.assign_position']);
        $limitedAssigner = new Actor('conf-limited-1', 'Limited Assigner');

        $role = Role::query()->create(['id' => RandomIdentifier::new(), 'name' => 'Identity Administrator Role']);
        $position = Position::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $organizationId,
            'name' => 'Identity Administrator Position',
        ]);
        app(DefineAccessPolicy::class)->bindPositionRole($policyAdmin, $position->id, $role->id, new CarbonImmutable('2026-08-25'), 'conf-policy-1');
        app(DefineAccessPolicy::class)->grantRolePermission($policyAdmin, $role->id, 'identity.admin', new CarbonImmutable('2026-08-25'), 'conf-policy-2');

        $this->expectException(AuthorizationDenied::class);
        $this->expectExceptionMessage('target position confers capability identity.admin outside the actor\'s authority');
        app(AssignPosition::class)->assign($limitedAssigner, 'conf-target-1', $position->id, new CarbonImmutable('2026-08-25'), 'conf-assign-1');

        $this->assertDatabaseMissing('position_assignments', ['person_id' => 'conf-target-1', 'position_id' => $position->id]);
    }

    public function test_limited_assigner_cannot_activate_a_powerful_position_proposed_by_another_actor(): void
    {
        $policyAdmin = $this->accessAdministrator('conf-policy-admin-2');
        $organizationId = $this->bootstrapOrganizationId;
        $this->personWithAuthority('conf-target-2', []);
        $this->personWithAuthority('conf-limited-2', ['access.assign_position']);
        $limitedAssigner = new Actor('conf-limited-2', 'Limited Assigner');

        $role = Role::query()->create(['id' => RandomIdentifier::new(), 'name' => 'Finance Administrator Role']);
        $position = Position::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $organizationId,
            'name' => 'Finance Administrator Position',
        ]);
        app(DefineAccessPolicy::class)->bindPositionRole($policyAdmin, $position->id, $role->id, new CarbonImmutable('2026-08-25'), 'conf-policy-3');
        app(DefineAccessPolicy::class)->grantRolePermission($policyAdmin, $role->id, 'finance.manage', new CarbonImmutable('2026-08-25'), 'conf-policy-4');

        $created = app(AssignPosition::class)->assign($policyAdmin, 'conf-target-2', $position->id, new CarbonImmutable('2026-08-25'), 'conf-assign-2');
        /** @var PositionAssignment $assignment */
        $assignment = PositionAssignment::query()->findOrFail($created['assignment_id']);

        try {
            app(TransitionPositionAssignment::class)->activate($limitedAssigner, $assignment, 'conf-activate-1');
            $this->fail('a limited assigner must not activate a position that would confer an unheld capability');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('access.position_conferability_denied', $denial->errorCode());
        }

        $this->assertDatabaseHas('position_assignments', ['id' => $assignment->id, 'lifecycle_state' => 'proposed']);
        $this->assertFalse((new AccessResolution)->decide(new Actor('conf-target-2', 'Target'), 'finance.manage', new StructureScope($organizationId))->allowed);
    }

    public function test_access_policy_administrator_retains_explicit_power_to_confer_any_position(): void
    {
        $policyAdmin = $this->accessAdministrator('conf-policy-admin-3');
        $organizationId = $this->bootstrapOrganizationId;
        $this->personWithAuthority('conf-target-3', []);

        $role = Role::query()->create(['id' => RandomIdentifier::new(), 'name' => 'Owner-Level Role']);
        $position = Position::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $organizationId,
            'name' => 'Owner-Level Position',
        ]);
        app(DefineAccessPolicy::class)->bindPositionRole($policyAdmin, $position->id, $role->id, new CarbonImmutable('2026-08-25'), 'conf-policy-5');
        app(DefineAccessPolicy::class)->grantRolePermission($policyAdmin, $role->id, 'organization.structure.approve', new CarbonImmutable('2026-08-25'), 'conf-policy-6');

        $result = app(AssignPosition::class)->assign($policyAdmin, 'conf-target-3', $position->id, new CarbonImmutable('2026-08-25'), 'conf-assign-3');
        $this->assertDatabaseHas('position_assignments', ['id' => $result['assignment_id'], 'lifecycle_state' => 'proposed']);
    }

    public function test_activation_rechecks_current_position_policy_after_a_new_capability_is_published(): void
    {
        $policyAdmin = $this->accessAdministrator('conf-policy-admin-4');
        $organizationId = $this->bootstrapOrganizationId;
        $this->personWithAuthority('conf-target-4', []);
        $this->personWithAuthority('conf-limited-4', ['access.assign_position', 'finance.manage']);
        $limitedAssigner = new Actor('conf-limited-4', 'Limited Assigner');

        $role = Role::query()->create(['id' => RandomIdentifier::new(), 'name' => 'Evolving Role']);
        $position = Position::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $organizationId,
            'name' => 'Evolving Position',
        ]);
        app(DefineAccessPolicy::class)->bindPositionRole($policyAdmin, $position->id, $role->id, new CarbonImmutable('2026-08-25'), 'conf-policy-7');
        app(DefineAccessPolicy::class)->grantRolePermission($policyAdmin, $role->id, 'finance.manage', new CarbonImmutable('2026-08-25'), 'conf-policy-8');

        $created = app(AssignPosition::class)->assign($limitedAssigner, 'conf-target-4', $position->id, new CarbonImmutable('2026-08-25'), 'conf-assign-4');
        $assignment = PositionAssignment::query()->findOrFail($created['assignment_id']);

        // The proposal was valid when created. A later policy publication must
        // be observed at activation rather than trusting stale proposal state.
        app(DefineAccessPolicy::class)->grantRolePermission($policyAdmin, $role->id, 'identity.admin', new CarbonImmutable('2026-09-01'), 'conf-policy-9');

        try {
            app(TransitionPositionAssignment::class)->activate($limitedAssigner, $assignment, 'conf-activate-2');
            $this->fail('activation must re-check the current effective position policy');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('access.position_conferability_denied', $denial->errorCode());
        }

        $this->assertDatabaseHas('position_assignments', ['id' => $assignment->id, 'lifecycle_state' => 'proposed']);
    }
}
