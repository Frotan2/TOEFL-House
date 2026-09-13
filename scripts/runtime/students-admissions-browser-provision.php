#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class StudentsAdmissionsBrowserProvisioner
{
    use BuildsActors;

    public const DESTINATION_BRANCH_NAME = 'E2E Students Destination Branch';

    public function run(): array
    {
        $this->ensureDestinationBranch();

        if (! UserAccount::query()->where('username', 'e2e-students-registrar')->exists()) {
            $this->personWithAuthority('e2e-students-registrar', ['admissions.register', 'admissions.initiate']);
            $this->personWithAuthority('e2e-students-reviewer', ['admissions.review']);
            $this->personWithAuthority('e2e-students-approver', ['admissions.approve']);
            $this->personWithAuthority('e2e-students-manager', ['students.manage', 'students.hold', 'students.communication']);
            $this->personWithAuthority('e2e-students-reactivator', ['students.reactivate']);
            $this->personWithAuthority('e2e-students-transfer', ['students.transfer']);
            $this->personWithAuthority('e2e-students-guardian', ['students.guardian']);

            foreach ([
                'e2e-students-registrar',
                'e2e-students-reviewer',
                'e2e-students-approver',
                'e2e-students-manager',
                'e2e-students-reactivator',
                'e2e-students-transfer',
                'e2e-students-guardian',
            ] as $actor) {
                $this->userForActor($actor, $actor);
            }

            // A verified, branch-homed person who will become the student.
            $this->personWithAuthority('e2e-students-candidate', []);
            // A verified branch-homed person who will act as guardian.
            $this->personWithAuthority('e2e-students-guardian-person', []);

            echo "students/admissions browser E2E actors provisioned on DB '".config('database.connections.pgsql.database')."'\n";
        } else {
            echo "students/admissions browser E2E accounts already present\n";
        }

        return UserAccount::query()
            ->whereIn('username', [
                'e2e-students-registrar',
                'e2e-students-reviewer',
                'e2e-students-approver',
                'e2e-students-manager',
                'e2e-students-reactivator',
                'e2e-students-transfer',
                'e2e-students-guardian',
            ])
            ->get()
            ->mapWithKeys(static fn (UserAccount $account): array => [$account->username => [
                'username' => $account->username,
                'person_id' => $account->person_id,
            ]])
            ->all();
    }

    private function ensureDestinationBranch(): void
    {
        $existing = Branch::query()->where('name', self::DESTINATION_BRANCH_NAME)->first();
        if ($existing instanceof Branch && $existing->lifecycle_state === 'active') {
            return;
        }

        $campusId = (string) DB::table('campuses')->where('lifecycle_state', 'active')->value('id');
        if ($campusId === '') {
            throw new RuntimeException('Students E2E requires an active campus for the destination branch');
        }

        $branchId = $existing?->id ?? RandomIdentifier::new();
        if ($existing === null) {
            DB::table('branches')->insert([
                'id' => $branchId,
                'name' => self::DESTINATION_BRANCH_NAME,
                'lifecycle_state' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('branches')->where('id', $branchId)->update([
                'lifecycle_state' => 'active',
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('campus_assignments')->where('branch_id', $branchId)->whereNull('effective_to')->exists()) {
            DB::table('campus_assignments')->insert([
                'id' => RandomIdentifier::new(),
                'branch_id' => $branchId,
                'campus_id' => $campusId,
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'transfer_correlation_id' => 'students-e2e-destination-branch',
            ]);
        }
    }
}

$manifest = (new StudentsAdmissionsBrowserProvisioner)->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
