<?php

declare(strict_types=1);

/**
 * Dependency-free migration-discipline audit.
 *
 * This is intentionally static: it does not pretend to validate PostgreSQL.
 * It protects the repository from duplicate migration numbers and flags
 * data-writing migrations that must be explicitly justified as transitional
 * reference-data behavior.
 */
$root = dirname(__DIR__);
// Overridable so a test can exercise the rules themselves against fixtures;
// production CI uses the repository path below.
$migrationsDir = getenv('MIGRATIONS_DIR') !== false && getenv('MIGRATIONS_DIR') !== ''
    ? (string) getenv('MIGRATIONS_DIR')
    : $root.'/database/migrations';

if (! is_dir($migrationsDir)) {
    fwrite(STDERR, "ERROR: database/migrations directory is missing\n");
    exit(1);
}

$files = array_values(array_filter(
    scandir($migrationsDir) ?: [],
    static fn (string $file): bool => str_ends_with($file, '.php') && $file !== '.' && $file !== '..',
));
sort($files, SORT_STRING);

$numbers = [];
$errors = [];
$warnings = [];
$oneWay = [];
$downExplained = [];
$downMissing = [];
$downEmpty = [];
$downVague = [];
$concurrently = [];
$downReversible = 0;
$allowedDataMigrations = ['2026_09_07_000186_seed_standard_finance_chart.php'];

foreach ($files as $file) {
    // Laravel migration filenames are `YYYY_MM_DD_NNNNNN_name.php`. The audited
    // ordinal is the sequence segment after the date, not the year.
    if (preg_match('/^\d{4}_\d{2}_\d{2}_(\d{6})_[^\.]+\.php$/', $file, $match) !== 1) {
        $errors[] = "invalid migration filename: {$file}";

        continue;
    }

    $number = (int) $match[1];
    if (isset($numbers[$number])) {
        $errors[] = sprintf('duplicate migration number %04d: %s and %s', $number, $numbers[$number], $file);
    } else {
        $numbers[$number] = $file;
    }

    $content = file_get_contents($migrationsDir.'/'.$file) ?: '';
    $hasDataWrite = preg_match('/\b(INSERT\s+INTO|UPDATE\s+\w+\s+SET|DB::table\s*\(|->insert\s*\(|->update\s*\(|Model::query\(\)->create|::create\s*\()\b/i', $content) === 1;
    if ($hasDataWrite && ! in_array($file, $allowedDataMigrations, true)) {
        $warnings[] = "data-writing migration requires explicit review: {$file}";
    }

    /*
     * Rollback discipline. Measured on 2026-09-08 while executing the deployment
     * audit's migration gate: 185 migrations, 168 with a working down(), 17 that
     * refuse by design because the change is one-way in the domain (accounting and
     * provenance history must not be rewound), 0 missing and 0 empty.
     *
     * An empty down() is the case worth banning: `migrate:rollback` then reports the
     * migration as reverted while leaving the schema advanced, which is how an
     * operator ends up believing a rollback happened that did not. A refusal, by
     * contrast, is honest — provided it says what to do instead, which is why the
     * message is required to be specific rather than "irreversible".
     */
    if (preg_match('/function\s+down\s*\(\s*\)\s*(?::\s*void\s*)?\{(.*?)\n    \}/s', $content, $down) !== 1) {
        $downMissing[] = $file;
    } else {
        // A down() with no executable statements is either a documented decision or an
        // accident, and only the accident is invisible: migrate:rollback reports both as
        // reverted, so an unexplained empty down() is how an operator ends up believing a
        // rollback happened that did not. A no-op that states its reason (seeded reference
        // rows that other records now reference) is legitimate and is accepted. Counting
        // commentary as a statement is what let `down(): void { // nothing to do }` pass as
        // reversible when this rule was first written.
        $raw = trim($down[1]);
        $body = trim((string) preg_replace(['/\\/\\*.*?\\*\\//s', '/(^|\\s)\\/\\/[^\\n]*/'], '', $raw));
        $commentText = trim((string) preg_replace('/\\s+/', ' ', str_replace(['/*', '*/', '//'], ' ', $raw)));

        if ($body === '') {
            $explained = strlen($commentText) >= 40
                && preg_match('/\\b(never|because|deliber|intentional|retain|preserv|referenc|immutab|not needed|do not|ignored on re-run)\\b/i', $commentText) === 1;

            if ($explained) {
                $downExplained[] = $file;
            } else {
                $downEmpty[] = $file;
            }
        } elseif (preg_match('/throw\\s+new\\b/', $body) === 1) {
            $oneWay[] = $file;
            $message = '';

            // Take every string literal that follows `throw new`, so a message built by
            // concatenation is measured as the operator will read it. The statement is not
            // delimited by text: these messages contain semicolons ("X is one-way; restore
            // from a reviewed baseline ..."), and an earlier version of this rule cut the
            // statement at the first one and flagged good code as vague.
            $offset = strpos($body, 'throw new');
            $tail = $offset === false ? $body : substr($body, $offset);
            preg_match_all('/([\'"])((?:(?!\1).|\n)*?)\1/s', $tail, $literals);
            $message = trim(implode(' ', $literals[2] ?? []));

            if (strlen($message) < 40 || preg_match('/^(this\s+)?(migration|change)\s+is\s+irreversible\.?$/i', $message) === 1) {
                $downVague[] = $file;
            }
        } else {
            $downReversible++;
        }
    }

    // PostgreSQL cannot build an index concurrently inside a transaction, and Laravel
    // wraps each migration in one on this connection (0 occurrences today; if one is ever
    // added it must run outside the migration transaction, which this repository does not
    // currently support, so it is rejected here instead). Matched as a SQL statement and
    // not as the word: migration comments contain prose such as "a repeated (or
    // concurrently referencing) request", which is not a hazard.
    if (preg_match('/(CREATE|DROP)\\s+(?:UNIQUE\\s+)?INDEX\\s+CONCURRENTLY/i', $content) === 1) {
        $concurrently[] = $file;
    }
}

