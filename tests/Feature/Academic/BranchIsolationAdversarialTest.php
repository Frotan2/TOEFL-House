<?php

declare(strict_types=1);

namespace Tests\Feature\Academic;

use App\Modules\Academic\Commands\DecideGraduation;
use App\Modules\Academic\Commands\DecideProgression;
use App\Modules\Academic\Commands\IssueTranscript;
use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainEnrollment;
use App\Modules\Academic\Commands\MaintainRoom;
use App\Modules\Academic\Commands\ManageAcademicAppeal;
use App\Modules\Academic\Commands\ManageAcademicOffering;
use App\Modules\Academic\Commands\ManageAssessmentResult;
use App\Modules\Academic\Commands\ManageClassWaitlist;
use App\Modules\Academic\Commands\RecordAttendance;
use App\Modules\Academic\Models\AcademicAppeal;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\AcademicRoom;
use App\Modules\Academic\Models\AssessmentAttempt;
use App\Modules\Academic\Models\AssessmentResult;
use App\Modules\Academic\Models\BranchAvailability;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\ClassSession;
use App\Modules\Academic\Models\ClassWaitlistEntry;
use App\Modules\Academic\Models\Enrollment;
use App\Modules\Academic\Models\Offering;
use App\Modules\Academic\Models\Program;
use App\Modules\Admissions\Commands\EnrollAdmittedApplicant;
use App\Modules\Admissions\Commands\RegisterApplicant;
use App\Modules\Admissions\Models\Applicant;
use App\Modules\Organization\Models\Branch;
use App\Modules\Students\Models\Student;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsSessions;
use Tests\Concerns\BuildsStudents;
use Tests\Concerns\BuildsTeachers;
use Tests\Concerns\DecidesAdmissions;
use Tests\TestCase;

/**
 * Adversarial branch-isolation coverage (WP-ACAD-SCOPE): an officer scoped
 * to branch A is refused on every delivery verb touching branch-B records
 * — input-branch verbs, stored-record verbs, seat queues, attendance,
 * assessment, progression, graduation, transcripts, and appeals — while
 * org-wide authority and same-branch authority keep working, and unknown
 * branches fail closed.
 */
final class BranchIsolationAdversarialTest extends TestCase
{
    use BuildsActors;
    use BuildsSessions;
    use BuildsStudents;
    use BuildsTeachers;
    use DecidesAdmissions;

    private const CAPS = [
        'academic.structure', 'academic.schedule', 'academic.enroll', 'academic.enroll_approve',
        'academic.attendance', 'academic.assess', 'academic.moderate', 'academic.approve_result',
        'academic.release', 'academic.progression_propose', 'academic.progression_review',
        'academic.progression_approve', 'academic.completion', 'academic.completion_approve',
        'academic.certify', 'academic.transcript_issue', 'academic.appeal_manage',
    ];

    private string $branchA;

    private string $branchB;

    private string $levelId;

    private string $level2Id;

    private string $periodId;

    private string $programVersionId;

    private string $availabilityB;

    private string $offeringA;

    private string $offeringB;

    private string $classId;

    private string $classB;

    private string $sessionId;

    private string $sessionBId;

    private string $studentFree;

    private string $studentA;

    private string $studentB;

    private string $studentWaitB;

    private string $seatA;

    private string $seatB;

    private string $fillerSeatC;

    private string $waitB;

    private string $releasedResultB;

    private string $moderatedResultA;

