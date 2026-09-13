# TOEFL House — Runtime, Verification & Release Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main` + `.github/workflows/verification.yml`  
**Last reconciled:** 2026-09-10
**Purpose:** Single source of truth for release policy, verification layers, evidence semantics and release certification.

> This is the current runtime/release control document. Its technical compatibility
> ranges are defined by [`RUNTIME_ENVIRONMENT_LOCK.md`](RUNTIME_ENVIRONMENT_LOCK.md),
> whose machine enforcer deploys and CI run. This document deliberately does not
> hard-code a commit SHA or workflow run number because every material repository
> change can supersede the previous evidence.

## 1. Runtime compatibility and execution references

| Component | Supported range | CI / Windows reference | Reproducible local reference |
|---|---|---:|---:|
| PHP | `>=8.2 <8.5` | 8.4.25 | 8.4.14 |
| Composer | `>=2.5 <3` | 2.10.3 | 2.9.2 |
| Laravel | `>=12.67 <13.0` | 12.67.0 | 12.67.0 |
| PostgreSQL | `>=18.0 <19.0` | 18.4 | 18.4 |
| Node | `>=22.0 <23.0` | 22.22.3 | 22.22.3 |
| npm | `>=10.0 <11.0` | 10.9.8 | 10.9.8 |
| React | lockfile-controlled | 19.1.1 | 19.1.1 |
| Vite | lockfile-controlled | 7.3.6 | 7.3.6 |
| TypeScript | lockfile-controlled | 5.9.x | 5.9.x |

CI and platform launchers intentionally use concrete reference versions inside
the supported ranges. Host eligibility is enforced by the compatibility lock,
not by duplicating those patch pins in deployment code. PostgreSQL is the only
supported database, SQLite is not a fallback, and Laravel 13 is prohibited for
this release line.

Any runtime or dependency change requires fresh applicable verification.

## 2. Verification layers and quality standard

Applicable release evidence includes environment validation, dependency/platform validation, fresh migrations/bootstrap, invariant verification on real PostgreSQL, concurrency verification on real PostgreSQL, backend tests, frontend type/build/mount checks, browser/E2E, security/adversarial checks and deployment/recovery evidence where required.

Required claims remain distinct: a test exists, a test passes, a test passes in the official runtime, and release-critical behavior is exercised in a production-like environment are four different claims.

Material changes require the strongest practical regression coverage for authority, lifecycle, scope and compatibility boundaries. Correct invariants, authorization checks and assertions must never be weakened merely to obtain green output. Concurrency, database-invariant and browser gates that require committed state belong to the dedicated verification commands rather than being implied by PHPUnit success.

Canonical commands:

```bash
npm ci --engine-strict
npm run verify:environment
composer validate --strict
composer check-platform-reqs
php artisan migrate:fresh --force
php artisan db:seed --class=StandardFinanceChartSeeder --force
npm run verify:invariants
npm run verify:concurrency
vendor/bin/phpunit --no-coverage
npm run typecheck
npm run build
npm run test:frontend
npm run test:runtime-safety
npm run verify:browser
```

### PostgreSQL verification safety and scope

Run `verify:invariants` and `verify:concurrency` only after a fresh migration on
an isolated, disposable verification database. Both commands deliberately write
short-lived probe rows; the invariant command rolls every probe back, while the
concurrency command verifies and removes its committed race fixtures. They fail
closed for database names that do not identify a disposable target (`dev`,
`test`, `ci`, `e2e`, or `verify`) unless an operator explicitly sets
`RUNTIME_VERIFICATION_ALLOW_MUTATING_DATABASE=1`. That escape hatch is for a
reviewed non-production rehearsal only, never a live business database.

Before either command writes, it opens one timeout-bounded transaction, proves
that its connected verifier role can run `SET LOCAL session_replication_role =
'replica'`, reads the setting back, and rolls the transaction back. The verifier
login must therefore be either a PostgreSQL superuser or a dedicated role granted
that parameter privilege by a database administrator:

```sql
GRANT SET ON PARAMETER session_replication_role TO toefl_house_verifier;
```

The grant is intentionally insufficient on its own: the verifier also needs the
DML privileges that the scripts preflight on their real fixture tables, and the
invariant verifier must be authorized to execute `ALTER TABLE ... DISABLE TRIGGER
USER` on `payments`, `enrollments`, `classes`, and `journal_lines`. The commands
fail before fixture writes if those checks fail. Never grant this trigger-bypass
capability to the normal application role; use a dedicated verifier only on the
isolated disposable database.