ksort($numbers, SORT_NUMERIC);
$keys = array_keys($numbers);
$gaps = [];
for ($i = 1, $count = count($keys); $i < $count; $i++) {
    if ($keys[$i] !== $keys[$i - 1] + 1) {
        $gaps[] = sprintf('%04d-%04d', $keys[$i - 1] + 1, $keys[$i] - 1);
    }
}

printf("MIGRATION FILES: %d\n", count($files));
printf("LOWEST NUMBER: %04d\n", $keys[0] ?? 0);
printf("HIGHEST NUMBER: %04d\n", $keys[count($keys) - 1] ?? 0);
printf("NUMBERING GAPS: %s\n", $gaps === [] ? 'none' : implode(', ', $gaps));
printf(
    "DOWN() COVERAGE: %d reversible, %d explicit one-way, %d explained no-op, %d undocumented empty, %d missing\n",
    $downReversible,
    count($oneWay),
    count($downExplained),
    count($downEmpty),
    count($downMissing)
);
printf("DATA-WRITE WARNINGS: %d\n", count($warnings));
foreach ($warnings as $warning) {
    echo "WARNING: {$warning}\n";
}

foreach ($downMissing as $file) {
    $errors[] = "migration declares no down() (rollout without a documented undo path): {$file}";
}
foreach ($downEmpty as $file) {
    $errors[] = "migration has an unexplained empty down(): migrate:rollback reports it as reverted while leaving the schema advanced, so either revert the change or state in down() why it must stand: {$file}";
}
foreach ($downVague as $file) {
    $errors[] = "one-way migration refuses rollback without naming what the operator should do instead (message must be at least 40 characters and specific): {$file}";
}
foreach ($concurrently as $file) {
    $errors[] = "migration uses CREATE INDEX CONCURRENTLY, which cannot run inside the per-migration transaction: {$file}";
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    exit(1);
}

echo "RESULT: PASS (static repository discipline; PostgreSQL schema not executed)\n";
