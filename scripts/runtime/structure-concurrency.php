#!/usr/bin/env php
<?php

/**
 * ORG-OPS-01 concurrency verification for staged structure governance.
 *
 * Real multi-process race against the migrated production schema in the DEV
 * database (DB_DATABASE=toefl_house_dev): independent PHP processes (never
 * forked — the provisioned native binary cannot safely pcntl_fork in this
 * sandbox) open their own PostgreSQL connections, synchronize on filesystem
 * barriers, and then fire competing commands at the same instant.
 *
 *   Race A: two initiators propose the SAME change concurrently. The
 *           partial unique index + row-lock check must leave exactly one
 *           open request; the loser gets organization.change_already_open.
 *   Race B: two retries of the SECOND owner signature with one shared
 *           Idempotency-Key (lost response) converge to one execution and
 *           both callers receive the executed outcome.
 *   Race C: two DISTINCT eligible owners take the second slot concurrently.
 *           Exactly one executes; the serialized loser is refused with
 *           organization.change_state. The topology row is created once.
 *
 * Usage:
 *   DB_DATABASE=toefl_house_dev php scripts/runtime/structure-concurrency.php
 *
 * Fixture rows carry a unique run id. Governance requests are append-only
 * audit facts and are never deleted; re-running creates a fresh run.
 */

declare(strict_types=1);

use App\Modules\Access\Domain\AccessLifecycle;
use App\Modules\Access\Models\ScopeGrant;
use App\Modules\Identity\Models\Person;
use App\Modules\Organization\Commands\GovernStructureChange;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Campus;
use App\Modules\Organization\Models\CampusAssignment;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Organization;
use App\Modules\Organization\Models\StructureChangeRequest;
use App\Support\Authorization\Actor;
use App\Support\Errors\DomainError;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const BOOTSTRAP_ORG = '00000000-0000-4000-8000-00000000b005';
const BOOTSTRAP_CAMPUS = '00000000-0000-4000-8000-00000000c005';
const BOOTSTRAP_BRANCH = '00000000-0000-4000-8000-00000000d005';

$mode = $argv[1] ?? 'verify';

if ($mode === 'propose-worker' || $mode === 'approve-worker') {
    runWorker($mode, $argv);
    exit(0);
}

verify($app);

// ---------------------------------------------------------------------------
// Orchestrator
// ---------------------------------------------------------------------------

