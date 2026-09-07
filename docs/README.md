# TOEFL House Documentation — Start Here

**STATUS: CURRENT CANONICAL INDEX — NORMATIVE**

This is the single human/AI entry point to the TOEFL House engineering knowledge system. Read the canonical set before consulting history.

## Authority chain

1. [`MASTER_ENGINEERING_CONTRACT.md`](MASTER_ENGINEERING_CONTRACT.md) — permanent constitution.
2. [`01-ULTIMATE-GOAL.md`](01-ULTIMATE-GOAL.md) — North Star / final destination.
3. [`02-TARGET-ARCHITECTURE.md`](02-TARGET-ARCHITECTURE.md) — accepted architecture.
4. [`03-DOMAIN-CAPABILITY-MODEL.md`](03-DOMAIN-CAPABILITY-MODEL.md) — business/domain ownership and capability state.
5. [`04-DATA-AUTHORITY-PROVENANCE.md`](04-DATA-AUTHORITY-PROVENANCE.md) — source of truth and provenance.
6. [`05-SECURITY-RBAC-GOVERNANCE.md`](05-SECURITY-RBAC-GOVERNANCE.md) — authorization, scope, delegation and SoD.
7. [`06-ACADEMIC-SYSTEM.md`](06-ACADEMIC-SYSTEM.md) — academic lifecycle.
8. [`07-FINANCE-PAYROLL-COMMERCIAL.md`](07-FINANCE-PAYROLL-COMMERCIAL.md) — financial and settlement truth.
9. [`08-PEOPLE-HR-CRM-OPERATIONS.md`](08-PEOPLE-HR-CRM-OPERATIONS.md) — people, HR, CRM, workspace and communication.
10. [`09-REPORTING-INTEGRATION-PROJECTIONS.md`](09-REPORTING-INTEGRATION-PROJECTIONS.md) — projections, reporting, events and integration.
11. [`10-FRONTEND-API-UX.md`](10-FRONTEND-API-UX.md) — frontend/API boundary and UX policy.
12. [`11-PRIVACY-AUDIT-DOCUMENTS.md`](11-PRIVACY-AUDIT-DOCUMENTS.md) — privacy, document and audit governance.
13. [`12-OPERATIONS-DEPLOYMENT-DR.md`](12-OPERATIONS-DEPLOYMENT-DR.md) — operations, deployment and recovery.
14. [`13-TESTING-QUALITY-RELEASE.md`](13-TESTING-QUALITY-RELEASE.md) — QA and release gates.
15. [`14-CURRENT-STATE-ROADMAP.md`](14-CURRENT-STATE-ROADMAP.md) — current implementation, target gaps and priorities.
16. [`15-REQUIREMENT-TRACEABILITY.md`](15-REQUIREMENT-TRACEABILITY.md) — requirement traceability.
17. [`16-DECISION-REGISTER.md`](16-DECISION-REGISTER.md) — decision authority index.
18. [`CODEBASE_HYGIENE_AND_STANDARDS.md`](CODEBASE_HYGIENE_AND_STANDARDS.md) — repository coding, cleanup, compatibility, naming, comment, testing and runtime-readiness standard.
19. [`CANONICAL_TERMINOLOGY.md`](CANONICAL_TERMINOLOGY.md) — canonical domain vocabulary and semantic distinctions.
20. [`RUNTIME_VERIFICATION_HANDOFF.md`](RUNTIME_VERIFICATION_HANDOFF.md) — pre-runtime state, blockers, verification order and handoff evidence template.
21. [`ai/00-AI-ENTRYPOINT.md`](ai/00-AI-ENTRYPOINT.md) — mandatory AI engineering entry point.

## Authority chain for implementation work

For implementation/cleanup work, apply the canonical architecture and data/security decisions first, then the repository-wide hygiene and terminology standards. The hygiene and terminology standards never override a domain, security, database, or release decision.

## Three-state rule

**CURRENT STATE** = what exists now.

**TARGET STATE** = what the approved product/architecture intends to become.

**RELEASE STATE** = what has been runtime-proven and certified safe to release.

Never treat a target capability as implemented merely because it is documented. Never treat implementation as release-certified without runtime evidence.

## Terminology rule

Use `CANONICAL_TERMINOLOGY.md` for current domain names. New synonyms for an existing concept require an explicit semantic distinction or an approved external-compatibility reason. Historical wording may remain only when needed to preserve historical truth.

## Runtime handoff rule

`RUNTIME_VERIFICATION_HANDOFF.md` is the canonical starting point for the next runtime-verification agent. It records the evidence boundary and must be updated with real command/output evidence rather than inferred success.

## Where to go next

- Humans: read the numbered canonical documents in order, then `CODEBASE_HYGIENE_AND_STANDARDS.md` and `CANONICAL_TERMINOLOGY.md`, then consult `history/` only for provenance.
- AI agents: start with `ai/00-AI-ENTRYPOINT.md`; when beginning runtime work, read `RUNTIME_VERIFICATION_HANDOFF.md` before executing or changing runtime-sensitive code.
- Operators: use `12-OPERATIONS-DEPLOYMENT-DR.md` and `SETUP.md` for the actual deployment procedure.
- Auditors: use `13-TESTING-QUALITY-RELEASE.md`, `15-REQUIREMENT-TRACEABILITY.md`, `16-DECISION-REGISTER.md`, `CANONICAL_TERMINOLOGY.md`, and the evidence sources they point to.

## Historical archive

`history/` is evidence only. Historical sources have been consolidated into four volumes so that engineers do not need to reconstruct project history from dozens of overlapping files.
