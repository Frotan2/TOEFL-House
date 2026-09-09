/**
 * Runtime environment contract check.
 *
 * Machine-checkable enforcement of supported runtime ranges and database
 * contract. PostgreSQL is the only supported database; application and test
 * configuration must use pgsql. An unused sqlite extension on a CI image does
 * not change that contract.
 *
 * Run: npm run verify:environment
 */
import { execFileSync } from 'node:child_process';

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name.padEnd(46)} ${detail}`);
};

function run(cmd, args) {
  try {
    return execFileSync(cmd, args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch {
    return null;
  }
}

const parse = (v) => {
  const m = /(\d+)(?:\.(\d+))?(?:\.(\d+))?/.exec(v ?? '');
  return m ? { major: +m[1], minor: +(m[2] ?? 0), patch: +(m[3] ?? 0), raw: m[0] } : null;
};

function inRange(version, min, max) {
  if (!version) return false;
  const cmp = (a, b) => a.major - b.major || a.minor - b.minor || a.patch - b.patch;
  return cmp(version, min) >= 0 && cmp(version, max) < 0;
}

const LOCK = {
  php: { min: { major: 8, minor: 2, patch: 0 }, max: { major: 8, minor: 5, patch: 0 } },
  composer: { min: { major: 2, minor: 5, patch: 0 }, max: { major: 3, minor: 0, patch: 0 } },
  node: { min: { major: 22, minor: 0, patch: 0 }, max: { major: 23, minor: 0, patch: 0 } },
  postgres: { min: { major: 18, minor: 0, patch: 0 }, max: { major: 19, minor: 0, patch: 0 } },
};

const REQUIRED_EXTENSIONS = [
  'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv',
  'json', 'libxml', 'mbstring', 'openssl', 'pcntl', 'pcre', 'pdo_pgsql',
  'phar', 'posix', 'session', 'tokenizer', 'xml', 'xmlwriter',
];

const phpRaw = run('php', ['-r', 'echo PHP_VERSION;']);
const php = parse(phpRaw);
record('PHP within locked range (>=8.2 <8.5)', inRange(php, LOCK.php.min, LOCK.php.max), phpRaw ?? 'php not found');

const extRaw = run('php', ['-r', 'echo implode(",", get_loaded_extensions());']);
const loaded = new Set((extRaw ?? '').toLowerCase().split(',').map((e) => e.trim()));
const missing = REQUIRED_EXTENSIONS.filter((e) => !loaded.has(e.toLowerCase()));
record('All required PHP extensions present', extRaw !== null && missing.length === 0,
  missing.length ? `missing: ${missing.join(', ')}` : `${REQUIRED_EXTENSIONS.length} present`);

const drivers = run('php', ['-r', 'echo class_exists("PDO") ? implode(",", PDO::getAvailableDrivers()) : "";']);
record('PDO exposes PostgreSQL driver', drivers?.split(',').includes('pgsql') === true, `drivers=[${drivers ?? 'none'}]`);

const dbConnection = process.env.DB_CONNECTION ?? 'pgsql';
record('Active database contract is PostgreSQL', dbConnection === 'pgsql', `DB_CONNECTION=${dbConnection}`);

const composerRaw = run('composer', ['--version', '--no-ansi']);
const composer = parse(composerRaw);
record('Composer within locked range (>=2.5 <3)', inRange(composer, LOCK.composer.min, LOCK.composer.max),
  composerRaw?.split('\n')[0] ?? 'composer not found');

const node = parse(process.version);
record('Node within locked range (>=22 <23)', inRange(node, LOCK.node.min, LOCK.node.max), process.version);

const laravel = run('php', ['-r',
  'require "vendor/autoload.php"; echo \\Illuminate\\Foundation\\Application::VERSION;']);
const lv = parse(laravel);
const laravelOk = lv !== null && lv.major === 12 && (lv.minor > 67 || (lv.minor === 67 && lv.patch >= 0));
record('Laravel 12.67+ (13 is prohibited)', laravelOk, laravel ?? 'vendor/ not installed');

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

const failed = results.filter((r) => !r.pass).length;
console.log(`\nENVIRONMENT LOCK: ${results.length - failed}/${results.length} satisfied`);
if (failed > 0) {
  console.error('\nThe runtime has drifted from the supported environment contract.');
  console.error('Fix the environment rather than relaxing the runtime ranges.');
}
process.exit(failed === 0 ? 0 : 1);
