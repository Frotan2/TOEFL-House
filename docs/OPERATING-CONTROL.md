# TOEFL House — Engineering Operating Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-09  
**Purpose:** Single operational control plane for current state, active domain, agent execution, evidence semantics and documentation governance.

> This is the one document an agent uses to determine **what may be worked on now, what is blocked, and what evidence is required before closure**. It deliberately does not hard-code a verification run number or commit SHA; those change whenever the repository changes.

## 1. Current control state

- **Authoritative branch:** `main`
- **Active domain:** Library & Resources
- **Active domain status:** **VERIFIED** — all Library closure gates were executed and observed green on branch `arena/01a09629-toefl-house` (Verification run 34713414829, head `e603db9`, plus workflow/docs-only successors). `RELEASE CERTIFIED` still requires the merge and a passing Verification run on the actual `main` HEAD.
- **Next allowed domain:** Documents, but only after Library is closed and a fresh reassessment is performed.
- **Release status:** **NOT CURRENTLY CERTIFIED** until the latest non-superseded Verification evidence for the actual `main` HEAD passes all applicable gates.

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

**Library & Resources is the only active material domain.**

It may be declared closed only after current-main evidence proves its applicable backend lifecycle, database invariants, authorization/scope, API, React parity, UX states, audit/provenance, idempotency, concurrency, unit/feature/integration tests, browser/E2E, security/adversarial and documentation gates.

Do not begin Documents while this gate is unresolved.

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

The next agent starts here, inspects current `main`, continues Library closure only, and updates the canonical control documents after every material change. Historical audits are never the execution authority.
