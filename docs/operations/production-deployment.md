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

### First installation (greenfield)

`deploy/deploy.sh` is the release-switch procedure; it migrates but
deliberately never seeds. On a **brand-new** installation, after the first
successful deployment (migrations applied, health green) run the guarded
first-run bootstrap exactly once to create the bootstrap organization, the
genesis campus + branch that every branch-mandated intake requires, and the
owner account:

```bash
BOOTSTRAP_OWNER_NAME="<full legal name>" \
BOOTSTRAP_OWNER_BIRTHDATE="YYYY-MM-DD" \
BOOTSTRAP_OWNER_USERNAME="<owner-username>" \
BOOTSTRAP_OWNER_PASSWORD='<strong-password>' \
  php artisan db:seed --class=FirstRunBootstrapSeeder --force
```

Contract (pinned by `WindowsOneClickDeploymentContractTest`):

- The variables are read from the **process environment only** — never from
  `.env` — and nothing but the bcrypt password hash is persisted.
- The seeder is a **no-op once any user account exists**: it can never touch,
  overwrite or compete with records on a live system, and re-running it is
  safe.
- This is the one sanctioned place where structure facts are written outside
  the four-actor `StructureDecision` chain, and only at genesis — see
  `docs/decisions/2026-09-09-first-run-genesis-structure.md`. From the moment
  bootstrap completes, all structure change goes through the governed
  four-actor console workflow.
- The standard finance chart needs no separate step: migration `000186`
  seeds it during `migrate`.

Afterwards log in as the owner and provision real staff through the normal
identity/access workflows; rotate away from the bootstrap owner for daily
administration.

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
(standard package manager, the self-contained provisioner
`scripts/runtime/provision.sh`, or the from-source fallback narrative in
`docs/RUNTIME_ENVIRONMENT.md`).

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

### Content Security Policy

Both the application (`App\Http\Middleware\SecurityHeaders::CSP_PRODUCTION`) and
the web server (`deploy/nginx/toefl-house.conf`) send the same policy; a test
asserts the two strings are identical, because two sources of truth drift and the
weaker one silently wins on whichever path serves the response.

**The web server's copy has to be installed, not just written.** `deploy.sh`
copies `deploy/nginx/toefl-house.conf` from the activated release to
`$NGINX_CONF_DEST` and reloads, refusing the change if `nginx -t` rejects it and
putting the previous file back if the reload does not take. Before that step
existed the script only reloaded whatever the host already had, so a header added
in a release reached PHP responses and never reached static files, 404s or 5xx
pages — the paths nginx serves without touching PHP. If the edge is managed
outside this repository, leave `NGINX_CONF_DEST` unset; the deploy then says so
instead of reporting a header set nobody applied.

Because the directive is at server scope, a proxied response carries the policy
twice — once from the middleware, once from `add_header` — and the browser enforces
all of them. Identical copies are harmless by construction here (the parity test is
what keeps them identical), and it is the single copy on a static file that has to
exist at all, since nothing else can put a header on it.

```
default-src 'self'; base-uri 'self'; script-src 'self';
style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self';
connect-src 'self'; form-action 'self'; frame-ancestors 'none';
frame-src 'none'; object-src 'none'; worker-src 'none'; manifest-src 'self'
```

It is **enforced, not report-only**, and it carries **no nonce or hash**, because
the deployed release needs neither: a browser inventory of the login page and all
14 authenticated consoles found no inline `<script>` element, no inline event
handler, no `blob:`/`data:` script, no cross-origin script and no iframe, object
or embed, and the shipped bundles contain no `eval`, no `new Function`, no
`document.write` and no `innerHTML` assignment. `style-src` keeps
`'unsafe-inline'` and nothing else does: Vite injects a `<style>` element at
runtime and Blade uses a handful of `style=""` attributes, and inline CSS cannot
execute script while inline JavaScript can.

Two consequences to know before editing the policy or a page:

* **Adding inline JavaScript will fail silently.** There is no `report-to`/
  `report-uri`, because the repository has no violation collector; a blocked inline
  script shows up only as a browser console error, and the page otherwise looks
  alive. Either move the script into the bundle (the pattern every console already
  uses) or add a collector and wire `report-to` in both places.
* **`php artisan serve` + `npm run dev` still works.** Outside production, when
  `public/hot` exists, the middleware adds the Vite dev-server origin to
  `script-src` and `connect-src` (plus its `ws:` form for HMR). Production never
  carves out, even if a stale `public/hot` is left behind by a build.

`upgrade-insecure-requests` is deliberately not set: it rewrites same-origin `http:`
URLs, which would break development over plain HTTP, and there is no cross-protocol
subresource in the app to protect.


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

Both probes were observed during a real database outage on a deployed release
(`pg_ctl -m fast stop`, PostgreSQL down, nothing else changed):

