# TOEFL House — Current Implementation Status / Roadmap

**STATUS: CURRENT CANONICAL — STATUS + PLANNING**

## Current state

The repository is a Laravel 12 modular monolith backed by PostgreSQL with broad domain implementation across organization, access, students/admissions, academic, placement, finance, HR/payroll, CRM, reporting, integrations, workspace and governance.

## Strongly achieved

- centralized authority model
- branch/campus/organization scope
- finance as monetary authority
- academic lifecycle authorities
- PostgreSQL business invariants
- temporal overlap protection
- idempotency/concurrency patterns
- audit and provenance controls
- React boundary plus employee/management workspace foundations
- outbox/consumer foundations

## Partially achieved / target gaps

- remaining interactive Blade migration
- richer reporting snapshots/replay
- dead-letter/replay operational tooling
- richer notification and workspace operations
- selected scheduling/enrollment boundary refinement
- donor/aid, bank reconciliation, procurement/AP and inventory/asset breadth
- student/employee portal breadth
- production observability/alerting depth
- full operational browser/accessibility/performance evidence

## Release blockers

The current documentation status must retain the runtime-certification blocker until the official runtime stack is actually available and the required gates pass.

## Priority order

1. Official-runtime certification.
2. Resolve any runtime defects discovered there.
3. Complete deterministic frontend dependency/release process.
4. Complete approved target-state capabilities in dependency order.
5. Keep documentation synchronized as implementation evolves.

Historical work-package roadmaps remain historical evidence and must not override this current-state roadmap.

## Capability state register

| Capability area | State | Notes |
|---|---|---|
| Organization / scope | CURRENT | Organization → campus → branch hierarchy and scoped authorization exist. |
| Identity / Access | CURRENT | Centralized authentication, actor state, grants, scope and approval controls. |
| Admissions / Students | CURRENT | Dedicated lifecycle and provenance controls. |
| Academic | CURRENT / TARGET GAP | Broad lifecycle implemented; selected boundary/product breadth remains. |
| Placement | CURRENT | Evidence, scoring, moderation, recommendation and release concepts exist. |
| Finance | CURRENT / STRONG | Monetary authority, payment/allocation/refund/cash/ledger/correction controls exist. |
| Payroll / settlement | CURRENT / TARGET GAP | Calculation/proposal/evidence separated from Finance recognition; broader reconciliation remains. |
| CRM / Workspace | CURRENT / TARGET GAP | Core capability exists; richer automation/SLA/portal depth remains. |
| Reporting / integration | CURRENT / TARGET GAP | Read-only projections/outbox/consumers exist; richer replay/DLQ/snapshot depth remains. |
| Frontend | CURRENT / TARGET GAP | React boundary exists; remaining interactive Blade retirement remains. |
| Operations / DR | CURRENT / TARGET GAP | Hardened deployment and recovery procedures exist; official-runtime drills remain. |

## Release-state separation

The current implementation may be structurally complete in an area while remaining **RUNTIME UNVERIFIED**. This is an evidence state, not an implementation defect. Current release status remains governed by `13-TESTING-QUALITY-RELEASE.md` and the runtime certification protocol.
