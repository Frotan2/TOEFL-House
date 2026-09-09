# TOEFL House — Runtime, Verification & Release Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main` + `.github/workflows/verification.yml`  
**Last reconciled:** 2026-09-09  
**Purpose:** Single source of truth for supported runtime, verification layers, evidence semantics and release certification.

> This document replaces separate runtime-lock, verification-handoff, verification-standard and release-certification documents. Historical reports are evidence only.

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

Any runtime/dependency change requires new verification; documentation must follow the actual lock and tooling.

## 2. Required verification layers

The applicable release chain includes:

1. environment/platform validation;
2. Composer/package validation;
3. migrations and fresh database bootstrap;
4. invariant verification on real PostgreSQL;
5. concurrency verification on real PostgreSQL;
6. backend PHPUnit suite;
7. frontend typecheck/build/mount tests;
8. browser/E2E against the real application;
9. security/adversarial scope checks;
10. deployment/recovery evidence where required by the release target.

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
| `UNVERIFIED` | Required proof is not available. |
| `BLOCKED` | A prerequisite prevented execution. |
| `FAILED` | Gate executed and failed. |
| `HISTORICAL` | Evidence belongs to an older commit and cannot certify current `main`. |

A test's existence is not proof of execution. A green old run is not proof of a later commit.

## 4. Current release control

Current release truth is always calculated from:

`current main HEAD → latest non-superseded Verification evidence → applicable manual release evidence`

A certification is attached to an exact commit. Any later commit requires fresh applicable verification.

Current control snapshot at reconciliation:

- `main` HEAD: `99c2b70b944e4365349e6c9ce25eb9c66d825af0`
- Verification run: **#457 / 34368019057**
- Result at reconciliation: **QUEUED**
- Therefore: **NOT RELEASE CERTIFIED**

Do not replace these values with historical certification claims until a later current-main run has actually completed.

## 5. Domain release gate

The active domain is Library & Resources. Domain closure requires all materially applicable implementation, backend authority, database, security/scope, API, React, UX, audit/provenance, idempotency, concurrency, test, browser/E2E and operational evidence gates to pass.

The full domain state is maintained only in `DOMAIN-REGISTRY.md` and the active execution gate in `OPERATING-CONTROL.md`.

## 6. Browser asset contract

All application shells reachable through nested paths must resolve application assets from the document origin, not relative-to-route URLs. Keep the canonical `<base href>` behavior synchronized between `resources/views/workspace.blade.php`, legacy layouts and their frontend mount tests.

This guard exists because browser verification previously exposed a nested `/login` asset-path failure that prevented React from mounting.

## 7. Release rules

A release claim is prohibited when any applicable gate is:

- unexecuted;
- blocked;
- failed;
- based only on historical evidence;
- based on a superseded commit;
- contradicted by current source inspection.

A domain may be called `IMPLEMENTATION COMPLETE` before runtime proof, but it may not be called `RELEASE CERTIFIED` before current runtime proof.

## 8. Documentation evidence rule

Documentation records controls and interpretation; it does not create execution evidence. After every material code/runtime change, update the operational documents only after inspecting the resulting repository and the actual verification outcome.

Historical audit reports must remain labeled `HISTORICAL` and must not be rewritten into current truth.
