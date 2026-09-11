/**
 * Runtime environment contract check.
 *
 * Machine-checkable enforcement of the supported runtime ranges recorded in
 * docs/RUNTIME_ENVIRONMENT_LOCK.md. CI and platform launchers may use a
 * concrete patch-version reference, but the verifier deliberately validates
 * the approved compatibility range so a reproducible supported host is not
 * rejected merely because it uses a different verified patch release.
 *
 * Run: npm run verify:environment
 */
import { execFileSync } from 'node:child_process';

const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name.padEnd(52)} ${detail}`);
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

function compare(left, right) {
  for (const part of ['major', 'minor', 'patch']) {
    if (left[part] !== right[part]) return left[part] < right[part] ? -1 : 1;
  }

  return 0;
}

function withinRange(version, lock) {
  return version !== null && compare(version, lock.min) >= 0 && compare(version, lock.max) < 0;
}

const LOCK = {
  php: { range: '>=8.2 <8.5', min: { major: 8, minor: 2, patch: 0 }, max: { major: 8, minor: 5, patch: 0 } },
  composer: { range: '>=2.5 <3', min: { major: 2, minor: 5, patch: 0 }, max: { major: 3, minor: 0, patch: 0 } },
  node: { range: '>=22.0 <23.0', min: { major: 22, minor: 0, patch: 0 }, max: { major: 23, minor: 0, patch: 0 } },
  npm: { range: '>=10.0 <11.0', min: { major: 10, minor: 0, patch: 0 }, max: { major: 11, minor: 0, patch: 0 } },
  postgres: { range: '>=18.0 <19.0', min: { major: 18, minor: 0, patch: 0 }, max: { major: 19, minor: 0, patch: 0 } },
  laravel: { range: '>=12.67 <13.0', min: { major: 12, minor: 67, patch: 0 }, max: { major: 13, minor: 0, patch: 0 } },
};

const REQUIRED_EXTENSIONS = [
  'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv',
  'json', 'libxml', 'mbstring', 'openssl', 'pcntl', 'pcre', 'pdo_pgsql',
  'phar', 'posix', 'session', 'tokenizer', 'xml', 'xmlwriter',
];

const phpRaw = run('php', ['-r', 'echo PHP_VERSION;']);
const php = parse(phpRaw);
record('PHP within supported range', withinRange(php, LOCK.php), `${phpRaw ?? 'php not found'} (required ${LOCK.php.range})`);

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
record('Composer within supported range', withinRange(composer, LOCK.composer), `${composerRaw?.split('\n')[0] ?? 'composer not found'} (required ${LOCK.composer.range})`);

const node = parse(process.version);
record('Node within supported range', withinRange(node, LOCK.node), `${process.version} (required ${LOCK.node.range})`);

const npmRaw = run('npm', ['--version']);
const npm = parse(npmRaw);
record('npm within supported range', withinRange(npm, LOCK.npm), `${npmRaw ?? 'npm not found'} (required ${LOCK.npm.range})`);

const laravel = run('php', ['-r',
  'require "vendor/autoload.php"; echo \\Illuminate\\Foundation\\Application::VERSION;']);
const lv = parse(laravel);
record('Laravel within supported range', withinRange(lv, LOCK.laravel), `${laravel ?? 'vendor/ not installed'} (required ${LOCK.laravel.range}; 13 is prohibited)`);

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
record('PostgreSQL within supported range', withinRange(pg, LOCK.postgres), pgRaw === 'UNREACHABLE' ? 'could not connect (set DB_HOST/DB_PORT/...)' : `${pgRaw ?? 'unknown'} (required ${LOCK.postgres.range})`);

const failed = results.filter((r) => !r.pass).length;
console.log(`\nENVIRONMENT LOCK: ${results.length - failed}/${results.length} satisfied`);
if (failed > 0) {
  console.error('\nThe runtime is outside the supported environment contract.');
  console.error('Fix the environment or update the lock documents and verification evidence together.');
}
process.exit(failed === 0 ? 0 : 1);
