# TOEFL House — Canonical Documentation

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reconciled:** 2026-09-09

This repository follows a **single-source documentation architecture**. A current subject has one owner document. Navigation files do not define policy, release status, maturity, or execution evidence.

## Current control plane

| Subject | Sole authority |
|---|---|
| Current execution state, active domain, agent protocol, handoff, documentation governance | `OPERATING-CONTROL.md` |
| Permanent engineering/business constitution | `MASTER_ENGINEERING_CONTRACT.md` |
| Accepted system architecture and boundaries | `02-TARGET-ARCHITECTURE.md` |
| Domain ownership, maturity, parity, requirements and closure gaps | `DOMAIN-REGISTRY.md` |
| Runtime, verification, evidence and release certification | `RUNTIME-RELEASE.md` + `.github/workflows/verification.yml` |
| Data ownership, provenance and correction doctrine | `04-DATA-AUTHORITY-PROVENANCE.md` |
| Authorization, scope, delegation and separation of duties | `05-SECURITY-RBAC-GOVERNANCE.md` |
| Academic lifecycle contract | `06-ACADEMIC-SYSTEM.md` |
| Finance/payroll/commercial authority | `07-FINANCE-PAYROLL-COMMERCIAL.md` |
| CRM/workspace/communication domain rules | `08-PEOPLE-HR-CRM-OPERATIONS.md` |
| Reporting/projections/integrations boundaries | `09-REPORTING-INTEGRATION-PROJECTIONS.md` |
| Frontend/API/UX boundary | `10-FRONTEND-API-UX.md` |
| Privacy/audit/documents authority | `11-PRIVACY-AUDIT-DOCUMENTS.md` |
| Material decision index | `16-DECISION-REGISTER.md` |
| Coding/repository hygiene | `CODEBASE_HYGIENE_AND_STANDARDS.md` |
| Canonical vocabulary | `CANONICAL_TERMINOLOGY.md` |
| Executable production deployment/recovery procedure | `operations/production-deployment.md` |

## Operating rule

**ONE MATERIAL DOMAIN AT A TIME.** The active domain must pass all materially applicable implementation, authority, database, authorization, scope, API, React, UX, audit/provenance, idempotency, concurrency, testing, browser/E2E, security and operational gates before another material domain starts.

`IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`.  
`DOCUMENTED ≠ EXECUTED`.  
`GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`.

## Historical evidence

`history/` contains preserved provenance from earlier execution lines. Historical material may explain how the system evolved but never overrides the current control plane. The former compliance snapshot is preserved there under its dated evidence name.

## Agent entry

Start with `OPERATING-CONTROL.md`, then inspect the master contract, the relevant domain authority, `DOMAIN-REGISTRY.md`, and `RUNTIME-RELEASE.md`. Do not create a new document when an existing canonical owner already covers the subject.
