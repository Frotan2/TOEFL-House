#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Modules\Identity\Models\UserAccount;
use Illuminate\Contracts\Console\Kernel;
use Tests\Concerns\BuildsActors;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

final class StudentsAdmissionsBrowserProvisioner
{
    use BuildsActors;

    public function run(): array
    {
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
}

$manifest = (new StudentsAdmissionsBrowserProvisioner)->run();
echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
