# TOEFL House — Setup, Verification & Runtime Readiness

**STATUS: CURRENT CANONICAL — RUNTIME PROCEDURE**

This document describes the supported local/Windows deployment path and the developer verification commands. It deliberately separates repository preparation from runtime evidence.

## 1. Runtime targets

The repository targets:

| Component | Target |
|---|---|
| PHP | 8.2.x |
| Laravel | 12.67.x line |
| PostgreSQL | 18.x |
| Composer | 2.10.x |
| Node | 22.x |
| React | 19.x |
| Vite | 7.x |
| TypeScript | 5.9.x |

The current repository pins concrete deployment artifacts inside the Windows launcher. Those pins are deployment implementation details and must remain consistent with the dependency lockfiles.

## 2. Repository facts

The current database migration chain contains **190 migrations**, ending at `000190`. The numbering gap `000175`–`000179` is intentional historical numbering and is not, by itself, a defect.

The migration chain has **not** been replaced by a guessed schema baseline. PostgreSQL baseline consolidation remains governed by `docs/DATABASE_SCHEMA_CONSOLIDATION.md` and is runtime-gated.

Retired database structures such as `compensation_components` and `work_bases` must not be reintroduced by application code or a future baseline.

## 3. Windows one-click deployment

The supported Windows convenience path is:

`START-TOEFL-HOUSE.bat` → local PostgreSQL → Laravel → local HTTP → optional Tailscale Serve

The launcher prepares its pinned runtime artifacts under `.runtime\`, installs Composer dependencies from the committed lockfile, creates a local PostgreSQL cluster, runs the current migration chain, performs guarded first-run bootstrap, starts Laravel, and checks `/health`.

The repository also includes:

| File | Purpose |
|---|---|
| `START-TOEFL-HOUSE.bat` | Windows local deployment/startup |
| `STOP-TOEFL-HOUSE.bat` | Windows shutdown |
| `BACKUP-TOEFL-HOUSE.bat` | Verified PostgreSQL backup |
| `RESTORE-TOEFL-HOUSE.bat` | Confirmed PostgreSQL restore |
| `DIAG-PHP-CRASH.bat` | Read-only Windows PHP native-crash diagnostic utility |

`DIAG-PHP-CRASH.bat` is an operational diagnostic, not an application runtime dependency. It is retained because it has a defined troubleshooting purpose and does not mutate the database or reinstall runtime components.

## 4. Manual developer bootstrap

### PHP dependencies

```text
composer install
```

The committed `composer.lock` is authoritative. Do not regenerate it just to change formatting or documentation.

### Frontend dependencies

```text
npm ci
```

The committed npm lockfile is authoritative when present. Do not replace it with a hand-edited dependency state.

### Environment

Copy `.env.example` to `.env` and populate local secrets/configuration. Generate the Laravel key with:

```text
php artisan key:generate
```

Do not commit `.env`.

### Database

Use PostgreSQL for the application database. The current canonical connection is `pgsql`.

After PostgreSQL is available:

```text
php artisan migrate
php artisan db:seed --class=FirstRunBootstrapSeeder
```

`FirstRunBootstrapSeeder` is guard-protected and is intended only for an empty installation. Reference/system data such as the standard finance chart is kept separate through dedicated seeders.

## 5. Verification commands

### Database migration audit

```text
php scripts/database-migration-audit.php
```

This is a dependency-free static migration audit. It validates migration filename numbering and known data-writing exceptions; it does not prove PostgreSQL schema equivalence.

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

### Frontend type checking

```text
npm run typecheck
```

### Frontend production build

```text
npm run build
```

### Application health

Once the application is actually running:

```text
curl http://127.0.0.1:8080/health
```

The expected HTTP status for a healthy runtime is `200`.

## 6. Runtime verification gates

Runtime claims are evidence-based:

- **VERIFIED** — command executed successfully in the required runtime.
- **STATICALLY VERIFIED** — established from repository inspection or static tooling without runtime execution.
- **UNVERIFIED** — not executed or not proven.
- **BLOCKED** — execution is prevented by a missing dependency/environment capability.

The following are release-critical and require real runtime evidence:

- Laravel boot;
- PostgreSQL migration replay;
- schema/invariant verification;
- authorization/scope isolation;
- concurrency tests on real PostgreSQL;
- frontend type/build execution;
- browser/accessibility/performance verification where applicable;
- backup/restore drills;
- production-like deployment/recovery checks.

A documented command is not evidence that the command passed.

## 7. Current development-environment limitation

The codebase has been statically prepared for runtime verification, but the present development environment used for repository work does not provide all required runtime components.

Current known blockers:

- Composer executable is unavailable locally;
- PostgreSQL client/server tooling (`psql`) is unavailable locally;
- Docker is unavailable locally;
- Laravel `vendor/` dependencies are therefore not installed locally;
- external network/DNS access from the repository execution environment is unavailable.

Accordingly, full Laravel/PostgreSQL runtime certification remains **BLOCKED**, not passed.

## 8. Database baseline rule

Do not delete the historical migration chain merely to reduce file count.

Before physical consolidation:

1. provision a disposable PostgreSQL instance;
2. replay all 190 accepted migrations from zero;
3. capture an authoritative schema-only PostgreSQL snapshot;
4. inventory tables, columns, nullability, defaults, keys, checks, exclusions, indexes, sequences, types, extensions, functions, triggers, and views;
5. verify retired structures are absent;
6. establish the canonical baseline/snapshot through the approved Laravel mechanism;
7. replay the baseline independently and compare it with the source snapshot;
8. reconcile any existing environment without destructive reset or fake migration history.

Until those gates pass, the existing migration chain remains the authoritative implementation candidate.

## 9. Compatibility boundary

The versioned `/api/v1` surface is the canonical interactive SPA API.

The older employee web POST endpoints remain as transport compatibility boundaries where they are still part of the current route contract. They delegate to the same domain command/query authorities and do not own business truth. Their eventual removal requires an explicit consumer-migration/deprecation decision; they must not be deleted solely because the React frontend no longer calls them directly.

## 10. Security requirements

Never commit:

- `.env` files containing real secrets;
- database passwords;
- API/service tokens;
- private keys;
- database dumps or live exports;
- local runtime data;
- generated logs.

The repository `.gitignore` excludes the normal environment, dependency, build, runtime, and backup artifacts.

## 11. Production-readiness statement

The repository is **not declared production-ready by this document alone**.

Production/release certification requires the official runtime stack and all material gates to pass with recorded evidence. The current status remains governed by `docs/13-TESTING-QUALITY-RELEASE.md`, `docs/14-CURRENT-STATE-ROADMAP.md`, and the database baseline decision documents.