    protected function setUp(): void
    {
        parent::setUp();
        $structure = app(MaintainAcademicStructure::class);
        $org = $this->orgOfficer();

        $this->branchA = $this->newBranch('Isolation Branch A');
        $this->branchB = $this->newBranch('Isolation Branch B');

        $program = $structure->defineProgram($org, 'Isolation Intensive', 'iso-prog');
        $version = $structure->publishVersion($org, Program::query()->findOrFail($program['program_id']), 'Isolation v1', 'iso-ver');
        $this->programVersionId = $version['version_id'];
        $this->levelId = $structure->defineLevel($org, $this->programVersionId, 'iso-l1', 1, 'Iso One', 'A1', 'iso-lvl1')['level_id'];
        $this->level2Id = $structure->defineLevel($org, $this->programVersionId, 'iso-l2', 2, 'Iso Two', 'A2', 'iso-lvl2')['level_id'];

        $this->periodId = $structure->definePeriod($org, 'Isolation Term', CarbonImmutable::today()->subMonth(), CarbonImmutable::today()->addMonths(3), 'iso-period')['period_id'];
        $structure->transitionPeriod($org, AcademicPeriod::query()->findOrFail($this->periodId), 'published', 'iso-period-pub');

        $structure->declareBranchAvailability($org, $this->branchA, $this->levelId, $this->periodId, 'iso-avail-a');
        $this->availabilityB = $structure->declareBranchAvailability($org, $this->branchB, $this->levelId, $this->periodId, 'iso-avail-b')['availability_id'];
        $this->offeringA = $structure->openOffering($org, $this->branchA, $this->levelId, $this->periodId, 4, 'iso-off-a')['offering_id'];
        $this->offeringB = $structure->openOffering($org, $this->branchB, $this->levelId, $this->periodId, 4, 'iso-off-b')['offering_id'];

        $classes = app(MaintainClass::class);
        $this->classId = $classes->defineClass($org, $this->programVersionId, $this->periodId, 4, 'iso-class', $this->levelId, $this->branchA)['class_id'];
        $teacher = $this->buildActiveTeacher('iso-teacher-1', $this->branchA, 'branchisd71')['person_id'];
        $classes->assignTeacher($org, ClassModel::query()->findOrFail($this->classId), $teacher, CarbonImmutable::today(), null, 'iso-class-teacher');
        $classes->transition($org, ClassModel::query()->findOrFail($this->classId), 'published', 'iso-class-pub');
        $classes->transition($org, ClassModel::query()->findOrFail($this->classId), 'active', 'iso-class-active');
        // Scheduling needs an authorized, available teacher whose assignment
        // carries the session skill; the domain will not infer any of it.
        $isoSkillId = $this->makeClassSchedulable($org, $this->classId, $this->branchA, 'iso-sess');
        $this->sessionId = $classes->scheduleSession(
            $org, ClassModel::query()->findOrFail($this->classId), CarbonImmutable::today()->addWeek(), '09:00', '10:30', 'iso-session', $isoSkillId
        )['session_id'];

        // Branch-B mirror: a second class on the branch-B offering, so every
        // stored branch-B record (seat, queue, session, result, appeal) hangs
        // off branch-B provenance the way the domain now requires — a class
        // is bound to one open offering of its branch, level, and period.
        $this->classB = $classes->defineClass($org, $this->programVersionId, $this->periodId, 4, 'iso-class-b', $this->levelId, $this->branchB)['class_id'];
        $teacherB = $this->buildActiveTeacher('iso-teacher-2', $this->branchB, 'branchisd72')['person_id'];
        $classes->assignTeacher($org, ClassModel::query()->findOrFail($this->classB), $teacherB, CarbonImmutable::today(), null, 'iso-class-b-teacher');
        $classes->transition($org, ClassModel::query()->findOrFail($this->classB), 'published', 'iso-class-b-pub');
        $classes->transition($org, ClassModel::query()->findOrFail($this->classB), 'active', 'iso-class-b-active');
        $isoSkillB = $this->makeClassSchedulable($org, $this->classB, $this->branchB, 'iso-sess-b');
        $this->sessionBId = $classes->scheduleSession(
            $org, ClassModel::query()->findOrFail($this->classB), CarbonImmutable::today()->addWeek(), '11:00', '12:30', 'iso-session-b', $isoSkillB
        )['session_id'];

        $this->studentA = $this->newStudent('iso-student-a');
        $this->studentB = $this->newStudent('iso-student-b');
        $this->studentFree = $this->newStudent('iso-student-free');
        $this->studentWaitB = $this->newStudent('iso-student-wait-b');
        // Provenance seeding: student B belongs to branch B. The isolation
        // boundary is exercised against this stored provenance.
        $this->transferStudentHome($this->studentB, $this->branchB, 'hb3');

        $enroll = app(MaintainEnrollment::class);
        $this->seatA = $enroll->request($org, $this->studentA, $this->classId, 'iso-enr-a', $this->offeringA)['enrollment_id'];
        $enroll->activate($org, Enrollment::query()->findOrFail($this->seatA), 'iso-act-a');
        $this->seatB = $enroll->request($org, $this->studentB, $this->classB, 'iso-enr-b', $this->offeringB)['enrollment_id'];
        $enroll->activate($org, Enrollment::query()->findOrFail($this->seatB), 'iso-act-b');

        // Fill the branch-B offering so the queue can be exercised; the last
        // filler stays requested to exercise the activation gate as well.
        foreach (['iso-fill-1', 'iso-fill-2'] as $index => $personId) {
            $student = $this->newStudent($personId);
            $seat = $enroll->request($org, $student, $this->classB, 'iso-fill-enr-'.$index, $this->offeringB)['enrollment_id'];
            $enroll->activate($org, Enrollment::query()->findOrFail($seat), 'iso-fill-act-'.$index);
        }
        $fillerActive = $this->newStudent('iso-fill-3');
        $this->fillerSeatC = $enroll->request($org, $fillerActive, $this->classB, 'iso-fill-enr-3', $this->offeringB)['enrollment_id'];
        // The last filler intentionally stays *requested*: the branch-B class
        // is at full capacity (4 live claims), so the queue is joinable, and
        // the requested seat doubles as the activation-gate probe.

        $this->waitB = app(ManageClassWaitlist::class)->join($org, $this->studentWaitB, $this->classB, $this->offeringB, 'iso-wait-b')['entry_id'];

        // Released result on the branch-B seat: scorer/moderator/approver/
        // releaser stay independent.
        $results = app(ManageAssessmentResult::class);
        $attemptB = $results->submitAttempt($this->grantedActor('iso-scorer-b', ['academic.assess']), Enrollment::query()->findOrFail($this->seatB), 'assessment', 'scan/iso-b', 'iso-att-b');
        $resultB = $results->score($this->grantedActor('iso-scorer-b', ['academic.assess']), AssessmentAttempt::query()->findOrFail($attemptB['attempt_id']), '88.00', 'iso-score-b');
        $rowB = AssessmentResult::query()->findOrFail($resultB['result_id']);
        $results->moderate($this->grantedActor('iso-mod-b', ['academic.moderate']), $rowB, 'iso-mod-b');
        $results->approve($this->grantedActor('iso-appr-b', ['academic.approve_result']), $rowB, 'iso-appr-b');
        $results->release($this->grantedActor('iso-rel-b', ['academic.release']), $rowB, 'iso-rel-b');
        $this->releasedResultB = $rowB->id;

        // Moderated result on the branch-A seat for same-branch positives.
        $attemptA = $results->submitAttempt($this->grantedActor('iso-scorer-a', ['academic.assess']), Enrollment::query()->findOrFail($this->seatA), 'assessment', 'scan/iso-a', 'iso-att-a');
        $resultA = $results->score($this->grantedActor('iso-scorer-a', ['academic.assess']), AssessmentAttempt::query()->findOrFail($attemptA['attempt_id']), '81.00', 'iso-score-a');
        $rowA = AssessmentResult::query()->findOrFail($resultA['result_id']);
        $results->moderate($this->grantedActor('iso-mod-a', ['academic.moderate']), $rowA, 'iso-mod-a');
        $results->approve($this->grantedActor('iso-appr-a', ['academic.approve_result']), $rowA, 'iso-appr-a');
        $results->release($this->grantedActor('iso-rel-a', ['academic.release']), $rowA, 'iso-rel-a');
        $this->moderatedResultA = $rowA->id;
    }

