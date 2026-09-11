<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Commands\ManageAssessmentResult;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\AssessmentAttempt;
use App\Modules\Academic\Models\AssessmentResult;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\Enrollment;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Placement\Models\PlacementProfile;
use App\Modules\Admissions\Commands\EnrollAdmittedApplicant;
use App\Modules\Admissions\Commands\RegisterApplicant;
use App\Modules\Admissions\Models\Applicant;
use App\Support\Errors\AuthorizationDenied;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsPlacementCatalog;
use Tests\Concerns\BuildsTeachers;
use Tests\Concerns\DecidesAdmissions;
use Tests\TestCase;

/**
 * Terminal release is a distinct authority step. The application model guard
 * protects canonical Eloquent writes; the database trigger protects raw SQL
 * and alternate writers. Both Placement and ordinary Assessment must preserve
 * scorer -> moderator/reviewer -> approver -> independent releaser separation.
 */
final class ReleaseSignoffIndependenceFeatureTest extends TestCase
{
    use BuildsPlacementCatalog;
    use BuildsTeachers;
    use DecidesAdmissions;

    private string $classId;

    private string $studentId;

    private string $enrollmentId;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_placement_model_and_database_reject_release_by_approver_or_reviewer(): void
    {
        $this->setUpPlacementCatalog();
        $person = $this->personWithAuthority('placement-release-sod-person', []);
        /** @var PlacementProfile $approved */
        $approved = $this->completeApprovedPlacement($person->id, 'placement-release-sod');

        $sameApprover = (string) $approved->approved_by;
        $sameReviewer = (string) $approved->reviewed_by;
        $this->assertNotSame('', $sameApprover);
        $this->assertNotSame('', $sameReviewer);

        $model = $approved->fresh();
        if ($model === null) {
            $this->fail('approved placement profile disappeared');
        }
        $model->released_by = $sameApprover;
        $model->lifecycle_state = PlacementProfile::STATE_RELEASED;
        try {
            $model->save();
            $this->fail('the canonical Placement model must reject an approver as releaser');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('placement.release_not_independent', $denial->errorCode());
        }

        $this->assertSqlRejected(
            fn (): int => DB::table('placement_profiles')->where('id', $approved->id)->update([
                'lifecycle_state' => PlacementProfile::STATE_RELEASED,
                'released_by' => $sameApprover,
                'updated_at' => now(),
            ]),
            'placement release signer must differ from approver and reviewer',
        );
        $this->assertSame(PlacementProfile::STATE_APPROVED, (string) PlacementProfile::query()->findOrFail($approved->id)->lifecycle_state);

        $this->assertSqlRejected(
            fn (): int => DB::table('placement_profiles')->where('id', $approved->id)->update([
                'lifecycle_state' => PlacementProfile::STATE_RELEASED,
                'released_by' => $sameReviewer,
                'updated_at' => now(),
            ]),
            'placement release signer must differ from approver and reviewer',
        );
        $this->assertSame(PlacementProfile::STATE_APPROVED, (string) PlacementProfile::query()->findOrFail($approved->id)->lifecycle_state);
    }

    public function test_assessment_model_and_database_reject_release_by_approver_or_moderator(): void
    {
        $this->setUpAssessmentFixture();

        $scorer = $this->grantedActor('release-sod-scorer', ['academic.assess']);
        $moderator = $this->grantedActor('release-sod-moderator', ['academic.moderate']);
        $approver = $this->grantedActor('release-sod-approver', ['academic.approve_result', 'academic.release']);

        $attempt = app(ManageAssessmentResult::class)->submitAttempt(
            $scorer,
            Enrollment::query()->findOrFail($this->enrollmentId),
            'assessment',
            'evidence/release-sod',
            'release-sod-attempt',
        );
        $result = app(ManageAssessmentResult::class)->score(
            $scorer,
            AssessmentAttempt::query()->findOrFail($attempt['attempt_id']),
            '88.00',
            'release-sod-score',
        );
        /** @var AssessmentResult $row */
        $row = AssessmentResult::query()->findOrFail($result['result_id']);
        app(ManageAssessmentResult::class)->moderate($moderator, $row, 'release-sod-moderate');
        app(ManageAssessmentResult::class)->approve($approver, $row->fresh() ?? $row, 'release-sod-approve');

        $approved = AssessmentResult::query()->findOrFail($row->id);
        $this->assertSame('approved', $approved->lifecycle_state);

        // The same actor has the release capability, but the model-level
        // invariant still refuses reuse of the approval authority.
        $approved->released_by = $approver->actorId;
        $approved->lifecycle_state = 'released';
        try {
            $approved->save();
            $this->fail('the canonical AssessmentResult model must reject an approver as releaser');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.release_not_independent', $denial->errorCode());
        }

        // Raw SQL is independently refused by the database trigger.
        $this->assertSqlRejected(
            fn (): int => DB::table('assessment_results')->where('id', $row->id)->update([
                'lifecycle_state' => 'released',
                'released_by' => $approver->actorId,
                'updated_at' => now(),
            ]),
            'assessment release signer must differ from approver and moderator',
        );

        $this->assertSqlRejected(
            fn (): int => DB::table('assessment_results')->where('id', $row->id)->update([
                'lifecycle_state' => 'released',
                'released_by' => $moderator->actorId,
                'updated_at' => now(),
            ]),
            'assessment release signer must differ from approver and moderator',
        );
        $this->assertSame('approved', (string) AssessmentResult::query()->findOrFail($row->id)->lifecycle_state);

        // The independent releaser remains valid, proving the guard is narrow
        // and does not weaken the existing release authority.
        app(ManageAssessmentResult::class)->release($this->grantedActor('release-sod-releaser', ['academic.release']), $row->fresh() ?? $row, 'release-sod-release');
        $this->assertSame('released', (string) AssessmentResult::query()->findOrFail($row->id)->lifecycle_state);
    }

