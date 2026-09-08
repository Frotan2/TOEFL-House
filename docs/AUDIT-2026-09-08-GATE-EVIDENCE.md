# Production-readiness audit — gate evidence (2026-09-08)

Companion to `AUDIT-2026-09-08-PRODUCTION-READINESS.md` and
`AUDIT-2026-09-08-RECONCILIATION.md`. Those files record findings and
decisions; **this file records execution**. Each gate is a claim about
operational behaviour that was previously only asserted by documentation or
reviewed by reading, and each was discharged by running the real thing and
keeping the output.

Method, applied to every gate:

1. Reproduce the behaviour on a provisioned runtime, not a mock: real
   PostgreSQL 18.4, real PHP-FPM, real `git` clone of the repository, real
   `composer`/`npm ci`, real Chromium driving the deployed front end.
2. When a defect appears, fix the root cause in the repository, add a test that
   would have failed before the fix, and re-run the step.
3. Record substitutions explicitly. A sandbox cannot execute every host
   facility; where a component was reproduced rather than installed, that is
   labelled rather than described as a pass.

Runtime: PHP 8.4.14 (CLI+FPM), Composer 2.9.2, PostgreSQL 18.4 (server and
client tools at the same minor, so `pg_dump`/`pg_restore` version skew is not in
play), Node 22.22.3 / npm 10.9.8. Deployment scripts were rehearsed against a
local clone (`REPO_URL`), because the GitHub credential available in this
session expired mid-work; the clone path is otherwise identical.

**Artifacts.** Raw transcripts (`logs/deploy*.log`, `logs/browser-edge-e2e.log`,
`logs/drill/*`, `logs/csp-inventory.json`, `logs/csp-probe-{NEW,OLD}-release.json`,
`logs/deploy1[123]-edgeconfig*.log`), the rehearsal drivers (`rehearse-deploy.sh`,
`dr-drill.sh`, `edge/edge.mjs`), the Gate E measurement scripts
(`csp-inventory.mjs`, `csp-probe.mjs`) and the fingerprint helper live in the audit
sandbox outside this repository, so they are cited by path rather than committed: they are host
evidence, not application inputs. Quoted output below is verbatim from those
files. Commands that are *part of the product contract* (`deploy/*.sh`,
`scripts/runtime/*`) are in the repository and are named as such.

## Gate status

| Gate | Subject | Verdict |
| --- | --- | --- |
| A | Deployment rehearsal in the documented production topology | **PASS**, one labelled substitution (nginx reproduced by a TLS→FastCGI edge; its config is static-reviewed only) |
| B | Backup and disaster recovery | **PASS**, four limits recorded (off-host durability, cron schedule, `age` encryption, dataset size) |
| C | Migration forward-safety and rollback discipline | **PASS**, limits recorded (the `down()` chain is exercised as far as the one-way policy allows; no production-volume `ALTER TABLE` measurement — that is Gate F) |
| D | Error surface and observability | **PASS with recorded gaps** — no leakage on any measured path; two serious defects found and fixed (readiness probe, FPM reload); envelope normalisation, request id and metrics/alerting recorded as open |
| E | Content Security Policy | **PASS**, one gap recorded (no violation collector) — CSP now enforced at both layers, injection blocked in a real browser with a pre-CSP control, and the gate found a second defect: nothing installed the repo's edge config, so headers declared there never reached the web server |
| F | Performance and capacity | not yet executed |


---

## Gate A — Deployment rehearsal in the documented production topology

Executed 2026-09-08, 18:24–19:25 UTC, on the provisioned runtime (`.runtime/`:
PHP 8.4.14 CLI+FPM, Composer 2.9.2, PostgreSQL 18.4, Node 22.22.3 / npm 10.9.8).
Everything below was **run**, not reasoned about. The exact commands are in
`/home/user/evidence/gates/rehearse-deploy.sh` and the raw transcripts in
`/home/user/evidence/gates/logs/`.

### 1. What was executed

`deploy/deploy.sh <ref>` end to end, twice, plus `--rollback` three times:

| Run | Ref | Result |
|---|---|---|
| rehearsal 1 | `ce7a837` (pre-fix) | **died, exit 126** — `backup.sh: Permission denied` (defect A-3) |
| rehearsal 2 | `ce7a837` (pre-fix) | **died, exit 2** — retention pipeline (defect A-4) |
| `deploy5` | `b4b5915`-dev | **died, exit 1** — `--count` on a virgin DB (defect A-5) |
| `deploy6` | `b4b5915`-dev | **died, exit 1** — unquoted `APP_NAME` in *my* env file (operator error, not a repo defect) |
| **`deploy7`** | `b4b5915` | **exit 0 — release live and healthy** |
| **`deploy8`** | `b4b5915` | **exit 0 — re-deploy; retention kept 4 releases** |

Successful run, verbatim milestones from `logs/deploy7.log`:

```
[deploy] deploying ref 'arena/01a081d4-toefl-house' to .../releases/20260908191433
[deploy] php-fpm pool configuration verified            <- step 0 (added in 69baa0b)
[deploy] source: commit b4b59152204d91de46f6dce57de55e1af46b787d
  Installing laravel/framework (v12.67.0) … (73 packages, --no-dev)
ENVIRONMENT LOCK: 8/8 satisfied                         <- step 2b, delegated (ce7a837)
npm ci --engine-strict: added 171 packages; vite build: 12 chunks, built in 1.61s
[backup] dump verified (pg_restore --list OK)           <- real pg_dump 18.4, step 5
[backup] applying retention (keep last 14)
[backup] backup complete
  Creating migration table ......... 4.97ms DONE
  2026_08_25_000001_create_organizations_table … 185 migrations on an EMPTY database
[deploy] verifying release at https://127.0.0.1:8443/health
[deploy] deployment OK: release 20260908191449 (commit b4b5915) is live and healthy
```

Real 185-migration replay against a never-migrated `toefl_house_prod` database,
then activation behind a TLS edge that serves `public/` only.

### 2. Journeys through the deployed release (not the working tree)

`current -> releases/20260908191650`; PHP-FPM pool `[toefl-house]` on a unix
socket; TLS edge on :8443; HTTP :8080 redirects.

* `GET /health` → `200 {"status":"ok","service":"The TOEFL House","environment":"production","checks":{"database":"ok","application_key":"ok","frontend_build":"ok"}}`
* `GET /login` → 200 (7 428 bytes of Blade-rendered HTML)
* `GET /build/assets/access-BYV4IkXp.js` → 200, `Content-Type: text/javascript`
  (static asset served from the release docroot, never through PHP)