    private function orgOfficer(): Actor
    {
        return $this->grantedActor('iso-org', self::CAPS);
    }

    private function branchOfficer(string $actorId, string $branchId): Actor
    {
        $this->personWithAuthority($actorId, []);
        $this->grantScopeAuthority($actorId, self::CAPS, 'branch', $branchId);

        return new Actor($actorId, 'Branch Officer');
    }

    private function newBranch(string $name): string
    {
        $id = Branch::query()->create([
            'id' => RandomIdentifier::new(),
            'name' => $name.' '.substr(md5(RandomIdentifier::new()), 0, 8),
            'lifecycle_state' => 'active',
        ])->id;
        $this->attachBranchToBootstrapOrganization($id);

        return $id;
    }

    private function newStudent(string $personId): string
    {
        $this->personWithAuthority($personId, []);
        $registered = app(RegisterApplicant::class)->register($this->admissionsClerk('iso-clerk-'.$personId), $personId, 'Program', 'iso-reg-'.$personId, null, $this->bootstrapBranchId());
        $applicant = Applicant::query()->findOrFail($registered['applicant_id']);
        $this->runAdmissionDecision(
            $this->admissionsClerk('iso-clerk-'.$personId),
            $this->admissionsReviewer('iso-review-'.$personId),
            $this->admissionsApprover('iso-approve-'.$personId),
            $applicant,
            true,
            'meets policy',
            'ev/iso-'.$personId,
            'iso-adm-'.$personId,
        );

        return app(EnrollAdmittedApplicant::class)->convert($this->admissionsApprover('iso-approve-'.$personId), $applicant, 'iso-conv-'.$personId)['student_id'];
    }

