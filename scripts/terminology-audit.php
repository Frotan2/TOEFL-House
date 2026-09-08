<?php

declare(strict_types=1);

/**
 * Advisory repository-wide terminology audit.
 *
 * It reports known competing terms, but does not fail merely because an
 * occurrence exists. Historical documentation and explicit compatibility
 * boundaries may legitimately retain a legacy term.
 */
$root = dirname(__DIR__);

/** @var array<string, string> $terms */
$terms = [
    '\bLearner\b' => 'Student',
    '\blearners\b' => 'students',
    '\bInstructor\b' => 'Teacher',
    '\binstructors\b' => 'teachers',
    '\bFaculty\b' => 'Teacher',
    '\bfaculty members\b' => 'teachers',
    '\bRegistration\b' => 'Enrollment',
    '\bregistration\b' => 'enrollment',
    '\bRegistrations\b' => 'Enrollments',
    '\bregistrations\b' => 'enrollments',
    '\bStaff\b' => 'Employee',
    '\bstaff members\b' => 'employees',
    '\bSemester\b' => 'Term',
    '\bsemester\b' => 'term',
    '\bClass Offering\b' => 'Offering',
    '\bCourse Offering\b' => 'Offering',
    '\bPlacement Test\b' => 'Placement',
    '\bPromotion\b' => 'Progression',
    '\bSalary Payment\b' => 'Settlement',
    '\bBill\b' => 'Invoice',
    '\bBills\b' => 'Invoices',
    '\bOutstanding\b' => 'Balance',
];

/** @var array<int, string> $excludedPathFragments */
$excludedPathFragments = [
    DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR,
];

$excludedDirectories = [
    $root.'/docs/history',
    // Generated Vite output is a build artifact, not authored source.
    $root.'/public/build',
];

$allowedLineMarkers = [
    'terminology:allowed',
    'historical terminology:',
    'compatibility terminology:',
];

$extensions = [
    'php', 'ts', 'tsx', 'js', 'jsx', 'vue', 'blade.php', 'md', 'mdx', 'json', 'yaml', 'yml', 'css', 'scss', 'html',
];

$violations = 0;
$allowed = 0;
$filesScanned = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
);

foreach ($iterator as $fileInfo) {
    if (! $fileInfo instanceof SplFileInfo || ! $fileInfo->isFile()) {
        continue;
    }

    $path = $fileInfo->getPathname();

    $skip = false;
    foreach ($excludedPathFragments as $fragment) {
        if (str_contains($path, $fragment)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    foreach ($excludedDirectories as $directory) {
        if ($path === $directory || str_starts_with($path, $directory.DIRECTORY_SEPARATOR)) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    $relative = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);
    $extension = strtolower($fileInfo->getExtension());
    $isBlade = str_ends_with(strtolower($relative), '.blade.php');
    if (! $isBlade && ! in_array($extension, $extensions, true)) {
        continue;
    }

    $content = file_get_contents($path);
    if ($content === false || ! mb_check_encoding($content, 'UTF-8')) {
        continue;
    }

    $filesScanned++;
    $lines = preg_split('/\R/', $content) ?: [];

    foreach ($lines as $lineNumber => $line) {
        foreach ($terms as $pattern => $replacement) {
            if (preg_match('/'.$pattern.'/u', $line) !== 1) {
                continue;
            }

            $isAllowed = false;
            foreach ($allowedLineMarkers as $marker) {
                if (stripos($line, $marker) !== false) {
                    $isAllowed = true;
                    break;
                }
            }

            if ($isAllowed) {
                $allowed++;
                printf("ALLOWED %s:%d `%s` → `%s`\n", $relative, $lineNumber + 1, trim($line), $replacement);

                continue;
            }

            $violations++;
            printf("REVIEW  %s:%d detected `%s`; canonical replacement: `%s`\n", $relative, $lineNumber + 1, trim($line), $replacement);
        }
    }
}

printf("FILES SCANNED: %d\n", $filesScanned);
printf("REVIEW FINDINGS: %d\n", $violations);
printf("ALLOWED FINDINGS: %d\n", $allowed);
echo "RESULT: ADVISORY REVIEW (historical and compatibility terminology must be judged semantically)\n";

exit(0);
