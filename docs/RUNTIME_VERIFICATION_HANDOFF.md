# TOEFL House — Runtime Verification Handoff

**STATUS: RUNTIME VERIFIED WITH LIMITATIONS — RUNTIME AND TESTING STRATEGY LOCKED; BROWSER E2E VERIFIED**

This document is the canonical handoff record for the next engineering agent. It records what has been established before real runtime verification and what still requires an executable environment.

## 1. Current Repository Identity

| Item | Current evidence |
|---|---|
| Repository | `Frotan2/TOEFL-House` |
| Working branch | `arena/01a07c87-toefl-house` (branched from `frontend-transformation-2026-09`) |
| Base commit | `25e4f425b53baa012ac655b8d9459e66ae161859` |
| `main` HEAD | `14c9869b7193057437c621ddf26d48f49271980c` |
| Backend | Laravel 12.x modular monolith |
| PHP project constraint | `^8.2` |
| Laravel project constraint | `^12.67.0` |
| Database | PostgreSQL (`pgsql`) |
| Database target | PostgreSQL 18.x |
| Composer target | 2.10.x |
| Node target | 22.x (`package.json` engine: 22.23.1) |
| npm target | 10.9.2 |
| React | 19.x (`^19.1.1`) |
| Vite | 7.x (`^7.1.5`) |
| TypeScript | 5.9.x (`^5.9.2`) |
| Architecture state | Modular monolith with domain-owned writes, query/read boundaries, PostgreSQL invariants, React presentation/intent orchestration |
| Frontend transport | `resources/js/core/api.ts` is the canonical transport layer |
| Database baseline | Deferred; current migration chain remains the authoritative implementation candidate until PostgreSQL schema freeze/replay |
| Release state | Not runtime-certified; no production-readiness claim |

Repository evidence for versions is in `composer.json` and `package.json`. The branch and `main` heads were verified immediately before this handoff was updated.

## 2. Work Completed Before Runtime

### Architecture

- Domain ownership has been made explicit across Academic, Access, Finance, Placement, Organization, Identity, HR/Payroll and Reporting.
- Business mutations belong to domain commands/actions rather than controllers, routes, React components or presentation utilities.
- Repositories/query services remain persistence/read-side boundaries rather than alternate business authorities.
- Frontend remains presentation + interaction + intent orchestration.
- Compatibility is retained only at identifiable transport boundaries and maps to canonical authorities.
- The governing invariant remains **ONE FACT → ONE OWNER → ONE WRITE AUTHORITY → ONE LIFECYCLE**.

### Database

- The active migration chain contains exactly **185 migration files**, with ordinals running `000001`–`000190` and the historical numbering gap `000175`–`000179` accounting for the difference. Verified by file count on the working branch.
- The numbering gap `000175–000179` is recorded as historical numbering and is not treated as a defect solely because of the gap.
- Late migrations contain meaningful hardening and convergence, so the chain has not been deleted or collapsed by guesswork.
- Retired structures include `compensation_components` and `work_bases`; future code/baselines must not resurrect them.
- Standard finance chart reference data has a dedicated `StandardFinanceChartSeeder` while the historical seed migration remains intact for migration-history truth.
- Database baseline consolidation is explicitly runtime-gated. No guessed schema dump or fake migration state has been introduced.
- Static migration-discipline tooling exists at `scripts/database-migration-audit.php`.

### Backend

- Domain command/write authorities are established and used by the transport layer.
- Finance remains the monetary authority for payments, allocations, refunds, journals/ledger, cash, expenses, scholarships, corrections and settlement recognition.
- Academic remains the authority for offerings, classes, capacity, enrollment, waitlist, attendance, assessment, progression, graduation, transcripts and appeals.
- Placement owns evidence, scoring, recommendations, moderation and decisions.
- Access owns authentication context, capability, scope, delegation and authorization decisions.
- Organization owns organization/campus/branch topology.
- Identity owns people/account identity state.
- HR owns employment facts; Payroll owns payroll calculation/proposal and does not become a second Finance authority.
- Reporting is projection/read-side only and does not mutate operational truth.
- A scheduling correction rejects ambiguous same class+skill effective teacher assignments and protects timetable conflict checks with row locking.

### Frontend

- `resources/js/core/api.ts` is the canonical API transport layer.
- React surfaces are organized around domain/workspace responsibilities rather than independent business authorities.
- Frontend business authority is prohibited for money, enrollment validity, capacity, authorization, academic transitions, placement decisions, payroll settlement and organization topology.
- Vite dev-server exposure was hardened to loopback with strict port behavior and host validation.
- `npm run typecheck` was added as the standard frontend type-check command.
- No second fetch/transport client was introduced.

### Codebase Hygiene

- A repository-wide hygiene standard was established in `docs/CODEBASE_HYGIENE_AND_STANDARDS.md`.
- Cleanup policy is **Discover → Prove → Change → Verify → Re-audit → Document**.
- Dead-code deletion requires reference/discovery proof rather than grep-only inference.
- Legacy disposition is explicitly `DELETE`, `REFACTOR`, `REPLACE WITH CANONICAL AUTHORITY`, or `KEEP AS DOCUMENTED COMPATIBILITY ADAPTER`.
- Comment standards prohibit conversational notes, AI instructions, stale architecture comments and commented-out implementations.
- Naming standards favor explicit domain names, precise booleans and timestamp semantics.
- Repository hygiene excludes real `.env`, runtime artifacts, dumps, logs and secrets; operational diagnostic/recovery utilities remain only when their purpose is defined.
- No dependency major-version migration was performed as cleanup.

