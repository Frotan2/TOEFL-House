<?php

declare(strict_types=1);

use Tests\Support\PgWire\PgWirePdo;

/**
 * Concurrency race child (plain CLI — no framework). Invoked by
 * ConcurrencyRaceTest as an independent process with its own PostgreSQL
 * connection. Modes choreograph a REAL two-session race:
 *
 *   - 'seed':    inserts the minimal fixture the race needs (an unverified
 *                person and a born-'requested' staged grant request) and
 *                commits, so both racers share the same committed row;
 *   - 'stale':   opens a transaction, takes its read snapshot of the
 *                pre-claim 'requested' row, waits out the winner's commit,
 *                then attempts the same claim — the schema guard must
 *                reject it (REJECTED: SQLSTATE[23514]);
 *   - 'winner':  claims the staged approver slots in its own transaction
 *                and commits (COMMITTED);
 *   - 'cleanup': removes the race rows in their own committed statements
 *                (the staged request table is append-only for row deletes,
 *                so it is truncated).
 *
 * argv: db host port user password row_id subject ready_file result_file [mode] [delay_ms]
 *   - 'seed'    uses subject as the person id and row_id as the request id;
 *   - 'cleanup' uses subject as the person id;
 *   - others    use subject as the approver id.
 */
/** @var array<int, string> $argv */
[
    , $db, $host, $port, $user, $password, $rowId, $subject, $readyFile, $resultFile,
] = $argv;
$mode = (string) ($argv[10] ?? 'winner');
$delayMs = (int) ($argv[11] ?? ($mode === 'stale' ? 2000 : 500));

$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);
if (extension_loaded('pdo_pgsql')) {
    $pdo = new PDO(
        $dsn,
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
} else {
    // Sandbox static PHP cannot load pdo_pgsql; same wire semantics via the
    // test-only driver (no-op wherever the native driver is available).
    require __DIR__.'/../Support/PgWire/PgWireException.php';
    require __DIR__.'/../Support/PgWire/PgWirePdo.php';
    require __DIR__.'/../Support/PgWire/PgWireStatement.php';
    $pdo = new PgWirePdo(
        $dsn,
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
}

try {
    if ($mode === 'seed') {
        // The request is born 'requested' with no approver, executor, or
        // grant — exactly the state the access request boundary produces.
        $pdo->exec('BEGIN');
        $pdo->exec(sprintf(
            "INSERT INTO people (id, legal_name, date_of_birth, verification_state)
             VALUES ('%s', 'Race Requestor', '1990-01-01', 'unverified')",
            $subject,
        ));
        $pdo->exec(sprintf(
            "INSERT INTO org_wide_grant_requests
                (id, person_id, permission, organization_id, effective_from,
                 effective_to, lifecycle_state, requested_by, created_at, updated_at)
             VALUES ('%s', '%s', 'identity.verify', '00000000-0000-4000-8000-00000000b005',
                     '2026-09-01', NULL, 'requested', '%s', now(), now())",
            $rowId,
            $subject,
            $subject,
        ));
        $pdo->exec('COMMIT');
        file_put_contents($resultFile, 'SEEDED');
    } elseif ($mode === 'cleanup') {
        $pdo->exec('TRUNCATE org_wide_grant_requests');
        $pdo->exec(sprintf("DELETE FROM people WHERE id = '%s'", $subject));
        file_put_contents($resultFile, 'CLEANED');
    } else {
        $pdo->exec('BEGIN');
        if ($mode === 'stale') {
            // Snapshot the row before the winner can commit its claim.
            $pdo->exec(sprintf(
                "SELECT lifecycle_state FROM org_wide_grant_requests WHERE id = '%s'",
                $rowId,
            ));
        }
        touch($readyFile);
        usleep($delayMs * 1000);

        $pdo->exec(sprintf(
            "UPDATE org_wide_grant_requests
                SET approver_one_id = '%s',
                    approver_two_id = '%s-2',
                    lifecycle_state = 'approved'
              WHERE id = '%s'",
            $subject,
            $subject,
            $rowId,
        ));
        $pdo->exec('COMMIT');
        file_put_contents($resultFile, 'COMMITTED');
    }
} catch (PDOException $e) {
    try {
        $pdo->exec('ROLLBACK');
    } catch (Throwable) {
        // The failed statement already aborted the transaction.
    }
    file_put_contents($resultFile, 'REJECTED: '.$e->getMessage());
}
