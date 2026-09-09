# TOEFL House — Current Implementation Status / Roadmap

**STATUS: CURRENT CANONICAL — STATUS + PLANNING**  
**Last reassessed:** 2026-09-09  
**Authority branch:** `main`

## Current state

The repository is a Laravel 12 modular monolith backed by PostgreSQL with broad domain implementation across organization, access, students/admissions, academic, placement, finance, HR/payroll, CRM, reporting, integrations, workspace and governance.

The project is now in a **domain-convergence phase** rather than a greenfield rebuild. The governing standard is that one material domain is closed completely before another is advanced.

## Strongly achieved / structurally established

- centralized authority model;
- organization → campus → branch scope hierarchy;
- finance as monetary authority;
- academic lifecycle authorities;
- PostgreSQL business invariants and temporal guards;
- idempotency/concurrency patterns;
- audit and provenance controls;
- React operational boundary plus employee/management workspace foundations;
- canonical API transport and navigation architecture;
- outbox/consumer foundations;
- repository-wide hygiene and standards policy;
- deterministic runtime/environment lock;
- frontend typecheck/build/mount verification;
- Library & Resources workflow convergence, including staged disposal governance.

## Release-state correction

**The repository is not to be treated as currently production-certified merely because an older certification document says so.**

`AUDIT-2026-09-09-FINAL-CERTIFICATION.md` is historical evidence for its own execution line. Its certification at commit `96925d3` does not automatically certify later `main` commits.

The current release state is governed by the latest non-superseded Verification workflow on `main` plus the evidence rules in `docs/ai/06-VERIFICATION-EVIDENCE-STANDARD.md` and `docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md`.

At this reassessment, Verification run **#442 / `34366532471`** is the active evidence line for commit `2ed406e6ae07c1dd24f38cc88432457a460519e8`. Frontend verification has passed; the remaining jobs must be judged only from their actual final conclusions. Do not infer success from progress or historical runs.

## Current domain register

| Domain | Current state | Required closure work |
|---|---|---|
| Organization / scope | COVERED / STRONG | Preserve authority and adversarial scope tests. |
| Identity | COVERED / STRONG | Preserve authentication, lifecycle, verification and provenance authority. |
| Access / RBAC | PARTIAL | Complete operational grant/revoke/delegation/approval parity and denial-path evidence. |
| Applicants & Students | PARTIAL | Re-evaluate every lifecycle transition, branch scope, evidence, financial gates, React parity and E2E. |
| CRM / Front Office | PARTIAL | Close follow-up/automation/evidence workflow parity and scope behavior. |
| Academic | PARTIAL | Close action parity for corrections, appeals, progression, graduation, transcripts, waitlist and terminal states. |
| Placement | PARTIAL | Close decision, moderation, appeals, release, supersede, reporting and content-management surfaces. |
| Teachers | PARTIAL | Close qualification, availability, assignment, transfer and workload action parity. |
| HR | PARTIAL | Close employment, contracts, leave and lifecycle action parity. |
| Finance | PARTIAL | Close approvals, corrections, reversals, cash, scholarships, settlement and GL control surfaces. |
| Payroll / settlement | PARTIAL | Close action/approval/held-resolution/clearance/settlement parity and verification. |
| Library & Resources | IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED | Current implementation is converged; release closure waits for current verification evidence. |
| Documents | PARTIAL / HIGH-PRIORITY NEXT | Converge document identity/version/classification/verification/retention lifecycle into an authoritative React workflow. |
| Privacy | PARTIAL | Modernize operational controls while retaining server policy authority. |
| Audit | PARTIAL | Provide scoped evidence search/read surfaces without moving authority to client. |
| Communication | PARTIAL | Close message/thread/search/status workflow and notification/work integration. |
| Reporting | PARTIAL | Close report-run evidence/status actions and remaining replay/snapshot/operational depth. |
| Management / Work | PARTIAL | Close queue membership, transitions and complete work lifecycle surface. |
| Integrations | BACKEND-ONLY / OPERATOR | Do not force normal business UI parity; expose operator observability only where approved. |

## Domain closure rule

A domain is **DONE** only when all materially applicable dimensions pass:

`Business lifecycle + Authority + DB integrity + Authorization + Scope + API + React + UX states + Audit/Provenance + Idempotency + Concurrency + Tests + Browser/E2E + Security + Operational evidence + Documentation`

A green endpoint or screen is not sufficient.

## Priority order from this point

1. Finish runtime verification of the currently active main line.
2. Close and certify Library & Resources; do not advance while its release gates remain unresolved.
3. Freeze/update documentation after each certified domain.
4. Start the next domain only after a fresh workflow-by-workflow reassessment; Documents is the current proposed next domain, subject to that fresh inspection.
5. Repeat domain closure discipline until all material Partial domains are either Covered and certified or explicitly Deferred by an approved decision.
6. Only then address broader productization, legacy retirement, scalability and optional breadth.

## Documentation governance

`docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md` is a blocking handoff contract for the next agent.

Whenever implementation state changes, update the affected canonical documents in the same work package. Historical evidence must remain historical rather than being silently rewritten into current truth.

## Runtime environment authority

The locked runtime is:

- PHP 8.4.25
- Composer 2.10.3
- Node 22.22.3
- npm 10.9.8
- PostgreSQL 18.4

The machine-checking script and runtime lock remain authoritative.

## Release-state separation

**CURRENT STATE** = what exists now.  
**TARGET STATE** = what approved product architecture intends to become.  
**RELEASE STATE** = what current, non-superseded runtime evidence has actually proven.

Never promote CURRENT or TARGET to RELEASE by documentation wording alone.

Historical work-package roadmaps remain historical evidence and must not override this current-state roadmap.
