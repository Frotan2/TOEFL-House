<?php

declare(strict_types=1);

namespace App\Modules\Students\Domain;

use App\Modules\Academic\Placement\Models\PlacementProfile;
use App\Modules\Academic\Placement\Queries\AcademicEligibilitySnapshotQuery;
use App\Modules\Admissions\Models\AdmissionDecision;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Students\Models\Student;
use App\Modules\Students\Models\StudentStatus;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;

/**
 * Student-owned admission conversion port. Admissions may authorize and
 * orchestrate the conversion, but Student Lifecycle remains the only module
 * that writes the student aggregate and its initial status history.
 *
 * The port also defends its own canonical boundary: a caller cannot create a
 * Student from a proposed/rejected/non-admit decision or a non-admitted
 * applicant merely because the foreign-key relationship happens to exist.
 * Placement evidence is consumed only through the exact lineage already
 * attached to the admitted applicant and the existing signed-snapshot reader.
 */
final class StudentAdmissionRegistrar
{
    public function __construct(
        private readonly AcademicEligibilitySnapshotQuery $eligibilitySnapshots,
        private readonly CalendarAuthority $calendar,
    ) {}

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

        $this->assertPlacementEvidenceBinding($applicant, $placementProfileId, $eligibilitySnapshotId, $personId, $originatingBranchId);

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
            // The initial status fact is effective on the Kabul civil append
            // day; the kabul_today() append-day trigger enforces equality.
            'effective_from' => $this->calendar->todayAsString(),
            'reason' => 'admission conversion',
            'actor_id' => $actorId,
        ]);

        return ['student' => $student, 'student_code' => $studentCode];
    }

    private function assertPlacementEvidenceBinding(
        Applicant $applicant,
        ?string $placementProfileId,
        ?string $eligibilitySnapshotId,
        string $personId,
        string $originatingBranchId,
    ): void {
        $applicantProfileId = trim((string) ($applicant->placement_profile_id ?? ''));
        $applicantSnapshotId = trim((string) ($applicant->academic_eligibility_snapshot_id ?? ''));
        $requestedProfileId = trim((string) ($placementProfileId ?? ''));
        $requestedSnapshotId = trim((string) ($eligibilitySnapshotId ?? ''));

        if ($applicantProfileId === '') {
            if ($requestedProfileId !== '' || $requestedSnapshotId !== '') {
                throw BusinessRejection::forCode('students.admission_placement_mismatch', 'Student conversion cannot attach placement evidence that was not part of the admitted applicant lineage');
            }

            return;
        }

        if ($applicantSnapshotId === '') {
            throw BusinessRejection::forCode('students.admission_eligibility_snapshot_missing', 'an applicant with placement evidence requires its bound eligibility snapshot');
        }
        if ($requestedProfileId !== $applicantProfileId || $requestedSnapshotId !== $applicantSnapshotId) {
            throw BusinessRejection::forCode('students.admission_placement_mismatch', 'Student conversion must preserve the exact placement profile and eligibility snapshot attached to the admitted applicant');
        }

        /** @var PlacementProfile|null $profile */
        $profile = PlacementProfile::query()->whereKey($applicantProfileId)->lockForUpdate()->first();
        if ($profile === null || trim((string) $profile->person_id) !== $personId) {
            throw BusinessRejection::forCode('students.admission_placement_person_mismatch', 'the applicant placement profile must belong to the admitted applicant person');
        }
        if ($profile->lineage_version !== PlacementProfile::LINEAGE_VERSION) {
            throw BusinessRejection::forCode('students.admission_placement_lineage_invalid', 'student conversion cannot consume placement evidence from an ungoverned lineage version');
        }
        if (! in_array($profile->lifecycle_state, [
            PlacementProfile::STATE_RELEASED,
            PlacementProfile::STATE_SUPERSEDED,
            PlacementProfile::STATE_RETIRED,
        ], true)) {
            throw BusinessRejection::forCode('students.admission_placement_not_released', 'student conversion requires released placement evidence or a governed historical successor state');
        }
        $profileBranchId = trim((string) ($profile->current_home_branch_id ?? $profile->originating_branch_id ?? ''));
        if ($profileBranchId !== $originatingBranchId) {
            throw BusinessRejection::forCode('students.admission_placement_branch_mismatch', 'the applicant placement evidence branch must match the student admission branch provenance');
        }
        if (trim((string) $profile->academic_eligibility_snapshot_id) !== $applicantSnapshotId) {
            throw BusinessRejection::forCode('students.admission_eligibility_snapshot_mismatch', 'the applicant snapshot must be the placement profile signed-evidence pointer');
        }

        $snapshot = $this->eligibilitySnapshots->byId($applicantSnapshotId);
        if ($snapshot === null || ($snapshot['verification']['valid'] ?? false) !== true) {
            $reason = trim((string) ($snapshot['verification']['reason'] ?? 'unverifiable'));
            throw BusinessRejection::forCode('students.admission_eligibility_snapshot_unverified', 'the applicant eligibility snapshot could not be verified at conversion: '.$reason);
        }

        $snapshotData = $snapshot['snapshot'] ?? [];
        if (! is_array($snapshotData)
            || trim((string) ($snapshotData['placement_profile_id'] ?? '')) !== $applicantProfileId
            || trim((string) ($snapshotData['person_id'] ?? '')) !== $personId
            || trim((string) ($snapshotData['originating_branch_id'] ?? '')) !== $profileBranchId) {
            throw BusinessRejection::forCode('students.admission_eligibility_snapshot_mismatch', 'the signed placement snapshot does not match the admitted applicant lineage');
        }
    }
}
