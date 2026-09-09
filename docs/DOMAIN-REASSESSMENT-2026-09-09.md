# TOEFL House — Domain Reassessment 2026-09-09

**STATUS: CURRENT CANONICAL ASSESSMENT — NORMATIVE FOR PLANNING**  
**Authority branch:** `main`

This document records the second-pass domain reassessment requested after the previous implementation/convergence work. It is intentionally stricter than a screen/endpoint checklist.

## Assessment model

A domain is evaluated by business workflows, not by endpoint count or screen count.

For each workflow the required chain is:

`Requirement → Authority → Lifecycle → DB invariant → Command/service → Authorization → Scope → API → React → UX/error states → Audit/provenance → Idempotency → Concurrency → Tests → Browser/E2E → Documentation`

### Status meaning

- **COVERED / STRONG:** current implementation is coherent and materially complete for the inspected scope.
- **IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED:** implementation/parity converged, but current-release evidence is still pending.
- **PARTIAL:** meaningful lifecycle, action, evidence, operational, security or user-facing gaps remain.
- **BACKEND-ONLY / OPERATOR:** deliberately server/control-plane behavior; do not duplicate it as business logic in React.

## Results

| Domain | Second-pass result | Main remaining concern | Next action |
|---|---|---|---|
| Organization / Scope | COVERED / STRONG | Preserve temporal topology and branch isolation | Regression-only unless new scope defects appear |
| Identity | COVERED / STRONG | Preserve account/person lifecycle and provenance | Regression-only unless new identity gaps appear |
| Access / RBAC | PARTIAL | Operational grant/revoke/delegation/approval completeness and denial-path evidence | Deep workflow parity + adversarial scope review |
| Applicants & Students | PARTIAL | Full terminal lifecycle/action parity and evidence-driven student workflows | Re-inventory lifecycle and denied paths |
| CRM / Front Office | PARTIAL | Follow-up, automation and evidence workflow depth | Reconcile backend lifecycle to React workspace |
| Academic | PARTIAL | Corrections, appeals, progression, graduation, transcripts, waitlist and terminal-state UX | Deep lifecycle parity audit |
| Placement | PARTIAL | Decision/review/appeal/release/supersede/content administration breadth | Deep evidence + action parity audit |
| Teachers | PARTIAL | Qualification/availability/assignment/transfer/workload operations | Complete operational action parity |
| HR | PARTIAL | Employment/contract/leave lifecycle surface | Complete lifecycle parity |
| Finance | PARTIAL | Approval, correction, reversal, cash, scholarship, settlement and GL control UX | Treat backend as authority; close user workflow gaps |
| Payroll / Settlement | PARTIAL | Held-resolution, approval, clearance and settlement workflow parity | Verify action states and SoD |
| Library & Resources | IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED | Current release evidence | Finish current Verification certification before leaving domain |
| Documents | PARTIAL / HIGH PRIORITY | React lifecycle missing/legacy-heavy boundary | Candidate next domain after Library certification |
| Privacy | PARTIAL | Operational controls remain legacy/partial | Converge UI without duplicating policy authority |
| Audit | PARTIAL | Scoped read/search evidence experience | Build read-only evidence workspace |
| Communication | PARTIAL | Message/thread/search/status workflow depth | Reconcile notification/work projection with user workflow |
| Reporting | PARTIAL | Complete report-run/evidence/status operations plus replay/snapshot depth | Workflow parity and operational evidence |
| Management / Work | PARTIAL | Queue membership and complete work lifecycle UX | Finish operational work surface |
| Integrations | BACKEND-ONLY / OPERATOR | Operator observability only when justified | Do not force business parity |

## Library & Resources second-pass conclusion

Implementation has converged around the authoritative backend commands and lifecycle. The React workspace covers circulation, asset custody, staged disposal, facilities work, explicit actor selection and irreversible-action confirmation. This is sufficient to classify the implementation as **IMPLEMENTATION COMPLETE**, but not sufficient to claim release certification without current Verification evidence.

## Rules for every future domain closure

1. Inspect the backend before changing the frontend.
2. Identify the canonical authority before implementing a UI action.
3. Verify authorization at both route/controller/domain-command boundaries.
4. Verify organization/campus/branch/class/own scope where applicable.
5. Test denial and invalid-state paths, not only success paths.
6. Verify idempotency and concurrency for consequential writes.
7. Keep irreversible actions visibly and technically gated.
8. Use the canonical API client and navigation architecture.
9. Update documentation in the same work package.
10. Require current runtime evidence before release certification.

## Current priority

**Do not start Documents or any other Partial domain until Library & Resources is closed against the current release gates.** After Library certification, re-inspect Documents from source rather than assuming the previous matrix is still correct.
