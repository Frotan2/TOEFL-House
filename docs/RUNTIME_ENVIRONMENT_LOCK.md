# TOEFL House — Runtime Environment Lock

**STATUS: CURRENT CANONICAL — AUTHORITATIVE RUNTIME SPECIFICATION**  
**Last reconciled:** 2026-09-09  
**Machine enforcement:** `npm run verify:environment`

This document defines the supported verification/runtime contract. Do not infer a runtime from historical reports. The machine-checking script is the enforcement mechanism; this document records the intended locked set and operational doctrine.

## 1. Locked runtime

| Component | Locked version | Enforcement / source |
|---|---:|---|
| PHP | **8.4.25** | CI environment + `verify:environment` |
| Composer | **2.10.3** | CI environment + `verify:environment` |
| Laravel | **12.67.0** | `composer.lock` / `composer.json` |
| PostgreSQL | **18.4** | CI service + `verify:environment` |
| Node | **22.22.3** | `package.json` engines + CI |
| npm | **10.9.8** | CI + `package.json` engines |
| React | **19.1.1** | `package.json` / lockfile |
| Vite | **7.3.6** | `package.json` / lockfile |
| TypeScript | **5.9.x** | `package.json` / lockfile |

**Laravel 13 is prohibited.**  
**PostgreSQL is the only supported database.** SQLite must not become a hidden fallback.

Any version change requires a fresh runtime verification and synchronized documentation update. Do not upgrade dependencies merely to make a failing test disappear.

## 2. Required PHP/runtime capabilities

The installed runtime must satisfy Composer and application requirements, including at minimum:

`pdo_pgsql, mbstring, dom, xml, xmlwriter, tokenizer, curl, bcmath, iconv, fileinfo, openssl, pcntl, posix, sockets`

The exact loaded set is verified by `npm run verify:environment` in CI and must not be guessed from local development environments.

## 3. Database contract

- Connection name: `pgsql`
- PostgreSQL major: 18
- PostgreSQL-specific constraints, triggers, functions, partial indexes and exclusion constraints are part of the domain contract.
- The migration chain remains authoritative until a separately approved schema-baseline freeze/consolidation is completed.
- No fake baseline, skipped migration, synthetic schema or guessed replacement is permitted.

## 4. Verification commands

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

Release-critical business journeys, when applicable, are also executed against fresh first-boot databases over real HTTP.

## 5. Runtime evidence doctrine

- `VERIFIED` means actually executed and observed.
- `STATICALLY VERIFIED` means proven without a live runtime.
- `UNVERIFIED` means insufficient evidence.
- `BLOCKED` means a required prerequisite prevented execution.
- `FAILED` means execution occurred and the gate failed.

A prior green run is evidence only for the commit it executed. A superseded run never certifies the current commit.

## 6. Browser/runtime asset rule

All application shells that can be reached through nested routes must resolve CSS/JS assets from the document origin rather than relative-to-route URLs. Keep the canonical `<base href>` contract synchronized between `resources/views/workspace.blade.php`, legacy layouts and their frontend contract tests.

This rule exists because the real Chromium E2E previously exposed an asset-path failure where stylesheet requests were resolved with a `/login` suffix, preventing React from mounting.

## 7. Runtime handoff

The current implementation and release state are governed by:

- `docs/RUNTIME_VERIFICATION_HANDOFF.md`
- `docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`
- latest non-superseded Verification workflow on `main`

Do not copy historical version numbers or certification claims from dated audit files into current status documents.
