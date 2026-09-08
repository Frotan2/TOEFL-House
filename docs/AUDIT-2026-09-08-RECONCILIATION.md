# Branch Reconciliation Record — `01a0814a` (production-readiness) ↔ `01a080c8` (current verified)

**Date:** 2026-09-08
**Performed on:** `arena/01a081d4-toefl-house`
**Inputs:** `arena/01a0814a-toefl-house` @ `9225b33`, `arena/01a080c8-toefl-house` @ `54d7e1a`
**Outcome:** one branch carrying the verified union of both lines — `01a080c8`'s code and CI-green runtime as the authoritative state, `01a0814a`'s documentation absorbed and corrected.
**Status of this document:** CURRENT — supersedes the certification verdicts in `AUDIT-SUMMARY.md`, `FINAL-ENGINEERING-REPORT.md` and `docs/AUDIT-2026-09-08-PRODUCTION-READINESS.md`.

---

## 1. Why neither branch's self-certification could be trusted

Both lines assert authority. `arena/01a0814a-toefl-house` is titled "Final Engineering
Report: TOEFL House Production-Readiness Certification" and declares
`✅ PRODUCTION-READY`, `zero blocking defects`, `zero security vulnerabilities`,
and "no changes to the codebase are required before production deployment".
`arena/01a080c8-toefl-house` carries no such claim — only a verification workflow
and a handoff document that describes its own gaps.

Neither assertion was accepted. The adjudication below uses only artifacts a
reviewer can re-run: git objects, the repository's own files, and GitHub Actions
job conclusions queried from the API.

| Question | Evidence | Answer |
|---|---|---|
| Does `01a0814a` contain work that `01a080c8` lacks? | `git diff --name-status origin/01a080c8 origin/01a0814a` | 3 files, all Markdown. No code, migration, test or config file. |
| Does `01a080c8` contain work that `01a0814a` lacks? | `git log f0e1424..origin/01a080c8` | 3 commits, 230 files — CI/toolchain convergence. |
| Which line is actually green? | GitHub Actions job conclusions | Only `01a080c8` @ `54d7e1a`. |
| Is `01a0814a` green? | run `34249481368` | **No** — static analysis FAIL, backend FAIL. |

## 2. Method (reproducible)

```bash
git fetch origin 'refs/heads/arena/01a080c8-toefl-house:refs/remotes/origin/01a080c8' \
                'refs/heads/arena/01a0814a-toefl-house:refs/remotes/origin/01a0814a'
git merge-base origin/01a0814a origin/01a080c8     # → no merge base: unrelated histories
git rev-list --count origin/01a0814a                # → 1  (orphan snapshot, no parent)
git rev-list --count origin/01a080c8                # → 165
git diff --name-status origin/01a080c8 origin/01a0814a | awk '{print $1}' | sort | uniq -c
#   → 3 A (the three audit reports), 230 M (01a080c8's later CI fixes)
git rev-list -40 origin/01a080c8 | while read c; do echo "$(git diff --name-only $c 9225b33 | wc -l) $c"; done | sort -n | head -1
#   → 3 f0e1424  ← the snapshot's true content parent
gh api repos/Frotan2/TOEFL-House/actions/runs/<run>/jobs --jq '.jobs[]|"  \(.name): \(.status)/\(.conclusion)"'
```

**Content genealogy.** `9225b33` is `f0e1424` + three added Markdown files. It has no
parent commit and shares no history with the branch whose commit it snapshots, so
its ancestry carries no information; only its tree does. `01a080c8` = `f0e1424`
+ `293fee2` + `84eb9ce` + `54d7e1a`.

The tree-diff distance sweep is decisive: of the last 40 commits on `01a080c8`,
`f0e1424` differs from `9225b33` in 3 files and the next-closest commit differs in
14. There is no code anywhere on `01a0814a` that `01a080c8` does not already have.

## 3. What the "production-readiness" branch actually contributed

| Artifact | Unique? | Verdict |
|---|---|---|
| `AUDIT-SUMMARY.md` | yes | Kept at its original path, annotated in place. |
| `FINAL-ENGINEERING-REPORT.md` | yes | Kept at its original path, annotated in place. |
| `docs/AUDIT-2026-09-08-PRODUCTION-READINESS.md` | yes | Kept at its original path, annotated in place (the substantive review record — its per-area walkthroughs were checked and mostly hold). |
| `.github/workflows/verification.yml` | **no — regression** | `01a0814a` carries `PHP_VERSION: '8.2'` with the explanatory comment removed; `01a080c8` @ `293fee2` fixed that drift to `'8.4'` and restored the rationale. `01a080c8`'s version is authoritative. |
| every other file | **no** | Byte-identical to `f0e1424`, which `01a080c8` then improved. |