| Probe | Response | Meaning |
| --- | --- | --- |
| `/up` | `200` | PHP and the framework are alive — **by design this stays green** |
| `/health` | `503 {"status":"error","checks":{"database":"error","application_key":"ok","frontend_build":"ok"}}` | the service is not operable, and the body says which dependency |
| `/login` | `500` | a page that genuinely needs the database |
| after the database came back | `200`, no reload needed | a connection is opened per request, so the app self-heals |

Gate traffic on the pair: `/up` 200 with `/health` 503 is "the application is fine,
its database is not", which is an infrastructure problem, not a restart-the-app
problem. `/health` is registered without the session and CSRF middlewares for
exactly this reason — inside the `web` group it died in `StartSession` before its
own controller could report, answering a 500 HTML page to the one caller that needed
a machine-readable 503. Keep it stateless.

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

Three measured properties of the migration chain decide this design, and all three
are worth knowing before an incident:

* A migration that fails part-way leaves **nothing** behind. PostgreSQL's DDL is
  transactional and Laravel wraps each migration in a transaction, so the injected
  failure test (DDL issued, then a throw) produced no table and no `migrations`
  row. Re-running `deploy.sh` after fixing the cause is therefore safe, which is
  precisely why the release is not allowed to auto-revert the database.
* `php artisan migrate:rollback --step=N` is **not** atomic. Rolling back two
  migrations when the older of the two refuses (17 migrations declare themselves
  one-way because the change is irreversible in the domain — accounting and
  provenance history) reverted the first and then aborted, leaving the database one
  migration behind the application. `migrate --force` re-applied it cleanly. Never
  use `migrate:rollback` as a production recovery step; use a forward-fix or
  `deploy/restore.sh` with the pre-deploy backup.
* The permitted forms of additive migration are **cheap at volume**, and the forbidden
  ones are not. Measured at 1,000,000 rows / 64 MB (`scripts/runtime/perf-envelope.php
  --task=ddl`, 2 shared cores, PostgreSQL 18.4 — the *ratios* are the transferable
  part): `add column` nullable 0.048 s, `add column … not null default <constant>`
  0.015 s, with 36 ms and 1 ms of time a competing writer had to wait. A
  `not null default gen_random_uuid()` — a volatile default, which is what
  `uuid_generate`/`now()` defaults become — took 2.259 s and stalled the writer for
  2,238 ms, because PostgreSQL rewrites every row to materialise it. Narrowing a
  column type behaved the same way (1.556 s, 1,532 ms stall). This is why the policy
  above says "add nullable or non-nullable with a constant default" rather than
  "add a column": at 100M rows the difference is milliseconds against minutes of write
  stall, and the stall is what an operator experiences as an outage.

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
9. Switch `current` → new release; reload FPM, install + reload the edge config.
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

## 18. Logs and observability

**Application log.** `config/logging.php` uses the `stack` channel whose only member
is `single` (`LOG_STACK` overrides the list, `LOG_LEVEL` defaults the threshold to
`debug`; the shipped production `.env` sets `warning`). Set **`LOG_PATH`** to a file
outside the release tree, for example:

```
LOG_PATH=/var/log/toefl-house/laravel.log
LOG_STACK=daily
LOG_DAILY_DAYS=30
```

This is not optional hygiene: `deploy.sh` keeps the four newest release directories
and prunes the rest, and the default path lives inside the release, so **logs are
deleted along with the release they came from** — the record of an incident can
disappear while the incident is open. `LOG_STACK=daily` additionally bounds a single
file's growth (two requests that each raised one database failure wrote 29 KB on the
rehearsal deployment; an error-per-request loop fills a disk in hours).

The `emergency` channel intentionally keeps the release-local path: it is Monolog's
last resort when the configured one fails, so it must not live on the storage that
just failed.

**Nothing else is emitted.** There is no metrics endpoint, no alert channel, and no
pager path in this repository, and `config/integrations.php` carries no transports.
The available observability surface is therefore: `/health` (JSON, per-dependency),
the log file, PostgreSQL's own statistics, and the browser-level API errors. A
`500` on any route writes one exception entry with a stack trace and no request
identifier, so correlated lookups across two requests are not possible today. Adding
metrics/alert routing is an open item recorded in
`docs/AUDIT-2026-09-08-RECONCILIATION.md`'s gap register, not a documented feature.

## 19. Capacity envelope

There is no performance SLO for this application, so this section records measured
shape and arithmetic rather than a latency target. If one is ever agreed, it belongs
in `docs/12-OPERATIONS-DEPLOYMENT-DR.md` next to the alert routing that does not exist
yet (§18), not here.

**Connection budget.** The shipped topology needs no pooler, and this is the arithmetic
to check before raising any of it:

| Contributor | Connections |
| --- | --- |
| PHP-FPM children (`deploy/php-fpm.conf`: `pm = dynamic`, `pm.max_children = 20`) | up to 20 |
| queue workers | 0 — `QUEUE_CONNECTION=sync`, and the application dispatches no jobs |
| deploy / backup / restore / `migrate` CLI | 1–3, briefly |
| operator `psql` | 1+ |
| Postgres `max_connections` (cluster default) | 100 |

