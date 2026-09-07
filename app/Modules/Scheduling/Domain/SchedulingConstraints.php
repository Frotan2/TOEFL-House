<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Domain;

use App\Modules\Academic\Domain\ClassLifecycle;
use App\Modules\Academic\Domain\ClassSectionLifecycle;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\AcademicRoom;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSection;
use App\Modules\Academic\Models\Skill;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Domain\TeacherAuthority;
use App\Support\Errors\BusinessRejection;

/**
 * Scheduling-owned planning constraints. Academic Delivery remains the sole
 * writer of ClassSession facts; this service validates room, section, skill,
 * class lifecycle, branch provenance, and time-window feasibility before that
 * write. Database exclusion/locking rules remain the final race arbiter.
 */
final class SchedulingConstraints
{
    public function __construct(
        private readonly TeacherAuthority $teacherAuthority,
    ) {}

    public function assertSessionCanBeScheduled(
        ClassModel $class,
        CarbonImmutable $scheduledOn,
        string $startsAt,
        string $endsAt,
        ?string $skillId,
        ?string $roomId,
        ?string $sectionId,
    ): void {
        if ($class->lifecycle_state !== ClassLifecycle::STATE_ACTIVE) {
            throw BusinessRejection::forCode('scheduling.class_not_active', 'sessions are scheduled only on active classes');
        }
        /** @var AcademicPeriod|null $period */
        $period = AcademicPeriod::query()->whereKey($class->period_id)->first();
        if ($period === null || $period->lifecycle_state !== 'published') {
            throw BusinessRejection::forCode('scheduling.period_not_published', 'sessions require a published academic period');
        }
        if ($scheduledOn->toDateString() < $period->starts_on || $scheduledOn->toDateString() > $period->ends_on) {
            throw BusinessRejection::forCode('scheduling.date_outside_period', 'a session must fall within its academic period');
        }
        $start = DateTimeImmutable::createFromFormat('!H:i', $startsAt);
        $end = DateTimeImmutable::createFromFormat('!H:i', $endsAt);
        if ($start === false || $end === false || $start->format('H:i') !== $startsAt || $end->format('H:i') !== $endsAt) {
            throw BusinessRejection::forCode('scheduling.window_invalid', 'session times must use valid 24-hour HH:MM values');
        }
        if ($end <= $start) {
            throw BusinessRejection::forCode('scheduling.window_invalid', 'a session must end after it starts');
        }
        $classBranchId = trim((string) ($class->branch_id ?? ''));
        if ($classBranchId === '') {
            throw BusinessRejection::forCode('scheduling.class_branch_missing', 'a schedulable class requires branch provenance');
        }

        if ($skillId === null || $skillId === '') {
            throw BusinessRejection::forCode('scheduling.skill_required', 'new sessions require explicit subject or skill authority');
        }
        /** @var Skill|null $skill */
        $skill = Skill::query()->find($skillId);
        if ($skill === null || $skill->lifecycle_state !== Skill::STATE_ACTIVE) {
            throw BusinessRejection::forCode('scheduling.skill_unknown', 'a session may deliver only an active skill');
        }
        if ($sectionId !== null && $sectionId !== '') {
            /** @var ClassSection|null $section */
            $section = ClassSection::query()->whereKey($sectionId)->first();
            if ($section === null || $section->class_id !== $class->id) {
                throw BusinessRejection::forCode('scheduling.section_class_mismatch', 'a session section must belong to its class');
            }
            if ($section->lifecycle_state !== ClassSectionLifecycle::STATE_OPEN) {
                throw BusinessRejection::forCode('scheduling.section_not_open', 'a session may be scheduled only in an open section');
            }
        }
        if ($roomId !== null && $roomId !== '') {
            /** @var AcademicRoom|null $room */
            $room = AcademicRoom::query()->whereKey($roomId)->first();
            if ($room === null || $room->lifecycle_state !== 'available') {
                throw BusinessRejection::forCode('scheduling.room_not_available', 'a session may be scheduled only in an available room');
            }
            if (trim((string) ($room->branch_id ?? '')) !== $classBranchId) {
                throw BusinessRejection::forCode('scheduling.room_branch_mismatch', 'a session room must belong to the class branch');
            }
        }

        // Scheduling is not allowed to create an orphan delivery event. The
        // Teacher authority resolves the effective assignment, employment,
        // qualification, subject authority, leave, availability, and
        // timetable-conflict facts before the session becomes writable.
        // Lock every existing effective assignment row for the teacher(s)
        // attached to this class before performing the conflict read. This
        // serializes concurrent session scheduling for the same teacher and
        // closes the check-then-write race that a plain existence query leaves.
        $teacherPersonIds = TeacherAssignment::query()
            ->where('class_id', $class->id)
            ->where(fn ($state) => $state->whereNull('lifecycle_state')->orWhere('lifecycle_state', '!=', 'cancelled'))
            ->where('effective_from', '<=', $scheduledOn->toDateString())
            ->where(function ($query) use ($scheduledOn): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $scheduledOn->toDateString());
            })
            ->whereNotNull('teacher_person_id')
            ->pluck('teacher_person_id')
            ->map(static fn ($id): string => (string) $id)
            ->unique()
            ->values();
        if ($teacherPersonIds->isEmpty()) {
            throw BusinessRejection::forCode('academic.teacher_assignment_required', 'a session requires an effective teacher assignment');
        }

        TeacherAssignment::query()
            ->whereIn('teacher_person_id', $teacherPersonIds->all())
            ->where(fn ($state) => $state->whereNull('lifecycle_state')->orWhere('lifecycle_state', '!=', 'cancelled'))
            ->where('effective_from', '<=', $scheduledOn->toDateString())
            ->where(function ($query) use ($scheduledOn): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $scheduledOn->toDateString());
            })
            ->orderBy('teacher_person_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id', 'teacher_person_id', 'class_id']);

        $this->teacherAuthority->assertClassCanDeliver($class, $scheduledOn, $startsAt, $endsAt, $skillId);
    }
}