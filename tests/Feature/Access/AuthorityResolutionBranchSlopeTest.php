<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Identity\Models\UserAccount;
use App\Modules\Organization\Models\Branch;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsAuthority;
use Tests\TestCase;

/**
 * The per-request authority cost scales with the number of active branches, and
 * this pins how fast.
 *
 * `Controller::authorizedBranches()` deliberately resolves every active branch
 * through the canonical decision point rather than trusting a navigation hint — a
 * fail-closed choice, not an oversight. The consequence is arithmetic: each branch
 * adds a decision pass, and each pass re-reads the same HR eligibility, policy and
 * grant rows. Measured on 2026-09-08 through `GET /api/v1/identity/people` (the
 * query count for the whole request, 1 to 12 active branches):
 *
 *   branches   1    2    5    12
 *   queries   24   37   76  167        = 11 + 13 x branches
 *
 * A 12-branch network therefore spends 155 of 167 queries finding out what the
 * actor may see, and one query reading the list. That is a cost a volume test
 * cannot see — the row count is the same in all four measurements — so it needs a
 * rule of its own, and the rule is a slope: the per-branch term may not grow.
 *
 * Two directions this guards. It fails if the per-branch pass gets more expensive
 * (a new authority source queried per branch instead of per decision). It does not
 * fail if the loop itself is removed by a correct per-request memo, which is the
 * optimization the numbers argue for and which would show up as a slope near zero —
 * a ceiling cannot forbid an improvement, so this test stays green when one lands.
 */
final class AuthorityResolutionBranchSlopeTest extends TestCase
{
    use SeedsAuthority;

    private const PROBE = '/api/v1/identity/people';

    /** Budget per additional active branch, one query above the measured 13. */
    private const QUERIES_PER_BRANCH = 14;

    private string $personId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->personId = 'slope-officer';
        $this->personWithAuthority($this->personId, ['identity.admin']);
        UserAccount::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $this->personId,
            'username' => 'slope.officer',
            'password_hash' => Hash::make('slope-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);
        $this->post('/login', ['username' => 'slope.officer', 'password' => 'slope-password-1'])->assertRedirect('/');
    }

    public function test_authority_resolution_costs_a_bounded_number_of_queries_per_active_branch(): void
    {
        $baseline = $this->activeBranchCount();
        $before = $this->countRequestQueries();

        $this->addBranches(4);

        $after = $this->countRequestQueries();
        $branches = $this->activeBranchCount() - $baseline;
        $this->assertSame(4, $branches, 'the fixture did not add the branches the slope is measured over');

        $slope = ($after - $before) / $branches;

        if (getenv('GATE_F_REPORT') === '1') {
            fwrite(STDOUT, sprintf(
                "\nGate F authority slope: %d queries at %d active branches, %d at %d — %.1f per branch (budget %d)\n",
                $before,
                $baseline,
                $after,
                $baseline + $branches,
                $slope,
                self::QUERIES_PER_BRANCH
            ));
        }

        // Non-vacuity: a probe that ignored branch count would report a perfect
        // zero slope and prove nothing about the loop this test exists to bound.
        $this->assertGreaterThan(
            0,
            $after - $before,
            'adding 4 active branches changed nothing in the request — either the loop is gone (good, and worth a comment here) or this probe is not resolving authority at all'
        );

        $this->assertLessThanOrEqual(
            self::QUERIES_PER_BRANCH,
            $slope,
            'each additional active branch costs '.round($slope, 1).' queries on a request that reads one bounded list; '
            .'the per-branch pass is where a growing branch count turns into a slow API, so a new per-branch query needs a reason in review'
        );
    }

    public function test_the_branch_term_is_not_a_function_of_how_many_people_are_visible(): void
    {
        $baseline = $this->countRequestQueries();

        // 400 more people the actor can see, then the same request again: the branch
        // loop must not notice. This is the difference between "cost per branch" and
        // "cost per branch per person", and the second form is what an authority
        // resolver quietly becomes when a per-decision fact is moved inside a loop.
        $this->amplifyPeople(400);

        $after = $this->countRequestQueries();

        $this->assertSame(
            $baseline,
            $after,
            'the request cost '.($after - $baseline < 0 ? 'decreased' : 'grew').' by '.abs($after - $baseline).' queries when visible people grew by 400'
        );
    }

    /* --- helpers ------------------------------------------------------------- */

    private function countRequestQueries(): int
    {
        $this->getJson(self::PROBE)->assertOk();   // warm the path being measured
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson(self::PROBE)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function activeBranchCount(): int
    {
        return (int) DB::table('branches')->where('lifecycle_state', 'active')->count();
    }

    private function addBranches(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $id = Branch::query()->create([
                'id' => RandomIdentifier::new(),
                'name' => 'Slope Branch '.$i.' '.substr(md5((string) random_int(1, PHP_INT_MAX)), 0, 8),
                'lifecycle_state' => 'active',
            ])->id;
            $this->attachBranchToBootstrapOrganization($id);
        }
    }

    /**
     * Copy the fixture officer's own person, so the added rows are legal against
     * `people_identity_guard()` and visible to the same actor.
     */
    private function amplifyPeople(int $copies): void
    {
        $person = DB::table('people')->where('id', trim($this->personId))->first();
        $this->assertNotNull($person);
        $branch = $this->quote((string) $person->home_branch_id);

        DB::statement(sprintf(
            'insert into "people" (id, legal_name, date_of_birth, verification_state, identity_key,
                                   identity_evidence_ref, verified_at, verified_by, home_branch_id)
             select gen_random_uuid()::character(36), %s, %s, %s,
                    %s, %s, now(), %s, %s::character(36)
             from generate_series(1, %d) as g',
            $this->quote((string) $person->legal_name),
            $this->quote((string) $person->date_of_birth),
            $this->quote((string) $person->verification_state),
            "'slope/' || g",
            "'slope/evidence/' || g",
            $this->quote((string) $person->verified_by),
            $branch,
            $copies
        ));
    }

    private function quote(?string $value): string
    {
        return $value === null ? 'null' : DB::connection()->getPdo()->quote($value);
    }
}
