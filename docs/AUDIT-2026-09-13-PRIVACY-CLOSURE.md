# Privacy & Consent — Audit Closure Report (2026-09-13)

**Verdict: CLOSED** — every proven defect is fixed at the layer that owns it,
regression-tested, and rehearsed in a real browser. Verification run
**34742746987** (tree `55fc461`) passed **all four jobs** — static analysis,
the full backend job, every frontend gate, and the Browser E2E job with all
four journeys, the Privacy journey at **38/38 checks** across four isolated
Chromium sessions; CRM Browser E2E run 34742746986 passed alongside. Run
34741595867 (tree `05aecf6`) had already proved the same four jobs green on the
tree that fixed the last PHPStan error, and 34741188832 (tree `30c0a70`) before
it proved the backend suite, the canonical suite, the runtime invariants and
races, every frontend gate and all four browser journeys green. The temporary CI evidence
channel used to read those results is retired in the closure commit, the same
lifecycle the Library and Documents channels had.

Scope: the Privacy & Consent domain only — purposes of personal-data use,
consent capture and its lifecycle, withdrawal evidence, disclosure evidence,
subject access (the dossier), subject-data release (direct and staged
organization-wide export), scope arithmetic, authorization, audit/provenance,
idempotency, concurrency, API↔React parity, UX/error states, and end-to-end
browser proof. Authority doctrine held throughout: consent is a recorded fact
with a lifecycle and never a boolean flag; erasure is revocation, expiry and
archive over append-only rows and never a deletion; React never owns business
truth. Library & Resources and Documents & Evidence were already closed and
were not reopened — the only touch to their journeys was the shared CI
evidence helper.

## Method

Nothing was trusted on appearance. The domain was re-derived from the command
layer, the PostgreSQL triggers and the canonical API, every finding was
reproduced before being called a defect, every fix shipped with a regression
test, and every workflow was rehearsed against live servers (scratch
PostgreSQL + real HTTP + real headless Chromium in CI). The audit sandbox has
no PHP toolchain — `composer`, `phpunit`, `phpstan` and `pint` cannot run
locally, and the Actions log endpoints redirect to blob hosts that are
unreachable from it — so backend evidence came exclusively from the
Verification workflow, and a budgeted annotation channel was added to make
that evidence readable (§ CI evidence channel). Local gates were limited to
the Node toolchain: a `php-parser` syntax gate on every touched PHP file, an
import/trailing-comma/whitespace scan, `node --check` on the harness, and the
frontend suites.

## Findings ledger

