# TOEFL House Documentation System

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-09

This repository uses a **single-source documentation architecture**. Each subject has exactly one canonical document. Current status, runtime evidence and agent instructions must not be duplicated across competing files.

## Canonical control plane

| Document | Sole responsibility | Status |
|---|---|---|
| `OPERATING-CONTROL.md` | Current HEAD, active domain, execution protocol, handoff, documentation governance | ACTIVE / CANONICAL |
| `MASTER_ENGINEERING_CONTRACT.md` | Permanent project constitution and business/engineering principles | ACTIVE / CANONICAL |
| `02-TARGET-ARCHITECTURE.md` | Accepted architecture and system boundaries | ACTIVE / CANONICAL |
| `DOMAIN-REGISTRY.md` | Domain ownership, maturity, backend↔frontend parity, requirements and closure gaps | ACTIVE / CANONICAL |
| `RUNTIME-RELEASE.md` | Runtime, verification, evidence semantics and release certification | ACTIVE / CANONICAL |
| `04-DATA-AUTHORITY-PROVENANCE.md` | Data ownership, provenance and correction doctrine | ACTIVE / CANONICAL |
| `05-SECURITY-RBAC-GOVERNANCE.md` | Authorization, scope, delegation and separation of duties | ACTIVE / CANONICAL |
| `06-ACADEMIC-SYSTEM.md` | Academic lifecycle authority | ACTIVE / CANONICAL |
| `07-FINANCE-PAYROLL-COMMERCIAL.md` | Finance/payroll/commercial authority | ACTIVE / CANONICAL |
| `08-PEOPLE-HR-CRM-OPERATIONS.md` | People, HR, CRM and operational domain rules | ACTIVE / CANONICAL |
| `09-REPORTING-INTEGRATION-PROJECTIONS.md` | Reporting, projections and integration boundaries | ACTIVE / CANONICAL |
| `10-FRONTEND-API-UX.md` | Frontend/API/UX boundary | ACTIVE / CANONICAL |
| `11-PRIVACY-AUDIT-DOCUMENTS.md` | Privacy, audit and documents authority | ACTIVE / CANONICAL |
| `12-OPERATIONS-DEPLOYMENT-DR.md` | Deployment, operations and disaster recovery | ACTIVE / CANONICAL |
| `13-TESTING-QUALITY-RELEASE.md` | Test/quality contract; current runtime/release conclusions defer to `RUNTIME-RELEASE.md` | ACTIVE / CANONICAL |
| `16-DECISION-REGISTER.md` | Current architecture/governance decision index | ACTIVE / CANONICAL |
| `CODEBASE_HYGIENE_AND_STANDARDS.md` | Cleanup, coding and repository hygiene | ACTIVE / CANONICAL |
| `CANONICAL_TERMINOLOGY.md` | Canonical vocabulary | ACTIVE / CANONICAL |

## Authority map

Use exactly one source for each subject:

`Operating state → OPERATING-CONTROL.md`  
`Domain maturity/parity/requirements → DOMAIN-REGISTRY.md`  
`Runtime/verification/release → RUNTIME-RELEASE.md`  
`Architecture constitution → MASTER_ENGINEERING_CONTRACT.md`  
`Accepted architecture → 02-TARGET-ARCHITECTURE.md`  
`Material decisions → 16-DECISION-REGISTER.md`

Domain-specific documents own only their declared domain subject. They must link to, not duplicate, current state or release evidence.

## One-domain rule

**ONE MATERIAL DOMAIN AT A TIME.** The active domain must pass implementation, authority, database, security/scope, API, frontend, UX, audit/provenance, idempotency, concurrency, testing, browser/E2E and operational evidence gates before another material domain starts.

## Historical evidence

Dated audits and `history/` are immutable provenance. Mark them `HISTORICAL` and never use them as current release authority. A prior green run or certification does not certify a later `main` commit.

## Agent entry

Start with `OPERATING-CONTROL.md`, then inspect the master contract, the relevant domain authority, `DOMAIN-REGISTRY.md`, and `RUNTIME-RELEASE.md` before material implementation.
