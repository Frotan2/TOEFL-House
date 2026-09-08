<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\Program;
use App\Modules\Academic\Queries\GradesheetQuery;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\BuildsTeachers;
use Tests\TestCase;

/**
 * AC15: the teacher-assignment lifecycle over the employee console —
 * end on an explicit date with reason, extend a dated assignment,
 * hand over to a successor in one audited step — plus the decided
 * post-end read tier (in-term viewing continues, post-term denied)
 * and governed refusals. The assessment/attendance/class authorities
 * are untouched: the viewer rule never grants mutation authority.
 *
 * The gradesheet page is part of the React Classes workspace (the GET
 * route is a shell redirect), so the read tier is verified against the
 * certified GradesheetQuery with the same production actors and its
 * denial audit — the viewer law itself, not a Blade label.
 */
final class TeacherAssignmentLifecycleConsoleTest extends TestCase
{
    use BuildsActors;
    use BuildsTeachers;

    private string $versionId;

    private string $periodId;

    private string $pastPeriodId;

    private string $pastPeriodStart;

    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchId = $this->bootstrapBranchId();
        $officer = $this->academicOfficer('tal-officer-setup');
        $structure = app(MaintainAcademicStructure::class);
        $program = $structure->defineProgram($officer, 'IELTS Preparation', 'tal-prog');
        $version = $structure->publishVersion($officer, Program::query()->findOrFail($program['program_id']), 'assignment rules', 'tal-ver');
        $this->versionId = $version['version_id'];
        // Every class references an open offering for its branch, level, and
        // period (academic.class_offering_required); the ended-term arc needs
        // its own period and offering, and its end must lie before today so
        // the post-term read denial is reachable without time travel.
        $level = $structure->defineLevel($officer, $this->versionId, 'tal-foundation', 1, 'Foundation', 'A2', 'tal-lvl');
        $period = $structure->definePeriod($officer, 'Fall 2026', new CarbonImmutable('2026-09-01'), new CarbonImmutable('2026-12-18'), 'tal-period');
        $structure->transitionPeriod($officer, AcademicPeriod::query()->findOrFail($period['period_id']), 'published', 'tal-period-pub');
        $this->periodId = $period['period_id'];
        $past = $structure->definePeriod($officer, 'Spring Term', new CarbonImmutable('2026-01-05'), new CarbonImmutable('2026-06-30'), 'tal-past');
        $structure->transitionPeriod($officer, AcademicPeriod::query()->findOrFail($past['period_id']), 'published', 'tal-past-pub');
        $this->pastPeriodId = $past['period_id'];
        $this->pastPeriodStart = '2026-01-05';
        $structure->declareBranchAvailability($officer, $this->branchId, $level['level_id'], $this->periodId, 'tal-avail');
        $structure->openOffering($officer, $this->branchId, $level['level_id'], $this->periodId, 4, 'tal-offering');
        $structure->declareBranchAvailability($officer, $this->branchId, $level['level_id'], $this->pastPeriodId, 'tal-avail-past');
        $structure->openOffering($officer, $this->branchId, $level['level_id'], $this->pastPeriodId, 4, 'tal-offering-past');
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{0: Person, 1: UserAccount}
     */
    private function makeEmployee(string $personId, array $capabilities, string $username): array
    {
        $person = $this->personWithAuthority($personId, $capabilities);
        $account = UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $person->id,
            'username' => $username,
            'password_hash' => Hash::make('tal-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        return [$person, $account];
    }

    private function signIn(string $username): void
    {
        $this->post('/login', ['username' => $username, 'password' => 'tal-password-1'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    private function signOut(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function prefix(): string
    {
        return DB::connection()->getTablePrefix();
    }

    /**
     * The read tier of the assignment law: an actor either opens the class
     * gradesheet through the certified query or is refused with the governed
     * denial code and a denial audit row.
     */
    private function assertGradesheetReadable(string $personId, string $classId): array
    {
        $gradesheet = app(GradesheetQuery::class)->forClass($this->teacherActor($personId), ClassModel::query()->findOrFail($classId));

        return $gradesheet['teachers'];
    }

    private function assertGradesheetDenied(string $personId, string $classId): void
    {
        try {
            app(GradesheetQuery::class)->forClass($this->teacherActor($personId), ClassModel::query()->findOrFail($classId));
            $this->fail('expected the gradesheet viewer rule to deny '.$personId);
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('academic.gradesheet_denied', $denial->errorCode());
        }
        $this->assertDatabaseHas($this->prefix().'audit_events', [
            'actor_id' => $personId,
            'operation' => 'academic.gradesheet.view.denied',
            'target_type' => 'class',
            'target_id' => $classId,
        ]);
    }

    /** @return array{0: list<string>, 1: list<string>} */
    private function teacherRowsByPerson(array $teachers, string $personId): array
    {
        $ids = [];
        $dates = [];
        foreach ($teachers as $row) {
            // Branch/person provenance columns are fixed-width char; the
            // driver returns them space-padded, so compare trimmed.
            if (trim((string) $row['teacher_person_id']) === $personId) {
                $ids[] = trim((string) $row['assignment_id']);
                $dates[] = ['from' => trim((string) $row['effective_from']), 'to' => $row['effective_to'] !== null ? trim((string) $row['effective_to']) : null];
            }
        }

        return [$ids, $dates];
    }

    /**
     * Define a class over HTTP and drive it to active with the given
     * teacher, returning class and assignment ids.
     *
     * @return array{class_id: string, assignment_id: string}
     */
    private function activeClass(string $suffix, string $teacherPersonId, string $periodId, string $effectiveFrom = '2026-09-01'): array
    {
        $knownIds = DB::table($this->prefix().'classes')->pluck('id')->all();
        $this->post('/academic/classes', [
            'program_version_id' => $this->versionId,
            'period_id' => $periodId,
            'capacity' => 2,
            'branch_id' => $this->branchId,
        ])->assertRedirect('/academic');
        /** @var string $classId */
        $classId = DB::table($this->prefix().'classes')->whereNotIn('id', $knownIds)->value('id');
        $this->assertNotNull($classId);

        $this->buildActiveTeacher($teacherPersonId, $this->branchId, $teacherPersonId);
        $knownAssignments = DB::table($this->prefix().'teacher_assignments')->pluck('id')->all();
        $this->post('/academic/teacher-assignments', [
            'class_id' => $classId,
            'teacher_person_id' => $teacherPersonId,
            'effective_from' => $effectiveFrom,
        ])->assertRedirect('/academic');
        /** @var string $assignmentId */
        $assignmentId = DB::table($this->prefix().'teacher_assignments')->whereNotIn('id', $knownAssignments)->value('id');
        $this->assertNotNull($assignmentId);

        $this->post('/academic/classes/'.$classId.'/transition', ['to_state' => 'published'])->assertRedirect('/academic');
        $this->post('/academic/classes/'.$classId.'/transition', ['to_state' => 'active'])->assertRedirect('/academic');

        return ['class_id' => $classId, 'assignment_id' => $assignmentId];
    }

    public function test_end_extend_and_handover_with_read_continuity(): void
    {
        $this->makeEmployee('tal-mgmt-1', ['academic.schedule'], 'assignment-manager');

        $this->signIn('assignment-manager');
        $setup = $this->activeClass('arc', 'tal-teacher-a1', $this->periodId);
        $classId = $setup['class_id'];
        $assignmentId = $setup['assignment_id'];
        $this->signOut();

        // The open-assigned teacher opens the gradesheet by identity.
        $this->assertGradesheetReadable('tal-teacher-a1', $classId);

        // Handover ends the open row and opens the successor in one
        // audited step; the audit links both rows. The class stays
        // active throughout.
        $this->buildActiveTeacher('tal-teacher-b1', $this->branchId, 'tal-teacher-b1');
        $this->signIn('assignment-manager');
        $this->post('/academic/teacher-assignments/'.$assignmentId.'/handover', [
            'successor_teacher_person_id' => 'tal-teacher-b1',
            'handover_on' => '2026-09-05',
            'reason' => 'handover approved by academic management',
        ])->assertRedirect('/academic');
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $assignmentId, 'effective_to' => '2026-09-05',
        ]);
        $successorId = DB::table($this->prefix().'teacher_assignments')
            ->where('class_id', $classId)->where('teacher_person_id', 'tal-teacher-b1')->value('id');
        $this->assertNotNull($successorId);
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $successorId, 'effective_from' => '2026-09-05', 'effective_to' => null,
        ]);
        $this->assertDatabaseHas($this->prefix().'audit_events', [
            'operation' => 'academic.teacher.handover',
            'target_type' => 'teacher_assignment',
            'target_id' => $successorId,
        ]);
        $this->assertDatabaseHas($this->prefix().'classes', ['id' => $classId, 'lifecycle_state' => 'active']);
        $this->signOut();

        // Ended but in-term, the outgoing teacher keeps read access;
        // the open successor reads by identity. The gradesheet shows
        // the handover lineage: the outgoing row is dated, the
        // successor row is open.
        $this->assertGradesheetReadable('tal-teacher-b1', $classId);
        [$outgoingIds, $outgoingDates] = $this->teacherRowsByPerson($this->assertGradesheetReadable('tal-teacher-a1', $classId), 'tal-teacher-a1');
        $this->assertSame([$assignmentId], $outgoingIds);
        $this->assertContains(['from' => '2026-09-01', 'to' => '2026-09-05'], $outgoingDates);

        // Management ends the successor outright on an explicit date
        // with a reason; the row stays as dated history.
        $this->signIn('assignment-manager');
        $this->post('/academic/teacher-assignments/'.$successorId.'/end', [
            'effective_to' => '2026-09-10',
            'reason' => 'cover finished',
        ])->assertRedirect('/academic');
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $successorId, 'effective_to' => '2026-09-10', 'lifecycle_state' => 'ended',
        ]);

