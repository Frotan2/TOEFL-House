<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainSkill;
use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Academic\Models\TeacherAssignment;
use App\Modules\Academic\Models\TeacherAvailability;
use App\Modules\Academic\Models\TeacherProfile;
use App\Modules\Academic\Models\TeacherSkillAuthority;
use App\Support\Authorization\Actor;
use Carbon\CarbonImmutable;

/**
 * Makes a class able to hold a session.
 *
 * Scheduling checks a chain the domain deliberately will not infer:
 *
 *   - the skill is authorized for the teacher IN THAT BRANCH (approval-side),
 *   - the teacher is available on the session weekday (management-side),
 *   - the skill is attributed to the class's effective teacher assignment.
 *
 * Tests that skipped it were refused with "new sessions require explicit
 * subject or skill authority" or "no assigned teacher is qualified, available,
 * and operational for this session". Everything here runs through the
 * production commands, so the fixture cannot drift from the rules it satisfies.
 */
trait BuildsSessions
{
    /**
     * Registers a skill.
     *
     * Skill registration is branchless governance and needs an
     * organization-wide `academic.skill` grant, which a branch-scoped academic
     * officer does not carry.
     */
    private function registerSkill(string $key): string
    {
        $registrar = $this->grantedActor('sess-skill-reg', ['academic.skill']);

        return app(MaintainSkill::class)
            ->register($registrar, $key, ucfirst(str_replace('-', ' ', $key)), $key.'-sk')['skill_id'];
    }

    /** Authorizes a teacher for a skill and makes them available all week. */
    private function makeTeacherDeliveryReady(
        string $teacherProfileId,
        string $skillId,
        string $branchId,
        string $keyPrefix
    ): void {
        // authorizeSkill is approval-side; declareAvailability is management-side.
        $approver = $this->grantedActor($keyPrefix.'-a', ['academic.teacher_approve']);
        $manager = $this->grantedActor($keyPrefix.'-m', ['academic.teacher_manage']);
        $profiles = app(MaintainTeacherProfile::class);
        $from = CarbonImmutable::today()->subYear()->toDateString();

        // Subject authorities may not overlap, so authorize once.
        $authorized = TeacherSkillAuthority::query()
            ->where('teacher_profile_id', $teacherProfileId)
            ->where('skill_id', $skillId)
            ->where('branch_id', $branchId)
            ->exists();

        if (! $authorized) {
            $profiles->authorizeSkill(
                $approver,
                TeacherProfile::query()->findOrFail($teacherProfileId),
                $skillId,
                $branchId,
                'teach',
                $from,
                null,
                'evidence/'.$keyPrefix.'/skill',
                $keyPrefix.'-auth'
            );
        }

        // Availability is per teacher/branch/weekday, NOT per skill: declaring
        // it again for a second skill would overlap an existing window and be
        // rejected. Skip weekdays that are already covered.
        $covered = TeacherAvailability::query()
            ->where('teacher_profile_id', $teacherProfileId)
            ->where('branch_id', $branchId)
            ->where('lifecycle_state', 'active')
            ->pluck('weekday')->map(static fn ($w) => (int) $w)->all();

        // Weekdays are 1-7 and the only valid kind is 'available'.
        foreach (array_diff(range(1, 7), $covered) as $weekday) {
            $profiles->declareAvailability(
                $manager,
                TeacherProfile::query()->findOrFail($teacherProfileId),
                $branchId,
                $weekday,
                '00:00',
                '23:59',
                $from,
                null,
                'available',
                $keyPrefix.'-av'.$weekday
            );
        }
    }

    /** Attributes a skill to the class's effective teacher assignment. */
    private function attributeSkill(string $classId, string $skillId, string $key): void
    {
        $assignment = TeacherAssignment::query()
            ->where('class_id', $classId)
            ->whereNull('effective_to')
            ->firstOrFail();

        // Attribution is a teacher-management action, and the scheduling
        // officer does not necessarily hold that capability.
        $manager = $this->grantedActor($key.'-mgr', ['academic.teacher_manage', 'academic.schedule']);

        app(MaintainClass::class)->assignSkill($manager, $assignment, $skillId, $key);
    }

    /**
     * Full chain: register a skill, make the class's teacher able to deliver
     * it, and attribute it to the assignment. Returns the skill id.
     */
    private function makeClassSchedulable(Actor $officer, string $classId, string $branchId, string $keyPrefix): string
    {
        $tag = substr($keyPrefix, 0, 6).substr(md5($keyPrefix), 0, 4);
        $skillId = $this->registerSkill($tag);

        $profileId = TeacherProfile::query()
            ->whereIn('person_id', TeacherAssignment::query()->where('class_id', $classId)->select('teacher_person_id'))
            ->value('id');

        if ($profileId !== null) {
            $this->makeTeacherDeliveryReady((string) $profileId, $skillId, $branchId, $tag);
            $this->attributeSkill($classId, $skillId, $tag.'-at');
        }

        return $skillId;
    }
}
