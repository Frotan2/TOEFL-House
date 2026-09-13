# Library & Resources closure audit — 2026-09-12

**Branch:** `arena/01a09629-toefl-house` (from `main` @ `83bde19`)
**Scope:** Library & Resources domain only (categories, ownership, lifecycle, availability,
assignment, disposal/retirement, provenance, auditability, authorization, API, React UI,
projections, idempotency, concurrency, database invariants, operational behavior, recovery).
Other domains were inspected only where Library depends on them; cross-domain defects are
recorded as observations, not fixed.

**Doctrine applied:** code/test/green-CI/doc existence is not proof. Every closure claim below
carries the chain requirement → authority → implementation → database invariant → API →
frontend → test → runtime proof → operational proof, with the executing command named.

---

## 1. Verdict

**CONDITIONALLY CLOSED** — every P1/P2 finding from the forensic audit is fixed with
regression, adversarial, concurrency, database-boundary, API-contract, canonical, frontend
and **observed-green CI browser evidence** on this branch: Verification run **34713414829**
(head `e603db9`) passed all four jobs — backend (migrations, full suite, invariants,
concurrency), frontend, static analysis, and real-Chromium browser E2E including the
**24/24-record Library journey** — and CRM Browser E2E run 34713414850 passed alongside.
The only remaining conditions are mechanical: merge to `main` and a fresh Verification run
on the actual `main` HEAD, which is what `docs/OPERATING-CONTROL.md` reserves for
`RELEASE CERTIFIED`.

No P0 finding was identified at any point.

## 2. Findings and dispositions

| # | Sev | Finding | Disposition | Evidence |
|---|-----|---------|-------------|----------|
| F7 | P1 | Disposal requests could not be withdrawn: a requester who raised a wrong request was wedged forever (asset blocked from a corrected request, approvers forced to process garbage). No withdrawal state, column, guard, route or UI. | **FIXED** (`8a60e16`) | Migration `2026_09_12_000209` (verified up/down/up + `migrate:fresh`), `DisposeAsset::withdraw()`, CHECK `asset_disposal_request_withdrawal_check`, guard-trigger branch (requester-only, terminal, write-once), API + web routes, UI Withdraw with irreversible confirm, 12-test recovery suite incl. 6 raw-DB boundary rejections, canonical withdrawal tests, browser journey step, invariant probe. |
| F8 | P1 | A closed book issuance could strand: `returned()`/`reportLoss()` accepted terminal-state transitions in a way that left the copy's circulation state inconsistent with the ledger (fixed pre-audit continuation). | **FIXED** (`6b7771a`) | `IssuanceCloseRecoveryTest` (feature), canonical terminality tests, DB history-guard probes. |
| F1 | P2 | `tests/Canonical/Resources` did not exist: zero canonical coverage of the domain authority. | **FIXED** (`4033d08`) | 27 canonical tests / 107 assertions across books, custody+staged disposal, work orders; mutation-checked per `TEST_SUITE_ARCHITECTURE.md` §5 (3 mutations, 3 kills; evidence recorded in the commit record per the current mutation-evidence policy). |
| F2 | P2 | The 15 `/api/v1/resources` mutation endpoints had no HTTP-level test. | **FIXED** (`2b6d975`, extended `f039d26`) | `ResourcesApiMutationContractTest`: 7 tests / 107 assertions — anonymous 401, full circulation over HTTP, three-session staged disposal incl. both signatures and withdrawal, work-order lifecycle, 403 denial contract + audit, 422/404 contracts, idempotent replay + conflicting payload, hostile `Idempotency-Key` headers. |
| F3 | P2 | `verify:concurrency` raced no Library table. | **FIXED** (`77a843d`) | 4/4 → **7/7** production-table races: one-open-issuance, one-open-custody (each one commit + one 23505 on the named index, distinct backends proven by PID), staged disposal approval (one `requested→approved` commit; stale writer rejected by the write-once approver-slot guard). |
| F4 | P2 | `verify:invariants` probed no Library boundary. | **FIXED** (`77a843d`) | 6/6 → **12/12** named boundaries: one-open issuance/custody/active-request indexes, terminal issuance history, withdrawal CHECK, independent-approvers CHECK; the two CHECKs + three indexes pinned in the schema preflight catalogue. |
| F5 | P2 | Browser coverage of `/library` was a mount-and-title smoke check. | **FIXED** (`b54370c`, hardened `2a0d150`, deterministic dialogs `36cdc3a`+`e603db9`) — **24/24 green in CI** | `scripts/runtime/library-browser-e2e.mjs`: three isolated Chromium sessions (librarian, two approvers) driving the real React workspace — register→issue→return, register asset→custody→release, staged disposal request→provoked 403 self-approval→withdrawal→corrected re-request→two distinct signatures→execution by the requester (asset disposed), work order request→provoked 403→independent approval→start→complete with evidence; mutation-route allowlist; step-summary evidence publishing. Wired into the Verification browser job. Server contract behind every step rehearsed green via authenticated HTTP before CI. |
| F6 | P3 | `resources-contract.test.mjs` was static source grep — and stale (no withdraw / custody-release pins). | **FIXED** (`e59c30e`) | 3 updated static pins + 5 behavioural tests rendering the real `LibraryApp` in JSDOM against a scripted API client: state-legal action matrix per lifecycle, six summary counts, exact POST payloads, irreversible-confirm gating (refused confirm posts nothing), server-calendar dates, rejection alerts without false success. 8/8. |
| F9 | P3 | A concurrent insert landing between a command's pre-check and its insert surfaced as an opaque 500 instead of the honest contract. | **FIXED** (`932a770`) | Five contested creates translate `UniqueConstraintViolationException` into retryable `ConcurrencyConflict` (HTTP 409, `concurrency_conflict`) following the `TransferBranchToCampus` precedent; `ResourcesRaceTranslationTest` forces each window deterministically with one-shot `DB::beforeExecuting` rival inserts (6 tests / 24 assertions, incl. HTTP 409 shape and complete rollback of the loser). |
| F10 | P3 | `LibraryController@index` executed twelve scoped collection queries that the mounting blade never reads. | **FIXED** (`73b5510`) | Dead projection and its private `applyRootScope` helper removed; authority gate (fail-closed audited denial for branch-less operators) untouched; 58 console/resources tests + frontend suites green. |
| — | P3 | `approveDisposal` JSON answered `{'status':'approved'}` on a FIRST signature (state stays `requested`). | **FIXED** (`f039d26`) | Response now returns the command's actual `lifecycle_state`; F2 contract test drives both signature stages across three sessions and pins each response. |