        // A dated assignment that is still open (a fixed-term cover)
        // is extended with a new reason: the end date moves later.
        $this->buildActiveTeacher('tal-teacher-c1', $this->branchId, 'tal-teacher-c1');
        $knownAssignments = DB::table($this->prefix().'teacher_assignments')->pluck('id')->all();
        $this->post('/academic/teacher-assignments', [
            'class_id' => $classId,
            'teacher_person_id' => 'tal-teacher-c1',
            'effective_from' => '2026-09-10',
            'effective_to' => '2026-09-15',
        ])->assertRedirect('/academic');
        $coverId = DB::table($this->prefix().'teacher_assignments')->whereNotIn('id', $knownAssignments)->value('id');
        $this->assertNotNull($coverId);
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $coverId, 'effective_to' => '2026-09-15',
        ]);
        $this->post('/academic/teacher-assignments/'.$coverId.'/extend', [
            'effective_to' => '2026-10-01',
            'reason' => 'cover extended by management',
        ])->assertRedirect('/academic');
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $coverId, 'effective_to' => '2026-10-01',
        ]);
        $this->signOut();

        // All three ended-or-cover teachers still read in-term; the
        // gradesheet shows the full assignment lineage with the dates
        // each row actually carried.
        $this->assertGradesheetReadable('tal-teacher-a1', $classId);
        [$successorIds, $successorDates] = $this->teacherRowsByPerson($this->assertGradesheetReadable('tal-teacher-b1', $classId), 'tal-teacher-b1');
        $this->assertSame([$successorId], $successorIds);
        $this->assertContains(['from' => '2026-09-05', 'to' => '2026-09-10'], $successorDates);
        $this->assertGradesheetReadable('tal-teacher-c1', $classId);
        [$coverIds, $coverDates] = $this->teacherRowsByPerson($this->assertGradesheetReadable('tal-teacher-a1', $classId), 'tal-teacher-c1');
        $this->assertSame([$coverId], $coverIds);
        $this->assertContains(['from' => '2026-09-10', 'to' => '2026-10-01'], $coverDates);
    }

    public function test_assignment_lifecycle_refusals_are_governed(): void
    {
        $this->makeEmployee('tal-mgmt-2', ['academic.schedule'], 'refusal-manager');
        $this->makeEmployee('tal-plain-2', [], 'refusal-stranger');

        $this->signIn('refusal-manager');
        $setup = $this->activeClass('ref', 'tal-teacher-c2', $this->periodId);
        $assignmentId = $setup['assignment_id'];

        // An end date on or before the start is refused.
        $this->post('/academic/teacher-assignments/'.$assignmentId.'/end', [
            'effective_to' => '2026-09-01',
            'reason' => 'same-day end attempt',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.assignment_period');

        $this->post('/academic/teacher-assignments/'.$assignmentId.'/end', [
            'effective_to' => '2026-09-04',
            'reason' => 'resigned',
        ])->assertRedirect('/academic');

        // A dated assignment cannot be ended again…
        $this->post('/academic/teacher-assignments/'.$assignmentId.'/end', [
            'effective_to' => '2026-10-01',
            'reason' => 'second end attempt',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.assignment_not_open');

        // …and an ended assignment cannot be extended at all: only an
        // open dated row has an end date to move.
        $this->post('/academic/teacher-assignments/'.$assignmentId.'/extend', [
            'effective_to' => '2026-09-04',
            'reason' => 'extension of an ended assignment',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.assignment_not_extendable');
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $assignmentId, 'effective_to' => '2026-09-04',
        ]);

        // An open assignment cannot be extended; it has no end date.
        $this->buildActiveTeacher('tal-teacher-d2', $this->branchId, 'tal-teacher-d2');
        $knownAssignments = DB::table($this->prefix().'teacher_assignments')->pluck('id')->all();
        $this->post('/academic/teacher-assignments', [
            'class_id' => $setup['class_id'],
            'teacher_person_id' => 'tal-teacher-d2',
            'effective_from' => '2026-09-01',
        ])->assertRedirect('/academic');
        $openId = DB::table($this->prefix().'teacher_assignments')->whereNotIn('id', $knownAssignments)->value('id');
        $this->post('/academic/teacher-assignments/'.$openId.'/extend', [
            'effective_to' => '2026-12-01',
            'reason' => 'extension of an open assignment',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.assignment_not_dated');

        // The successor must not already hold an open assignment.
        $this->post('/academic/teacher-assignments/'.$openId.'/handover', [
            'successor_teacher_person_id' => 'tal-teacher-c2',
            'handover_on' => '2026-09-05',
            'reason' => 'handover to the dated teacher',
        ])->assertRedirect('/academic');

        $this->buildActiveTeacher('tal-teacher-e2', $this->branchId, 'tal-teacher-e2');
        $knownAssignments = DB::table($this->prefix().'teacher_assignments')->pluck('id')->all();
        $this->post('/academic/teacher-assignments', [
            'class_id' => $setup['class_id'],
            'teacher_person_id' => 'tal-teacher-e2',
            'effective_from' => '2026-09-01',
        ])->assertRedirect('/academic');
        $thirdId = DB::table($this->prefix().'teacher_assignments')->whereNotIn('id', $knownAssignments)->value('id');
        // tal-teacher-c2 now holds the open handover successor row.
        $this->post('/academic/teacher-assignments/'.$thirdId.'/handover', [
            'successor_teacher_person_id' => 'tal-teacher-c2',
            'handover_on' => '2026-09-06',
            'reason' => 'duplicate successor attempt',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.teacher_duplicate');
        $this->signOut();

        // Without the schedule capability, lifecycle verbs are refused.
        $this->signIn('refusal-stranger');
        $this->post('/academic/teacher-assignments/'.$thirdId.'/end', [
            'effective_to' => '2026-10-01',
            'reason' => 'no authority behind this',
        ], ['referer' => 'http://localhost/academic'])
            ->assertRedirect('/academic')
            ->assertSessionHas('error_code', 'academic.schedule_denied');
        $this->assertDatabaseHas($this->prefix().'teacher_assignments', [
            'id' => $thirdId, 'effective_to' => null,
        ]);
    }

    public function test_ended_assignment_loses_read_access_after_term_end(): void
    {
        $this->makeEmployee('tal-mgmt-3', ['academic.schedule'], 'past-manager');
        $this->buildActiveTeacher('tal-teacher-f3', $this->branchId, 'tal-teacher-f3');

        $this->signIn('past-manager');
        $setup = $this->activeClass('past', 'tal-teacher-f3', $this->pastPeriodId, $this->pastPeriodStart);
        $this->post('/academic/teacher-assignments/'.$setup['assignment_id'].'/end', [
            'effective_to' => '2026-03-01',
            'reason' => 'term cover finished',
        ])->assertRedirect('/academic');
        $this->signOut();

        // The term has ended, so the viewer rule refuses the gradesheet
        // even though the teacher was once assigned; the denial is
        // audited exactly like a command denial.
        $this->assertGradesheetDenied('tal-teacher-f3', $setup['class_id']);
    }
}