The invariant command asserts the exact named PostgreSQL check, foreign-key and
unique-index boundaries reached by each probe, rather than accepting an earlier
workflow trigger or an unrelated foreign-key failure. Both commands pin their
connection `search_path` to `public`, preventing a verifier role's personal
schema from shadowing the migrated application tables. The concurrency command
uses independent PostgreSQL backends against the actual `idempotency_keys`,
`scope_grants`, `org_wide_grant_requests`, and `accounts` tables; it creates no
mirror schema. Do not run either command concurrently with migrations or the
transaction-wrapped PHPUnit database suite.

The official Verification workflow is authoritative for CI conclusions.

## 3. Evidence vocabulary

| Evidence | Meaning |
|---|---|
| `VERIFIED` | Required action actually executed and observed successfully. |
| `STATICALLY VERIFIED` | Source/config/tooling inspection proves a property without live execution. |
| `UNVERIFIED` | Required proof is unavailable. |
| `BLOCKED` | A prerequisite prevented execution. |
| `FAILED` | Gate executed and failed. |
| `PENDING` / `QUEUED` | Workflow has not produced a final conclusion. |
| `HISTORICAL` | Evidence belongs to an older commit and never certifies current `main`. |

A test's existence is not proof of execution. A green old run is not proof of a later commit.

## 4. Current release control

Current release truth is always calculated from:

`actual main HEAD → latest non-superseded Verification workflow for that HEAD → applicable manual release evidence`

Certification is attached to an exact commit. Any later commit requires fresh applicable verification.

At the current documentation baseline the release state is **CERTIFIED for `main` HEAD `b824efd`**: Verification run 34745363765 concluded successfully across all four jobs (static analysis, backend, frontend, browser E2E) and CRM Browser E2E run 34745363720 concluded successfully alongside, both on that exact commit.

`b824efd` was produced by documentation-only PR #30, which superseded the prior certification at `27b96d8` (PR #29, Verification run 34744897784, CRM Browser E2E run 34744897779). The compare between the two commits touches four Markdown files and no source, test, migration, workflow or route file, so the certification was re-earned by execution at the new HEAD rather than inherited from the old one. That certification lapses with the next commit to `main`, which requires its own applicable evidence.

## 5. Domain release gate

Library & Resources, Documents & Evidence and Privacy & Consent are each closed, and each closure was certified on `main` (Library and Documents by Verification run 34735425882 at HEAD `0e46611`, Privacy by run 34744897784 at HEAD `27b96d8`, re-verified at the current HEAD `b824efd` by run 34745363765 after documentation-only PR #30). Every closure required all materially applicable implementation, backend authority, database, authorization/scope, API, React, UX, audit/provenance, idempotency, concurrency, test, browser/E2E, security and operational evidence gates.

The next-domain reassessment has since been performed: **CRM / Front Office is the selected active material domain and is not started**. Its release gate is not yet open. No CRM or Front Office work may begin, and no domain may be promoted, until the baseline gate in `docs/OPERATING-CONTROL.md` §4 is satisfied against the *actual current* `main` HEAD and recorded there with its run identifiers. Because that gate is re-evaluated at every commit, `b824efd` evidence does not carry forward past the merge that records the selection.

Domain maturity is recorded only in `docs/DOMAIN-REGISTRY.md`; active execution control is recorded only in `docs/OPERATING-CONTROL.md`.

## 6. Browser asset contract

All application shells reachable through nested paths must resolve application assets from the document origin rather than relative-to-route URLs. Keep canonical `<base href>` behavior synchronized between `resources/views/workspace.blade.php`, legacy layouts and frontend mount tests.

This guard remains because browser verification previously exposed a nested `/login` asset-path failure that prevented React from mounting.

## 7. Deployment and recovery boundary

`docs/operations/production-deployment.md` is the executable production deployment/recovery runbook. It owns step-by-step operator procedure; this document owns only the release gate and evidence semantics. Application rollback does not imply database rollback. Schema compatibility, backup/restore, readiness and recovery drills must be executed according to the runbook when required; a documented procedure is never proof that the drill occurred.

## 8. Release rules

A release claim is prohibited when any required gate is unexecuted, pending, blocked, failed, based only on historical evidence, based on a superseded commit, or contradicted by current source inspection.

`IMPLEMENTATION COMPLETE` may exist before runtime proof. `RELEASE CERTIFIED` may not.

## 9. Documentation evidence rule

Documentation records control rules and interpretation. It does not create execution evidence. After a material change, derive current status from the actual repository and latest workflow rather than copying an older report.
