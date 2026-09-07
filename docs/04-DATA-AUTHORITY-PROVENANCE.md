# TOEFL House — Data, Authority & Provenance

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Core doctrine

Every material business fact has one canonical owner. Other modules may reference, project or record evidence about that fact but must not independently redefine it.

## Authority registry

| Fact | Canonical authority |
|---|---|
| organization/campus/branch topology | Organization |
| authentication/effective identity/scope decisions | Access/Identity |
| person/student profile | Students/Identity |
| admission workflow | Admissions |
| academic calendar/term | Calendar/Academic |
| program/version/level/offering/class | Academic |
| enrollment/waitlist | Enrollment/Academic boundary |
| placement attempt/score/recommendation evidence | Placement/Academic |
| money/obligation/invoice/payment/allocation/refund/ledger | Finance |
| payroll calculation/proposal | Payroll |
| monetary settlement fact | Finance |
| CRM lead/interaction | CRM |
| notifications/read state | Communication projection |
| tasks/work items | Work Management |
| reporting metrics/projections | Reporting |
| audit evidence | Audit |
| integration delivery state | Outbox/Integrations |

## Provenance

Important facts should retain source context, actor, time, scope and correction history. Historical facts are not silently rewritten.

## Corrections

A correction must preserve the original fact and express the correcting event/record through its canonical authority.

## Derived data

Reporting, search, workspace, notification and integration projections are rebuildable derived state. They may lag or fail without becoming the business source of truth.

## Database boundary

Where a business invariant can and should be enforced by PostgreSQL, the database is part of the authority contract. Examples include uniqueness, referential integrity, positive monetary values, capacity relationships and temporal overlap protection.


---

# Consolidated Governing Evidence