    private function setUpAssessmentFixture(): void
    {
        $officer = $this->academicOfficer('release-sod-academic');
        $program = app(MaintainAcademicStructure::class)->defineProgram($officer, 'Release SOD Program', 'release-sod-program');
        $version = app(MaintainAcademicStructure::class)->publishVersion(
            $officer,
            Program::query()->findOrFail($program['program_id']),
            'v1',
            'release-sod-version',
        );
        $period = app(MaintainAcademicStructure::class)->definePeriod(
            $officer,
            'Release SOD Term',
            new CarbonImmutable('2026-09-01'),
            new CarbonImmutable('2026-12-18'),
            'release-sod-period',
        );
        app(MaintainAcademicStructure::class)->transitionPeriod(
            $officer,
            AcademicPeriod::query()->findOrFail($period['period_id']),
            'published',
            'release-sod-period-publish',
        );

        $level = app(MaintainAcademicStructure::class)->defineLevel(
            $officer,
            $version['version_id'],
            'release-sod-level',
            1,
            'Release SOD Level',
            'A1',
            'release-sod-level',
        );
        app(MaintainAcademicStructure::class)->declareBranchAvailability(
            $officer,
            $this->bootstrapBranchId(),
            $level['level_id'],
            $period['period_id'],
            'release-sod-availability',
        );
        app(MaintainAcademicStructure::class)->openOffering(
            $officer,
            $this->bootstrapBranchId(),
            $level['level_id'],
            $period['period_id'],
            20,
            'release-sod-offering',
        );

        $class = app(MaintainClass::class)->defineClass(
            $officer,
            $version['version_id'],
            $period['period_id'],
            5,
            'release-sod-class',
            null,
            $this->bootstrapBranchId(),
        );
        $this->classId = $class['class_id'];
        $this->buildActiveTeacher('release-sod-teacher', null, 'release-sod-teacher');
        app(MaintainClass::class)->assignTeacher(
            $officer,
            ClassModel::query()->findOrFail($this->classId),
            'release-sod-teacher',
            new CarbonImmutable('2026-09-01'),
            null,
            'release-sod-class-teacher',
        );
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->classId), 'published', 'release-sod-class-published');
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->classId), 'active', 'release-sod-class-active');

        $person = $this->personWithAuthority('release-sod-student-person', []);
        $registered = app(RegisterApplicant::class)->register(
            $this->admissionsClerk('release-sod-clerk'),
            $person->id,
            'Release SOD Program',
            'release-sod-applicant',
            null,
            $this->bootstrapBranchId(),
        );
        /** @var Applicant $applicant */
        $applicant = Applicant::query()->findOrFail($registered['applicant_id']);
        $this->runAdmissionDecision(
            $this->admissionsClerk('release-sod-clerk-2'),
            $this->admissionsReviewer('release-sod-admissions-review'),
            $this->admissionsApprover('release-sod-admissions-approve'),
            $applicant,
            true,
            'meets policy',
            'evidence/release-sod-admission',
            'release-sod-admission',
        );
        $this->studentId = app(EnrollAdmittedApplicant::class)->convert(
            $this->admissionsApprover('release-sod-admissions-approve-2'),
            $applicant,
            'release-sod-convert',
        )['student_id'];

        $seat = app(MaintainEnrollment::class)->request(
            $this->enrollmentClerk('release-sod-enroll'),
            $this->studentId,
            $this->classId,
            'release-sod-enrollment-request',
        );
        app(MaintainEnrollment::class)->activate(
            $officer,
            Enrollment::query()->findOrFail($seat['enrollment_id']),
            'release-sod-enrollment-active',
        );
        $this->enrollmentId = $seat['enrollment_id'];
    }

    private function assertSqlRejected(callable $operation, string $expectedMessage): void
    {
        try {
            // A PostgreSQL constraint violation aborts its transaction. The
            // test case itself runs in a transaction, so contain the expected
            // rejection in a nested transaction/savepoint before inspecting
            // the untouched fixture below.
            DB::transaction(function () use ($operation): void {
                $operation();
            });
            $this->fail('raw SQL unexpectedly bypassed the release signoff boundary');
        } catch (QueryException $exception) {
            $this->assertStringContainsString($expectedMessage, $exception->getMessage());
        }
    }
}
