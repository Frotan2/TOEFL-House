<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * Rollback discipline is enforced by `scripts/database-migration-audit.php`, which
 * GitHub Actions runs on every push. These tests exist for one reason: a CI rule that
 * nobody exercises is a rule that can be deleted, or silently broken, without anyone
 * noticing — the reconciliation register already carried "a 185-migration `down()`
 * chain has never been exercised" as an open item, and the first two drafts of these
 * rules produced false positives (they flagged a 140-character refusal message because
 * the message contains a semicolon, and they flagged the English word "concurrently"
 * in migration prose). Both are covered here as fixtures so the rules are tested in
 * both directions.
 *
 * Measured while writing them (2026-09-08, scratch database `toefl_house_c_scratch`):
 * 185 migrations apply to an empty database in 1.7 s; a migration that throws *after*
 * issuing DDL leaves no residue at all — no table, no `migrations` row — because
 * PostgreSQL DDL is transactional and Laravel wraps each migration, which is what makes
 * an interrupted deployment resumable and, with it, `deploy.sh`'s refusal to roll the
 * database back automatically the right design. `migrate:rollback --step=2` at the head
 * of the chain reverts one migration and then stops at a one-way migration, leaving 184
 * applied: batch rollback is *not* atomic. Re-applying returned 185/168/392/285
 * (migrations/tables/indexes/triggers) with every business table's row count and content
 * digest unchanged; the only delta was the `migrations` ledger row itself.
 */
final class MigrationDisciplineAuditTest extends TestCase
{
    public function test_the_repository_passes_the_migration_discipline_audit(): void
    {
        [$code, $out] = $this->runAudit(base_path('database/migrations'));

        $this->assertSame(0, $code, "scripts/database-migration-audit.php failed:\n{$out}");

        $this->assertMatchesRegularExpression('/RESULT: PASS/', $out);

        // Non-vacuity: the audit must see every migration file that exists, and every
        // one must be accounted for by exactly one of the down() categories.
        $files = glob(base_path('database/migrations/*.php'));
        $this->assertMatchesRegularExpression(
            '/MIGRATION FILES: '.count($files).'(?!\d)/',
            $out,
            'the audit is not scanning the directory the repository actually holds'
        );

        preg_match(
            '/DOWN\(\) COVERAGE: (\d+) reversible, (\d+) explicit one-way, (\d+) explained no-op, (\d+) undocumented empty, (\d+) missing/',
            $out,
            $m
        );
        $this->assertMatchesRegularExpression(
            '/DOWN\(\) COVERAGE: \d+ reversible, \d+ explicit one-way, \d+ explained no-op, 0 undocumented empty, 0 missing/',
            $out,
            'every migration must declare a down() that either reverts, refuses with a reason, or documents why nothing may be undone'
        );
        $this->assertSame(
            count($files),
            (int) $m[1] + (int) $m[2] + (int) $m[3] + (int) $m[4] + (int) $m[5],
            'each migration must be counted exactly once, or a category is silently swallowing files'
        );
    }

    public function test_the_audit_rejects_a_migration_that_declares_no_down_method(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(901, <<<'PHP'
            public function up(): void
            {
                Schema::create('gate_c_no_down', function (Blueprint $table): void {
                    $table->id();
                });
            }
            PHP, down: null));

