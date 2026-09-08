<?php

declare(strict_types=1);

namespace Tests\Feature\Reliability;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

/**
 * PHASE_4 reliability: the staged SoD guard holds under a REAL concurrent
 * race, not just sequential replay. Two independent PostgreSQL sessions
 * (each a separate PHP child with its own connection) both try to claim the
 * same approver slots on the same organization-wide grant request. The row
 * lock serializes the writers; the schema guard rejects the stale claimant
 * and the winner's write is intact.
 *
 * RefreshDatabase keeps the test process inside one wrapping transaction,
 * which would hide uncommitted fixture rows from child connections and make
 * a nested raw transaction impossible. The fixture is therefore committed by
 * a 'seed' child, and both racers run entirely in child sessions; the
 * test-side assertions only read committed rows.
 */
final class ConcurrencyRaceTest extends TestCase
{
    use BuildsActors;

    public function test_the_schema_guard_rejects_the_stale_writer_in_a_concurrent_slot_claim(): void
    {
        $requests = DB::connection()->getTablePrefix().'org_wide_grant_requests';

        $tmp = sys_get_temp_dir().'/race-'.getmypid();
        $cfg = config('database.connections.'.config('database.default'));
        $base = [
            PHP_BINARY,
            base_path('tests/Stubs/concurrency_race_child.php'),
            (string) $cfg['database'], (string) $cfg['host'], (string) $cfg['port'],
            (string) $cfg['username'], (string) $cfg['password'],
        ];
        $devNull = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];

        /** @var list<string> $artifacts */
        $artifacts = [];

        $spawn = function (array $args, string $label, string $waitFile) use (&$artifacts, $devNull) {
            $proc = proc_open($args, $devNull, $pipes);
            if (! is_resource($proc)) {
                $this->fail("the {$label} race child could not start");
            }
            $deadline = time() + 15;
            while (! file_exists($waitFile) && time() < $deadline) {
                usleep(50_000);
            }
            $this->assertFileExists($waitFile, "the {$label} race child never reached its checkpoint");
            $artifacts[] = $waitFile;
            $artifacts[] = (string) $args[9];

            return $proc;
        };

        // 1. A seed child commits the request fixture (person + born-'requested'
        //    staged request) so both racing sessions share the same committed row.
        $seedResult = $tmp.'.seed.result';
        $seed = proc_open(
            [...$base, 'race-request-row', 'race-requestor', $tmp.'.seed.ready', $seedResult, 'seed'],
            $devNull,
            $seedPipes,
        );
        $this->assertIsResource($seed);
        $deadline = time() + 15;
        while (! file_exists($seedResult) && time() < $deadline) {
            usleep(50_000);
        }
        $this->assertFileExists($seedResult, 'the seed race child never committed its fixture');
        $seedStatus = proc_close($seed);
        $this->assertSame(0, $seedStatus, 'the seed race child crashed');
        $this->assertSame('SEEDED', (string) file_get_contents($seedResult));
        $artifacts[] = $seedResult;

        /** @var string $requestId */
        $requestId = (string) DB::table($requests)->where('requested_by', 'race-requestor')->value('id');
        $this->assertDatabaseHas($requests, ['id' => $requestId, 'lifecycle_state' => 'requested']);
        $grantsBefore = (int) DB::table(DB::connection()->getTablePrefix().'scope_grants')->count();

        // 2. The stale claimant goes first: it opens its own transaction, takes
        //    its read snapshot of the pre-claim 'requested' row, and then waits
        //    out the winner's commit before attempting its own write.
        $loserReady = $tmp.'.loser.ready';
        $loserResult = $tmp.'.loser.result';
        $loser = $spawn(
            [...$base, $requestId, 'race-loser', $loserReady, $loserResult, 'stale'],
            'stale',
            $loserReady,
        );

        // 3. The winner claims the same slots in its own session and commits.
        $winnerReady = $tmp.'.winner.ready';
        $winnerResult = $tmp.'.winner.result';
        $winner = $spawn(
            [...$base, $requestId, 'race-winner', $winnerReady, $winnerResult, 'winner'],
            'winning',
            $winnerReady,
        );
        $winnerStatus = proc_close($winner);
        $this->assertSame(0, $winnerStatus, 'the winning race child crashed');
        $this->assertSame('COMMITTED', (string) file_get_contents($winnerResult));

        // 4. The stale claimant's write now lands against the committed row and
        //    the schema guard rejects it.
        $loserStatus = proc_close($loser);
        $this->assertSame(0, $loserStatus, 'the stale race child crashed');

        $outcome = (string) file_get_contents($loserResult);
        $this->assertStringStartsWith(
            'REJECTED: SQLSTATE[23514]',
            $outcome,
            'the stale concurrent claimant must be rejected by the schema guard (check violation)',
        );
        // Depending on the interleaving the guard fires its state-transition
        // branch (the stale writer read 'requested' before the winner
        // committed) or its written-once slot branch. Either way the stale
        // claimant cannot win.
        $this->assertTrue(
            str_contains($outcome, 'moves only requested -> approved -> granted')
            || str_contains($outcome, 'written once'),
            'unexpected guard message: '.$outcome,
        );

        // The winner's write is intact; no authority materialized.
        $this->assertDatabaseHas($requests, [
            'id' => $requestId,
            'lifecycle_state' => 'approved',
            'approver_one_id' => 'race-winner',
            'approver_two_id' => 'race-winner-2',
        ]);
        $this->assertSame($grantsBefore, (int) DB::table(DB::connection()->getTablePrefix().'scope_grants')->count());

        // The children committed outside the wrapping transaction, so a
        // cleanup child removes their rows in committed statements (the
        // request table is append-only for row deletes, so it is truncated).
        $cleanupResult = $tmp.'.cleanup.result';
        $cleanup = proc_open(
            [...$base, 'race-cleanup', 'race-requestor', $tmp.'.cleanup.ready', $cleanupResult, 'cleanup'],
            $devNull,
            $cleanupPipes,
        );
        $this->assertIsResource($cleanup);
        $cleanupStatus = proc_close($cleanup);
        $this->assertSame(0, $cleanupStatus, 'the cleanup race child crashed');
        $this->assertSame('CLEANED', (string) file_get_contents($cleanupResult));
        $artifacts[] = $cleanupResult;

        foreach (array_unique($artifacts) as $file) {
            @unlink($file);
        }
    }
}
