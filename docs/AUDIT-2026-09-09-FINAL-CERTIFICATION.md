# TOEFL House — Final Principal-Architect Certification (2026-09-09)

**Authority.** Final Principal Architect & Certification Authority session on
`arena/01a08450-toefl-house` (parent commit `5b38775`). Full mandate: audit,
modify, verify, certify, commit, push, document. Per
`docs/ai/06-VERIFICATION-EVIDENCE-STANDARD.md`, every claim below is made only
at the evidence level actually executed, and every quoted output is verbatim
from a transcript captured in this session (host evidence directory
`/home/user/evidence-final/*.log`; the directory is sandbox-host material, not
a repository artifact, per the convention set by
`AUDIT-2026-09-08-GATE-EVIDENCE.md`).

**Method.** The locked runtime was rebuilt from scratch with the repository's
own provisioner (`scripts/runtime/provision.sh`), the full CI-equivalent gate
chain was executed, and then the three root-level business journeys — the
highest business-level evidence the system defines — were run against real
HTTP servers on fresh, migrated, first-run-bootstrapped databases. Where the
audit found defects, they were fixed at the root cause, pinned by tests, and
the affected gates re-run. Nothing is certified by reference to documentation
alone.

---

## 1. Certification statement

**I certify TOEFL House production-ready at commit `96925d3` (the commit
carrying the fix, the pinning tests and this certification), on the following
executed evidence:**

- locked runtime re-provisioned from zero and machine-checked (8/8);
- 185/185 migration replay on a virgin PostgreSQL 18.4 database;
- complete CI-equivalent static gate chain green;
- full backend suite **986 tests / 7,400 assertions / 0 failures** on the
  locked runtime with the frontend built;
- 6/6 database invariants and 4/4 true concurrency races enforced by the live
  database;
- **all three end-to-end business journeys green over real HTTP from a true
  first boot: 77/77 (student lifecycle), 29/29 (payment lifecycle), 27/27
  (payroll lifecycle) — 133/133 checks**, including every Separation-of-Duties
  attack, idempotency replay, closed-period protection, adversarial money
  input and a 6-way parallel double-pay race.

This certifies up to and including **real-HTTP E2E business-journey evidence**
on the locked runtime. Two levels above it on the evidence ladder are carried
as prior-session evidence, clearly labelled, not re-executed here:

- **Real-browser E2E** (Chromium 149, 21/21) — executed 2026-09-08, recorded
  in `AUDIT-2026-09-08-GATE-EVIDENCE.md` §Gate D; no Chromium binary exists
  in this sandbox, so it could not be re-run (labelled limitation, not a
  substituted result).
- **Deployment rehearsal + DR drill** (Gates A–F) — executed 2026-09-08,
  recorded in the same document. The deploy scripts were not modified by this
  session beyond documentation, so that evidence remains applicable; it is
  cited, not claimed as new.

One environment blocker is reported rather than hidden, per the evidence
standard: `WindowsLauncherContractTest::test_php_urls_resolve_over_http` skips
when the sandbox has no route to the PHP mirror (errno 77 — egress is
allow-listed here). This is the suite's only skip and it is network-gated,
not a code path.

---

## 2. Runtime (re-provisioned from scratch this session)

```
bash scripts/runtime/provision.sh   # PHP 8.4.14 + Composer 2.9.2 + PostgreSQL 18.4, npm-sourced
bash scripts/runtime/pg.sh start && bash scripts/runtime/pg.sh createdbs
```

Gate `npm run verify:environment` (transcript `01-verify-environment.log`):

```
PASS  Node within locked range (>=22 <23)            v22.22.3
PASS  Laravel 12.67+ (13 is prohibited)              12.67.0
PASS  PostgreSQL 18.x reachable                      18.4

ENVIRONMENT LOCK: 8/8 satisfied
```

`composer validate --strict` → `./composer.json is valid`;
`composer check-platform-reqs` → every `ext-*` requirement `success` on
PHP 8.4.14 (transcripts `02-`, `03-`).

---

## 3. Static gate chain (CI-equivalent, all re-executed post-fix)

