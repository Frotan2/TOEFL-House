<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Modules\Resources\Models\BookCopy;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class ResourcesApiMutationScopeTest extends TestCase
{
    use BuildsActors;

    public function test_branch_scoped_library_manager_cannot_mutate_a_copy_from_another_branch(): void
    {
        $this->ensureBootstrapAuthority();
        $branchA = RandomIdentifier::new();
        $branchB = RandomIdentifier::new();
        $this->newBranch($branchA, 'Mutation Scope A');
        $this->newBranch($branchB, 'Mutation Scope B');

        $copyB = BookCopy::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $branchB,
            'code' => 'LIB-'.RandomIdentifier::new(),
            'title' => 'Protected Cross Branch Copy',
            'acquired_on' => '2026-09-09',
        ]);
        $borrower = $this->personWithAuthority('mutation-scope-borrower', []);

        $managerId = 'mutation-scope-manager';
        $this->personWithAuthority($managerId, []);
        $this->grantScopeAuthority($managerId, ['resources.books'], 'branch', $branchA);
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $managerId,
            'username' => 'mutation-scope-manager',
            'password_hash' => Hash::make('mutation-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        $this->post('/login', ['username' => 'mutation-scope-manager', 'password' => 'mutation-password-1'])->assertRedirect('/');

        $this->postJson('/api/v1/resources/books/'.$copyB->id.'/issue', [
            'borrower_id' => $borrower->id,
            'issued_on' => '2026-09-09',
            'due_on' => '2026-09-20',
        ])->assertForbidden()->assertJsonPath('error', 'resources.books_denied');
    }

    private function newBranch(string $id, string $name): void
    {
        Branch::query()->create([
            'id' => $id,
            'name' => $name,
            'lifecycle_state' => 'active',
        ]);
        $this->attachBranchToBootstrapOrganization($id);
    }
}
