# TOEFL House — Engineering Operating Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-13  
**Purpose:** Single operational control plane for current state, active domain, agent execution, evidence semantics and documentation governance.

> This is the one document an agent uses to determine **what may be worked on now, what is blocked, and what evidence is required before closure**. It deliberately does not hard-code a verification run number or commit SHA; those change whenever the repository changes.

## 1. Current control state

- **Authoritative branch:** `main`
- **Active domain:** CRM / Front Office — **SELECTED, BASELINE CONFIRMED, IMPLEMENTATION NOT YET WRITTEN**
- **Active domain status:** **PLANNED — the §4 baseline gate is SATISFIED, so the domain is unblocked.** Selected by the fresh reassessment of `DOMAIN-REGISTRY.md` §2–§3 recorded at PR #31. The mandatory baseline gate has now been executed against the actual current `main` HEAD `eb67300` and is green (§4). No CRM implementation, migration, test, route or surface change has been written yet; the gate that blocked it is open, and closure work proceeds against the measured inventory `DOMAIN-REGISTRY.md` §3.3 (G1–G8).
- **Closed predecessors:** Library & Resources (`docs/AUDIT-2026-09-12-LIBRARY-RESOURCES-CLOSURE.md`), Documents & Evidence (`docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`) and Privacy & Consent (`docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md`) are each **VERIFIED and CERTIFIED**, and none was reopened. Privacy closed through PR #29 (merge commit `27b96d8`) with Verification run 34744897784 green on all four jobs and the Privacy browser journey at 38/38; its findings ledger and per-dimension evidence chain remain in its own audit and are not restated here.
- **Next allowed domain:** CRM / Front Office, now unblocked. The one-domain rule stays strictly enforced: no second material domain may start, and no closed domain may be reopened, while CRM / Front Office is active. CRM / Front Office must itself reach complete closure before any other domain is selected.
- **Release status:** **CERTIFIED for `main` HEAD `eb67300`** — Verification run 34750406256 (all four jobs: static analysis, backend, frontend, browser E2E) and CRM Browser E2E run 34750406270 are the latest non-superseded evidence for that exact commit. PR #31 produced `eb67300` and was documentation-only — it changed four Markdown files and no source, test, migration, workflow or route file — so this certification was again re-earned by execution at the new HEAD rather than inherited from `b824efd` (Verification run 34745363765, CRM Browser E2E run 34745363720). Certification is attached to the commit, not to the repository: any later commit on `main`, including the merge of this baseline record, requires fresh applicable verification before it can be called certified.

To determine current evidence, inspect the actual `main` HEAD and the latest non-superseded Verification workflow. Do not infer state from a previous run recorded in a document.

## 2. State vocabulary

| State | Meaning |
|---|---|
| `PLANNED` | Approved direction, not implemented. |
| `PARTIAL` | Material implementation exists but closure gates remain. |
| `IMPLEMENTATION COMPLETE` | Source and parity inspection indicate the selected scope is converged. |
| `RUNTIME-UNVERIFIED` | Implementation exists, but current authoritative runtime evidence is missing. |
| `VERIFIED` | Required evidence was actually executed and observed. |
| `RELEASE CERTIFIED` | The current authoritative commit passed all applicable release gates. |
| `BLOCKED` | A required prerequisite prevents execution. |
| `FAILED` | An executed gate failed. |
| `HISTORICAL` | Evidence for an older execution line; never current authority. |

## 3. One-domain rule

**ONE MATERIAL DOMAIN AT A TIME.**

The active domain must reach complete closure before another material domain starts. A closure decision is based on the current repository, not on a previous audit, branch, run or conversation.

Required trace for every material workflow:

`Requirement → Authority → Lifecycle → Data Model → DB Invariant → Command/Service → Authorization → Scope → API → React → UX/Error States → Audit/Provenance → Idempotency → Concurrency → Tests → Browser/E2E → Operational Evidence → Documentation`

Only materially applicable stages are required, but omissions must be explicit.

## 4. Current domain gate

**The Privacy & Consent gate is resolved. The CRM / Front Office gate is open — its mandatory baseline precondition is satisfied.**

