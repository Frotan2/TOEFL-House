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

final class ResourcesApiScopeTest extends TestCase
{
    use BuildsActors;

    private string $branchA;

    private string $branchB;

    private string $copyA;

    private string $copyB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureBootstrapAuthority();

        $this->branchA = RandomIdentifier::new();
        $this->branchB = RandomIdentifier::new();
        $this->newBranch($this->branchA, 'Library Scope A');
        $this->newBranch($this->branchB, 'Library Scope B');

        $this->copyA = $this->newCopy($this->branchA, 'Scope A Copy');
        $this->copyB = $this->newCopy($this->branchB, 'Scope B Copy');

        $personId = 'library-scope-manager';
        $this->personWithAuthority($personId, []);
        $this->grantScopeAuthority($personId, ['resources.books'], 'branch', $this->branchA);
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'username' => 'library-scope-manager',
            'password_hash' => Hash::make('library-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
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

    private function newCopy(string $branchId, string $title): string
    {
        return BookCopy::query()->create([
            'id' => RandomIdentifier::new(),
            'organization_id' => $this->bootstrapOrganizationId,
            'originating_branch_id' => $branchId,
            'code' => 'LIB-'.RandomIdentifier::new(),
            'title' => $title,
            'acquired_on' => '2026-09-09',
        ])->id;
    }

    public function test_branch_scoped_library_manager_can_open_workspace_and_sees_only_the_authorized_branch(): void
    {
        $this->post('/login', ['username' => 'library-scope-manager', 'password' => 'library-password-1'])->assertRedirect('/');

        $payload = $this->getJson('/api/v1/resources/workspace')->assertOk()->json();
        $copyIds = array_map(static fn (array $copy): string => (string) $copy['id'], $payload['copies']);

        $this->assertContains($this->copyA, $copyIds);
        $this->assertNotContains($this->copyB, $copyIds);
        $this->assertSame([$this->branchA], array_map(static fn (array $branch): string => (string) $branch['id'], $payload['book_branches']));
    }
}
