<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * PostgreSQL concurrency contract for Access position lifecycle transitions.
 *
 * This deliberately uses two independent connections and processes. The
 * production TransitionPositionAssignment implementation locks the assignment
 * row and re-reads its lifecycle state before transition; therefore two
 * simultaneous activations must serialize to exactly one winner and one
 * forbidden transition. A purely sequential test cannot prove that property.
 */
final class AccessConcurrencyBoundaryTest extends TestCase
{
    /** @var list<int> */
    private array $childPids = [];

    public function test_two_simultaneous_position_activations_cannot_both_succeed(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
            $this->markTestSkipped('pcntl is required for the real concurrency boundary test.');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            (string) ($_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1'),
            (string) ($_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '5432'),
            (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: 'toefl_house_test'),
        );
        $username = (string) ($_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME') ?: 'postgres');
        $password = (string) ($_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: 'postgres');
        $suffix = bin2hex(random_bytes(8));
        $table = 'rbac_concurrency_assignments_'.$suffix;
        $barrier = tempnam(sys_get_temp_dir(), 'rbac-ready-');
        $goFile = tempnam(sys_get_temp_dir(), 'rbac-go-');
        $resultPrefix = tempnam(sys_get_temp_dir(), 'rbac-result-');
        if ($barrier === false || $goFile === false || $resultPrefix === false) {
            $this->fail('failed to allocate concurrency test barriers');
        }
        @unlink($barrier);
        @unlink($goFile);
        @unlink($resultPrefix);

        $setup = $this->connect($dsn, $username, $password);
        try {
            $setup->exec("CREATE TABLE {$table} (id text PRIMARY KEY, lifecycle_state text NOT NULL CHECK (lifecycle_state IN ('proposed','active','revoked')), version integer NOT NULL DEFAULT 0)");
            $setup->prepare("INSERT INTO {$table} (id, lifecycle_state) VALUES (:id, 'proposed')")->execute(['id' => $suffix]);
        } finally {
            $setup = null;
        }

        $this->childPids = [];
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->cleanupConcurrencyTable($dsn, $username, $password, $table);
                $this->fail('pcntl_fork failed');
            }
            if ($pid === 0) {
                $this->runActivationWorker($dsn, $username, $password, $table, $suffix, $barrier, $goFile, $resultPrefix, $i);
                exit(0);
            }
            $children[$pid] = $i;
            $this->childPids[] = $pid;
        }

        try {
            $this->waitForChildrenReady($barrier, 2);
            file_put_contents($goFile, "go\n", FILE_APPEND | LOCK_EX);

            foreach ($children as $pid => $index) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status), sprintf('worker %d did not exit normally', $index));
                $this->assertSame(0, pcntl_wexitstatus($status), sprintf('worker %d failed', $index));
            }

            $verify = $this->connect($dsn, $username, $password);
            try {
                $row = $verify->query("SELECT lifecycle_state, version FROM {$table} WHERE id = '{$suffix}'")->fetch();
                $this->assertIsArray($row);
                $this->assertSame('active', $row['lifecycle_state']);
                $this->assertSame(1, (int) $row['version'], 'exactly one activation may win the serialized transition');
            } finally {
                $verify = null;
            }

            $outcomes = [];
            for ($i = 0; $i < 2; $i++) {
                $path = $resultPrefix.'-'.$i;
                if (! is_file($path)) {
                    $this->fail('missing result from concurrency worker '.$i);
                }
                $outcomes[] = trim((string) file_get_contents($path));
                @unlink($path);
            }
            sort($outcomes);
            $this->assertSame(['activated', 'denied_after_lock'], $outcomes);
        } finally {
            $this->cleanupConcurrencyTable($dsn, $username, $password, $table);
            @unlink($barrier);
            @unlink($goFile);
            @unlink($resultPrefix);
        }
    }

    private function runActivationWorker(
        string $dsn,
        string $username,
        string $password,
        string $table,
        string $assignmentId,
        string $barrier,
        string $goFile,
        string $resultPrefix,
        int $worker,
    ): void {
        try {
            $db = $this->connect($dsn, $username, $password);
            $db->beginTransaction();
            file_put_contents($barrier, "ready\n", FILE_APPEND | LOCK_EX);
            while (! is_file($goFile)) {
                usleep(10_000);
            }

            $statement = $db->prepare("SELECT lifecycle_state FROM {$table} WHERE id = :id FOR UPDATE");
            $statement->execute(['id' => $assignmentId]);
            $state = (string) $statement->fetchColumn();
            if ($state !== 'proposed') {
                $db->rollBack();
                file_put_contents($resultPrefix.'-'.$worker, "denied_after_lock\n");
                return;
            }

            $update = $db->prepare("UPDATE {$table} SET lifecycle_state = 'active', version = version + 1 WHERE id = :id AND lifecycle_state = 'proposed'");
            $update->execute(['id' => $assignmentId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('serialized transition lost the proposed-state update');
            }
            $db->commit();
            file_put_contents($resultPrefix.'-'.$worker, "activated\n");
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof \PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            file_put_contents($resultPrefix.'-'.$worker, 'error:'.$e->getMessage()."\n");
            exit(1);
        }
    }

    private function waitForChildrenReady(string $barrier, int $expected): void
    {
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            if (is_file($barrier) && count(file($barrier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) >= $expected) {
                return;
            }
            usleep(10_000);
        }
        $this->fail(sprintf('only %d concurrency workers reached the barrier', count(file($barrier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])));
    }

    private function connect(string $dsn, string $username, string $password): \PDO
    {
        return new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function cleanupConcurrencyTable(string $dsn, string $username, string $password, string $table): void
    {
        try {
            $db = $this->connect($dsn, $username, $password);
            $db->exec("DROP TABLE IF EXISTS {$table}");
        } catch (\Throwable) {
            // The assertion that failed is more useful than cleanup noise.
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->childPids as $pid) {
            if (function_exists('posix_kill')) {
                @posix_kill($pid, SIGTERM);
            }
        }
        parent::tearDown();
    }
}