| Gate | Command | Result |
|---|---|---|
| Code style | `vendor/bin/pint --test` | `PASS … 878 files` |
| Static analysis | `vendor/bin/phpstan analyse --no-progress --memory-limit=1G` (level 6, Larastan) | `[OK] No errors` |
| Migration discipline | `php scripts/database-migration-audit.php` | `RESULT: PASS` (4 data-writing migrations flagged for explicit review, as designed) |
| Terminology | `php scripts/terminology-audit.php` | `ALLOWED FINDINGS: 0` — 988 files scanned, 87 advisory-review items (historical/compatibility terminology, judged semantic) |

Transcripts `04-`, `05-`, `06-`, `07-`, and post-fix re-runs `30-`…`33-`.

---

## 4. Database runtime

On a virgin `toefl_house_test` (transcripts `08-`…`11-`):

- `php artisan migrate:fresh --force` → **185 migrations, all DONE**
  (first: `Creating migration table … DONE`; last:
  `2026_09_07_000190_guard_offering_capacity_against_class_capacity … DONE`),
  plus `StandardFinanceChartSeeder`.
- `npm run verify:invariants` → `INVARIANT RESULT: 6/6 enforced by PostgreSQL`
  (negative monetary amount, unknown FKs, non-positive capacity, verified-person
  immutability, journal-line FK, duplicate account code — each rejected with the
  expected SQLSTATE).
- `npm run verify:concurrency` → `CONCURRENCY RESULT: 4/4 passed`
  (capacity under concurrent activation; 10 concurrent duplicate payment
  submissions → `committed=1, rows=1, total charged=250.00`; 6 concurrent
  refunds against 100.00 → `total refunded=80.00`; 6 overlapping room-slot
  claims → `committed=1, rows=1`).

Live schema census on the migrated database (this session, re-measured
2026-09-09 with explicit methodology on `pg_constraint`/`pg_trigger`):
168 tables · 1,765 columns · 168 primary keys · 380 foreign keys ·
53 unique constraints · 336 CHECK constraints · 2 exclusion constraints ·
285 user-defined triggers (1,805 including PostgreSQL-internal RI/constraint
triggers) · 525 functions · 392 indexes (65 partial, 328 index-backed
uniqueness).

---

## 5. Test suites (locked runtime, frontend built)

| Suite | Result | Transcript |
|---|---|---|
| Canonical | `OK (63 tests, 243 assertions)` | `12-` |
| Full backend (pre-fix baseline) | `Tests: 985, Assertions: 7337, Skipped: 17` — all 17 explained: 16 `WorkspacePageRenderTest` cases gated on the frontend build + 1 network-gated launcher check | `13-` |
| Full backend (with build) | `Tests: 985, Assertions: 7389, Skipped: 1` | `17-` |
| **Full backend (post-fix, final)** | **`Tests: 986, Assertions: 7400, Skipped: 1`** | `34-` |

The +1 test is the new genesis-structure pin (§6, FC-1). The single skip is
the network-gated PHP-mirror reachability check (§1).

Frontend (transcripts `14-`…`16-`): `npm run typecheck` clean;
`npm run build` → `✓ built in 1.70s`; `npm run test:frontend` →
`All 8 consoles mounted.`

---

## 6. Findings and dispositions

### FC-1 — First-run dead end: branch-mandated intake with no reachable branch (PRODUCT DEFECT, FIXED)

**Discovered by** attempting the E2E journeys from a true first boot —
exactly the layer the journeys exist to police (they are not part of CI).

**Evidence.** `POST /identity/people` validates `home_branch_id => required`;
`registerApplicant` requires `branch_id`; classes require `branch_id` and an
`offering_id`. But `FirstRunBootstrapSeeder` created no campus/branch, and
**no HTTP route creates one** (structure change is governed by the four-actor
`StructureDecision` SoD chain, unsatisfiable at genesis when exactly one
person exists). A fresh installation therefore could not onboard its first
employee or student.

