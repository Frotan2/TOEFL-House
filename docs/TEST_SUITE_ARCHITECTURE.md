# TOEFL House — Test Suite Architecture

**This document defines how TOEFL House is tested. It is mandatory for new
work.**

The governing principle is not coverage percentage and not a green count:

> A passing result must be *meaningful*. A smaller suite that proves more is
> better than a large suite of shallow tests.

Related contracts: `docs/TESTING_STRATEGY_LOCK.md` (isolation strategy, and the
architecture test that enforces it) and `docs/RUNTIME_ENVIRONMENT_LOCK.md`
(runtime versions, enforced by `npm run verify:environment`).

---

## 1. Layers, and What Each Layer Proves

| Layer | Location | Proves | Must not |
|---|---|---|---|
| **Unit** | `tests/Unit` | Pure logic, value objects, architecture rules. No database. | Own domain rules that the database enforces. |
| **Canonical domain** | `tests/Canonical/<Domain>` | Business invariants through **production commands**, asserted against persisted state. | Use mocks for money, authority or lifecycle. |
| **Database invariant** | `scripts/runtime/database-invariants.mjs` | PostgreSQL itself rejects invalid state. | Assert via the application layer. |
| **API** | `tests/Feature/Api` | Transport contract: status, shape, and resulting database state. | Stop at HTTP 200. |
| **Authorization / security** | `tests/Canonical/Access`, `tests/Feature/Security` | Positive *and* negative authority; denial persists nothing. | Rely on the frontend hiding a control. |
| **Concurrency** | `scripts/runtime/concurrency-verification.mjs` | Invariants hold under genuinely simultaneous transactions. | Simulate a race sequentially. |
| **Page render** | `tests/Feature/Http` | Blade templates render below 500 for authenticated routes. | Replace browser verification. |
| **Frontend mount** | `tests/Frontend/mount.test.mjs` | Console bundles mount in a DOM without throwing. | Be called browser certification. |
| **Browser E2E** | `scripts/runtime/browser-e2e.mjs` | Real Chromium: auth, render, live API traffic, console errors. | Be replaced by jsdom. |

Two failure modes this split exists to prevent:

- **Everything as E2E** — slow, flaky, and bad at explaining *why*.
- **Critical behaviour as unit-only** — money, authority and capacity are
  enforced by the database and by commands, so unit tests cannot prove them.

### Why concurrency and invariants live outside PHPUnit

`RefreshDatabase` wraps each test in a transaction that is rolled back. That is
correct for almost everything and wrong for exactly two cases: a race needs
*separate, concurrently committed* transactions, and a browser needs committed
state served over HTTP. Those live in `scripts/runtime/` and run via npm.

---

## 2. The Canonical Suite

`tests/Canonical` is the authority for new domain coverage. It is derived from
the **current implementation** — commands, schema, triggers, authorization,
lifecycle — never from what an older test happened to assert.

Rules:

1. Extend `Tests\Canonical\CanonicalTestCase`.
2. Build state with **production commands**. If a command exists, use it.
3. Assert against **persisted state** (`assertDatabaseHas`, direct queries),
   not return values alone.
4. Include the negative case. A rule is only proven when the violation is
   rejected *and* leaves nothing behind.
5. Name the behaviour, not the method:
   `test_allocation_cannot_exceed_the_obligation_it_settles`.

Run it alone:

```bash
vendor/bin/phpunit --testsuite Canonical --no-coverage
```

---

## 3. Fixture Strategy

Fixtures caused the large majority of historic failures. The recurring mistake
was creating *incomplete* domain state and then treating the domain's refusal
as a bug.

| Trait | Builds | Notes |
|---|---|---|
| `SeedsAuthority` | organization → campus → branch → campus assignment; people with capabilities | `ensureBootstrapAuthority()` seeds the **whole** provenance chain. |
| `BuildsActors` | Actors with named capability sets | Authority is granted through the real access model. |
| `BuildsStudents` | person → applicant → admission → student | Passes an explicit originating branch. |
| `BuildsAcademicStructure` | program → version → level → period → availability → **open offering** | `buildAcademicChain()`. |
| `BuildsTeachers` | identity → employment → signed contract → hire → profile → verified qualification → activation | `buildActiveTeacher()`; register and approve use **distinct** actors. |

