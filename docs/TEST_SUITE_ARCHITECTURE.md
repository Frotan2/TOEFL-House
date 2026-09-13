# TOEFL House — Test Suite Architecture

**STATUS: ACTIVE / CANONICAL / NORMATIVE**

This document defines how TOEFL House is tested. It is mandatory for new work.

The governing principle is not coverage percentage or a green count:

> A passing result must be meaningful. A smaller suite that proves more is better than a large suite of shallow tests.

Related contracts:
- `docs/TESTING_STRATEGY_LOCK.md` — database isolation and strategy enforcement.
- `docs/RUNTIME_ENVIRONMENT_LOCK.md` — supported runtime and machine verification.
- `docs/RUNTIME-RELEASE.md` — release evidence and certification rules.

## 1. Verification layers

| Layer | Location | Proves | Must not |
|---|---|---|---|
| Unit | `tests/Unit` | Pure logic, value objects, architecture rules | Replace database/domain proof |
| Canonical domain | `tests/Canonical/<Domain>` | Business invariants through production commands and persisted state | Use mocks as authority proof |
| Database invariant | `scripts/runtime/database-invariants.mjs` | PostgreSQL rejects invalid state | Assert only through application code |
| API | `tests/Feature/Api` | HTTP contract and resulting persisted state | Stop at HTTP 200 |
| Authorization/security | `tests/Canonical/Access`, `tests/Feature/Security` | Positive and negative authority, scope and denial behavior | Rely on frontend visibility |
| Concurrency | `scripts/runtime/concurrency-verification.mjs` | Simultaneous committed transactions preserve invariants | Simulate a race sequentially |
| Page render | `tests/Feature/Http` | Authenticated Blade transport surfaces render successfully | Replace browser verification |
| Frontend mount | `tests/Frontend` | Bundles mount and contract checks pass in the DOM runtime | Claim browser certification |
| Browser E2E | `scripts/runtime/browser-e2e.mjs` | Real Chromium auth, rendering, API traffic and console behavior | Be replaced by jsdom |

Concurrency and database-invariant probes live outside PHPUnit because they require committed state and/or genuinely separate database sessions. Browser verification also requires committed state served over HTTP.

## 2. Canonical domain coverage

`tests/Canonical` is the preferred authority for new domain tests. It is derived from the current implementation, schema, authorization and lifecycle rules rather than from historical test expectations.

Rules:
1. Extend `Tests\\Canonical\\CanonicalTestCase`.
2. Build state through production commands whenever a command exists.
3. Assert persisted state, not return values alone.
4. Include negative cases and prove rejected mutations leave no unauthorized state.
5. Name behavior, not implementation methods.

Run it with:

```bash
vendor/bin/phpunit --testsuite Canonical --no-coverage
```

## 3. Fixture strategy

Shared fixture traits encode valid production provenance. In particular:

- `SeedsAuthority` builds organization → campus → branch → assignment → actor authority.
- `BuildsActors` uses the real access model rather than fake capability state.
- `BuildsStudents` preserves originating branch provenance.
- `BuildsAcademicStructure` builds the required academic chain through an open offering.
- `BuildsTeachers` builds the required identity, employment, contract, qualification and authorization chain.

A fixture that cannot be produced by the current production lifecycle is not evidence of a production defect. Fix the fixture or expectation; never weaken a correct invariant merely to make the test pass.

## 4. Database strategy

`tests/TestCase.php` uses `RefreshDatabase`. `DatabaseMigrations` is prohibited because replaying the entire migration chain for every test is materially slower and obscures failures.

PostgreSQL is the supported database. SQLite is not a fallback.

Tests that require genuinely committed concurrent transactions or direct PostgreSQL invariant rejection belong in the dedicated runtime verification scripts rather than being simulated inside the transaction-wrapped PHPUnit suite.

## 5. Testing the tests

A test that remains green while its target production rule is broken is defective.

For material canonical/invariant tests, verification should include a controlled mutation of the protected production rule, confirmation that the intended test fails, restoration of the rule, and a green rerun. Mutation evidence belongs to the engineering record of that change; this document does not freeze historical test counts or run durations as current facts.

Representative previously executed mutation checks have included monetary over-allocation, academic capacity, authorization decisions, API registration, page rendering, database uniqueness, test-strategy enforcement, React mount failures, staged asset-disposal withdrawal and document evidence lifecycle rules. Those examples describe the verification method, not permanent current test counts.

## 6. CI responsibilities

`.github/workflows/verification.yml` is authoritative for CI conclusions. It runs the applicable frontend, static-analysis, runtime-environment, migration, database-invariant, concurrency, backend and browser gates. The dedicated CRM browser workflow separately verifies the CRM lifecycle in real Chromium.

A local green result does not certify another commit. A test's existence does not prove execution. A historical green run never certifies the current `main` HEAD.

## 7. Anti-patterns

- Weakening an invariant to obtain green output.
- Deleting a migration to make replay pass.
- Mocking financial truth or authorization as a substitute for authoritative persistence proof.
- Asserting only HTTP status without resulting-state or authority checks.
- Calling jsdom browser certification.
- Calling sequential requests concurrency testing.
- Bypassing production commands to create impossible fixture state.
- Fixing backend authority defects in React.
- Deleting failing tests merely to reduce the failure count.

## 8. Legacy test retirement

`tests/Feature` and `tests/Unit` remain valid CI gates while the repository converges toward stronger canonical coverage. A legacy test may be retired only when its intent is demonstrably covered by a stronger canonical test, or when the protected architecture itself has been intentionally removed.

Classify legacy tests before deletion:

| Class | Meaning | Action |
|---|---|---|
| `RETAIN` | Valid intent without canonical equivalent | Keep and migrate later |
| `REWRITE` | Valid intent with obsolete fixture/expectation | Rewrite canonically |
| `MIGRATED` | Intent fully covered canonically | Safe to remove |
| `OBSOLETE` | Protected architecture no longer exists | Remove with evidence |
| `DUPLICATE` | Weaker duplicate of another test | Remove the weaker test |
| `INVALID FIXTURE` | Test creates impossible production state | Repair the fixture |

Retirement is evidence-driven, domain-by-domain. No test is deleted solely because it fails.

## 9. Completion rule

For material behavior, the strongest applicable chain is:

`production command → persisted state → DB invariant → authorization/scope → API → frontend → browser/E2E → operational evidence`

Only the layers that are materially applicable are required, but omitted layers must be consciously classified rather than silently ignored.
