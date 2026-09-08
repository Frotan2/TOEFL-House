<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Modules\Identity\Models\UserAccount;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsAuthority;
use Tests\TestCase;

/**
 * Gate F rule: a read path must cost what it costs regardless of how much data
 * the tenant has.
 *
 * Two shapes of failure are possible in a request that returns the right rows
 * today, and neither is visible in a test suite that runs against an empty
 * database: the endpoint issues one query per row (a flat count at zero or a few
 * rows, a linear count once the customer has thousands), or it streams every row
 * it can see into the response and lets the browser pay for it.
 *
 * The register's own words on this were "query count at 0 rows and 40 rows is
 * identical, so this assertion is vacuous". This file refuses to assert on volume
 * it did not create: it grows `people` and `user_accounts` to 1,000 rows through
 * the identity API's own tables, in the transaction the test already owns, and
 * compares the same endpoint before and after.
 *
 * Two rules, each deliberately coarse:
 *   - per-request query count must be identical between volumes, and under a hard
 *     ceiling — linear growth of any constant fails the first, a join-per-row
 *     implementation fails the second;
 *   - the returned list must stay under its declared bound while demonstrably
 *     containing amplified rows, so a list that returns nothing cannot pass.
 *
 * `accounts` here is `user_accounts` and `people`, the two tables the identity
 * read surface selects from. The students/finance chains are deliberately not
 * fabricated: their guard triggers bind a student to an admitted applicant to a
 * final decision, so a copied row either collides with `students_one_per_person`
 * or has to invent a whole admission — see the Gate F report for why fixture
 * volume is honest for the identity surface and not for that one.
 */
final class ReadPathEnvelopeTest extends TestCase
{
    use SeedsAuthority;

    /** Rows the fixture adds on top of whatever the database already holds. */
    private const VOLUME = 1000;

    /** Hard ceiling on queries per request, independent of volume. */
    private const QUERY_CEILING = 40;

    /** `IdentityApiController` limits both lists to 300 rows; the rule is that it does. */
    private const LIST_BOUND = 300;

    /** Queries each additional active branch adds to the authority resolution. */
    private const AUTHORITY_QUERIES_PER_ACTIVE_BRANCH = 14;

    /** Wall-clock ceiling, loose on purpose: this catches queueing, not jitter. */
    private const P95_CEILING_MS = 1500.0;

    /** @var list<string> */
    private const ENDPOINTS = [
        '/api/v1/identity/people',
        '/api/v1/identity/accounts',
    ];

    private string $officerPersonId;

    private string $officerAccountId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->officerPersonId = 'gatef-officer-'.substr(md5((string) random_int(1, PHP_INT_MAX)), 0, 8);
        $this->personWithAuthority($this->officerPersonId, ['identity.admin']);

        $this->officerAccountId = RandomIdentifier::new();
        UserAccount::query()->create([
            'id' => $this->officerAccountId,
            'person_id' => $this->officerPersonId,
            'username' => 'gatef.officer',
            'password_hash' => Hash::make('gatef-password-1'),
            'account_state' => UserAccount::STATE_ACTIVE,
        ]);