Hard-won rules encoded in those traits:

- **Provenance is mandatory.** Scope resolves
  `Person.home_branch_id → Branch → active CampusAssignment → Organization`.
  A fixture that stops at the organization is incomplete.
- **A verified person is immutable.** Set `home_branch_id` on insert; a later
  `UPDATE` is correctly rejected by `people_identity_guard`.
- **Teacher assignment needs a full HR chain.** Hiring requires an active
  signed contract; activation requires a verified qualification and an
  effective branch authorization.
- **Some rows are immutable by trigger.** To adjust fixture dates, delete and
  recreate rather than update.

---

## 4. Database Strategy

Locked in `docs/TESTING_STRATEGY_LOCK.md` and enforced by
`tests/Unit/Architecture/TestStrategyLockTest.php`:

- `tests/TestCase.php` uses **`RefreshDatabase`** — migrate once per process,
  roll back a transaction per test.
- `DatabaseMigrations` is **prohibited**: it replays the full current migration
  chain per test. Measured on 27 tests: **62.9s → 3.0s (~21×)**.
- PostgreSQL only. SQLite is deliberately compiled out of the PHP build.
- PostgreSQL's transactional DDL is why tests that issue
  `ALTER TABLE ... DISABLE TRIGGER` still roll back.

---

## 5. Testing the Tests

**A test that stays green while its target behaviour is broken is defective.**

Before trusting a new invariant test, break the production rule it protects and
confirm it fails. Verified examples:

| Mutation | Observed |
|---|---|
| Disable both over-allocation guards in `AllocatePayment` | **2 canonical tests failed** |
| Disable both capacity guards in `MaintainClass` | **3 canonical tests failed** |
| Disable `EnrollmentConstraints::assertCapacity` | **1 canonical test failed** |
| Make `AccessResolution::decide()` always allow | **4 canonical tests failed** |
| Remove the self-approval denial in `DisposeAsset::approve()` | **1 canonical test errored** — the database's independent-approver guard still refuses the signature |
| Remove the lost-copy denial in `CirculateBooks::issue()` | **1 canonical test failed** |
| Remove the completion-evidence denial in `MaintainWorkOrder::complete()` | **1 canonical test failed** |
| Unregister `/api/v1/payroll/workspace` | **2 API contract tests failed**, offending path named |
| Drop the `accounts_code_unique` index | **`verify:invariants` 6/6 → 5/6** |
| Reintroduce `DatabaseMigrations` | **3 strategy-lock tests failed** |
| Remove the `createRoot` import from `finance.tsx` | **mount test failed** |
| Unbalance the `workspace.blade.php` title | **7 page-render tests failed** |

Restore the mutation immediately and re-run to confirm green.

---

## 6. Performance

| Suite | Command | Measured |
|---|---|---|
| Canonical | `--testsuite Canonical` | **~4.8s** (13 tests) |
| Unit | `--testsuite Unit` | seconds |
| Everything | `vendor/bin/phpunit --no-coverage` | **~140s** (884 tests) |
| Frontend mount | `npm run test:frontend` | ~6s |
| Invariants | `npm run verify:invariants` | ~2s |
| Concurrency | `npm run verify:concurrency` | <1s |
| Browser E2E | `npm run verify:browser` | ~19s |

Debugging guidance: run the narrowest scope that reproduces the failure. Do not
loop the full suite, and do not wrap a suspected hang in a long blind timeout —
diagnose it (see the orphaned-process section of the strategy lock).

---

## 7. Anti-Patterns

| Anti-pattern | Why it is wrong |
|---|---|
| Weakening an invariant to get green | Removes the thing the test existed to prove. |
| Deleting a migration to make replay pass | Destroys schema history. |
| Mocking money | Finance is the sole monetary authority; mocks prove nothing about conservation. |
| Asserting only HTTP 200 | Says nothing about resulting state or ownership. |
| Calling jsdom "browser verification" | jsdom is a DOM runtime; it has no network stack or renderer. |
| Sequential calls labelled "concurrency" | A race requires simultaneous committed transactions. |
| Bypassing commands to insert fixture rows | Produces state production would refuse. |
| Fixing a backend defect in React | Wrong authority layer. |