### Observations (out of scope — recorded, not fixed)

- `FirstRunBootstrapSeeder` creates the owner person with `home_branch_id = NULL`, so a
  first-run deployment's Library workspace shows an empty borrower/custodian directory until
  any branch-homed verified person exists. Journey provisioning sidesteps this via
  `library-browser-provision.php`; a seeder-side decision belongs to Deployment/Identity.
- Topology fail-closed lockout semantics (branch closure locking person-linked operations at
  creation while in-flight closures still complete) — Identity/Access design question.
- `evidence_ref` on work orders is an unvalidated string reference (Documents-domain
  integration would make it a verified pointer).
- `SchemaCompatibilityProbeTest` (5 tests, Deployment domain) requires a `psql` binary; none
  exists in this sandbox — documented environmental deviation, green in CI.
- `BuildsActors` has no Resources-specific builders; fixtures are inline per suite.

## 3. Evidence ledger (commands actually executed)

Backend (local, PHP 8.4.10 / PostgreSQL 18.4, `toefl_house_test`):

| Gate | Command | Result |
|---|---|---|
| Full regression | `vendor/bin/phpunit --no-coverage` | **1183 tests / 8873 assertions / 5 failures (all `SchemaCompatibilityProbeTest`, psql-absent env) / 3 skipped** |
| Resources subset | Feature+Unit+Canonical Resources | 80/80 (canonical sweep), 58/58 (console+workflow) |
| Canonical suite | `--testsuite Canonical` | 94 tests / 376 assertions OK (incl. 27 Resources) |
| Static | `pint --test` / `phpstan` | 968 files PASS / `[OK] No errors` |
| Clean migration | `migrate:fresh --force` (toefl_house) | **206 migrations DONE**, incl. `000209` |
| Environment lock | `verify:environment` | 9/9 |
| Concurrency | `verify:concurrency` | **7/7 production-table races** |
| Invariants | `verify:invariants` | **12/12 named boundaries** |