function verify(Application $app): void
{
    $results = [];
    $record = function (string $name, bool $pass, string $detail) use (&$results): void {
        $results[] = compact('name', 'pass', 'detail');
        echo ($pass ? 'PASS' : 'FAIL')."  {$name}\n      {$detail}\n";
    };

    seedOperationalFixture();
    $runId = substr(bin2hex(random_bytes(6)), 0, 12);
    $fixture = seedActors();

    $govern = app(GovernStructureChange::class);
    $initiator = actor($fixture['initiator'], 'Race Initiator');
    $reviewer = actor($fixture['reviewer'], 'Race Reviewer');
    $ownerOne = actor($fixture['owners'][0], 'Race Owner One');
    $ownerTwo = actor($fixture['owners'][1], 'Race Owner Two');
    $ownerThree = actor($fixture['owners'][2], 'Race Owner Three');

    // --- Race A: duplicate concurrent proposals ---------------------------
    $deptName = 'Race '.$runId.' Department';
    $proposalPayload = [
        'scope_type' => 'branch',
        'scope_id' => BOOTSTRAP_BRANCH,
        'name' => $deptName,
    ];
    $a = runRace([
        ['propose-worker', json_encode(['payload' => $proposalPayload, 'actor' => $fixture['initiator'], 'key' => 'race-a-1-'.RandomIdentifier::new()])],
        ['propose-worker', json_encode(['payload' => $proposalPayload, 'actor' => $fixture['initiator'], 'key' => 'race-a-2-'.RandomIdentifier::new()])],
    ], $app);

    $openCount = StructureChangeRequest::query()
        ->where('change_type', 'create_department')
        ->whereIn('lifecycle_state', StructureChangeRequest::OPEN_STATES)
        ->where('payload->name', $deptName)
        ->count();
    $departmentCount = Department::query()->where('name', $deptName)->count();
    $aOk = $openCount === 1 && $departmentCount === 0
        && count(array_filter($a, static fn (array $r): bool => $r['ok'] === true)) === 1
        && count(array_filter($a, static fn (array $r): bool => ($r['errorCode'] ?? null) === 'organization.change_already_open')) === 1;
    $record('competing proposals leave one open request (unique index + lock)',
        $aOk, "open={$openCount} departments={$departmentCount} outcomes=".json_encode(array_map(static fn ($r) => $r['ok'] ? 'proposed:'.$r['state'] : $r['errorCode'] ?? 'error', $a)));

    // Helper preparing a reviewed campus proposal with owner one signed.
    $prepareReviewedCampus = function (string $name) use ($govern, $initiator, $reviewer, $ownerOne): array {
        $proposed = $govern->propose($initiator, 'create_campus', [
            'organization_id' => BOOTSTRAP_ORG,
            'name' => $name,
        ], 'prep-'.RandomIdentifier::new());
        $request = StructureChangeRequest::query()->findOrFail($proposed['request_id']);
        $govern->review($reviewer, $request, 'prep-review-'.RandomIdentifier::new());
        $govern->approve($ownerOne, $request->fresh(), 'prep-owner1-'.RandomIdentifier::new());

        return ['id' => $proposed['request_id'], 'name' => $name];
    };

    // --- Race B: lost-response retry with one idempotency key --------------
    $campusB = 'Race '.$runId.' Retry Campus';
    $preparedB = $prepareReviewedCampus($campusB);
    $sharedKey = 'race-b-shared-'.RandomIdentifier::new();
    $b = runRace([
        ['approve-worker', json_encode(['request' => $preparedB['id'], 'actor' => $fixture['owners'][1], 'key' => $sharedKey])],
        ['approve-worker', json_encode(['request' => $preparedB['id'], 'actor' => $fixture['owners'][1], 'key' => $sharedKey])],
    ], $app);
    $requestB = StructureChangeRequest::query()->findOrFail($preparedB['id']);
    $campusCountB = Campus::query()->where('name', $campusB)->count();
    $safeB = array_filter($b, static function (array $r): bool {
        return ($r['ok'] === true && $r['state'] === 'executed')
            || ($r['errorCode'] ?? null) === 'organization.change_state';
    });
    // After the race commits, a genuine lost-response retry (new request,
    // same Idempotency-Key) must replay the recorded executed outcome.
    $replay = $govern->approve($ownerTwo, StructureChangeRequest::query()->findOrFail($preparedB['id']), $sharedKey);
    $campusCountAfterReplay = Campus::query()->where('name', $campusB)->count();
    $bOk = $requestB->lifecycle_state === 'executed' && $campusCountB === 1
        && count($safeB) === 2
        && count(array_filter($b, static fn (array $r): bool => $r['ok'] === true && $r['state'] === 'executed')) >= 1
        && ($replay['lifecycle_state'] ?? null) === 'executed'
        && $campusCountAfterReplay === 1
        && (string) $requestB->owner_two_id === $fixture['owners'][1];
    $record('duplicated second-owner approval with one idempotency key executes once; later retry replays',
        $bOk, "state={$requestB->lifecycle_state} campuses={$campusCountB} replay={$replay['lifecycle_state']} race=".json_encode(array_map(static fn ($r) => $r['ok'] ? $r['state'] : $r['errorCode'] ?? 'error', $b)));

    // --- Race C: two distinct owners for the same slot ---------------------
    $campusC = 'Race '.$runId.' Double Campus';
    $preparedC = $prepareReviewedCampus($campusC);
    $c = runRace([
        ['approve-worker', json_encode(['request' => $preparedC['id'], 'actor' => $fixture['owners'][1], 'key' => 'race-c-1-'.RandomIdentifier::new()])],
        ['approve-worker', json_encode(['request' => $preparedC['id'], 'actor' => $fixture['owners'][2], 'key' => 'race-c-2-'.RandomIdentifier::new()])],
    ], $app);
    $requestC = StructureChangeRequest::query()->findOrFail($preparedC['id']);
    $campusCountC = Campus::query()->where('name', $campusC)->count();
    $executedC = array_filter($c, static fn (array $r): bool => $r['ok'] === true && $r['state'] === 'executed');
    $refusedC = array_filter($c, static fn (array $r): bool => ($r['errorCode'] ?? null) === 'organization.change_state');
    $cOk = $requestC->lifecycle_state === 'executed' && $campusCountC === 1
        && count($executedC) === 1 && count($refusedC) === 1
        && (string) $requestC->owner_one_id !== (string) $requestC->owner_two_id;
    $record('distinct concurrent second owners: one executes, the serialized loser is refused',
        $cOk, "state={$requestC->lifecycle_state} campuses={$campusCountC} outcomes=".json_encode(array_map(static fn ($r) => $r['ok'] ? $r['state'] : $r['errorCode'] ?? 'error', $c)));

    runSqlInvariants($record, $preparedC['id']);

    $failed = count(array_filter($results, static fn (array $r): bool => ! $r['pass']));
    echo "\n".($failed === 0 ? 'ALL STRUCTURE CONCURRENCY & INVARIANT CHECKS PASSED' : "{$failed} CHECK(S) FAILED")."\n";
    exit($failed === 0 ? 0 : 1);
}

