/**
 * Runtime environment contract check.
 *
 * Machine-checkable enforcement of docs/RUNTIME_ENVIRONMENT_LOCK.md. Fails
 * when the runtime drifts outside the locked ranges, when a required PHP
 * extension is missing, or when SQLite reappears (PostgreSQL is the only
 * supported database and SQLite is deliberately compiled out so nothing can
 * silently fall back to it).
 *
 * Run: npm run verify:environment
 */
import { execFileSync } from 'node:child_process';

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name.padEnd(46)} ${detail}`);
};

/** Runs a command, returning trimmed stdout or null when unavailable. */
function run(cmd, args) {
  try {
    return execFileSync(cmd, args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null;
  }
}

/** Accepts 1, 2 or 3 component versions (PostgreSQL reports e.g. "18.4"). */
const parse = (v) => {
  const m = /(\d+)(?:\.(\d+))?(?:\.(\d+))?/.exec(v ?? '');
  return m ? { major: +m[1], minor: +(m[2] ?? 0), patch: +(m[3] ?? 0), raw: m[0] } : null;
};

/** Inclusive-lower, exclusive-upper semver range check. */
function inRange(version, min, max) {
  if (!version) return false;
  const cmp = (a, b) => a.major - b.major || a.minor - b.minor || a.patch - b.patch;
  return cmp(version, min) >= 0 && cmp(version, max) < 0;
}

const LOCK = {
  php: { min: { major: 8, minor: 2, patch: 0 }, max: { major: 8, minor: 3, patch: 0 } },
  composer: { min: { major: 2, minor: 5, patch: 0 }, max: { major: 3, minor: 0, patch: 0 } },
  node: { min: { major: 22, minor: 0, patch: 0 }, max: { major: 23, minor: 0, patch: 0 } },
  postgres: { min: { major: 18, minor: 0, patch: 0 }, max: { major: 19, minor: 0, patch: 0 } },
};

/** Extensions the application cannot run without. */
const REQUIRED_EXTENSIONS = [
  'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv',
  'json', 'libxml', 'mbstring', 'openssl', 'pcntl', 'pcre', 'pdo_pgsql',
  'phar', 'posix', 'session', 'tokenizer', 'xml', 'xmlwriter',
];

/** Extensions that must NOT be present. */
const FORBIDDEN_EXTENSIONS = ['sqlite3', 'pdo_sqlite'];

// --- PHP -------------------------------------------------------------------
const phpRaw = run('php', ['-r', 'echo PHP_VERSION;']);
const php = parse(phpRaw);
record('PHP within locked range (>=8.2 <8.3)', inRange(php, LOCK.php.min, LOCK.php.max), phpRaw ?? 'php not found');

// --- PHP extensions --------------------------------------------------------
const extRaw = run('php', ['-r', 'echo implode(",", get_loaded_extensions());']);
const loaded = new Set((extRaw ?? '').toLowerCase().split(',').map((e) => e.trim()));
const missing = REQUIRED_EXTENSIONS.filter((e) => !loaded.has(e.toLowerCase()));
record('All required PHP extensions present', extRaw !== null && missing.length === 0,
  missing.length ? `missing: ${missing.join(', ')}` : `${REQUIRED_EXTENSIONS.length} present`);

const forbidden = FORBIDDEN_EXTENSIONS.filter((e) => loaded.has(e));
record('SQLite absent (PostgreSQL is the only database)', extRaw !== null && forbidden.length === 0,
  forbidden.length ? `FORBIDDEN present: ${forbidden.join(', ')}` : 'sqlite3/pdo_sqlite not loaded');

// A driver list is stronger evidence than an extension name.
const drivers = run('php', ['-r', 'echo class_exists("PDO") ? implode(",", PDO::getAvailableDrivers()) : "";']);
record('PDO exposes pgsql and only pgsql', drivers === 'pgsql', `drivers=[${drivers ?? 'none'}]`);

// --- Composer --------------------------------------------------------------
const composerRaw = run('composer', ['--version', '--no-ansi']);
const composer = parse(composerRaw);
record('Composer within locked range (>=2.5 <3)', inRange(composer, LOCK.composer.min, LOCK.composer.max),
  composerRaw?.split('\n')[0] ?? 'composer not found');

// --- Node ------------------------------------------------------------------
const node = parse(process.version);
record('Node within locked range (>=22 <23)', inRange(node, LOCK.node.min, LOCK.node.max), process.version);

// --- Laravel ---------------------------------------------------------------
const laravel = run('php', ['-r',
  'require "vendor/autoload.php"; echo \\Illuminate\\Foundation\\Application::VERSION;']);
const lv = parse(laravel);
const laravelOk = lv !== null && lv.major === 12 && (lv.minor > 67 || (lv.minor === 67 && lv.patch >= 0));
record('Laravel 12.67+ (13 is prohibited)', laravelOk, laravel ?? 'vendor/ not installed');

// --- PostgreSQL ------------------------------------------------------------
const pgRaw = run('php', ['-r', `
  $h = getenv('DB_HOST') ?: '127.0.0.1';
  $p = getenv('DB_PORT') ?: '5432';
  $d = getenv('DB_DATABASE') ?: 'postgres';
  $u = getenv('DB_USERNAME') ?: 'postgres';
  $w = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
  try {
      $pdo = new PDO("pgsql:host=$h;port=$p;dbname=$d", $u, $w);
      echo $pdo->query('show server_version')->fetchColumn();
  } catch (Throwable $e) { echo 'UNREACHABLE'; }
`]);
const pg = parse(pgRaw);
record('PostgreSQL 18.x reachable', inRange(pg, LOCK.postgres.min, LOCK.postgres.max),
  pgRaw === 'UNREACHABLE' ? 'could not connect (set DB_HOST/DB_PORT/...)' : (pgRaw ?? 'unknown'));

// --- Summary ---------------------------------------------------------------
const failed = results.filter((r) => !r.pass).length;
console.log(`\nENVIRONMENT LOCK: ${results.length - failed}/${results.length} satisfied`);
if (failed > 0) {
  console.error('\nThe runtime has drifted from docs/RUNTIME_ENVIRONMENT_LOCK.md.');
  console.error('Fix the environment rather than relaxing the lock.');
}
process.exit(failed === 0 ? 0 : 1);
