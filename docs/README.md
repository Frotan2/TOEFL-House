# TOEFL House Documentation — Start Here

**STATUS: CURRENT CANONICAL INDEX — NORMATIVE**  
**Authority branch:** `main`  
**Last reconciled:** 2026-09-09

This is the single human/AI entry point to the TOEFL House engineering knowledge system. Read the canonical set before consulting historical evidence.

## Mandatory authority chain

1. `MASTER_ENGINEERING_CONTRACT.md` — permanent engineering constitution.
2. `01-ULTIMATE-GOAL.md` — North Star.
3. `02-TARGET-ARCHITECTURE.md` — accepted architecture.
4. `03-DOMAIN-CAPABILITY-MODEL.md` — domain ownership and capability model.
5. `04-DATA-AUTHORITY-PROVENANCE.md` — source-of-truth and provenance doctrine.
6. `05-SECURITY-RBAC-GOVERNANCE.md` — authorization, scope, delegation and SoD.
7. `06-ACADEMIC-SYSTEM.md` — academic lifecycle authority.
8. `07-FINANCE-PAYROLL-COMMERCIAL.md` — financial authority.
9. `08-PEOPLE-HR-CRM-OPERATIONS.md` — people, HR, CRM, operations.
10. `09-REPORTING-INTEGRATION-PROJECTIONS.md` — reporting, projections, events and integrations.
11. `10-FRONTEND-API-UX.md` — frontend/API/UX boundary.
12. `11-PRIVACY-AUDIT-DOCUMENTS.md` — privacy, documents and audit governance.
13. `12-OPERATIONS-DEPLOYMENT-DR.md` — deployment and recovery.
14. `13-TESTING-QUALITY-RELEASE.md` — verification and release gates.
15. `14-CURRENT-STATE-ROADMAP.md` — **current domain/state register**.
16. `15-BACKEND-FRONTEND-PARITY-AUTHORITY.md` — **current parity contract**.
17. `15-REQUIREMENT-TRACEABILITY.md` — requirement traceability.
18. `16-DECISION-REGISTER.md` — decision authority index.
19. `CODEBASE_HYGIENE_AND_STANDARDS.md` — codebase hygiene and compatibility rules.
20. `CANONICAL_TERMINOLOGY.md` — vocabulary authority.
21. `RUNTIME_ENVIRONMENT_LOCK.md` — runtime version authority.
22. `RUNTIME_VERIFICATION_HANDOFF.md` — runtime evidence/handoff record.
23. `TESTING_STRATEGY_LOCK.md` and `TEST_SUITE_ARCHITECTURE.md` — test architecture.
24. `ai/00-AI-ENTRYPOINT.md` — mandatory AI entry point.
25. `ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md` — **blocking operating contract for the next agent**.

## Non-negotiable state model

**CURRENT STATE** = what the repository implements now.  
**TARGET STATE** = approved intended product/architecture state.  
**RELEASE STATE** = what the current authoritative commit has actually proven through the release gates.

Documentation alone never promotes a capability to RELEASE STATE.

## Current release warning

An older `AUDIT-2026-09-09-FINAL-CERTIFICATION.md` certifies a different historical execution line and commit. It must not be used to certify later `main` commits.

At the time of this reconciliation, Verification run **#442 / `34366532471`** is the current active evidence line for commit `2ed406e6ae07c1dd24f38cc88432457a460519e8`; frontend has passed, while the remaining jobs require their actual final conclusions. A superseded or in-progress run never counts as release certification.

## Current domain state

- **Covered/strong:** Organization, Identity.
- **Implementation complete but current runtime certification pending:** Library & Resources.
- **Partial:** Access/RBAC, Applicants & Students, CRM/Front Office, Academic, Placement, Teachers, HR, Finance, Payroll/settlement, Documents, Privacy, Audit, Communication, Reporting, Management/Work.
- **Backend-only/operator:** Integration transport, workers/queues/scheduling, projections/materialization internals, database/migration machinery, accounting authority, audit/outbox internals, concurrency/idempotency enforcement.

The authoritative workflow-by-workflow matrix is `15-BACKEND-FRONTEND-PARITY-AUTHORITY.md` and the current planning register is `14-CURRENT-STATE-ROADMAP.md`.

## Domain execution rule

**ONE DOMAIN AT A TIME.** Do not advance to the next material domain until the current one passes its applicable implementation, authority, database, security/scope, API, frontend, UX, testing, browser/runtime, audit/provenance and documentation gates.

For every domain, use:

`Requirement → Authority → Lifecycle → DB → Command → Authorization → Scope → API → React → UX → Audit → Idempotency → Concurrency → Tests → E2E → Documentation`

Endpoint count is not parity.

## Historical evidence

`history/` and dated audit files preserve provenance. They must remain identifiable as historical evidence. They do not silently override the current canonical documents.

## Operations

Use `12-OPERATIONS-DEPLOYMENT-DR.md`, `RUNTIME_ENVIRONMENT_LOCK.md`, `RUNTIME_VERIFICATION_HANDOFF.md`, and the root `SETUP.md` for actual runtime/deployment operations.
