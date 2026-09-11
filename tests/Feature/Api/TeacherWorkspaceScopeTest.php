<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsTeachers;
use Tests\TestCase;

/**
 * Teacher workspace is a read projection, not an authority boundary. Every
 * branch-scoped child fact must be filtered by the viewer's server-derived
 * scope, while global qualification evidence remains visible to the scoped
 * teacher manager. This pins API/React parity to the canonical transport.
 */
final class TeacherWorkspaceScopeTest extends TestCase
{
    use BuildsActors;
    use BuildsTeachers;

    private string $branchA;

    private string $branchB;

    private function branch(string $name): string
    {
        $id = Branch::query()->create([
            'id' => RandomIdentifier::new(),
            'name' => $name.' '.substr(md5(RandomIdentifier::new()), 0, 8),
            'lifecycle_state' => 'active',
        ])->id;
        $this->attachBranchToBootstrapOrganization($id);

        return $id;
    }

    private function login(string $personId, string $username): void
    {
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => $username,
            'password_hash' => Hash::make('teacher-workspace-pw-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        $this->post('/login', ['username' => $username, 'password' => 'teacher-workspace-pw-1'])->assertRedirect('/');
    }

    public function test_branch_scoped_teacher_workspace_excludes_foreign_child_facts(): void
    {
        $this->branchA = $this->branch('Teacher Workspace A');
        $this->branchB = $this->branch('Teacher Workspace B');

        $teacher = $this->buildActiveTeacher('workspace-teacher-person', $this->branchA, 'workspace');
        $profile = TeacherProfile::query()->findOrFail($teacher['teacher_profile_id']);
        $profiles = app(MaintainTeacherProfile::class);
        $from = CarbonImmutable::today()->subDay()->toDateString();

        // Workload limits are approval facts. The workspace reader also needs
        // teacher_manage, but each fixture writer must hold the separate,
        // branch-scoped teacher_approve capability used by setWorkloadLimit().
        $managerAId = 'workspace-manager-a';
        // Start without a role-derived organization-wide policy. The named
        // ScopeGrant below is the complete authority under test, rather than a
        // second, broader source that would make every branch appear visible.
        $managerA = $this->grantedActor($managerAId, []);
        $this->grantScopeAuthority($managerAId, ['academic.teacher_manage', 'academic.teacher_approve'], 'branch', $this->branchA);

        $managerBId = 'workspace-manager-b';
        $managerB = $this->grantedActor($managerBId, []);
        $this->grantScopeAuthority($managerBId, ['academic.teacher_manage', 'academic.teacher_approve'], 'branch', $this->branchB);

        $approverId = 'workspace-approver';
        $approver = $this->grantedActor($approverId, []);
        $this->grantScopeAuthority($approverId, ['academic.teacher_approve'], 'branch', $this->branchB);

        // The same teacher legitimately has branch-B authority, but the
        // branch-A manager must not receive branch-B operational evidence.
        $profiles->authorizeBranch(
            $approver,
            $profile,
            $this->branchB,
            $from,
            null,
            'workspace scope regression',
            // BuildsTeachers reserves the `workspace-xbranch` idempotency key
            // while establishing the initial teacher fixture. This is a later,
            // distinct branch-authorization command, so it needs its own key.
            'workspace-branch-b-authorization',
        );
        $profiles->declareAvailability(
            $managerA,
            $profile,
            $this->branchA,
            1,
            '09:00',
            '10:00',
            $from,
            null,
            'available',
            'workspace-avail-a',
        );
        $profiles->declareAvailability(
            $managerB,
            $profile,
            $this->branchB,
            2,
            '11:00',
            '12:00',
            $from,
            null,
            'available',
            'workspace-avail-b',
        );
        $profiles->setWorkloadLimit(
            $managerA,
            $profile,
            $this->branchA,
            '10.00',
            $from,
            null,
            'evidence/workspace/A',
            'workspace-load-a',
        );
        $profiles->setWorkloadLimit(
            $managerB,
            $profile,
            $this->branchB,
            '20.00',
            $from,
            null,
            'evidence/workspace/B',
            'workspace-load-b',
        );

        $this->login($managerAId, 'teacher.workspace.manager');

        $payload = $this->getJson('/api/v1/teachers/workspace')->assertOk()->json('data');
        $rows = $payload['profiles'] ?? [];
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame($teacher['teacher_profile_id'], $row['id']);
        $this->assertNotEmpty($row['qualifications'], 'global qualification evidence remains part of the teacher record');

        $this->assertSame([$this->branchA], array_values(array_unique(array_map(
            static fn (array $item): string => $item['branch_id'],
            $row['branch_authorizations'],
        ))));
        $this->assertSame([$this->branchA], array_values(array_unique(array_map(
            static fn (array $item): string => $item['branch_id'],
            $row['availability'],
        ))));
        $this->assertSame([$this->branchA], array_values(array_unique(array_map(
            static fn (array $item): string => $item['branch_id'],
            $row['workload_limits'],
        ))));
    }
}