        $this->assertSame(1, $code, "an undocumented rollout with no undo path must fail the audit:\n{$out}");
        $this->assertStringContainsString('declares no down()', $out);
    }

    public function test_the_audit_rejects_an_unexplained_empty_down(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(902, <<<'PHP'
            public function up(): void
            {
                Schema::dropIfExists('gate_c_silent');
            }
            PHP, down: "public function down(): void\n    {\n        // nothing to do\n    }"));

        $this->assertSame(1, $code, "a rollback that silently does nothing reports success while the schema stays advanced:\n{$out}");
        $this->assertStringContainsString('unexplained empty down()', $out);
    }

    public function test_the_audit_accepts_a_documented_no_op_down(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(903, <<<'PHP'
            public function up(): void
            {
                Schema::dropIfExists('gate_c_documented');
            }
            PHP, down: "public function down(): void\n    {\n        // Never delete these rows: journal lines already reference them, and the\n        // seed is ignored on re-run for codes already present, so no reverse step is needed.\n    }"));

        $this->assertSame(0, $code, "a no-op that states its reason is a decision, not an accident:\n{$out}");
        $this->assertStringContainsString('1 explained no-op', $out);
    }

    public function test_the_audit_rejects_a_refusal_that_names_no_alternative(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(904, <<<'PHP'
            public function up(): void
            {
                Schema::dropIfExists('gate_c_bare_refusal');
            }
            PHP, down: "public function down(): void\n    {\n        throw new \\RuntimeException('irreversible');\n    }"));

        $this->assertSame(1, $code, "an operator told only 'irreversible' has no next step:\n{$out}");
        $this->assertStringContainsString('without naming what the operator should do instead', $out);
    }

    public function test_a_refusal_with_a_semicolon_in_the_message_is_accepted(): void
    {
        // Regression test for the rule itself: the message boundary must not be found
        // by scanning for `;`, because these messages are written as two clauses.
        [$code, $out] = $this->runAuditWith($this->migration(905, <<<'PHP'
            public function up(): void
            {
                Schema::dropIfExists('gate_c_semicolon');
            }
            PHP, down: "public function down(): void\n    {\n        throw new \\RuntimeException('This convergence is one-way; restore from a reviewed baseline rather than weakening accounting history.');\n    }"));

        $this->assertSame(0, $code, "a specific refusal must pass, whatever punctuation it contains:\n{$out}");
        $this->assertStringContainsString('1 explicit one-way', $out);
    }

    public function test_the_audit_rejects_concurrent_index_creation(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(906, <<<'PHP'
            public function up(): void
            {
                DB::statement('CREATE INDEX CONCURRENTLY gate_c_idx ON gate_c_concurrent (id)');
            }
            PHP, down: "public function down(): void\n    {\n        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS gate_c_idx');\n    }"));

        $this->assertSame(1, $code, "CONCURRENTLY cannot run inside the transaction Laravel opens per migration:\n{$out}");
        $this->assertStringContainsString('per-migration transaction', $out);
    }

    public function test_prose_that_merely_mentions_concurrency_is_not_flagged(): void
    {
        [$code, $out] = $this->runAuditWith($this->migration(907, <<<'PHP'
            public function up(): void
            {
                // Guard against two requests running concurrently and both inserting.
                Schema::create('gate_c_prose', function (Blueprint $table): void {
                    $table->id();
                });
            }
            PHP, down: "public function down(): void\n    {\n        Schema::dropIfExists('gate_c_prose');\n    }"));

        $this->assertSame(0, $code, "the rule is about a SQL statement, not the English word:\n{$out}");
    }

    /**
     * @param  string  $php  One migration file's method bodies.
     */
    private function migration(int $number, string $php, ?string $down): string
    {
        $downMethod = $down ?? '';
        $marker = "// fixture {$number}";

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Illuminate\\Database\\Migrations\\Migration;
            use Illuminate\\Database\\Schema\\Blueprint;
            use Illuminate\\Support\\Facades\\DB;
            use Illuminate\\Support\\Facades\\Schema;

            return new class extends Migration
            {
                {$marker}

                {$php}

                {$downMethod}
            };
            PHP;
    }

    /** @return array{0:int,1:string} */
    private function runAuditWith(string $source): array
    {
        $dir = sys_get_temp_dir().'/gate-c-migrations-'.bin2hex(random_bytes(5));
        mkdir($dir.'/database/migrations', 0o755, true);
        file_put_contents($dir.'/database/migrations/2026_09_09_000'.sprintf('%03d', 900).'_fixture.php', $source);

        try {
            return $this->runAudit($dir.'/database/migrations');
        } finally {
            exec('rm -rf '.escapeshellarg($dir));
        }
    }

    /** @return array{0:int,1:string} */
    private function runAudit(string $migrationsDir): array
    {
        $out = [];
        $code = 0;
        exec(
            sprintf(
                'MIGRATIONS_DIR=%s %s %s 2>&1',
                escapeshellarg($migrationsDir),
                escapeshellarg(PHP_BINARY),
                escapeshellarg(base_path('scripts/database-migration-audit.php'))
            ),
            $out,
            $code
        );

        return [$code, implode("\n", $out)];
    }
}