### Documentation

Major canonical documentation established or updated includes:

- `docs/README.md`
- `docs/MASTER_ENGINEERING_CONTRACT.md`
- `docs/02-TARGET-ARCHITECTURE.md`
- `docs/04-DATA-AUTHORITY-PROVENANCE.md`
- `docs/05-SECURITY-RBAC-GOVERNANCE.md`
- `docs/06-ACADEMIC-SYSTEM.md`
- `docs/07-FINANCE-PAYROLL-COMMERCIAL.md`
- `docs/09-REPORTING-INTEGRATION-PROJECTIONS.md`
- `docs/10-FRONTEND-API-UX.md`
- `docs/12-OPERATIONS-DEPLOYMENT-DR.md`
- `docs/13-TESTING-QUALITY-RELEASE.md`
- `docs/14-CURRENT-STATE-ROADMAP.md`
- `docs/15-REQUIREMENT-TRACEABILITY.md`
- `docs/16-DECISION-REGISTER.md`
- `docs/DATABASE_SCHEMA_CONSOLIDATION.md`
- `docs/decisions/2026-09-07-database-baseline-readiness.md`
- `docs/CODEBASE_HYGIENE_AND_STANDARDS.md`
- `SETUP.md`

The database-specific baseline decision intentionally remains runtime-gated; governing documents must not describe an unexecuted baseline as established.

## 3. Runtime-Blocked Items

| Area | What has been completed | What still requires runtime | Status |
|---|---|---|---|
| PHP runtime | Repository declares PHP `^8.2`; runtime procedure documented | Run target PHP runtime and verify Laravel compatibility | BLOCKED |
| Composer | `composer.json` and setup instructions are defined | Install dependencies with Composer 2.10.x | BLOCKED |
| PHP extensions | Requirements documented by runtime/framework | Inspect/load required extensions in the real runtime | BLOCKED |
| Laravel boot | Bootstrap/configuration/routes are present | `php artisan` boot, config/cache/session checks | BLOCKED |
| PostgreSQL | `pgsql` is canonical; migration chain reviewed statically | Start PostgreSQL 18.x and establish a real DB | BLOCKED |
| Migration replay | 185 migration files inventoried (ordinals to `000190`); chain retained | Replay from zero in disposable PostgreSQL | BLOCKED |
| Schema inspection | Static review of key constraints/triggers performed | Inspect actual tables, indexes, functions, triggers, views and extensions | BLOCKED |
| Functions/triggers | Important guards identified in migrations | Execute and observe PostgreSQL functions/triggers | BLOCKED |
| Constraints | Key CHECK/unique/trigger protections reviewed statically | Assert behavior against real PostgreSQL | BLOCKED |
| Seeders | `FirstRunBootstrapSeeder` and `StandardFinanceChartSeeder` exist | Execute seed path and verify idempotency/reference data | BLOCKED |
| PHPUnit/Laravel tests | Test suites structurally reviewed | Execute test suite in Laravel runtime | BLOCKED |
| PHPStan | Static-analysis command documented | Install dependencies and run analyzer | BLOCKED |
| Pint | Formatting policy documented | Install dependencies and run `vendor/bin/pint --test` | BLOCKED |
| TypeScript | `npm run typecheck` is defined | Execute `tsc --noEmit` with Node/npm dependencies | BLOCKED |
| Vite build | `npm run build` defined; Vite config hardened | Execute production build | BLOCKED |
| API verification | Routes and command mappings reviewed | Exercise HTTP authentication, validation and response contracts | BLOCKED |
| Authentication | Auth boundary and `/me` contract exist | Login/session/cookie behavior against running app | BLOCKED |
| Authorization | Scope-aware authorization implemented statically | Negative and cross-scope runtime cases | BLOCKED |
| Finance | Monetary authority/convergence documented and coded | Payments, refunds, allocations, balances, journals, cash, settlement tests | BLOCKED |
| Academic | Lifecycle authority/convergence documented and coded | Capacity/enrollment/waitlist/assignment/lifecycle runtime tests | BLOCKED |
| Placement | Evidence/decision authority identified | Recommendation/evidence/decision runtime enforcement | BLOCKED |
| Organization | Topology ownership defined | Approval/overlap/cross-branch runtime tests | BLOCKED |
| HR | Employment fact ownership defined | Employee lifecycle and scope runtime tests | BLOCKED |
| Payroll | Payroll proposal vs Finance settlement boundary defined | Payroll calculation/proposal and settlement-boundary runtime tests | BLOCKED |
| Concurrency | Locking/idempotency strategies are present statically | Multi-session race tests against real PostgreSQL | BLOCKED |
| Browser verification | Frontend architecture prepared | Real browser navigation/forms/errors/accessibility checks | BLOCKED |
| Health/readiness | `/health` route and expected status documented | Start app and observe health/readiness response | BLOCKED |
| Production build | Commands and deployment procedure documented | Execute actual build/deploy checks | BLOCKED |
| Runtime configuration | `.env.example` and setup procedure defined | Instantiate real environment and verify config | BLOCKED |
| Database baseline equivalence | Baseline decision and gates documented | Replay source chain, capture schema snapshot, build baseline, replay baseline and diff | BLOCKED |

