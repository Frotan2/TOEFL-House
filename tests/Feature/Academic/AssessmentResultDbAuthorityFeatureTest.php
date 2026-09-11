<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainEnrollment;
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
        $requested = $this->newSeatRequest(
            $this->actorWith($keyPrefix.'-enroller', ['academic.enroll']),
            (string) $student['student']->id,
            $class['class_id'],
            $keyPrefix.'-enrollment-request',
        );
        app(MaintainEnrollment::class)->activate(
            $this->actorWith($keyPrefix.'-enrollment-approver', ['academic.enroll_approve']),
            Enrollment::query()->findOrFail($requested['enrollment_id']),
            $keyPrefix.'-enrollment-activate',
        );
        $enrollment = Enrollment::query()->findOrFail($requested['enrollment_id']);

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

        $this->assertSqlRejected(
            fn (): int => DB::table('assessment_results')
                ->where('id', $fixture['result']->id)
                ->update(['approved_by' => 'forged-approver']),
            'release sign-off provenance must be immutable at the database boundary',
        );

        $fresh = AssessmentResult::query()->findOrFail($fixture['result']->id);
        $this->assertSame($fixture['result']->approved_by, $fresh->approved_by);
        $this->assertSame($fixture['result']->released_by, $fresh->released_by);
    }

    public function test_direct_sql_cannot_release_a_correction_against_an_unclosed_source_or_unapproved_correction(): void
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

        $this->assertSqlRejected(
            fn (): bool => DB::table('assessment_results')->insert([
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
            ]),
            'a replacement must not be insertable while the source is still released and the correction is proposed',
        );

        $this->assertDatabaseHas('result_corrections', [
            'id' => $correction->id,
            'lifecycle_state' => 'proposed',
        ]);
        $this->assertDatabaseHas('assessment_results', [
            'id' => $fixture['result']->id,
            'lifecycle_state' => 'released',
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
                // RefreshDatabase keeps this test in an outer transaction, so
                // PostgreSQL would otherwise wait until teardown to evaluate
                // the deferred commit guard. Force its named constraint now;
                // the nested transaction then rolls back the rejected write.
                DB::statement('SET CONSTRAINTS academic_result_correction_commit_guard_trigger IMMEDIATE');
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

    /**
     * PostgreSQL marks the current transaction aborted after a constraint
     * violation. Run deliberately-invalid direct SQL in a savepoint so the
     * following assertions inspect the unchanged committed fixture instead of
     * a poisoned test transaction.
     */
    private function assertSqlRejected(callable $operation, string $failureMessage): void
    {
        try {
            DB::transaction(function () use ($operation): void {
                $operation();
            });
            $this->fail($failureMessage);
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->getCode());
        }
    }
}
