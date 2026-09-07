# TOEFL House — Runtime Verification Handoff

**STATUS: CURRENT HANDOFF — RUNTIME VERIFICATION PENDING**

This document is the canonical handoff record for the next engineering agent. It records what has been established before real runtime verification and what still requires an executable environment.

## 1. Current Repository Identity

| Item | Current evidence |
|---|---|
| Repository | `Frotan2/TOEFL-House` |
| Working branch | `frontend-transformation-2026-09` |
| Current HEAD | `c092c92809232d93c14c962174c6623290ab1a7b` |
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

- The active migration chain contains exactly **190 migrations**, ending at `000190`.
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
| Migration replay | 190 migrations inventoried; chain retained | Replay from zero in disposable PostgreSQL | BLOCKED |
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