    public function test_branch_officer_cannot_declare_availability_or_open_offerings_in_a_foreign_branch(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-1', $this->branchA);

        try {
            app(MaintainAcademicStructure::class)->declareBranchAvailability($officerA, $this->branchB, $this->levelId, $this->periodId, 'iso-x-avail');
            $this->fail('a branch-A officer must not declare availability for branch B');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }

        try {
            app(MaintainAcademicStructure::class)->openOffering($officerA, $this->branchB, $this->levelId, $this->periodId, 2, 'iso-x-off');
            $this->fail('a branch-A officer must not open an offering for branch B');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }

        // Unknown branches fail closed instead of resolving to a null scope.
        // Branch identity is owned by the Organization module: the shared
        // access chokepoint rejects a nonexistent branch id before any scope
        // decision (organization.branch_unknown).
        try {
            app(MaintainAcademicStructure::class)->declareBranchAvailability($officerA, RandomIdentifier::new(), $this->levelId, $this->periodId, 'iso-x-unknown');
            $this->fail('an unknown branch must fail closed');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('organization.branch_unknown', $rejection->errorCode());
        }

        // Positive control: the same officer declares availability at home.
        $avail = app(MaintainAcademicStructure::class)->declareBranchAvailability($officerA, $this->branchA, $this->level2Id, $this->periodId, 'iso-home-avail');
        $this->assertDatabaseHas('branch_availabilities', ['id' => $avail['availability_id'], 'branch_id' => $this->branchA]);
    }

    public function test_branch_officer_cannot_mutate_foreign_stored_offerings_and_rooms(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-2', $this->branchA);
        $offerings = app(ManageAcademicOffering::class);

        foreach ([
            fn () => $offerings->closeAvailability($officerA, BranchAvailability::query()->findOrFail($this->availabilityB), 'iso-x-close-avail'),
            fn () => $offerings->closeOffering($officerA, Offering::query()->findOrFail($this->offeringB), 'iso-x-close-off'),
            fn () => $offerings->cancelOffering($officerA, Offering::query()->findOrFail($this->offeringB), 'iso-x-cancel-off'),
            fn () => $offerings->resizeCapacity($officerA, Offering::query()->findOrFail($this->offeringB), 9, 'iso-x-resize-off'),
        ] as $index => $attempt) {
            try {
                $attempt();
                $this->fail('foreign stored-record mutation #'.$index.' must be refused');
            } catch (AuthorizationDenied $denial) {
                $this->assertSame('academic.structure_denied', $denial->errorCode());
            }
        }

        $org = $this->orgOfficer();
        $roomB = app(MaintainRoom::class)->defineRoom($org, $this->branchB, 'Iso Room B', 'ISO-RB', 20, 'classroom', 'iso-room-b')['room_id'];
        $roomA = app(MaintainRoom::class)->defineRoom($org, $this->branchA, 'Iso Room A', 'ISO-RA', 20, 'classroom', 'iso-room-a')['room_id'];

        try {
            app(MaintainRoom::class)->defineRoom($officerA, $this->branchB, 'Iso Room X', 'ISO-RX', 10, 'classroom', 'iso-room-x');
            $this->fail('a branch-A officer must not define rooms for branch B');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }
        try {
            app(MaintainRoom::class)->transition($officerA, AcademicRoom::query()->findOrFail($roomB), 'maintenance', 'iso-x-room-tr');
            $this->fail('a branch-A officer must not transition a branch-B room');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }
        try {
            app(MaintainRoom::class)->resize($officerA, AcademicRoom::query()->findOrFail($roomB), 5, 'iso-x-room-rs');
            $this->fail('a branch-A officer must not resize a branch-B room');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }

        // Positive control: the same officer resizes the home-branch room.
        app(MaintainRoom::class)->resize($officerA, AcademicRoom::query()->findOrFail($roomA), 25, 'iso-home-room-rs');
        $this->assertDatabaseHas('academic_rooms', ['id' => $roomA, 'capacity' => 25]);
    }