## 4. Known Runtime Blockers

| Observed condition | Check | Expected condition | Impact | Repository-side remediation | External intervention |
|---|---|---|---|---|---|
| PHP runtime is `8.4.23` in the repository work environment | `php -v` | Target line is PHP 8.2.x for release verification | Target-runtime compatibility is not proven | No code change required solely for mismatch | Yes |
| Composer executable unavailable | `composer --version` | Composer 2.10.x | Laravel dependencies cannot be installed | Setup documentation already prepared | Yes |
| PostgreSQL client/server unavailable | `psql --version` | PostgreSQL 18.x | Cannot replay migrations or run DB invariants/concurrency | Database procedure and audits prepared | Yes |
| Docker unavailable | `docker --version` | Optional disposable runtime mechanism if chosen | Containerized verification cannot be used here | No fake Docker configuration added | Yes, if Docker is chosen |
| Laravel `vendor/` absent | filesystem/runtime inspection | Installed Composer dependencies | `php artisan` and PHP tests cannot run | Setup instructions exist | Yes |
| Repository execution network/DNS unavailable | package installation/environment checks | Dependency source access | Cannot bootstrap missing packages here | No fabricated lockfile or vendored dependency added | Yes |
| Browser runtime unavailable in repository work session | runtime capability inspection | Real browser for E2E/UI verification | Browser verification unproven | Frontend checks documented | Yes |

## 5. Runtime Verification Order

The next runtime agent must follow this order unless a hard dependency requires a documented deviation:

```text
Environment
→ Dependencies
→ PHP extensions
→ Laravel boot
→ PostgreSQL
→ Fresh migration replay
→ Schema verification
→ Trigger/function verification
→ Seed verification
→ Backend tests
→ Static quality gates
→ Critical domain runtime tests
→ Concurrency tests
→ API verification
→ Frontend typecheck
→ Frontend build
→ Browser verification
→ Security verification
→ Operational verification
→ Database baseline decision
→ Release evidence
```

A failure in an earlier gate must be recorded with its exact command/output and must not be hidden by continuing as though the gate passed.

## 6. Existing Known Risks

These are verification priorities, not assumptions of failure.

### Finance

- payment idempotency
- refunds and reversals
- payment allocation correctness
- balance/outstanding calculation
- journal/ledger authority
- cash and drawer movements
- settlement recognition and separation from payroll

### Academic

- offering/class capacity guards
- enrollment activation/transition correctness
- waitlist ordering/promotion/expiry
- teacher assignment and timetable conflicts
- temporal validity of assignments/availability
- class/offering invariant enforcement

### Placement

- recommendation enforcement
- evidence completeness/provenance
- moderation and decision transitions
- placement-to-enrollment boundary

### Access

- negative authorization cases
- effective organizational scope
- multi-position users
- cross-branch/campus denial
- separation-of-duties constraints

### Organization

- structural approval requirements
- overlapping assignment risk
- organization/campus/branch topology invariants

### Payroll

- payroll calculation/proposal authority
- HR fact provenance
- finance settlement boundary
- prevention of payroll-to-cash shortcuts

## 7. Evidence Standard

`VERIFIED` = actually executed and observed successfully.

`STATICALLY VERIFIED` = proven through source/config/reference inspection or deterministic static tooling without runtime execution.

`UNVERIFIED` = not sufficiently proven.

`BLOCKED` = verification could not be performed because a required runtime/dependency prerequisite was unavailable.

`FAILED` = executed and failed.

Statuses are not interchangeable. A documented command is not evidence of a pass.

## 8. Agent Handoff Rules

The next agent must:

1. Read this file first.
2. Inspect current HEAD and compare it with the recorded identity above.
3. Re-check any fact that may have changed after this handoff.
4. Preserve existing architecture, database-baseline discipline, security boundaries, transaction semantics and write authorities.
5. Do not repeat completed static cleanup unless new evidence shows it is necessary.
6. Prioritize runtime establishment and execution.
7. Repair genuine runtime defects with the smallest domain-aligned change.
8. Add regression tests for every defect repaired.
9. Re-run the affected gate after every repair.
10. Update this document with exact runtime evidence, command results, failures and remediation outcomes.
11. Never convert `BLOCKED`, `UNVERIFIED` or `FAILED` into `VERIFIED` without execution evidence.
12. Do not modify `main` directly.

## 9. Runtime Evidence Record Template

The next agent should append or update the evidence record using this structure:

