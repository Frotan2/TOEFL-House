<?php

declare(strict_types=1);

namespace Tests\Feature\Hr;

use App\Modules\Academic\Commands\MaintainTeacherProfile;
use App\Modules\Hr\Commands\MaintainContract;
use App\Modules\Hr\Commands\MaintainEmployment;
use App\Modules\Hr\Models\Contract;
use App\Modules\Hr\Models\Employment;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * Future-effective HR status must not be mistaken for current teacher
 * eligibility. This pins the temporal contract at the Teacher boundary.
 */
final class TeacherEmploymentTemporalBoundaryTest extends TestCase
{
    use BuildsActors;

    public function test_teacher_profile_registration_rejects_future_effective_hire(): void
    {
        $person = $this->personWithAuthority('future-hire-teacher', []);
        $hr = $this->grantedActor('future-hire-hr', ['hr.employ']);
        $contractOfficer = $this->grantedActor('future-hire-contract', ['hr.contract']);
        $academic = $this->grantedActor('future-hire-academic', ['academic.teacher_manage']);

        $employment = app(MaintainEmployment::class)->employ(
            $hr,
            $person->id,
            'future-hire-employ',
        );
        $employmentModel = Employment::query()->findOrFail($employment['employment_id']);

        $contract = app(MaintainContract::class)->draft(
            $contractOfficer,
            $employmentModel,
            'Future teacher terms',
            CarbonImmutable::today()->toDateString(),
            'future-hire-contract-draft',
        );
        app(MaintainContract::class)->sign(
            $contractOfficer,
            Contract::query()->findOrFail($contract['contract_id']),
            'evidence/future-hire/contract',
            'future-hire-contract-sign',
        );

        $future = CarbonImmutable::today()->addWeeks(2)->toDateString();
        app(MaintainEmployment::class)->hire(
            $hr,
            $employmentModel,
            $future,
            'future-hire-effective',
        );

        $this->assertSame('active', (string) $employmentModel->fresh()->lifecycle_state);

        try {
            app(MaintainTeacherProfile::class)->register(
                $academic,
                $employmentModel->fresh(),
                'Instructor',
                null,
                'future-hire-profile',
            );
            $this->fail('a future-effective hire must not activate teacher capability today');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('academic.teacher_employment_inactive', $rejection->errorCode());
        }

        $this->assertDatabaseCount('teacher_profiles', 0);
    }
}
