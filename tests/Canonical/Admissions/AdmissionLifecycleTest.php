<?php

declare(strict_types=1);

namespace Tests\Canonical\Admissions;

use App\Modules\Admissions\Commands\DecideAdmission;
use App\Modules\Admissions\Commands\EnrollAdmittedApplicant;
use App\Modules\Admissions\Commands\RegisterApplicant;
use App\Modules\Admissions\Models\AdmissionDecision;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Identity\Models\Person;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;
use Tests\Concerns\DecidesAdmissions;

final class AdmissionLifecycleTest extends CanonicalTestCase
{
    use DecidesAdmissions;

    private string $applicantPersonId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applicantPersonId = 'canon-adm-person-1';
        $this->canonicalPersonWithAuthority($this->applicantPersonId, []);
    }

    private function registeredApplicant(): Applicant
    {
        $result = app(RegisterApplicant::class)->register(
            $this->admissionsClerk('canon-adm-reception'),
            $this->applicantPersonId,
            'IELTS Preparation',
            'canon-adm-reg-1',
            null,
            $this->sharedBranchId(),
        );

        return Applicant::query()->findOrFail($result['applicant_id']);
    }

    public function test_register_review_approve_and_convert_produces_one_active_student(): void
    {
        $clerk = $this->admissionsClerk('canon-adm-reception-1');
        $reviewer = $this->admissionsReviewer('canon-adm-review-1');
        $approver = $this->admissionsApprover('canon-adm-approve-1');
        $applicant = $this->registeredApplicant();

        $initiated = app(DecideAdmission::class)->initiate($clerk, $applicant, true, 'meets entry policy', 'interview-notes/canon-1', 'canon-adm-dec-init');
        $this->assertSame('proposed', $initiated['lifecycle_state']);
        $this->assertDatabaseHas('admission_decisions', [
            'id' => $initiated['decision_id'],
            'outcome' => 'admit',
            'initiator_id' => 'canon-adm-reception-1',
            'reviewer_id' => null,
            'approver_id' => null,
            'lifecycle_state' => 'proposed',
        ]);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'lifecycle_state' => 'applicant']);

        $reviewed = app(DecideAdmission::class)->review($reviewer, AdmissionDecision::query()->findOrFail($initiated['decision_id']), 'canon-adm-dec-review');
        $this->assertSame('reviewed', $reviewed['lifecycle_state']);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'lifecycle_state' => 'applicant']);

        $decision = app(DecideAdmission::class)->approve($approver, AdmissionDecision::query()->findOrFail($initiated['decision_id']), 'canon-adm-dec-approve');
        $this->assertSame('admit', $decision['outcome']);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'lifecycle_state' => 'admitted']);
        $this->assertDatabaseHas('admission_decisions', [
            'id' => $decision['decision_id'],
            'outcome' => 'admit',
            'initiator_id' => 'canon-adm-reception-1',
            'reviewer_id' => 'canon-adm-review-1',
            'approver_id' => 'canon-adm-approve-1',
            'lifecycle_state' => 'final',
        ]);

        $converted = app(EnrollAdmittedApplicant::class)->convert($approver, $applicant, 'canon-adm-convert');
        $replay = app(EnrollAdmittedApplicant::class)->convert($approver, $applicant, 'canon-adm-convert');

        $this->assertSame($converted, $replay);
        $this->assertDatabaseHas('students', [
            'id' => $converted['student_id'],
            'person_id' => $this->applicantPersonId,
            'admission_decision_id' => $decision['decision_id'],
        ]);
        $this->assertDatabaseHas('student_statuses', [
            'student_id' => $converted['student_id'],
            'status' => 'active',
            'reason' => 'admission conversion',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'operation' => 'admissions.convert',
            'target_type' => 'student',
            'target_id' => $converted['student_id'],
        ]);
    }

    public function test_reception_cannot_review_or_approve_and_an_independent_chain_can_finalize(): void
    {
        $clerk = $this->admissionsClerk('canon-adm-reception-2');
        $applicant = $this->registeredApplicant();
        $initiated = app(DecideAdmission::class)->initiate($clerk, $applicant, true, 'canonical', 'ev/canon-2', 'canon-adm-dec-2-init');
        $decisionId = $initiated['decision_id'];

        try {
            app(DecideAdmission::class)->review($clerk, AdmissionDecision::query()->findOrFail($decisionId), 'canon-adm-dec-2-review');
            $this->fail('reception must never review');
        } catch (AuthorizationDenied $denial) {
            // The clerk initiated this decision, so separation of duties is
            // evaluated before capability: the same actor may never review
            // their own proposal, whatever authority they hold.
            $this->assertSame('admissions.single_actor', $denial->errorCode());
        }
        $this->assertDatabaseHas('admission_decisions', ['id' => $decisionId, 'lifecycle_state' => 'proposed']);
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'lifecycle_state' => 'applicant']);

        app(DecideAdmission::class)->review($this->admissionsReviewer('canon-adm-review-2'), AdmissionDecision::query()->findOrFail($decisionId), 'canon-adm-dec-2b-review');

        try {
            app(DecideAdmission::class)->approve($clerk, AdmissionDecision::query()->findOrFail($decisionId), 'canon-adm-dec-2-approve');
            $this->fail('reception must not approve');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('admissions.single_actor', $denial->errorCode());
        }
        $this->assertDatabaseHas('admission_decisions', ['id' => $decisionId, 'lifecycle_state' => 'reviewed']);

        app(DecideAdmission::class)->approve($this->admissionsApprover('canon-adm-approve-2'), AdmissionDecision::query()->findOrFail($decisionId), 'canon-adm-dec-2b-approve');
        $this->assertDatabaseHas('applicants', ['id' => $applicant->id, 'lifecycle_state' => 'admitted']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'admissions.review.denied', 'actor_id' => 'canon-adm-reception-2']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'admissions.approve.denied', 'actor_id' => 'canon-adm-reception-2']);
    }

    public function test_one_actor_holding_all_admission_capabilities_cannot_review_own_proposal(): void
    {
        $super = $this->actorWithStructureCapabilities('canon-adm-super', [
            'admissions.initiate',
            'admissions.review',
            'admissions.approve',
        ]);
        $applicant = $this->registeredApplicant();
        $initiated = app(DecideAdmission::class)->initiate($super, $applicant, true, 'one actor', 'ev/canon-3', 'canon-adm-dec-3-init');

        $this->expectException(AuthorizationDenied::class);
        $this->expectExceptionMessage('the admission reviewer must differ from the initiator');
        app(DecideAdmission::class)->review($super, AdmissionDecision::query()->findOrFail($initiated['decision_id']), 'canon-adm-dec-3-review');
    }

    public function test_rejected_applicant_cannot_convert_and_unverified_person_cannot_register(): void
    {
        $clerk = $this->admissionsClerk('canon-adm-reception-3');
        $reviewer = $this->admissionsReviewer('canon-adm-review-3');
        $approver = $this->admissionsApprover('canon-adm-approve-3');
        $applicant = $this->registeredApplicant();
        $this->runAdmissionDecision($clerk, $reviewer, $approver, $applicant, false, 'below threshold', 'placement/canon-3', 'canon-adm-dec-4');

        try {
            app(EnrollAdmittedApplicant::class)->convert($approver, $applicant, 'canon-adm-convert-reject');
            $this->fail('a rejected applicant must not convert');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('admissions.convert_requires_admission', $rejection->errorCode());
        }

        $unverified = Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => 'Canonical Unverified Applicant',
            'date_of_birth' => '2005-05-05',
            'verification_state' => Person::VERIFICATION_UNVERIFIED,
            'home_branch_id' => $this->sharedBranchId(),
        ]);

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('an applicant requires a verified person identity');
        app(RegisterApplicant::class)->register($clerk, $unverified->id, 'General English', 'canon-adm-reg-unverified', null, $this->sharedBranchId());
    }

    public function test_duplicate_open_admission_file_is_rejected(): void
    {
        $applicant = $this->registeredApplicant();

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('already has an open admission file');
        app(RegisterApplicant::class)->register($this->admissionsClerk('canon-adm-reception-4'), $this->applicantPersonId, 'Another Program', 'canon-adm-reg-duplicate', null, $this->sharedBranchId());
    }

    public function test_conversion_is_idempotent_for_the_same_key_and_rejected_for_a_new_key(): void
    {
        $clerk = $this->admissionsClerk('canon-adm-reception-5');
        $reviewer = $this->admissionsReviewer('canon-adm-review-5');
        $approver = $this->admissionsApprover('canon-adm-approve-5');
        $applicant = $this->registeredApplicant();
        $this->runAdmissionDecision($clerk, $reviewer, $approver, $applicant, true, 'ok', 'ev/canon-5', 'canon-adm-dec-5');

        app(EnrollAdmittedApplicant::class)->convert($approver, $applicant, 'canon-adm-convert-1');

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('already produced a student');
        app(EnrollAdmittedApplicant::class)->convert($approver, $applicant, 'canon-adm-convert-2');
    }

    public function test_final_admission_decision_is_immutable(): void
    {
        $applicant = $this->registeredApplicant();
        $decision = $this->runAdmissionDecision(
            $this->admissionsClerk('canon-adm-reception-6'),
            $this->admissionsReviewer('canon-adm-review-6'),
            $this->admissionsApprover('canon-adm-approve-6'),
            $applicant,
            true,
            'ok',
            'ev/canon-6',
            'canon-adm-dec-6',
        );

        $this->assertSame('final', $decision['lifecycle_state']);
        $this->expectException(QueryException::class);
        DB::statement("UPDATE admission_decisions SET outcome = 'reject' WHERE id = ?", [$decision['decision_id']]);
    }

    public function test_registration_requires_operational_branch_provenance(): void
    {
        $person = Person::query()->create([
            'id' => RandomIdentifier::new(),
            'legal_name' => 'No Branch Applicant',
            'date_of_birth' => '2004-04-04',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'canon-no-branch',
            'identity_evidence_ref' => 'evidence/canon-no-branch',
            'verified_by' => 'canon-no-branch-verifier',
            'verified_at' => now()->toDateTimeString(),
            'home_branch_id' => null,
        ]);

        $this->expectException(BusinessRejection::class);
        $this->expectExceptionMessage('new applicant registration requires an operational branch');
        app(RegisterApplicant::class)->register($this->admissionsClerk('canon-adm-reception-7'), $person->id, 'General English', 'canon-adm-reg-no-branch');
    }
}
