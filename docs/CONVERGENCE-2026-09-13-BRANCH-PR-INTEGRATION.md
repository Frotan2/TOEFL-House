# Main Convergence Report — 2026-09-13

**Mandate:** converge all valuable, non-obsolete completed work from existing
branches and pull requests into current `main`; produce one coherent, verified
canonical state. Not "merge every branch" — integrate what is proven, reject
what is obsolete, duplicated, superseded, experimental, or contradictory.

**Method (per integration):** inspect the exact diff against current `main`
first; establish ancestry and containment from SHAs, never from PR
descriptions, labels, historical runs, or prior conversation; identify
conflicts before integrating; resolve conflicts explicitly by domain
authority; prove each integration with a fresh authoritative Verification run
on the exact tree; one integration at a time.

**Test authority note:** the sandbox-local PHP/PostgreSQL toolchain was lost
to an environment rebuild mid-task. All gates in this convergence were
therefore executed by the repository's own authoritative Verification
workflow (Static analysis, Backend: migrations + full suite + invariants +
concurrency, Frontend: typecheck + build + mount contracts, Browser E2E:
smoke + Library + Documents real-Chromium journeys, plus the separate CRM
Browser E2E workflow) on the exact integrated trees. The Backend-failure root
cause for PR #25 was established from code inspection of the pinned contract
test plus two failed CI runs on that exact tree — evidence, not inference.

---

## 1. Final state

