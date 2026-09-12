# TOEFL House — Decision Register

**STATUS: CURRENT CANONICAL — NORMATIVE INDEX**

This register is the canonical index of material architecture and governance decisions. Full historical decision text is preserved in `history/02-architecture-history.md`. The register itself is the place to determine which decisions remain current.

## Resolution rule

A decision is current only when it is consistent with the Master Engineering Contract and not explicitly superseded. Later accepted decisions govern their subject matter. Historical ADR text does not itself create current authority.

## Current decision families

| Decision | Subject | Current effect | Evidence |
|---|---|---|---|
| `2026-09-05-broad-read-authorization.md` | ADR: broad console reads require explicit root authority | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-broad-read-authorization.md` |
| `2026-09-05-class-session-branch-provenance.md` | ADR: class provenance is the canonical scope for sessions | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-class-session-branch-provenance.md` |
| `2026-09-05-employee-workspace-principle.md` | ADR: Employee Workspace is a first-class operational work environment | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-employee-workspace-principle.md` |
| `2026-09-05-enrollment-constraint-boundary.md` | ADR: Enrollment constraint boundary | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-enrollment-constraint-boundary.md` |
| `2026-09-05-finance-payroll-liability-reporting.md` | ADR: Finance recognition is required before Payroll amounts enter monetary reporting | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-finance-payroll-liability-reporting.md` |
| `2026-09-05-integrated-system-architecture-graph.md` | ADR: Integrated Enterprise System Architecture Graph | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-integrated-system-architecture-graph.md` |
| `2026-09-05-notification-projection-boundary.md` | ADR: Notification projection and event intent boundary | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-notification-projection-boundary.md` |
| `2026-09-05-production-authority-and-correction.md` | ADR: Production authority, provenance, lifecycle gates, and financial corrections | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-production-authority-and-correction.md` |
| `2026-09-05-scheduling-constraint-boundary.md` | ADR: Scheduling owns planning constraints; Academic owns delivery facts | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-scheduling-constraint-boundary.md` |
| `2026-09-05-transactional-domain-events-and-delivery.md` | ADR: transactional domain events are distinct from audit and endpoint delivery | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-transactional-domain-events-and-delivery.md` |
| `2026-09-05-unified-platform-architecture.md` | ADR: Unified TOEFL House Platform Architecture | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-unified-platform-architecture.md` |
| `2026-09-05-work-management-boundary.md` | ADR: Work Management coordinates; domains decide | **CURRENT** | `history/02-architecture-history.md` § `2026-09-05-work-management-boundary.md` |
| `2026-09-06-finance-fixed-point-money.md` | ADR: Finance accepts one fixed-point money representation | **CURRENT** | `history/02-architecture-history.md` § `2026-09-06-finance-fixed-point-money.md` |
| `2026-09-06-financial-coverage-serialization.md` | ADR: Serialize student-level Finance coverage decisions | **CURRENT** | `history/02-architecture-history.md` § `2026-09-06-financial-coverage-serialization.md` |
| `2026-09-06-payroll-held-resolution.md` | ADR: Held Payroll calculations require explicit evidenced resolution | **CURRENT** | `history/02-architecture-history.md` § `2026-09-06-payroll-held-resolution.md` |
| `decisions/2026-09-07-database-baseline-readiness.md` | ADR: Database baseline readiness and migration consolidation (deferred until schema freeze) | **CURRENT** | `decisions/2026-09-07-database-baseline-readiness.md` |
| `decisions/2026-09-09-first-run-genesis-structure.md` | ADR: First-run genesis structure lives in the guarded bootstrap; all later structure change keeps the four-actor SoD chain | **CURRENT** | `decisions/2026-09-09-first-run-genesis-structure.md` |
| `WP2-approved-decisions.md` | WP-2 — Approved Architecture Decisions | **CURRENT** | `history/02-architecture-history.md` § `WP2-approved-decisions.md` |
| `wp-academic-appeal-resolution-semantics.md` | WP-ACAD-APPEAL-RESOLVE — Appeal resolution semantics | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-appeal-resolution-semantics.md` |
| `wp-academic-branch-scope-doctrine.md` | WP-ACAD-SCOPE — Academic branch-scope doctrine | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-branch-scope-doctrine.md` |
| `wp-academic-eligibility-snapshot.md` | Academic Eligibility Snapshot — Architecture Decision (AC1) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-eligibility-snapshot.md` |
| `wp-academic-enrollment-completion.md` | Enrollment Completion Lifecycle — Architecture Decision (AC5) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-enrollment-completion.md` |
| `wp-academic-gradesheets.md` | Class Gradesheets — Architecture Decision (AC-gradesheets) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-gradesheets.md` |
| `wp-academic-graduation-integrity.md` | Graduation Integrity & Certification Outputs — Architecture Decision (AC6) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-graduation-integrity.md` |
| `wp-academic-level-progression-and-packaging.md` | Level Progression, Prerequisites, Academic History & Offering-Linked Fee Packaging — Architecture Decision (AC4) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-level-progression-and-packaging.md` |
| `wp-academic-offering-operations.md` | Offering & Availability Console Operations — Architecture Decision (AC8) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-offering-operations.md` |
| `wp-academic-offerings-waitlist.md` | Academic Offerings, Availability and Class Waitlist — Architecture Decision (WP-AO) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-offerings-waitlist.md` |
| `wp-academic-operational-completion.md` | Academic Operational Completion — Architecture Decision (AC14) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-operational-completion.md` |
| `wp-academic-progression-lifecycle.md` | Progression Decision Lifecycle — Architecture Decision (AC13) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-progression-lifecycle.md` |
| `wp-academic-progression-rules.md` | Level Progression Rules & Prerequisites — Architecture Decision (AC12) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-progression-rules.md` |
| `wp-academic-room-section-timetable-operations.md` | Room, Section & Timetable Console Operations — Architecture Decision (AC10) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-room-section-timetable-operations.md` |
| `wp-academic-rooms-sections-timetable.md` | Academic Rooms, Class Sections and Timetable — Architecture Decision (WP-AR) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-rooms-sections-timetable.md` |
| `wp-academic-teacher-assignment-lifecycle.md` | Teacher Assignment Lifecycle — Architecture Decision (AC15) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-teacher-assignment-lifecycle.md` |
| `wp-academic-terminal-guard-doctrine.md` | Class & Period Terminal Guards — Architecture Decision (audit blocker 2) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-terminal-guard-doctrine.md` |
| `wp-academic-transcript-issuance.md` | Official Transcript Issuance — Architecture Decision (AC7) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-transcript-issuance.md` |
| `wp-academic-waitlist-operations.md` | Class Waitlist Operations — Architecture Decision (AC11) | **CURRENT** | `history/02-architecture-history.md` § `wp-academic-waitlist-operations.md` |
| `wp-enrollment-financial-gate.md` | Enrollment Financial Gate — Architecture Decision (AC3) | **CURRENT** | `history/02-architecture-history.md` § `wp-enrollment-financial-gate.md` |
| `wp-placement.md` | Placement Decision System — Architecture Decision (WP-P) | **CURRENT** | `history/02-architecture-history.md` § `wp-placement.md` |
| `wp-visitor-crm.md` | AD-2026-09-04 / WP — Visitor / Lead / CRM Domain | **CURRENT** | `history/02-architecture-history.md` § `wp-visitor-crm.md` |

## Mandatory decision recording

Any change that alters module boundaries, fact ownership, lifecycle authority, security/scope, financial semantics, public API contracts, database invariants, event/projection semantics, or release policy must either follow an existing decision or add a new decision entry before the change is considered complete.