* `GET /.env` → **403**, `GET /index.php` → **403**, `GET http://…/login` → **301**
* All five security headers present on every response including 403/502 (`always`)
* `FirstRunBootstrapSeeder` executed **inside the release**: organization, Owner
  role with 133 capabilities, account `runtime.owner`
* **`npm run verify:browser` against `https://127.0.0.1:8443` → 21/21 passed**
  (`logs/browser-edge-e2e.log`): real Chromium, login through the real form,
  all 14 authenticated consoles rendered by the built React bundle,
  25 `/api/v1` calls observed and 25 succeeded, zero console errors, zero
  failed network requests, sign-out ends the session.

### 3. Rollback semantics, exercised

* **Compatible target** → `[deploy] target release verified against the live
  schema: 20260908191449` then `rolled back application -> … (schema
  compatibility verified)`, exit 0, 0.08 s; health after rollback: 200 for
  `/health` and `/login` (the edge follows the symlink, so this is the rolled-
  back release answering).
* **Target missing an applied migration** (file deleted from the older release)
  → exit 1, `current` **unchanged**, and the operator reads:
  `refusing application-only rollback: live database contains migration(s)
  absent from target release 20260908191449:
  2026_08_25_000005_create_departments_table .`
* **Database unreachable** (`DB_PORT` pointed at a closed port) → exit 1,
  `current` unchanged, `cannot verify schema compatibility … (probe exit 2,
  probe said: … connection refused)` — an unreadable state is never treated as
  "compatible" (the earlier version of the probe *did* have that hole; it is now
  locked by `test_it_reports_unverifiable_instead_of_compatible_when_the_database_cannot_be_reached`).

### 4. Defects found by this rehearsal (each reproduced → fixed → tested → suite-green)

| # | Commit | Defect |
|---|---|---|
| A-1 | `ce7a837` | Step 5/6 read schema state via `php artisan tinker`, which is not a dependency → *every* deployment died at "unable to read current migration state before deployment". Replaced by `deploy/schema-compatibility.sh` (psql, dependency-free, 0/1/2 exit contract). |
| A-2 | `ce7a837` | Second copy of the runtime contract: exact pins (PHP 8.2.27 / Node 22.23.1 / npm 10.9.2 / PG 18.4) that rejected the certified runtime (8.4.14 / 22.22.3 / 10.9.8). deploy.sh now delegates to `verify-environment.mjs`; `npm ci --engine-strict` makes `package.json` engines binding; engines became the lock's ranges. |
| A-2b | `62ff83d` | `deploy/schema-compatibility.sh` shipped without the exec bit while documenting path-style invocation. |
| A-3 | `3c3179d` | `deploy/deploy.sh`, `backup.sh`, `restore.sh` committed 100644 → the documented `./deploy/deploy.sh` could not run, and the internal backup call aborted mid-deploy (exit 126, reproduced in rehearsal 1). |
| A-4 | `c2742e8` | Retention pipelines under `set -o pipefail`: `ls` on a non-matching glob exits 2 and `grep -v` with nothing to remove exits 1 → a *verified* dump was reported as a failed backup, and a *healthy live* release would have been reported as a failed deploy. Now one tested helper, `deploy/lib/retention.sh`. |
| A-4b | `73392eb` | My own fix sourced `deploy/deploy/lib/retention.sh`; added a test that resolves every `source` line in `deploy/*.sh`, and normalised `scripts/runtime/env.sh` (sourced-only) to non-executable. |
| A-5 | `82c99c3` | `--count` referenced `public.migrations` inside a `to_regclass` guard → parse-time failure on a never-migrated database, i.e. on the *first* deploy to any new host. |
| A-6 | `b4b5915` | Step 7 hardcoded `/etc/php/*/fpm/pool.d/toefl-house.conf` to learn the web user; when absent it silently used `www-data` and `chown` aborted the deploy *after* migrations ran. Pool location now configured once (`PHP_FPM_POOL`), `WEB_USER` overridable, and a failed chown is fatal only when ownership is genuinely wrong, with the remedy in the message. |
| A-7 | (this commit) | The refusal message interleaved the probe's stderr (and any loader noise) into the operator-facing error; stdout is now the machine-readable list, stderr is quoted only in the "unverifiable" branch. |
| A-8 | `69baa0b` | `deploy/php-fpm.conf` carried `opcache.*` as pool directives → `php-fpm -t` exit 78, "unknown entry 'opcache.enable'": the documented FPM install could not start. Moved to `deploy/opcache.ini` (conf.d), and deploy.sh step 0 now tests the installed pool. |

### 5. Substitutions (host facts, labelled — not repo behaviour)

* **No nginx binary** is installable here (no root; no PCRE/zlib/OpenSSL headers;
  no package source). The edge is a purpose-built TLS terminator + FastCGI
  relay (`/home/user/evidence/gates/edge/edge.mjs`) that mirrors
  `deploy/nginx/toefl-house.conf` location-for-location: docroot `public/`,
  `try_files $uri $uri/ @php`, `= /health`, `~ /\.` deny, `~ \.php$` 403, the
  five `add_header … always`, 301 with `$host` (port excluded), TLS 1.2/1.3,
  `fastcgi_param SCRIPT_FILENAME $document_root/index.php`, `fastcgi_read_timeout
  60s`, and an access log. **The repo's nginx config file itself was therefore
  not executed** — it remains static-reviewed; every *semantic* it declares was
  reproduced and measured.
* **FPM pool**: derived from `deploy/php-fpm.conf` with only host-bound values
  rewritten (socket → evidence path, `user`/`group` → the unprivileged sandbox
  account, log paths). All directives, pm sizing, `security.limit_extensions`,
  slowlog and opcache policy (via `deploy/opcache.ini`) unchanged; `php-fpm -t`
  accepts it. `deploy.sh` step 0 verified it.
* **TLS leaf**: self-signed via `openssl req -x509` with SAN
  `toeflhouse.example.com`, `localhost`, `127.0.0.1`. The health check verifies
  it through `CURL_CA_BUNDLE` — the check was **not** weakened with `-k`. The
  browser run needed `--ignore-certificate-errors` and
  `NODE_TLS_REJECT_UNAUTHORIZED=0` for the same reason.
* **Composer** ran with `COMPOSER_DISABLE_NETWORK=1` (packagist is unreachable
  from this sandbox): a real `install --no-dev` against `composer.lock` from the
  warm cache, verified to produce a production-only vendor tree (no phpunit).