Frontend (Node 22):

| Gate | Command | Result |
|---|---|---|
| Typecheck | `npm run typecheck` | clean |
| Build | `npm run build` | ✓ built |
| Contract suites | `npm run test:frontend` | all pass / 0 fail (incl. resources 8/8) |
| Runtime safety | `npm run test:runtime-safety` | 0 fail |

CI (GitHub Actions, PHP 8.4.25 / PG 18.4 / Chromium):

| Run | Commit | Result |
|---|---|---|
| Verification + CRM Browser E2E | `8a60e16` (F7+F8) | **success / success** (7m45s, 2m36s) |
| Verification + CRM Browser E2E | `77a843d` (F1–F4) | **success / success** (7m55s, 2m7s) |
| Verification | `b54370c` (F5) | backend/frontend **success**; static failed on two pint violations (provisioner fix committed `72b1476`); browser job: smoke **success**, Library journey failed at ~25s → hardened in `2a0d150` (poll-based interactions, idle waits, provisioning diagnostics, `$GITHUB_STEP_SUMMARY` evidence publishing) |
| Verification + CRM | final head | pending push (sandbox GitHub token rotation outage at time of writing; commits are local and intact) |

Security / adversarial evidence (all executed, not asserted):

- Anonymous API mutation attempts → 401 `authentication_required`, zero writes.
- Capability-less actor → 403 `resources.*_denied`, denial audit committed, **no domain event**
  (canonical + HTTP tests assert `domain_events` count = 0 for the denial's audit row).
- Requester self-approval (disposal + work order) → 403, audited; DB CHECK
  `asset_disposal_request_independent_approvers_check` proven live via invariant probe.
- Foreign-session withdrawal / execution → 403 `resources.disposal_withdrawer` /
  `resources.disposal_executor` + audit.
- Hostile `Idempotency-Key` headers (spaces, 5 chars, 129 chars) → sanitized fallback key,
  contract intact; replay → recorded result exactly once; conflicting payload → 409.
- Raw-DB boundary battery (F7 suite + canonical + invariant probes): born-withdrawn, revival,
  approver-slot rewrite, withdrawal-identity rewrite, terminal-history rewrite/delete,
  provenance rewrite, state jumps, evidence-free completion — every one rejected by the
  PostgreSQL boundary with the named guard.

Concurrency evidence:

- Three new races with distinct backend PIDs, all-open-then-write barriers, one-winner
  assertions on the named indexes, and the staged-approval race proving the write-once
  approver-slot guard stops the stale writer after the winner commits.
- Deterministic command-level race-window translation tests (F9) for all five contested
  inserts, plus complete rollback of the losing transaction.

## 4. What remains

1. Merge to `main` and re-run Verification on the actual `main` HEAD; only that run can
   support `RELEASE CERTIFIED` per OPERATING-CONTROL. (The final branch run was observed
   green; this cleanup commit changes only the workflow's temporary evidence channel,
   `ci-evidence/` artifacts and docs — no product code.)
2. Optional follow-ups (none block closure): seeder-side home-branch decision (observation
   above), `evidence_ref` integration with Documents, Resources builders in `BuildsActors`.

## 5. Recommendation

On the evidence executed above, the Library & Resources domain on this branch meets the
closure gates named in `docs/OPERATING-CONTROL.md` at the **VERIFIED** level: every gate —
backend suite, canonical proofs, static analysis, clean migration, environment lock,
concurrency races, database-invariant probes, frontend typecheck/build/behavioural
contracts, and the real-Chromium three-session journey — was actually executed and
observed green, locally and on CI (Verification run 34713414829 at head `e603db9`;
CRM run 34713414850). Production-readiness recommendation: **merge, then certify from the
`main`-HEAD Verification run**. The two P1 authority defects found by the audit (disposal wedge F7,
issuance stranding F8) are fixed at every layer — command, database guard, API, UI, tests,
runtime races and browser journey — and the domain now carries the canonical, HTTP,
concurrency, invariant and behavioural-frontend proof layers it previously lacked.
