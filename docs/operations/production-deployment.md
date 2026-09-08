# The TOEFL House — Production Deployment

This is the single authoritative document for deploying and operating **The
TOEFL House** in production. It reflects the actual repository — no
infrastructure is documented that the application does not use, and no
environment is invented. Where a target-server detail is provider-specific,
the exact value to supply is called out as a `TODO`.

## 1. Runtime requirements

| Component | Requirement | Verified with |
|---|---|---|
| PHP | `>=8.2 <8.5` (CLI + FPM) with `pdo_pgsql`/`pgsql`, `mbstring`, `openssl`, `bcmath`, `intl`, `xml` | 8.4.14 |
| PostgreSQL | `>=18.0 <19.0` with `pgcrypto` and `btree_gist` available | 18.4 |
| Node.js | `>=22.0 <23.0` (`package.json` engines) | 22.22.3 |
| npm | `>=10.0` (`package.json` engines, enforced with `--engine-strict`) | 10.9.8 |
| Web server | nginx (TLS termination) + PHP-FPM | nginx 1.x / php-fpm |
| OS | Any Linux that ships the above (Debian/Ubuntu reference) | — |

The employee console is a React/TypeScript application bundled by Vite; the
module pages remain transitional Blade screens. Production therefore requires
Node.js/npm during release build, but no Node process remains at runtime. There
is **no** Redis or message broker. The core request path uses PHP-FPM; the
Integrations job/relay path is not enabled by this synchronous deployment and
requires an explicit operator/scheduler deployment before use — see §9/§10.

## 2. Required environment variables

The live `.env` is created from `.env.example` and stored persistently at
`$DEPLOY_ROOT/.env` (never in the repository). `deploy/deploy.sh` copies it
into each release and **aborts** unless all hold:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<non-empty>
```

Required values (from `.env.example`): `APP_NAME`, `APP_KEY`
(`php artisan key:generate`), `APP_URL`, `LOG_CHANNEL`/`LOG_LEVEL`,
`SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE`,
`CACHE_STORE=database` (the login rate limiter must be durable across FPM
workers), `QUEUE_CONNECTION=sync`, and the `DB_*` PostgreSQL connection.
Secrets (`APP_KEY`, `DB_PASSWORD`) are set only in the live `.env`, never
committed.

## 3. PostgreSQL configuration

Create one database for the application (and one for the test suite, if you
run tests on the server):

```sql
CREATE DATABASE toefl_house;
```

Point `DB_*` in the `.env` at it. The application requires PostgreSQL extensions `pgcrypto` and `btree_gist`. The
deployment preflight verifies PostgreSQL 18.4, checks that both extensions are
available, and refuses to continue when an uninstalled extension cannot be
provisioned by the deployment role. Keep the
database on the same host (or a trusted private network); `DB_SSLMODE=require`, `verify-ca`, or `verify-full` if it is remote; the
deployment script rejects weaker remote settings. Timezone: the app uses
UTC storage and renders per the `APP_LOCALE`/browser; no server tz change is
needed.

## 4. Installing the application

The deployment is file-based (releases + a `current` symlink):

```
/var/www/toefl-house/
├── .env                  # persistent, created from .env.example (NOT in git)
├── releases/<timestamp>/ # one immutable checkout per deployment
└── current -> releases/<live-timestamp>
```

nginx serves **only** `current/public` (see §11). The project root —
`app/`, `config/`, `.env`, `database/`, `storage/`, `.git` — is never
reachable over HTTP.

## 5. Installing dependencies

`deploy/deploy.sh` runs, per release:

```
composer install --no-dev --no-interaction --prefer-dist \
    --no-progress --optimize-autoloader
