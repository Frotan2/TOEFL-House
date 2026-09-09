<?php

declare(strict_types=1);

namespace Tests\Canonical\Admissions;

use App\Modules\Admissions\Commands\DecideAdmission;
use App\Modules\Admissions\Commands\RegisterApplicant;
use App\Modules\Admissions\Models\AdmissionDecision;
use App\Modules\Admissions\Models\Applicant;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

final class AdmissionDecisionConcurrencyTest extends CanonicalTestCase
{
    private string $personId = 'canon-adm-concurrency-person';

    protected function setUp(): void
    {
        parent::setUp();
        $this->canonicalPersonWithAuthority($this->personId, []);
    }

    private function applicant(): Applicant
    {
        $registered = app(RegisterApplicant::class)->register(
            $this->admissionsClerk('canon-adm-concurrency-reception'),
            $this->personId,
            'General English',
            'canon-adm-concurrency-register',
            null,
            $this->sharedBranchId(),
        );

        return Applicant::query()->findOrFail($registered['applicant_id']);
    }

    public function test_second_in_flight_decision_is_rejected_at_command_boundary(): void
    {
        $applicant = $this->applicant();
        $clerk = $this->admissionsClerk('canon-adm-concurrency-clerk');

        $first = app(DecideAdmission::class)->initiate(
            $clerk,
            $applicant,
            true,
            'first proposal',
            'evidence/concurrency-1',
            'canon-adm-concurrency-init-1',
        );

        try {
            app(DecideAdmission::class)->initiate(
                $clerk,
                $applicant,
                false,
                'second proposal',
                'evidence/concurrency-2',
                'canon-adm-concurrency-init-2',
            );
            $this->fail('a second in-flight admission decision must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('admissions.decision_in_progress', $rejection->errorCode());
            $this->assertFalse($rejection->retryable());
        }

        $this->assertDatabaseCount('admission_decisions', 1);
        $this->assertDatabaseHas('admission_decisions', [
            'id' => $first['decision_id'],
            'lifecycle_state' => 'proposed',
        ]);
    }

    public function test_reviewed_decision_also_excludes_a_second_in_flight_insert_at_database_boundary(): void
    {
        $applicant = $this->applicant();
        $clerk = $this->admissionsClerk('canon-adm-concurrency-clerk-2');
        $reviewer = $this->admissionsReviewer('canon-adm-concurrency-reviewer');
        $initiated = app(DecideAdmission::class)->initiate(
            $clerk,
            $applicant,
            true,
            'reviewed proposal',
            'evidence/concurrency-3',
            'canon-adm-concurrency-init-3',
        );
        app(DecideAdmission::class)->review(
            $reviewer,
            AdmissionDecision::query()->findOrFail($initiated['decision_id']),
            'canon-adm-concurrency-review-3',
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('admission_decisions_one_inflight_per_applicant');
        DB::table('admission_decisions')->insert([
            'id' => RandomIdentifier::new(),
            'applicant_id' => $applicant->id,
            'outcome' => 'reject',
            'reason' => 'forged concurrent chain',
            'evidence_ref' => 'evidence/forged',
            'initiator_id' => $clerk->actorId,
            'reviewer_id' => null,
            'approver_id' => null,
            'lifecycle_state' => 'proposed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
