<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Real PostgreSQL concurrency contract for Access position transitions.
 * Two independent processes race the same row; only one may activate it.
 */
final class AccessConcurrencyBoundaryTest extends TestCase
{
    /** @var list<int> */
    private array $childPids = [];

    public function test_two_simultaneous_position_activations_cannot_both_succeed(): void
    {
        if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
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
        unlink($barrier);
        unlink($goFile);
        unlink($resultPrefix);

        $setup = $this->connect($dsn, $username, $password);
        $setup->exec("CREATE TABLE {$table} (id text PRIMARY KEY, lifecycle_state text NOT NULL CHECK (lifecycle_state IN ('proposed','active')), version integer NOT NULL DEFAULT 0)");
        $statement = $setup->prepare("INSERT INTO {$table} (id, lifecycle_state) VALUES (:id, 'proposed')");
        $statement->execute(['id' => $suffix]);
        $setup = null;

        try {
            $children = [];
            for ($worker = 0; $worker < 2; $worker++) {
                $pid = pcntl_fork();
                if ($pid === -1) {
                    $this->fail('pcntl_fork failed');
                }
                if ($pid === 0) {
                    $this->activationWorker($dsn, $username, $password, $table, $suffix, $barrier, $goFile, $resultPrefix, $worker);
                }
                $children[$pid] = $worker;
                $this->childPids[] = $pid;
            }

            $this->waitForReadyWorkers($barrier, 2);
            file_put_contents($goFile, "go\n");

            foreach ($children as $pid => $worker) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status), sprintf('worker %d did not exit normally', $worker));
                $this->assertSame(0, pcntl_wexitstatus($status), sprintf('worker %d failed', $worker));
            }

            $verify = $this->connect($dsn, $username, $password);
            $row = $verify->query("SELECT lifecycle_state, version FROM {$table} WHERE id = '{$suffix}'")->fetch();
            $this->assertSame('active', $row['lifecycle_state']);
            $this->assertSame(1, (int) $row['version']);

            $outcomes = [];
            for ($worker = 0; $worker < 2; $worker++) {
                $path = $resultPrefix.'-'.$worker;
                $this->assertFileExists($path);
                $outcomes[] = trim((string) file_get_contents($path));
                unlink($path);
            }
            sort($outcomes);
            $this->assertSame(['activated', 'denied_after_lock'], $outcomes);
        } finally {
            $this->dropTable($dsn, $username, $password, $table);
            $this->removeFile($barrier);
            $this->removeFile($goFile);
            $this->removeFile($resultPrefix);
        }
    }

    private function activationWorker(
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
            while (!is_file($goFile)) {
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
                throw new RuntimeException('serialized transition failed');
            }
            $db->commit();
            file_put_contents($resultPrefix.'-'.$worker, "activated\n");
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
                $db->rollBack();
            }
            file_put_contents($resultPrefix.'-'.$worker, 'error:'.$e->getMessage()."\n");
            exit(1);
        }
    }

    private function waitForReadyWorkers(string $barrier, int $expected): void
    {
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            $ready = is_file($barrier) ? count(file($barrier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0;
            if ($ready >= $expected) {
                return;
            }
            usleep(10_000);
        }
        $ready = is_file($barrier) ? count(file($barrier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : 0;
        $this->fail(sprintf('only %d concurrency workers reached the barrier', $ready));
    }

    private function connect(string $dsn, string $username, string $password): PDO
    {
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function dropTable(string $dsn, string $username, string $password, string $table): void
    {
        try {
            $db = $this->connect($dsn, $username, $password);
            $db->exec("DROP TABLE IF EXISTS {$table}");
        } catch (\Throwable) {
        }
    }

    private function removeFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->childPids as $pid) {
            if (function_exists('posix_kill')) {
                posix_kill($pid, SIGTERM);
            }
        }
        parent::tearDown();
    }
}
