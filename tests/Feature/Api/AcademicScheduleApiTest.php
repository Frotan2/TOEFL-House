<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Academic\Commands\MaintainAcademicStructure;
use App\Modules\Academic\Commands\MaintainClass;
use App\Modules\Academic\Commands\MaintainSkill;
use App\Modules\Academic\Models\AcademicPeriod;
use App\Modules\Academic\Models\ClassModel;
use App\Modules\Academic\Models\Program;
use App\Modules\Identity\Models\UserAccount;
use App\Support\Authorization\Actor;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Blocker 1 (audit): AcademicApiController::schedule passed the skill value
 * into the $idempotencyKey slot and the generated idempotency key into the
 * $skillId slot of MaintainClass::scheduleSession — diverging from the
 * console call site. With a skill the API stored the idempotency key as the
 * skill (rejected as unknown, idempotency destroyed); without a skill PHP
 * raised a TypeError (500). These tests pin both API cases to the same
 * authoritative command path the console uses, plus invalid-input refusals.
 */
final class AcademicScheduleApiTest extends TestCase
{
    use BuildsActors;
    use \Tests\Concerns\BuildsTeachers;
    use \Tests\Concerns\BuildsSessions;

    private string $classId;

    private string $skillId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personWithAuthority('api-sched-officer', ['academic.structure', 'academic.skill', 'academic.schedule']);
        $scheduler = $this->personWithAuthority('api-scheduler', ['academic.schedule']);
        $this->buildActiveTeacher('api-sched-teacher', null, 'academic871');

        $structure = app(MaintainAcademicStructure::class);
        $program = $structure->defineProgram($this->asOfficer('api-sched-officer'), 'API Schedule Program', 'api-sched-prog');
        $version = $structure->publishVersion($this->asOfficer('api-sched-officer'), Program::query()->findOrFail($program['program_id']), 'rules', 'api-sched-ver');
        $period = $structure->definePeriod($this->asOfficer('api-sched-officer'), 'API Term', new CarbonImmutable('2026-09-01'), new CarbonImmutable('2026-12-18'), 'api-sched-per');
        $structure->transitionPeriod($this->asOfficer('api-sched-officer'), AcademicPeriod::query()->findOrFail($period['period_id']), 'published', 'api-sched-per-pub');
        // A class requires an OPEN OFFERING for its branch, level and period;
        // the domain refuses to infer one.
        $fixtureLevel = app(MaintainAcademicStructure::class)->defineLevel($this->asOfficer('api-sched-officer'), $version['version_id'], 'lvl-ofacademic', 1, 'Level', 'A1', 'ofacademic-lvl');
        app(MaintainAcademicStructure::class)->declareBranchAvailability($this->asOfficer('api-sched-officer'), $this->bootstrapBranchId(), $fixtureLevel['level_id'], $period['period_id'], 'ofacademic-av');
        $fixtureOffering = app(MaintainAcademicStructure::class)->openOffering($this->asOfficer('api-sched-officer'), $this->bootstrapBranchId(), $fixtureLevel['level_id'], $period['period_id'], 200, 'ofacademic-of');

        $class = app(MaintainClass::class)->defineClass($this->asOfficer('api-sched-officer'), $version['version_id'], $period['period_id'], 4, 'api-sched-class', null, $this->bootstrapBranchId());
        $this->classId = $class['class_id'];
        app(MaintainClass::class)->assignTeacher($this->asOfficer('api-sched-officer'), ClassModel::query()->findOrFail($this->classId), 'api-sched-teacher', new CarbonImmutable('2026-09-01'), null, 'api-sched-teach');
        app(MaintainClass::class)->transition($this->asOfficer('api-sched-officer'), ClassModel::query()->findOrFail($this->classId), 'published', 'api-sched-pub');
        app(MaintainClass::class)->transition($this->asOfficer('api-sched-officer'), ClassModel::query()->findOrFail($this->classId), 'active', 'api-sched-act');

        $this->skillId = app(MaintainSkill::class)->register($this->asOfficer('api-sched-officer'), 'api_reading', 'API Reading', 'api-sched-skill')['skill_id'];

