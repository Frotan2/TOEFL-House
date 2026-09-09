/**
 * Runtime environment contract check.
 *
 * Machine-checkable enforcement of the supported runtime contract. The locked
 * exact versions come from docs/RUNTIME_ENVIRONMENT_LOCK.md; keeping the
 * verifier on ranges would allow unverified patch/minor drift to pass CI.
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

function exact(version, expected) {
  return version?.raw === expected;
}

const LOCK = {
  php: { exact: '8.4.14', min: { major: 8, minor: 2, patch: 0 }, max: { major: 8, minor: 5, patch: 0 } },
  composer: { exact: '2.9.2', min: { major: 2, minor: 5, patch: 0 }, max: { major: 3, minor: 0, patch: 0 } },
  node: { exact: '22.22.3', min: { major: 22, minor: 0, patch: 0 }, max: { major: 23, minor: 0, patch: 0 } },
  npm: { exact: '10.9.8', min: { major: 10, minor: 0, patch: 0 }, max: { major: 11, minor: 0, patch: 0 } },
  postgres: { exact: '18.4', min: { major: 18, minor: 0, patch: 0 }, max: { major: 19, minor: 0, patch: 0 } },
};

const REQUIRED_EXTENSIONS = [
  'bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'iconv',
  'json', 'libxml', 'mbstring', 'openssl', 'pcntl', 'pcre', 'pdo_pgsql',
  'phar', 'posix', 'session', 'tokenizer', 'xml', 'xmlwriter',
];

const phpRaw = run('php', ['-r', 'echo PHP_VERSION;']);
const php = parse(phpRaw);
const phpSupported = php !== null && php.major === 8 && php.minor >= 2 && php.minor < 5;
record('PHP exact locked version', exact(php, LOCK.php.exact), `${phpRaw ?? 'php not found'} (supported=${phpSupported})`);

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
record('Composer exact locked version', exact(composer, LOCK.composer.exact), `${composerRaw?.split('\n')[0] ?? 'composer not found'} (supported >=2.5 <3)`);

const node = parse(process.version);
record('Node exact locked version', exact(node, LOCK.node.exact), `${process.version} (supported >=22 <23)`);

const npmRaw = run('npm', ['--version']);
const npm = parse(npmRaw);
record('npm exact locked version', exact(npm, LOCK.npm.exact), `${npmRaw ?? 'npm not found'} (supported >=10 <11)`);

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
const pgExact = pg !== null && (pg.raw === LOCK.postgres.exact || pg.raw.startsWith(`${LOCK.postgres.exact}.`));
record('PostgreSQL exact locked version', pgExact, pgRaw === 'UNREACHABLE' ? 'could not connect (set DB_HOST/DB_PORT/...)' : (pgRaw ?? 'unknown'));

const failed = results.filter((r) => !r.pass).length;
console.log(`\nENVIRONMENT LOCK: ${results.length - failed}/${results.length} satisfied`);
if (failed > 0) {
  console.error('\nThe runtime has drifted from the supported environment contract.');
  console.error('Fix the environment or update the lock documents together with a complete verification run.');
}
process.exit(failed === 0 ? 0 : 1);
