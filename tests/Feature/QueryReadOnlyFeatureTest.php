<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Commands\RegisterPerson;
use App\Modules\Identity\Commands\VerifyPerson;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Queries\PersonDirectoryQuery;
use App\Modules\Organization\Queries\EffectiveStructureQuery;
use App\Support\Authorization\Actor;
use App\Support\Authorization\StructureScope;
use App\Support\Identifiers\RandomIdentifier;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\Concerns\OperatesStructure;
use Tests\TestCase;

final class QueryReadOnlyFeatureTest extends TestCase
{
    use BuildsActors;
    use OperatesStructure;

    public function test_queries_never_mutate_the_authoritative_facts(): void
    {
        $organization = $this->establishActiveOrganization();
        $campus = $this->establishActiveCampus($organization);
        $branch = $this->establishActiveBranch($campus);

        // Person intake is the only lawful birth of a person row: it persists
        // the active home branch the person-linked scope resolves from
        // (identity intake doctrine), so the fixture registers through the
        // command instead of writing an unprovenanced row.
        $admin = 'qr-admin-1';
        $this->personWithAuthority($admin, ['identity.admin', 'identity.verify']);
        $this->grantScopeAuthority($admin, ['identity.admin', 'identity.verify'], 'organization', $organization->id);
        $registered = app(RegisterPerson::class)->register(
            new Actor($admin, 'Identity Administrator'),
            'Queried Person',
            '1992-02-02',
            $branch->id,
            RandomIdentifier::new(),
        );
        $person = Person::query()->findOrFail($registered['person_id']);
        app(VerifyPerson::class)->verify(new Actor($admin, 'Identity Administrator'), $person, 'national-id-read', 'documents/national-id-read', RandomIdentifier::new());

        $before = $this->rowCounts();
        $structure = (new EffectiveStructureQuery)->effectiveStructure(new CarbonImmutable('2026-08-25'));
        $directory = (new PersonDirectoryQuery)->personDetail($person->id);
        $verified = (new PersonDirectoryQuery)->verifiedPersons();
        $after = $this->rowCounts();

        $this->assertSame($before, $after);
        $organizationEntry = collect($structure['organizations'])->firstWhere('id', $organization->id);
        $this->assertNotNull($organizationEntry);
        $this->assertSame('Queried Person', $directory['legal_name'] ?? null);
        $this->assertNotEmpty($verified);
        $this->assertSame('branch', $this->unitTypeOf($structure['branches'], $branch->id));
    }

    public function test_scope_filtered_structure_returns_only_the_scoped_subtree(): void
    {
        $inScope = $this->establishActiveOrganization('In Scope Organization');
        $this->establishActiveOrganization('Out Of Scope Organization');
        $campus = $this->establishActiveCampus($inScope, 'Scoped Campus');

        $result = (new EffectiveStructureQuery)->effectiveStructure(
            new CarbonImmutable('2026-08-25'),
            new StructureScope($inScope->id),
        );

        $this->assertCount(1, $result['organizations']);
        $this->assertSame('In Scope Organization', $result['organizations'][0]['name']);
        $this->assertSame('Scoped Campus', $result['campuses'][0]['name'] ?? null);
    }

    /**
     * @return array<string, int>
     */
    private function rowCounts(): array
    {
        return [
            'organizations' => DB::table('organizations')->count(),
            'campuses' => DB::table('campuses')->count(),
            'branches' => DB::table('branches')->count(),
            'departments' => DB::table('departments')->count(),
            'campus_assignments' => DB::table('campus_assignments')->count(),
            'people' => DB::table('people')->count(),
            'audit_events' => DB::table('audit_events')->count(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function unitTypeOf(array $rows, string $id): ?string
    {
        foreach ($rows as $row) {
            if ($row['id'] === $id) {
                return 'branch';
            }
        }

        return null;
    }
}
