# TOEFL House — Engineering Operating Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-13  
**Purpose:** Single operational control plane for current state, active domain, agent execution, evidence semantics and documentation governance.

> This is the one document an agent uses to determine **what may be worked on now, what is blocked, and what evidence is required before closure**. It deliberately does not hard-code a verification run number or commit SHA; those change whenever the repository changes.

## 1. Current control state

- **Authoritative branch:** `main`
- **Active domain:** Human Resources (HR)
- **Active domain status:** **VERIFIED on PR #34 / branch `arena/01a09a1b-toefl-house`** — all HR closure gates were executed and observed green on branch `arena/01a09a1b-toefl-house` (Verification run 34768179272), with CRM Browser E2E run 34768179209 green alongside. The findings ledger and the per-dimension evidence chain are in `docs/AUDIT-2026-09-13-HR-CLOSURE.md`. PR #34 is open, fully verified, and merge-ready.
- **Closed predecessors:** Library & Resources (`docs/AUDIT-2026-09-12-LIBRARY-RESOURCES-CLOSURE.md`), Documents & Evidence (`docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`), and Privacy & Consent (`docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md`) are **VERIFIED** and certified on `main`.
- **Next allowed domain:** none yet. HR closure is verified on PR #34 and pending merge to `main`. Following merge, a fresh reassessment of `DOMAIN-REGISTRY.md` §2 will select the next material domain.
- **Release status:** **CERTIFIED for `main` HEAD `eb67300`** (pre-HR merge baseline) / **VERIFIED on PR #34 branch HEAD** (Verification run 34768179272 all 4 jobs green, CRM Browser E2E run 34768179209 green).

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

**Human Resources (HR) was the active material domain, and it is closed.**

It was declared closed after branch HEAD evidence (Verification run 34768179272) proved its applicable backend lifecycle, database invariants (49/49 runtime probes including 13 HR-specific schema constraints/triggers), authorization/scope, canonical API (`HrApiFeatureTest` 18/18), React parity, UX states, audit/provenance, idempotency, concurrency (`HrConcurrencyTest` 7/7), unit/feature/integration tests, real Chromium browser E2E (`verify:browser:hr`), security/adversarial and documentation gates — including the separation of duties for contract approvals (preparer and beneficiary independence) and leave decisions (independent decider).

The gate is resolved on PR #34. Merge PR #34 to `main` and execute the certification run on `main` HEAD. Do not begin another material domain until a fresh reassessment of `DOMAIN-REGISTRY.md` §2 selects one and this section names it.

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

The next agent starts here, inspects current `main`, treats Human Resources (HR) as closed and verified on PR #34, and may select the next material domain only through a fresh reassessment of `DOMAIN-REGISTRY.md` §2 recorded here. Update the canonical control documents after every material change. Historical audits are never the execution authority.
