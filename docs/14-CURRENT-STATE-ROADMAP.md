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
- repository-wide hygiene and standards policy is now canonical
- runtime/setup documentation distinguishes preparation from runtime evidence
- frontend has an explicit TypeScript typecheck command

## Partially achieved / target gaps

- remaining interactive Blade migration and retirement of compatible web POST adapters once external/current consumers are migrated
- richer reporting snapshots/replay
- dead-letter/replay operational tooling
- richer notification and workspace operations
- selected scheduling/enrollment boundary refinement
- donor/aid, bank reconciliation, procurement/AP and inventory/asset breadth
- student/employee portal breadth
- production observability/alerting depth
- accessibility/performance evidence depth (browser E2E was executed 2026-09-08; accessibility and realistic-volume performance evidence remain open)
- physical PostgreSQL schema-baseline consolidation and independent schema equivalence proof (the full chain now replays 185/185 on the locked runtime; the consolidation decision itself remains runtime-gated behind a schema freeze)

## Release blockers

**No runtime-certification blocker remains.** The full repository-wide gate chain (environment lock, static gates, 185-migration replay, complete suite, invariants, concurrency, frontend build/mount, and three real-HTTP business journeys) was executed on the locked runtime, and the system is certified production-ready at commit `96925d3` — see `AUDIT-2026-09-09-FINAL-CERTIFICATION.md` (the current release authority) for the exact evidence boundary, including the items carried forward from the 2026-09-08 line (browser E2E, deployment/DR rehearsal) and the honest environment limitations.

The database baseline decision is separately runtime-gated. No guessed baseline or fake migration state is acceptable.

## Priority order

Items 1–2 are **complete** (2026-09-09): the official runtime was established via `scripts/runtime/provision.sh`, every verification gate executed, and the runtime defects discovered there (first-run genesis structure dead end; journey-script contract drift) were fixed and pinned by tests. They are retained below for provenance.

1. Establish the official runtime environment and execute the verification gates.
2. Resolve any runtime defects discovered there.
3. Freeze and independently verify the PostgreSQL schema baseline before any historical migration-chain removal.
4. Complete deterministic frontend dependency/release process.
5. Complete approved target-state capabilities in dependency order.
6. Migrate/retire compatibility boundaries when real consumers are proven migrated.
7. Keep documentation synchronized as implementation evolves.

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
| Operations / DR | CURRENT | Hardened deployment and recovery procedures exist; deployment-rehearsal and DR-drill evidence was executed 2026-09-08 and carried forward by the 2026-09-09 certification; periodic re-drills remain operational policy. |
| Codebase hygiene | CURRENT | Standards, naming, comment, compatibility and cleanup policy are canonical; all gates executed on the official locked runtime and certified 2026-09-09. |

## Release-state separation

The current implementation may be structurally complete in an area while remaining **RUNTIME UNVERIFIED**. This is an evidence state, not an implementation defect. Current release status remains governed by `13-TESTING-QUALITY-RELEASE.md` and the runtime certification protocol.