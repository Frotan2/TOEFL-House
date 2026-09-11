<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Hr\Models\Employment;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\CampusAssignment;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Modules\Students\Domain\StudentStatusRegistry;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Closing topology is bottom-up: a unit may close only once everything it
 * governs has already closed. Suspension (a reversible, fail-closed freeze)
 * is not gated; closure that strands active structure or active operations
 * is rejected. Historical ledger, CRM and academic records are immutable and
 * keep their foreign keys regardless — they never block closure, while
 * CURRENT operational anchors (active students, active teachers, active
 * employment homed in a branch) must be transferred or retired first.
 *
 * No structure row is ever deleted; closure is the terminal, auditable act.
 */
final class StructureRetirementGuard
{
    public function requireCloseAllowed(StructureUnit&Model $unit): void
    {
        match (true) {
            $unit instanceof Organization => $this->requireOrganizationClosable($unit),
            $unit instanceof Campus => $this->requireCampusClosable($unit),
            $unit instanceof Branch => $this->requireBranchClosable($unit),
            $unit instanceof Department => null,
            default => null,
        };
    }

    private function requireOrganizationClosable(Organization $organization): void
    {
        $openCampuses = Campus::query()
            ->where('organization_id', $organization->id)
            ->where('lifecycle_state', '!=', OrganizationLifecycle::STATE_CLOSED)
            ->count();
        if ($openCampuses > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_open_campuses',
                sprintf('close or remove %d campus(es) before closing the organization', $openCampuses),
            );
        }

        $openDepartments = Department::query()
            ->where('scope_type', 'organization')
            ->where('scope_id', $organization->id)
            ->where('lifecycle_state', '!=', OrganizationLifecycle::STATE_CLOSED)
            ->count();
        if ($openDepartments > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_open_departments',
                sprintf('close %d organization-scoped department(s) before closing the organization', $openDepartments),
            );
        }
    }

    private function requireCampusClosable(Campus $campus): void
    {
        $openBranchIds = CampusAssignment::query()
            ->where('campus_assignments.campus_id', $campus->id)
            ->whereNull('campus_assignments.effective_to')
            ->join('branches', 'branches.id', '=', 'campus_assignments.branch_id')
            ->where('branches.lifecycle_state', '!=', OrganizationLifecycle::STATE_CLOSED)
            ->count();
        if ($openBranchIds > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_open_branches',
                sprintf('close %d branch(es) attributed to this campus before closing it', $openBranchIds),
            );
        }

        $openDepartments = Department::query()
            ->where('scope_type', 'campus')
            ->where('scope_id', $campus->id)
            ->where('lifecycle_state', '!=', OrganizationLifecycle::STATE_CLOSED)
            ->count();
        if ($openDepartments > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_open_departments',
                sprintf('close %d campus-scoped department(s) before closing the campus', $openDepartments),
            );
        }
    }

    private function requireBranchClosable(Branch $branch): void
    {
        $openDepartments = Department::query()
            ->where('scope_type', 'branch')
            ->where('scope_id', $branch->id)
            ->where('lifecycle_state', '!=', OrganizationLifecycle::STATE_CLOSED)
            ->count();
        if ($openDepartments > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_open_departments',
                sprintf('close %d department(s) before closing the branch', $openDepartments),
            );
        }

        // A student's current state is their latest student_statuses fact
        // (the students row itself carries no lifecycle column).
        $activeStudents = DB::table('students')
            ->join('student_statuses as latest', function ($join): void {
                $join->on('latest.student_id', '=', 'students.id')
                    ->whereRaw('latest.id = (select ss.id from student_statuses ss where ss.student_id = students.id order by ss.seq desc limit 1)');
            })
            ->where('students.current_home_branch_id', $branch->id)
            ->where('latest.status', StudentStatusRegistry::STATUS_ACTIVE)
            ->count();
        if ($activeStudents > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_active_students',
                sprintf('transfer or withdraw %d active student(s) before closing the branch', $activeStudents),
            );
        }

        $activeTeachers = TeacherProfile::query()
            ->where('current_home_branch_id', $branch->id)
            ->where('lifecycle_state', 'active')
            ->count();
        if ($activeTeachers > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_active_teachers',
                sprintf('transfer or retire %d active teacher profile(s) before closing the branch', $activeTeachers),
            );
        }

        $activeEmployments = Employment::query()
            ->join('people', 'people.id', '=', 'employments.person_id')
            ->where('people.home_branch_id', $branch->id)
            ->where('employments.lifecycle_state', 'active')
            ->count();
        if ($activeEmployments > 0) {
            throw BusinessRejection::forCode(
                'organization.close_has_active_employment',
                sprintf('end or transfer %d active employment record(s) before closing the branch', $activeEmployments),
            );
        }
    }
}
