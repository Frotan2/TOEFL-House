# TOEFL House — Testing Strategy Lock

**This is the canonical and mandatory testing strategy.**

It is enforced by `tests/Unit/Architecture/TestStrategyLockTest.php`, which
fails the suite if the repository drifts back to the slow strategy. This
document explains *why*; the test is what actually holds the line.

---

## 1. The Rule

`tests/TestCase.php` — the single base class every test extends — uses
**`RefreshDatabase`**.

```php
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;
}
```

**`DatabaseMigrations` must not be reintroduced.**

### Why this matters

`DatabaseMigrations` replays the entire migration chain **before every single
test**. With 185 migrations and 867 tests that is not a small tax — it made the
suite effectively unrunnable, which in turn hid real defects because nobody
could get a full result.

`RefreshDatabase` gives each test the *same* pristine database: it migrates
once per test process, then wraps each test in a transaction that is rolled
back on teardown.

### Measured impact

Measured on `tests/Feature/Api` (27 tests) against PostgreSQL 18.4:

| Strategy | Wall clock | Result |
|---|---|---|
| `DatabaseMigrations` | **62.9s** | 27 tests, 23 errors, 4 failures |
| `RefreshDatabase` | **3.0s** | 27 tests, 19 errors, 4 failures |

**~21x faster.** The four fewer errors were migration-replay artifacts, not
application behaviour. The full suite went from unrunnable to **~37s**.

### Isolation is proven, not assumed

- Running the same suite twice produces byte-identical counts.
- After a run, `organizations`, `people`, `students`, `payments` and
  `enrollments` all contain **0 rows**.
- PostgreSQL has **transactional DDL**, so tests that issue statements such as
  `ALTER TABLE ... DISABLE TRIGGER` are still rolled back correctly. This is a
  specific reason the strategy is safe here and would not be on MySQL.

---

## 2. Test Database Lifecycle

| Concern | Rule |
|---|---|
| Database | `toefl_house_test`, from `phpunit.xml`. Disposable. |
| Port | `phpunit.xml` pins 5432. Override with `DB_PORT`, never by editing the committed file. |
| Creation | Created once; `RefreshDatabase` migrates it on first use per process. |
| Cleanup | Automatic transaction rollback per test. No manual truncation. |
| Corruption recovery | Drop and recreate the database, then re-run. |

### Orphaned processes

A killed or timed-out PHPUnit process can leave a backend mid-migration and
corrupt the test database. Symptoms are nonsensical and *non-deterministic*:
`relation "migrations" does not exist`, `duplicate key ... pg_class_relname`.

This was observed and diagnosed during runtime verification. If results look
impossible, check first:

```bash
ps aux | grep [p]hpunit                     # kill stragglers
psql -c "select pid, state, query from pg_stat_activity where datname like 'toefl%'"
```

Then recreate `toefl_house_test`. Do not chase the symptom.

---

## 3. Legitimate Exceptions

Transaction-wrapped isolation is correct for almost everything. Three cases are
genuinely different and **must be isolated outside the PHPUnit suite**:

| Case | Why it cannot use the default | Where it lives |
|---|---|---|
| **Concurrency** | Racing writers need *separate, concurrently committed* transactions. A single wrapping transaction makes a race impossible to express. | `scripts/runtime/concurrency-verification.mjs` (`npm run verify:concurrency`) |
| **Database invariants** | Asserting PostgreSQL rejects invalid states is clearer against the real migrated schema. | `scripts/runtime/database-invariants.mjs` (`npm run verify:invariants`) |
| **Browser E2E** | A browser needs committed state served over HTTP. | `scripts/runtime/browser-e2e.mjs` (`npm run verify:browser`) |

If you need committed state inside PHPUnit, prefer moving the check to one of
the above. Only if that is impossible, override the trait **on that single test
class** with a comment explaining why. Never change the base `TestCase`.

---

## 4. Suite Partitioning and Speed

| Suite | Command | Typical |
|---|---|---|
| Unit | `vendor/bin/phpunit --testsuite Unit` | seconds |
| Feature | `vendor/bin/phpunit --testsuite Feature` | tens of seconds |
| Everything | `vendor/bin/phpunit --no-coverage` | ~37s (867 tests) |
| One class | `vendor/bin/phpunit path/to/Test.php` | ~2-3s |
| One test | `vendor/bin/phpunit --filter test_name` | ~2-3s |

Guidance while debugging: run the narrowest scope that reproduces the failure.
Do not run the full suite in a loop, and do not wrap it in long blind timeouts —
if a command appears to hang, diagnose it (see §2) rather than waiting.

### Parallelisation

Not enabled. `RefreshDatabase` already removed the dominant cost, and parallel
workers would each need an isolated database. If it is ever added, every worker
must get its own database; sharing `toefl_house_test` across workers will
produce exactly the non-deterministic corruption described in §2.

### Determinism

Tests must not depend on execution order or on rows left by a previous test.
Order-dependence is a defect: the transaction rollback means nothing carries
over, so a test that only passes in sequence is asserting something untrue.

---

## 5. Fixture Rules

Fixtures caused the large majority of failures found during runtime
verification. The recurring mistake was creating *incomplete* domain state and
then blaming the domain for rejecting it.

1. **Build state through production commands**, not direct inserts, wherever a
   command exists. The commands are the authority.
2. **Provenance is mandatory.** Person-linked operations resolve scope through
   `Person.home_branch_id -> Branch -> active CampusAssignment -> Organization`.
   A fixture that stops at the organization is incomplete, and the domain will
   correctly refuse it. `SeedsAuthority::ensureBootstrapAuthority()` seeds the
   whole chain — use it.
3. **A verified person is immutable.** Set `home_branch_id` at insert time; a
   later `UPDATE` is rejected by `people_identity_guard`, and that trigger is
   correct.
4. **Academic structure has a required chain:** program -> published version ->
   level -> published period -> branch availability -> open offering -> class.
   Use `Tests\Concerns\BuildsAcademicStructure::buildAcademicChain()`.

### When a test fails against a database invariant

Classify before you change anything:

| Classification | Correct action |
|---|---|
| Fixture is incomplete | Fix the fixture. |
| Expectation is obsolete | Update the expectation, and say why in the commit. |
| Invariant is genuinely too broad | Change it at the database layer, with evidence. |
| Production behaviour is wrong | Fix production, add regression coverage. |

**Never weaken a correct invariant, delete a migration, or relax an assertion
to get a green run.** A green suite that proves nothing is worse than a red one
that tells the truth.

---

## 6. Enforcement

| Guard | What it prevents |
|---|---|
| `tests/Unit/Architecture/TestStrategyLockTest.php` | Reverting to `DatabaseMigrations`; adding a second base test case. |
| `npm run verify:environment` | Running the suite on a drifted runtime. |
| `tests/Feature/Http/WorkspacePageRenderTest.php` | Blade templates that compile in CI but 500 in a browser. |
| `tests/Frontend/mount.test.mjs` | Console bundles that build but crash on load. |

---

## 7. The Canonical Suite Is The Authority For New Work

`docs/TEST_SUITE_ARCHITECTURE.md` defines the layered test architecture and is
mandatory for new tests.

- New domain coverage belongs in `tests/Canonical` (PHPUnit testsuite
  `Canonical`), derived from the current implementation rather than from older
  tests' expectations.
- Legacy `tests/Feature` and `tests/Unit` remain runnable and are still CI
  gates. They are being converged, not trusted wholesale: a legacy expectation
  never overrides current implementation behaviour.
- Every canonical invariant test must be shown to fail when the production rule
  it protects is broken. Record the mutation result in the commit message.