Privacy & Consent was declared closed only after current-main evidence proved its applicable backend lifecycle, database invariants, authorization/scope, API, React parity, UX states, audit/provenance, idempotency, concurrency, unit/feature/integration tests, browser/E2E, security/adversarial and documentation gates — plus the three that are specific to personal data: consent is a lifecycle fact and never a boolean flag, erasure is revocation/expiry/archive over retained evidence and never a row deletion, and an organization-wide release needs two distinct approver signatures with its requester excluded and execution decided at organization scope.

That gate stays resolved. PR #29 merged the closure, Verification run 34744897784 passed every job at merge commit `27b96d8`, and the documentation-only PR #30 that produced the current HEAD `b824efd` was itself verified green by Verification run 34745363765 with CRM Browser E2E run 34745363720 alongside. Do not reopen Privacy except on evidence of regression observed at the current `main` HEAD.

### Baseline gate — mandatory before any CRM work

No CRM or Front Office implementation, migration, test, route or surface change may begin until **all** of the following hold at the *actual current* `main` HEAD:

1. **Verification** has been executed at that exact commit and all four jobs — static analysis, backend (migrations, suite, invariants, concurrency), frontend (typecheck, build, mount), and browser E2E (real Chromium) — concluded green;
2. **CRM Browser E2E** has been executed at that same exact commit and concluded green;
3. the resulting green baseline is **recorded here with its run identifiers and commit SHA**.

Merging the reconciliation that recorded the selection advanced `main` and lapsed the `b824efd` certification, so steps 1–3 were re-executed against the resulting merge commit rather than inherited: `b824efd` evidence did not carry forward, because `GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`. Both workflows trigger on `push` to `main`, so the runs existed automatically — the obligation was to read them at the exact SHA and record them, not to assume them.

**Gate status: SATISFIED at `main` HEAD `eb67300`.** Recorded as executed, with each run read at that exact commit:

| Step | Gate | Run | Head | Result |
|---|---|---|---|---|
| 1 | Verification — static analysis (Pint, PHPStan, audits) | **34750406256** | `eb67300` | **success** |
| 1 | Verification — backend (migrations, suite, invariants, concurrency) | **34750406256** | `eb67300` | **success** |
| 1 | Verification — frontend (typecheck, build, mount) | **34750406256** | `eb67300` | **success** |
| 1 | Verification — browser E2E (real Chromium) | **34750406256** | `eb67300` | **success** |
| 2 | CRM Browser E2E | **34750406270** | `eb67300` | **success** |
| 3 | Green baseline confirmed and recorded here | — | `eb67300` | **this record** |

Both runs were `push`-triggered on `main` at `eb673007c42abaacb047ed1d2c341b6b8bf08b73`, the merge commit of PR #31. **CRM / Front Office closure work is therefore unblocked** and may begin against `DOMAIN-REGISTRY.md` §3.3.

This gate is not permanently discharged. Recording it advances `main` again, so the certification above lapses at the merge of this record and the gate must be re-satisfied at that new HEAD before the *next* material CRM change is certified. The gate governs every material step of the closure, not only its start: it is a standing precondition, and a green baseline is a fact about one commit rather than a licence for the repository.

### CRM / Front Office closure scope

The domain is `PARTIAL` in `DOMAIN-REGISTRY.md` §2. Its backend authority is already substantially present, so closure is predominantly a **frontend-parity and browser-evidence** problem, not an authority-invention problem. `DOMAIN-REGISTRY.md` §3 carries the measured gap inventory; this section states only the gate.

Closure requires the full §3 trace across visitor capture, provenance and status lifecycle, interaction append and lineage, follow-up scheduling and terminal states, automation-rule definition/evaluation/retirement, source and campaign catalog maintenance, and conversion handoff — plus the §5 security/scope matrix, idempotency and concurrency on every CRM mutation, React parity for every route that carries a normal operator capability, UX/error states, audit/provenance, and a browser journey deep enough to assert the workflows it claims. `IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`, and a route with no reachable operator surface is a parity gap, not coverage.

## 5. Security and adversarial checklist

