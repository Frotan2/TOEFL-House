<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\ManageAssessmentResult;
use App\Modules\Academic\Models\AssessmentAttempt;
use App\Modules\Academic\Models\AssessmentResult;
use App\Modules\Academic\Models\Enrollment;
use App\Modules\Academic\Models\ResultCorrection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

final class AssessmentResultDbAuthorityFeatureTest extends CanonicalTestCase
{
    /** @return array{enrollment: Enrollment, attempt: AssessmentAttempt, result: AssessmentResult} */
    private function releasedResult(string $keyPrefix): array
    {
        $officer = $this->actorWith($keyPrefix.'-officer', ['academic.structure', 'academic.schedule']);
        $class = $this->newActiveClass($officer, $keyPrefix, 2);
        $student = $this->newStudent();
        $enrollment = Enrollment::query()->create([
            'id' => $keyPrefix.'-enrollment',
            'student_id' => $student['student']->id,
            'class_id' => $class['class_id'],
            'offering_id' => $class['offering_id'],
            'originating_branch_id' => $class['branch_id'],
            'lifecycle_state' => 'active',
        ]);

        $command = app(ManageAssessmentResult::class);
        $scorer = $this->actorWith($keyPrefix.'-scorer', ['academic.assess']);
        $moderator = $this->actorWith($keyPrefix.'-moderator', ['academic.moderate']);
        $approver = $this->actorWith($keyPrefix.'-approver', ['academic.approve_result']);
        $releaser = $this->actorWith($keyPrefix.'-releaser', ['academic.release']);
        $attempt = $command->submitAttempt($scorer, $enrollment, 'assessment', 'evidence/'.$keyPrefix, $keyPrefix.'-attempt');
        $result = $command->score($scorer, AssessmentAttempt::query()->findOrFail($attempt['attempt_id']), '88.00', $keyPrefix.'-score');
        $resultRow = AssessmentResult::query()->findOrFail($result['result_id']);
        $command->moderate($moderator, $resultRow, $keyPrefix.'-moderate');
        $command->approve($approver, $resultRow, $keyPrefix.'-approve');
        $command->release($releaser, $resultRow, $keyPrefix.'-release');

        return [
            'enrollment' => $enrollment->refresh(),
            'attempt' => AssessmentAttempt::query()->findOrFail($attempt['attempt_id']),
            'result' => $resultRow->refresh(),
        ];
    }

    public function test_direct_sql_cannot_rewrite_release_signoff_provenance(): void
    {
        $fixture = $this->releasedResult('dbsign');

        try {
            DB::table('assessment_results')
                ->where('id', $fixture['result']->id)
                ->update(['approved_by' => 'forged-approver']);
            $this->fail('release sign-off provenance must be immutable at the database boundary');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $fresh = AssessmentResult::query()->findOrFail($fixture['result']->id);
        $this->assertSame($fixture['result']->approved_by, $fresh->approved_by);
        $this->assertSame($fixture['result']->released_by, $fresh->released_by);
    }

    public function test_direct_sql_cannot_release_a_correction_before_the_correction_is_approved(): void
    {
        $fixture = $this->releasedResult('dbreplace');
        $moderator = $this->actorWith('dbreplace-corrector', ['academic.moderate']);
        $command = app(ManageAssessmentResult::class);
        $proposal = $command->proposeCorrection(
            $moderator,
            $fixture['result'],
            '92.00',
            'database-boundary test',
            'dbreplace-correction-propose',
        );
        $correction = ResultCorrection::query()->findOrFail($proposal['correction_id']);

        // Close the source result exactly as the correction workflow does, but
        // deliberately leave the correction in PROPOSED state. The database
        // must reject any forged released replacement at this boundary.
        DB::table('assessment_results')
            ->where('id', $fixture['result']->id)
            ->update(['lifecycle_state' => 'corrected']);

        try {
            DB::table('assessment_results')->insert([
                'id' => 'dbreplace-forged-result',
                'attempt_id' => $fixture['attempt']->id,
                'score' => '92.00',
                'lifecycle_state' => 'released',
                'corrects_id' => $fixture['result']->id,
                'correction_reason' => $correction->reason,
                'scored_by' => $correction->proposed_by,
                'moderated_by' => $fixture['result']->moderated_by,
                'approved_by' => 'forged-approver',
                'released_by' => 'forged-approver',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('a proposed correction must never be enough to create a released replacement');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $this->assertDatabaseHas('result_corrections', [
            'id' => $correction->id,
            'lifecycle_state' => 'proposed',
        ]);
        $this->assertDatabaseCount('assessment_results', 1);
    }

    public function test_direct_sql_cannot_commit_an_approved_correction_without_its_replacement_result(): void
    {
        $fixture = $this->releasedResult('dbdefer');
        $moderator = $this->actorWith('dbdefer-corrector', ['academic.moderate']);
        $command = app(ManageAssessmentResult::class);
        $proposal = $command->proposeCorrection(
            $moderator,
            $fixture['result'],
            '93.00',
            'deferred correction authority test',
            'dbdefer-correction-propose',
        );

        try {
            DB::transaction(function () use ($proposal): void {
                DB::table('result_corrections')
                    ->where('id', $proposal['correction_id'])
                    ->update([
                        'lifecycle_state' => 'approved',
                        'approved_by' => 'dbdefer-forged-approver',
                        'updated_at' => now(),
                    ]);
            });
            $this->fail('an approved correction cannot commit without its corrected source and released replacement');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }

        $this->assertDatabaseHas('result_corrections', [
            'id' => $proposal['correction_id'],
            'lifecycle_state' => 'proposed',
            'approved_by' => null,
        ]);
        $this->assertDatabaseHas('assessment_results', [
            'id' => $fixture['result']->id,
            'lifecycle_state' => 'released',
        ]);
    }
}