---

## 8. Adding a Test (for the next engineer or agent)

1. Identify the layer from §1. Push it as low as it can honestly go.
2. Extend `CanonicalTestCase` for domain work.
3. Build state with the shared fixture traits.
4. Assert persisted state, and add the negative case.
5. **Mutate the production rule and confirm your test fails.**
6. Restore, re-run, and note the mutation result in the commit message.
7. Use `docs/CANONICAL_TERMINOLOGY.md` names — do not import older synonyms.

---

## 9. CI

`.github/workflows/verification.yml` runs the frontend gates, static analysis
and audits, the runtime environment lock, the full current migration-chain
replay, database invariants, concurrency, and the PHPUnit suite (which includes the
strategy lock). Browser E2E runs where a Chromium binary is available via
`CHROMIUM_PATH`.

---

## 10. Escape Audit

The system was challenged against the question it exists to answer: *can a
material defect reach production unnoticed?* Each row was executed, not
reasoned about.

| Can this escape? | Answer | Caught by |
|---|---|---|
| A Finance over-allocation bug | **No** | `MonetaryIntegrityTest` |
| An Academic capacity bug | **No** | `ClassLifecycleAndCapacityTest`, `EnrollmentCapacityTest` |
| An authorization bypass | **No** | `NegativeAuthorizationTest` (+3 others) |
| A concurrency race | **No** | `verify:concurrency` (4/4, real transactions) |
| A broken/unregistered API route | **No** | `ApiContractTest` |
| A broken Blade page | **No** | `WorkspacePageRenderTest` |
| A broken React mount | **No** | `test:frontend` |
| A silently dropped database invariant | **No** | `verify:invariants` |
| Reverting to the slow test strategy | **No** | `TestStrategyLockTest` |
| Runtime version drift | **No** | `verify:environment` |
| Terminology drift | **Advisory only** | `terminology-audit.php` reports, does not fail |

Known gap: the terminology audit is advisory by design and exits 0. Making it
blocking requires triaging its 67 pre-existing findings first.

---

## 11. Legacy Retirement Policy

Two suites currently exist. That is a transitional state, not the target.

**Current position (measured):**

| | Tests | Failing | Classes |
|---|---|---|---|
| Canonical | 24 | **0** | 6 |
| Whole repository | 895 | 406 | 142 (51 fully green) |

The 406 legacy failures are concentrated in fixture chains (offering, branch
and teacher provenance). Each is either an incomplete fixture or the
database/domain correctly refusing invalid state. **None is an unrepaired
production defect.**

### Retirement is earned, not scheduled

A legacy test may only be deleted once its *intent* exists in the canonical
suite. Classify before touching anything:

| Class | Meaning | Action |
|---|---|---|
| `RETAIN` | Valid intent, no canonical equivalent yet | Keep. Write the canonical test first. |
| `REWRITE` | Valid intent, invalid fixture or obsolete expectation | Rewrite into `tests/Canonical`. |
| `MIGRATED` | Intent now covered canonically | Safe to delete. |
| `OBSOLETE` | Tests removed architecture | Delete, cite what was removed. |
| `DUPLICATE` | Same intent as another test | Delete the weaker one. |
| `INVALID FIXTURE` | Asserts state production cannot produce | Repair the fixture, never the invariant. |

**Deleting failing tests to lower the number is prohibited.** It converts a
noisy suite into a quiet one that proves less, which is the opposite of the
goal. The 406 failures are informative: they mark exactly where fixtures do not
yet match the current domain.

### Order of work

1. Grow canonical coverage for a domain.
2. Port the legacy intent for that domain.
3. Delete the superseded legacy class in the same commit as its replacement.
4. Retire the fixture trait once nothing references it.

Until a domain has been through that, its legacy tests stay.
