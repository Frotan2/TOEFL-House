# TOEFL House — Domain & Business Capability Catalog

**STATUS: CURRENT CANONICAL — NORMATIVE**

| Domain | Authority | Current maturity | Major deferred scope |
|---|---|---|---|
| Organization | organization hierarchy/lifecycle | implemented | deeper operational topology only where approved |
| Access/Identity | authentication, actor status, grants, scope | implemented | advanced governance automation |
| Students/Admissions | student profile + admissions workflow | implemented | portal breadth |
| Calendar | Kabul/Solar Hijri operational calendar | implemented | wider calendar-facing UX |
| Academic | terms, programs, offerings, classes, academic decisions | implemented/broad | remaining boundary and product breadth |
| Enrollment | enrollment/capacity/waitlist | implemented | further operational optimization |
| Placement | assessment, attempts, scoring, recommendation evidence | implemented | richer analytics |
| Scheduling | timetable/room/teacher constraints | implemented | target boundary refinements |
| Finance | obligations, invoices, payments, allocations, refunds, ledger, corrections | implemented/strong | bank reconciliation, procurement/AP, aid depth |
| Payroll | calculation/proposal/evidence | implemented | wider HR/payroll breadth |
| Employment settlement | Finance-recorded settlement fact | implemented | broader commercial workflows |
| CRM | visitor/lead/follow-up/conversion | implemented | richer automation |
| Workspace | work-first operational composition | implemented | SLA/escalation depth |
| Communication | notification/recipient/read projections | implemented | richer channels/preferences |
| Reporting | read models, metrics, audits | implemented | broader immutable snapshots/replay tooling |
| Outbox/Integrations | delivery of committed facts | implemented | richer replay/DLQ/external subscriptions |
| Documents/Privacy | private document and privacy controls | implemented | operational/runtime depth |
| Audit | historical evidence and attempted/rejected operations | implemented | runtime tamper drills |

## Lifecycle

The core academic lifecycle is:

`Term → Program/Version → Level → Offering → Class → Capacity → Enrollment → Attendance/Assessment → Progression → Graduation → Transcript`

with placement, scheduling, teacher assignment, financial gating and evidence integrated at their canonical boundaries.

## Capability status semantics

- **Implemented:** code authority exists.
- **Partially implemented:** meaningful capability exists but approved target breadth remains.
- **Deferred:** explicitly approved future work.
- **Runtime-unverified:** implementation exists but official runtime evidence is outstanding.
