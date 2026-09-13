# TOEFL House — Engineering Operating Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-13  
**Purpose:** Single operational control plane for current state, active domain, agent execution, evidence semantics and documentation governance.

> This is the one document an agent uses to determine **what may be worked on now, what is blocked, and what evidence is required before closure**. It deliberately does not hard-code a verification run number or commit SHA; those change whenever the repository changes.

## 1. Current control state

- **Authoritative branch:** `main`
- **Active domain:** CRM / Front Office — **SELECTED, NOT STARTED**
- **Active domain status:** **PLANNED / BLOCKED on baseline confirmation.** Selected by the fresh reassessment of `DOMAIN-REGISTRY.md` §2 recorded in this reconciliation. No CRM implementation, migration, test, route or surface change has begun under this selection, and none may until the baseline gate in §4 is satisfied against the actual current `main` HEAD.
- **Closed predecessors:** Library & Resources (`docs/AUDIT-2026-09-12-LIBRARY-RESOURCES-CLOSURE.md`), Documents & Evidence (`docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`) and Privacy & Consent (`docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md`) are each **VERIFIED and CERTIFIED**, and none was reopened by this reconciliation. Privacy closed through PR #29 (merge commit `27b96d8`) with Verification run 34744897784 green on all four jobs and the Privacy browser journey at 38/38; its findings ledger and per-dimension evidence chain remain in its own audit and are not restated here.
- **Next allowed domain:** CRM / Front Office, and only once the §4 baseline gate is recorded green at the then-current `main` HEAD. The one-domain rule stays strictly enforced: no second material domain may start, and no closed domain may be reopened, while CRM / Front Office is active.
- **Release status:** **CERTIFIED for `main` HEAD `b824efd`** — Verification run 34745363765 (all four jobs: static analysis, backend, frontend, browser E2E) and CRM Browser E2E run 34745363720 are the latest non-superseded evidence for that exact commit. PR #30 produced `b824efd` and was documentation-only: the compare against `27b96d8` touches four Markdown files and no source, test, migration, workflow or route file. The certification was therefore re-earned by execution at the new HEAD, not inherited from the old one. Certification is attached to the commit, not to the repository: any later commit on `main` — including the merge of this reconciliation — requires fresh applicable verification before it can be called certified.

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

**The Privacy & Consent gate is resolved. The CRM / Front Office gate is selected but not yet open.**

Privacy & Consent was declared closed only after current-main evidence proved its applicable backend lifecycle, database invariants, authorization/scope, API, React parity, UX states, audit/provenance, idempotency, concurrency, unit/feature/integration tests, browser/E2E, security/adversarial and documentation gates — plus the three that are specific to personal data: consent is a lifecycle fact and never a boolean flag, erasure is revocation/expiry/archive over retained evidence and never a row deletion, and an organization-wide release needs two distinct approver signatures with its requester excluded and execution decided at organization scope.

That gate stays resolved. PR #29 merged the closure, Verification run 34744897784 passed every job at merge commit `27b96d8`, and the documentation-only PR #30 that produced the current HEAD `b824efd` was itself verified green by Verification run 34745363765 with CRM Browser E2E run 34745363720 alongside. Do not reopen Privacy except on evidence of regression observed at the current `main` HEAD.

### Baseline gate — mandatory before any CRM work

No CRM or Front Office implementation, migration, test, route or surface change may begin until **all** of the following hold at the *actual current* `main` HEAD:

1. **Verification** has been executed at that exact commit and all four jobs — static analysis, backend (migrations, suite, invariants, concurrency), frontend (typecheck, build, mount), and browser E2E (real Chromium) — concluded green;
2. **CRM Browser E2E** has been executed at that same exact commit and concluded green;
3. the resulting green baseline is **recorded here with its run identifiers and commit SHA**.

Merging this reconciliation advances `main` and therefore lapses the `b824efd` certification. Steps 1–3 must be re-executed against the resulting merge commit; `b824efd` evidence does not carry forward, because `GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`. Both workflows trigger on `push` to `main`, so the runs exist automatically — the obligation is to read them at the exact SHA and record them, not to assume them.

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

The next agent starts here and inspects the actual current `main` HEAD rather than trusting any SHA written below. As of this reconciliation: Library & Resources, Documents & Evidence and Privacy & Consent are closed and certified and must not be reopened absent observed regression; `b824efd` is the last commit carrying green Verification (run 34745363765, all four jobs) and CRM Browser E2E (run 34745363720); and **CRM / Front Office is the selected next material domain and is not started**.

The next-domain reassessment this handoff previously waited on has now been performed and is recorded in `DOMAIN-REGISTRY.md` §2–§3, so the selection is not to be re-litigated. What remains gating is execution order, not choice: satisfy the §4 baseline gate against the current `main` HEAD, record the green baseline here with its run identifiers, and only then begin CRM / Front Office closure. Keep the one-domain rule strictly enforced throughout — one material domain, closed completely, before the next.

Update the canonical control documents after every material change. Historical audits are never the execution authority.
