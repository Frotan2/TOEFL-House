<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainRoom;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\AcademicRoom;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSection;
use App\Modules\Academic\Models\ClassSession;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Queries\TimetableQuery;
use App\Modules\Calendar\CalendarAuthority;
use App\Modules\Organization\Models\Branch;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsSessions;
use Tests\Concerns\BuildsTeachers;
use Tests\TestCase;

final class AcademicRoomsAndSectionsFeatureTest extends TestCase
{
    use BuildsActors;
    use BuildsSessions;
    use BuildsTeachers;

    private string $skillId;

    /**
     * Frozen Kabul business day for this test (see Tests\Support\DeterministicTestClock).
     * All "future session" fixtures are anchored relative to it so the room and
     * section retirement guards, which evaluate against the Kabul civil date
     * (kabul_today() in SQL), observe the sessions as future on EVERY real run
     * date — fixed literals silently move into the past and rot the suite.
     */
    private CarbonImmutable $businessDay;

    private string $classId;

    private string $secondSkillId;

    private string $secondClassId;

    private string $teacherPersonId = 'sched-teacher-1';

    private string $secondTeacherPersonId = 'sched-teacher-2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildActiveTeacher($this->teacherPersonId, null, 'academicf3f');
        $this->buildActiveTeacher($this->secondTeacherPersonId, null, 'academicf3g');
        $structure = app(MaintainAcademicStructure::class);
        $officer = $this->academicOfficer('sched-officer-setup');

        // Rooms and timetables live in the class branch: a session room must
        // belong to the class branch, so no second branch is seeded.
        $this->businessDay = app(CalendarAuthority::class)->today();
        $program = $structure->defineProgram($officer, 'Scheduling Program', 'sched-prog');
        $version = $structure->publishVersion($officer, Program::query()->findOrFail($program['program_id']), 'Scheduling v1', 'sched-ver');
        $period = $structure->definePeriod($officer, 'Scheduling Term', $this->businessDay->subMonth(), $this->businessDay->addMonths(3), 'sched-period');
        $structure->transitionPeriod($officer, AcademicPeriod::query()->findOrFail($period['period_id']), 'published', 'sched-period-pub');
        // A class requires an OPEN OFFERING for its branch, level and period;
        // the domain refuses to infer one.
        $fixtureLevel = app(MaintainAcademicStructure::class)->defineLevel($officer, $version['version_id'], 'lvl-ofacademic', 1, 'Level', 'A1', 'ofacademic-lvl');
        app(MaintainAcademicStructure::class)->declareBranchAvailability($officer, $this->bootstrapBranchId(), $fixtureLevel['level_id'], $period['period_id'], 'ofacademic-av');
        $fixtureOffering = app(MaintainAcademicStructure::class)->openOffering($officer, $this->bootstrapBranchId(), $fixtureLevel['level_id'], $period['period_id'], 200, 'ofacademic-of');