/**
 * Spawns one independent PHP process per contender. Every process must
 * report ready before the common barrier is released, so the competing
 * statements actually overlap.
 *
 * @param  list<list<string>>  $invocations
 * @return list<array<string, mixed>>
 */
function runRace(array $invocations, Application $app): array
{
    $dir = rtrim(sys_get_temp_dir(), '/').'/structure-race-'.bin2hex(random_bytes(6));
    mkdir($dir, 0700, true);
    $go = $dir.'/go';
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $processes = [];

    foreach ($invocations as $index => $invocation) {
        $ready = "{$dir}/{$index}.ready";
        $out = "{$dir}/{$index}.out";
        $args = array_map('escapeshellarg', array_merge([$invocation[0], $invocation[1], $ready, $go, $out], array_slice($invocation, 2)));
        $command = PHP_BINARY.' '.__FILE__.' '.implode(' ', $args);
        $processes[] = ['resource' => proc_open($command, $descriptors, $pipes), 'ready' => $ready, 'out' => $out, 'pipes' => $pipes ?? null];
    }

    $deadline = microtime(true) + 30;
    foreach ($processes as $process) {
        while (! file_exists($process['ready']) && microtime(true) < $deadline) {
            usleep(25_000);
        }
    }
    usleep(250_000); // let every contender reach and begin its wait
    touch($go);

    $outcomes = [];
    foreach ($processes as $process) {
        proc_close($process['resource']);
        $raw = file_exists($process['out']) ? (string) file_get_contents($process['out']) : '';
        $decoded = json_decode($raw, true);
        $outcomes[] = is_array($decoded) ? $decoded : ['ok' => false, 'errorCode' => 'no_outcome', 'raw' => $raw];
    }

    return $outcomes;
}

// ---------------------------------------------------------------------------
// Worker mode (separate process)
// ---------------------------------------------------------------------------

function runWorker(string $mode, array $argv): void
{
    $args = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    $ready = $argv[3] ?? '';
    $go = $argv[4] ?? '';
    $outFile = $argv[5] ?? '';

    touch($ready);
    $deadline = microtime(true) + 30;
    while (! file_exists($go) && microtime(true) < $deadline) {
        usleep(10_000);
    }

    $out = static function (array $payload) use ($outFile): void {
        file_put_contents($outFile, json_encode($payload));
    };

    try {
        $govern = app(GovernStructureChange::class);
        $worker = actor($args['actor'], 'Race worker');
        // Tiny jitter so statements arrive within the same instant but are
        // not serialized by deterministic ordering.
        usleep(random_int(0, 30) * 1000);

        if ($mode === 'propose-worker') {
            $result = $govern->propose($worker, 'create_department', $args['payload'], $args['key']);
            $out(['ok' => true, 'state' => $result['lifecycle_state'] ?? null]);

            return;
        }

        $request = StructureChangeRequest::query()->findOrFail($args['request']);
        $result = $govern->approve($worker, $request, $args['key']);
        $out(['ok' => true, 'state' => $result['lifecycle_state'] ?? null]);
    } catch (Throwable $exception) {
        $code = $exception instanceof DomainError ? $exception->errorCode() : null;
        $out(['ok' => false, 'errorCode' => $code, 'message' => $exception->getMessage()]);
    }
}