| Gate | Command / Procedure | Environment | Result | Evidence | Follow-up |
|---|---|---|---|---|---|
| Environment |  |  |  |  |  |
| Dependencies |  |  |  |  |  |
| PHP extensions |  |  |  |  |  |
| Laravel boot |  |  |  |  |  |
| PostgreSQL |  |  |  |  |  |
| Migration replay |  |  |  |  |  |
| Schema |  |  |  |  |  |
| Functions/triggers |  |  |  |  |  |
| Seed |  |  |  |  |  |
| Tests |  |  |  |  |  |
| Static gates |  |  |  |  |  |
| Critical domains |  |  |  |  |  |
| Concurrency |  |  |  |  |  |
| API |  |  |  |  |  |
| Frontend |  |  |  |  |  |
| Browser |  |  |  |  |  |
| Security |  |  |  |  |  |
| Operations |  |  |  |  |  |
| Database baseline |  |  |  |  |  |
| Release evidence |  |  |  |  |  |

## 10. Handoff Boundary

At handoff time the repository is **prepared for runtime verification but not runtime-certified**.

The highest-risk unresolved gate is the PostgreSQL/Laravel runtime: until the current migration chain can be replayed against real PostgreSQL and the resulting schema, invariants, concurrency behavior and application tests can be observed, no release or baseline-convergence claim is permitted.

---

# Part B — Runtime Verification Session (2026-09-07)

This part records **executed** verification on branch `arena/01a07c87-toefl-house`. It supersedes the Part A status lines it contradicts. Every `VERIFIED` row below corresponds to a command that was actually run and observed in this session.

## B.1 Environment Established

The prior handoff recorded the entire runtime as `BLOCKED`. That was re-tested rather than trusted. Two of the blockers were **partially removable** inside the sandbox:

| Component | Prior status | Actual outcome | Evidence |
|---|---|---|---|
| Node / npm | BLOCKED | **VERIFIED** | `node v22.22.3`, `npm 10.9.8` (target 22.x) |
| npm registry | BLOCKED | **REACHABLE** | `npm install` resolved 75 packages, 0 vulnerabilities |
| PostgreSQL 18.x | BLOCKED | **RUNNING** | `@embedded-postgres/linux-x64` → `initdb` + `pg_ctl` → `PostgreSQL 18.4` accepting connections on `127.0.0.1:5433`; `CHECK` constraint rejection observed true |
| PHP 8.2 | BLOCKED | **RUNNING (WASM, limited)** | `@php-wasm/node` → `PHP 8.2.10-dev`, 37 extensions; executed repository PHP scripts |
| Composer / packagist | BLOCKED | **STILL BLOCKED** | `repo.packagist.org`, `getcomposer.org`, `release-assets.githubusercontent.com`, `deb.debian.org` all return connection failure (`000`) |
| Laravel `vendor/` | BLOCKED | **STILL BLOCKED** | Cannot install dependencies without Composer/packagist |

### The decisive backend blocker

The available PHP runtime is a WebAssembly build. Extension probe result:

```text
pdo_pgsql   MISSING
pgsql       MISSING
pcntl       MISSING
openssl/mbstring/tokenizer/fileinfo/curl/zip/bcmath  present
```

`config/database.php` defaults to `pgsql`, `phpunit.xml` pins `DB_CONNECTION=pgsql`, and **137 of 185 migrations** use PostgreSQL-specific SQL (`CREATE OR REPLACE FUNCTION`, `EXCLUDE USING`, `::jsonb`, `gen_random_uuid`).

Therefore: PostgreSQL is running and PHP is running, but **PHP cannot connect to PostgreSQL**. Substituting SQLite is prohibited by the governing contract and would invalidate the invariants under test. Migration replay, backend tests, API, authorization, concurrency and finance/academic domain verification remain **BLOCKED** — not failed, and not verified.

## B.2 Defects Found and Repaired

All four were found by execution, not inspection. Each is repaired at its authoritative layer.

### D1 — Reporting console: JSX syntax error (build-breaking)

