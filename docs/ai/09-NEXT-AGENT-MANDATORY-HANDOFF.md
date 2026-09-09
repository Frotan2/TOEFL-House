# TOEFL House — Next-Agent Mandatory Handoff

**STATUS: CURRENT CANONICAL — MANDATORY / BLOCKING**  
**Authority branch:** `main`  
**Effective state:** 2026-09-09  
**Purpose:** prevent the next engineering agent from trusting stale certification claims, skipping domain re-evaluation, or moving forward before the active domain is genuinely closed.

## 1. Non-negotiable operating rule

Work exactly one material domain at a time.

A domain is **NOT DONE** until its backend authority, database integrity, lifecycle, authorization/scope, API, React surface, UX/error states, tests, browser/runtime behavior, security, audit/evidence, and documentation are all reconciled and verified as applicable.

Do not move to the next domain because the UI looks complete, endpoints exist, unit tests pass, or a previous report says the domain is complete.

## 2. Repository authority

- `main` is the authoritative implementation branch.
- `docs/MASTER_ENGINEERING_CONTRACT.md` is the project constitution.
- `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md` is the current user-facing parity contract.
- `docs/14-CURRENT-STATE-ROADMAP.md` is the current planning/status register.
- `docs/RUNTIME_ENVIRONMENT_LOCK.md` is the runtime version authority.
- `docs/RUNTIME_VERIFICATION_HANDOFF.md` and the latest Verification workflow run are runtime evidence; claims must come from actual evidence, not prose.
- Historical audit/certification documents are evidence only unless explicitly declared current by the canonical index.

## 3. Current release-state warning

The repository contains an older 2026-09-09 certification document that states production readiness at commit `96925d3`. That claim must **not** be treated as current release certification for the present `main` head.

The latest active verification chain is the authoritative state for release gating. At handoff creation, Verification run **#442 / run `34366532471`** is still the active verification line. Frontend has passed; Static, Backend and Browser E2E remain subject to their actual final conclusions. No agent may infer their result.

## 4. Current domain re-evaluation

### Covered / structurally strong

- Organization / scope
- Identity
- Library & Resources

### Partial / still requiring domain-by-domain closure

- Access / RBAC operational breadth
- Applicants & Students
- CRM / Front Office
- Academic
- Placement
- Teachers
- HR
- Finance
- Payroll / settlement
- Documents
- Privacy
- Audit
- Communication
- Reporting
- Management / Work

### Backend-only / operator-oriented

- Integrations and transport infrastructure
- queue workers, retries, schedules
- projections/materialization internals
- audit/event/outbox internals
- database/migration/constraint machinery
- accounting/ledger authority
- concurrency/idempotency enforcement

These are not to be duplicated in React merely for visual parity.

## 5. Library & Resources closure boundary

Library & Resources has materially converged in implementation:

- books/copies and circulation
- issue/return/loss
- assets and custody
- staged disposal request → independent approval → execution
- facilities work request → approval → start → completion/cancellation
- explicit borrower/custodian selection
- execution-date capture for irreversible disposal
- evidence references where required
- server-authoritative scope, lifecycle, audit, idempotency and approval separation

However, the domain is not release-certified until the current Verification line proves the repository state. Do not mark it `RELEASE CERTIFIED` from implementation inspection alone.

## 6. Required reassessment for every Partial domain

For the selected domain, produce a workflow inventory first. For every workflow, record:

`Requirement → Authority → State machine → DB invariants → Command/service → Authorization → Scope → API → React surface → UX states → Audit/provenance → Idempotency → Concurrency → Tests → Browser/E2E → Documentation`

Classify each workflow as:

- `COVERED`
- `PARTIAL`
- `MISSING`
- `BACKEND-ONLY`
- `DEFERRED / EXPLICITLY APPROVED`

Endpoint count is not a parity metric.

## 7. Security and scope must be adversarially checked

For every domain, explicitly test:

- organization isolation
- campus isolation
- branch isolation
- class/record ownership where applicable
- own-scope vs cross-scope access
- manager cross-branch denial
- campus-scoped manager access
- owner/global access
- teacher/employee own-record restrictions where applicable
- unauthorized mutation
- invalid lifecycle transition
- approval self-approval and separation-of-duties violations
- idempotency replay and conflicting replay
- concurrent duplicate operation

A UI hiding a button is never a security control.

## 8. Frontend rules

React is an operational client of authoritative backend behavior.

Use the canonical transport at `resources/js/core/api.ts`.
Do not create duplicate API clients.
Do not copy server business rules into React.
Do not fabricate local lifecycle state that can become authoritative.
Do not use implicit first-record selections for consequential actors.
Do not allow irreversible actions without explicit state gating and confirmation.
Every specialist entrypoint must remain compatible with the canonical navigation/mount architecture.
Blade is a legacy/transport boundary; new operational work belongs in React unless an explicit exception exists.

## 9. Verification rules

A successful static or unit-test job does not certify browser behavior.
A successful browser mount does not certify business correctness.
A historical report does not certify the current commit.
A green run that is superseded does not certify the new commit.

When a verification run fails:

1. capture the exact failure;
2. determine whether it is product, harness, documentation, environment, or stale-evidence failure;
3. fix only the confirmed root cause;
4. rerun the affected gates;
5. inspect the complete new evidence;
6. update the canonical documentation;
7. only then declare the domain state.

## 10. Documentation is part of the implementation

After every material domain change, update the affected canonical documents in the same work package.

At minimum review:

- `docs/README.md`
- `docs/14-CURRENT-STATE-ROADMAP.md`
- `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
- `docs/16-DECISION-REGISTER.md` when a governed decision changed
- the relevant domain specification
- relevant AI governance/handoff documents
- runtime handoff/evidence documents when runtime state changed

Never leave a known stale `CURRENT`, `COMPLETE`, `CERTIFIED`, or `PRODUCTION-READY` claim in a canonical document.

## 11. Forbidden shortcuts

- No mass rewrite of a large controller from memory.
- No blind file replacement using stale blob content.
- No weakening tests to make a gate green.
- No deletion of historical evidence merely to remove contradictions.
- No invented migration baseline.
- No bypass of domain commands for convenience.
- No credentials in source or logs.
- No fabricated runtime success.
- No declaring `100%` or `PRODUCTION READY` without current release evidence.

## 12. Definition of done

The active domain may be closed only when:

`Implementation + Parity + Security/Scope + Tests + Runtime/E2E + UX + Documentation + Evidence = PASS`

and there is no unresolved material defect that belongs to that domain.

Only after closure may the next domain begin.
