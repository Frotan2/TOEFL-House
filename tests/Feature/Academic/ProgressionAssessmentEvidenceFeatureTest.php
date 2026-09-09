<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\DecideProgression;
use App\Modules\Academic\Models\AssessmentAttempt;
use App\Modules\Academic\Models\AssessmentResult;
use App\Modules\Academic\Models\Enrollment;
use App\Support\Errors\BusinessRejection;
use Tests\Canonical\CanonicalTestCase;

final class ProgressionAssessmentEvidenceFeatureTest extends CanonicalTestCase
{
    public function test_progression_rejects_an_assessment_result_that_is_not_released(): void
    {
        $officer = $this->actorWith('progev-officer', ['academic.structure', 'academic.schedule']);
        $class = $this->newActiveClass($officer, 'progev-class', 2);
        $student = $this->newStudent();

        $enrollment = Enrollment::query()->create([
            'id' => 'progev-enrollment',
            'student_id' => $student['student']->id,
            'class_id' => $class['class_id'],
            'offering_id' => $class['offering_id'],
            'originating_branch_id' => $class['branch_id'],
            'lifecycle_state' => 'active',
        ]);

        $attempt = AssessmentAttempt::query()->create([
            'id' => 'progev-attempt',
            'enrollment_id' => $enrollment->id,
            'assessed_on' => now()->toDateString(),
            'kind' => 'assessment',
            'evidence_ref' => 'evidence/progev',
            'lifecycle_state' => 'submitted',
            'recorded_by' => 'progev-recorder',
        ]);
        $result = AssessmentResult::query()->create([
            'id' => 'progev-result',
            'attempt_id' => $attempt->id,
            'score' => '99.00',
            'lifecycle_state' => 'scored',
            'scored_by' => 'progev-scorer',
        ]);

        $proposer = $this->actorWith('progev-proposer', ['academic.progression_propose']);

        try {
            app(DecideProgression::class)->propose(
                $proposer,
                $student['student']->id,
                $class['class_id'],
                'repeat',
                'progression evidence review',
                'progev-propose',
                $result->id,
                'released evidence required for progression',
            );
            $this->fail('unreleased assessment evidence must never support progression');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.progression_result_not_released', $rejection->errorCode());
        }

        $this->assertDatabaseCount('progression_decisions', 0);
        $this->assertDatabaseHas('assessment_results', [
            'id' => $result->id,
            'lifecycle_state' => 'scored',
        ]);
    }
}