| Item | Value |
|---|---|
| Final `main` HEAD (content) | `bc930a2` — merge of PR #27 |
| Certification on `bc930a2` | Verification run **34734509163** — all 4 jobs SUCCESS; CRM run **34734509074** — SUCCESS |
| This report | docs-only addition merged after `bc930a2`; the Verification run on the report-bearing HEAD was triggered by its merge (result recorded in PR #28 and the convergence conversation — a report cannot contain the run ID of its own merge) |
| Branches deleted | **none** (see §5) |
| Unresolved conflicts | none |
| Duplicate authorities introduced | none |

## 2. Convergence matrix (every remote branch and PR)

| Branch / PR | HEAD inspected | Decision | Integrated SHA(s) | Reason |
|---|---|---|---|---|
| `main` | `83bde199` at task start | baseline | — | canonical authority; contained PR #24 (ORG-OPS governance) and all earlier work |
| `arena/01a09629-toefl-house` (no PR at start) | `a7e6f02` | **MERGE** (PR #26 → `b8eb8bf`) | 28 substantive commits `83bde19..a7e6f02` (41 incl. retired CI-evidence churn) | Library & Resources closure (F1–F10, `docs/AUDIT-2026-09-12-LIBRARY-RESOURCES-CLOSURE.md`) + Documents & Evidence closure (D1–D7, `docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`); both fully CI-verified pre-merge (run 34732618637 all jobs green on the exact tree `a7e6f02`); based on main HEAD, zero conflicts |
| `chore/repository-prune-and-normalize` (PR #25, OPEN at start, Backend FAILURE ×2) | `48c833d1` | **SELECTIVE PORT** (12 commits via PR #27 → `bc930a2`), **REJECT** 5, **SKIP** 2 net-zero; PR #25 closed as superseded | ported: `774c6c1` `55fa690` `24dab32` `96984ff` `b603245` `f175b78` `ccbbcbd` `93b3ef7` `8783aeb` `e949371` `78d1088` `67c943f` + reconciliation `a2b198b` | valuable docs/artifact hygiene, but as a whole it contradicted the pinned Windows deployment/recovery contract (proved root cause of its red Backend gate) and weakened provenance — see §3 |
| PRs #1–#24 (merged) | — | **ALREADY IN MAIN** | — | ancestors of `83bde19` by merge history |
| PRs #7, #8, #11, #13, #15, #16, #17, #18, #21, #22 (closed unmerged) | branches deleted | **REJECTED / SUPERSEDED historically** | — | dispositions from earlier convergence cycles; nothing retrievable (branches pruned, decisions superseded by merged successors) |
| PR #26 | `a7e6f02` | MERGED `b8eb8bf` | see above | post-merge certification: run **34733706878** all jobs SUCCESS + CRM **34733706867** SUCCESS on `b8eb8bf` |
| PR #27 | `0f02e29` | MERGED `bc930a2` | see above | pre-merge certification on exact tree `0f02e29`: run **34734189137** all jobs SUCCESS (see §4 for the infra-failure rerun) |

## 3. PR #25 per-commit disposition (19 commits)

**Root cause of its Backend failure (proven):** commits `469a98f` +
`2971cb5` delete `BACKUP-TOEFL-HOUSE.bat` and `RESTORE-TOEFL-HOUSE.bat`,
which `tests/Feature/Deployment/WindowsOneClickDeploymentContractTest` pins
in its `FILES` constant (`assertFileExists`) and whose contents it asserts as
the backup/restore contract; `START-TOEFL-HOUSE.bat` itself references both.
Runs 34712425383 / 34712423614 on `48c833d1`: Backend FAILURE, all other
jobs SUCCESS — consistent with exactly this cause.

| Commit | Subject | Disposition | Reason |
|---|---|---|---|
| `74df18b` | remove root AUDIT-SUMMARY.md | **PORTED** `774c6c1` | superseded by the dated `docs/AUDIT-*` evidence series; no test/audit/script references |
| `5d0cdbf` | remove root FINAL-ENGINEERING-REPORT.md | **PORTED** `55fa690` | ditto; referenced only from other dated historical documents |
| `08807df` | remove DIAG-PHP-CRASH.bat | **REJECTED** | retention is an explicit documented decision in SETUP.md ("operational diagnostic … retained because it has a defined troubleshooting purpose"); not proven obsolete |
| `469a98f` | remove BACKUP-TOEFL-HOUSE.bat | **REJECTED** | contract-pinned recovery artifact (Backend failure root cause); deleting it weakens the recovery guarantee |
| `2971cb5` | remove RESTORE-TOEFL-HOUSE.bat | **REJECTED** | contract-pinned restore path asserted by content in the deployment contract test |
| `0f955cf` | remove docs/history/01-foundation-history.md | **PORTED** `24dab32` | git history is the authoritative record; only docs/history/README referenced it (rewritten in the same port) |
| `2065c82` | remove docs/history/02-architecture-history.md | **SKIPPED** | net-zero churn — restored by `dfad1c9` in the same PR |
| `fa1a598` | remove docs/history/03-implementation-history.md | **PORTED** `96984ff` | as 01 |
| `5f9bd03` | remove docs/history/04-operations-governance-history.md | **PORTED** `b603245` | as 01 |
| `cfc7b72` | remove docs/history/05-current-state-compliance-evidence-2026-09-07.md | **PORTED** `f175b78` | stale dated snapshot; superseded by current OPERATING-CONTROL/RUNTIME-RELEASE evidence |
| `ee2e02c` | normalize docs/history/README.md | **PORTED** `ccbbcbd` | consistent with the retained-02-only directory |
| `dfad1c9` | restore docs/history/02-architecture-history.md | **SKIPPED** | net-zero pair with `2065c82`; 02 was never removed in the port |
| `0e53e9e` | document retained architecture-history dependency | **PORTED** `93b3ef7` | records why 02 must stay (decision-register full-text provenance) |
| `2a5e0ac` | retire frozen test-suite metrics | **PORTED** `8783aeb` (conflict resolved) | keeps the mutation-check obligation, moves evidence into commit records; **the only conflict of this convergence** — the freshly merged Documents §5 table rows; resolved by domain authority in favour of the newer policy (the obligation is unchanged; Documents/Library mutation evidence is recorded in their closure commits and reports) |
| `804c9da` | fix broken/duplicate architecture references | **PORTED** `e949371` | removes a pointer to nonexistent `docs/reference/current-state-compliance-evidence.md` and a self-referential duplicate; adds DOMAIN-REGISTRY/RUNTIME-RELEASE pointers |
| `7762c32` | docs/README historical-evidence paragraph | **PORTED** `78d1088` | makes the paragraph match the post-prune reality |
| `d6dc1ab` | remove "stale certification reference" from 16-DECISION-REGISTER.md | **REJECTED** | the pointer (`AUDIT-2026-09-09-FINAL-CERTIFICATION.md §6 FC-1`) is **valid** — that document exists in main; removing it weakens decision provenance |
| `0bf1c88` | remove orphan recovery/chunk1.b64 | **PORTED** `67c943f` | one-line orphan; zero references in code, tests, scripts, or the retained .bat files |
| `48c833d` | remove SETUP.md references to "retired" root utilities | **REJECTED** | documents the retirement of files that are retained (BACKUP/RESTORE contract-pinned; DIAG retention is a documented decision); also degrades markdown list indentation |

Port-side reconciliation (`a2b198b`): added the staged asset-disposal
withdrawal and document evidence lifecycle mutation checks to the ported
document's representative-mutations list, and updated the two closure
reports' references to the retired frozen §5 table so every merged document
states post-merge truth.

## 4. Verification evidence chain (fresh runs, exact trees)

| Tree | Run(s) | Result |
|---|---|---|
| `a7e6f02` (Library+Documents closure) | 34732618637 + CRM 34732618508 | SUCCESS (pre-merge certification) |
| `b8eb8bf` (main after PR #26) | 34733706878 + CRM 34733706867 | SUCCESS |
| `a2b198b` (port tree) | 34733753221 | **FAILURE — runner-side infra**: step "Install and warm Chromium" exit 100; all E2E steps skipped; Static/Backend/Frontend SUCCESS. Not tree-caused: identical app code passed the same step at `a7e6f02`; the port touches docs only. `gh run rerun --failed` refused (known API behaviour) → honest empty re-trigger commit `0f02e29` |
| `0f02e29` (same tree + re-trigger) | 34734189137 | SUCCESS — all 4 jobs incl. Browser E2E (Library 24/24, Documents 26/26) |
| `bc930a2` (**final main HEAD**) | **34734509163** + CRM **34734509074** | **SUCCESS — all 4 jobs; final certification** |

Gate coverage of the final run: environment/runtime lock, dependency
install, code style (Pint), static analysis (PHPStan + migration/terminology
audits), migrations on PostgreSQL, full backend suite, database invariant
probes, concurrency race verification, frontend typecheck, Vite production
build, mount contracts, real-Chromium smoke E2E, Library domain journey,
Documents domain journey, CRM journey (separate workflow), security
boundaries enforced within the backend suite (SoD, authorization, scope).

## 5. Branch hygiene

- `arena/01a09629-toefl-house` — fully represented in `main` (second parent
  of `bc930a2`); retained: it is the tracked session branch and the evidence
  chain (including retired CI-evidence commits) referenced by the closure
  reports.
- `chore/repository-prune-and-normalize` — intentionally **not** merged and
  **not** deleted: 12 of its 19 commits are in main content-wise via the
  documented cherry-picks; the 5 rejected commits (contract-breaking `.bat`
  deletions, provenance strip, their SETUP doc companion) and 2 net-zero
  commits remain only on this branch, as the record of what was rejected and
  why. PR #25's closing comment carries the same disposition. Merging it
  would reintroduce the rejected deletions — do not merge.
- No other remote branches exist. No branch was deleted in this convergence;
  every unmerged commit anywhere is provably either integrated content-wise
  or intentionally rejected with a recorded reason.

## 6. Counts

| Category | Count |
|---|---|
| Branches converged | 2 of 2 non-main branches dispositioned (1 merged whole; 1 selectively ported + partially rejected) |
| PRs opened/merged in this convergence | 2 (#26, #27) |
| PRs closed as superseded | 1 (#25, with per-commit disposition) |
| Commits integrated via #26 | 41 (28 substantive + 13 retired CI-evidence churn) |
| Commits selectively ported via #27 | 12 cherry-picks + 1 reconciliation + 1 documented empty re-trigger |
| Commits rejected | 5 (+2 net-zero skipped) |
| Conflicts encountered | 1 (`docs/TEST_SUITE_ARCHITECTURE.md`), resolved by domain authority |
| Already in main | PRs #1–#24 |
| Verification runs recorded | 5 green (incl. final certification 34734509163) + 1 proven infra failure + 2 pre-existing red runs root-caused on PR #25 |

## 7. Acceptance checklist

- [x] All valuable, proven branch/PR work is in `main` (Library & Resources
      closure, Documents & Evidence closure, salvageable repository hygiene).
- [x] Nothing valuable omitted; nothing obsolete reintroduced (`.bat`
      recovery surface, SETUP documentation, and decision-register provenance
      preserved against the prune PR).
- [x] No duplicate authorities (single decision register, single history
      policy, single testing-strategy doctrine; frozen metrics table retired
      by its owner's newer policy, obligation retained).
- [x] No unresolved conflicts or markers.
- [x] Final main HEAD passes the authoritative Verification workflow
      (run 34734509163, all jobs) + CRM (34734509074).
- [x] Clean repository/working state; local checkout fast-forwarded to main
      content.
- [x] Final main SHA recorded: `bc930a2` (content), advanced only by this
      docs-only report.
- [x] Every branch and PR dispositioned and documented (§§2, 3, 5).
