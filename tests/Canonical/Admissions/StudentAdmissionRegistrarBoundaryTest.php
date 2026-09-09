<?php

declare(strict_types=1);

namespace Tests\Canonical\Admissions;

use App\Modules\Admissions\Commands\DecideAdmission;
use App\Modules\Admissions\Commands\RegisterApplicant;
use App\Modules\Admissions\Models\AdmissionDecision;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Students\Domain\StudentAdmissionRegistrar;
use App\Modules\Students\Models\Student;
use App\Support\Errors\BusinessRejection;
use Tests\Canonical\CanonicalTestCase;
use Tests\Concerns\DecidesAdmissions;

final class StudentAdmissionRegistrarBoundaryTest extends CanonicalTestCase
{
    use DecidesAdmissions;

    private string $personId = 'canon-student-boundary-person';

    protected function setUp(): void
    {
        parent::setUp();
        $this->canonicalPersonWithAuthority($this->personId, []);
    }

    private function applicant(): Applicant
    {
        $registered = app(RegisterApplicant::class)->register(
            $this->admissionsClerk('canon-student-boundary-reception'),
            $this->personId,
            'General English',
            'canon-student-boundary-register',
            null,
            $this->sharedBranchId(),
        );

        return Applicant::query()->findOrFail($registered['applicant_id']);
    }

    public function test_students_registrar_rejects_a_non_final_admission_decision(): void
    {
        $applicant = $this->applicant();
        $initiated = app(DecideAdmission::class)->initiate(
            $this->admissionsClerk('canon-student-boundary-init'),
            $applicant,
            true,
            'pending review',
            'evidence/boundary-1',
            'canon-student-boundary-init-key',
        );
        $decision = AdmissionDecision::query()->findOrFail($initiated['decision_id']);

        try {
            app(StudentAdmissionRegistrar::class)->register(
                $decision->id,
                $this->personId,
                'STU-BOUNDARY-1',
                $this->sharedBranchId(),
                null,
                null,
                'canon-student-boundary-actor',
            );
            $this->fail('the Students-owned registrar must not materialize a student from a proposed decision');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('students.admission_decision_not_final_admit', $rejection->errorCode());
        }

        $this->assertDatabaseCount('students', 0);
    }

    public function test_students_registrar_requires_the_approved_applicant_and_matching_person_and_branch(): void
    {
        $applicant = $this->applicant();
        $decision = $this->runAdmissionDecision(
            $this->admissionsClerk('canon-student-boundary-reception-2'),
            $this->admissionsReviewer('canon-student-boundary-review-2'),
            $this->admissionsApprover('canon-student-boundary-approve-2'),
            $applicant,
            true,
            'approved',
            'evidence/boundary-2',
            'canon-student-boundary-decision-2',
        );
        $decisionModel = AdmissionDecision::query()->findOrFail($decision['decision_id']);

        try {
            app(StudentAdmissionRegistrar::class)->register(
                $decisionModel->id,
                'different-person',
                'STU-BOUNDARY-2',
                $this->sharedBranchId(),
                null,
                null,
                'canon-student-boundary-actor-2',
            );
            $this->fail('the Students-owned registrar must enforce applicant/person lineage');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('students.admission_person_mismatch', $rejection->errorCode());
        }

        try {
            app(StudentAdmissionRegistrar::class)->register(
                $decisionModel->id,
                $this->personId,
                'STU-BOUNDARY-3',
                'ffffffff-ffff-4fff-8fff-ffffffffffff',
                null,
                null,
                'canon-student-boundary-actor-3',
            );
            $this->fail('the Students-owned registrar must enforce applicant branch lineage');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('students.admission_branch_mismatch', $rejection->errorCode());
        }

        $registered = app(StudentAdmissionRegistrar::class)->register(
            $decisionModel->id,
            $this->personId,
            'STU-BOUNDARY-4',
            $this->sharedBranchId(),
            null,
            null,
            'canon-student-boundary-actor-4',
        );

        $this->assertSame('STU-BOUNDARY-4', $registered['student_code']);
        $this->assertInstanceOf(Student::class, $registered['student']);
        $this->assertDatabaseHas('students', [
            'id' => $registered['student']->id,
            'admission_decision_id' => $decisionModel->id,
            'person_id' => $this->personId,
            'originating_branch_id' => $this->sharedBranchId(),
            'current_home_branch_id' => $this->sharedBranchId(),
        ]);
        $this->assertDatabaseHas('student_statuses', [
            'student_id' => $registered['student']->id,
            'status' => 'active',
        ]);
    }
}
