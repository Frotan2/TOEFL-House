# TOEFL House — Setup, Verification & Runtime Readiness

**STATUS: ACTIVE RUNTIME PROCEDURE (synchronized 2026-09-10; current release
status is owned by `docs/RUNTIME-RELEASE.md`)**

This document describes the supported local/Windows deployment path, the
clean-environment provisioner, and the developer verification commands. It
deliberately separates repository preparation from runtime evidence.

## 1. Runtime targets

The authoritative specification is
[`docs/RUNTIME_ENVIRONMENT_LOCK.md`](docs/RUNTIME_ENVIRONMENT_LOCK.md),
machine-checked by `npm run verify:environment`. Summary:

| Component | Reproducible local reference | Supported range |
|---|---|---|
| PHP | 8.4.14 | `>=8.2 <8.5` (`composer.json` `^8.2`) |
| Composer | 2.9.2 | `>=2.5 <3` |
| Laravel | 12.67.0 | `>=12.67 <13.0` (Laravel 13 is **prohibited**) |
| PostgreSQL | 18.4 | `>=18.0 <19.0` |
| Node | 22.22.3 | `>=22.0 <23.0` (`package.json` engines) |
| npm | 10.9.8 | `>=10.0 <11.0` |
| React / Vite / TypeScript | 19.1.1 / 7.3.6 / 5.9.x | per lockfiles |

CI and the Windows launcher use concrete reference patches inside these
ranges; the compatibility lock, not a duplicated patch comparison, decides
whether a host is eligible. **SQLite/pdo_sqlite are deliberately excluded** —
PostgreSQL is the only supported database.

## 2. Repository facts

The current database migration chain contains exactly **202 migration files**,
with ordinals running `000001`–`000207`; the numbering gap
`000175`–`000179` is intentional historical numbering and is not, by itself,
a defect. The chain replays cleanly to completion on the reproducible local
runtime (202/202, re-verified on PostgreSQL 18.4 on 2026-09-10).

The migration chain has **not** been replaced by a guessed schema baseline.
PostgreSQL baseline consolidation remains governed by
`docs/DATABASE_SCHEMA_CONSOLIDATION.md` and
`docs/decisions/2026-09-07-database-baseline-readiness.md`, and is
runtime-gated behind a schema freeze.

The **standard finance chart is seeded by migration `000186`**, so any
`migrate` run lands with a complete chart. `StandardFinanceChartSeeder`
exists as the standalone re-seed equivalent for databases that need it
without a migration replay.

Retired database structures such as `compensation_components` and
`work_bases` must not be reintroduced by application code or a future
baseline.

## 3. Windows one-click deployment

The supported Windows convenience path is:

`START-TOEFL-HOUSE.bat` → local PostgreSQL → Laravel → local HTTP on
`http://127.0.0.1:8080` → optional Tailscale Serve

The launcher prepares its pinned runtime artifacts under `.runtime\` (PHP,
Composer, Node — build-only — and PostgreSQL), installs Composer
dependencies from the committed lockfile, **builds the employee console**
(pinned Node 22.22.3, `npm ci --engine-strict` from the committed
`package-lock.json`, then `vite build`; production `/health` refuses to
report healthy without the built manifest), creates a local PostgreSQL
cluster, runs the current migration chain, performs the guarded first-run
bootstrap (prompting for the owner account's name, birthdate, username and
password on first run), starts Laravel and checks `/health`.

The launcher deliberately serves with the PHP built-in server directly
(not `artisan serve`) — the same rule as every other environment, see
`docs/RUNTIME_ENVIRONMENT_LOCK.md` §6.

The repository also includes:

| File | Purpose |
|---|---|
| `START-TOEFL-HOUSE.bat` | Windows local deployment/startup |
| `STOP-TOEFL-HOUSE.bat` | Windows shutdown |
| `BACKUP-TOEFL-HOUSE.bat` | Verified PostgreSQL backup |
| `RESTORE-TOEFL-HOUSE.bat` | Confirmed PostgreSQL restore |
| `DIAG-PHP-CRASH.bat` | Read-only Windows PHP native-crash diagnostic utility |

`DIAG-PHP-CRASH.bat` is an operational diagnostic, not an application runtime
dependency. It is retained because it has a defined troubleshooting purpose
and does not mutate the database or reinstall runtime components.

## 4. Manual developer bootstrap (Linux / clean environment)

### Provision the runtime (one command)

Environments without a system PHP/PostgreSQL use the self-contained
provisioner (it builds around the sandbox's allow-listed network channels —
see `docs/RUNTIME_ENVIRONMENT_LOCK.md` §6 for why):

```bash
bash scripts/runtime/provision.sh    # PHP 8.4.14 + Composer 2.9.2 + PostgreSQL 18.4 → .runtime/
source scripts/runtime/env.sh        # put the runtime on PATH
bash scripts/runtime/pg.sh start     # start the local PostgreSQL server
bash scripts/runtime/pg.sh createdbs # create dev + test databases
```

On hosts with suitable system PHP/PostgreSQL, the same `composer`/`npm`/
`php artisan` commands below apply directly.

### PHP dependencies

```text
composer install
```

The committed `composer.lock` is authoritative. Do not regenerate it just to
change formatting or documentation.

### Frontend dependencies

```text
npm ci
```

The committed `package-lock.json` is authoritative. Use `npm ci` (not
`npm install`) so the lockfile is honored exactly.

### Environment

Copy `.env.example` to `.env` and populate local secrets/configuration.
Generate the Laravel key with:

```text
php artisan key:generate
```

Do not commit `.env`.

### Database

Use PostgreSQL for the application database. The current canonical
connection is `pgsql`.

After PostgreSQL is available:

```text
php artisan migrate --force
```

The standard finance chart is included (migration `000186`).

### First-run bootstrap (fresh installations only)

`FirstRunBootstrapSeeder` provisions the bootstrap organization, the genesis
campus + branch every branch-mandated intake needs, the owner role/position
with the complete canonical capability set, and the owner account. It is
guard-protected: **it is a no-op once any user account exists**, so it can
never touch a live system. It runs ONLY on an empty installation:

```text
BOOTSTRAP_OWNER_NAME="First Owner" BOOTSTRAP_OWNER_BIRTHDATE="1980-01-01" \
BOOTSTRAP_OWNER_USERNAME=owner BOOTSTRAP_OWNER_PASSWORD='<strong-password>' \
  php artisan db:seed --class=FirstRunBootstrapSeeder --force
