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
$migrationsDir = $root.'/database/migrations';

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
printf("DATA-WRITE WARNINGS: %d\n", count($warnings));
foreach ($warnings as $warning) {
    echo "WARNING: {$warning}\n";
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, "ERROR: {$error}\n");
    }
    exit(1);
}

echo "RESULT: PASS (static repository discipline; PostgreSQL schema not executed)\n";