* **pg client tools**: PostgreSQL 18.4 `pg_dump`/`pg_restore`/`psql` were not
  present in the sandbox and are a documented prerequisite of these scripts
  (`postgresql-client` ≥ server). They were supplied out-of-band, matching the
  server's exact version, so `backup.sh`/`restore.sh` ran their real code path.
* **Release source**: `REPO_URL=/home/user/TOEFL-House` (a local clone) because
  the GitHub credential Arena injects expired mid-session, so pushes after
  `3c3179d` are local-only and the private repo is not anonymously fetchable.
  The clone/checkout logic is identical; re-running against `origin` is the same
  command without `REPO_URL`.
* `systemctl`/`service` and `nginx -t` in the activation step are absent here
  and self-guarded (`|| true`); the FPM reload they perform was issued
  manually where it mattered (opcache `validate_timestamps=0`).

### 6. Verdict

**Gate A: PASS with the substitution in §5** — the deployment procedure, the
release layout, the backup precondition, the migration replay, the activation
gate, the health verification, the retention and all three rollback outcomes
were executed for real against PostgreSQL 18.4 with a real PHP-FPM and a real
browser, after five deployment defects were reproduced and fixed. The only
element not executed is the nginx binary; its configuration is unchanged and
its behaviour was reproduced and measured rather than assumed.

---

## Gate B — Backup and disaster recovery, exercised

Audited component: `deploy/backup.sh`, `deploy/restore.sh`, `deploy/lib/retention.sh`,
and the recovery instructions in `docs/operations/production-deployment.md` §13–§14.
Date: 2026-09-08/09 (UTC). Environment: the same live rehearsal cluster built for
Gate A — PostgreSQL 18.4, PHP 8.4.14 FPM behind a TLS edge, database
`toefl_house_prod` with 168 tables, 185 migrations and first-run bootstrap data
(organization "The TOEFL House", Owner role with 133 capabilities, account
`runtime.owner`).

This gate answers one question: **if the database is destroyed, does the documented
procedure bring it back, and does the application work afterwards?** Not "does the
script look right". Everything below was executed against the real cluster.

### 1. Preflight guards, executed

| Case | Command | Result |
| --- | --- | --- |
| Restore without `--confirm`, existing dump | `deploy/restore.sh <newest dump>` | exit 1 — `refusing to restore without an explicit second argument '--confirm' (this overwrites the live database toefl_house_prod)` |
| Restore of a missing file | `deploy/restore.sh /nonexistent.dump` | exit 1 — `backup file not found: /nonexistent.dump` (the file is resolved before the confirmation gate, so a typo is reported as such rather than as a confirmation refusal) |
| `--latest` with an empty backup directory | `BACKUP_DIR=<empty> deploy/restore.sh --latest --confirm` | exit 1 — `no backup file given and no backup found in …`; never an empty filename passed to `pg_restore` |
| Backup without client tools | `PATH=/usr/bin:/bin deploy/backup.sh` | exit 1 — `pg_dump not found on PATH. Install postgresql-client (>= server version).` |
| Restore without client tools | `PATH=/usr/bin:/bin deploy/restore.sh --latest --confirm` | exit 1 — `pg_restore not found on PATH.` — the tools are checked before anything is read or written |

The `pg_*` client used is PostgreSQL 18.4, matching the server (18.4), so
`pg_dump`/`pg_restore` version skew — the classic silent-failure mode of a
"backup that restores nothing" — is not in play.

### 2. The drill: backup → total loss → restore

Driver: `/home/user/evidence/gates/dr-drill.sh` (transcripts in
`logs/drill/`, summary in `logs/drill/summary.txt`).

1. **Fingerprint** the live database: exact per-table `count(*)` plus, for every
   non-empty table up to 20 000 rows, `md5(string_agg(row_text ORDER BY row_text))`,
   aggregated into one content digest. Row counts alone would accept a restore that
   kept the count but lost the data.
2. **Drill meaningfulness preconditions** (added after run 4 exposed the need):
   total rows > 0, at least 5 non-empty tables to digest, `organizations ≥ 1`,
   exactly 1 `runtime.owner` account, `roles ≥ 1`. Without these, a schema-only
   restore of an empty dump "passes" trivially.
3. **Back up** with `deploy/backup.sh`: `pg_dump --format=custom --compress=6
   --no-owner --no-privileges`, then `pg_restore --list` integrity check, then
   retention pruning (keep 14).
4. **Write a row after the backup** (table `post_backup_write`) — this must be
   *lost*, and is the honest measurement of the RPO boundary.
5. **Destroy**: `DROP DATABASE toefl_house_prod WITH (FORCE); CREATE DATABASE
   toefl_house_prod;` — total loss, connections still attached.
6. **Restore** with `deploy/restore.sh --latest --confirm`.
7. **Verify**: fingerprint again (digest must be identical, total rows equal,
   seeded rows present, owner `password_hash` intact), the post-backup table must be
   gone, then the real browser journey against the recovered database.

#### Measured result

| Step | Duration |
| --- | --- |
| `backup.sh` (dump + verify + retention) | 246 ms |
| destroy (drop + create database) | 166 ms |
| `restore.sh --latest --confirm` | 876 ms |
| fingerprint + comparison | 99 ms |
| **RTO (restore + verify, time to serviceable)** | 975 ms |
| Browser journey on the recovered database | 21/21 passed in 16.8 s (real Chromium through the TLS edge, against the recovered database) |

Facts recorded by the run: tables 169, exact rows 346, migrations
185, content digest `7055417d9a2f8cd4b9a1996e34861b40` identical before and after, `organizations`
and the `runtime.owner` account (including its password hash prefix) restored, and
`post_backup_write` correctly absent.

Against the documented objectives: **RPO** = the last nightly backup (a manual
`backup.sh` run was the boundary measured here — the 24 h figure is a schedule
claim, not something the drill can prove; the drill proves only that *a* dump
taken at T restores the state at T). **RTO** — the docs said "minutes for the
expected dataset size"; measured at 168 tables / ~1.3 MB dump it is under a
second on a warm cluster. `deploy/restore.sh` now records the measured number
next to the estimate instead of only the estimate.

### 3. Defects found by this gate (reproduced → fixed → tested)