```

These variables are read from the **process environment** (never from
`.env`); nothing but the bcrypt password hash is persisted. The governance
basis is `docs/decisions/2026-09-09-first-run-genesis-structure.md`: this is
the one sanctioned place where structure facts may be written outside the
four-actor `StructureDecision` chain, and only at genesis.

## 5. Verification commands

### Environment contract

```text
npm run verify:environment
```

Asserts the supported runtime contract (compatibility ranges + required PHP extensions, 9 checks).

### Database migration audit

```text
php scripts/database-migration-audit.php
```

Dependency-free static migration audit: validates migration filename
numbering and known data-writing exceptions; it does not prove PostgreSQL
schema equivalence (that is what `migrate:fresh` + the schema census on a
supported PostgreSQL runtime proves).

### Terminology audit

```text
php scripts/terminology-audit.php
```

### Backend tests

```text
php artisan test
```

### PHP formatting check

```text
vendor/bin/pint --test
```

### PHP static analysis

```text
vendor/bin/phpstan analyse
```

### Frontend gates

```text
npm run typecheck
npm run build
npm run test:frontend
```

### Database invariants and concurrency (real PostgreSQL)

```text
npm run test:runtime-safety   # no-DB guard/preflight contract
npm run verify:invariants     # PostgreSQL itself rejects invalid states (6/6)
npm run verify:concurrency    # genuinely simultaneous transactions (4/4)
```

Run both only against an isolated, migration-backed disposable database. The
invariant script asserts the exact named PostgreSQL boundary for each invalid
write; the concurrency script races independent database connections against
real `idempotency_keys`, `scope_grants`, `org_wide_grant_requests`, and
`accounts` tables, not a mirror schema. Both commands reject a non-disposable
`DB_DATABASE` name unless the reviewed rehearsal escape hatch described in
`docs/RUNTIME-RELEASE.md` is set. They require a dedicated disposable-database
verifier role that can `SET LOCAL session_replication_role = 'replica'` (a
superuser or a role with `GRANT SET ON PARAMETER session_replication_role`),
not the normal application login. The invariant command additionally needs the
ownership-level trigger-control access described in that release document. Do
not run them alongside PHPUnit or a migration process.

### End-to-end business journeys (real HTTP, fresh first-boot databases)

The three root-level journey scripts drive the whole system over HTTP the way
the release protocol's critical-journey gate requires:

| Script | Proves |
|---|---|
| `e2e-journey.php` | Student lifecycle: structure, intake, academic chain, enrollment, billing — 77 checks |
| `e2e-payment-journey.php` | Payment lifecycle: obligations, payments, allocations, refunds — 29 checks |
| `e2e-payroll-journey.php` | Payroll → Finance liability recognition → exactly-once journal → reconciliation — 27 checks |

Run each against its own freshly migrated database and a served instance. From
the repository root, use Laravel's static-aware built-in-server router (not
`public/index.php` directly, which would route Vite JS/CSS through Laravel):

```bash
(
  cd public
  PHP_CLI_SERVER_WORKERS=8 php -S 127.0.0.1:<port> -t . \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
)
```

The router must run with `public/` as its working directory: it returns static
assets to the PHP server and sends only application routes to `index.php`.
Choose an appropriate worker count for the host; the journey concurrency checks
require more than one worker. See the header of each script for the exact reset
recipe.

### Browser E2E

```text
npm run verify:browser        # real Chromium; requires CHROMIUM_PATH + NSS on LD_LIBRARY_PATH
```

### Application health

Once the application is actually running:

```text
curl http://127.0.0.1:8080/health
```

The expected HTTP status for a healthy runtime is `200`.

## 6. Runtime verification gates

Runtime claims are evidence-based, on the ladder defined by
`docs/ai/06-VERIFICATION-EVIDENCE-STANDARD.md`:

- **VERIFIED** — command executed successfully in the required runtime.
- **STATICALLY VERIFIED** — established from repository inspection or static
  tooling without runtime execution.
- **UNVERIFIED** — not executed or not proven.
- **BLOCKED** — execution is prevented by a missing dependency/environment
  capability. Environment blockers are reported, never hidden by substituting
  unsupported runtimes.

The following are release-critical and require real runtime evidence:

- Laravel boot;
- PostgreSQL migration replay;
- schema/invariant verification;
- authorization/scope isolation;
- concurrency tests on real PostgreSQL;
- frontend type/build execution;
- critical business journeys over real HTTP;
- browser/accessibility/performance verification where applicable;
- backup/restore drills;
- production-like deployment/recovery checks.

A documented command is not evidence that the command passed. The
2026-09-09 certification record is historical evidence only; it does not
certify a later checkout. Determine the current release state from
`docs/RUNTIME-RELEASE.md`, the actual commit, and fresh applicable gate
results.

## 7. Environment limitations (current, honest)

The reproducible local reference runtime is available from a clean environment
via `scripts/runtime/provision.sh` — Composer, PostgreSQL and the applicable
non-browser verification chain are executable. The remaining known limitations
are:

- **Browser E2E** (`npm run verify:browser`) requires a Chromium binary
  (`CHROMIUM_PATH`) plus NSS libraries; sandboxes without Chromium cannot run
  it. It was executed on 2026-09-08 (Chromium 152) and is carried as prior
  evidence.
- **`php artisan serve`** cannot serve the provisioned runtime whenever a
  `.env` exists (it strips `LD_LIBRARY_PATH`/`PHPRC` from the child
  `php -S`); serve with the built-in server directly instead. See
  `docs/RUNTIME_ENVIRONMENT_LOCK.md` §6.
- Network egress in sandboxed environments is allow-listed; the provisioner
  is built around exactly those channels. External mirrors (Debian apt,
  `getcomposer.org`, Packagist) may be unreachable — never substitute an
  unofficial runtime to work around that.

## 8. Database baseline rule

Do not delete the historical migration chain merely to reduce file count.

Before physical consolidation:

1. provision a disposable PostgreSQL instance;
2. replay all 202 accepted migrations from zero (re-verified on the
   reproducible local runtime: 202/202 on 2026-09-10);
3. capture an authoritative schema-only PostgreSQL snapshot;
4. inventory tables, columns, nullability, defaults, keys, checks,
   exclusions, indexes, sequences, types, extensions, functions, triggers,
   and views;
5. verify retired structures are absent;
6. establish the canonical baseline/snapshot through the approved Laravel
   mechanism;
7. replay the baseline independently and compare it with the source snapshot;
8. reconcile any existing environment without destructive reset or fake
   migration history.

Until those gates pass, the existing migration chain remains the
authoritative implementation candidate. The live schema census for the
current chain is recorded in
`docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md` §4.

## 9. Compatibility boundary

The versioned `/api/v1` surface is the canonical interactive SPA API.

The older employee web POST endpoints remain as transport compatibility
boundaries where they are still part of the current route contract. They
delegate to the same domain command/query authorities and do not own business
truth. Their eventual removal requires an explicit
consumer-migration/deprecation decision; they must not be deleted solely
because the React frontend no longer calls them directly.

## 10. Security requirements

Never commit:

- `.env` files containing real secrets;
- database passwords;
- API/service tokens;
- private keys;
- database dumps or live exports;
- local runtime data;
- generated logs.

The repository `.gitignore` excludes the normal environment, dependency,
build, runtime, and backup artifacts.

## 11. Production-readiness statement

The repository's release authority is
[`docs/RUNTIME-RELEASE.md`](docs/RUNTIME-RELEASE.md). This checkout is **not
release certified** until the required gates have passed for its exact commit;
`docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md` remains a historical record for
its own commit only.

This setup document is a procedure, not a certification. Any future runtime
range/reference change or material change requires re-running the applicable
verification chain and updating `docs/RUNTIME_ENVIRONMENT_LOCK.md` together
with the release control and fresh evidence.