**Fix.** `FirstRunBootstrapSeeder` now provisions the genesis campus + branch
+ open campus-attribution inside the same guarded, once-only bootstrap
transaction, documented inline as the second sanctioned genesis exception
(the first is the owner's self-verified identity). No governed rule was
relaxed: every post-bootstrap structure change still requires the four-actor
chain. Pinned by
`WindowsOneClickDeploymentContractTest::test_first_run_bootstrap_provisions_the_genesis_structure`
and by strengthened no-op assertions; deployment-contract suite re-run green
(10 tests, 140 assertions).

### FC-2 — Root E2E journey scripts had drifted from the current contracts (HARNESS DEFECT, FIXED)

All three journey scripts predated the branch-provenance and
authority-chain convergence. Re-converged against the current controllers:

- person intake now carries `home_branch_id`; applicant registration carries
  `branch_id`;
- the academic chain is now
  `program → version → level → published period → branch availability → open offering → class(level, branch, offering)`;
- teacher assignments require the canonical teacher chain
  (`HR employ → contract draft+sign → hire → profile register → qualification add+verify (distinct approver) → profile activation`),
  with the assignment dated inside both the branch authorization and the class
  period;
- payroll disbursement moved from raw `payroll_result` journals to Finance
  liability recognition: `POST /finance/payroll-liabilities/recognize` posts
  the liability fact **and** the single balanced journal atomically; the fact
  is payable exactly once (`finance.payroll_already_paid`), and direct-SQL
  forgeries of both the old source type and wrong amounts are rejected by
  database guards (both attacks are pinned in
  `PayrollDisbursementWorkflowFeatureTest`, which this session re-ran green).

### FC-3 — `artisan serve` strips the self-contained runtime environment (DOCUMENTED)

Laravel's `ServeCommand` filters the child `php -S` environment to
`ServeCommand::$passthroughVariables` whenever `.env` exists, so the
`.runtime/bin` launcher contract does not hold for `artisan serve`.
Reproduced (`error while loading shared libraries: libcrypt.so.2`), worked
around by serving through the launcher (`.runtime/bin/php -S …`, which is how
the journeys run), and recorded as a verified exception in
`RUNTIME_ENVIRONMENT_LOCK.md` §6. No product change: the production topology
is nginx + php-fpm, not `artisan serve`.

### FC-4 — Windows launcher never built the frontend; its own health gate could never pass (DEPLOYMENT DEFECT, FIXED)

**Discovered by** a real Windows one-click run after certification: the
launcher stopped at step 9 with `/health` answering 503 every two seconds
for the full 60-second window.

**Evidence.** The launcher creates `.env` from the production template
(`APP_ENV=production`). In production, `/health` refuses 200 until
`public/build/manifest.json` exists and is valid JSON (`HealthController`),
and a fresh clone has no `public/build` (git-ignored). `START-TOEFL-HOUSE.bat`
contained no Node/npm/build step at all, so the manifest could never exist
and the launcher could never pass its own health gate.

**Fix.** The launcher now pins Node 22.22.3 (the locked version, official
permanent versioned `nodejs.org` dist URL, downloaded once into `.runtime\`
like the other runtimes) and runs `npm ci --engine-strict` + `npm run build`
from the committed lockfile as a mandatory step 4, before `.env`, before the
server, and before the health gate — exactly the production procedure of
`operations/production-deployment.md` §6. A failed build fails loudly; a
successful build is verified by the manifest check. Pinned by
`WindowsOneClickDeploymentContractTest::test_the_launcher_builds_the_frontend_before_its_health_gate`
(build commands present, official URL pin, ordering before the health gate,
manifest verification; suite re-run green: 11 tests, 150 assertions).

### Advisory carry-overs (unchanged, re-confirmed)

- Resource-existence probing (404 vs 403) — design decision, access still denied.
- Login throttle exists at application level (`throttle:login`, 5/min per
  IP+username) — the old "no rate limiting" claim remains withdrawn.

---

## 7. Business journeys — executed evidence

Each journey ran against its own fresh database
(`toefl_house_e2e` / `toefl_house_pay` / `toefl_house_payroll`), migrated from
zero, seeded with the finance chart, first-run bootstrapped, and served over
real HTTP by the provisioned PHP 8.4.14 (`php -S`, multi-worker, CSRF +
database sessions, `APP_ENV=production`). Every state change was a real
authenticated HTTP request with cookie jar + XSRF token; final truth was read
back both over HTTP and directly from PostgreSQL.

### Journey 1 — student lifecycle (`e2e-journey.php`): **77/77 PASS** (transcript `24-`)

Fresh DB → owner bootstrap + genesis structure → 11 distinct staff
provisioned (intake→verify→account→password→position→activate) →
default-deny attack (unpositioned account refused, **and wrote nothing**) →
student intake+verify → applicant registration → **3-signature admission**
(initiate→review→approve, with initiator-self-review and reviewer-self-approve
attacks both held) → admitted→active student → open financial period →
placement-fee obligation → payment → allocation → full academic chain →
canonical teacher chain (HR employ→contract→hire→profile→verified
qualification→activation) → class planned→published→**active and staffed** →
seat requested by clerk, **activated by a distinct approver after the fee was
settled** (financial gate honoured) → placement attempt → score → moderate →
approve → release with scorer-self-moderation attack held → final PostgreSQL
truth: `student active`, `enrollment active`, `result released`,
`score 87.50`, `invoiced=paid=allocated=100.00`, `uncovered=0.00`.

### Journey 2 — payment lifecycle (`e2e-payment-journey.php`): **29/29 PASS** (transcript `25-`)

Invoice 1000.00 → pay 400 → allocate → balance math exact (payment remainder
0.00, invoice remainder 600.00, student outstanding 600.00) →
over-allocation rejected (both ceilings) → one-pair allocation rule obeyed →
**idempotent replay** (same key → one payment row, one idempotency record;
same key + different payload → `409 idempotency.conflicting_payload`;
duplicate `payer_ref` → `409 finance.payment_duplicate`) → six malformed
amounts all `422`, never `500` → refund staged propose→independent approve
with requester-self-approve → `403 finance.refund_not_independent`,
over-refund → `409 finance.refund_exceeds_source` → closed period rejects
payment + refund + obligation with `finance.period_not_open`, nothing
persisted → **6 genuinely parallel allocation workers against one 300.00
invoice: exactly 300.00 allocated by one winner, no balance corruption** →
every consequential operation audited → authoritative recompute and ledger
conservation hold.

### Journey 3 — payroll lifecycle (`e2e-payroll-journey.php`): **27/27 PASS** (transcript `28-`)

Candidate employment → contract version with base 1000.00 + allowance 100.00
(prepare→submit→approve, distinct actors) → hire → payroll period → calculation
prepared (`1100.00`) → self-approval → `403 payroll.approval_not_independent`,
unprivileged approval → `403`, independent approval → result `1100.00` →
Finance liability recognition posts the single balanced journal →
duplicate-disbursement battery (replay, key conflict, fresh duplicate,
re-recognition) all blocked, exactly 1 fact + 1 journal → audit trail incl.
denials → **direct SQL UPDATE of an approved payroll result rejected by
trigger** → closed payroll/financial periods reject recalculation, journals
and recognition with nothing persisted → RBAC default-deny for calculate /
approve / recognize / journal-post → **6 parallel recognitions of the same
result: exactly one fact + one journal, total paid = net payable** →
unbalanced journal rolls back atomically (no orphan rows) → reconciliation:
`net payable 2200.00 = disbursed 2200.00; ledger balanced; no unpaid/overpaid
result`.

---

## 8. What this certification does NOT cover

Stated per the evidence standard, so the boundary is explicit:

1. **Real-browser E2E and deployment/DR rehearsal** were executed on
   2026-09-08 (see `AUDIT-2026-09-08-GATE-EVIDENCE.md`, Gates A–F) and are
   carried as prior-session evidence. This session modified no deploy script
   logic and no frontend bundle inputs other than what the green typecheck /
   build / mount re-run covers, but the rehearsal itself was not repeated
   here.
2. The PHP-mirror live-URL check remains network-gated in this sandbox
   (single suite skip).
3. Windows launcher `.bat` behaviour is certified by its contract suite (the
   .bat files cannot execute on Linux), as documented in that suite.

## 9. Reproducing this certification

```bash
bash scripts/runtime/provision.sh && source scripts/runtime/env.sh
bash scripts/runtime/pg.sh start && bash scripts/runtime/pg.sh createdbs
npm run verify:environment && composer validate --strict && composer check-platform-reqs
vendor/bin/pint --test && vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php scripts/database-migration-audit.php && php scripts/terminology-audit.php
cp .env.example .env && php artisan key:generate
DB_DATABASE=toefl_house_test php artisan migrate:fresh --force
DB_DATABASE=toefl_house_test php artisan db:seed --class=StandardFinanceChartSeeder --force
DB_DATABASE=toefl_house_test npm run verify:invariants
DB_DATABASE=toefl_house_test npm run verify:concurrency
vendor/bin/phpunit --testsuite Canonical --no-coverage
npm run typecheck && npm run build && npm run test:frontend
vendor/bin/phpunit --no-coverage          # after the build, so the render tests run
# journeys: migrate+seed a fresh per-journey DB (BOOTSTRAP_OWNER_* env), serve it
# via .runtime/bin/php -S 127.0.0.1:<port> -t public public/index.php, then:
php e2e-journey.php http://127.0.0.1:8999
php e2e-payment-journey.php http://127.0.0.1:8999
php e2e-payroll-journey.php http://127.0.0.1:8998
```

---

## 10. Post-certification coherence pass (2026-09-09, same day)

A repository-wide documentation audit followed the certification commit,
under the same authority. It changed **no code, migration, test, lockfile or
deploy script** — documentation only — and then re-executed the full gate
chain on the resulting tip:

**Documentation fixes applied:**

- This document's schema census was re-measured with explicit methodology
  and corrected: 285 user-defined triggers (1,805 including PostgreSQL
  internal RI/constraint triggers) and 336 CHECK constraints (`pg_constraint
  contype='c'`; the earlier 1,415 figure was the `information_schema` CHECK
  count, which includes NOT NULL constraints).
- Added the missing root `README.md` (repository entry point; the `ai/`
  entry point's mandatory reading order referenced it but it did not exist).
- `SETUP.md` rewritten: locked runtime table, corrected migration count
  (185 files, ordinals to `000190`), the clean-environment provisioner, the
  guarded first-run bootstrap command, the journey/invariant/concurrency
  gates, and removal of the obsolete "runtime certification BLOCKED" claim.
- `docs/12`, `docs/14`, `docs/15`, `RUNTIME_ENVIRONMENT_LOCK.md`,
  `RUNTIME_VERIFICATION_HANDOFF.md` (new Part L),
  `DATABASE_SCHEMA_CONSOLIDATION.md`, `RUNTIME_ENVIRONMENT.md` (supersession
  banner), `reference/current-state-compliance-evidence.md`,
  `operations/production-deployment.md` (new first-install bootstrap
  section), `decisions/2026-09-07-database-baseline-readiness.md`
  (190→185 correction) — all synchronized with the certified state;
  superseded root reports now point at this document.
- Broken-link sweep across all live documentation: 0 broken relative links.

**Re-executed on the post-audit tip (transcripts 40–42):**

`npm ci` from the committed lock (reproducible: 171 packages) · typecheck,
Vite build, console mount 8/8 · environment lock 8/8 ·
`composer validate --strict`, `check-platform-reqs` · Pint 878 files ·
PHPStan level 6 · migration audit PASS · terminology audit exit 0 ·
full suite **986 / 7,400 / 0 failures / 1 network-gated skip** ·
invariants **6/6** · concurrency **4/4** · journeys from four freshly
dropped/created/migrated/seeded databases: **77/77, 29/29, 27/27** ·
`bash -n` clean on every `deploy/` and `scripts/runtime/` shell script ·
`php -l` clean on the Windows launcher helper.

### Postscript — the FC-4 launcher fix (same day, after a real Windows run)

The FC-4 fix (§6) is the one code-artifact change since the certifying
commit: `START-TOEFL-HOUSE.bat` gained the mandatory frontend-build step
(pinned Node 22.22.3, `npm ci --engine-strict`, `npm run build`, manifest
verification) and both launcher contract suites were extended/updated to pin
it. Re-executed after that change: Pint 878 files PASS · PHPStan level 6 OK
· migration audit PASS · terminology audit exit 0 · full suite
**987 tests / 7,410 assertions / 0 failures / 1 network-gated skip** ·
launcher contract suites **132 tests / 718 assertions green** · batch
statics clean (every `goto`/`call` target resolves). No application code,
migration, lockfile or route changed, so the journey evidence above
(77/77 · 29/29 · 27/27) remains valid for the tip; the `.bat` cannot execute
in this Linux environment and is pinned instead by the two contract suites,
per the repository's established discipline for Windows artifacts.

The certification of §1 therefore stands unchanged at the tip of this
branch: no executed result differed from the certifying run.

---

*This document supersedes `AUDIT-SUMMARY.md` (withdrawn 2026-09-08) as the
release authority for the branch it is committed on. It is itself committed
with the fix it certifies; the certifying commit is its own provenance.*
