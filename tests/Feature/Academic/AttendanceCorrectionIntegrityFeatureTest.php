<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Commands\RecordAttendance;
use App\Modules\Academic\Models\AttendanceFact;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSession;
use App\Modules\Academic\Models\Enrollment;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Attendance corrections form a strict one-successor lineage. The command
 * rejects a second direct correction, and the PostgreSQL boundary rejects a
 * direct-SQL bypass of the same invariant.
 */
final class AttendanceCorrectionIntegrityFeatureTest extends CanonicalTestCase
{
    public function test_an_attendance_fact_has_one_direct_correction_and_sql_cannot_create_a_second(): void
    {
        $officer = $this->actorWith('attendance-canon-officer', [
            'academic.structure',
            'academic.schedule',
            'academic.teacher_manage',
            'academic.attendance',
            'academic.enroll',
            'academic.enroll_approve',
        ]);
        $delivery = $this->newActiveClass($officer, 'attcorr', 2, 10);
        $student = $this->newStudent()['student'];

        $requested = $this->newSeatRequest(
            $this->actorWith('attendance-enroll-request', ['academic.enroll']),
            (string) $student->id,
            $delivery['class_id'],
            'attendance-enroll-request',
        );
        app(MaintainEnrollment::class)->activate(
            $this->actorWith('attendance-enroll-approve', ['academic.enroll_approve']),
            Enrollment::query()->findOrFail($requested['enrollment_id']),
            'attendance-enroll-activate',
        );

        $session = app(MaintainClass::class)->scheduleSession(
            $officer,
            ClassModel::query()->findOrFail($delivery['class_id']),
            CarbonImmutable::today()->addDays(2),
            '09:00',
            '10:00',
            'attendance-session',
            $delivery['skill_id'],
        );

        $recorder = $this->actorWith('attendance-recorder', ['academic.attendance']);
        $original = app(RecordAttendance::class)->record(
            $recorder,
            ClassSession::query()->findOrFail($session['session_id']),
            Enrollment::query()->findOrFail($requested['enrollment_id']),
            'present',
            'attendance-record',
        );

        $firstCorrection = app(RecordAttendance::class)->correct(
            $recorder,
            AttendanceFact::query()->findOrFail($original['fact_id']),
            'absent',
            'gate log correction',
            'attendance-correction-1',
        );

        try {
            app(RecordAttendance::class)->correct(
                $recorder,
                AttendanceFact::query()->findOrFail($original['fact_id']),
                'late',
                'second correction attempt',
                'attendance-correction-2',
            );
            $this->fail('an attendance fact must not accept two direct corrections');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.attendance_correction_exists', $rejection->errorCode());
        }

        $this->assertSame(2, AttendanceFact::query()->where('enrollment_id', $requested['enrollment_id'])->count());
        $this->assertSame(1, AttendanceFact::query()->where('corrects_id', $original['fact_id'])->count());
        $this->assertSame($original['fact_id'], AttendanceFact::query()->findOrFail($firstCorrection['fact_id'])->corrects_id);

        DB::beginTransaction();
        try {
            DB::table('attendance_facts')->insert([
                'id' => RandomIdentifier::new(),
                'session_id' => $session['session_id'],
                'enrollment_id' => $requested['enrollment_id'],
                'status' => 'late',
                'corrects_id' => $original['fact_id'],
                'reason' => 'direct SQL bypass attempt',
                'recorded_by' => 'attendance-sql-test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('the database must reject a second correction targeting the same fact');
        } catch (QueryException $exception) {
            DB::rollBack();
            $this->assertSame('23505', $exception->getCode());
        }

        $this->assertSame(2, AttendanceFact::query()->where('enrollment_id', $requested['enrollment_id'])->count());
    }
}