    public function test_enrollment_seat_verbs_are_branch_scoped(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-3', $this->branchA);
        $enroll = app(MaintainEnrollment::class);

        try {
            $enroll->request($officerA, $this->studentFree, $this->classB, 'iso-x-enr', $this->offeringB);
            $this->fail('a branch-A officer must not request seats in a branch-B offering');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.enrollment_denied', $denial->errorCode());
        }
        // The requested branch-B seat carries branch-B provenance and the
        // activation gate follows it there: an org-wide requester seated the
        // student, but only a branch-B (or org-wide) officer may activate.
        $this->assertDatabaseHas('enrollments', ['id' => $this->fillerSeatC, 'originating_branch_id' => $this->branchB]);
        try {
            $enroll->activate($officerA, Enrollment::query()->findOrFail($this->fillerSeatC), 'iso-x-activate-foreign');
            $this->fail('a branch-A officer must not activate a branch-B seat');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.enrollment_denied', $denial->errorCode());
        }
        try {
            $enroll->freeze($officerA, Enrollment::query()->findOrFail($this->seatB), 'probe', 'iso-x-freeze');
            $this->fail('a branch-A officer must not freeze a branch-B seat');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.enrollment_denied', $denial->errorCode());
        }
        try {
            $enroll->withdraw($officerA, Enrollment::query()->findOrFail($this->seatB), 'probe', 'iso-x-withdraw');
            $this->fail('a branch-A officer must not withdraw a branch-B seat');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.enrollment_denied', $denial->errorCode());
        }

        // A transfer into a foreign class is refused even though the seat
        // itself is at home: both ends are enforced.
        try {
            $enroll->transfer($officerA, Enrollment::query()->findOrFail($this->seatA), $this->classB, 'iso-x-transfer', $this->offeringB);
            $this->fail('a branch-A officer must not transfer a seat into branch B');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.enrollment_denied', $denial->errorCode());
        }

        // Positive controls: request and freeze at home.
        $home = $enroll->request($officerA, $this->studentFree, $this->classId, 'iso-home-enr', $this->offeringA);
        $enroll->activate($officerA, Enrollment::query()->findOrFail($home['enrollment_id']), 'iso-home-act');
        $this->assertDatabaseHas('enrollments', ['id' => $home['enrollment_id'], 'lifecycle_state' => 'active', 'originating_branch_id' => $this->branchA]);
    }

    public function test_waitlist_and_attendance_are_branch_scoped(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-4', $this->branchA);

        try {
            app(ManageClassWaitlist::class)->join($officerA, $this->studentFree, $this->classB, $this->offeringB, 'iso-x-wait');
            $this->fail('a branch-A officer must not queue students on a branch-B offering');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.waitlist_denied', $denial->errorCode());
        }
        try {
            app(ManageClassWaitlist::class)->promote($officerA, ClassWaitlistEntry::query()->findOrFail($this->waitB), 'iso-x-promote');
            $this->fail('a branch-A officer must not promote a branch-B queue entry');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.waitlist_denied', $denial->errorCode());
        }
        try {
            app(RecordAttendance::class)->record($officerA, ClassSession::query()->findOrFail($this->sessionBId), Enrollment::query()->findOrFail($this->seatB), 'present', 'iso-x-att');
            $this->fail('a branch-A officer must not record attendance on a branch-B seat');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.attendance_denied', $denial->errorCode());
        }

        // Positive control: attendance on the home seat records.
        app(RecordAttendance::class)->record($officerA, ClassSession::query()->findOrFail($this->sessionId), Enrollment::query()->findOrFail($this->seatA), 'present', 'iso-home-att');
        $this->assertDatabaseHas('attendance_facts', ['enrollment_id' => $this->seatA, 'status' => 'present']);
    }