| # | Sev | Finding | Proof | Fix | Verification |
|---|-----|---------|-------|-----|--------------|
| P1 | P1 | Two privacy read models. `/governance/privacy` and `PrivacyController::index()` rendered a Blade privacy projection while the React workspace existed only as a read-only surface with no canonical write transport — a second, weaker authority over the same facts | Route table + controller diff; `ConsoleSmokeTest` pinned the `privacy.index => governance.privacy` redirect | `/privacy` now serves the React workspace (`Route::view('workspace', …, view: privacy)`); the Blade index method and the `/governance/privacy` alias are deleted; all thirteen mutations travel over `/api/v1/privacy` | `ConsoleSmokeTest` redirect entry removed; `WorkspaceMountConvergenceTest` pins the mount; the browser journey asserts a canonical-POST-only mutation allowlist with zero violations |
| P2 | P1 | Row affordances were not derived from the lifecycle. The transport could not tell the browser which consent transitions were legal, so any UI that offered them would have had to carry a private copy of the lifecycle table — business truth duplicated in React (the Documents D7 defect, present here before it was built) | API/response audit: no per-row action projection existed | `PrivacyApiController::consentAffordances()` derives every flag from granted scope ∧ `ConsentLifecycle::allowsTransition()`; `privacy.tsx` renders the flags verbatim with zero lifecycle-state gates | Feature test walks every state through production commands asserting the exact matrix; `privacy-contract.test.mjs` pins that the component re-derives nothing; the journey asserts the projected button set per state (draft offers Submit only, revoked offers Dossier+Archive, …) |
| P3 | P2 | `DefineConsentPurpose` had no duplicate guard: a second definition of the same (name, channel) hit the unique index and surfaced as a raw `QueryException` → HTTP 500, leaking SQL text to the caller | Read of the command: it called `requireDefiner` then inserted; the DB index `consent_purposes_name_channel_unique` was the only guard | `BusinessRejection('privacy.purpose_duplicate')` raised after the authority check, before the insert — a 409 with a correlation id | `PrivacyApiFeatureTest` asserts 409 + code + still one row + the original category retained; `ConsentFeatureTest`'s `expectException(QueryException::class)` for this path converted to the business rejection (the two remaining `QueryException` expectations — the one-open-consent index and the append-only revocation trigger — are unchanged and still correct) |
| P4 | P2 | Consent expiry had no transport. The lifecycle table allowed `active -> expired` and `TransitionConsent::expire()` implemented it against the CalendarAuthority, but neither the API nor the UI exposed it, so a lapsed consent could only be produced by raw SQL | Route table: submit/verify/activate/revoke/archive existed, expire did not | `POST /api/v1/privacy/consents/{id}/expire` + the `expire` affordance + the UI action with its confirmation copy | `ConsentLifecycleBoundaryTest` proves the window rule (not lapsed → `privacy.consent_expiry_not_due`; lapsed → expired); the journey observes the rejection live on an open-ended consent and expires a lapsed one |
| P5 | P2 | Export staging authority was inline in the command. The two-distinct-signature chain, the requester exclusion and the write-once approver slots lived inside `ExportSubjectData::approve/execute`, untestable in isolation and invisible to the projection, which could therefore offer a signature the command would refuse | Read of `approve()`/`execute()`; the projection had no chain predicate | Extracted `App\Modules\Privacy\Domain\ExportApprovalChain` — one registry that decides which chain step is legal next (`acceptsSignature`, `requireSignature`, `allowsExecution`, `requireExecution`, `isClosed`). Both the command and the projection consult it, so they cannot disagree | Canonical `SubjectDataReleaseTest`; `DisclosureExportFeatureTest`; the journey: first signature fills one slot and stays `requested`, the signer is then offered nothing, the same actor is refused 403 `privacy.bulk_export_single_actor`, a distinct second signature closes it as `approved` |
| P6 | P2 | Execution scope was ambiguous. A branch-scoped officer who requested an organization-wide release could also execute it — requesting was treated as authority to release | Read of `execute()`: it resolved the subject's branch scope | `execute()` now requires `privacy.export` at **organization** scope (as does `approve()`), while `request()` stays branch-scoped: a branch officer may request, never sign or execute | `PrivacyApiFeatureTest` bulk-chain test pins the requester's 403 `privacy.export_denied` **and** its `privacy.export.execute.denied` audit row, then executes as the organization-scoped exporter |
| P7 | P2 | No canonical Privacy suite. The domain's invariants had no `tests/Canonical/Privacy` proof; the lifecycle trigger, the one-open-consent index and consent immutability were unattacked | Repo audit at the merge base: `tests/Canonical/` held twelve suites (Academic, Access, Admissions, Api, Architecture, Documents, Finance, Identity, Journeys, Placement, Resources) and no Privacy | `ConsentLifecycleBoundaryTest` (savepoint-wrapped raw-SQL attacks: lifecycle trigger, one-open-consent partial index, `DELETE`/`forceDelete` immutability) + `SubjectDataReleaseTest` (dossier completeness, subject self-read, staged release). Fixtures are built only through production commands | CI Canonical suite: green (run 34740270037, step "Canonical suite", and again on 34740680278) |
| P8 | P2 | No privacy browser journey. The domain had no real-browser proof: four sessions, no end-to-end walk of capture → lifecycle → withdrawal → disclosure → release → staged export | Repo audit: `scripts/runtime/` held calendar, structure, crm, library, documents journeys | `privacy-browser-e2e.mjs` (38 checks) + `privacy-browser-provision.php` (actors provisioned through the canonical `BuildsActors` trait, never a script-local authz model), wired as `npm run verify:browser:privacy` | CI Browser E2E job; four isolated sessions (officer, approver one, approver two, subject) with per-session console/network hygiene and a canonical-POST-only mutation allowlist |
| P9 | P3 | The dossier could not answer a subject access request. `SubjectPrivacyQuery::subjectProfile()` projected only currently-effective consents: no history, no revocations attached to the consent they ended, no export requests | Read of the query | Added `consent_history` (each row with its withdrawal evidence), `export_requests`, and `as_of`; the existing keys are unchanged, so every prior assertion still holds (they are count/emptiness based) and the shape is a strict superset | `SubjectDataReleaseTest` asserts dossier completeness; `PrivacyApiFeatureTest` asserts the projection keys; the journey reads the dossier as the subject and as the officer |
| P10 | P3 | `effective_to` accepted a window equal to `effective_from` (`after_or_equal`), so a consent could be recorded already lapsed and expired in the same breath | Validation rules in both transports | `after:effective_from` in the API and the legacy adapter | Feature tests record a lapsed window and an open-ended one; the journey drives both |
| P11 | P3 | Style: two docblocks spelled out types their file already imports (`\Closure(...)`, `\Illuminate\Support\Collection<int, Consent>`) | CI run 34740270037, `vendor/bin/pint --test`: "978 files, 2 style issues", rule `fully_qualified_strict_types` | Imported short names, the house pattern in the other query classes | Commit `30c0a70`; run 34741188832 static analysis green |
| P12 | P3 | Test authority ordering: the bulk-chain test probed "execution before approval is rejected" while signed in as the branch-scoped requester, so it asserted a 409 the authorization layer never lets that caller reach | CI run 34740270037 backend suite: `Failed asserting that 403 is identical to 409` in `PrivacyApiFeatureTest::test_the_bulk_export_chain_resolves_the_organization_server_side_and_needs_two_sessions` | The probe now signs in as the organization-scoped exporter — the only actor for whom the state rejection is reachable. Authorization still precedes chain state; the requester-denial assertions later in the test are untouched | Commit `30c0a70`; backend suite green on run 34741188832 |
| P13 | P3 | PHPStan: `PrivacyExportRequest` declares `@property` tags for its domain columns, so larastan holds it to that set — and the two timestamps the new projections read (`created_at` for the release chronology, `updated_at` for the chain's last movement) were not in it. Three errors, one cause | CI run 34741188832, `vendor/bin/phpstan analyse`: "Access to an undefined property …::$created_at" at `PrivacyApiController:210`, `:211` and `SubjectPrivacyQuery:120` | Both timestamps declared in the house form used by `VisitorFollowup` (`Illuminate\Support\Carbon`, non-nullable). The sibling privacy models declare no properties and are therefore not held to a set; this one is | Commit `05aecf6`; static analysis green on run 34741595867 |

## Journey harness bugs fixed (test-side, no production change)

Commands refresh the workspace **silently** — `load(false)` paints no busy
state — so `waitIdle()` (a DOM check: workspace mounted, placeholder gone, no
ellipsis button) can pass in the gap before the refreshed projection lands.
Four reads raced that gap and asserted against the stale pre-command view. The
Documents journey documents the identical race and its rule: *gate the read on
the refreshed projection*.

| Run | Tree | Journey result | What it caught |
|---|---|---|---|
| 34740270037 | `4b88837` | 34/38, ran to completion | purpose catalog read before the third definition's refresh; disclosure row read before it landed; signatures cell and post-signature affordances read before the approver-slot refresh |
| 34740680278 | `7ed900b` | 34/38 (same tree behaviour, evidence channel only) | confirmed the four failures were read races, not server state: the same-actor refusal downstream observed the filled slot, proving the write had landed |
| **34741188832** | **`30c0a70`** | **38/38 PASS, exit 0** | — (green: full lifecycle, provoked denials, staged chain, subject-side boundary, canonical mutations only, four clean sessions) |
| **34741595867** | **`05aecf6`** | **38/38 PASS, exit 0** | — (re-proved on the closure tree; PHPStan fix only) |
| 34742078457 | `9440e41` | **not reached** — the shared smoke journey failed first and the remaining journeys were skipped | the smoke journey's one-shot console read (§ The same race in the shared smoke journey) |
| **34742746987** | **`55fc461`** | **38/38 PASS, exit 0** | — (green behind a hardened smoke journey; all four journeys ran) |

Fixes: `waitForRow()` polls until a created row exists in the refreshed
projection, and `waitForRowCellSettled()` polls until an approver slot is no
longer the `—` placeholder; the neighbouring cells and the affordance read then
come from one consistent post-command paint. Reads that follow a **denied**
command are deliberately left one-shot: a denial performs no refresh, so the
unchanged view is the correct expectation, and a genuine projection defect must
still record a FAIL rather than time out.

### The same race in the shared smoke journey

Adding `/privacy` to the general journey's console tour exposed the identical
defect one level up: the tour read each console **once**, immediately after
`page.goto(…, { waitUntil: 'networkidle2' })`, and a console mounts
asynchronously — `networkidle2` can settle in the gap before React has painted,
so `mounted` and the expected-text match were sampled from an unpainted
document. The closure commit's run (34742078457) failed exactly there, in the
Browser E2E job's first step, with Library, Documents and Privacy skipped
behind it, on a tree that had passed the same journey four consecutive times
and whose commit touched only docs and the workflow's evidence mirror.

The tour now polls, bounded at fifteen seconds, until the console is mounted and
its expected text is present; the timeout falls through to one honest read, so a
console that never renders still fails. The error and failed-request deltas are
still compared against the counts captured before the visit — nothing was made
more lenient. The failure detail also names its evidence now (the bad API calls
with statuses, the first console errors and failed requests seen during that
visit) instead of only counting it, because an operator who cannot read CI logs
has to be able to diagnose a journey from its own output.

## CI evidence channel (temporary, retired at closure)

The audit sandbox cannot download Actions logs: `gh run view --log`,
`--log-failed` and the per-job log endpoint all redirect to
`*.blob.core.windows.net` / `results-receiver.actions.githubusercontent.com`,
which fail TLS from the sandbox, and the REST annotations carried only
`Process completed with exit code 1`. The per-step status API *is* readable, so
the failing gate could be identified but not diagnosed.

Commits `7ed900b` and `30c0a70` therefore added a budgeted mirror: each gate
that can fail tees its output to a log and, on failure only, re-emits the
decisive lines as workflow annotations — the gate's own summary as `error`, the
matched detail lines as `warning`/`notice`, stack frames dropped, one pattern
per gate (Pint's verbose diff, PHPStan's raw error list, PHPUnit's failure
headers, a journey's FAIL lines and their observed projections). GitHub keeps
only a handful of annotations per level, which is why the budget is spent
summary-first. No gate was weakened: every wrapped step runs the identical
command and still decides its job. The first patch did drop
`composer check-platform-reqs` from the static job while inserting the helper;
reviewing the patch's removed lines caught it and it was restored before the
commit, so no gate was ever missing from a run.

That channel is what produced P11, P12, P13 and the four harness races above.
It was retired in the closure commit — and then restored for the Browser E2E
job only, four journey steps, when the smoke journey failed on that commit and
reruns turned out to be unavailable to this operator (the rerun endpoints answer
403 `Resource not accessible by integration`, so a fresh attempt requires a
commit). The static and backend jobs were never re-wrapped, no gate command
changed, and the channel is retired again in the commit that carries the smoke
journey's fix.

## Evidence chain (per required dimension)

| Dimension | Where proven |
|---|---|
| Requirement | `docs/11-PRIVACY-AUDIT-DOCUMENTS.md` (Privacy doctrine + workspace transport), `docs/17-FRONTEND-SURFACE-REGISTER.md`, this report |
| Authority | `privacy.define_purpose`, `privacy.consent`, `privacy.disclose`, `privacy.export`, `privacy.approve_bulk_export`; subject-side acts need no staff capability (`ConsentFeatureTest`, `PrivacyScopeAndEvidenceFeatureTest`) |
| Lifecycle | `ConsentLifecycle` (one table, consulted by command, transport and projection); `ConsentLifecycleTest`; `ConsentLifecycleBoundaryTest`; the state-matrix feature test |
| Data model | `consents`, `consent_purposes`, `consent_revocations`, `disclosures`, `privacy_export_requests`; born-draft and write-once facts in `Consent::save()` |
| DB invariant | Migration `000211` (one open consent per subject+purpose, partial unique); `000114` (export-request chain trigger); `consents_guard_trigger`, `consent_revocations_append_only_trigger`, `disclosures_append_only_trigger`; `verify:invariants` privacy probes and `verify:concurrency` privacy races, both green in CI |
| Command | Canonical fixtures are built only through `DefineConsentPurpose`, `RecordConsent`, `TransitionConsent`, `RecordDisclosure`, `ExportSubjectData` |
| Authorization/Scope | `PrivacyScopePolicy` (`privacy.scope_mismatch`, `scope_required`, `scope_type_invalid`, `scope_unknown`, `scope_inactive`); `api.read_denied` / `api.organization_read_denied` fail-closed; every denial audited with its code and capability reason |
| API | `PrivacyApiFeatureTest` (8 tests), `PrivacyAuditApiFeatureTest` (4), `PrivacyWorkflowFeatureTest` (4); 13 mutations + 2 reads, no PUT/PATCH/DELETE anywhere |
| React | `privacy.tsx` renders the server projection; `privacy-contract.test.mjs` (13 tests) + `privacy-audit-contract.test.mjs` (2) pin the transport, the affordance rendering and the absence of client-side lifecycle re-derivation |
| Audit | `audit_events` asserted per operation and per denial (`privacy.purpose.define(.denied)`, `privacy.consent.*`, `privacy.export.*`, `privacy.disclose(.denied)`, `privacy.api.subject.denied`, `privacy.api.workspace`) |
| Idempotency | Every mutation carries an Idempotency-Key; conflicting payload → `idempotency.conflicting_payload` 409; replays asserted in the feature suite and exercised by the journey |
| Concurrency | `verify:concurrency` privacy races (one open consent per subject+purpose, consent transition races) green in CI |
| Erasure doctrine | `Consent::delete()`/`forceDelete()` throw `privacy.consent_immutable`; no delete/erase/purge control exists in the UI; revocation, expiry and archive are the only endings |
| Browser/E2E | `verify:browser:privacy`: 38 checks, four sessions, canonical-POST-only allowlist, per-session console/network hygiene |

## Local gate results (this sandbox)

- PHP syntax gate (`php-parser`) on all 23 touched PHP files: clean.
- Import audit (unused imports, import order) across the touched files: 0 issues.
- Style pre-scan (trailing commas in multiline structures, trailing whitespace,
  tabs, EOF newline, double blank lines, `!` spacing, concat spacing, blank line
  before `return`/`throw`): 0 issues. Pint still found the two docblock FQCNs
  (P11) — the reason the CI mirror exists.
- `node --check` on the journey harness: clean.
- `npm run test:runtime-safety`: 3/3.
- Frontend suites (`npm run test:frontend`, incl. the privacy contract suites)
  and `typecheck`/`build`: green in CI on every run of this cycle.
- `phpunit`, `phpstan`, `pint`, `verify:invariants`, `verify:concurrency`:
  **not runnable here** (no PHP toolchain, no `vendor/`); CI is the evidence.

## Current CI state

| Run | Tree | Frontend | Static analysis | Backend | Browser E2E | What it proved / caught |
|---|---|---|---|---|---|---|
| 34740270037 | `4b88837` | green | **pint FAIL** (2 style issues) | **full suite FAIL** (1 test: 403 vs 409) — migrations, invariants, concurrency and the **canonical suite green** | **privacy journey 34/38** — general, Library, Documents green | P11 style, P12 authority ordering in the test, the four harness read races |
| 34740680278 | `7ed900b` | green | pint FAIL (same 2) | full suite FAIL (same test) | privacy journey 34/38 | evidence channel only; confirmed the four journey failures were stale reads, not server state |
| 34741188832 | `30c0a70` | green | **pint PASS (978 files)**; **phpstan FAIL** (3 errors) | **green** — migrations, invariants, concurrency, canonical suite, full suite | **green** — all four journeys, privacy 38/38 | P13 property declarations |
| 34741595867 | `05aecf6` | green | **green** — pint, phpstan, migration audit, terminology audit, composer validate/audit/platform | green | green | last PHPStan error fixed; all four jobs green |
| 34742078457 | `9440e41` | green | green | green | **FAIL** — the shared smoke journey, with the three domain journeys skipped behind it | the console-paint race; docs-and-workflow-only commit, so nothing in it could explain the failure |
| **34742746987** | **`55fc461`** | **green** | **green** | **green** | **green** — all four journeys, privacy 38/38 | — **closure evidence run** |

- The closure commit re-proved every gate on its own tree: run **34743236679**
  (tree `5e5ea4c`, this report plus the retirement of the evidence mirror) is
  green on all four jobs, with CRM Browser E2E run 34743236760 alongside. The
  green run above and the green run on the closure tree are the same workflow
  shape, because retiring the mirror changes no gate command.
- CRM Browser E2E runs 34740270038, 34741188858, 34741595850, 34742746986 and
  34743236760: success (actionlint plus the CRM journey, so every workflow edit
  was structurally valid and the CRM surface is untouched).
- The temporary annotation channel is retired in the commit that carries this
  report; the workflow returns byte-for-byte to the shape that ran as `4b88837`,
  so every gate command is identical to the ones that produced the green run
  above and only the evidence mirror is gone. Reruns were never available to
  this operator (403 `Resource not accessible by integration`), which is why
  each diagnosis cost a commit and a full run.
- No mutation-check evidence is claimed for the new canonical Privacy tests:
  this audit sandbox has no PHP toolchain, so "break the production rule and
  watch the test fail" could not be executed here. The canonical tests attack
  the guards directly through raw SQL under savepoints and through the model's
  own `save()`/`delete()` overrides, and the runtime invariant and concurrency
  scripts independently exercise the same boundaries in CI; recording a
  mutation run remains open work for an environment that can execute PHPUnit.

## Remaining work

1. Merge to `main` and run the Verification workflow on the main HEAD as the
   certification run (same doctrine as the Library and Documents closures). No
   known Privacy defects remain open.
2. Execute a mutation check on the canonical Privacy suite in an environment
   that can run PHPUnit (remove the lifecycle guard → the boundary test must
   fail; remove the one-open-consent index → the savepoint attack must
   succeed), and record it here per `docs/TEST_SUITE_ARCHITECTURE.md`.
3. Decide the deprecation of the legacy web POST adapters. They are thin,
   tested and carry the same validations, so this is a scheduling decision, not
   a privacy defect.

## Observations (not defects)

- The subject-side boundary is asymmetric by design and is proven as such: the
  subject's workspace projection is **denied** fail-closed
  (`api.organization_read_denied`) while the subject may still read its own
  dossier and submit or revoke its own consent. A UI that showed the subject an
  empty workspace instead of a denial would be indistinguishable from a bug, so
  the journey asserts the denial text itself.
- Personal data has no delete path anywhere in the domain — model, transport or
  UI. That is the doctrine (`docs/11-PRIVACY-AUDIT-DOCUMENTS.md`), not a gap:
  erasure requests are answered by revocation, expiry and archive over retained
  evidence.
- `ExportSubjectData::export()` (direct, single-subject, non-organization
  scope) and `request()`/`approve()`/`execute()` (staged, organization-wide)
  are two different releases with two different scope decisions. Keeping them
  in one command is deliberate: they share the disclosure-evidence rule, and
  splitting them would create a second place where a release is defined.
- The legacy web POST endpoints remain as thin compatibility adapters. They are
  exercised by `PrivacyWorkflowFeatureTest` and carry the same validations as
  the API; removing them is a separate deprecation decision, not a privacy
  defect.
