<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Hr\Domain\EmploymentLifecycle;
use App\Modules\Hr\Models\Employment;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Department;
use App\Modules\Students\Models\Student;
use App\Modules\Students\Models\StudentStatus;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\BuildsTeachers;
use Tests\Concerns\OperatesStructure;
use Tests\TestCase;

/**
 * Closure is bottom-up and must never strand active structure or active
 * operational anchors (students, teachers, employment). Suspension is a
 * reversible fail-closed freeze and is not gated by the same rule.
 */
final class StructureRetirementGuardFeatureTest extends TestCase
{
    use BuildsActors;
    use BuildsStudents;
    use BuildsTeachers;
    use OperatesStructure;

    public function test_close_is_enforced_bottom_up_then_succeeds(): void
    {
        $organization = $this->establishActiveOrganization();
        $campus = $this->establishActiveCampus($organization);
        $branch = $this->establishActiveBranch($campus);

        $createdDepartment = $this->createCommand()->createDepartment(
            $this->structureDecisionForGlobalActors(),
            'branch',
            $branch->id,
            'Guard Department',
            RandomIdentifier::new(),
        );
        /** @var Department $department */
        $department = Department::query()->findOrFail($createdDepartment['id']);
        $this->transitionCommand()->activate($department, $this->structureDecisionForGlobalActors(), RandomIdentifier::new());

        $closeOrganization = fn () => $this->transitionCommand()->close($organization->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $closeCampus = fn () => $this->transitionCommand()->close($campus->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $closeBranch = fn () => $this->transitionCommand()->close($branch->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $closeDepartment = fn () => $this->transitionCommand()->close($department->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());

        $this->expectRejection('organization.close_has_open_campuses', $closeOrganization);
        $this->expectRejection('organization.close_has_open_branches', $closeCampus);
        $this->expectRejection('organization.close_has_open_departments', $closeBranch);

        // Leaf-first retirement: once each unit closes, its parent may close.
        $closeDepartment();
        $this->assertSame('closed', $department->fresh()->lifecycle_state);
        $closeBranch();
        $closeCampus();
        $closeOrganization();
        $this->assertSame('closed', $branch->fresh()->lifecycle_state);
        $this->assertSame('closed', $campus->fresh()->lifecycle_state);
        $this->assertSame('closed', $organization->fresh()->lifecycle_state);
    }

    public function test_suspension_is_allowed_with_children_and_reverses(): void
    {
        $organization = $this->establishActiveOrganization();
        $campus = $this->establishActiveCampus($organization);
        $this->establishActiveBranch($campus);

        $this->transitionCommand()->suspend($organization->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $this->assertSame('suspended', $organization->fresh()->lifecycle_state);

        $this->transitionCommand()->activate($organization->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $this->assertSame('active', $organization->fresh()->lifecycle_state);
    }

    public function test_branch_close_is_blocked_by_active_employment(): void
    {
        $organization = $this->establishActiveOrganization();
        $campus = $this->establishActiveCampus($organization);
        $branch = $this->establishActiveBranch($campus, 'Employment Branch');

        $person = $this->personHomedAt($branch->id, 'employed-person');
        // Rows are born 'candidate' (employments_lifecycle_guard); candidate ->
        // active is the permitted production transition.
        $employment = Employment::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'lifecycle_state' => EmploymentLifecycle::STATE_CANDIDATE,
        ]);
        $employment->forceFill(['lifecycle_state' => EmploymentLifecycle::STATE_ACTIVE])->save();

        $closeBranch = fn () => $this->transitionCommand()->close($branch->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $this->expectRejection('organization.close_has_active_employment', $closeBranch);

        $employment->forceFill(['lifecycle_state' => EmploymentLifecycle::STATE_TERMINATED])->save();
        // No active employment anchor anymore; the branch now closes (no other anchors).
        $closeBranch();
        $this->assertSame('closed', $branch->fresh()->lifecycle_state);
    }

    public function test_branch_close_is_blocked_by_an_active_teacher(): void
    {
        // Pin the canonical Kabul business clock to an instant whose Kabul
        // civil day equals the database's CURRENT_DATE (noon UTC). The
        // teacher onboarding commands stamp dates through CalendarAuthority
        // (Kabul) while PL/pgSQL guards compare against CURRENT_DATE; without
        // a pin, the 19:30–00:00 UTC window (Kabul already on tomorrow) is a
        // pre-existing calendar-boundary gap, not an Organization-domain
        // fact. Production commands are used unchanged below.
        $this->pinBusinessClockToUtcToday();

        // The canonical teacher fixture runs every production onboarding
        // command, producing a verified person, active employment and an
        // active profile homed in the bootstrap operational branch.
        $teacher = $this->buildActiveTeacher('teacher-anchor-1');
        $bootstrapBranch = Branch::query()->whereKey($this->bootstrapBranchId())->firstOrFail();

        $closeBootstrapBranch = fn () => $this->transitionCommand()->close($bootstrapBranch->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $this->expectRejection('organization.close_has_active_teachers', $closeBootstrapBranch);

        // Retire the teaching authority, then terminate the underlying
        // employment: both operational anchors clear before closure.
        TeacherProfile::query()->whereKey($teacher['teacher_profile_id'])->update(['lifecycle_state' => 'retired']);
        $this->expectRejection('organization.close_has_active_employment', $closeBootstrapBranch);
        Employment::query()->whereKey($teacher['employment_id'])->update(['lifecycle_state' => EmploymentLifecycle::STATE_TERMINATED]);

        $closeBootstrapBranch();
        $this->assertSame('closed', $bootstrapBranch->fresh()->lifecycle_state);
    }

    public function test_branch_close_is_blocked_by_an_active_student(): void
    {
        // Canonical admission produces an active student homed at the
        // bootstrap operational branch.
        $this->makeStudent();
        $bootstrapBranch = Branch::query()->whereKey($this->bootstrapBranchId())->firstOrFail();

        $closeBootstrapBranch = fn () => $this->transitionCommand()->close($bootstrapBranch->fresh(), $this->structureDecisionForGlobalActors(), RandomIdentifier::new());
        $this->expectRejection('organization.close_has_active_students', $closeBootstrapBranch);

        // After the student withdraws (the latest status fact wins), the
        // anchor no longer blocks closure.
        $student = Student::query()->where('current_home_branch_id', $bootstrapBranch->id)->firstOrFail();
        StudentStatus::query()->create([
            'id' => RandomIdentifier::new(),
            'student_id' => $student->id,
            'status' => 'withdrawn',
            'effective_from' => now()->toDateString(),
            'reason' => 'guard test withdrawal',
            'actor_id' => 'stu-mgr-1',
        ]);

        $closeBootstrapBranch();
        $this->assertSame('closed', $bootstrapBranch->fresh()->lifecycle_state);
    }

    /**
     * Binds CalendarAuthority to noon UTC of the current UTC day so the
     * Kabul business date used by the commands matches CURRENT_DATE in the
     * test database during every clock window.
     */
    private function pinBusinessClockToUtcToday(): void
    {
        $this->app->instance(CalendarAuthority::class, new CalendarAuthority(
            null,
            static fn (): CarbonImmutable => CarbonImmutable::today('UTC')->addHours(12),
        ));
    }

    private function personHomedAt(string $branchId, string $identifier): Person
    {
        return Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => 'Anchor '.$identifier,
            'date_of_birth' => '1985-05-05',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'fixture-'.$identifier.'-'.RandomIdentifier::new(),
            'identity_evidence_ref' => 'evidence/fixture/'.$identifier,
            'verified_by' => 'fixture-verifier',
            'verified_at' => now()->toDateTimeString(),
            'home_branch_id' => $branchId,
        ]);
    }

    private function expectRejection(string $code, callable $operation): void
    {
        try {
            $operation();
            $this->fail(sprintf('expected business rejection %s', $code));
        } catch (BusinessRejection $rejection) {
            $this->assertSame($code, $rejection->errorCode());
        }
    }
}