    public function test_assessment_verbs_are_branch_scoped(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-5', $this->branchA);
        $results = app(ManageAssessmentResult::class);

        try {
            $results->submitAttempt($officerA, Enrollment::query()->findOrFail($this->seatB), 'assessment', 'scan/iso-x', 'iso-x-att2');
            $this->fail('a branch-A officer must not assess a branch-B seat');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.assess_denied', $denial->errorCode());
        }
        try {
            $results->markAppealed($officerA, AssessmentResult::query()->findOrFail($this->releasedResultB), 'iso-x-mark');
            $this->fail('a branch-A officer must not mark a branch-B result appealed');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.result_denied', $denial->errorCode());
        }
        try {
            $results->proposeCorrection($officerA, AssessmentResult::query()->findOrFail($this->releasedResultB), '89.00', 'probe', 'iso-x-cor');
            $this->fail('a branch-A officer must not correct a branch-B result');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.moderate_denied', $denial->errorCode());
        }

        // Positive control: the home result can be marked appealed.
        $results->markAppealed($officerA, AssessmentResult::query()->findOrFail($this->moderatedResultA), 'iso-home-mark');
        $this->assertDatabaseHas('assessment_results', ['id' => $this->moderatedResultA, 'lifecycle_state' => 'appealed']);
    }

    public function test_student_anchored_verbs_follow_the_branched_student(): void
    {
        $officerA = $this->branchOfficer('iso-off-a-6', $this->branchA);

        // Progression is anchored to the delivery class: the branch-B class
        // is branch-B business regardless of the student's home branch.
        try {
            app(DecideProgression::class)->propose($officerA, $this->studentB, $this->classB, 'advance', 'probe', 'iso-x-prog');
            $this->fail('a branch-A officer must not propose progression on a branch-B class');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.progression_denied', $denial->errorCode());
        }
        try {
            app(DecideGraduation::class)->propose($officerA, $this->studentB, $this->programVersionId, 'eligible', 'probe basis', 'iso-x-grad');
            $this->fail('a branch-A officer must not propose graduation for a branch-B student');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.graduation_denied', $denial->errorCode());
        }
        try {
            app(IssueTranscript::class)->issue($officerA, $this->studentB, $this->programVersionId, 'iso-x-tr');
            $this->fail('a branch-A officer must not issue transcripts for a branch-B student');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.transcript_denied', $denial->errorCode());
        }

        // Positive control: the branch-B officer proposes progression there.
        $officerB = $this->branchOfficer('iso-off-b-6', $this->branchB);
        $decision = app(DecideProgression::class)->propose($officerB, $this->studentB, $this->classB, 'advance', 'meets the boundary rules', 'iso-home-prog', $this->releasedResultB, 'released result review confirms readiness');
        $this->assertDatabaseHas('progression_decisions', ['id' => $decision['decision_id'], 'student_id' => $this->studentB]);
    }

