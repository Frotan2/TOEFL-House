<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\ClassModel;
use App\Support\Authorization\Actor;
use Carbon\CarbonImmutable;

/**
 * Canonical delivery fixture: an *active* class a student can actually enrol
 * into.
 *
 * A class only reaches `active` after the whole chain is satisfied:
 *
 *     open offering  (BuildsAcademicStructure)
 *   + published period
 *   + an active canonical teacher assignment  (BuildsTeachers)
 *   + planned -> published -> active           (no shortcuts)
 *
 * Dozens of legacy tests re-derived fragments of this chain and were rejected
 * partway through. It is expressed once here so a test can state what it is
 * actually about.
 */
trait BuildsEnrollments
{
    /**
     * Builds an active class with a teacher, ready to accept enrollments.
     *
     * @return array{
     *     branch_id: string,
     *     program_version_id: string,
     *     period_id: string,
     *     offering_id: string,
     *     class_id: string,
     *     teacher_person_id: string
     * }
     */
    private function buildActiveClass(
        Actor $officer,
        string $keyPrefix,
        int $classCapacity = 2,
        int $offeringCapacity = 25,
    ): array {
        $chain = $this->buildAcademicChain($officer, $keyPrefix, $offeringCapacity);

        $teacherPersonId = $keyPrefix.'-teacher';
        $this->buildActiveTeacher($teacherPersonId, $chain['branch_id'], $keyPrefix);

        $class = app(MaintainClass::class)->defineClass(
            $officer,
            $chain['program_version_id'],
            $chain['period_id'],
            $classCapacity,
            $keyPrefix.'-class',
        );
        $classId = $class['class_id'];

        // An active class requires a canonical effective teacher assignment,
        // and the assignment must sit inside the class academic period.
        $periodStart = new CarbonImmutable(
            (string) AcademicPeriod::query()->findOrFail($chain['period_id'])->starts_on
        );
        app(MaintainClass::class)->assignTeacher(
            $officer,
            ClassModel::query()->findOrFail($classId),
            $teacherPersonId,
            $periodStart,
            null,
            $keyPrefix.'-assign-teacher',
        );

        // planned -> published -> active. The lifecycle guard forbids skipping.
        app(MaintainClass::class)->transition(
            $officer,
            ClassModel::query()->findOrFail($classId),
            'published',
            $keyPrefix.'-publish',
        );
        app(MaintainClass::class)->transition(
            $officer,
            ClassModel::query()->findOrFail($classId),
            'active',
            $keyPrefix.'-activate',
        );

        return $chain + [
            'class_id' => $classId,
            'teacher_person_id' => $teacherPersonId,
        ];
    }

    /**
     * Requests a seat for a student on a class. Activation is a separate,
     * independently authorized decision and is deliberately not bundled here.
     *
     * @return array{enrollment_id: string, correlation_id: string}
     */
    private function requestSeat(Actor $requester, string $studentId, string $classId, string $key, ?string $offeringId = null): array
    {
        return app(MaintainEnrollment::class)->request($requester, $studentId, $classId, $key, $offeringId);
    }
}
