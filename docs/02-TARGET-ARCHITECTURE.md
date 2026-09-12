# TOEFL House — System Architecture

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Architecture

Laravel 12.x modular monolith + PostgreSQL transactional backend + React/TypeScript feature frontend.

The accepted integrated enterprise architecture graph and approved architecture decisions are the detailed architecture authority. Historical source material is provenance only.

## Module map

Current top-level modules include:

`Academic, Access, Admissions, Audit, Calendar, Communication, Crm, Documents, Enrollment, Finance, Governance, Hr, Identity, Integrations, Organization, Outbox, Payroll, Privacy, Reporting, Resources, Scheduling, Students, WorkManagement, Workspace`.

## Boundary rules

- Controllers/routes are transport adapters, not alternate business authorities.
- Commands perform governed mutations.
- Queries/projections are read-side only.
- Cross-domain calls use explicit application/domain contracts.
- Integration events describe committed facts; they do not become authorities.
- Reporting is read-only with respect to business truth.
- Workspace composes canonical capabilities; it does not own them.
- Frontend visibility is not authorization.

## Transaction model

Critical mutations use explicit transactions and appropriate row/database locking. PostgreSQL constraints are used where they provide durable invariant enforcement.

## Lifecycle model

Every stateful aggregate has a guarded transition graph and a single transition authority. Coarse UI status is derived from domain state.

## Target-state gaps

The accepted target still calls for completion of remaining React migration, richer replay/dead-letter and external integration operations, selected workflow boundary extraction, and additional product breadth. Current maturity and active closure work are owned by `docs/DOMAIN-REGISTRY.md` and `docs/OPERATING-CONTROL.md`.

## Source evidence

- `docs/MASTER_ENGINEERING_CONTRACT.md`
- `docs/16-DECISION-REGISTER.md`
- `docs/DOMAIN-REGISTRY.md`
- `docs/RUNTIME-RELEASE.md`
- `docs/history/02-architecture-history.md` — historical provenance only; it is retained temporarily because the decision register still uses it for full decision-text provenance.

---

# Consolidated Governing Evidence