The application holds one connection per FPM child, so a fully busy box uses ~20 of
100. `pm.max_children` past ~80 will make an *operator's* `psql` fail with `FATAL: too
many connections` before it affects a user's request; there is no PgBouncer in the
documented topology to absorb that, so raise `max_connections` in the same change as
the pool.

**Session rows are self-pruning.** `SESSION_DRIVER=database` writes one `sessions` row
per browser session; `config/session.php` sets `'lottery' => [2, 100]`, so ~2% of
requests call the handler's `gc()`, which deletes rows older than `SESSION_LIFETIME`
through the shipped `sessions_last_activity_index`. `deploy/lib/retention.sh` does not
touch `sessions`, so the lottery is the only mechanism — if the driver changes to
`file`/`redis` for another reason, drop the table instead of leaving it populated.

**Stalled transactions.** The cluster default is `idle_in_transaction_session_timeout =
0`, i.e. disabled: a transaction left open by a killed request holds its connection and
its locks until the process dies. On this topology (no worker processes, one connection
per child) that is recoverable by reloading FPM, but it is a maintenance-window
operation. Setting it to `60s` and `statement_timeout` to `30s` is recommended for
production clusters; neither is enforced by this repository, and `deploy.sh` will not
set them, because they are cluster-level settings that belong to the DBA's file, not to
a release.

**Reading `pg_stat_statements`.** The extension is not installed by the application's
migrations and it is **per-database**, so both halves are needed:

```bash
# 1. load it (postgresql.conf, then restart — a reload is not enough)
shared_preload_libraries = 'pg_stat_statements'
# 2. per application database
psql -U postgres -d toefl_house -c 'create extension if not exists pg_stat_statements'
# 3. then the interesting question, top queries by total time
psql -U postgres -d toefl_house -c \
  "select calls, round(total_exec_time::numeric,1) as total_ms, rows, left(query,80)
     from pg_stat_statements order by total_exec_time desc limit 15"
```

`scripts/runtime/perf-envelope.php --task=stats` wraps exactly that for the dev
databases, with `--task=reset` to zero the counters before a run; without the reset the
numbers describe the whole life of the cluster rather than the thing you just changed.

**What the read paths cost.** A single authenticated API request over the identity
surface issues 24 queries with one active branch, and that number is flat in row count
(measured at 1 and at 1,001 visible rows:
`tests/Feature/Performance/ReadPathEnvelopeTest.php`). Two of the 24 are the list; the
rest is the authenticated-request backdrop — session start, the employee-session check,
and resolving `identity.admin` across organization, campus, position, assignment, grant
and delegation rows. The backdrop is where the growth risk is, and it grows with the
*tenant's shape*, not its volume: **13 queries per additional active branch**, because
`authorizedBranches()` resolves the canonical decision once per branch (measured 24 /
37 / 76 / 167 queries at 1 / 2 / 5 / 12 active branches,
`tests/Feature/Access/AuthorityResolutionBranchSlopeTest.php`). A single-branch
installation never sees this; a district with 20 branches pays ~260 queries of authority
resolution per API request while its tables stay the same size. Gate F reduced the fixed
part (HR eligibility was consulted four times per decision and is now memoized once —
30 → 24 queries, ~15% off p50) and deliberately left the per-branch loop alone, because
that loop is a fail-closed design choice; collapsing it is an authorization decision to
record in `docs/05-SECURITY-RBAC-GOVERNANCE.md`, not an optimization to slip in. If a
dashboard starts polling the API, the first lever is that loop, and the second is the
number of active branches an actor's grants reach.

**The tail is set by the pool, not by SQL.** Measured on a 2-core box with the same
request (~15 ms of work): one `php -S` worker produced mean latencies of 12 / 46 / 74 /
182 ms at 1 / 8 / 16 / 32 concurrent clients, while eight workers stayed at 13 / 34 / 31
/ 41 ms. Uncontended the two are the same request; under overlap a single server turns
every additional in-flight request into latency. FPM behaves like the multi-worker case,
so the practical consequence for this deployment is that `pm.max_children = 20` is what
absorbs a morning when every branch opens a console at once, and requests beyond 20
start waiting regardless of how fast the queries are. If a burst ever shows multi-second
latency, check `max_children` and the box's core count before optimizing a query.

**What is not known.** The students, reporting, finance and payroll read paths are not
measured at volume: their tables refuse a copied fixture (`Gate F` §5 of
`docs/AUDIT-2026-09-08-GATE-EVIDENCE.md` explains the guard chain), and building 1,000
legally admitted students costs ~110 s of fixture time per volume point, which does not
belong in a default suite. Sustained load above 8 concurrent clients has never been
measured, and no load generator is part of the toolchain.

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