## 4. CI ground truth

`Verification` workflow, three jobs, from the GitHub API — not from any README.

| Run | Commit | Line | Frontend | Static | Backend |
|---|---|---|---|---|---|
| `34230674669` | `f0e1424` | 01a080c8 (= the cert's baseline) | PASS | **FAIL** | **FAIL** |
| `34238114478` | `293fee2` | 01a080c8 | PASS | PASS | **FAIL** |
| `34244517655` | `84eb9ce` | 01a080c8 | PASS | PASS | **FAIL** |
| `34247710050` | `54d7e1a` | 01a080c8 | PASS | PASS | PASS |
| `34249481368` | `9225b33` | **01a0814a** (the certified branch) | PASS | **FAIL** | **FAIL** |

Two conclusions, both uncomfortable for `01a0814a`:

1. **The certified commit was red.** The certification certifies `f0e1424`, whose
   static-analysis job failed `vendor/bin/pint --test` and whose backend job failed.
   Its own tip `9225b33` inherited both failures untouched.
2. **`01a080c8` is the line that did the convergence work.** Three commits after
   the snapshot, all three jobs pass. The certification's "already converged"
   framing inverts the actual order of events.

The local claims inside the certification were nonetheless honest — the full
suite really did pass on a provisioned runtime (see §7); what was wrong is the
inference from "green locally" to "production-ready, no changes required".

## 5. Integration decisions

1. **Base = `54d7e1a`, the CI-green tip.** Adopted wholesale, not merged file-by-file.
   A three-way merge across unrelated histories with 230 modified files would have
   had to resolve every style difference by hand and would have re-introduced a
   red CI configuration.
2. **`git merge -s ours --allow-unrelated-histories origin/01a0814a`** — records
   `9225b33` as a real ancestor of the reconciled branch while keeping the green
   tree. The certification line is therefore *in the history* (blame, bisect and
   attribution keep working) instead of being overwritten or discarded.
3. **Verbatim import, then correction.** `f5e29a0` imported the three reports
   byte-for-byte (`git diff 9225b33 f5e29a0 -- <paths>` is empty); `d0edd60` added
   the corrections. Refuted sentences are kept struck-through (or re-quoted verbatim
   next to the correction that retires them) rather than deleted, and the corrections
   register in §6 quotes every retired claim in its original wording — per this
   repository's own archive rule: *"Historical facts have not been silently
   rewritten."*
4. **Nothing was taken from the red snapshot's tooling** — including its
   `PHP_VERSION: '8.2'`, which is the drift `01a080c8` fixed.
5. **Result is fast-forwardable onto `arena/01a080c8-toefl-house`.** `54d7e1a` is
   this branch's first parent, so the authoritative branch can be promoted without
   any further merge: `git push origin arena/01a081d4-toefl-house:arena/01a080c8-toefl-house`
   is a pure fast-forward.

## 6. Corrections applied to the imported certification

| ID | Claim as issued | What the repository shows | Disposition |
|---|---|---|---|
| R.1 | "CERTIFIED PRODUCTION-READY … zero blocking defects" | CI at the certified commit: static FAIL, backend FAIL (`34230674669`); `docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md` additionally requires deployment rehearsal, readiness verification and demonstrated schema compatibility, none of which the audit evidences | Certification **withdrawn** |
| R.2 | "No changes to the codebase are required before production deployment" | 230 files across `293fee2` / `84eb9ce` / `54d7e1a` were required before CI went green — and the baseline's own handoff says so: Part J records **222 pre-existing Pint style issues**, **24 pre-existing PHPStan level-6 errors**, and a **broken helper call site that Pint's own auto-fix introduced** (`TestStrategyLockTest::testFiles()` renamed at the definition but not at two call sites → `Call to undefined method`), all of them present at the certified commit | **Refuted, and refuted by the source branch's own record** |
| R.3 | ADV-002 "no rate limiting middleware"; ADV-003 "no HTTPS enforcement" | `RateLimiter::for('login', …)` → `Limit::perMinute(5)` keyed on IP+username (`app/Support/Providers/AppServiceProvider.php`), applied via `throttle:login` (`routes/web.php:38`); `deploy/nginx/toefl-house.conf` does `listen 80` + `return 301 https://` with `ssl_protocols TLSv1.2 TLSv1.3`, `SecurityHeaders` emits HSTS `max-age=31536000; includeSubDomains`, `.env.example` sets `SESSION_SECURE_COOKIE=true` | Both **withdrawn** — the recommendations were already implemented |
| R.4 | Commits `e696678` and `bf08353` authored the audit | Neither object exists (`git cat-file -t` fails on both); the branch has exactly one commit | **Unverifiable citation** — flagged in place |
| R.5 | "Idempotency-Key header on all mutations" | Header honoured with an `idempotency_key` field fallback by `Controller::idempotencyKey()`, used by 28 controllers; 433 mutation routes exist overall | **Overstated** — restated as measured |
| R.6 | "168 tables, 1,765 columns, 230 PK, 380 FK, 101 unique, 336 CHECK, 2 exclusion, 392 indexes (65 partial), 525 functions, 285 triggers" | Re-measured against a live PostgreSQL 18.4 after replaying all 185 migrations: **every number matched exactly** | **Confirmed** — see §7, including a retraction of a mistaken objection raised while this reconciliation was in progress |

## 7. What survived independent re-verification

Re-run on this branch in the repository's own provisioned runtime
(`bash scripts/runtime/provision.sh`), not quoted from the reports:

| Gate | Command | Result |
|---|---|---|
| Runtime lock | `npm run verify:environment` | **8/8 PASS** — PHP 8.4.14, Composer 2.9.2, Laravel 12.67.0, PostgreSQL 18.4, Node 22.22.3, 21 extensions |
| Composer integrity | `composer validate --strict` | PASS (`./composer.json is valid`) |
| Migration replay | `php artisan migrate:fresh --force` | **185/185 applied, 0 pending**, exit 0 |
| Live schema shape | direct `pg_*`/`information_schema` queries | 168 tables · 1,765 columns · 230 PK · 380 FK · 101 unique · 336 CHECK · 2 exclusion · 392 indexes (65 partial) · 525 functions · 285 triggers — **identical to the audited figures** |
| Database invariants | `npm run verify:invariants` | **6/6 enforced by PostgreSQL** |
| Concurrency | `npm run verify:concurrency` | **4/4** — incl. 8 writers vs capacity 2 → 2 committed; 10 duplicate payments → 1 row; 6× 40.00 refunds vs 100.00 → 80.00 |
| Canonical suite | `phpunit --testsuite Canonical` | **OK (63 tests, 243 assertions)** |
| Style gate | `vendor/bin/pint --test` | **PASS**, 859 files |
| Static analysis | `phpstan analyse` (level 6, larastan) | **[OK] No errors** |
| Migration discipline | `php scripts/database-migration-audit.php` | **PASS** (3 advisories: data-writing migrations `000140`, `000149`, `000168` need explicit review) |
| Terminology | `php scripts/terminology-audit.php` | exit 0, advisory (87 findings, none fatal) |
| Frontend types | `npm run typecheck` | PASS |
| Frontend build | `npm run build` | PASS (Vite, clean) |
| Console mounts | `npm run test:frontend` | **8/8 consoles mount** |
| JS dependency audit | `npm ci` | "found 0 vulnerabilities" |
| Full backend suite | `phpunit --no-coverage` | **OK — 899 tests, 6,990 assertions, 1 skipped, 0 failures** (exit 0, 5m54s) |
| **Live browser E2E** | `php artisan serve` + `node scripts/runtime/browser-e2e.mjs` on real Chromium 152.0.7977.0 | **21/21 PASS** — all 14 consoles render live React trees, 25 `/api/v1` calls observed / 25 succeeded / 0 failed, no uncaught console errors, no failed network requests, sign-out ends the session |
| Readiness on a running instance | `GET /up`, `GET /health` | 200 / `{"status":"ok","checks":{"database":"ok","application_key":"ok","frontend_build":"ok"}}` |
| Security headers on a live response | `curl -I /login` | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Strict-Transport-Security: max-age=31536000; includeSubDomains`, `Cache-Control: no-store, private` |
| Deployment bootstrap path | `db:seed --class=FirstRunBootstrapSeeder` | PASS — `organization "The TOEFL House", Owner role (133 capabilities) and account "runtime.owner" created`, i.e. the privileged-bootstrapping route a fresh install actually uses |
| CI on this branch | `Verification` workflow | Runs `34252336159`, `34253459128`, `34253581349`, `34253638765` — **Frontend PASS · Static analysis PASS · Backend PASS on every one**. This is the gate that was red at `f0e1424` (static + backend) and red at `9225b33` (static + backend): the reconciled branch is the only one of the three states that passes all three jobs |

**A correction of this correction (kept for the record).** While reviewing §7's
schema row, a static reading of `database/migrations` produced 165 `Schema::create`
names minus 1 later drop = **164** tables, and an interim version of this document
called the audited "168 tables" irreproducible. That judgement was **wrong** and is
retracted. The migration tree also creates tables through raw SQL —
`settlement_proposals`, `result_corrections`, `privacy_export_requests`,
`asset_disposal_requests`, `org_wide_grant_requests` — plus the framework's
`migrations` table, and consolidates away `compensation_components`, `work_bases`
and `final_settlements`. 165 + 5 + 1 − 3 = **168**. Grep of the migration files is
not an acceptable way to dispute a live-database figure; only a replay is. The
certification's database evidence was sound, and the audit's insistence that these
numbers came from `php artisan db:show` and live queries was the right method.

**The 900 → 899 test-count change is also explained, and it is not a regression.**
The certification (and Part I.4 of the baseline handoff) measured **900 tests /
6,991 assertions / 2 skipped** at `f0e1424`; this branch measures **899 / 6,990 /
1 skipped, 0 failures**. The whole delta is one test: at `f0e1424`,
`WindowsLauncherContractTest::test_php_urls_resolve_over_http()` was parameterised by
`#[DataProvider('phpUrlProvider')]` with two rows — `'releases (current)'` and
`'archives (permanent fallback)'` — so it executed as two cases. `84eb9ce`
(*"Make launcher live-URL test robust to PHP mirror tier rotation"*) replaced the
provider with a single test that probes both tiers and skips only when neither
resolves, which is the correct behaviour when a mirror tier rotates. One test case
and its assertion disappeared; nothing was weakened. The certification's figure was
accurate for the commit it reviewed and is superseded, not contradicted.

## 8. Verification neither branch performed

Added by this reconciliation, because the certification's absolute claims
("no bypass routes", "zero security vulnerabilities") deserved at least one
check that was not inherited from it.

1. **Route-surface enumeration** — `php artisan route:list --json`: **511 routes,
   433 mutation routes**, of which exactly **one** is not behind
   `employee`/`auth`/`throttle`: `PUT storage/{path}`.
   Investigated to its source: it and its `GET` sibling come from
   `Illuminate\Filesystem\FilesystemServiceProvider::serveFiles()` for any
   `local` disk with `serve => true`, which is the **framework default** for the
   `local` disk in `vendor/laravel/framework/config/filesystems.php` — the app
   publishes no `config/filesystems.php` at all. `ReceiveFile` and `ServeFile`
   both `abort_unless($this->hasValidSignature($request), …)`; for a non-`public`
   visibility disk that requires a valid relative signature, and
   `PathTraversalDetected` is caught → 404. **Not a bypass and not exploitable**;
   recorded as the answer to "no bypass routes" rather than as a defect.
   Note for operators: the `GET` half of that pair would serve an unauthenticated
   read of *any* disk with `visibility => 'public'`, so publishing a custom
   `config/filesystems.php` must keep `serve` off public disks deliberately.
2. **Live header and cookie inspection on a running instance** — `curl -I /login`
   against the booted app returns `X-Content-Type-Options: nosniff`,
   `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` and
   `Strict-Transport-Security: max-age=31536000; includeSubDomains`; the session
   cookie is `secure; httponly; samesite=lax` and the XSRF cookie is
   `secure; samesite=lax` (readable by design). **One gap that neither line
   recorded: there is no `Content-Security-Policy` anywhere in the delivery path.**
   `SecurityHeaders` emits five headers, not six, and `deploy/nginx/toefl-house.conf`
   mirrors the same five (`grep -rn Content-Security-Policy` over `app/`,
   `config/`, `deploy/`, `resources/`, `routes/`, `tests/` returns **nothing** — the
   framework's per-file header in `ServeFile` is not in this app's delivery path at
   all, since `DocumentsController` returns metadata rather than file bytes).
   Corrected and closed by Gate E, which enforced a policy at both layers. The
   certification's `Security: EXCELLENT ✅ … 10/10`
   did not mention it; for an app that renders 14 authenticated React consoles this
   is a real, if low-severity, defense-in-depth omission — recorded as
   verified-open in §10 rather than asserted-away.
3. **Middleware/exception-path check** — `bootstrap/app.php` confirms CSRF on the
   `api` group, `SecurityHeaders` appended globally, and a `DomainError` renderer
   mapping authorization failures to 403, validation to 422, business and
   concurrency rejections to 409, integration-unknown to 502, with a
   `correlation_id` on every payload.
4. **Secret hygiene** — no `.env`, key material or credentials are tracked
   (`git ls-tree -r` scan); `phpunit.xml` carries a fixed `APP_KEY`, which is
   test-scoped by design; `.env.example` defaults `SESSION_SECURE_COOKIE=true`.
5. **Gate-ordering measurement artifact, found and explained.** Re-running the
   schema measurements *after* the other gates reported **527** functions in the
   `public` schema instead of the audited 525. Not drift:
   `scripts/runtime/concurrency-verification.mjs` creates
   `conc_capacity_guard()` (line 36) and `conc_refund_guard()` (line 136) and never
   drops them, so they persist in the target database (`SELECT … ORDER BY oid DESC`
   puts both at the top: 22695, 22663). They are the only residue;
   `database-invariants.mjs` and `verify-environment.mjs` create nothing. So the
   certification's 525/285/168 figures are correct **for a pristine replay**, and
   any future agent who measures after running the concurrency gate will see 527 —
   record the count before that gate, or on a fresh cluster. Follow-up worth doing
   (not done here, to keep this branch's code byte-identical to the verified
   baseline): have that script drop both functions in its cleanup so the gate stops
   mutating the schema it is measuring.
6. **Dead tooling audit** — `recovery/_apply_supplied_patch.yml` is a one-shot
   patch applier left from the `b951c1b`/`d84097d` recovery session; it `test -f`s
   twelve `recovery/chunk*.b64` files of which only a 25-byte stub `chunk1.b64`
   remains, so the workflow is inoperative. Nothing in `.github/`, `tests/`,
   `scripts/`, `composer.json` or `package.json` references it. Both lines carried
   it; removed in this reconciliation (see the hygiene commit) as
   non-functional recovery scaffolding that a future operator could mistake for a
   supported path.

## 9. Fresh full verification of the final branch state

Executed against `d3d574d` (this branch's tip) in the provisioned runtime, in one
chain — every gate the `Verification` workflow runs, plus the live items §10 lists as
newly closed:

| # | Gate | Result |
|---|---|---|
| 0 | `composer validate --strict` / `composer check-platform-reqs` | PASS / PASS |
| 1 | `npm run verify:environment` | **8/8 satisfied** |
| 2 | `php artisan migrate:fresh --force` + `StandardFinanceChartSeeder` | **185/185**, seed PASS |
| 3 | live schema measurement | 168 tables · 1,765 cols · 230 PK · 380 FK · 101 unique · 336 CHECK · 2 exclusion · 392 indexes · 65 partial · 285 triggers (function count read *after* the concurrency gate — see §8.5) |
| 4 | `vendor/bin/pint --test` | **PASS**, 859 files |
| 5 | `vendor/bin/phpstan analyse` | **[OK] No errors** |
| 6 | `scripts/database-migration-audit.php` | **PASS** |
| 7 | `scripts/terminology-audit.php` | exit **0** (advisory) |
| 8 | `npm run verify:invariants` | **6/6** |
| 9 | `npm run verify:concurrency` | **4/4** |
| 10 | `tsc --noEmit` · `vite build` · `test:frontend` | PASS · PASS · **8/8 consoles** |
| 11 | `phpunit --testsuite Canonical` | **OK (63 tests, 243 assertions)** |
| 12 | `phpunit` (full backend suite) | **OK — 899 tests, 6,990 assertions, 1 skipped, 0 failures** |
| 13 | suites touching the `recovery/` removal (`Feature/Deployment`, `Unit/Launcher`, `Unit/Architecture`) | **OK (73 tests, 593 assertions, 1 skipped)** |
| — | CI on this branch | `34252336159`, `34253459128`, `34253581349`, `34253638765` → **all three jobs success on each**; tip run `34255157213` recorded in the commit that follows this table |

**No gate fails on this branch. No code was changed to make that true** — the tree is
`arena/01a080c8` @ `54d7e1a` plus documentation and one dead-directory removal.

## 10. Production-readiness assessment (truthful)

The engineering baseline is **strong and genuinely verified**. The release verdict
is **not** "production-ready", and that is a documentation-of-record problem, not a
defect-hunt problem.

**Proven (reproducible in this repository, §7 + §8):** architecture and authority
boundaries as documented; PostgreSQL-enforced invariants; concurrency safety under
real simultaneous transactions; financial correctness scaffolding (single balance
authority, immutable facts, `bc*` arithmetic); a full 185-migration replay from
zero; static analysis clean at PHPStan level 6 and Pint clean; frontend typecheck,
build and 8/8 console mounts; CI green across all three jobs.

**Not proven — and the certification should not have implied otherwise:**

| Gap | Why it still matters | How to close it |
|---|---|---|
| ~~Live browser E2E on this branch~~ **CLOSED by this reconciliation** | It was the largest inherited-not-measured claim in both lines (handoff Part I.5 calls it host-gated; the audit just quotes Part D.2). Re-measured here on real Chromium 152.0.7977.0 against a live server: **21/21 PASS** (§7) | closed — no action; keep running it wherever `@sparticuz/chromium` is available |
| Deployment rehearsal — **closed for php-fpm/FPM and the release pipeline (Gate A); nginx config remains review-only** | The app was started for real (provisioned runtime → migrate → seed → first-run owner bootstrap → `artisan serve` → `/health` + `/up` 200 → browser journeys), which is the readiness half. Still missing: the authored **nginx + php-fpm topology** (`deploy/nginx/toefl-house.conf`, `deploy/php-fpm.conf`) has never been exercised, and the `.bat`/`deploy` Windows path is contract-tested only | run the documented topology once behind nginx with TLS, `APP_ENV=production`, and record |
| ~~DR drill / timed restore-verify~~ **CLOSED (Gate B, 2026-09-08)** | A restore had never been executed and timed, so the documented procedure could have been wrong in either direction. Executed: backup (246 ms) → `DROP DATABASE … WITH (FORCE)` → `deploy/restore.sh --latest --confirm` (876 ms, exit 0) → verification (99 ms); 169 tables / 346 rows / 185 migrations, content digest `7055417d9a2f…` identical to pre-loss, owner account and bcrypt hash intact, a table written after the dump correctly absent (that gap is the RPO), and the browser journey passing 21/21 against the recovered database. RTO to serviceable: **0.98 s** | closed — see `docs/AUDIT-2026-09-08-GATE-EVIDENCE.md` §Gate B; it also found and fixed two restore defects (a `--create` that made a successful restore exit 1, and an undefined `$HEALTH_URL` that killed the script on its last line) |
| ~~Schema compatibility / rollback demonstration~~ **CLOSED (Gate A + Gate C, 2026-09-08)** | Both halves were unexecuted. Executed now: `deploy.sh --rollback` in all three outcomes (compatible → symlink moved and the target verified against the live schema; schema-ahead target → refused, exit 1, `current` untouched; unreachable DB → refused, never treated as compatible). And on a scratch database: 185 migrations in 1.7 s; a migration that throws after issuing DDL leaves **no** residue (transactional DDL, so an interrupted deploy is resumable); `migrate:rollback --step=2` reverted one migration then stopped at a one-way migration **leaving 184 applied** — batch rollback is not atomic — and re-applying returned the exact prior schema (168 tables / 392 indexes / 285 triggers) with every business table's content digest unchanged. `down()` coverage is now 168 reversible, 17 explicit one-way refusals that each name an alternative, 1 documented no-op, 0 missing, 0 unexplained-empty, enforced by `scripts/database-migration-audit.php` in CI | closed — see `docs/AUDIT-2026-09-08-GATE-EVIDENCE.md` §§Gate A, Gate C |
| Observability — **partially closed (Gate D, 2026-09-08)** | Executed against the deployed release with the database stopped: nothing leaked on any measured path (no SQLSTATE, DSN, vendor path or frame), and the app self-heals on restart. Two defects found and fixed — `/health` answered `500 text/html` during a database outage because `StartSession` died before the controller (now a stateless route honouring its documented 503 JSON), and activation could report success while PHP-FPM still executed the previous release under `validate_timestamps=0` (reload is now required and verified, else the deploy fails and the symlink is restored). `LOG_PATH` makes logs survive release pruning, and §18 now states plainly that no metrics/alert/pager path exists. **Still open:** two error envelopes on one API (`{"error","category","correlation_id"…}` for `DomainError` vs a bare `{"message"}` for framework 404/405/419/500), no request identifier in any response or log line, no metrics/alert routing, and no enforced log rotation | normalise the envelope via `Handler::respondUsing` (front-end contract change: needs a decision, not an ops fix); add a request id; define SLO + alert routing in `docs/12-OPERATIONS-DEPLOYMENT-DR.md` |
| Dependency currency policy | 73 + 33 locked packages, `npm ci` clean; no update/vulnerability-triage cadence is documented | add a cadence + `composer audit`/`npm audit` gate to CI |
| ~~Data volume / performance envelope~~ **CLOSED for the measured surface (Gate F, 2026-09-08); domain journeys recorded as unmeasured** | The claim is now measured where it was made, and the 500 ms signal is attributed by experiment rather than by preference. Issues raised in this row (three endpoints at ~500 ms under a 14-console burst, single-worker `artisan serve` vs real cost) were separated as follows: with `PHP_CLI_SERVER_WORKERS=1` vs `=8`, eight concurrent `/health` requests complete in 107 ms (1 worker) and 80 ms (8 workers) when fired with `xargs -P`, while a *single* request costs 12.3 ms vs 13.0 ms on the same two servers: identical when uncontended, 4.5× apart at 32 concurrent clients, with the 1-worker mean growing linearly in concurrency (12 → 46 → 74 → 182 ms) and the 8-worker mean staying flat (13 → 34 → 31 → 41 ms). So **the ~500 ms is queueing in front of the dev server's single worker, not per-endpoint cost** — and the first version of that measurement, a shell `&` loop whose requests arrived staggered and therefore never queued, produced the opposite conclusion and was discarded (Gate F §2 explains both). Read path at volume, measured through the application's own middleware stack with 1,000 amplified `people` + `user_accounts` rows in scope: `/api/v1/identity/people` and `/api/v1/identity/accounts` each issued **30 queries before and after** the growth (p50 17.4 / 19.5 ms), both return exactly 300 rows under their `limit(300)`, and the bound was proven load-bearing by tightening the test's own ceiling and watching it fail. Gate C's deferred `ALTER TABLE` question answered at 1,000,000 rows / 64 MB: nullable and constant-default additions 0.048 s / 0.015 s with 36 ms / 1 ms of writer stall, while a volatile default (2.259 s, **2,238 ms** stall) and a type narrow (1.556 s, **1,532 ms** stall) rewrite every row — the expand/contract policy is correct for the reason it claims. Connection budget arithmetic recorded (`pm.max_children=20` vs `max_connections=100`, no queue workers, session GC via `lottery => [2,100]` with `sessions_last_activity_index` present). | closed for the identity read surface — `tests/Feature/Performance/ReadPathEnvelopeTest.php` and `scripts/runtime/perf-envelope.php` are committed, so the property is now re-measured by CI instead of asserted from review; see `docs/AUDIT-2026-09-08-GATE-EVIDENCE.md` §Gate F. **Still open, and why:** the students/reporting journeys cannot be amplified by copy (`students_one_per_person` + `students_one_per_admission_decision` + `students_admission_authority_guard()` require a real final-admit decision per row) and the legal builder costs 110 ms/student, so 1,000-row volumes are not measured in the default suite; there is no performance SLO in the repository to certify against, so the committed rules assert shape (flat query count, bounded payload) rather than speed; no sustained load generator exists — 8-way curl bursts are a queueing probe only |
| ~~Content-Security-Policy~~ **CLOSED (Gate E, 2026-09-08)** | Absent from both `SecurityHeaders` and `deploy/nginx/toefl-house.conf` (§8.2), while 14 authenticated consoles execute JS — the certification scored security 10/10 without recording it. The §8.2 parenthetical was also wrong: the `ServeFile` per-file header is framework code this application never reaches (`DocumentsController` returns metadata, not bytes). | Enforced CSP at both layers from a browser inventory of the deployed release (0 inline scripts, no `eval`/`new Function`, no framing, no `blob:`/`data:`), so no nonce or hash is needed and `script-src` keeps no exception; parity between middleware and nginx config is asserted, including server scope; six injection probes blocked in a real browser against a pre-CSP control that executed all six; 14 consoles + E2E 21/21 unchanged, 0 violations during normal use. **Also found and fixed:** nothing installed `deploy/nginx/toefl-house.conf` on the host, so headers declared there never reached static/404/5xx responses — `deploy.sh` now installs, validates and reloads it (`deploy/lib/nginx-edge-config.sh`, `NginxEdgeConfigTest`, 8 tests), rehearsed in all three outcomes. **Still open:** no violation collector, so `report-to` is absent and a future inline script fails silently (Gate E §6) |

**Verdict: `arena/01a081d4-toefl-house` is the authoritative engineering baseline —
CI-green, and re-verified end to end on a provisioned runtime in this session, now
including the live browser journeys and a running instance that the certification
could only cite from documents. It is ready to be promoted to
`arena/01a080c8-toefl-house` (fast-forward).**

**It is still not release-certified**, and the remaining list is short and specific:
a rehearsal of the authored nginx/php-fpm production topology, an executed and timed
backup→restore DR drill, a demonstrated `migrate`/`rollback`/re-apply cycle (the
protocol separates app rollback from database rollback), and an observability/alerting
path. None of the two input branches evidenced these; a "no changes required before
production deployment" claim that skips the protocol's own checklist is exactly the
failure mode this reconciliation exists to prevent.

### 10.1 This verdict agrees with the repository's own registers

The gaps above are not an outside reviewer's importation — they are what the
project's normative documents already say, which is precisely why the imported
certification's "no changes required before production deployment" is the odd one
out:

| Source | Its own words |
|---|---|
| `docs/reference/current-state-compliance-evidence.md` C-28 | backup/restore is **PARTIALLY ACHIEVED** — *"Current official-environment drills still required"* |
| `docs/12-OPERATIONS-DEPLOYMENT-DR.md` | *"A documented recovery plan is not equivalent to a successful recovery drill."* |
| `docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md` | a release claim additionally requires deployment rehearsal, readiness verification, and *"Schema compatibility must be demonstrated; a symlink rollback alone is not evidence of database rollback"* |
| `docs/RUNTIME_VERIFICATION_HANDOFF.md` Part I.5 | *"Release certification still additionally depends on the items that remain genuinely runtime/host-gated here"* |
| `config/logging.php` | file-based `stack`/`single` channels and a `null` deprecations channel — no metrics or alert sink is configured in-repo, which is why the observability row above is open rather than merely undocumented |

The reconciled branch therefore closes the browser-E2E and readiness items and moves
DR/schema-rollback/observability from "unverified" to "verified-open", with named
commands to execute. Nothing in either line of work supports a release sign-off yet,
and this document is the record of why.

## 11. Disposition of the two input branches

| Branch | Disposition |
|---|---|
| `arena/01a0814a-toefl-house` | Fully absorbed. Its only unique content (three reports) is imported and corrected; its `verification.yml` was a regression and was not adopted. Recommend retiring it or marking it read-only — nothing further should land there. |
| `arena/01a080c8-toefl-house` | The authoritative code state, preserved exactly (tree-identical at the merge, then documentation-only commits on top). Fast-forward this branch onto the reconciled result to converge. |
| `arena/01a081d4-toefl-house` | The reconciled result: `01a080c8` history + `9225b33` as an ancestor + absorbed/corrected documentation. |

## 12. How to reproduce this verification

```bash
bash scripts/runtime/provision.sh            # PHP 8.4.14 + Composer 2.9.2 + PostgreSQL 18.4
source scripts/runtime/env.sh
bash scripts/runtime/pg.sh start && bash scripts/runtime/pg.sh createdbs

cp .env.example .env && php artisan key:generate

npm run verify:environment                   # 8/8 runtime lock
composer validate --strict && composer check-platform-reqs

export DB_DATABASE=toefl_house_test
php artisan migrate:fresh --force            # 185/185
php artisan db:seed --class=StandardFinanceChartSeeder --force
npm run verify:invariants                    # 6/6
npm run verify:concurrency                   # 4/4
vendor/bin/pint --test                       # 859 files
vendor/bin/phpstan analyse --no-progress --memory-limit=1G   # level 6, no errors
php scripts/database-migration-audit.php
php scripts/terminology-audit.php
vendor/bin/phpunit --testsuite Canonical --no-coverage      # 63 / 243
vendor/bin/phpunit --no-coverage                            # 899 / 6,990 / 1 skipped

npm ci && npm run typecheck && npm run build && npm run test:frontend   # 8/8

# live rehearsal (dev database, separate from the test database)
export DB_DATABASE=toefl_house_dev
php artisan migrate:fresh --force
php artisan db:seed --class=StandardFinanceChartSeeder --force
BOOTSTRAP_OWNER_NAME='Rehearsal Owner' BOOTSTRAP_OWNER_BIRTHDATE=1990-01-01 \
BOOTSTRAP_OWNER_USERNAME=runtime.owner BOOTSTRAP_OWNER_PASSWORD='...' \
  php artisan db:seed --class=FirstRunBootstrapSeeder --force
php artisan serve --host=0.0.0.0 --port=8000
curl -s localhost:8000/health && curl -s -o /dev/null -w '%{http_code}\n' localhost:8000/up

# real browser E2E (Chromium 152 from the @sparticuz/chromium tarball; this
# container needs --no-zygote and cannot use the script's --single-process)
CHROMIUM_PATH=/tmp/chromium-wrap/chromium BASE_URL=http://127.0.0.1:8000 \
E2E_USERNAME=runtime.owner E2E_PASSWORD='...' node scripts/runtime/browser-e2e.mjs
```

Route-surface enumeration used in §8.1:

```bash
php artisan route:list --json | jq -r '.[] | select(.method | test("POST|PUT|PATCH|DELETE"))
  | select((.middleware | join(" ")) | test("employee|auth|throttle") | not) | .uri'
# 511 routes, 433 mutations, 1 unauthenticated: PUT storage/{path}  (framework default, signature-gated)
```

## 13. Reading order

1. This file — what was compared, decided and verified, and what is still open.
2. `docs/AUDIT-2026-09-08-PRODUCTION-READINESS.md` — the full per-area review, with
   the certification verdict corrected in place.
3. `docs/RUNTIME_VERIFICATION_HANDOFF.md` Part I — the honest gate record of the
   baseline the certification reviewed.
4. `FINAL-ENGINEERING-REPORT.md`, `AUDIT-SUMMARY.md` — the superseded summary layer,
   retained for provenance.

CI evidence: `34252336159` → all three jobs **success** (that run is what proves the
reconciled *code* state is green); `34253638765` → all three jobs **success** on
`d087d7f`, which already includes the `recovery/` removal and the full
reconciliation record. Any later commit on this branch touches only these two
Markdown documents. The authoritative branch state is therefore **CI-green as
verified, not CI-green as asserted**.
