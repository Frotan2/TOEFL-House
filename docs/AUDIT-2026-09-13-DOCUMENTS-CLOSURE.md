# Documents & Evidence — Audit Closure Report (2026-09-13)

**Verdict: CLOSED** — every proven defect is fixed, regression-tested,
mutation-checked where applicable, and rehearsed at runtime. Verification run
**34732090672** (tree `5f32839`) passed **all four jobs** with the Documents
browser journey at **26/26 checks** across three isolated Chromium sessions;
CRM Browser E2E run 34732090655 passed alongside. The temporary CI evidence
channel used to stabilize the journey is retired in the closure commit, the
same lifecycle the Library journey's channel had.

Scope: the Documents & Evidence domain only — identity of records, registration,
versioning, classification, submission, verification, retention, access/scope,
separation of duties, audit/provenance, idempotency, concurrency, API↔React
parity, UX/error states, recovery, and end-to-end browser proof. Authority
doctrine held throughout: `documents.register` owns registration/versioning,
`documents.verify` owns independent verification and the post-verdict
lifecycle, the uploader of a version can never verify that version, and React
never owns business truth.

## Method

Nothing was trusted on appearance: the domain was re-derived from the command
layer and the PostgreSQL schema, every finding was reproduced before being
called a defect, every fix shipped with a regression test that was
mutation-checked (break the production rule → the test must fail), and every
workflow was rehearsed against live servers (scratch PostgreSQL + real HTTP +
real headless Chromium in CI). Unreproducible suspicions were dropped, not
recorded.

## Findings ledger

| # | Sev | Finding | Proof | Fix | Verification |
|---|-----|---------|-------|-----|--------------|
| D4 | P2 | `document_verifications` had no UNIQUE(document, version) one-verdict boundary; two concurrent verdicts on one version could both persist | Raw-SQL repro inserting two verdict rows for one version succeeded (`/tmp/d4-repro.php` pattern) | Migration `000210` adds the one-verdict unique index | Commit `894f7ea`; concurrency race 9 observes one-committed/one-rejected `document_verifications_one_verdict_per_version`; invariant probe rejects the duplicate; canonical boundary test attacks it via raw SQL |
| D3 | P2 | `verify:invariants` probed 0 documents boundaries | Script source audit | 8 new probes (append-only versions, one-verdict, lifecycle-state check, retention FK/check, classification constraints…) | Commit `71695c7`; local run 23/23 rejected invalid writes |
| D2 | P2 | `verify:concurrency` raced 0 documents tables | Script source audit | 3 new races (double-verify one version, double-submit one draft, verify/submit interleaving) | Commit `1b06c98`; local run 9/9 passed |
| D1 | P2 | No `tests/Canonical/Documents` suite — the domain's invariants had no canonical proof | Repo audit | `DocumentEvidenceLifecycleTest` (5 tests) + `DocumentEvidenceBoundaryTest` (5 tests incl. savepoint-wrapped raw-SQL attacks); fixtures only via production commands; asserts rows, `audit_events`, `domain_events` | Commit `1b69ed6`; 10 tests / 94 assertions; mutation-checked (SoD guard removal → 1 failure; `requireTransition` removal → 2 failures); mutation evidence recorded in the commit record per the current `docs/TEST_SUITE_ARCHITECTURE.md` §5 policy (the frozen mutation table was retired by the repository-hygiene port); plus `83be57b` converting a dead `expectException` assertion in the feature suite into real audit-row assertions |
| D6 | P3 | `documents-contract.test.mjs` was static grep only — no proof of what the UI renders, posts, or surfaces | Test source audit | 6 behavioural tests bundling the REAL `DocumentsApp` (esbuild) into JSDOM against a scripted API client: action matrix per state + metrics, exact register payload, verify fail path with SoD alert and no success notice, archive confirm gate (refuse → zero posts), immutable history rendering with no storage reference, denied workspace fail-closed with retry | Commit `c25eae5`; suite 9/9, full frontend 19/19 |
| D7 | P3 | Row-level `available_actions` were scope-only "UX hints": the API told a verifier a REJECTED document was verifiable and told a registrar an ARCHIVED document could take a new version; the UI stayed correct only because `documents.tsx` carried a private copy of the lifecycle table — business truth duplicated in React | Live HTTP rehearsal: rejected row projected `verify:true`, archived row projected `submit:true`, while the commands themselves reject both (409 `documents.transition_forbidden`) | `DocumentsApiController::rowAffordances()` derives every row flag from granted scope ∧ `DocumentLifecycle::allowsTransition()` (submit/verify/activate/expire/archive + scope-gated retention); `documents.tsx` renders the flags verbatim with zero lifecycle-state gates | Commit `bf317c1`; new feature test walks ALL SEVEN states via production commands asserting the exact matrix; old defect-pinning assertion flipped with justification; mutation check (scope-only verify → matrix test fails); static pins forbid re-derivation in the component; runtime rehearsal re-run shows state-legal flags for every state |
| D5 | P2 | No documents browser E2E journey — the domain had no real-browser proof | Repo audit (`scripts/runtime/` had calendar/crm/library/structure journeys only) | `documents-browser-e2e.mjs` + `documents-browser-provision.php`: three isolated Chromium sessions (officer / registrar / verifier, provisioned through the canonical test traits) walk classification → retention rule → register → submit → FAIL verdict → re-submit → PASS → activate → retention decision → expire → archive, observe the SoD denial live (verifier self-verify → server alert, document unmoved), assert the immutable history and the absence of storage references, the canonical-POST-only mutation allowlist, scripted confirm dialogs, and per-session console/network hygiene | Commit `dd3db96`→`adb2374` (message amended: it accidentally contained a literal ci-skip token that suppressed the first run) + harness fixes `8dc317b`, `df24719`, `0272d1e`; wired into the Browser E2E job via `npm run verify:browser:documents` |