// ---------------------------------------------------------------------------
// SQL-level invariants (every probe rolls back; no fixture persists)
// ---------------------------------------------------------------------------

/**
 * @param  callable(string,bool,string):void  $record
 */
function runSqlInvariants(callable $record, string $executedRequestId): void
{
    $expectFailure = function (string $label, callable $probe, ?string $messageFragment, ?string $sqlState) use ($record): void {
        DB::beginTransaction();
        try {
            $probe();
            DB::rollBack();
            $record($label, false, 'expected rejection but the write succeeded');
        } catch (Throwable $exception) {
            DB::rollBack();
            $sqlStateCode = $exception instanceof PDOException ? ($exception->errorInfo[0] ?? null) : null;
            $pdo = $exception->getPrevious() instanceof PDOException ? ($exception->getPrevious()->errorInfo[0] ?? null) : null;
            $messageOk = $messageFragment === null || str_contains($exception->getMessage(), $messageFragment);
            $stateOk = $sqlState === null || in_array($sqlState, [$sqlStateCode, $pdo], true);
            $record($label, $messageOk && $stateOk,
                'observed code='.($sqlStateCode ?? $pdo ?? 'n/a').' message='.$exception->getMessage());
        }
    };

    $now = now()->toIso8601String();
    $baseRow = static function (string $key) use ($now): array {
        return [
            'id' => RandomIdentifier::new(),
            'change_type' => 'create_department',
            'unit_type' => 'department',
            'organization_id' => BOOTSTRAP_ORG,
            'target_id' => null,
            'parent_scope_type' => 'branch',
            'parent_scope_id' => BOOTSTRAP_BRANCH,
            'change_key' => $key,
            'payload' => json_encode(['scope_type' => 'branch', 'scope_id' => BOOTSTRAP_BRANCH, 'name' => 'Invariant '.$key]),
            'lifecycle_state' => 'proposed',
            'proposed_by' => RandomIdentifier::new(),
            'created_at' => $now,
            'updated_at' => $now,
        ];
    };

    // 1. Governance facts can never be deleted, even directly in SQL.
    $expectFailure(
        'structure change requests are append-only (DELETE refused)',
        static fn () => DB::statement('DELETE FROM structure_change_requests WHERE id = ?', [$executedRequestId]),
        'cannot be deleted',
        '23514',
    );

    // 2. Two OPEN rows for the same change identity are refused (duplicate
    //    proposals) while the first transaction is still open.
    DB::beginTransaction();
    DB::table('structure_change_requests')->insert($baseRow('inv:open-key:'.RandomIdentifier::new()));
    $expectFailure(
        'one open request per change identity (partial unique index)',
        function () use ($baseRow): void {
            $first = DB::table('structure_change_requests')->whereRaw("change_key LIKE 'inv:open-key:%'")->first();
            DB::table('structure_change_requests')->insert($baseRow((string) $first->change_key));
        },
        null,
        '23505',
    );
    DB::rollBack();

    // 3. A request cannot be born executed; signatures and result are earned.
    $expectFailure(
        'a structure change request is born proposed',
        static function () use ($baseRow): void {
            $row = $baseRow('inv:born:'.RandomIdentifier::new());
            $row['lifecycle_state'] = 'executed';
            $row['owner_one_id'] = RandomIdentifier::new();
            $row['owner_two_id'] = RandomIdentifier::new();
            $row['result'] = json_encode(['id' => RandomIdentifier::new()]);
            DB::table('structure_change_requests')->insert($row);
        },
        'born proposed',
        '23514',
    );

    // 4. Identity columns are immutable after proposal.
    DB::beginTransaction();
    DB::table('structure_change_requests')->insert($baseRow($key = 'inv:immutable:'.RandomIdentifier::new()));
    $expectFailure(
        'proposed request identity/provenance is immutable',
        static function () use ($key): void {
            DB::statement('UPDATE structure_change_requests SET proposed_by = ? WHERE change_key = ?', [RandomIdentifier::new(), $key]);
        },
        'only lifecycle state, signatures, closure and result may change',
        '23514',
    );
    DB::rollBack();
}

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function actor(string $id, string $name): Actor
{
    return new Actor($id, $name);
}

