# TOEFL House — Runtime, Verification & Release Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main` + `.github/workflows/verification.yml`  
**Last reconciled:** 2026-09-09  
**Purpose:** Single source of truth for supported runtime, verification layers, evidence semantics and release certification.

> This document is the only current runtime/release control document. Historical reports are provenance only.

## 1. Locked verification environment

| Component | Required version | Enforcement |
|---|---:|---|
| PHP | **8.4.25** | CI + `npm run verify:environment` |
| Composer | **2.10.3** | CI + environment verification |
| Laravel | **12.67.0** | `composer.lock` / `composer.json` |
| PostgreSQL | **18.4** | CI service + environment verification |
| Node | **22.22.3** | CI + `package.json` engines |
| npm | **10.9.8** | CI + package engines |
| React | **19.1.1** | package lock |
| Vite | **7.3.6** | package lock |
| TypeScript | **5.9.x** | package lock |

PostgreSQL is the only supported database. SQLite is not a fallback. Laravel 13 is prohibited for this release line.

Any runtime or dependency change requires fresh applicable verification.

## 2. Verification layers

Applicable release evidence includes environment validation, dependency/platform validation, fresh migrations/bootstrap, invariant verification on real PostgreSQL, concurrency verification on real PostgreSQL, backend tests, frontend type/build/mount checks, browser/E2E, security/adversarial checks and deployment/recovery evidence where required.

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
npm run verify:browser
```

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

`current main HEAD → latest non-superseded Verification evidence → applicable manual release evidence`

Certification is attached to an exact commit. Any later commit requires fresh applicable verification.

Current snapshot at reconciliation:

- **main HEAD:** `e16a0c9ccd716d7853f27d3ce56de0a6bcbe30cf`
- **Latest Verification:** run #465 / workflow id `34369571962`
- **Status:** PENDING
- **Conclusion:** not yet available
- **Release state:** **NOT RELEASE CERTIFIED**

Run #464 on the immediately previous documentation commit was cancelled and does not certify the current HEAD.

## 5. Domain release gate

The active material domain is **Library & Resources**. Its closure requires all materially applicable implementation, backend authority, database, authorization/scope, API, React, UX, audit/provenance, idempotency, concurrency, test, browser/E2E, security and operational evidence gates.

Domain maturity is recorded only in `docs/DOMAIN-REGISTRY.md`; active execution control is recorded only in `docs/OPERATING-CONTROL.md`.

## 6. Browser asset contract

All application shells reachable through nested paths must resolve application assets from the document origin rather than relative-to-route URLs. Keep canonical `<base href>` behavior synchronized between `resources/views/workspace.blade.php`, legacy layouts and frontend mount tests.

This guard remains because browser verification previously exposed a nested `/login` asset-path failure that prevented React from mounting.

## 7. Release rules

A release claim is prohibited when any required gate is unexecuted, pending, blocked, failed, based only on historical evidence, based on a superseded commit, or contradicted by current source inspection.

`IMPLEMENTATION COMPLETE` may exist before runtime proof. `RELEASE CERTIFIED` may not.

## 8. Documentation evidence rule

Documentation records control rules and interpretation. It does not create execution evidence. After a material change, current status must be derived from the actual repository and latest workflow rather than copied from older reports.