        $this->classId = app(MaintainClass::class)->defineClass($officer, $version['version_id'], $period['period_id'], 20, 'sched-class', null, $this->bootstrapBranchId())['class_id'];
        app(MaintainClass::class)->assignTeacher($officer, ClassModel::query()->findOrFail($this->classId), $this->teacherPersonId, $this->businessDay->subMonth(), null, 'sched-class-teacher');
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->classId), 'published', 'sched-class-pub');
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->classId), 'active', 'sched-class-active');

        // New sessions require explicit subject or skill authority: register
        // a skill, authorize the teacher for it in the branch, cover every
        // weekday with availability, and attribute it to the assignment.
        $this->skillId = $this->makeClassSchedulable($officer, $this->classId, $this->bootstrapBranchId(), 'ofrooms-sched');

        // A second class on the same version, period and branch lets the
        // suite prove the database timetable guards are the final arbiter
        // for concurrent bookings that the authority layer cannot see
        // (different teachers share a room).
        $this->secondClassId = app(MaintainClass::class)->defineClass($officer, $version['version_id'], $period['period_id'], 20, 'sched-class-2', null, $this->bootstrapBranchId())['class_id'];
        app(MaintainClass::class)->assignTeacher($officer, ClassModel::query()->findOrFail($this->secondClassId), $this->secondTeacherPersonId, $this->businessDay->subMonth(), null, 'sched-class-2-teacher');
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->secondClassId), 'published', 'sched-class-2-pub');
        app(MaintainClass::class)->transition($officer, ClassModel::query()->findOrFail($this->secondClassId), 'active', 'sched-class-2-active');
        $this->secondSkillId = $this->makeClassSchedulable($officer, $this->secondClassId, $this->bootstrapBranchId(), 'ofrooms-sched2');
    }

    public function test_room_lifecycle_resize_and_future_session_retire_guard(): void
    {
        $officer = $this->academicOfficer('sched-officer-room');
        $maintainRoom = app(MaintainRoom::class);

        $room = $maintainRoom->defineRoom($officer, $this->bootstrapBranchId(), 'Room One', 'R-01', 20, 'classroom', 'room-key-1');
        $this->assertDatabaseHas('academic_rooms', ['id' => $room['room_id'], 'capacity' => 20, 'lifecycle_state' => 'available']);

        $maintainRoom->transition($officer, AcademicRoom::query()->findOrFail($room['room_id']), 'maintenance', 'room-key-2');
        $this->assertSame('maintenance', AcademicRoom::query()->findOrFail($room['room_id'])->lifecycle_state);
        $maintainRoom->transition($officer, AcademicRoom::query()->findOrFail($room['room_id']), 'available', 'room-key-3');

        $resized = $maintainRoom->resize($officer, AcademicRoom::query()->findOrFail($room['room_id']), 30, 'room-key-4');
        $this->assertSame(30, $resized['capacity']);

        app(MaintainClass::class)->scheduleSession(
            $officer,
            ClassModel::query()->findOrFail($this->classId),
            $this->businessDay->addDays(3),
            '09:00',
            '11:00',
            'sched-session-room',
            $this->skillId,
            $room['room_id'],
            null,
        );

        try {
            $maintainRoom->transition($officer, AcademicRoom::query()->findOrFail($room['room_id']), 'retired', 'room-key-5');
            $this->fail('a room with future sessions cannot be retired');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.room_has_future_sessions', $rejection->errorCode());
        }

        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.room.define']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.room.transition.maintenance']);
    }

    public function test_section_lifecycle_and_timetable_scheduling(): void
    {
        $officer = $this->academicOfficer('sched-officer-section');
        $maintainClass = app(MaintainClass::class);
        $room = app(MaintainRoom::class)->defineRoom($officer, $this->bootstrapBranchId(), 'Room Two', 'R-02', 20, 'classroom', 'room-key-10');
        $section = $maintainClass->defineSection($officer, ClassModel::query()->findOrFail($this->classId), 'A', 10, 'section-key-1');
        $maintainClass->transitionSection($officer, ClassSection::query()->findOrFail($section['section_id']), 'open', 'section-key-2');

        $session = $maintainClass->scheduleSession(
            $officer,
            ClassModel::query()->findOrFail($this->classId),
            $this->businessDay->addDays(4),
            '09:00',
            '11:00',
            'section-session-1',
            $this->skillId,
            $room['room_id'],
            $section['section_id'],
        );
        $this->assertDatabaseHas('class_sessions', ['id' => $session['session_id'], 'room_id' => $room['room_id'], 'section_id' => $section['section_id']]);

        // a non-overlapping class session in the same section is allowed
        $maintainClass->scheduleSession(
            $officer,
            ClassModel::query()->findOrFail($this->classId),
            $this->businessDay->addDays(4),
            '12:00',
            '13:30',
            'section-session-2',
            $this->skillId,
            $room['room_id'],
            $section['section_id'],
        );

        // The delivery authority refuses overlapping windows for the class
        // teacher before any row is written: one teacher cannot deliver two
        // overlapping sessions in the same class.
        try {
            $maintainClass->scheduleSession(
                $officer,
                ClassModel::query()->findOrFail($this->classId),
                $this->businessDay->addDays(4),
                '10:30',
                '12:30',
                'section-session-overlap',
                $this->skillId,
                $room['room_id'],
                $section['section_id'],
            );
            $this->fail('overlapping section sessions must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.teacher_delivery_unauthorized', $rejection->errorCode());
        }
        $this->assertSame(2, ClassSession::query()->where('section_id', $section['section_id'])->where('scheduled_on', $this->businessDay->addDays(4)->toDateString())->count());

        $timetable = (new TimetableQuery)->forClass($this->classId, $this->businessDay->addDays(4));
        $this->assertSame('A', $timetable['sections'][0]['name']);
        $this->assertCount(2, $timetable['sessions']);
        $this->assertSame('R-02', $timetable['sessions'][0]['room']);

        try {
            $maintainClass->transitionSection($officer, ClassSection::query()->findOrFail($section['section_id']), 'closed', 'section-key-3');
            $this->fail('a section with future sessions cannot close');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.section_has_future_sessions', $rejection->errorCode());
        }

        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.section.define']);
        $this->assertDatabaseHas('audit_events', ['operation' => 'academic.section.transition.open']);
    }

    public function test_schedule_rejects_non_available_room_and_non_open_section(): void
    {
        $officer = $this->academicOfficer('sched-officer-guard');
        $maintainClass = app(MaintainClass::class);
        $room = app(MaintainRoom::class)->defineRoom($officer, $this->bootstrapBranchId(), 'Room Three', 'R-03', 20, 'classroom', 'room-key-20');
        app(MaintainRoom::class)->transition($officer, AcademicRoom::query()->findOrFail($room['room_id']), 'maintenance', 'room-key-21');

        try {
            $maintainClass->scheduleSession(
                $officer,
                ClassModel::query()->findOrFail($this->classId),
                $this->businessDay->addDays(5),
                '09:00',
                '11:00',
                'guard-session-room',
                $this->skillId,
                $room['room_id'],
                null,
            );
            $this->fail('a non-available room must reject scheduling');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('scheduling.room_not_available', $rejection->errorCode());
        }

        $section = $maintainClass->defineSection($officer, ClassModel::query()->findOrFail($this->classId), 'B', 10, 'section-key-20');
        try {
            $maintainClass->scheduleSession(
                $officer,
                ClassModel::query()->findOrFail($this->classId),
                $this->businessDay->addDays(5),
                '09:00',
                '11:00',
                'guard-session-section',
                $this->skillId,
                null,
                $section['section_id'],
            );
            $this->fail('a non-open section must reject scheduling');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('scheduling.section_not_open', $rejection->errorCode());
        }
    }

    public function test_room_double_booking_across_classes_is_refused_by_the_database_guard(): void
    {
        $officer = $this->academicOfficer('sched-officer-roomdb');
        $maintainClass = app(MaintainClass::class);
        $room = app(MaintainRoom::class)->defineRoom($officer, $this->bootstrapBranchId(), 'Room Four', 'R-04', 20, 'classroom', 'room-key-30');

        // Class one books the room for the morning...
        $maintainClass->scheduleSession(
            $officer,
            ClassModel::query()->findOrFail($this->classId),
            $this->businessDay->addDays(6),
            '09:00',
            '11:00',
            'roomdb-session-1',
            $this->skillId,
            $room['room_id'],
            null,
        );

        // ...and class two (a different teacher) tries to book the same room
        // in an overlapping window. The teacher authority cannot see the
        // other class's teacher, so the database room guard is the arbiter
        // that refuses the concurrent booking.
        DB::beginTransaction();
        try {
            $maintainClass->scheduleSession(
                $officer,
                ClassModel::query()->findOrFail($this->secondClassId),
                $this->businessDay->addDays(6),
                '10:30',
                '12:30',
                'roomdb-session-overlap',
                $this->secondSkillId,
                $room['room_id'],
                null,
            );
            $this->fail('an overlapping room booking across classes must be refused');
            DB::rollBack();
        } catch (QueryException $exception) {
            DB::rollBack();
            $this->assertStringContainsString('already booked', $exception->getMessage());
        }
        $this->assertSame(1, ClassSession::query()->where('room_id', $room['room_id'])->where('scheduled_on', $this->businessDay->addDays(6)->toDateString())->count());
        $this->assertDatabaseHas('class_sessions', ['class_id' => $this->classId, 'room_id' => $room['room_id'], 'scheduled_on' => $this->businessDay->addDays(6)->toDateString()]);
    }
}