/** Minimum complete operational structure, identical in shape to test fixtures. */
function seedOperationalFixture(): void
{
    if (! Organization::query()->whereKey(BOOTSTRAP_ORG)->exists()) {
        Organization::query()->create(['id' => BOOTSTRAP_ORG, 'name' => 'Concurrency Verification Org', 'lifecycle_state' => 'active']);
    }
    if (! Campus::query()->whereKey(BOOTSTRAP_CAMPUS)->exists()) {
        Campus::query()->create(['id' => BOOTSTRAP_CAMPUS, 'organization_id' => BOOTSTRAP_ORG, 'name' => 'Concurrency Verification Campus', 'lifecycle_state' => 'active']);
    }
    if (! Branch::query()->whereKey(BOOTSTRAP_BRANCH)->exists()) {
        Branch::query()->create(['id' => BOOTSTRAP_BRANCH, 'name' => 'Concurrency Verification Branch', 'lifecycle_state' => 'active']);
    }
    if (! CampusAssignment::query()->where('branch_id', BOOTSTRAP_BRANCH)->whereNull('effective_to')->exists()) {
        CampusAssignment::query()->create([
            'id' => RandomIdentifier::new(),
            'branch_id' => BOOTSTRAP_BRANCH,
            'campus_id' => BOOTSTRAP_CAMPUS,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'transfer_correlation_id' => RandomIdentifier::new(),
        ]);
    }
}

/**
 * @return array{initiator: string, reviewer: string, owners: list<string>}
 */
function seedActors(): array
{
    $make = static function (string $tag): string {
        $id = RandomIdentifier::new();
        Person::query()->create([
            'id' => $id,
            'legal_name' => 'Concurrency '.$tag,
            'date_of_birth' => '1980-01-01',
            'verification_state' => Person::VERIFICATION_VERIFIED,
            'identity_key' => 'concurrency-'.$tag.'-'.RandomIdentifier::new(),
            'identity_evidence_ref' => 'evidence/concurrency/'.$tag,
            'verified_by' => 'concurrency-verifier',
            'verified_at' => now()->toDateTimeString(),
            'home_branch_id' => BOOTSTRAP_BRANCH,
        ]);

        return $id;
    };

    $grant = static function (string $personId, string $capability): void {
        ScopeGrant::query()->create([
            'id' => RandomIdentifier::new(),
            'person_id' => $personId,
            'permission' => $capability,
            'scope_type' => 'organization',
            'scope_id' => BOOTSTRAP_ORG,
            'lifecycle_state' => AccessLifecycle::STATE_ACTIVE,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'is_emergency' => false,
            'review_required' => false,
            'granted_by' => $personId,
        ]);
    };

    $initiator = $make('initiator');
    $grant($initiator, 'organization.structure.initiate');
    $reviewer = $make('reviewer');
    $grant($reviewer, 'organization.structure.review');
    $owners = [];
    foreach (['owner-one', 'owner-two', 'owner-three'] as $tag) {
        $owner = $make($tag);
        $grant($owner, 'organization.structure.approve');
        $owners[] = $owner;
    }

    return ['initiator' => $initiator, 'reviewer' => $reviewer, 'owners' => $owners];
}