For every applicable workflow, explicitly test organization, campus, branch, class and own-record scope; manager cross-branch denial; valid campus-scoped manager access; owner/global access where authorized; teacher/employee own-record and cross-record behavior; unauthorized mutation; invalid lifecycle transition; separation-of-duties/self-approval; idempotency conflict/replay; concurrency/race behavior; and auditability of success and rejection.

A UI hiding a control is never a substitute for server authorization.

## 6. Frontend operating rules

- `resources/js/core/api.ts` is the canonical transport client.
- `resources/js/core/navigation.ts` is the canonical navigation registry.
- React owns presentation, interaction and local UI state only.
- Backend/domain commands own business truth, authorization, scope, lifecycle, accounting, audit, idempotency and concurrency.
- Do not recreate server lifecycle or accounting rules in React.
- Consequential actor choices must be explicit; never silently use the first record returned by the API.
- Irreversible actions require explicit confirmation and, where required, server-driven workflow stages.
- Blade is a transport/compatibility boundary; new normal operational work belongs in React unless explicitly approved.

## 7. Agent execution protocol

Before changing code: read this document; read the master contract; inspect the relevant architecture/domain authority; inspect `DOMAIN-REGISTRY.md`; inspect `RUNTIME-RELEASE.md`; inspect current `main` source/tests; identify the single active domain and exact closure gap; make the smallest coherent change; run strongest applicable verification; then update canonical status/evidence documents.

A material change may invalidate older evidence. The current commit controls current truth.

## 8. Forbidden behavior

Never invent a business authority, duplicate an existing one, weaken a correct invariant/security check/test, create fake migrations, add secrets or sensitive logs, perform blind whole-file rewrites, claim runtime verification that was not executed, start the next material domain early, or preserve duplicate current-state documentation.

## 9. Documentation operating model

| Subject | Single canonical document |
|---|---|
| Project constitution | `MASTER_ENGINEERING_CONTRACT.md` |
| Accepted architecture | `02-TARGET-ARCHITECTURE.md` |
| Domain maturity/parity/requirements | `DOMAIN-REGISTRY.md` |
| Current execution state and agent rules | `OPERATING-CONTROL.md` |
| Runtime/verification/release | `RUNTIME-RELEASE.md` |
| Material decisions | `16-DECISION-REGISTER.md` |
| Cleanup/coding hygiene | `CODEBASE_HYGIENE_AND_STANDARDS.md` |
| Vocabulary | `CANONICAL_TERMINOLOGY.md` |
| Domain-specific contract | The relevant domain document only |
| Executable deployment/recovery | `operations/production-deployment.md` |

No document may restate another document's full subject. Link to the owner instead.

New documentation is permitted only when all of the following are true: its subject has no canonical owner, its operational purpose is explicit, its evidence boundary is explicit, its owner/status is declared, and a removal/supersession condition is defined.

## 10. Completion formula

`Implementation + Authority + DB + Authorization + Scope + API + React + UX + Audit/Provenance + Idempotency + Concurrency + Tests + Browser/E2E + Security + Operational Evidence + Documentation = PASS`

`IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`.

`GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`.

`DOCUMENTED ≠ EXECUTED`.

## 11. Handoff

The next agent starts here and inspects the actual current `main` HEAD rather than trusting any SHA written below. As of this record: Library & Resources, Documents & Evidence and Privacy & Consent are closed and certified and must not be reopened absent observed regression; `eb67300` is the commit whose green baseline was confirmed by Verification run 34750406256 (all four jobs) and CRM Browser E2E run 34750406270; and **CRM / Front Office is the active material domain, selected, baseline-confirmed, and not yet implemented**.

Both questions that previously gated this handoff are now answered and are not to be re-litigated. The next-domain reassessment was performed and is recorded in `DOMAIN-REGISTRY.md` §2–§3, so the *choice* is settled. The §4 baseline gate was then executed at `eb67300` and is recorded green, so the *execution order* precondition is discharged. What remains is the work itself: close CRM / Front Office against the measured inventory in `DOMAIN-REGISTRY.md` §3.3 (G1–G8), one gap at a time, re-satisfying the §4 gate at each material step because a green baseline is a fact about one commit and not a standing licence.

Keep the one-domain rule strictly enforced throughout — one material domain, closed completely, before the next. Update the canonical control documents after every material change. Historical audits are never the execution authority.
