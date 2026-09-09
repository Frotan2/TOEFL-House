# TOEFL House — Testing / Quality / Release

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Evidence hierarchy

1. test exists;
2. test currently passes;
3. test passes in the official runtime;
4. release-critical behavior is exercised in a production-like environment.

These are different claims and must never be conflated.

## Required layers

- PHP syntax/lint
- unit/feature/integration tests
- database schema/invariant tests
- authorization/scope tests
- concurrency tests on real PostgreSQL
- frontend type/build checks
- browser/E2E where applicable
- deployment/recovery checks
- security/adversarial tests

## Concurrency

Critical races must be tested with genuine multi-session PostgreSQL behavior, not mocks alone.

## Release gate

`RELEASE READY` is permitted only when the official runtime stack has been used and all material release gates have actually passed.

## Current release distinction

Target-state completeness and runtime certification are independent judgments. The repository may be target-complete but runtime-unverified, or runtime-proven while still having approved non-blocking product breadth gaps.


---

# Consolidated Governing Evidence

## Canonical Testing Strategy

`docs/TESTING_STRATEGY_LOCK.md` is the mandatory testing strategy and takes
precedence over any older guidance in this file.

Key points:

- `tests/TestCase.php` uses `RefreshDatabase` (migrate once per process, then
  roll back a transaction per test). `DatabaseMigrations` replays all 185
  migrations per test and is prohibited; it made the suite unrunnable.
- The rule is enforced by `tests/Unit/Architecture/TestStrategyLockTest.php`,
  not by convention.
- Concurrency, database-invariant and browser verification deliberately live
  outside PHPUnit because they need committed state:
  `npm run verify:concurrency`, `npm run verify:invariants`,
  `npm run verify:browser`.
- Runtime versions are locked by `docs/RUNTIME_ENVIRONMENT_LOCK.md` and checked
  by `npm run verify:environment`.

CI runs all of the above (`.github/workflows/verification.yml`).