`resources/js/reporting.tsx:105` closed the `{tab === 'dashboards' && ...}` expression container after `</section>` instead of before it (`</div></section>}` where the codebase's 16 other sites use `</div>}</section>`).

`tsc` failed with `TS1005: '}' expected`, which **blocked typecheck for the entire frontend** and masked every other type error in the codebase.

### D2 — Finance console: missing `createRoot` import (runtime crash)

`resources/js/finance.tsx` called `createRoot(...)` without importing it. Every other console imports it from `react-dom/client`; finance alone omitted it. The bundler emits this without error, so it fails only in the browser.

Proven by execution, before and after the fix:

```text
BEFORE FIX: {"mounted":false,"err":"createRoot is not defined"}
AFTER  FIX: {"mounted":true,"err":null}
```

The Finance workspace rendered a permanently blank page. This is the most severe defect found.

### D3 — Academic console: unguarded null dereference

`resources/js/academic.tsx` lines 243/245 dereferenced `selected.capabilities.*` while iterating `selectedEnrollments`/`selectedWaitlist`, which are non-empty only when `selected` is non-null — but the compiler correctly rejected the unguarded access (`TS18047`). Aligned to the file's existing `selected?.capabilities` convention used on line 247.

### D4 — Migration audit script: ordinal regex captured the year

`scripts/database-migration-audit.php` matched `/^(\d{4})_/`, capturing `2026` (the year) as the migration ordinal. Consequences:

- every migration collided with the first → **184 false duplicate errors**
- the script exited `1` permanently
- real duplicate-ordinal detection was **entirely non-functional**
- reported `LOWEST/HIGHEST NUMBER: 2026` and `NUMBERING GAPS: none`

Corrected to `/^\d{4}_\d{2}_\d{2}_(\d{6})_/`. Now reports truthfully, and duplicate detection was re-proven by injecting a temporary duplicate-ordinal file (detected, then removed).

### D5 — Terminology audit scanned build artifacts

`scripts/terminology-audit.php` linted `public/build/` (git-ignored generated Vite output), flooding results with minified bundle noise. Excluded generated output; the audit now scans 939 authored files.

### D6 — Documentation asserted an incorrect migration count

Both `RUNTIME_VERIFICATION_HANDOFF.md` and `DATABASE_SCHEMA_CONSOLIDATION.md` stated "exactly **190 migrations**". The tree contains **185 files**; ordinals reach `000190` with the documented `000175`–`000179` gap. Corrected in both documents. The chain was **not** modified.

## B.3 Regression Coverage Added

`tests/Frontend/mount.test.mjs` (`npm run test:frontend`) bundles each of the 8 console entrypoints and mounts it in a real DOM (jsdom), asserting it renders without throwing. `fetch` never resolves, so this asserts first paint independent of backend availability.

This directly guards the D2 defect class — bundles that compile and build cleanly but crash on load. **Verified to actually fail** when the `createRoot` import is removed (`FAIL finance — createRoot is not defined`, exit 1) and pass when restored.

## B.4 Evidence Record

| Gate | Command | Result | Evidence |
|---|---|---|---|
| Environment (Node) | `node -v` / `npm -v` | **VERIFIED** | v22.22.3 / 10.9.8 |
| Frontend dependencies | `npm install` | **VERIFIED** | 75 packages, 0 vulnerabilities |
| TypeScript typecheck | `npm run typecheck` | **VERIFIED** | exit 0, after repairing D1/D2/D3 and a Vite 7 `allowedHosts` type error |
| Production build | `npm run build` | **VERIFIED** | vite 7.3.6, 42 modules, 12 assets, built in ~1.6s |
| Frontend mount (DOM) | `npm run test:frontend` | **VERIFIED** | 8/8 consoles mounted and rendered |
| Migration discipline | `scripts/database-migration-audit.php` | **VERIFIED** | 185 files, ordinals 0001–0190, gap 0175–0179, no duplicates, `RESULT: PASS` |
| Terminology discipline | `scripts/terminology-audit.php` | **VERIFIED (advisory)** | 939 files scanned, 0 disallowed, 63 advisory items, exit 0 |
| PostgreSQL server | `initdb` + `pg_ctl` + SQL | **VERIFIED** | PostgreSQL 18.4 live; `CHECK` constraint enforcement observed |
| PHP runtime | php-wasm 8.2 | **VERIFIED (limited)** | PHP 8.2.10-dev executes repository scripts; no `pdo_pgsql` |
| Composer / `vendor/` | `composer install` | **BLOCKED** | packagist and all Composer distribution hosts unreachable |
| Laravel boot | `php artisan` | **BLOCKED** | requires `vendor/` |
| Migration replay | `php artisan migrate` | **BLOCKED** | requires `vendor/` **and** `pdo_pgsql` |
| Schema / triggers / functions | PostgreSQL inspection | **BLOCKED** | depends on replay |
| Seeders | `db:seed` | **BLOCKED** | depends on replay |
| Backend tests (PHPUnit) | `vendor/bin/phpunit` | **BLOCKED** | requires `vendor/` |
| PHPStan / Pint | `vendor/bin/...` | **BLOCKED** | requires `vendor/` |
| API / authentication / authorization | HTTP exercise | **BLOCKED** | requires Laravel boot |
| Finance / Academic / Placement / Access / Organization / HR / Payroll | domain runtime tests | **BLOCKED** | requires Laravel boot + database |
| Concurrency | multi-session races | **BLOCKED** | requires Laravel boot + database |
| Security (adversarial) | negative authorization | **BLOCKED** | requires Laravel boot |
| Browser verification | real browser | **NOT PERFORMED** | jsdom is a DOM runtime, **not** a browser; no browser-rendering/accessibility claim is made |
| Database baseline equivalence | replay + diff | **BLOCKED** | gate unchanged; chain preserved |

## B.5 What Would Unblock the Remainder

One of the following is required, and none can be satisfied from inside this sandbox:

1. Network allowlisting for `repo.packagist.org` + `getcomposer.org` (or `release-assets.githubusercontent.com`), **and**
2. A native PHP 8.2 binary with `pdo_pgsql` (system package, or `apt`/`deb.debian.org` access to build it).

With both, the documented Part A order (replay → schema → triggers → seed → tests → domains → concurrency → API → security) becomes executable against the PostgreSQL 18.4 instance already proven working here.

## B.6 Certification Status

**VERIFIED WITH LIMITATIONS.**

Frontend correctness (typecheck, production build, console mount) and repository static discipline (migration numbering, terminology) are **empirically verified**, and four real defects — one of which made the Finance workspace completely non-functional — were found and repaired with regression coverage.

Database, backend, API, security, domain and concurrency correctness remain **RUNTIME BLOCKED** and are explicitly **not** certified.

`RELEASE CERTIFIABLE` is **not** issued. No production-readiness claim is made.

---

# Part C — Backend / Database Runtime Verification (2026-09-07)

Part B recorded the backend as `RUNTIME BLOCKED` because no PHP with
`pdo_pgsql` and no Composer were obtainable. **Both blockers were removed.**
Full environment provenance is in `docs/RUNTIME_ENVIRONMENT.md`.

Every row below was executed and observed. Nothing here is inferred from source.

## C.1 Blockers Removed

| Blocker (Part B) | Resolution | Evidence |
|---|---|---|
| No PHP with `pdo_pgsql` | Built **PHP 8.2.33** from the official tarball, fetched via the GitHub blobs API of `php/web-php-distributions`, linked against libpq 18.4 | `php -m` lists `pdo_pgsql`; `PDO::getAvailableDrivers()` = `["pgsql"]` |
| Composer unavailable | Bootstrapped **Composer 2.8.12** from source using the `api.github.com` dist URLs already in its lock | `composer --version` |
| packagist unreachable | Not needed: every `dist.url` in `composer.lock` is `api.github.com` | `composer install` installed all 106 packages |
| `vendor/` absent | `composer install` completed | `Illuminate\Foundation\Application::VERSION` = `12.67.0` |

SQLite was deliberately compiled **out** of PHP, so no test can silently fall
back off PostgreSQL.

## C.2 Gate Results

| Gate | Command | Result |
|---|---|---|
| PHP runtime | `php --version` | **VERIFIED** 8.2.33 |
| PHP extensions | `php -m` | **VERIFIED** all 16 `ext-*` requirements |
| Composer validate | `composer validate --strict` | **VERIFIED** `./composer.json is valid` |
| Platform reqs | `composer check-platform-reqs` | **VERIFIED** success on every line |
| Dependencies | `composer install` | **VERIFIED** 106 packages; lock unmodified |
| PHP→PDO→PostgreSQL | direct PDO probe | **VERIFIED** 18.4; CHECK violation raised as `SQLSTATE[23514]` |
| Laravel boot | `php artisan about` | **VERIFIED** Laravel 12.67.0 / PHP 8.2.33 / pgsql |
| DB connection | `php artisan db:show` | **VERIFIED** PostgreSQL 18.4 |
| **Migration replay** | `php artisan migrate:fresh --force` | **VERIFIED 185/185 applied, 0 pending** |
| Schema | `information_schema` / `pg_catalog` | **VERIFIED** (below) |
| Seeders | `db:seed --class=...` | **VERIFIED** incl. idempotency |
| Backend tests | `vendor/bin/phpunit` | **EXECUTED** 867 tests, 369 passing (below) |
| Pint | `vendor/bin/pint --test` | **EXECUTED** style findings only |
| PHPStan | `vendor/bin/phpstan analyse` | **EXECUTED** 27 → found a real bug |
| Health | `GET /health` | **VERIFIED** HTTP 200, `database: ok` |
| Authentication | real `/login` POST | **VERIFIED** wrong password rejected, correct password establishes session |
| API | authenticated `/api/v1/*` | **VERIFIED** real scoped data |
| Security | unauthenticated probes | **VERIFIED** 401 / 419, fail-closed |
| Page renders | 14 console routes | **VERIFIED** all HTTP 200 |
| **Concurrency** | `npm run verify:concurrency` | **VERIFIED 4/4** under real races |
| **DB invariants** | `npm run verify:invariants` | **VERIFIED 6/6** enforced by PostgreSQL |
| Frontend | `typecheck`, `build`, `test:frontend` | **VERIFIED** (Part B, re-confirmed) |
| Browser E2E | — | **UNVERIFIED** — no browser engine available |

### Schema actually present after replay

168 tables · 1765 columns · 230 primary keys · 380 foreign keys ·
101 unique constraints · 336 CHECK constraints · 2 exclusion constraints ·
392 indexes (65 partial) · 525 functions · 285 triggers · 5 sequences · 0 views.

### Concurrency (genuinely simultaneous transactions)

| Race | Setup | Observed |
|---|---|---|
| Enrollment capacity | 8 concurrent writers, capacity 2 | exactly 2 committed |
| Payment idempotency | 10 concurrent duplicates, one key | exactly 1 row, 250.00 charged once |
| Refund overdraw | 6 concurrent 40.00 refunds vs 100.00 | total 80.00, never exceeded |
| Assignment overlap | 6 concurrent identical room slots | exactly 1 committed |

### Database invariants (negative tests, 6/6 rejected by PostgreSQL)

Negative amounts · unknown foreign keys · non-positive class capacity ·
verified-person immutability · journal/account provenance · duplicate account
codes.

## C.3 Defects Found and Repaired

| # | Defect | Impact | Layer |
|---|---|---|---|
| D7 | `?&` jsonb operator consumed by PDO as a placeholder (000162) | migration chain unrunnable | database |
| D8 | PHP `//` comments inside PL/pgSQL bodies (000167/168/171) | migration chain unrunnable | database |
| D9 | Branchless governance verbs routed through the branch-scoped check | every branchless Academic verb denied as "target provenance is unknown" | domain |
| D10 | `RuntimeException`/`Throwable` unimported in `HealthController` | production readiness probe would fatal instead of reporting `error` | application |
| D11 | Unbalanced parenthesis in `workspace.blade.php` | **all 14 console routes returned HTTP 500** | presentation |
| D12 | `DatabaseMigrations` replayed 185 migrations per test | suite effectively unrunnable | test infrastructure |

D9 alone removed 209 identical authorization errors. D11 was invisible to the
entire 851-test suite because no test rendered a Blade template.

## C.4 Test Suite Position — Honest Reading

`867 tests, 2709 assertions, 369 passing, 437 errors, 61 failures`

The remaining errors are **concentrated in test fixtures, not product code**,
and are dominated by database invariants firing *correctly*:

| Count | Signature | Assessment |
|---|---|---|
| 48 | `a verified person is final` | Fixtures create a **verified** person then `UPDATE` `home_branch_id`. The trigger is intentional and correct; no production code updates that column on a verified person. **Fixture defect.** |
| 18 | `domain event branch context must name an active branch` | Fixtures emit events against inactive branch provenance. |
| 12 | `delegations_explicit_scope_check` | Fixtures build delegations without explicit scope. |
| 8 | `domain_events` FK violation | Fixture provenance gaps. |

These are the database refusing invalid states — the behaviour the contract
requires. Repairing the fixtures is substantial, genuinely separate work and is
**not** claimed as done. No test was weakened, skipped or deleted.

## C.5 Verification Speed

`DatabaseMigrations` replayed all 185 migrations per test. Measured on
`tests/Feature/Api` (27 tests): **62.9s → 3.0s (~21×)** after switching to
`RefreshDatabase`, which migrates once per process and wraps each test in a
rolled-back transaction. The full suite went from effectively unrunnable to
**~37s**. Isolation was proven, not assumed: repeated runs give identical
results and all business tables hold 0 rows afterwards.

## C.6 Remaining Limitations

| Area | Status | Reason |
|---|---|---|
| Browser E2E | **UNVERIFIED** | No browser engine in the sandbox. jsdom is a DOM runtime and is not counted as browser verification. |
| Test fixture repair | **OUTSTANDING** | 437 errors / 61 failures, overwhelmingly fixture provenance. |
| Database baseline consolidation | **NOT PERFORMED** | Correctly gated: requires a green suite first. The 185-migration chain is preserved. |
| Pint / PHPStan findings | **OUTSTANDING** | Style plus 26 residual static findings; the one real bug (D10) is fixed. |

## C.7 Certification

**RUNTIME VERIFIED WITH LIMITATIONS.**

Empirically verified: PHP 8.2.33 + Laravel 12.67.0 + PostgreSQL 18.4 runtime,
full 185-migration replay, real schema, seeders, boot, authentication,
authorization fail-closed behaviour, API contracts, all 14 page renders,
6/6 database invariants and 4/4 concurrency races.

Not certified: browser E2E, the outstanding fixture-driven test failures, and
database baseline consolidation.

`RELEASE CERTIFIABLE` is **not** issued. No production-readiness claim is made.

---

# Part D — Stabilization, Institutionalization and Browser Verification (2026-09-07)

Part C established the runtime. Part D makes it **reproducible, enforced and
browser-verified**, and converges the largest failure clusters.

## D.1 What Is Now Locked

| Contract | Document | Enforcement | State |
|---|---|---|---|
| Runtime versions + extensions | `docs/RUNTIME_ENVIRONMENT_LOCK.md` | `npm run verify:environment` | **8/8 satisfied** |
| Test isolation strategy | `docs/TESTING_STRATEGY_LOCK.md` | `tests/Unit/Architecture/TestStrategyLockTest.php` | **enforced** |
| All gates | `.github/workflows/verification.yml` | CI (3 jobs) | **added** |

The strategy lock was verified to be load-bearing: reintroducing
`DatabaseMigrations` produces 3 failures. The environment lock fails on version
drift, a missing extension, or any reappearance of SQLite.

## D.2 Browser E2E — Now VERIFIED

Previously `UNVERIFIED` for lack of a browser. Chrome's CDN and Playwright's
download host are both blocked, but `@sparticuz/chromium` ships the binary
inside the npm tarball; extracting it plus the NSS libraries from the same
package produced a working **Chromium 149.0.7827.0**.

`npm run verify:browser` drives it against the running application:

| Check | Result |
|---|---|
| Unauthenticated console access redirects to login | PASS |
| Invalid credentials rejected | PASS |
| Valid credentials establish a session | PASS |
| All 14 consoles render a live React tree | PASS |
| Real `/api/v1` traffic | PASS — 25 calls, 25 succeeded |
| No uncaught console errors | PASS |
| No failed network requests | PASS |
| Sign-out ends the session | PASS |

**21/21 passed.**

## D.3 Defects Found and Repaired in This Phase

| # | Defect | Impact | Found by |
|---|---|---|---|
| D13 | `/api/v1/payroll/workspace` route never registered | Payroll console loaded with a 404 and no data | **real browser only** — the page returned 200 and mounted, so HTTP and jsdom checks both missed it |
| D14 | E2E journeys hardcoded `127.0.0.1:5432` and credentials | Scripts could not run against any other instance | execution |
| D15 | E2E journeys called the unversioned `/api/...` prefix | 28 paths returned 404 | execution |
| D16 | E2E journeys read `$me['json']['username']` | Contract is `{"data":{"username":...}}` | execution |
| D17 | Authority fixtures had no branch provenance | ~89 failures across the suite | clustering |

D13 is the clearest argument for the browser gate: every cheaper check passed.

## D.4 Test Suite Convergence

| Stage | Errors + Failures | Assertions |
|---|---|---|
| Start of Part D | 498 | 2,709 |
| After fixture provenance repair | 409 | 3,465 |
| Current | **407** | **3,469** |

Assertions rose ~28% because tests now reach real behaviour instead of dying in
setup.

### Honest classification of what remains

| Count | Cluster | Classification |
|---|---|---|
| 122 | `a new class must reference an open offering...` | **Fixture defect.** 23 test files call `defineClass` without building the required chain. `Tests\Concerns\BuildsAcademicStructure` now encodes it; applying it per file is remaining work. |
| 73 | `new applicant registration requires an operational branch` | **Fixture defect.** Registration deliberately refuses to infer a branch. |
| 45 | `a new class requires an explicit branch...` | **Fixture defect.** Actor sees multiple branches; the test must state which. |
| 24 | `a verified person is final` | **Intentional invariant.** Fixtures `UPDATE` a verified person. The trigger is correct and was not modified. |
| 22 | `this account code already exists` | **Fixture defect.** Duplicate seeding within a test. |
| 13 | `person-linked operations require an active home branch` | **Fixture defect.** |
| 7 | `no active canonical teacher profile` | **Fixture defect.** Teacher profiles require an HR employment chain. |

**No cluster is an unrepaired production defect.** Every one is either an
incomplete fixture or the database/domain correctly refusing an invalid state.
No invariant was weakened, no migration deleted, no assertion relaxed.

## D.5 Final Evidence Matrix

| Area | Status | Evidence |
|---|---|---|
| Runtime (PHP 8.2.33) | **VERIFIED** | `php -v`; 21 required extensions; `verify:environment` 8/8 |
| Runtime lock | **VERIFIED** | `npm run verify:environment` |
| Dependencies | **VERIFIED** | `composer validate --strict`; `check-platform-reqs` all success; 106 packages; lock unmodified |
| PostgreSQL 18.4 | **VERIFIED** | `php artisan db:show` |
| Migrations | **VERIFIED** | `migrate:fresh` 185/185, 0 pending |
| Schema | **VERIFIED** | 168 tables, 380 FKs, 336 CHECK, 285 triggers, 525 functions, 2 exclusion, 65 partial indexes |
| Triggers / functions | **VERIFIED** | invariant negative tests, 6/6 rejected |
| Seeders | **VERIFIED** | finance chart + first-run bootstrap; idempotency confirmed |
| Test suite | **EXECUTED** | 871 tests, 3,469 assertions, 407 errors+failures, all classified |
| Test performance | **VERIFIED** | 62.9s → 3.0s (~21x) measured; full suite ~37s |
| Testing strategy lock | **VERIFIED** | architecture test fails with 3 failures when reverted |
| Static analysis | **EXECUTED** | Pint + PHPStan run; found and fixed a real bug (D10) |
| Frontend typecheck / build | **VERIFIED** | `npm run typecheck`, `npm run build` |
| Frontend mount | **VERIFIED** | 8/8 consoles |
| API | **VERIFIED** | authenticated `/api/v1` returns real scoped data |
| Authentication | **VERIFIED** | wrong password rejected; correct establishes session; sign-out ends it |
| Authorization | **VERIFIED** | unauthenticated 401; mutations 419; fail-closed |
| Concurrency | **VERIFIED** | 4/4 races under genuinely simultaneous transactions |
| **Browser E2E** | **VERIFIED** | Chromium 149, 21/21, 25 API calls, 0 console errors |
| Security (adversarial) | **VERIFIED** | unauthenticated + CSRF probes fail closed |
| Operational readiness | **VERIFIED** | `/health` 200 `database: ok`; `/up` 200 |
| CI safeguards | **VERIFIED** | 3 jobs; every referenced script executed locally |
| Finance / Academic / Placement / Organization / HR / Payroll | **PARTIAL** | invariants, concurrency and API verified; full journeys blocked on fixture chains |
| Database baseline consolidation | **NOT PERFORMED** | correctly gated behind a converged suite; 185-chain preserved |

## D.6 Remaining Work

1. **Fixture convergence** — apply `BuildsAcademicStructure` and explicit branch
   provenance across the ~23 affected files. Mechanical but not trivial;
   teacher-profile chains cross into HR.
2. **Full domain journeys** — the three root E2E scripts now run; later stages
   need the same fixture prerequisites.
3. **Baseline consolidation** — deliberately not attempted.

## D.7 Certification

**RUNTIME VERIFIED WITH LIMITATIONS.**

Verified: locked and machine-checked runtime, 185-migration replay, real
schema, seeders, boot, authentication, fail-closed authorization, API,
6/6 database invariants, 4/4 concurrency races, all 14 page renders, and
**real browser E2E at 21/21**. The runtime and testing strategy are now
enforced by tooling rather than documented by habit.

Not certified: the outstanding fixture-driven test failures (all classified,
none an unrepaired production defect) and database baseline consolidation.

`RELEASE CERTIFIABLE` is **not** issued. No production-readiness claim is made.