| # | Defect | How it was found | Fix |
| --- | --- | --- | --- |
| B-1 | `restore.sh` interpolated `$HEALTH_URL` in its closing message but never defined it; under `set -u` **every** restore — including a fully successful one — ended with `HEALTH_URL: unbound variable` and exit 1 | The drill's exit code was asserted, and it came back 1 while the data was demonstrably back | `HEALTH_URL` is now an overridable host input with a default, next to the other host facts |
| B-2 | `pg_restore --clean --create` on a target database that still exists emits `DROP DATABASE` → "cannot drop the currently open database", then `CREATE DATABASE` → "already exists"; pg_restore ignores both, restores everything, and **exits 1** | The first drill run: correct data, exit 1 | `--create` removed. The script checks `pg_database` and creates the target itself (aborting with a named remedy if that fails), then restores `--clean --if-exists`. Pinned by `RestoreProcedureContractTest::test_pg_restore_is_not_asked_to_create_the_database` |
| B-3 | `phpunit.xml`'s `<env>` entries were not `force`d, so a shell-exported `DB_DATABASE` wins over the declared test database — and `Tests\TestCase` uses `RefreshDatabase`, whose first step is `migrate:fresh` (drops every table). **The suite can delete a production database.** | The drill's seed data vanished between two runs; only the exported `DB_DATABASE=toefl_house_prod` in the invoking shell explains it, and a canary table confirmed the suite had run `migrate:fresh` against `toefl_house_prod` | `force="true"` added to the isolation-critical entries (`APP_ENV`, `DB_CONNECTION`, `DB_DATABASE`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION`) |
| B-4 | …but `force="true"` is **not sufficient** in this stack: PHPUnit writes forced values to `getenv()`/`$_ENV`, while PHP also copies the process environment into `$_SERVER` (`variables_order=EGPCS`) and Laravel's `env()` reads `$_SERVER` first. Measured: with `force="true"` present, `config('database.connections.pgsql.database')` was still `toefl_house_prod` and the canary was still dropped | The canary experiment run *after* applying B-3's fix — the fix looked right and was not | `tests/bootstrap.php` (already the configured bootstrap) re-asserts every `force="true"` entry into `$_SERVER`, `$_ENV` and `getenv()`, making `phpunit.xml` authoritative. Proven executably: a child process with a hostile `$_SERVER['DB_DATABASE']` ends up pinned to `toefl_house_test` in all three channels |
| B-5 | Defense in depth for the same hazard from the other direction: a stale `bootstrap/cache/config.php` (or any future precedence change) can make the *resolved* connection differ from the declared one, with no warning anywhere | Identified while designing B-4; a cached config makes Laravel ignore `.env` and the test environment entries entirely | `Tests\Support\TestDatabaseGuard` runs in `TestCase::refreshApplication()`, i.e. after the container exists and **before** `setUpTheTestEnvironment()` reaches `setUpTraits()` where `RefreshDatabase` migrates. Any divergence aborts the run with the remedy in the message |
| B-6 | `restore.sh`'s header documented usage without the mandatory `--confirm`, so the documented command always hit the refusal | Reading the script's own usage block against its behaviour | Usage block corrected, and `TestDatabaseIsolationTest`-style text pin added: `test_usage_documents_the_required_confirm_argument` asserts every documented invocation carries `--confirm` |

Two notes on *why B-5's guard sits where it sits*: the obvious placement,
`setUp()` before `parent::setUp()`, fails with `Target class [config] does not
exist` because the application is only created inside `parent::setUp()`; and a
guard that reads the declaration from `phpunit.xml` must read the **`value`
attribute**, not the element text — `(string) $env` is `''`, and an empty
declaration silently disables the guard. Both were hit while building this and are
now covered by assertions (`test_the_guard_aborts_when_the_resolved_database_diverges_from_the_declaration`
asserts the parsed declaration equals `toefl_house_test`, so a broken parse cannot
quietly disarm it; the same trap in `tests/bootstrap.php` blanked `CACHE_STORE` and
surfaced as `Cache store [] is not defined` during application boot).

### 4. What was *not* proven here (substitutions and limits)

* **No off-host durability.** The scripts write dumps into `BACKUP_DIR` on the same
  filesystem as the data. `docs/operations/production-deployment.md` already tells
  operators to ship dumps to object storage; that step cannot be executed or measured
  in this sandbox, so "the backup survives the loss of the host" is **not** verified
  by this gate — only "the backup survives loss of the database".
* **No cron.** The "nightly" schedule (and therefore the RPO figure of 24 h) is a
  deployment-time configuration claim. The drill proves the mechanism at an arbitrary
  time; `backup.sh` is invoked exactly as the documented cron line invokes it.
* **Optional `age` encryption** is unverified: `age` is not installed and not
  installable here (no package network). The script's behaviour in that case is
  deliberate — it logs `age not installed; leaving unencrypted` rather than failing
  silently or dropping the backup — but "encryption at rest for dumps" is not proven.
* **Single-instance, same-cluster restore.** `--clean --if-exists` restores into the
  cluster's own database. A cross-version or cross-provider restore (e.g. RDS) is
  not exercised.
* **Timings are from a small database.** 168 tables / ~1.3 MB is the *current*
  production-shaped dataset size, not an expected peak; the RTO claim is therefore
  "measured at this size, budget minutes at larger sizes", as recorded in the script
  header.
* The drill destroys and recreates `toefl_house_prod` in the rehearsal cluster only.
  It is destructive by design and must not be pointed at a live host without editing
  `DB`/`BACKUP_DIR` at the top of `dr-drill.sh`.

### 5. Verdict

**PASS**, conditional on the caveat that off-host durability and the nightly schedule
are configuration claims this environment cannot execute. Everything the repository
itself controls for recovery — dump creation and integrity verification, retention,
the refusal semantics of the restore, restore-then-verify fidelity down to row
content, the RPO boundary, and the application's behaviour on recovered data — was
executed, and five defects were found in the process, two of which (B-1, B-2) made
the documented recovery procedure report failure on success and one of which (B-3/B-4)
let the test suite delete a production database.

Test coverage added for this gate: `tests/Feature/Deployment/RestoreProcedureContractTest.php`
(6 tests, including `test_a_successful_restore_exits_zero`, which runs the script
end-to-end against stubbed `pg_restore`/`psql` so the exit code is verified with no
server present) and `tests/Feature/Deployment/TestDatabaseIsolationTest.php`
(6 tests: forced entries, dedicated database name, deliberately overridable
connection coordinates, executable bootstrap-precedence proof, guard fires on
divergence, guard quiet on match).

---

## Gate C — Migration forward-safety and rollback discipline

Executed 2026-09-08 on a scratch database (`toefl_house_c_scratch`) created,
migrated, rolled back and re-applied from the working tree, plus the three
`deploy.sh --rollback` outcomes already measured in Gate A §3. The question this
gate answers is the one `docs/operations/production-deployment.md` §15 asserts
without proof: *is the forward-only migration policy actually safe, and what
exactly happens when a migration or a rollback is interrupted?*

### 1. What was executed

| Step | Command | Result |
| --- | --- | --- |
| Full chain, empty database | `php artisan migrate --force` | **185 migrations, 1.74 s** → 168 tables, 392 indexes, 285 triggers |
| Interrupted deploy | injected migration that creates a table, inserts a row, then `throw new RuntimeException('GATE_C_INJECTED_FAILURE')` | artisan exits **1** with the message; **no residue**: `to_regclass('public.gate_c_probe')` is NULL and `migrations` gained no row |
| Recovery from it | remove the file, `migrate --force` | exit 0, `Nothing to migrate.` — the release is resumable without repair |
| Rollback at the head | `migrate:rollback --step=2` | reverts `…000190`, then **stops** at `…000189`, whose `down()` throws: `Employment-settlement ledger authority is one-way; restore from a reviewed pre-convergence baseline rather than weakening accounting history.` Leaves **184** applied — batch rollback is **not atomic** |
| Re-apply | `migrate --force` | back to 185 applied / 168 tables / 392 indexes / 285 triggers |
| Data fidelity across that cycle | content fingerprint before vs after | every business table identical in row count and content digest; the only delta is the `migrations` ledger (`184 → 185`) |
| Post-restore migration (Gate B handoff) | `migrate --force` in the active release, against the database recovered by `restore.sh` | `Nothing to migrate.` — a verified restore needs no schema repair step, so §14's "re-run migrations" line is a safety net, not a requirement |
| `CREATE INDEX CONCURRENTLY` audit | scan of all 185 migrations | **0 occurrences** → no migration needs to run outside the per-migration transaction today |

### 2. `down()` coverage, measured

`scripts/database-migration-audit.php` reports it, and CI now enforces it:

```
DOWN() COVERAGE: 167 reversible, 17 explicit one-way, 1 explained no-op, 0 undocumented empty, 0 missing
```

The three categories are the whole point. A migration either reverts, or **refuses
with a message that names the alternative action** (all 17 do, 86–140 characters,
e.g. *"Funding source organization convergence is one-way; do not erase Finance
tenant provenance or reopen cross-organization allocation."*), or states in `down()`
why nothing may be undone (the standard chart-of-accounts seed: rows are referenced
by journal lines and the seed is ignored on re-run). What is not acceptable is an
empty `down()` with no explanation: `migrate:rollback` reports success for it while
leaving the schema advanced, which is how an operator comes to believe a rollback
happened that did not.

### 3. Rules added, and the two false positives that produced them

No behavioural defect was found in the migration chain itself — the design held
under every attempt to break it. What was added is enforcement (each rule executed
against fixture migrations, `tests/Feature/Deployment/MigrationDisciplineAuditTest.php`,
8 tests):

| Rule | Why it is worth a CI gate |
| --- | --- |
| every migration declares `down()` | an undocumented rollout with no undo path |
| no unexplained empty `down()` | silent success during a rollback (see §2) |
| a refusing `down()` must name an alternative (≥ 40 chars, not a bare "irreversible") | an operator with no next step in an incident |
| no `CREATE INDEX CONCURRENTLY` in a migration | it cannot run inside the transaction Laravel opens per migration |

Two of these first flagged **correct code**, which is recorded because the failure
mode is instructive: the refusal-message rule delimited the `throw` statement by
scanning for `;`, and these messages are two clauses ("X is one-way; restore from
…"), so a 140-character message read as empty; and the index rule matched the
English word `concurrently`, catching prose such as *"a repeated (or concurrently
referencing) request"* in migration comments. Both are now regression-tested in
both directions (`test_a_refusal_with_a_semicolon_in_the_message_is_accepted`,
`test_prose_that_merely_mentions_concurrency_is_not_flagged`,
`test_the_audit_accepts_a_documented_no_op_down`).

### 4. Documentation corrected as a result

* `docs/operations/production-deployment.md` §15 now states the two measured
  properties above — resumability after a failed migration, and the non-atomicity
  of `migrate:rollback` — and says in as many words never to use `migrate:rollback`
  as a production recovery step.
* `docs/AUDIT-2026-09-08-RECONCILIATION.md`'s open-item row that asked for exactly
  this ("`migrate` to N, `migrate:rollback --step=…`, re-apply, record") is closed
  with the numbers.

### 5. Limits of this gate (labelled, not glossed)

* **The `down()` chain cannot be exercised "end-to-end" by design.** `…000189` sits
  one step below the head and refuses, so a full 185-step reverse walk is impossible
  while the policy that created it stands. The register's claim is therefore closed
  as *far as the policy allows*: the head reverts and re-applies cleanly, and every
  refusal is deliberate, specific and now enforced — not "all 185 `down()` methods
  were run".
* Fidelity was measured on a seeded but small database (342 rows). Rollback of the
  **first** migrations in the chain (table creation) was not attempted: they are only
  revertible in reverse-dependency order and the scratch database is cheap to rebuild,
  so the interesting case for production was the head of the chain.
* Lock and index-build cost on populated tables is Gate F, not this one; nothing here
  measures `ALTER TABLE` behaviour at production volume.
* The one-way refusals are the reason the **backup** in Gate B is load-bearing: with
  17 migrations that cannot be reverted, `restore.sh` is the only schema-recovery path
  that exists. That is why its exit code had to be made truthful before this gate could
  be called a pass.

### 6. Verdict

**PASS.** The forward-only policy is executable as documented: the chain applies in
1.74 s, an interruption leaves no half-applied schema, a verified restore needs no
further migration step, and the rollback refusals are deliberate rather than absent.
One hazard was documented (non-atomic batch rollback) and four static rules added to
CI with bidirectional fixtures, after two of my own first drafts of those rules
produced false positives that the fixtures now prevent.

---

## Gate D — Error surface and observability

Executed 2026-09-08 against the **deployed** release through the TLS edge (not the
working tree): a real `pg_ctl -m fast stop` of the cluster under `APP_ENV=production`,
`APP_DEBUG=false`, `SESSION_DRIVER=database`, `CACHE_STORE=database`,
`LOG_CHANNEL=stack`, `LOG_LEVEL=warning` — the configuration the procedure ships.

### 1. What a client sees, measured

| Request | Response | Assessment |
| --- | --- | --- |
| `GET /this-page-does-not-exist` | `404 text/html`, 6659 bytes, app layout | fine |
| `GET /api/v1/management` (unauthenticated) | `401 {"error":"authentication_required","message":"Sign in as an employee to continue."}` | fine — the app's own envelope |
| `GET /api/v1/nope` | `404 {"message":"The route api/v1/nope could not be found."}` | **inconsistent envelope**: no `error` code field |
| `POST /api/v1/identity/people` (no token) | `419 {"message":"CSRF token mismatch."}` | same inconsistency |
| `DELETE /login` | `405` HTML with `noindex,nofollow,noarchive` robots meta | fine |
| `GET /login` without a session, `POST` without a token | `419` "Page Expired" HTML | fine for a browser |
| every response above | `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, HSTS | present (see Gate E for what is *not* present) |
| every error body, grepped for `SQLSTATE`, `PDOException`, the DSN user/database, `vendor/`, `#0` frames | **nothing leaked** | clean, including during the database outage |

### 2. The database outage, which is the case that matters

With PostgreSQL stopped and nothing else changed:

| Probe | Observed | Correct? |
| --- | --- | --- |
| `/health` | was `500 text/html` "Server Error" (6665 bytes) **before the fix**; `503 {"status":"error","checks":{"database":"error","application_key":"ok","frontend_build":"ok"}}` **after** | the fix is what the documentation already promised |
| `/up` | `200` | by design — liveness, not readiness |
| `/login` | `500` | correct: it genuinely needs the database |
| `/api/v1/management` | `500 {"message":"Server Error"}` | correct status, JSON maintained |
| after restart | `/health` `200` in ~20 ms, no FPM reload required | the app self-heals; a connection per request |

`/health`'s 500 was not the controller's answer. The route lived in the `web`
group, so `StartSession` opened a **database-backed session** before
`HealthController` ran and threw there; the controller's own (correct) try/catch was
never reached. The consequence is the worst available one for an operator: the
endpoint whose entire job is to distinguish "database down" from "application broken"
answered with a generic application-broken 500, to the load balancer that was about
to act on it — and to `deploy.sh`, which polls the same URL to decide whether the
release it just activated is healthy.

Fix: `->withoutMiddleware([StartSession, ShareErrorsFromSession, ValidateCsrfToken, VerifyCsrfToken])`.
Removing only the session middleware is *not* enough and the first attempt did
exactly that: `VerifyCsrfToken::addCookieToResponse()` reads
`$request->session()->token()` on GET requests too, so the exception simply moved to
the next middleware (`Session store not set on request`) and the probe still answered
500. A public read-only side-effect-free GET has nothing for CSRF to protect;
`SecurityHeaders` is appended globally, so the response headers are unchanged
(`SecurityHardeningFeatureTest`, 8 tests, still green).

### 3. Where the evidence of the failure went — and what that exposed

During the outage the exception **was** logged, with a full stack trace (29 KB from
two requests). What made it worth reading was the *location*: it was written to
`releases/20260908191449/storage/logs/laravel.log`, while `current` pointed at
`releases/20260908191650`. `deploy.sh` moves a symlink, and with
`opcache.validate_timestamps=0` the running master never stats a PHP file again, so
the pool was still executing the **previous release** — the reload step was written
`( systemctl reload … || service … reload || true )` and this host has neither. The
deployment had reported "deployment OK", `/health` had agreed, and the live
application was one release behind while its migrations had already advanced the
database.

Two defects, both fixed:

* **`deploy.sh` activation now requires a proven reload** — `PHP_FPM_RELOAD_CMD`,
  then `systemctl`, then `service`, then `kill -USR2` on the master read from
  `PHP_FPM_PID_FILE`; if none succeeds the symlink is restored to the release that is
  actually executing and the deployment **fails** with the remedy and the caveat that
  the database migration is not undone. Rehearsed both ways: without an override the
  deploy now stops at that step (`logs/gateD-negative-reload.log`, exit 1, `current`
  unchanged); with `PHP_FPM_PID_FILE` set it reloads via SIGUSR2 and activates
  (`logs/gateD-positive-reload.log`, exit 0), and the next outage wrote its log into
  the **new** release's `storage/logs/` with the old release's absent — the same
  detector, inverted.
* **Log location is now configurable and documented** (`LOG_PATH`): with release
  retention keeping 4 directories, a release-local log is a log that gets deleted,
  so the record of an incident can vanish while the incident is open. The
  `emergency` channel deliberately keeps the local path — it is the target used when
  the configured one fails, so it must not live on the storage that just failed.

### 4. Recorded, not fixed (this is where the honest limit of the audit sits)

* **Two error shapes on one API.** The application's envelope is
  `{"error","category","message","correlation_id","retryable"}` (built for
  `DomainError` in `bootstrap/app.php`), but framework-generated responses — 404,
  419, 405, and every 500 — bypass it and emit a bare `{"message": …}`. So clients
  must special-case "did the domain reject me or did the framework reject me", and
  the `correlation_id` that the envelope advertises exists exactly when it is *not*
  useful. A `Handler::renderable`/`respondUsing` normalisation would close it; it is
  a small API-contract change with front-end consequences, so it is recorded for
  decision rather than slipped into an operations fix.
* **No request identifier.** Nothing correlates a response with a log line: no
  `X-Request-Id` in any response, and log entries carry no request id. With one
  `laravel.log` and no metrics, "which burst caused this" is unrecoverable.
* **No metrics, no alert channel, no pager path.** `config/integrations.php` has no
  transports; nothing reports a rising 5xx rate, a failed job, or `/health` flipping
  to 503 — the deployment procedure's own "if it fails, roll back" logic is the only
  place a failure is acted on. §18 now states this in the docs instead of implying a
  monitoring story that does not exist.
* **Log volume is unbounded by default** unless an operator opts into
  `LOG_STACK=daily`; nothing enforces or even checks it.
* The unauthenticated-error paths that *did* stay clean (no stack, no DSN, no
  internal paths) were verified by grep over the captured bodies only for the cases
  exercised above; a debug-mode misconfiguration is not caught by anything, since no
  check asserts `APP_DEBUG=false` in the running process (only in the `.env` text).

### 5. Verdict

**PASS with recorded gaps.** No information leaked on any path measured, including a
live total database outage, and the application self-heals when the database returns
without needing a reload. The gate found the two most serious defects of the whole
audit, because it exercised the *failure* path against the *deployed* artefact rather
than the working tree: a readiness probe that could not report the one condition it
exists for, and an activation step that could report success while the pool kept
serving the previous release. Both are fixed, covered by tests
(`HealthProbeContractTest`, 5 tests; `PhpFpmActivationReloadTest`, 4 tests;
`LogLocationTest`, 4 tests) and verified executably against the live deployment in
both directions — including the non-vacuity check that the health tests fail with
"Failed asserting that 500 is not identical to 500" when the fix is reverted.
What remains is recorded in §4: a normalised API error envelope, a request id, and
any form of metrics or alerting.


---

## Gate E — Content Security Policy

### 0. The recorded basis was wrong, and the correction matters

The status table this gate replaces said the repository had five shared headers with
"only `ServeFile` setting a per-file policy". `ServeFile` is framework code in
`vendor/`, and this application never reaches it: `DocumentsController` returns
metadata, not file bytes, so no response in the delivery path carried a
per-file CSP. The measured state before this gate was plainer and worse —

```
$ grep -rn "Content-Security-Policy" app/ config/ deploy/ resources/ routes/ tests/
(no output)
```

i.e. **no CSP at all**, at either layer, for an application whose 14 authenticated
consoles execute JavaScript under a session cookie. The reconciliation file
(`AUDIT-2026-09-08-RECONCILIATION.md` §8.2) had the substance right ("there is no
`Content-Security-Policy` anywhere in the delivery path") and the parenthetical
wrong; both are corrected here rather than left to disagree.

### 1. What the deployed pages actually require, measured before writing the policy

A CSP copied from a template gets relaxed until it passes, which is how headers
become decorative. So `evidence/gates/csp-inventory.mjs` drove a real Chromium
against the deployed release through the TLS edge — `/login` plus every console path
taken from the repository's own E2E script — and scanned the shipped bundles:

| measured | result |
| --- | --- |
| inline `<script>` elements / bytes | 0 / 0 on all 15 pages |
| inline event handlers (`onclick=` etc.) | 0 |
| script tags from another origin | 0 (every `<script src>` is `/build/…`, same origin) |
| `blob:` / `data:` scripts, service workers | 0 |
| `<iframe>`, `<object>`, `<embed>` | 0 |
| `eval` / `new Function` / `document.write` in bundles | 0 / 0 / 0 |
| `innerHTML` / `insertAdjacentHTML` assignments | 0 |
| `URL.createObjectURL`, canvas `toDataURL` | 0 / 0 (no document preview leaves the server) |
| `url()` or `@font-face` in built CSS, font requests | 0 `url()`, no font fetch |
| inline CSS that *is* used | 1 `<style>` element (Vite) + 6 `style=""` attributes per page |
| form actions | same-origin `/login`, `/logout` only |

Raw output: `logs/csp-inventory.json`. That is why the policy below needs no nonce
and no hash: there is nothing inline to allow.

### 2. The policy, enforced at both layers

```
default-src 'self'; base-uri 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';
img-src 'self'; font-src 'self'; connect-src 'self'; form-action 'self';
frame-ancestors 'none'; frame-src 'none'; object-src 'none'; worker-src 'none'; manifest-src 'self'
```

Sent identically by `App\Http\Middleware\SecurityHeaders::CSP_PRODUCTION` (documents,
API, errors) and `deploy/nginx/toefl-house.conf` at **server scope** (static files,
404s, 5xx). Deliberate exclusions, recorded in the middleware docblock: no
`upgrade-insecure-requests` (it rewrites same-origin `http:` URLs and would break
`php artisan serve`, protecting nothing here); no nonce (no inline script exists);
`style-src 'unsafe-inline'` and nothing else (`innerHTML` is absent, Vite injects a
`<style>` element, and inline CSS cannot execute script while inline JS can). Local
development keeps working: outside production *and* when `public/hot` parses as an
http(s) origin, the Vite dev-server origin is added to `script-src`/`connect-src`
(with its `ws:` form for HMR) — production never carves out, so a stale `public/hot`
left by a stray `npm run dev` cannot loosen a live deployment.

`frame-src 'none'`/`object-src 'none'` were checked against the one feature that
could have needed them: document/preview flows. `DocumentsController` serves metadata
and never a file URL, and no view opens a `blob:` window — so no framing is required.

### 3. Enforcement, with a control that proves the test is not vacuous

Proving a CSP exists is not proving it bites. `evidence/gates/csp-probe.mjs` runs
six injection attempts against a logged-in session and records three independent
measurements each: the observable side effect the payload would produce, the page's
own `securitypolicyviolation` events (which name the directive), and the number of
requests that reached the web server (read from the edge access log, to separate
"blocked by policy" from "the page was broken"). It was then run **twice**, against
two releases, by moving the `current` symlink and reloading PHP-FPM — which doubles as
a second rollback/roll-forward exercise:

| probe (`evidence/gates/logs/`) | pre-CSP release `20260908201557` | with CSP, release `20260908210006` |
| --- | --- | --- |
| inline `<script>` sets a global | **`EXECUTED`** | `NOT-EXECUTED`, `script-src-elem`, 0 outbound |
| inline `onclick` handler | **`EXECUTED`** | `NOT-EXECUTED`, `script-src-attr` |
| remote `<script src>` | request reached the server | not loaded, `script-src-elem`, 0 outbound |
| cross-origin `fetch` | request reached the server | refused, `connect-src`, 0 outbound |
| injected `<iframe>` to a foreign origin | request reached the server | `SecurityError`, `frame-src`, 0 outbound |
| form retargeted to a foreign origin | **navigation happened** (the page's execution context was destroyed by the POST) | no navigation, `form-action`, 0 outbound |
| same-origin `GET /api/v1/notifications` | 200 | **200 application/json** |
| violations during normal use (14 consoles + login + logout) | — | **0** |
| repository browser E2E against the CSP release | 21/21 (pre-CSP baseline) | **21/21**, 25/25 API calls ok, 0 console errors, 0 failed requests |

Header counts on the live deployment, after the release below was deployed:
`/login` → 2 identical `Content-Security-Policy` lines (middleware + nginx's
`add_header`, which *appends*; the browser enforces the union, and a real browser run
of 21 flows plus 14 console renders shows that costs nothing), `/build/manifest.json`
and `/robots.txt` → 1 (only the web server can header those), and `/favicon.ico`
404 → 2 (error responses keep the header because it is declared with `always`).

### 4. The second defect this gate found: the edge config was never installed

While measuring (3), one control result initially looked wrong — and the reason was a
real gap. The rehearsal's stand-in had its header set hard-coded, and when it was made
to read the repository's config instead, the *pre-CSP* control still served a CSP,
because it was reading the **working tree's** file rather than the deployed release's.
Chasing that produced the actual finding for the product:

```
deploy.sh:  nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || true
```

validates and reloads whatever the host already has; **no step in the repository ever
copies `deploy/nginx/toefl-house.conf` to the host.** So every header declared in the
versioned edge config applies only if an operator installed it at some earlier point —
and the paths where the edge is the *only* header source (static files, 404s, 5xx) are
exactly the ones left uncovered. Same class as the swallowed PHP-FPM reload from Gate
D: an activation step that succeeds by doing nothing, with `|| true` hiding it.

Fixed in `deploy/lib/nginx-edge-config.sh` (sourced helper, so it can be executed in a
test): `NGINX_CONF_DEST` unset → warned about in the deploy output instead of
silently skipped; identical file already installed → no reload (a release flip does
not need one, since `root` is the `current` symlink resolved per request); host config
already invalid → refuse *before* overwriting; new config rejected by `nginx -t` or
not reloadable → restore the previous file, reload, exit 1. Rehearsed on the live
cluster in all three directions:

```
logs/deploy11-edgeconfig.log   [deploy] edge config installed from the release: …/etc/nginx/conf.d/toefl-house.conf
                               [deploy] nginx reloaded (nginx -s reload)
                               [deploy] deployment OK: release 20260908205929 … is live and healthy
logs/deploy12-edgeconfig-refused.log  host config pre-broken by hand:
                               [deploy][ERROR] nginx -t already fails with the config the host has now;
                               not replacing … with an untested file on top of a broken edge   → exit 1
                               host file byte-identical afterwards, no backup written
logs/deploy13-edgeconfig-recovered.log  operator repairs it differently from the release:
                               [deploy] edge config installed from the release …  deployment OK: release 20260908210006
                               previous host file preserved at …toefl-house.conf.pre-<release>
```

After (13) the edge was restarted reading *only* the installed path, which is how a
real nginx gets it (`include /etc/nginx/conf.d/*.conf`), and the static-asset CSP in
§3 is from that state — so the chain "commit → release → installed file → header the
browser sees" is closed by execution, not by assertion.

### 5. Tests added, and how each was shown to be capable of failing

| test | guards | non-vacuity |
| --- | --- | --- |
| `SecurityHardeningFeatureTest::test_the_content_security_policy_leaves_no_hole_in_script_execution` | policy parsed into a directive map; `script-src` may not contain `unsafe-inline`/`unsafe-eval`, no `https:` wildcard anywhere, `img-src` may not allow `data:` | asserted per directive on purpose: a whole-header grep for `'unsafe-inline'` would have failed on the legitimate `style-src` allowance, and a `"script-src" is present` check passes with `script-src 'unsafe-inline'` |
| `…::test_the_policy_covers_api_responses_as_well_as_pages` | JSON/error paths carry it too | — |
| `…::test_the_web_server_config_and_the_middleware_agree_on_the_policy` | byte-identical strings, exactly one `add_header` for it, `always`, and **server scope** (depth scan of the config) | moving the directive into `location /` failed with `'server' !== 'location'`; the conf was then restored from git |
| `…::test_the_dev_server_carve_out_is_scoped_to_a_hot_file_in_a_non_production_environment` | marker absent → strict; present in non-production → HMR origin only; production → never relaxes | — |
| `NginxEdgeConfigTest` (8 tests, 39 assertions) | installs, skips reload when unchanged, refuses a broken host config, restores on rejection and on unreloadable config, requires `nginx` when a destination is set, and that `deploy.sh` sources/calls/undoes correctly | each case asserts on the file on disk and a reload marker produced by a stub `nginx`; the stub deliberately errors if it is not told which file to validate, because a stub that greps an empty path calls every config valid and all three refusal tests then pass vacuously |
| `ShippedScriptPermissionsTest` (existing) | the new helper is non-executable and says so in its first lines | it failed on the first draft (the "sourced, not executed" note sat past line 20) and was fixed by matching `lib/retention.sh`'s header convention |

Suite: **961 tests, 7,243 assertions, 1 skip** (was 957/7,205 before the CSP);
`tests/Feature/Security` 19/124, `tests/Feature/Deployment` 75/385; Pint 872 files
clean; PHPStan level 6 `[OK] No errors`.

### 6. Recorded, not fixed

* **No violation reporting.** `report-to`/`report-uri` are absent because the
  repository has no collector to send to. The consequence is real: enforcing (rather
  than `Content-Security-Policy-Report-Only`) means a future inline script simply
  does not run, with the failure visible only in a browser console. Accepting a
  report endpoint is a product decision (it receives URLs, referrers and user agents,
  which is a privacy surface this application should not open casually), so it is
  recorded rather than silently wired to some third party. §11 of the operations doc
  states the consequence where someone editing a page will read it.
* **`php artisan serve` and the Windows one-click runtime have no web-server config in
  this repository**, so on those paths only the middleware's policy applies; static
  files served directly are outside it. That asymmetry predates this gate (the other
  five headers behave identically) and is the intended trade for keeping local
  development honest rather than relaxed.
* **Substitution, labelled:** the sandbox has no nginx binary, so `nginx -t`/`-s reload`
  semantics are exercised through a stub that validates the installed file and records
  the reload. The stub cannot catch a directive nginx itself would reject; that check is
  therefore *structurally* verified (refuse-before-overwrite, restore-on-rejection) and
  not syntactically. The header application in §3 is measured through the TLS stand-in,
  which reads the installed config for real.

### 7. Verdict

**PASS**, one gap recorded (no violation collector). The policy is derived from a
measured inventory rather than a template, it is enforced rather than report-only,
`script-src` has no escape hatch, both layers send the identical string, and the
header survives on the paths that have no application behind them. Enforcement is
demonstrated in a browser against a control in which the same six payloads all
succeeded, and the browser E2E passes 21/21 unchanged under the policy. The gate also
turned up a delivery defect that made the edge half of the claim fiction — versioned
config, never installed — now fixed, tested executably, and rehearsed in all three
outcomes on the live cluster.
