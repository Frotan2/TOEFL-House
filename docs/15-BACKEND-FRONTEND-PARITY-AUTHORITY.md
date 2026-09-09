# TOEFL House — Backend ↔ Frontend Parity Authority

**STATUS: CURRENT CANONICAL — NORMATIVE**  
**Authority branch:** `main`  
**Last reassessed:** 2026-09-09  
**Purpose:** authoritative capability map for user-facing parity, backend-only boundaries, frontend-only presentation responsibilities, remediation and release certification.

> This document supersedes stale capability claims in older audit/report documents. Historical reports remain historical; this file is the current implementation contract.

## 1. Source-of-truth rules

The Laravel backend is authoritative for authentication, authorization, scope, lifecycle legality, persistence, domain commands, accounting, idempotency, audit/provenance, concurrency and all irreversible business decisions. React must never re-implement those rules.

The React application is authoritative only for presentation and interaction concerns: navigation, workspace composition, loading/error/empty states, forms, filters, accessible controls and invoking the canonical API client. A frontend capability is not considered a business capability unless the corresponding server API/domain behavior exists.

Blade is a transport/legacy boundary. New operational work belongs in React workspaces unless there is an explicit reason to retain a legacy surface.

## 2. Parity classification

- **Covered:** the principal user-facing backend workflows are represented in React and use the canonical API.
- **Partial:** React represents the domain, but one or more meaningful backend lifecycle actions, administrative views or evidence workflows are not surfaced.
- **Missing:** a user-facing backend capability has no adequate React operational surface.
- **Backend-only:** implementation infrastructure or control-plane behavior that must remain server-side.
- **Frontend-only:** presentation behavior with no independent domain authority.
- **Implementation complete / runtime-unverified:** code and parity inspection are converged, but current release evidence has not yet certified the latest authoritative commit.

## 3. Capability matrix

| Domain | Backend authority | Current frontend surface | Status | Required direction |
|---|---|---|---|---|
| Organization | organization/campus/branch structure and lifecycle | Organization workspace | Covered | Preserve server scope authority |
| Identity | people, accounts, verification, lifecycle | Identity workspace | Covered | Preserve canonical API |
| Access | roles, permissions, grants, delegations, scoped assignments | Access workspace | Partial | Surface complete approval/revoke/delegation workflows and denial-path evidence |
| Applicants & Students | registration, admissions, enrollment, hold/suspend/withdraw/reactivate/complete/graduate, guardians | Students workspace | Partial | Verify every terminal/lifecycle transition and expose missing approved workflows |
| CRM / Front Office | sources, campaigns, visitors, timelines, person linking, follow-ups, automations | CRM/front-office workspaces | Partial | Close automation/follow-up and evidence gaps |
| Academic | programs/versions, periods, prerequisites, offerings, rooms, sessions, attendance correction, assessment, moderation, results, appeals, progression, enrollments, waitlist, graduation, certificates, transcripts | Academic workspace | Partial | Complete lifecycle/action parity, especially appeals/corrections/progression/graduation/transcript flows |
| Placement | tests/versions, profiles, attempts, digital/physical evidence, scoring/moderation/approval, recommendation, review, release, eligibility, finance link, appeals, content administration | Placement workspace | Partial | Surface remaining decision, appeal, report, supersede and content-management capabilities |
| Teachers | qualifications, profile transitions, branch transfer, skills, availability, workload, assignment lifecycle | Teachers workspace | Partial | Close assignment/availability/workload action gaps |
| HR | employment lifecycle, leave, contracts, scales | HR workspace | Partial | Surface full contract/leave/employment lifecycle controls |
| Finance | obligations, payments, periods, GL, allocations, journals, reversals, discounts, reconciliations, funds, credits, installments, gate exceptions, coverage, refunds, corrections, settlements, expenses, cash drawers, scholarships, statements/completeness | Finance workspace | Partial | Complete approval, reversal/correction, cash, scholarship, settlement and GL control actions |
| Payroll | periods, calculate/approve/resolve-held, clearance, settlement | Payroll workspace | Partial | Verify action parity and approval exceptions |
| Library & Resources | books, circulation, loss, assets, custody, staged disposal request/dual approval/execution, facilities work lifecycle | Library workspace | **Implementation complete / runtime-unverified** | Verify current main release gates; preserve server-owned lifecycle, scope, evidence and irreversible-action controls |
| Documents | documents, versions, classification, verification, retention rules/decisions | Legacy/partial document UI | Partial / High priority | Converge operational document lifecycle into React without duplicating document authority |
| Privacy | consent/disclosure/privacy controls | Legacy/partial privacy UI | Partial | Keep policy authority server-side; modernize operational controls |
| Audit | audit events and evidence | Legacy/partial audit UI | Partial | Provide scoped search/filter/read-only evidence view without moving audit authority client-side |
| Communication | messages, notification projection, queue/work coordination | Communication/navigation + work/notification UI | Partial | Surface usable message/thread/search/status workflow |
| Reporting | metric definitions/versions, report runs, dashboards, projections/reconciliation | Reporting workspace | Partial | Complete run lifecycle and evidence/status actions |
| Management / Work | management search, work items, queue membership, transitions, notifications | Workspace/management | Partial | Surface queue membership and complete work lifecycle |
| Integrations | endpoints, deliveries, inbound events, retry jobs, scheduling | No normal business UI | Backend-only / Operator-only | Keep infrastructure server-side; add operator observability only when required |