```

The committed `composer.lock` is authoritative (no `composer update`). Runtime
versions are **ranges enforced by the environment lock**, not patch pins held by
the deploy script: step 2b runs `npm run verify:environment`'s script inside the
release, and `npm ci --engine-strict` enforces the Node/npm range from
`package.json`. (It used to re-declare a handful of exact patch
versions of its own; a duplicated contract drifts, and it had started refusing
releases that were certified on the locked runtime. If a version must be tightened, change
`docs/RUNTIME_ENVIRONMENT_LOCK.md` and `scripts/runtime/verify-environment.mjs`
together — never here.) PHP and Composer are installed once on the host
(standard package manager or the repository's `docs/environment` recovery
procedure for a from-scratch build).

## 6. Frontend build

The selected interactive root is the standalone React/TypeScript workspace in
`resources/js/app.tsx`. The release host needs Node.js/npm and must build the
Vite asset before the release goes live:

```
npm ci --no-audit --no-fund --engine-strict
npm run build
```

This writes the release's `public/build` manifest/assets. The Blade module
pages are transitional transport/rendering surfaces, not a second interactive
workspace architecture. `artisan view:cache` still compiles those Blade
templates (see §8). The committed `package-lock.json` is mandatory; `npm ci`
refuses to build when the manifest and lockfile disagree.

## 7. Deploying migrations

`deploy/deploy.sh` runs `php artisan migrate --force` **before** the release
goes live, so a failing migration aborts the deployment. If a later health check
fails after the schema advanced, the script does not perform an unsafe application-only rollback. Migrations are **forward-only and never destructive** as part of
normal deployment — the app never drops or rewrites business tables in a
deploy (see `database/migrations`, currently 185 migrations).

## 8. Generating caches

After migrating, `deploy/deploy.sh` runs the Laravel production optimization:

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

(These are equivalent to `php artisan optimize` for this app, which has no
event cache. Re-run them — or simply redeploy — after any code/config change;
a cached config is a snapshot and must not be stale.)

## 9. Workers

**Not required for the core request path.** `QUEUE_CONNECTION=sync`; the
application dispatches no Laravel queue jobs. There is no `queue:work` process
to start or supervise. The Integrations module has a separate durable
`JobRun`/`ProcessJobRun` model and the outbox relay has explicit leases and
idempotent consumers, but those paths are not enabled by this deployment.

## 10. Scheduler and integration relay

The current checkout has no registered `routes/console.php` command,
framework scheduler entry, cron unit, or process supervisor for
`EnqueueJobRun`, `ProcessJobRun`, `ProcessDeliveries`, or `outbox.relay`.
This is an explicit pre-production/runtime gap, not a claim that the relay is
operational. Before enabling it, provision a durable verified employee actor
with the required integration capabilities, add an explicit command/schedule
entry, supervise retries, and verify the lease/consumer behavior. Do not use a
synthetic actor or silently infer a scheduler identity. Runtime verification
is deferred by the architecture review restriction.

## 11. HTTPS / web server

TLS is terminated at nginx (`deploy/nginx/toefl-house.conf`):

- HTTP → HTTPS 301 redirect.
- `ssl_protocols TLSv1.2 TLSv1.3`; replace the certificate paths with your
  CA-issued (e.g. Let's Encrypt) files — a `TODO` in the file.
- `root …/current/public` — **only** `public/` is served.
- Security headers (HSTS, `X-Content-Type-Options`, `X-Frame-Options: DENY`,
  `Referrer-Policy`, `Permissions-Policy`) set at the edge; the app sets the
  same headers (`app/Http/Middleware/SecurityHeaders.php`).
- `SESSION_SECURE_COOKIE=true` makes the session cookie HTTPS-only.

PHP-FPM pool: `deploy/php-fpm.conf` (dynamic `pm`, slowlog, security
`limit_extensions`). Opcache policy is a separate conf.d fragment,
`deploy/opcache.ini`: opcache entries are `PHP_INI_SYSTEM` and cannot be set in
a pool section (`php-fpm -t` refuses the whole file with `unknown entry
'opcache.enable'`).

## 12. Health checks

Two public probes:

- `GET /up` — framework **liveness** (app boots).
- `GET /health` — **readiness**: verifies the database is reachable and the
  application key is set; returns `200 {"status":"ok",...}` when healthy,
  `503` when a critical dependency is down. The body never leaks secrets or
  connection details.

Point your orchestrator (systemd `ExecStartPost`, a load balancer, or a
deploy script) at `/health`. `deploy/deploy.sh` itself polls `/health` after
switching the live release.

## 13. Backup

`deploy/backup.sh` takes a compressed `pg_dump` (custom format) of the
application database, verifies it (`pg_restore --list`), and applies a
retention window. Recommendation: run **nightly** via cron:

```
0 2 * * * /path/to/deploy/backup.sh
```

Backups land in `BACKUP_DIR` (default `/var/backups/toefl-house`), optionally
encrypted with `age` when `AGE_KEYRECIPIENT` is set. **Recovery point
objective (RPO): the last nightly backup.** Ship the dumps off-host
(object storage) for durability beyond the server's disk.

## 14. Restore

`deploy/restore.sh <backup-file> --confirm` (or `--latest --confirm`) restores
the database from a backup. It refuses to run without the explicit `--confirm`,
verifies the dump's integrity **before** touching the live database, creates
the target database if it is missing, restores with
`pg_restore --clean --if-exists`, and reports post-restore verification.

`--create` is deliberately not passed to `pg_restore`: with `--clean` it emits
`DROP DATABASE`/`CREATE DATABASE` for the target, which error whenever the
database still exists ("cannot drop the currently open database", "already
exists"). `pg_restore` ignores those errors, restores everything correctly, and
still **exits 1** — a recovery that reports failure on success cannot be
scripted or trusted, so the script handles database existence itself.

**A backup is only considered verified once this procedure has been exercised
successfully in a recovery drill.** The final drill run (2026-09-08, UTC
19:51:11) backed up the production-shaped database, dropped it entirely with
`DROP DATABASE ... WITH (FORCE)` — 169 tables (168 application tables plus one
drill marker table), 346 rows, 185 migrations — and restored it with
`deploy/restore.sh --latest --confirm`: exit 0, restore 0.88 s, verification
0.10 s, content digest identical to the pre-loss fingerprint
(`7055417d9a2f…`, md5 over the ordered row text of every non-empty table), the
owner account restored with its bcrypt hash intact, and the table written *after*
the dump correctly absent — that gap is the RPO. The application was then driven
in a real browser against the recovered database (login plus 14 consoles,
25 `/api/v1` calls) and passed 21/21 through the TLS edge. Measured recovery
time to serviceable: **0.98 s**. Full transcript:
`docs/AUDIT-2026-09-08-GATE-EVIDENCE.md` §Gate B.

## 15. Rollback

There are two different recovery operations and they must not be conflated.

### Application rollback

`./deploy/deploy.sh --rollback` changes the `current` symlink only after it
proves that the target application's migration files contain every migration
already applied to the live database. This prevents an older application from
being pointed at a newer schema accidentally.

### Database rollback

A schema/data rollback is **not** performed by `deploy.sh`. When a release has
already advanced the database and the new application is unhealthy, the script
refuses an automatic application-only rollback. The operator must either deploy
a forward-compatible fix or restore the verified pre-deploy backup using
`deploy/restore.sh <backup-file> --confirm`, then redeploy the known-good
application.

### Expand / Contract policy

Production schema changes must remain compatible with the immediately previous
application during deployment. Additive changes belong in the expand phase;
code migration and traffic cutover occur after compatibility is established;
destructive changes are deferred until old application consumers are no longer
possible. Historical migrations are never rewritten merely to make rollback
easier.

The deployment script records the migration-table count before and after migration.
If a health check fails after the schema advanced, it does not pretend that symlink
rollback is a complete release rollback.

## 16. Deploying a new release

```
./deploy/deploy.sh <git-ref>      # branch, tag, or commit SHA
```

Steps (all-or-nothing per release; the live symlink only switches after a
green health check):

1. Fresh checkout of `<git-ref>` into `releases/<timestamp>`.
2. `composer install --no-dev --optimize-autoloader`.
3. `npm ci --no-audit --no-fund --engine-strict && npm run build` to produce the React/Vite
   `public/build` assets.
4. Copy the persistent `.env`; enforce `APP_ENV=production`, `APP_DEBUG=false`,
   non-empty `APP_KEY`.
5. **Pre-deploy backup** via `deploy/backup.sh` (fresh dump of the live
   database, taken moments before it is migrated). Without the PostgreSQL
   client tools the deploy is refused — migrations never run without a backup.
6. `php artisan migrate --force` (forward-only).
7. Ensure runtime dirs exist and are owned by the web user.
8. `config:cache` + `route:cache` + `view:cache`.
9. Switch `current` → new release; reload FPM + nginx.
10. Poll `GET /health` until 200. If it fails, rollback is automatic only when
    the database schema did not advance and the previous release is compatible;
    otherwise the script stops and requires a forward-fix or database restore.

Older releases are pruned to the last three.

## 17. Recovering a failed deployment

- **Health check fails before schema advancement:** `deploy.sh` safely verifies the
  previous release's schema compatibility and rolls the `current` symlink back.
  The failed release is preserved for forensics.
- **Health check fails after schema advancement:** `deploy.sh` refuses automatic
  application rollback. Use a forward-fix, or restore the pre-deploy backup and
  then redeploy the known-good application. This is intentional: application
  rollback and database rollback are separate recovery operations.
- **A release that is live but misbehaves:** `./deploy/deploy.sh --rollback`,
  or redeploy the last known-good ref.
- **Data corruption / bad migration:** take a fresh backup of the bad state
  (for forensics), then `deploy/restore.sh <good-backup> --confirm`, verify,
  and re-run `php artisan migrate --force` if the live schema is newer.
- **Crash / host failure:** provision a new host, restore the latest backup
  (§14), redeploy the last known-good ref (§16), and repoint DNS.

## Verification gate (run after any change)

Before declaring a deployment healthy, the full gate must be green:

```
php artisan migrate:fresh --seed:off   # disposable clean-schema verification
npm ci --no-audit --no-fund --engine-strict && npm run build
vendor/bin/phpunit                     # full feature suite
vendor/bin/phpstan analyse             # static analysis
vendor/bin/pint --test                 # formatting
# then, on the live host:
curl -fsS https://<host>/health        # expect 200 {"status":"ok",...}
```

A deployment is production-ready only when these pass against the exact
commit being released.
