<?php

declare(strict_types=1);

namespace App\Modules\Students\Domain;

use App\Modules\Admissions\Models\AdmissionDecision;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Students\Models\Student;
use App\Modules\Students\Models\StudentStatus;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;

/**
 * Student-owned admission conversion port. Admissions may authorize and
 * orchestrate the conversion, but Student Lifecycle remains the only module
 * that writes the student aggregate and its initial status history.
 *
 * The port also defends its own canonical boundary: a caller cannot create a
 * Student from a proposed/rejected/non-admit decision or a non-admitted
 * applicant merely because the foreign-key relationship happens to exist.
 */
final class StudentAdmissionRegistrar
{
    /**
     * @return array{student: Student, student_code: string}
     */
    public function register(
        string $admissionDecisionId,
        string $personId,
        string $studentCode,
        string $originatingBranchId,
        ?string $placementProfileId,
        ?string $eligibilitySnapshotId,
        string $actorId,
    ): array {
        $admissionDecisionId = trim($admissionDecisionId);
        $personId = trim($personId);
        $studentCode = trim($studentCode);
        $originatingBranchId = trim($originatingBranchId);
        $placementProfileId = $placementProfileId === null ? null : trim($placementProfileId);
        $eligibilitySnapshotId = $eligibilitySnapshotId === null ? null : trim($eligibilitySnapshotId);
        $actorId = trim($actorId);
        if ($admissionDecisionId === '' || $personId === '' || $studentCode === '' || $originatingBranchId === '' || $actorId === '') {
            throw BusinessRejection::forCode('students.admission_conversion_shape', 'student conversion requires decision, person, code, branch, and actor provenance');
        }

        /** @var AdmissionDecision $decision */
        $decision = AdmissionDecision::query()->whereKey($admissionDecisionId)->lockForUpdate()->first();
        if ($decision === null) {
            throw BusinessRejection::forCode('students.admission_decision_missing', 'student conversion requires an existing admission decision');
        }
        if ($decision->lifecycle_state !== 'final' || $decision->outcome !== 'admit') {
            throw BusinessRejection::forCode('students.admission_decision_not_final_admit', 'student conversion requires a final admission decision with an admit outcome');
        }

        /** @var Applicant|null $applicant */
        $applicant = Applicant::query()->whereKey($decision->applicant_id)->lockForUpdate()->first();
        if ($applicant === null) {
            throw BusinessRejection::forCode('students.admission_applicant_missing', 'student conversion requires the admission applicant to exist');
        }
        if (trim((string) $applicant->person_id) !== $personId) {
            throw BusinessRejection::forCode('students.admission_person_mismatch', 'student conversion person must match the approved applicant');
        }
        if ($applicant->lifecycle_state !== 'admitted') {
            throw BusinessRejection::forCode('students.admission_applicant_not_admitted', 'student conversion requires the applicant to be in admitted state');
        }

        if (Student::query()->where('admission_decision_id', $admissionDecisionId)->exists()) {
            throw BusinessRejection::forCode('students.admission_decision_consumed', 'the admission decision already has a student aggregate');
        }
        if (Student::query()->where('person_id', $personId)->exists()) {
            throw BusinessRejection::forCode('students.person_already_student', 'the person already has a student aggregate');
        }
        $applicantBranchId = trim((string) ($applicant->current_home_branch_id ?? $applicant->originating_branch_id ?? ''));
        if ($applicantBranchId === '' || $applicantBranchId !== $originatingBranchId) {
            throw BusinessRejection::forCode('students.admission_branch_mismatch', 'student conversion branch must match the applicant operational provenance');
        }

        $student = Student::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'admission_decision_id' => $admissionDecisionId,
            'student_code' => $studentCode,
            'originating_branch_id' => $originatingBranchId,
            'current_home_branch_id' => $originatingBranchId,
            'placement_profile_id' => $placementProfileId,
            'academic_eligibility_snapshot_id' => $eligibilitySnapshotId,
        ]);

        StudentStatus::query()->create([
            'id' => RandomIdentifier::new(),
            'student_id' => $student->id,
            'status' => StudentStatusRegistry::STATUS_ACTIVE,
            'effective_from' => (new CarbonImmutable)->startOfDay()->toDateString(),
            'reason' => 'admission conversion',
            'actor_id' => $actorId,
        ]);

        return ['student' => $student, 'student_code' => $studentCode];
    }
}
