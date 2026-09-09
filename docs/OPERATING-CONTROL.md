# TOEFL House — Engineering Operating Control

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-09  
**Purpose:** Single operational control plane for current state, active domain, agent execution, evidence semantics and documentation governance.

> This is the one document an agent uses to determine **what may be worked on now, what is blocked, and what evidence is required before closure**. Architecture subject matter remains in the architecture documents; domain maturity remains in `DOMAIN-REGISTRY.md`; runtime/release evidence remains in `RUNTIME-RELEASE.md`.

## 1. Current control state

- **Authoritative branch:** `main`
- **Current HEAD:** `99c2b70b944e4365349e6c9ce25eb9c66d825af0`
- **Active domain:** Library & Resources
- **Active domain status:** IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED
- **Current Verification:** run #457 / workflow id `34368019057` — QUEUED for the current HEAD at the time of this reconciliation.
- **Next allowed domain:** Documents, but only after Library is closed and a fresh reassessment is performed.
- **Release status:** NOT CURRENTLY CERTIFIED; the current HEAD must be proven by the latest non-superseded verification evidence.

Never convert `QUEUED`, `IN PROGRESS`, `STATICALLY VERIFIED`, historical evidence, or documentation claims into `VERIFIED` or `RELEASE CERTIFIED`.

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

It may be declared closed only after current-main evidence proves its applicable:

- backend lifecycle and database invariants;
- authorization and organization/campus/branch/class/own scope behavior;
- API and canonical command paths;
- React parity and workspace mounting;
- UX for loading, empty, invalid, forbidden, pending and irreversible actions;
- audit/provenance, idempotency and concurrency behavior;
- unit/feature/integration tests;
- browser/E2E behavior against the locked runtime;
- security/adversarial behavior;
- documentation consistency.

Do not begin Documents while this gate is unresolved.

## 5. Security and adversarial checklist

For every applicable workflow, explicitly test:

- organization isolation;
- campus scope;
- branch scope;
- class scope;
- own-record/own-action scope;
- manager cross-branch denial;
- valid campus-scoped manager access;
- owner/global access where authorized;
- teacher/employee own-record access and cross-record denial;
- unauthorized mutation denial;
- invalid lifecycle transition denial;
- separation-of-duties and self-approval rules;
- idempotency conflict/replay behavior;
- concurrency/race behavior;
- auditability of successful and rejected sensitive actions.

A UI hiding a control is never a substitute for server authorization.

## 6. Frontend operating rules

- `resources/js/core/api.ts` is the canonical transport client.
- `resources/js/core/navigation.ts` is the canonical navigation registry.
- React owns presentation, interaction and local UI state only.
- Backend/domain commands own business truth, authorization, scope, lifecycle, accounting, audit, idempotency and concurrency.
- Do not recreate server lifecycle or accounting rules in React.
- Consequential actor choices must be explicit; never silently use the first record returned by the API.
- Irreversible actions require an explicit confirmation and, where required, server-driven workflow stages.
- Blade is a transport/compatibility boundary; new normal operational work belongs in React unless a specific exception is approved.

## 7. Agent execution protocol

Before changing code:

1. read this document;
2. read `MASTER_ENGINEERING_CONTRACT.md`;
3. read the relevant architecture/domain authority document;
4. read `DOMAIN-REGISTRY.md` for current maturity and the selected workflow;
5. read `RUNTIME-RELEASE.md` before interpreting verification or release state;
6. inspect the current `main` HEAD and actual source/tests before trusting documentation;
7. identify the single active domain and the exact closure gap;
8. make the smallest coherent change that preserves established authorities;
9. run the strongest applicable verification;
10. update this control document and the domain registry in the same work package.

A material change may invalidate older evidence. The current commit controls current truth.

## 8. Forbidden behavior

Never:

- invent a business authority or duplicate an existing one;
- weaken a correct invariant, security check or test to obtain green output;
- create fake migrations or guessed database baselines;
- add secrets or sensitive logs;
- perform blind whole-file rewrites from stale content;
- claim runtime verification that was not executed;
- start the next material domain early;
- preserve duplicate documentation merely because it is convenient;
- silently turn historical evidence into current status.

## 9. Documentation operating model

The repository uses **one source of truth per subject**:

| Subject | Canonical document |
|---|---|
| Project constitution, architecture principles and permanent business rules | `MASTER_ENGINEERING_CONTRACT.md` |
| Accepted target architecture | `02-TARGET-ARCHITECTURE.md` |
| Domain maturity, backend/frontend parity and requirement status | `DOMAIN-REGISTRY.md` |
| Current execution state, active domain and agent rules | `OPERATING-CONTROL.md` |
| Runtime, verification, testing and release evidence rules | `RUNTIME-RELEASE.md` |
| Material architecture/governance decisions | `16-DECISION-REGISTER.md` |
| Code cleanup and repository standards | `CODEBASE_HYGIENE_AND_STANDARDS.md` |
| Canonical vocabulary | `CANONICAL_TERMINOLOGY.md` |
| Domain-specific authoritative contracts | the relevant domain document only |

No document may restate another document's full subject. Cross-document references should point to the authority instead.

## 10. Completion formula

A material domain is `DONE` only when:

`Implementation + Authority + DB + Authorization + Scope + API + React + UX + Audit/Provenance + Idempotency + Concurrency + Tests + Browser/E2E + Security + Operational Evidence + Documentation = PASS`

`IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`.

`GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`.

`DOCUMENTED ≠ EXECUTED`.

## 11. Handoff requirement

The next agent must start from this document, not from an old audit or a remembered task list. It must inspect current `main`, continue Library closure only, and update the canonical status/evidence documents after every material change.