        $this->post('/login', ['username' => 'gatef.officer', 'password' => 'gatef-password-1'])->assertRedirect('/');
    }

    public function test_read_queries_do_not_scale_with_rows(): void
    {
        $before = $this->profile();

        $this->amplify(self::VOLUME);

        $this->assertSame(
            self::VOLUME,
            (int) DB::table('people')->where('identity_key', 'like', 'gatef/%')->count(),
            'the fixture did not create the volume this comparison needs, so it would prove nothing'
        );

        $after = $this->profile();
        $report = [];

        foreach (self::ENDPOINTS as $endpoint) {
            $this->assertSame(
                $before[$endpoint]['queries'],
                $after[$endpoint]['queries'],
                "{$endpoint} issued {$before[$endpoint]['queries']} queries against the pre-amplification row count and "
                .$after[$endpoint]['queries'].' at '.number_format($after[$endpoint]['rows']).
                ' visible rows: query count is tracking the data, which is the join-per-row signature'
            );

            // The ceiling grows with the number of active branches, not with rows:
            // `Controller::authorizedBranches()` resolves the canonical decision once
            // per branch (measured at 13 queries each — see
            // tests/Feature/Access/AuthorityResolutionBranchSlopeTest.php). Padding the
            // budget by that term keeps this assertion about the thing it is testing —
            // that the *data* adds no queries — instead of turning it into a fixture
            // census that fails the day someone adds a branch.
            $budget = self::QUERY_CEILING
                + self::AUTHORITY_QUERIES_PER_ACTIVE_BRANCH * max(0, $this->activeBranchCount() - 1);

            $this->assertLessThanOrEqual(
                $budget,
                $after[$endpoint]['queries'],
                sprintf(
                    '%s issued %d queries per request against a budget of %d: %d, plus %d for each of the %d active '
                    .'branches whose authority has to be resolved before the list is read',
                    $endpoint,
                    $after[$endpoint]['queries'],
                    $budget,
                    self::QUERY_CEILING,
                    self::AUTHORITY_QUERIES_PER_ACTIVE_BRANCH,
                    $this->activeBranchCount()
                )
            );

            $this->assertLessThanOrEqual(
                self::P95_CEILING_MS,
                $after[$endpoint]['p95_ms'],
                sprintf(
                    '%s took %.1f ms at the 95th percentile with %s rows; a single request should not be able to occupy a worker for that long',
                    $endpoint,
                    $after[$endpoint]['p95_ms'],
                    number_format($after[$endpoint]['rows'])
                )
            );

            $tables = [];
            foreach ($after[$endpoint]['groups'] as $table => $count) {
                $tables[] = $table.'×'.$count;
            }

            $report[] = sprintf(
                "  %-28s rows=%-6s queries=%-3d p50=%7.2fms p95=%7.2fms json=%d B\n      tables: %s",
                $endpoint,
                number_format($after[$endpoint]['rows']),
                $after[$endpoint]['queries'],
                $after[$endpoint]['p50_ms'],
                $after[$endpoint]['p95_ms'],
                $after[$endpoint]['bytes'],
                implode(', ', $tables)
            );
        }

        $this->writeReport($report);
    }

    public function test_list_endpoints_keep_their_bound_at_volume(): void
    {
        $this->amplify(self::VOLUME);

        foreach (self::ENDPOINTS as $endpoint) {
            $response = $this->getJson($endpoint)->assertOk();
            $payload = (array) $response->json();
            $key = str_contains($endpoint, 'people') ? 'people' : 'accounts';
            $listed = $payload[$key] ?? [];

            // Non-vacuity first: an empty list would satisfy any bound, and scoping
            // is exactly the thing that silently empties a list in a test.
            $this->assertGreaterThan(
                1,
                count($listed),
                "{$endpoint} returned nothing for an actor this fixture just granted identity.admin on — the bound check below would have passed anyway"
            );

            $this->assertLessThanOrEqual(
                self::LIST_BOUND,
                count($listed),
                'with '.self::VOLUME." extra people rows in scope, {$endpoint} returned ".count($listed)
                .' rows: an unbounded list turns tenant growth into a payload whose size nobody chose'
            );
        }
    }

    public function test_the_amplified_rows_are_visible_through_the_endpoint_being_measured(): void
    {
        $this->amplify(20);

        $payload = (array) $this->getJson('/api/v1/identity/people')->assertOk()->json();
        $names = array_column($payload['people'] ?? [], 'legal_name');

        $this->assertNotEmpty($names, 'the actor cannot see the rows the fixture created');
        $this->assertContains(
            'Gate F Person 1',
            $names,
            'amplified people are not readable through the endpoint being profiled, so the measurement below describes an empty table'
        );
    }

    /* --- helpers ------------------------------------------------------------- */

    /**
     * Copy the fixture officer's own person and account N times.
     *
     * Only three columns are invented per copy — id, username and identity_key —
     * because those are the uniqueness rules the schema actually enforces:
     * `user_accounts_one_active_per_person`, `user_accounts_username_unique`, and
     * the identity guard that makes a verified person carry a key, evidence,
     * verifier and timestamp. Everything else is the template's value, so the
     * copies are as legal as the row they came from and the first draft of this
     * fixture learned that the hard way when `people_identity_guard()` refused a
     * verified person with no verifier.
     */
    private function amplify(int $copies): void
    {
        $people = $this->quote($this->officerPersonId);
        $account = $this->quote($this->officerAccountId);

        DB::statement(<<<"SQL"
            with new_people as (
                insert into "people" (id, legal_name, date_of_birth, verification_state, identity_key,
                                     identity_evidence_ref, verified_at, verified_by, home_branch_id)
                select gen_random_uuid()::character(36),
                       'Gate F Person ' || g,
                       tp.date_of_birth,
                       tp.verification_state,
                       'gatef/' || g,
                       'gatef/evidence/' || g,
                       now(),
                       tp.verified_by,
                       tp.home_branch_id
                from (select * from "people" where id = {$people}::character(36)) as tp
                cross join generate_series(1, {$copies}) as g
                returning id
            )
            insert into "user_accounts" (id, person_id, username, account_state, password_hash, password_changed_at)
            select gen_random_uuid()::character(36),
                   np.id,
                   'gatef.' || (row_number() over (order by np.id))::text,
                   ta.account_state,
                   ta.password_hash,
                   now()
            from new_people as np
            cross join (select * from "user_accounts" where id = {$account}::character(36)) as ta
            SQL);
    }

    private function activeBranchCount(): int
    {
        return (int) DB::table('branches')->where('lifecycle_state', 'active')->count();
    }

    private function quote(string $value): string
    {
        return DB::connection()->getPdo()->quote($value);
    }

    /**
     * A per-request cost breakdown, not an assertion: which tables a request touches
     * and how often decides whether a future optimization is about the read or about
     * the authenticated-request backdrop, and a number without that split is a number
     * an operator cannot act on.
     *
     * @param  list<array{query: string, bindings: list<mixed>, time: float}>  $log
     * @return array<string, int>
     */
    private function groupQueryLog(array $log): array
    {
        $groups = [];

        foreach ($log as $entry) {
            preg_match('/\bfrom\s+(?:"?([a-z_]+)"?)|\binto\s+(?:"?([a-z_]+)"?)|\bupdate\s+(?:"?([a-z_]+)"?)/i', $entry['query'], $m);
            $table = $m[1] ?? ($m[2] ?? ($m[3] ?? 'other'));
            $groups[$table] = ($groups[$table] ?? 0) + 1;
        }

        arsort($groups);

        return $groups;
    }

    /** @return array<string, array{queries: int, rows: int, p50_ms: float, p95_ms: float, bytes: int, groups: array<string, int>}> */
    private function profile(): array
    {
        $measured = [];

        foreach (self::ENDPOINTS as $endpoint) {
            $this->getJson($endpoint)->assertOk(); // warm the schema/opcache path

            $timings = [];
            $queries = 0;
            $bytes = 0;
            $rows = 0;

            for ($i = 0; $i < 4; $i++) {
                DB::flushQueryLog();
                DB::enableQueryLog();
                $started = microtime(true);
                $response = $this->getJson($endpoint);
                $timings[] = (microtime(true) - $started) * 1000;
                $log = DB::getQueryLog();
                $queries = count($log);
                DB::disableQueryLog();

                $response->assertOk();
                $bytes = strlen((string) $response->getContent());
                $payload = (array) $response->json();
                $rows = is_array($payload) ? count(reset($payload) ?: []) : 0;
            }

            sort($timings);
            $measured[$endpoint] = [
                'groups' => $this->groupQueryLog($log),
                'queries' => $queries,
                'rows' => $rows,
                'p50_ms' => round(($timings[1] + $timings[2]) / 2, 2),
                'p95_ms' => round(max($timings), 2),
                'bytes' => $bytes,
            ];
        }

        return $measured;
    }

    /** @param list<string> $lines */
    private function writeReport(array $lines): void
    {
        if (getenv('GATE_F_REPORT') !== '1') {
            return;
        }

        fwrite(STDOUT, "\nGate F read-path envelope, ".self::VOLUME.' amplified identity rows, '
            .$this->activeBranchCount()." active branches (timings on 2 shared cores, indicative only)\n"
            .implode("\n", $lines)."\n");
    }
}
