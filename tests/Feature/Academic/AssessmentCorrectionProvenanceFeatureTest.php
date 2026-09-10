<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\ManageAssessmentResult;
use App\Modules\Academic\Models\AssessmentAttempt;
use App\Modules\Academic\Models\AssessmentResult;
use App\Modules\Academic\Models\Enrollment;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

final class AssessmentCorrectionProvenanceFeatureTest extends CanonicalTestCase
{
    public function test_approved_correction_records_approval_and_release_provenance_on_released_result(): void
    {
        $officer = $this->actorWith('cor-provenance-officer', ['academic.structure', 'academic.schedule']);
        $class = $this->newActiveClass($officer, 'corprov', 2);
        $student = $this->newStudent();

        $enrollment = Enrollment::query()->create([
            'id' => 'corprov-enrollment',
            'student_id' => $student['student']->id,
            'class_id' => $class['class_id'],
            'offering_id' => $class['offering_id'],
            'originating_branch_id' => $class['branch_id'],
            'lifecycle_state' => 'active',
        ]);

        $scorer = $this->actorWith('corprov-scorer', ['academic.assess']);
        $moderator = $this->actorWith('corprov-moderator', ['academic.moderate']);
        $approver = $this->actorWith('corprov-approver', ['academic.approve_result']);
        $releaser = $this->actorWith('corprov-releaser', ['academic.release']);

        $command = app(ManageAssessmentResult::class);
        $attempt = $command->submitAttempt($scorer, $enrollment, 'assessment', 'evidence/corprov', 'corprov-attempt');
        $result = $command->score(
            $scorer,
            AssessmentAttempt::query()->findOrFail($attempt['attempt_id']),
            '81.00',
            'corprov-score',
        );
        $resultRow = AssessmentResult::query()->findOrFail($result['result_id']);
        $command->moderate($moderator, $resultRow, 'corprov-moderate');
        $command->approve($approver, $resultRow, 'corprov-approve');
        $command->release($releaser, $resultRow, 'corprov-release');

        $proposal = $command->proposeCorrection(
            $moderator,
            $resultRow->refresh(),
            '84.00',
            'verified against the source evidence',
            'corprov-correction-propose',
        );

        $corrected = $command->approveCorrection(
            $approver,
            \App\Modules\Academic\Models\ResultCorrection::query()->findOrFail($proposal['correction_id']),
            'corprov-correction-approve',
        );

        $this->assertDatabaseHas('assessment_results', [
            'id' => $corrected['result_id'],
            'lifecycle_state' => 'released',
            'corrects_id' => $result['result_id'],
            'approved_by' => $approver->actorId,
            'released_by' => $approver->actorId,
        ]);

        $audit = DB::table('audit_events')
            ->where('operation', 'academic.result.correction.approve')
            ->where('target_id', $corrected['result_id'])
            ->first();

        $this->assertNotNull($audit);
        $payload = json_decode((string) $audit->after, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($approver->actorId, $payload['approved_by'] ?? null);
        $this->assertSame($approver->actorId, $payload['released_by'] ?? null);
    }
}