## 4. Library & Resources re-evaluation

The domain was rechecked after the previous convergence work.

Confirmed implementation scope includes books/copies, circulation, loss, assets, custody, facilities work, staged disposal request/approval/execution, explicit actor selection, evidence capture, irreversible confirmation, idempotency and server-owned scope/lifecycle/audit controls.

The disposal workflow is deliberately staged and server-driven. React must never infer approval state or reproduce dual-approver SoD in client code.

The domain is **not** called release-certified until the current authoritative `main` commit passes the complete Verification release chain.

## 5. Partial-domain re-evaluation rule

Every Partial domain must be re-evaluated workflow-by-workflow before implementation continues. The minimum trace is:

`Requirement → Authority → Lifecycle → DB invariant → Command/service → Authorization → Scope → API → React → UX/error states → Audit/provenance → Idempotency → Concurrency → Tests → Browser/E2E → Documentation`

A domain cannot be promoted from Partial because a page, endpoint list or happy-path test exists.

## 6. Backend-only capabilities

The following must not be duplicated in React:

- database schema, migrations, constraints and transactional persistence
- RBAC/scope computation and authorization decisions
- domain commands/services and lifecycle invariants
- idempotency and replay protection
- audit/event/outbox creation
- accounting/ledger calculation and reconciliation authority
- concurrency and temporal guards
- projections/materializations and data provenance
- queue execution, retry workers and scheduled jobs
- integration transport details and secrets

## 7. Frontend-only responsibilities

The following are legitimate frontend capabilities with no backend counterpart:

- navigation registry and route/tab selection
- accessible keyboard behavior and skip-link focus handling
- loading, empty, pending, disabled and error states
- local form composition and validation hints that do not replace server validation
- presentation filtering and table/card composition over server-provided facts
- client-side request cancellation/timeouts through the canonical API client

These are UX capabilities, not business-domain authority.

## 8. Remediation standard

A parity fix is accepted only when all are true:

1. The backend endpoint/domain command is authoritative and scope-aware.
2. The React surface reaches it through `resources/js/core/api.ts` or a specialist entrypoint that uses the same canonical client.
3. The UI does not infer or mutate authoritative lifecycle/accounting/security state locally.
4. The relevant happy path, denied path and invalid-state path are represented in tests where practical.
5. Documentation names the capability and identifies whether it is covered, partial, missing, backend-only or runtime-unverified.
6. Browser verification confirms the intended workspace mounts without credential leakage or asset-path regressions.

## 9. Release gates

The repository is **not certified complete merely because the capability matrix exists**. Release certification requires the authoritative `main` branch to pass the current Verification workflow, including frontend, static analysis, backend tests and browser E2E, with no unresolved critical/high parity or security defects.

A run that is superseded by a later commit is evidence for the older commit only.

## 10. Audit evidence

Primary implementation evidence includes:

- `routes/api.php` and domain-specific `routes/*-api.php`
- `resources/js/app.tsx`
- `resources/js/core/api.ts`
- `resources/js/core/navigation.ts`
- domain React entrypoints under `resources/js/`
- Laravel API controllers and domain command modules under `app/`
- `tests/Frontend/`, `tests/Canonical/`, `tests/Unit/` and the Verification workflow
- `docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`

The capability map must be reviewed whenever a domain route, command, lifecycle, React workspace or security boundary changes materially.