    public function test_appeal_lifecycle_is_branch_scoped(): void
    {
        $appeals = app(ManageAcademicAppeal::class);
        $org = $this->orgOfficer();
        $officerA = $this->branchOfficer('iso-off-a-7', $this->branchA);

        try {
            $appeals->file($officerA, $this->studentB, 'assessment_result', $this->releasedResultB, 'probe', 'iso-x-file');
            $this->fail('a branch-A officer must not file appeals on branch-B subjects');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.appeal_denied', $denial->errorCode());
        }

        $this->branchOfficer('iso-reviewer-b-7', $this->branchB);
        // One open appeal per subject (academic_appeals_one_open_subject), so
        // every further appeal on the same result waits until this one leaves
        // the open set — rejection releases the slot.
        $appeal = $appeals->file($org, $this->studentB, 'assessment_result', $this->releasedResultB, 'section two was mis-marked', 'iso-file-b');

        // A branch-A officer cannot assign a branch-B appeal (open → legal
        // transition, refused on the subject's branch scope).
        try {
            $appeals->assign($officerA, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'iso-reviewer-b-7', 'iso-x-assign');
            $this->fail('a branch-A officer must not assign a branch-B appeal');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.appeal_denied', $denial->errorCode());
        }

        // Same-branch lifecycle keeps working: the branch-B reviewer is
        // assigned, investigates, and rejects the appeal on the merits.
        $appeals->assign($org, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'iso-reviewer-b-7', 'iso-assign-b');
        $reviewerB = $this->grantedActor('iso-reviewer-b-7', []);

        // From assigned onward the same branch gate holds for investigate and
        // decide: the appeals are branch-B business at every state.
        try {
            $appeals->investigate($officerA, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'iso-x-investigate');
            $this->fail('a branch-A officer must not investigate a branch-B appeal');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.appeal_denied', $denial->errorCode());
        }
        $appeals->investigate($reviewerB, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'iso-investigate-b');

        try {
            $appeals->reject($officerA, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'no merit', 'answer-sheet review', 'iso-x-reject');
            $this->fail('a branch-A officer must not decide a branch-B appeal');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.appeal_denied', $denial->errorCode());
        }
        $appeals->reject($reviewerB, AcademicAppeal::query()->findOrFail($appeal['appeal_id']), 'no merit', 'answer-sheet review', 'iso-reject-b');
        $this->assertDatabaseHas('academic_appeals', ['id' => $appeal['appeal_id'], 'lifecycle_state' => 'rejected']);

        // Positive control: a branch-B officer files on a branch-B subject.
        $officerB = $this->branchOfficer('iso-off-b-7', $this->branchB);
        $home = $appeals->file($officerB, $this->studentB, 'assessment_result', $this->releasedResultB, 'home-branch filing', 'iso-home-file');
        $this->assertDatabaseHas('academic_appeals', ['id' => $home['appeal_id'], 'student_id' => $this->studentB, 'lifecycle_state' => 'open']);
    }

    public function test_org_wide_authority_still_covers_every_branch(): void
    {
        $org = $this->orgOfficer();

        // Non-destructive sweep across layers on branch-B records.
        app(ManageAcademicOffering::class)->resizeCapacity($org, Offering::query()->findOrFail($this->offeringB), 6, 'iso-org-resize');
        app(MaintainEnrollment::class)->freeze($org, Enrollment::query()->findOrFail($this->seatB), 'org hold', 'iso-org-freeze');
        app(MaintainEnrollment::class)->unfreeze($org, Enrollment::query()->findOrFail($this->seatB), 'iso-org-unfreeze');
        app(RecordAttendance::class)->record($org, ClassSession::query()->findOrFail($this->sessionBId), Enrollment::query()->findOrFail($this->seatB), 'present', 'iso-org-att');

        $this->assertDatabaseHas('offerings', ['id' => $this->offeringB, 'capacity' => 6]);
        $this->assertDatabaseHas('enrollments', ['id' => $this->seatB, 'lifecycle_state' => 'active']);
        $this->assertDatabaseHas('attendance_facts', ['enrollment_id' => $this->seatB, 'status' => 'present']);
    }

    public function test_governance_verbs_stay_capability_gated_by_design(): void
    {
        // Governance rows (curriculum terms) are branchless by design: they
        // carry no branch anywhere, and the single access authority gates
        // them on an ORGANIZATION-WIDE capability grant (WP-ACAD-SCOPE
        // governance tier; org-wide structure grants go through the staged
        // org-wide approval chain). A branch-narrow academic.structure grant
        // must therefore not perform org-global governance, and governance
        // verbs must never resolve to a foreign-branch scope.
        $officerA = $this->branchOfficer('iso-off-a-9', $this->branchA);
        try {
            app(MaintainAcademicStructure::class)->definePeriod(
                $officerA, 'Isolation Governance Term', new CarbonImmutable('2027-01-01'), new CarbonImmutable('2027-03-30'), 'iso-gov-period'
            );
            $this->fail('a branch-narrow structure grant must not perform org-global governance');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.structure_denied', $denial->errorCode());
        }

        // Positive control: org-wide authority performs the branchless
        // governance verb; no branch scoping applies to the record.
        $period = app(MaintainAcademicStructure::class)->definePeriod(
            $this->orgOfficer(), 'Isolation Governance Term', new CarbonImmutable('2027-01-01'), new CarbonImmutable('2027-03-30'), 'iso-gov-period'
        );
        $this->assertDatabaseHas('academic_periods', ['id' => $period['period_id']]);
    }
}