        // Registering a skill is not authority to teach it: the teacher needs an
        // effective subject authority and availability, and the assignment must
        // carry the skill. Establish that through the real commands.
        $apiProfileId = (string) \App\Modules\Academic\Models\TeacherProfile::query()
            ->where('person_id', 'api-sched-teacher')->value('id');
        $this->makeTeacherDeliveryReady($apiProfileId, $this->skillId, $this->bootstrapBranchId(), 'apisch');
        $this->attributeSkill($this->classId, $this->skillId, 'apisch-at');

        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $scheduler->id,
            'username' => 'api.scheduler',
            'password_hash' => Hash::make('api-scheduler-pw-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
        $this->post('/login', ['username' => 'api.scheduler', 'password' => 'api-scheduler-pw-1'])->assertRedirect('/');
    }

    private function asOfficer(string $actorId): Actor
    {
        return new Actor($actorId, 'API Schedule Officer');
    }

    public function test_api_schedules_a_session_with_skill_on_the_authoritative_path(): void
    {
        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-08',
            'starts_at' => '09:00',
            'ends_at' => '10:30',
            'skill_id' => $this->skillId,
        ])->assertCreated()
            ->assertJsonPath('status', 'scheduled');

        $this->assertDatabaseHas('class_sessions', [
            'class_id' => $this->classId,
            'skill_id' => $this->skillId,
            'scheduled_on' => '2026-09-08',
            'starts_at' => '09:00',
            'ends_at' => '10:30',
        ]);
    }

    public function test_api_refuses_a_session_without_a_skill_through_the_same_command_path(): void
    {
        // The original blocker was that the API passed the skill into the
        // idempotency-key slot, so a skill-less request raised a TypeError
        // (500). Scheduling now REQUIRES a skill, so the durable guarantee is
        // that the API refuses it as a governed 409 from the same command the
        // console uses — never a 500 and never a silent skill-less session.
        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-09',
            'starts_at' => '11:00',
            'ends_at' => '12:00',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'scheduling.skill_required');

        $this->assertDatabaseMissing('class_sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-09',
        ]);
    }

    public function test_api_treats_an_empty_skill_like_an_absent_one(): void
    {
        // An empty string must not be smuggled through as "no skill"; it is
        // refused identically to an absent one.
        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-10',
            'starts_at' => '11:00',
            'ends_at' => '12:00',
            'skill_id' => '',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'scheduling.skill_required');

        $this->assertDatabaseMissing('class_sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-10',
        ]);
    }

    public function test_api_rejects_an_unknown_skill_in_the_skill_slot(): void
    {
        // Under the swapped order the generated idempotency key landed here;
        // the unknown-skill refusal proves the skill argument now reaches the
        // skill slot of the authoritative command.
        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-10',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
            'skill_id' => 'skill-that-does-not-exist',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'scheduling.skill_unknown');

        $this->assertDatabaseMissing('class_sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-10',
        ]);
    }

    public function test_api_refuses_to_schedule_on_an_inactive_class(): void
    {
        $officer = $this->asOfficer('api-sched-officer');
        $versionId = ClassModel::query()->findOrFail($this->classId)->program_version_id;
        $periodId = ClassModel::query()->findOrFail($this->classId)->period_id;
        $planned = app(MaintainClass::class)->defineClass($officer, $versionId, $periodId, 4, 'api-sched-planned', null, $this->bootstrapBranchId())['class_id'];

        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $planned,
            'scheduled_on' => '2026-09-11',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'scheduling.class_not_active');
    }

    public function test_api_rejects_an_inverted_time_window_and_malformed_input(): void
    {
        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-12',
            'starts_at' => '10:00',
            'ends_at' => '09:00',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'scheduling.window_invalid');

        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-12',
            'starts_at' => '9am',
            'ends_at' => '10:00',
        ])->assertStatus(422);
    }

    public function test_api_requires_the_schedule_capability(): void
    {
        $nobody = $this->personWithAuthority('api-sched-nobody', []);
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $nobody->id,
            'username' => 'api.scheduler.nobody',
            'password_hash' => Hash::make('api-scheduler-pw-2'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
        $this->post('/login', ['username' => 'api.scheduler.nobody', 'password' => 'api-scheduler-pw-2'])->assertRedirect('/');

        $this->postJson('/api/v1/academic/sessions', [
            'class_id' => $this->classId,
            'scheduled_on' => '2026-09-13',
            'starts_at' => '09:00',
            'ends_at' => '10:00',
        ])->assertForbidden();
    }
}