### Harness bugs found by the first CI run and by static cross-check (all fixed before the final run)

- Waited for `#app-toolbar`, an element that exists nowhere; the authenticated
  `/documents` page had rendered fine (evidence HTML committed back by the
  temporary diagnostics channel). Now waits for the real `#documents-title`.
- An aborted journey exited 0 because every record collected before the throw
  had passed — the CI step reported success for an aborted run. Aborts are now
  recorded as failed checks; the exit code cannot lie.
- Row selectors mismatched the real markup (`th` vs `td`, chip cell index,
  action-strip class), and the registry table unmounts off-tab (journey now
  switches back after registrations).
- The policy tab mounts two forms that both carry a `Category` label bound to
  different state; the journey filled only the first match, so the retention
  rule would have posted an empty category. Label resolution is now
  form-scoped.
- A `<label>` wrapping a `<select>` carries every option's text inside its
  `textContent`, so exact-text label matching could never resolve a select
  (it would have stalled on the officer's first policy step). Labels are now
  matched by their first text node — and the whole journey DOM contract was
  replayed against the real rendered component in a throwaway JSDOM suite
  (row lookup, chip cell index, action-strip matrix for all eight fixture
  rows, tabs, both h3-scoped Category forms with independent state,
  registration controls, the verify panel's dynamic Pass/Fail submit labels,
  the submit-version panel, metrics grid, history panel): 10/10 green before
  burning another CI cycle.
- The `clickPageButton` helper's definition was silently lost by a patch
  whose anchor had been consumed earlier in the same script (a string
  replace that misses is a no-op `node --check` cannot see). Every helper
  the journey calls is now definition-checked.
- Stale-projection race class: commands refresh the workspace SILENTLY
  (`load(false)` shows no busy indicator and the notice renders before the
  reload lands), so one-shot chip reads observed pre-command state. Every
  state read now polls (`waitForChip`), and whenever ANOTHER session moved
  a document (registrar recovery, verifier re-read, officer retention,
  terminal buttons) the acting session clicks 'Refresh facts' first —
  exactly what a real operator must do. The last gap of this class was
  `waitIdle` passing inside the pre-render gap right after a refresh
  click; the officer's button read is now gated on the refreshed
  projection.

### CI stabilization history (what each run proved)

| Run | Tree | Journey result | What it caught |
|---|---|---|---|
| 34717011786 | `adb2374` | aborted at login wait, exited 0 | bogus `#app-toolbar` selector; the false-success exit code |
| 34730341173 | `71acd19` | aborted at officer policy step, exited 1 | missing `clickPageButton` definition; honest exit code confirmed working |
| 34730722898 | `8796d44` | 10/11, aborted at PASS verdict | silent-reload races; cross-session staleness (registrar recovery, verifier re-read) |
| 34731647033 | `7bd9de1` | 25/26, ran to completion | officer pre-render gap after Refresh |
| **34732090672** | **`5f32839`** | **26/26 PASS, exit 0** | — (green: full lifecycle, SoD denial, terminal state, history, storage-leak, 14 canonical mutations / 0 violations, 3 clean sessions) |

## Evidence chain (per required dimension)

| Dimension | Where proven |
|---|---|
| Requirement | Domain docs + this report; lifecycle table in `DocumentLifecycle` |
| Authority | register/submit → `documents.register`; verify/activate/expire/archive → `documents.verify`; classify/retention-rule → `documents.classify`; retention decision → `documents.retention` (feature + canonical suites) |
| Lifecycle | `DocumentEvidenceLifecycleTest`; new matrix feature test walks all seven states |
| DB invariant | `verify:invariants` 23/23 (incl. 8 documents probes); boundary raw-SQL attacks under savepoints |
| Command | Canonical suite builds fixtures only via production commands |
| Authorization/Scope | Fail-closed denials audited (`83be57b` fix makes the audit assertions actually run); branch-scope projection tests |
| API | `DocumentsApiFeatureTest` (9 tests/116 assertions) incl. lifecycle-legal row matrix |
| React | `documents-contract.test.mjs` 9 tests: static pins + behavioural rendering |
| Audit | Canonical + feature assertions on `audit_events` per operation; denial attempts recorded |
| Idempotency | Canonical/feature idempotent-key replays; adversarial keys fall back safely (prior audit, re-verified) |
| Concurrency | `verify:concurrency` 9/9 incl. 3 documents races; one-verdict index observed rejecting under race |
| Tests | Full local phpunit: 1195 tests, 9001 assertions — only 5 sandbox-environment failures (`SchemaCompatibilityProbeTest` requires a `psql` binary absent from the audit sandbox; the same tree passes the CI backend job) |
| Browser/E2E | `verify:browser:documents` in the Browser E2E job; SoD denial observed in a real browser; canonical-POST-only mutation allowlist |
| Runtime evidence | Live scratch-server rehearsal of every journey step (all 7 states, retention decision row, 409 `documents.verifier_is_uploader`, state-legal projections); CI evidence channel committed console log + summary |

## Local gate results (pre-closure tree; the later commits touched only the
## journey harness under `scripts/runtime/`, which no PHP/frontend suite reads,
## and CI re-proved every gate on `5f32839`)

- phpunit full suite: 1195 tests / 9001 assertions; 5 failures, all
  `SchemaCompatibilityProbeTest`, all caused by the missing `psql` binary in
  the sandbox (green in CI on the same tree).
- Frontend: 19/19 across mount/productization/parity/resources/documents suites.
- `verify:invariants`: 23/23. `verify:concurrency`: 9/9.
- pint + phpstan: clean on every touched file.

## Current CI state

- **Verification run 34732090672 (tree `5f32839`): success** — Static
  analysis (pint/phpstan/audits), Backend (migrations, full suite,
  invariants 23/23, concurrency 9/9), Frontend (typecheck, build, contract
  suites), Browser E2E (smoke + Library 24/24 + **Documents 26/26**).
- CRM Browser E2E run 34732090655: success.
- The journey's temporary evidence channel (tee + commit-back step +
  job-scoped `contents: write`) is retired in the closure commit; the
  `ci-evidence/` artifacts are removed from the tree and live on in branch
  history (`69550b1`, `fc01fdb`, `025980e`, `ca7a25c`, `33dd3a6`) — exactly
  the Library channel's lifecycle.
- A sandbox git restore mid-audit rewound the local branch to the merge
  base and discarded four local-only harness commits as objects; their full
  content survived in the working tree and was re-committed squashed as
  `71acd19` (nothing else was lost — every other commit had been pushed).

## Remaining work

1. Merge to `main` and run the Verification workflow on the main HEAD as the
   certification run (same doctrine as the Library closure). No known
   Documents defects remain open.

## Observations (not defects)

- activate/expire/archive are gated behind `documents.verify` (only four
  capability codes exist for the domain); this is deliberate design and was
  re-verified, not weakened.
- No scheduled retention sweep exists: retention decisions are operator-driven
  via `DecideRetention`. Recorded as a product observation.
- `RetentionPruningTest` concerns deploy-script directory pruning, not the
  documents domain — out of scope, untouched.
- The uploader/verifier SoD applies per version: the same verifier may verify a
  NEW version uploaded by someone else after failing an earlier one; a verifier
  can never verify any version they uploaded. Both halves are pinned by tests
  and observed in the browser journey.
