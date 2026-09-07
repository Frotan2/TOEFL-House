# Foundation, Discovery & Requirements History

STATUS: HISTORICAL — NOT NORMATIVE

This consolidated volume preserves historical source documents verbatim by section. The source path is retained before each section. Current project authority lives in the canonical documentation set, not here.


---

## HISTORICAL SOURCE: `foundation/00-foundation-state.md`

> **Historical-status notice (2026-09-07):** This record is a Foundation-era discovery/state artifact. Its statements that the repository was Express/TypeScript/SQLite and that Laravel/PostgreSQL production implementation was not authorized are historical and no longer describe the current implementation. For current documentation-to-implementation truth, use `docs/architecture/current-state-compliance.md`; for current architecture authority use `docs/MASTER_ENGINEERING_CONTRACT.md` and the accepted 2026-09-05 architecture graph/ADRs. The historical evidence below is intentionally preserved.

# TOEFL House Foundation State

**Governing contract:** `docs/MASTER_ENGINEERING_CONTRACT.md` (TOEFL HOUSE ERP — WORLD-CLASS MASTER ENGINEERING CONTRACT, v3.0, Canonical Project Engineering Constitution) is the highest-authority project engineering directive; this record and the foundation artifacts below it must remain consistent with it.

**Phase:** Foundation / discovery
**Current gate:** Gate 6 — Implementation Readiness and Execution Contract (`PASS WITH NON-BLOCKING OPEN ITEMS`)
**Certification:** `NOT CLAIMED` — business discovery is complete; Gates 1–16 and Foundation Certification remain pending
**Updated:** 2026-08-25

## Branch and repository boundary

- Active branch: `arena/01a034c7-toefl-house`.
- This Arena session is fixed to that branch; a second branch cannot be created without violating the session boundary.
- The branch is **not greenfield in its current checkout**. Its HEAD already contains a substantial React/Vite frontend, Express/TypeScript backend, SQLite schema, routes, domain services, scripts, fixtures, and tests.
- Therefore the existing implementation is classified as **legacy reference material** for this directive. It is not accepted as requirements authority, architectural authority, or business-rule authority.
- No production implementation, production database, migration, CRUD, API, or UI work is authorized before Foundation Certification.

## Evidence snapshot

| Item | Evidence | Classification |
|---|---|---|
| Branch is fixed as above | `git status --short --branch` | KNOWN, Level 5 |
| HEAD is `e9322b2` | `git log --oneline -1` | KNOWN, Level 5 |
| Existing implementation is extensive | `README.md`, `package.json`, `server/package.json`, `src/`, `server/src/` | KNOWN, Level 5 |
| Existing backend uses Express + TypeScript + SQLite | `server/package.json`, `server/src/db/schema.sql`, `server/src/index.ts` | KNOWN, Level 5 |
| Existing frontend uses React + Vite + TypeScript | `package.json`, `vite.config.ts`, `src/` | KNOWN, Level 5 |
| Existing repository has 216 backend test files | `find server/src/tests -type f` | KNOWN, Level 5 |
| Existing repository already contains engineering registries and work-package records | `docs/registries/`, `docs/certification/`, `docs/work-packages/` | KNOWN, Level 5; historical/reference only |
| No production dataset requiring preservation | Existing documentation states this, but it is not a user-confirmed decision in this session | UNVERIFIED until confirmed |

## Foundation controls initialized

The following controlled registers are now established as the working foundation set:

1. Project state and gate register — this document.
2. Legacy intelligence report — `docs/foundation/01-legacy-system-intelligence-report.md`.
3. Organization discovery and question register — `docs/foundation/02-organization-discovery.md`.
4. Foundation document index — `docs/foundation/03-document-index.md`.
5. Foundation decision ledger — `docs/foundation/04-decision-ledger.md`.
6. Foundation risk and contradiction register — `docs/foundation/05-risk-register.md`.

Existing `docs/registries/*` files are preserved as repository evidence and are not silently promoted to authority for the new system.

## Gate 0 exit criteria

Gate 0 cannot pass until:

- the repository and legacy boundary are recorded;
- the current-state map is complete enough to plan discovery;
- the initial actor and organization questions are answered or explicitly classified as non-critical;
- all material assumptions are separated from decisions;
- contradictions between the directive, repository, and user decisions are recorded;
- the next discovery cluster is approved.

**Current result:** Gate 6 is `PASS WITH NON-BLOCKING OPEN ITEMS`; see `docs/implementation/19-gate-6-review.md`. Production implementation is not authorized.

## Prohibited claims

This document does not claim that the current application is complete, secure, certified, greenfield, PostgreSQL-based, Laravel-based, or compliant with the directive. Existing tests and certifications are evidence about the existing repository only.

---

## HISTORICAL SOURCE: `foundation/01-legacy-system-intelligence-report.md`

# Legacy System Intelligence Report

**Classification:** Reference evidence only
**Phase:** Foundation / forensic discovery
**Updated:** 2026-08-24

## 1. Executive finding

The checkout is an already implemented ERP-oriented system, not an empty greenfield foundation. It contains a React/Vite client, an Express/TypeScript server, a canonical SQLite schema, domain services, HTTP routes, operational scripts, fixtures, and a large automated test inventory. Under the Absolute Master Directive, this implementation must be inspected as evidence and must not be copied or treated as authoritative.

The report does not certify the legacy system or infer that its behavior is approved business policy.

## 2. Current-state map

| Area | Observed evidence | Forensic interpretation |
|---|---|---|
| Client | `src/App.tsx`, `src/components/`, `src/api/`, `src/contexts/` | React frontend with many operational views and client contracts |
| Server boundary | `server/src/index.ts`, `server/src/routes/` | Express API with route-level middleware and domain calls |
| Domain services | `server/src/core/academic/`, `finance/`, `placement/`, `rbac/`, `payroll/`, `books/`, `funding/`, `reporting/` | Material domains have been implemented, but their final boundaries remain to be rediscovered |
| Persistence | `server/src/db/schema.sql`, `server/src/db/connection.ts` | SQLite is the current implementation database; schema is large and trigger-heavy |
| Identity/access | `server/src/core/rbac/`, `server/src/middleware/auth.ts`, `users`, `roles`, `permissions`, `user_roles` | Existing design attempts multi-position, scoped RBAC/ABAC; must be independently revalidated |
| Organization | `server/src/db/organizationHierarchy.ts`, `branches.routes.ts` | Existing bootstrap includes one organization, one campus, and one default branch; this is not evidence that the business has only one |
| Finance | `server/src/core/finance/`, `server/src/routes/finance.routes.ts`, `invoices.routes.ts`, `payments`-related tests | Existing monetary and ledger behavior is substantial; exact accounting policy remains a discovery question |
| Calendar | `server/src/core/calendar/periods.ts`, `server/src/utils/jalali.ts` | Existing implementation includes Solar Hijri/Gregorian handling; central authority and business semantics require verification |
| Operations | `server/src/core/operations/database-backup.ts`, `docs/OPERATIONS.md` | Existing backup/recovery behavior is documented and tested, but is not automatically approved for the new architecture |
| Test evidence | 216 files under `server/src/tests/` at inspection time | Test count is evidence of exercised behavior, not proof of completeness or certification |
| Documentation | Legacy-era paths `docs/MASTER_ENGINEERING_PROTOCOL.md`, `docs/certification/`, `docs/work-packages/`, `docs/registries/`. The legacy `MASTER_ENGINEERING_PROTOCOL.md` is **not present** in the current tree; the canonical governing contract is now `docs/MASTER_ENGINEERING_CONTRACT.md` (v3.0). | Existing governance records are historical repository evidence; they must be reconciled with the new directive, and the current governing contract supersedes the legacy protocol path |

## 3. Valid candidate business knowledge to verify

These are leads for discovery, not accepted requirements:

- TOEFL House has organizational concepts including campuses and branches.
- A person may be represented separately from an account and may hold multiple positions.
- Academic delivery includes programs, levels, classes, teachers, enrollments, attendance, examinations, and placement.
- Finance includes fees, invoices, payments, refunds, budgets, expenses, payroll, funding, and reporting concepts.
- The institution uses Solar Hijri business dates while technical storage may use Gregorian/ISO dates.
- Operational users need scoped workspaces and server-enforced access controls.
- Audit, reporting, notifications, backup, and recovery are material concerns.

Each candidate must be promoted only by explicit user decision, verified domain evidence, or approved foundation documentation.

## 4. Observed architectural risks and contradictions

| ID | Finding | Evidence | Status |
|---|---|---|---|
| L-001 | The current checkout violates the directive's Foundation Phase prohibition on production implementation because implementation already exists in the branch. | `src/`, `server/src/`, `server/src/db/schema.sql` | BLOCKING; do not extend it as new production work |
| L-002 | Current persistence is SQLite, while the directive names PostgreSQL as the preferred database and asks for architecture discovery before selection. | `server/package.json`, `better-sqlite3` imports, `schema.sql` | OPEN DECISION |
| L-003 | Current backend is Express/TypeScript, while Laravel is the preferred backend recommendation. | `server/package.json`, `server/src/index.ts` | OPEN DECISION |
| L-004 | Existing documents describe prior decisions, certifications, and implementation outcomes that may conflict with the new directive or may not be user-approved for this session. | `docs/registries/`, `docs/certification/`, `docs/work-packages/` | Must be treated as historical evidence until confirmed |
| L-005 | Existing bootstrap contains a default Kabul campus/main branch and fixed identifiers. This may be seed behavior rather than an institutional policy. | `server/src/db/organizationHierarchy.ts` | OPEN BUSINESS QUESTION |
| L-006 | Existing schema has many cross-domain tables and database triggers. The new system must establish domain ownership and decide which invariants belong in database, application, or both. | `server/src/db/schema.sql` | OPEN ARCHITECTURAL ANALYSIS |
| L-007 | Existing test and certification volume could create false confidence if treated as proof that the directive's newly required domains and traceability are complete. | `server/src/tests/`, `docs/certification/` | CONTROL: no certification by test count |

## 5. Legacy reuse disposition

No legacy code, model, service, route, schema, migration, component, fixture, or test has been approved for reuse by this report. Any future reuse proposal requires a recorded reuse decision with requirement fit, security review, architectural compatibility, and test evidence.

## 6. Next forensic activities

1. Confirm institutional organization and authority facts with the user.
2. Build the organization, ownership, campus, branch, department, position, role, permission, scope, and workspace models.
3. Inventory all actors and material workflows from source without treating route existence as requirement proof.
4. Reconcile existing registries against confirmed decisions.
5. Select target architecture only after critical business decisions are resolved.

---

## HISTORICAL SOURCE: `foundation/02-organization-discovery.md`

# Organization Discovery — Decision Cluster 1

**Status:** `BLOCKED / QUESTIONS REQUIRED`
**Authority level:** User decision required for policy; repository observations are evidence only.
**Updated:** 2026-08-24

## Why this cluster comes first

Every later permission, financial posting, academic assignment, report, workspace, and audit record depends on knowing what organizational units exist and who may act for them. If this is guessed, branch isolation and financial ownership can be wrong throughout the system.

## Questions for the owner

Please answer in plain language. Short answers are acceptable; use `UNKNOWN` where the institution has not decided yet.

### O-01 — Organizational units

The directive requires `Organization → Campus → Branch → Department / Operational Unit` and says multiple campuses and branches must be supported from day one.

Which units exist or are planned?

- Organization(s): Is TOEFL House one legal/operating organization, or should the system support multiple organizations/tenants?
- Campuses: How many currently exist, and what should a campus mean operationally?
- Branches: How many currently exist, and can a branch belong to exactly one campus?
- Departments/operational units: Which departments exist today (for example Academic, Reception, Finance, HR, Marketing, Operations, IT), and may one person work in several?

### O-02 — Ownership and authority

There are three Owners with equal authority according to the directive.

Please confirm:

1. Are all three legal/operational owners equal for every decision?
2. Are there decisions requiring two or three-owner approval (for example high-value refunds, asset disposal, borrowing, or ownership changes)? If yes, list the decisions and approval counts.
3. May an Owner's access be restricted to a campus or branch for day-to-day work, or are all Owners organization-wide by default?
4. Can a General Manager make decisions across every campus/branch, or only explicitly assigned scopes?
5. Who may create, deactivate, or change another Owner's account and authority?

### O-03 — Management authority

For each item, identify the position that has final authority, if any:

- creating or closing a campus;
- creating or closing a branch;
- assigning a branch manager;
- creating departments;
- approving staff appointments;
- approving fees and discounts;
- approving scholarships and sponsorships;
- approving refunds and financial adjustments;
- approving payroll;
- approving configuration changes;
- viewing organization-wide reports;
- changing permissions and scopes.

### O-04 — Position inventory

The known positions are Owners, General Manager, Head of Department, Finance Manager, Male Receptionist, Female Receptionist, Designer/Social Media Manager, Test Officer, Teachers, Cleaner, and Guard.

Please identify any missing current or planned positions, including:

- campus/branch managers;
- academic coordinator or registrar;
- HR/payroll officer;
- accountant/cashier;
- librarian/book custodian;
- IT/system administrator;
- admissions officer;
- student/parent account holder;
- auditor or external reviewer;
- temporary, acting, or delegated positions.

Also confirm whether gendered receptionist labels are formal positions or only describe current staffing.

### O-05 — Person and account rules

1. May one person hold multiple positions at the same time? (The directive says yes; confirm operationally.)
2. May one person work across multiple branches/campuses? If yes, who authorizes it and can the assignment be time-limited?
3. Should students, parents/guardians, teachers, and employees have separate login experiences, or can one person have multiple account types?
4. What happens to access when employment or a position ends?
5. Can accounts be shared by reception/finance teams, or must every human operator have an individual account?

### O-06 — Scope vocabulary

Which scopes are needed in daily operations beyond organization, campus, branch, department, class, student, teacher, employee, financial resource, and relationship-based access?

Examples to confirm or reject: program, academic period, financial period, cash drawer, bank account, document, event, inventory location, room, report, and assigned queue.

### O-07 — Workspace priorities

For the first workspace design pass, rank the most important daily workspaces:

- Owner / executive;
- General Manager;
- Branch or Campus Manager;
- Reception / Admissions;
- Finance;
- Academic management;
- Teacher;
- HR / Payroll;
- Test Officer;
- Marketing / Events;
- Library / Books;
- Operations / Facilities;
- Security / Guard;
- System Administration;
- Student / Parent portal.

For each top-priority workspace, what is the single most important queue or decision it must show first?

## Current answers

No answers recorded yet in this session.

## Decision rule

No critical authorization, scope, workspace, organizational ownership, or financial posting behavior may be implemented or certified until these answers are resolved and entered into the decision ledger.

---

## HISTORICAL SOURCE: `foundation/03-document-index.md`

> **Historical-index notice (2026-09-07):** The table below is a Foundation-era planning index. Many entries labeled `PLANNED`/`BLOCKED` were subsequently authored or superseded during architecture/implementation convergence. Do not use those historical states as the current repository inventory. The authoritative current-state reconciliation is `docs/architecture/current-state-compliance.md`.

# Foundation Document Index

**Status:** Foundation set initialized; most documents are not yet authored.

This index maps the directive's required foundation artifacts to their current state. `PLANNED` means the artifact is intentionally queued, not complete.

> **Governing contract.** The highest-authority engineering document is
> `docs/MASTER_ENGINEERING_CONTRACT.md` — **TOEFL HOUSE ERP — WORLD-CLASS
> MASTER ENGINEERING CONTRACT, Version 3.0 (Canonical Project Engineering
> Constitution)**. It is the PRIMARY PROJECT ENGINEERING DIRECTIVE for the
> entire TOEFL House ERP / EdTech platform and sits above the artifacts indexed
> here. Every artifact below (and every architecture/implementation/operations
> record) must be consistent with it unless a later approved revision explicitly
> supersedes it.

| # | Required artifact | Path / planned path | State |
|---:|---|---|---|
| 00 | Master Engineering Contract | `docs/MASTER_ENGINEERING_CONTRACT.md` | MASTER / CANONICAL / CONTINUOUSLY IN FORCE |
| 01 | Project Charter | `docs/foundation/charter.md` | PLANNED |
| 02 | System Mission & Scope | `docs/foundation/mission-and-scope.md` | PLANNED |
| 03 | Organization Model | `docs/foundation/organization-model.md` | BLOCKED by O-01–O-03 |
| 04 | Campus/Branch Model | `docs/foundation/campus-branch-model.md` | BLOCKED by O-01 |
| 05 | Stakeholder Register | `docs/foundation/stakeholders.md` | PLANNED |
| 06 | Position Register | `docs/foundation/positions.md` | BLOCKED by O-04 |
| 07 | Role Register | `docs/foundation/roles.md` | BLOCKED by O-02–O-06 |
| 08 | Permission Register | `docs/foundation/permissions.md` | BLOCKED by role/scope decisions |
| 09 | Scope Model | `docs/foundation/scopes.md` | BLOCKED by O-06 |
| 10 | Workspace Specification | `docs/foundation/workspaces.md` | BLOCKED by O-07 |
| 11 | Requirements Specification | `docs/foundation/requirements.md` | IN PROGRESS after discovery |
| 12 | Domain Register | `docs/foundation/domains.md` | PLANNED |
| 13 | Domain Specifications | `docs/foundation/domains/` | PLANNED |
| 14 | Business Rule Registry | `docs/foundation/business-rules.md` | PLANNED |
| 15 | Entity Registry | `docs/foundation/entities.md` | PLANNED |
| 16 | State Transition Registry | `docs/foundation/states.md` | PLANNED |
| 17 | Source-of-Truth Registry | `docs/foundation/source-of-truth.md` | PLANNED |
| 18 | Invariant Registry | `docs/foundation/invariants.md` | PLANNED |
| 19 | Decision Register | `docs/foundation/04-decision-ledger.md` | IN PROGRESS |
| 20 | Assumption Register | `docs/foundation/assumptions.md` | PLANNED |
| 21 | Open Question Register | `docs/foundation/02-organization-discovery.md` and subsequent clusters | IN PROGRESS |
| 22 | Risk Register | `docs/foundation/05-risk-register.md` | IN PROGRESS |
| 23 | Threat Model | `docs/foundation/threat-model.md` | PLANNED |
| 24 | Security Architecture | `docs/foundation/security-architecture.md` | PLANNED |
| 25 | Financial Model | `docs/foundation/financial-model.md` | BLOCKED by business policy decisions |
| 26 | Accounting Model | `docs/foundation/accounting-model.md` | BLOCKED by finance decisions |
| 27 | Calendar & Period Model | `docs/foundation/calendar-periods.md` | BLOCKED by period policy decisions |
| 28 | Configuration Model | `docs/foundation/configuration.md` | PLANNED |
| 29 | Approval Model | `docs/foundation/approvals.md` | BLOCKED by O-02/O-03 |
| 30 | Audit Model | `docs/foundation/audit-model.md` | PLANNED |
| 31 | Notification Model | `docs/foundation/notifications.md` | PLANNED |
| 32 | Reporting Model | `docs/foundation/reporting.md` | PLANNED |
| 33 | Integration Model | `docs/foundation/integrations.md` | PLANNED |
| 34 | Data Model | `docs/foundation/data-model.md` | PLANNED after domain discovery |
| 35 | Database Architecture | `docs/foundation/database-architecture.md` | PLANNED (target architecture decided 2026-08-25; ADR-013) |
| 36 | API Architecture | `docs/foundation/api-architecture.md` | PLANNED (target architecture decided 2026-08-25; ADR-013) |
| 37 | API Contracts | `docs/foundation/api-contracts/` | PROHIBITED before architecture |
| 38 | Application Architecture | `docs/foundation/application-architecture.md` | BLOCKED by discovery |
| 39 | Frontend Architecture | `docs/foundation/frontend-architecture.md` | BLOCKED by workspace discovery |
| 40 | Workspace/UI Specifications | `docs/foundation/ui/` | BLOCKED by workspace discovery |
| 41 | Testing Strategy | `docs/foundation/testing-strategy.md` | PLANNED |
| 42 | Acceptance Criteria | `docs/foundation/acceptance-criteria.md` | PLANNED |
| 43 | Traceability Matrix | `docs/foundation/traceability.md` | PLANNED |
| 44 | Backup & Recovery Strategy | `docs/foundation/backup-recovery.md` | PLANNED |
| 45 | Deployment Architecture | `docs/foundation/deployment.md` | BLOCKED by operational requirements |
| 46 | Observability Strategy | `docs/foundation/observability.md` | PLANNED |
| 47 | Performance Strategy | `docs/foundation/performance.md` | PLANNED |
| 48 | Data Governance | `docs/foundation/data-governance.md` | PLANNED |
| 49 | Change Management | `docs/foundation/change-management.md` | PLANNED |
| 50 | Foundation Certification Report | `docs/foundation/50-foundation-certification.md` | PROHIBITED until Gate 16 evidence exists |

## Modeling package

- `19-canonical-domain-model.md` — canonical technology-independent domain map
- `20-entity-relationship-registry.md` — business entities and relationships
- `21-governance-and-access-model.md` — authority, scope, roles, permissions, delegation, segregation of duties
- `22-lifecycle-and-control-model.md` — business lifecycle and exception model
- `23-finance-academic-hr-controls.md` — financial, academic, HR/payroll, privacy, and reporting controls
- `24-data-privacy-resilience-model.md` — data classification, audit, retention, recovery, and integrations
- `25-foundation-modeling-review.md` — modeling review and gate disposition

## Gate rule

The presence of a file is not evidence that its contents are complete. Every artifact must be cross-referenced, decision-aware, and evidence-gated before its owning gate can pass.

## Gate 1 artifact

- `26-gate-1-requirements-completeness-review.md` — requirements completeness audit and Gate 1 disposition

## Gate 2 artifact

- `27-gate-2-business-rule-completeness-review.md` — adversarial business-rule completeness audit and Gate 2 disposition

- `28-gate-3-domain-model-completeness-review.md` — initial adversarial domain model completeness review (failed)

## Gate 3 remediation package

- `29-canonical-entity-registry.md` — canonical entity inventory and ownership
- `30-source-of-truth-registry.md` — authoritative records and writer boundaries
- `31-relationship-registry.md` — cardinality, ownership, dating, transfer, and deletion
- `32-lifecycle-transition-registry.md` — entity states and valid transitions
- `33-authority-scope-operation-registry.md` — operation authority, scope, and segregation
- `34-financial-domain-model.md` — source financial entities and invariants
- `35-academic-evidence-decision-model.md` — evidence versus official decisions
- `36-hr-payroll-domain-model.md` — employment, entitlement, calculation, and payment separation
- `37-privacy-consent-disclosure-model.md` — consent, disclosure, verification, and retention
- `38-derived-data-lineage-registry.md` — derivation sources and reproducibility
- `39-domain-contracts.md` — domain ownership and cross-domain boundaries
- `40-configuration-domain-classification.md` — configuration, entities, facts, and derived data
- `41-gate-3-remediation-review.md` — independent remediation re-review and disposition


## Gate 4 architecture readiness package

- `42-architecture-boundary-model.md` — business and system boundary responsibilities
- `43-domain-boundary-contract-registry.md` — cross-domain fact contracts and failure behavior
- `44-authoritative-data-flow-model.md` — canonical fact ownership and flow
- `45-authorization-and-scope-architecture-contract.md` — authority and scope architecture contract
- `46-financial-architecture-contract.md` — financial truth and invariant contract
- `47-lifecycle-architecture-contract.md` — deterministic lifecycle contract
- `48-reporting-and-derived-data-contract.md` — reporting lineage and period contract
- `49-privacy-audit-resilience-architecture-contract.md` — privacy, history, audit, and resilience boundary
- `50-gate-4-architecture-readiness-review.md` — formal Gate 4 audit and disposition


## Gate 5 architecture package

The formal architecture package is maintained under `docs/architecture/`: artifacts `01`–`26` cover topology, style, module boundaries, dependencies, transactions, finance, authorization/scope, approvals, lifecycles, academics, HR/payroll, reporting, audit, privacy/documents, integrations, background work, consistency, errors, observability, resilience/security, testing, legacy migration, ADRs, invariants, traceability, and the Gate 5 review.


## Architecture state

- `docs/architecture/00-architecture-state.md` — current approved architecture state and implementation boundary

## Gate 6 implementation contract package

The implementation readiness package is maintained under `docs/implementation/`, artifacts `01`–`19`, covering gap audit, module/command contracts, finance, authorization, lifecycle, academic, HR/payroll, reporting, integration, concurrency, errors, testing, migration, legacy disposition, sequence, risks, and Gate 6 review.


## Implementation state

- `docs/implementation/00-implementation-state.md` — current package state and execution boundary
- `docs/implementation/20-package-01-contract-harness-checkpoint.md` — Package 01 certification and verification record
- `docs/implementation/21-implementation-quality-directive.md` — standing implementation quality and verification contract
- `docs/implementation/22-environment-blocker-report.md` — environment blocker evidence (Laravel/Composer unobtainable via Packagist) and its remediation/closure (2026-08-25); not a technology decision (ADR-013 authoritative; ADR-013-A rejected per D-F-103)
- `docs/implementation/23-environment-readiness.md` — environment-readiness plan and verification record; final status **ENVIRONMENT READY** (PHP 8.2.27 + Laravel 12.67.0 + PostgreSQL 18.4, verified 2026-08-25)

---

## HISTORICAL SOURCE: `foundation/04-decision-ledger.md`

# Foundation Decision Ledger

**Status:** Discovery in progress
**Rule:** Repository behavior and prior documentation are not silently promoted to user decisions.

| ID | Decision | Authority | Evidence | State |
|---|---|---|---|---|
| D-F-001 | Work in this session remains on `arena/01a034c7-toefl-house`. | Arena session constraint | Session context | DECIDED |
| D-F-002 | Existing implementation is reference-only; no production changes are made during Foundation Phase. | User directive | Directive §§3–4, §66 | DECIDED |
| D-F-003 | Target backend/database choice is not finalized. Laravel/PostgreSQL are preferred recommendations requiring research, not implementation authorization. | User directive | Directive §§18–19 | OPEN |
| D-F-004 | Organization, authority, position, role, permission, scope, and workspace policy must be confirmed progressively. | User directive | Directive §§6, 67–68 | DECIDED |
| D-F-005 | Structural organizational changes require operation-specific authority through an approval matrix. Sensitive structural changes must not depend on one uncontrolled actor. | O-10 | User response, 2026-08-24 | DECIDED |
| D-F-006 | A sensitive administrative action affecting another Owner requires at least two Owner approvals. Emergency suspension requires a separate, time-limited, fully audited workflow with mandatory review. | O-19 | User response, 2026-08-24 | DECIDED |
| D-F-007 | Sensitive approvals use risk/value/action-based thresholds varying by action type, amount, risk, scope, or consequence. Exact thresholds remain unknown and must not be invented. | O-20 | User response, 2026-08-24 | DECIDED / THRESHOLDS OPEN |
| D-F-008 | Scope administration is differentiated by scope type and authority through an explicit scope-assignment authority matrix. | O-23 | User response, 2026-08-24 | DECIDED |
| D-F-009 | Workspace priority is Owner/Executive, General Manager, Finance, Reception/Admissions, Academic Management, Teacher, HR/Payroll, Test Officer, Campus/Branch Manager, Marketing/Social Media, Library/Books, Operations/Facilities, Security/Guard, Student, Parent/Guardian, System Administration. This is priority only, not workflow specification. | O-25 | User response, 2026-08-24 | DECIDED / WORKFLOW OPEN |
| D-F-010 | For creation of the first campus or branch, the General Manager prepares the request and at least two Owners give final approval. The same rule applies to both campus and branch creation. | O-35 | User response, 2026-08-24 | DECIDED |
| D-F-011 | Permanent closure of a campus or branch requires a request prepared by the General Manager and final approval from at least two Owners. | O-36 | User response, 2026-08-24 | DECIDED |
| D-F-012 | Reopening a closed campus or branch requires a request prepared by the General Manager and final approval from at least two Owners. | O-37 | User response, 2026-08-24 | DECIDED |
| D-F-013 | Transferring a branch from one campus to another requires a request prepared by the General Manager and final approval from at least two Owners. | O-38 | User response, 2026-08-24 | DECIDED |
| D-F-014 | After a branch transfer, records may be visible under the new campus for current operations, but every historical record must retain its original campus, branch, date, and historical attribution. | C-F-001 clarification | User response, 2026-08-24 | DECIDED |
| D-F-015 | Changing the official name of a campus or branch requires a General Manager request and approval from at least two Owners. | O-40 | User response, 2026-08-24 | DECIDED |
| D-F-016 | Creating a new department or operational unit requires a General Manager request and approval from at least two Owners. | O-41 | User response, 2026-08-24 | DECIDED |
| D-F-017 | A department may have units at multiple organizational levels; for example, an organization-wide department may also have campus or branch units. | O-42 | User response, 2026-08-24 | DECIDED |
| D-F-018 | Every formally established active branch must have a designated Branch Manager responsible for day-to-day branch operations. | O-43 | User response, 2026-08-24 | DECIDED |
| D-F-019 | The General Manager proposes a Branch Manager appointment or replacement, and at least two Owners approve it. | O-44 | User response, 2026-08-24 | DECIDED |
| D-F-020 | A Branch Manager may manage daily branch operations and change branch-level operating rules within limits approved by the Owners or General Manager. | O-45 | User response, 2026-08-24 | DECIDED / LIMITS OPEN |
| D-F-021 | A Branch Manager may approve routine branch financial actions within formally defined limits; higher-risk or higher-value actions require Finance or Owner approval. | O-46 | User response, 2026-08-24 | DECIDED / LIMITS OPEN |
| D-F-022 | A Branch Manager's limited routine financial authority may include routine expenses, discounts, and refunds, subject to formally defined limits; higher-risk or higher-value actions require Finance or Owner approval. | O-47 | User response, 2026-08-24 | DECIDED / LIMITS OPEN |
| D-F-023 | A Branch Manager may prepare or request an expense, discount, or refund but must not approve their own request. Another authorized person must approve it. | O-48 | User response, 2026-08-24 | DECIDED |
| D-F-024 | A Branch Manager's routine expense request is normally approved by the Finance Manager; the General Manager is the only substitute approver when the Finance Manager is unavailable. Refund and discount requests are always approved by the General Manager. | O-49, O-50, O-51, O-52 | User responses, 2026-08-24 | DECIDED / EXPENSE LIMITS OPEN |
| D-F-025 | Branch Managers may use pre-approved limits for low-impact operating-rule changes. Changes affecting staff, students, schedules, safety, or money require separate approval. | O-53 custom response | User response, 2026-08-24 | DECIDED / IMPACT RULES OPEN |
| D-F-026 | A non-financial branch-level change affecting staff, students, schedules, or safety requires review by the relevant department head and final approval by the General Manager. | O-54 | User response, 2026-08-24 | DECIDED |
| D-F-027 | A branch-level change affecting money requires Finance Manager review and final approval by the General Manager. If Finance disagrees, the General Manager and Finance Manager must jointly prepare written explanations, and the matter must be escalated to at least two Owners before approval. If the Owners approve despite the disagreement, Finance records and reports the transaction while preserving the objection and approvals. | O-55 through O-59 | User responses, 2026-08-24 | DECIDED / FINANCIAL CONTROL DETAILS OPEN |
| D-F-028 | The General Manager may prepare or request a money-related action but must not give final approval to their own request. If the General Manager submits the request, the Finance Manager reviews it and at least two Owners give final approval. | O-60, O-61 | User responses, 2026-08-24 | DECIDED |
| D-F-029 | No Branch Manager, General Manager, Finance Manager, or Owner may approve a money-related request that directly benefits themselves. | O-62 | User response, 2026-08-24 | DECIDED |
| D-F-030 | Emergency suspension of an Owner's access may be initiated only jointly by two other Owners; the mandatory review must occur within seven days; the two unaffected Owners decide whether access is restored, remains suspended, or is permanently changed; the action must remain fully audited. | O-63, O-64, O-65 | User responses, 2026-08-24 | DECIDED |
| D-F-031 | Granting or removing organization-wide access for a person requires approval from at least two Owners. | O-66 | User response, 2026-08-24 | DECIDED |
| D-F-032 | An Owner must submit a request for organization-wide access, and another Owner must approve it; the two-Owner approval rule remains mandatory. | O-67 | User response, 2026-08-24 | DECIDED |
| D-F-033 | The General Manager may request campus-wide access for a person, but at least two Owners must approve it. | O-69 | User response, 2026-08-24 | DECIDED |
| D-F-034 | The Branch Manager may request branch-wide access only for people working in that Branch Manager's own branch, and the General Manager gives final approval. The General Manager may request cross-branch access for a person working in another branch when there is a documented operational reason; at least two Owners must approve that cross-branch request. Cross-branch access always has an automatic end date and requires renewal. | O-70 through O-74 | User responses, 2026-08-24 | DECIDED |
| D-F-035 | The General Manager proposes a Head of Department appointment or replacement, and at least two Owners approve it. | O-75 | User response, 2026-08-24 | DECIDED |
| D-F-036 | Head of Department authority differs by department; each head manages only the scope explicitly assigned to that department. | O-76 | User response, 2026-08-24 | DECIDED / DEPARTMENT SCOPES OPEN |
| D-F-037 | A Head of Department may recommend team-member appointments or replacements, but an authorized higher authority must approve them. | O-77 | User response, 2026-08-24 | DECIDED |
| D-F-038 | A Head of Department may make routine duty changes, but changes affecting pay, position, branch, or access require separate approval. | O-78 | User response, 2026-08-24 | DECIDED / APPROVAL DETAILS OPEN |
| D-F-039 | Every cross-branch assignment for a Head of Department has an automatic end date and requires renewal. | O-79 | User response, 2026-08-24 | DECIDED |
| D-F-040 | Access outside a department requires review by the responsible department head and final approval by the General Manager; sensitive or organization-wide access follows stronger Owner rules. | O-80 | User response, 2026-08-24 | DECIDED |
| D-F-041 | A Head of Department may not approve a decision that directly benefits themselves. | O-81 | User response, 2026-08-24 | DECIDED |

| D-F-042 | Final approval for a team-member appointment or replacement recommended by a Head of Department is given by the HR Manager; senior or sensitive positions remain subject to higher approval rules. | O-82 | User response, 2026-08-24 | DECIDED / SENIOR POSITION RULES OPEN |
| D-F-043 | A pay, allowance, bonus, or compensation change recommended by a Head of Department requires HR and Finance review, then General Manager approval; higher-risk cases escalate. | O-83 | User response, 2026-08-24 | DECIDED / THRESHOLDS OPEN |
| D-F-044 | Moving a team member between branches or departments requires review by current and receiving authorities, then General Manager approval. | O-84 | User response, 2026-08-24 | DECIDED |
| D-F-045 | Access changes caused by duty changes require department confirmation and approval by the General Manager or authorized security administrator according to scope. | O-85 | User response, 2026-08-24 | DECIDED |
| D-F-046 | A Head of Department may temporarily remove a team member from duties for an urgent safety, misconduct, or serious operational concern, with documentation and mandatory HR/General Manager review within a defined period. | O-86 | User response, 2026-08-24 | DECIDED / REVIEW PERIOD OPEN |
| D-F-047 | An acting Head of Department is recommended by the department and appointed by the General Manager for a defined start and end date. | O-87 | User response, 2026-08-24 | DECIDED |

| D-F-084 | Owner approval is required for senior, sensitive, or organization-wide positions; ordinary team appointments remain with HR and the General Manager. | O-88 | User response, 2026-08-24 | DECIDED / POSITION CATEGORIES OPEN |
| D-F-085 | An urgent temporary removal from duties requires HR and General Manager initial review within 24 hours. | O-89 | User response, 2026-08-24 | DECIDED / FULL REVIEW PERIOD OPEN |
| D-F-086 | During temporary removal, only protective access or scheduling restrictions may occur; permanent pay or employment changes require separate approval. | O-90 | User response, 2026-08-24 | DECIDED |
| D-F-087 | A Head of Department's own leave, expense, bonus, or schedule exception is approved by the General Manager after HR or Finance review as relevant; higher-risk matters escalate. | O-91 | User response, 2026-08-24 | DECIDED |
| D-F-088 | A Head of Department may approve routine team leave, schedule changes, or duty swaps within staffing rules; material effects require higher review. | O-92 | User response, 2026-08-24 | DECIDED / IMPACT RULES OPEN |
| D-F-089 | A Head of Department may formally evaluate team members; HR and the General Manager review consequences affecting pay, position, or employment. | O-93 | User response, 2026-08-24 | DECIDED |

| D-F-048 | At least two Owners approve appointments or replacements for General Manager, Finance Manager, HR Manager, Security lead, System Administrator, and every Head of Department; ordinary positions remain with HR and the General Manager. | O-94, C-F-002, O-75 | User responses, 2026-08-24 | DECIDED |
| D-F-049 | After an urgent temporary removal, the full employment decision must be completed within seven days. | O-95 | User response, 2026-08-24 | DECIDED |
| D-F-050 | HR reviews a permanent employment decision after urgent removal; the General Manager decides, with serious or senior cases escalated. | O-96 | User response, 2026-08-24 | DECIDED |
| D-F-051 | HR may approve ordinary appointments within approved staffing plans; unusual cost, authority, or sensitivity requires higher approval. | O-97 | User response, 2026-08-24 | DECIDED / EXCEPTION RULES OPEN |
| D-F-052 | A routine leave, schedule change, or duty swap affecting minimum staffing or student service requires Head recommendation and Branch Manager or General Manager approval. | O-98 | User response, 2026-08-24 | DECIDED |
| D-F-053 | HR reviews every formal performance evaluation before it is used for promotion, pay, discipline, or termination. | O-99 | User response, 2026-08-24 | DECIDED |

| D-F-054 | The relevant Head of Department may request department-level access for a person. | O-100 | User response, 2026-08-24 | DECIDED |
| D-F-055 | Department-level access requires department confirmation and final General Manager approval; sensitive or organization-wide access follows stronger Owner rules. | O-101 | User response, 2026-08-24 | DECIDED |
| D-F-056 | Every department-level access assignment has an automatic end date and requires renewal. | O-102 | User response, 2026-08-24 | DECIDED |
| D-F-057 | Academic Management assigns teachers to classes or student groups. | O-103 | User response, 2026-08-24 | DECIDED |
| D-F-058 | A teacher may keep normal class access until the academic term ends after the class assignment ends. | O-104, O-106, C-F-004 | User responses, 2026-08-24 | DECIDED |
| D-F-059 | After an approved assignment ends, at least two Owners must approve or perform ordinary access removal when Owner authority is used; this aligns with the organization-wide access rule. | O-105, C-F-003 | User response, 2026-08-24 | DECIDED |

| D-F-061 | Academic Management approves a teacher handover period after assignment end. | O-107 | User response, 2026-08-24 | DECIDED |
| D-F-062 | When an assignment ends during an active examination, grading period, or assessment, Academic Management decides whether it continues. | O-108 | User response, 2026-08-24 | DECIDED |
| D-F-063 | A temporary assignment extension may be requested by the Branch Manager or Academic Management, depending on operational or academic reason. | O-109 | User response, 2026-08-24 | DECIDED |
| D-F-064 | Academic Management approves temporary teacher assignment extensions. | O-110 | User response, 2026-08-24 | DECIDED |
| D-F-065 | Every temporary access or assignment extension requires a reason and explicit end date. | O-111 | User response, 2026-08-24 | DECIDED |

| D-F-066 | After a teacher assignment ends, the teacher may retain access until the academic term ends for viewing, but access is read-only and cannot edit grades, attendance, or comments. | O-112 | User response, 2026-08-24 | DECIDED |

| D-F-067 | When a teacher assignment ends during active grading or assessment, Academic Management may edit or assign remaining grade and assessment work. | O-113 | User response, 2026-08-24 | DECIDED |

| D-F-068 | Academic Management edits to grades or assessment records after a teacher assignment ends require a reason and an audit record. | O-114 | User response, 2026-08-24 | DECIDED |

| D-F-069 | When a teacher assignment ends before term end, the former teacher, newly responsible academic authority, and relevant manager must be notified of the access and responsibility change, with minimum necessary student information. | O-115 | User response, 2026-08-24 | DECIDED |

| D-F-070 | A teacher may appeal removal of a class assignment or related access change through HR; HR investigates and records the outcome. | O-116 | User response, 2026-08-24 | DECIDED |

| D-F-071 | An appeal by a teacher against removal of a class assignment or related access pauses the reassignment until HR decides the appeal. | O-117 | User response, 2026-08-24 | DECIDED / STUDENT-CONTINUITY RISK OPEN |

| AD-001 | Academic Management may appoint a temporary replacement while an HR appeal is reviewed; the temporary assignment is dated, scope-limited, and notified. | Best-practice default under Discovery Execution Optimization Directive | Agent analysis, 2026-08-24 | AGENT-DECIDED DEFAULT |

| D-F-072 | The General Manager proposes new job positions and at least two Owners approve them. | O-118 | User response, 2026-08-24 | DECIDED |
| D-F-073 | An Owner submits permission changes; the responsible business authority reviews them; sensitive or organization-wide increases require at least two Owner approvals. | O-119 | User response, 2026-08-24 | DECIDED |
| D-F-074 | Any Owner may assign an existing role to a person after the role exists. | O-120 | User response, 2026-08-24 | DECIDED / CONFLICT-OF-INTEREST CONTROL OPEN |
| D-F-075 | Role access automatically ends when the underlying position, employment, or approved scope ends, unless a separate dated extension is approved. | O-121 | User response, 2026-08-24 | DECIDED |
| D-F-076 | Permission exceptions must be temporary, separately approved, specifically scoped, justified, dated, and auditable. | O-122 | User response, 2026-08-24 | DECIDED |
| D-F-077 | A person may hold multiple roles for multiple positions; each assignment is separately scoped and dated and combined authority is checked for conflicts. | O-123 | User response, 2026-08-24 | DECIDED |

| D-F-078 | O-124 selected that any Owner may assign any existing role to themselves. This conflicts with the established two-Owner requirement for organization-wide access and with separation-of-duties controls for sensitive powers. | O-124 vs D-F-031/D-F-073 | User response, 2026-08-24 | CONFLICT / CLARIFICATION REQUIRED |
| D-F-079 | At least two Owners must approve assignment of a sensitive role to another Owner. | O-125 | User response, 2026-08-24 | DECIDED |
| D-F-080 | A proposed permission combination that permits one person to request, approve, and record the same financial action may be approved by the General Manager when operationally necessary. | O-126 | User response, 2026-08-24 | DECIDED / CONTROL EXCEPTION OPEN |
| D-F-081 | A person may request additional access for themselves, but cannot approve or grant it. | O-127 | User response, 2026-08-24 | DECIDED |
| D-F-082 | A requester should receive the reason when a permission change is rejected, without disclosure of confidential reviewer information. | O-128 | User response, 2026-08-24 | DECIDED |
| D-F-083 | Sensitive or organization-wide access must be reviewed at least annually. | O-129 | User response, 2026-08-24 | DECIDED |

| D-F-096 | Consolidated decisions MD-001 through MD-065 are accepted as the current TOEFL House business-policy baseline; earlier compatible decisions remain active, and conflicting earlier recommendations are superseded. | Consolidated user decision document | User response, 2026-08-24 | DECIDED |
| D-F-097 | Residual exact campus/branch inventory, legal succession details, agreement-specific scholarship/funding rules, and additional contract variations are recorded as non-blocking UNKNOWN items and must be resolved before their specific configuration or implementation. | OPEN-01 through OPEN-04 | User response, 2026-08-24 | UNKNOWN / NON-BLOCKING |
| D-F-098 | The earlier agent default allowing temporary replacement during a teacher reassignment appeal is superseded by the user decision to pause reassignment; no replacement is assumed without explicit policy. | O-117 and AD-001 | User response, 2026-08-24 | SUPERSEDED |
| D-F-099 | The earlier permissive permission-combination answer is superseded by the consolidated rule that one person must never initiate, approve, record, and reconcile the same financial transaction. | MD-007 | User response, 2026-08-24 | SUPERSEDED |

| G1-D-001 | Approval thresholds are configurable by action type with fail-closed behavior when no applicable threshold exists. | G1-B01 | User response, 2026-08-25 | USER-DECIDED |
| G1-D-002 | Refunds or discounts outside standard policy require Finance review and General Manager final approval; higher-risk cases escalate. | G1-B02 | User response, 2026-08-25 | USER-DECIDED |
| G1-C-001 | G1-B03 selected General Manager approval for all expenses, conflicting with O-51/O-52, which establish Finance Manager as normal approver and General Manager only as substitute. | G1-B03 vs O-51/O-52 | User response, 2026-08-25 | CONFLICT / CLARIFICATION REQUIRED |
| G1-D-004 | Compensation changes, restricted-fund allocations, material asset disposal, and financial-period reopening require approval from at least two Owners. | G1-B04 | User response, 2026-08-25 | USER-DECIDED |

| G1-D-003 | Routine Branch Manager expense requests are normally approved by the Finance Manager; the General Manager is the only substitute when Finance is unavailable. | G1-C-001 clarification | User response, 2026-08-25 | USER-DECIDED; supersedes G1-B03 interpretation |

| G2-D-001 | Refunds/cancellations are permitted only under documented conditions; original payment remains immutable and Finance records the refund. | G2-B01 | User response, 2026-08-25 | USER-DECIDED |
| G2-D-002 | Discounts may be applied only when published or separately approved, with eligibility, dates, and audit; reversal is a controlled correction. | G2-B02 | User response, 2026-08-25 | USER-DECIDED |
| G2-D-003 | If no program-specific progression rule exists, a student does not advance automatically; Academic Management must review and decide. | G2-B03 | User response, 2026-08-25 | USER-DECIDED |
| G2-D-004 | If an employment contract is silent on absence, overtime, advances, or deductions, Payroll holds the affected entry for HR and Finance review and does not invent a charge or payment. | G2-B04 | User response, 2026-08-25 | USER-DECIDED |
| G2-D-005 | If a restricted-fund agreement is silent after its supported student or program ends, funds remain restricted and on hold until authorized clarification. | G2-B05 | User response, 2026-08-25 | USER-DECIDED |
| D-F-100 | Application technology is PHP with the Laravel framework; the database is PostgreSQL. The system remains a modular monolith with strict contexts. This resolves the previously open D-F-003 and legacy findings L-002/L-003; ADR-013 records the architectural decision. | User response, 2026-08-25 | USER-DECIDED; supersedes D-F-003 |
| D-F-101 | The Implementation Quality Directive is adopted as the mandatory standing engineering standard for every implementation artifact in every package, codified at `docs/implementation/21-implementation-quality-directive.md`. It does not by itself authorize any package. | User directive, 2026-08-25 | USER-DIRECTED |
| D-F-102 | Environment-forced stack substitution: PHP + PostgreSQL remain; the Laravel framework is replaced by a framework-free modular monolith because Composer/Packagist/getcomposer.org/raw.githubusercontent.com are unreachable in the build environment (SSL connect failures verified 2026-08-25). Approved architecture intent (modular monolith, module ownership, policy authorization, owner transactions, context state machines, append-only audit) is preserved; recorded as ADR-013-A and `docs/implementation/22-environment-blocker-report.md`. Amends D-F-100. | User decision, 2026-08-25 | USER-DECIDED; framework substitution REJECTED by D-F-103 (2026-08-25) — the environment limitation is a blocker, not a technology decision; only the environment-blocker evidence portion of this row stands |
| D-F-103 | Correction of D-F-102: the approved technology decision remains PHP + Laravel + PostgreSQL as a strict modular monolith (ADR-013, D-F-100 authoritative). ADR-013-A is rejected/withdrawn and does not take effect; the framework-free PHP variant is not adopted and no framework substitution is authorized. The inability to obtain Laravel/Composer/Packagist in the current environment is an environment blocker, not a technology decision; the statement in ADR-013-A that a user decision selected the framework-free variant is not an approved user decision. Package 02 (Identity and Organization) must not begin production implementation while the approved Laravel dependency cannot be reproducibly obtained; status is IMPLEMENTATION BLOCKED BY ENVIRONMENT. Business rules, architecture, module boundaries, and implementation contracts are unchanged. No production code is authorized by this correction. | User correction, 2026-08-25 | USER-DIRECTED; supersedes D-F-102 framework substitution; ADR-013 remains in force |

Existing repository decisions about currency, language, notifications, treasury thresholds, backup, placement, finance, or prior work packages remain legacy evidence. They are not part of this session's approved business decision set until explicitly confirmed.

## Decision protocol

Each future critical decision must include the plain-language question, options, recommendation, operational consequences, affected domains, rules, invariants, permissions/scopes, and the user's explicit decision.

## Gate 3 remediation decisions

- **D-G3-001:** Financial balances are derived from posted source facts; payment, refund, discount, adjustment, reversal, journal, allocation, and reconciliation remain distinct.
- **D-G3-002:** Academic evidence is not an official academic decision; appeals and corrections preserve prior history.
- **D-G3-003:** Contractual entitlement, payroll calculation, payroll result, and actual payment remain distinct.
- **D-G3-004:** Authority is modeled as Position + Assignment + Permission + Scope + Policy, with default deny and expiration for temporary authority.
- **D-G3-005:** Gate 3 remediation passes with non-blocking open items; Gate 4 is not automatically started.

---

## HISTORICAL SOURCE: `foundation/05-risk-register.md`

> **Historical-status notice (2026-09-07):** This risk register contains Foundation-era risks and their then-current dispositions. Several technology/implementation entries are now historical because Laravel/PostgreSQL implementation subsequently proceeded; use `docs/architecture/current-state-compliance.md` for current-state status. Preserve the original risk evidence.

# Foundation Risk and Contradiction Register

| ID | Type | Risk / contradiction | Impact | Control | Status |
|---|---|---|---|---|---|
| R-F-001 | Foundation boundary | Existing branch contains production implementation although the directive prohibits production implementation before certification. | High: greenfield claims could be false. | Keep current work documentation-only; classify existing code as reference evidence. | OPEN |
| R-F-002 | Architecture | Current implementation uses Express/TypeScript + SQLite; directive lists Laravel + PostgreSQL as preferred. | High: affects domain, persistence, deployment, testing, and API design. | Ask architecture decision only after business discovery; do not silently migrate or extend. | TECHNOLOGY DECIDED 2026-08-25: PHP + Laravel + PostgreSQL, strict modular monolith (ADR-013, ledger D-F-100). IMPLEMENTATION BLOCKED BY ENVIRONMENT: Laravel/Composer unobtainable (blocker report `22-environment-blocker-report.md`; ADR-013-A framework substitution rejected, ledger D-F-103). Package 02 NOT STARTED |
| R-F-003 | Business policy | Existing bootstrap hard-codes Kabul Campus/Main Branch values. | High: may encode seed convenience as institutional policy. | Confirm organization facts and seed policy with user. | OPEN |
| R-F-004 | Authority | Existing docs contain prior decisions and certifications. | High: stale or unapproved decisions may be mistaken for requirements. | Treat as historical evidence; record explicit confirmation before promotion. | OPEN |
| R-F-005 | Completeness | Existing domain/test breadth may create false confidence about omitted domains, actors, failure paths, and traceability. | High: certification could be invalid. | Use directive domain checklist and registries; no certification by test count. | OPEN |
| R-F-006 | Calendar | Existing code contains date/calendar implementation, but business period semantics have not been user-confirmed in this session. | High: affects finance, payroll, academic periods, and reporting. | Calendar discovery before implementation. | OPEN |
| R-F-007 | Finance | Existing financial behavior is substantial, but accounting policy and authority must be confirmed independently. | Critical: money and historical truth. | Financial decision cluster; fail closed. | OPEN |
| R-F-008 | Authorization | Existing RBAC/ABAC behavior is evidence, not proof of complete position/permission/scope/projection policy. | Critical: privilege escalation and branch leakage. | Model organization and authorization before implementation. | OPEN |

---

## HISTORICAL SOURCE: `foundation/06-foundation-purity-verification.md`

# Foundation Purity Verification Report

**Date:** 2026-08-24
**Phase:** Foundation / discovery
**Gate:** Gate 0 — Discovery Initialization
**Result:** `PASS — ACTIVE WORKSPACE PURIFIED`
**Implementation authorization:** `NONE`

## Active branch

`arena/01a034c7-toefl-house`

This is the only branch used. The Arena session fixes work to this branch; no alternate branch was created or checked out.

## Repository boundary

The Git repository remains `/home/user/TOEFL-House/.git`. Git history has not been deleted, rewritten, or replaced. The previous implementation remains recoverable from Git history, including the pre-purification HEAD `e9322b2`.

The active working tree is now a Foundation workspace. The repository root contains only the `docs/foundation/` Foundation artifact directory as an active project tree. There is no active application source tree, package manifest, runtime launcher, database schema, test tree, asset tree, or build configuration.

## Legacy location

Legacy implementation evidence originated in the repository tree at commit `e9322b2`, including:

- `src/`
- `server/src/`
- `server/fixtures/`
- `server/scripts/`
- `public/`
- root and server package/build configuration
- prior non-foundation documentation and certification records

Those paths have been removed from the active tree using Git-tracked deletions. Their history remains preserved and can be inspected with Git without placing implementation in the Foundation workspace.

Legacy material is classified as **LEGACY EVIDENCE** only. It is not automatically a requirement, business rule, policy, authority, architecture, schema, API contract, or implementation.

## New Foundation location

`docs/foundation/`

Current Foundation artifacts:

- `00-foundation-state.md`
- `01-legacy-system-intelligence-report.md`
- `02-organization-discovery.md`
- `03-document-index.md`
- `04-decision-ledger.md`
- `05-risk-register.md`
- `06-foundation-purity-verification.md`

## Files permitted in Foundation

Only controlled, implementation-independent artifacts are permitted at this stage:

- foundation documentation;
- requirements and open-question registers;
- decision records;
- domain discovery records;
- business-rule records;
- authorization, scope, and workspace models;
- source-of-truth and invariant registries;
- traceability records;
- risk and threat models;
- architecture decision records;
- gate status and verification reports;
- other explicitly approved Foundation artifacts.

## Files prohibited in Foundation

The following are prohibited until Gate 16 Foundation Certification:

- Laravel application code;
- PostgreSQL schemas;
- SQLite schemas;
- migrations;
- API or route implementations;
- React or other UI application code;
- CRUD;
- production configuration;
- runtime services;
- seeds and fixtures containing implementation behavior;
- application tests presented as production implementation evidence;
- copied or imported legacy source;
- legacy business logic or compatibility layers.

## Import and implementation confirmation

- No React code has been imported.
- No Express code has been imported.
- No TypeScript application code has been imported.
- No SQLite schema has been imported.
- No legacy routes, services, domain logic, components, tests, migrations, configuration, seeds, or business rules have been imported.
- No new production implementation exists in the active tree.
- No production database or migration was created.
- No API, UI, CRUD, or framework scaffold was created.

## Remediation performed

1. Inspected the existing repository and recorded the current-state evidence before modifying the active tree.
2. Created the initial Foundation documentation set under `docs/foundation/`.
3. Removed all previously tracked application, runtime, test, fixture, asset, package, schema, and non-foundation legacy documentation files from the active branch tree.
4. Preserved the complete prior repository history in `.git` and retained the prior implementation as recoverable Git evidence.
5. Recorded this boundary verification before any further discovery or implementation activity.

## Verification evidence

The following checks were performed after remediation:

- `git status --short --branch` confirms the active branch remains `arena/01a034c7-toefl-house`.
- `git diff --name-status` shows deletions of the prior application tree and additions only under `docs/foundation/`.
- `find . -mindepth 1 -maxdepth 1 -not -name .git -not -name docs` returns no active root-level project files.
- `find docs -mindepth 1 -maxdepth 1 -not -name foundation` returns no active non-foundation documentation directories.
- Active Foundation files contain documentation only; no executable source, schema, migration, package manifest, route, component, test, or runtime configuration exists.
- `git show e9322b2:server/src/index.ts` and `git show e9322b2:src/App.tsx` remain available as historical evidence, proving history was preserved rather than destroyed.

## Remaining contamination risks

| Risk | Control | State |
|---|---|---|
| Git history contains legacy implementation | History is provenance only; future work must not copy/import without an explicit reuse decision | OPEN / CONTROLLED |
| Foundation documents reference historical paths | References are explicitly labeled evidence and point to the preserved prior commit | CONTROLLED |
| Future contributor adds application code early | Gate 0 and the prohibited-file list must be checked before every change | OPEN / CONTROLLED |
| Existing user decisions may be mixed with legacy documentation | Only explicit decisions in the Foundation decision ledger are authoritative | CONTROLLED |
| The fixed session branch name does not itself prove greenfield purity | Active-tree verification, not branch name, is the purity criterion | CONTROLLED |

## Gate disposition

The active workspace passes the **purity boundary check**. This does **not** pass Gate 0 as a whole, and it does not pass any later gate.

**Current gate state:** Gate 0 — `IN_PROGRESS`

**STOP CONDITION:** No implementation may begin. Continue only with documented discovery after the user authorizes continuation and answers the open organization questions.

---

## HISTORICAL SOURCE: `foundation/07-batch-01-organization-discovery.md`

# Batch 01 — Organization and Access Discovery

**Status:** QUESTIONS ISSUED
**Gate:** Gate 0 — Discovery Initialization
**Authority:** User decision required
**Date:** 2026-08-24

Previously established decisions are not repeated here: TOEFL HOUSE is the institutional system; the hierarchy must support Organization → Campus → Branch → Department/Operational Unit; multiple campuses and branches are required from day one; there are three equal Owners; one person may hold multiple positions; one person may work across authorized scopes; authorization is server-enforced, minimum-necessary, and default-deny; workspaces are role-specific; the system interface and technical identifiers are English; Solar Hijri is the business calendar and Gregorian/ISO is the technical calendar.

## Organization and ownership

### O-01 — Legal and operating organization
**Question:** Should the first release operate one TOEFL House organization, while keeping the structure ready for future separate organizations, or must multiple independent organizations be supported immediately?
**Why it matters:** It determines the highest ownership boundary for people, money, reports, settings, and access.
**Options:** A) One organization now, future-ready structure; B) Multiple independent organizations now; C) Unknown.
**Recommended option:** A, unless separate legal entities already need isolated data.
**User decision:**

### O-02 — Campus definition
**Question:** What should a campus represent in daily operations?
**Why it matters:** A campus can be only a grouping, or it can own staff, departments, resources, reports, and approvals.
**Options:** A) Physical/administrative collection of branches; B) Physical site that may also operate directly; C) Both, depending on configuration; D) Unknown.
**Recommended option:** C, with branches as the normal operating units.
**User decision:**

### O-03 — Current campuses
**Question:** Which campuses exist today, and which are planned? Provide name, city/location, and whether each is active.
**Why it matters:** Names and status affect organization records, reporting, and access scopes.
**Options:** A) Provide the list; B) Only one campus currently; C) Unknown.
**Recommended option:** A.
**User decision:**

### O-04 — Current branches
**Question:** Which branches exist under each campus? Provide branch name, location, manager if known, and active/planned status.
**Why it matters:** Branch is the primary operational and isolation boundary for many academic, reception, staff, and financial activities.
**Options:** A) Provide the list; B) Only one branch currently; C) Unknown.
**Recommended option:** A.
**User decision:**

### O-05 — Branch ownership
**Question:** Should every branch belong to exactly one campus at a time?
**Why it matters:** This controls reporting, access isolation, financial attribution, and historical transfers.
**Options:** A) Yes, exactly one campus; B) A branch may serve multiple campuses; C) Branches can move, but history must be retained; D) Unknown.
**Recommended option:** C with one active campus at a time.
**User decision:**

### O-06 — Branch independence
**Question:** Which policies may differ by branch?
**Why it matters:** It determines configuration scope and prevents accidental organization-wide changes.
**Options:** A) Only operating hours and rooms; B) Academic, fees, staffing, schedules, and operations; C) Nearly all policies, with organization-wide controls for accounting and security; D) Unknown.
**Recommended option:** C.
**User decision:**

## Departments and management

### O-07 — Department list
**Question:** Which departments or operational units exist or are planned? Select all that apply and add missing ones.
**Options:** Academic; Reception/Admissions; Finance/Accounting; HR/Payroll; Marketing/Social Media; Testing/Examinations; Library/Books; Facilities/Cleaning; Security/Guard; IT/System Administration; Other: ____.
**Why it matters:** Departments organize responsibility, reporting, approval authority, and department-scoped access.
**Recommended option:** Include every unit that owns a queue, decision, record, or recurring task.
**User decision:**

### O-08 — Department placement
**Question:** Must every department belong to one branch, or may a department operate across a campus or the whole organization?
**Why it matters:** It defines whether department access can cross branch boundaries.
**Options:** A) Every department is branch-specific; B) Departments may be branch, campus, or organization-wide; C) One department can have several scoped units; D) Unknown.
**Recommended option:** B.
**User decision:**

### O-09 — Management positions
**Question:** Which positions have management authority, and over what scope?
**Options:** Owner; General Manager; Campus Manager; Branch Manager; Head of Department; Academic Manager; Finance Manager; HR Manager; Other: ____.
**Why it matters:** A title alone must not grant access; the system needs explicit responsibility and scope.
**Recommended option:** Name each position and assign organization, campus, branch, or department scope explicitly.
**User decision:**

### O-10 — Authority for organizational changes
**Question:** Who may create, rename, deactivate, transfer, or reopen organizations, campuses, branches, and departments?
**Why it matters:** Structural changes affect every permission, report, record owner, and financial posting.
**Options:** A) Any Owner; B) Owners jointly for structural changes; C) Owner creates, General Manager manages, Owners approve closure; D) Different authority by change type; E) Unknown.
**Recommended option:** D with explicit approval thresholds.
**User decision:**

### O-11 — Acting and delegated authority
**Question:** May a person temporarily act for another position or receive delegated authority?
**Why it matters:** Temporary access must expire, remain auditable, and not become a permanent privilege by accident.
**Options:** A) No delegation; B) Delegation allowed only with start/end dates; C) Delegation allowed without expiry; D) Unknown.
**Recommended option:** B.
**User decision:**

## Positions, roles, and people

### O-12 — Position register completion
**Question:** Confirm whether these are needed now or later: Campus/Branch Manager, Academic Coordinator/Registrar, Admissions Officer, Accountant/Cashier, HR/Payroll Officer, Librarian/Book Custodian, IT Administrator, Auditor, Student, Parent/Guardian, Driver/Transport, Maintenance, and external instructor.
**Why it matters:** Missing positions create missing workspaces, permissions, queues, and accountability.
**Options:** A) Current; B) Planned; C) Not needed; D) Unknown — provide status per item.
**Recommended option:** Provide a status for every item and add missing positions.
**User decision:**

### O-13 — Gendered receptionist titles
**Question:** Are “Male Receptionist” and “Female Receptionist” separate formal positions, or one Receptionist position describing current staffing?
**Why it matters:** Formal gender-based positions would affect staffing, reporting, and access design; staffing characteristics should not accidentally become authorization rules.
**Options:** A) One Receptionist position; B) Separate formal positions; C) One position with optional staffing attributes; D) Unknown.
**Recommended option:** A or C; do not use gender as an access rule.
**User decision:**

### O-14 — Position versus role
**Question:** Should a position describe a person’s job and a role describe the permissions attached to it, allowing one position to use more than one role when necessary?
**Why it matters:** Separating job title from permissions supports least privilege and controlled exceptions.
**Options:** A) Separate position and role; B) Position and role are always identical; C) Unknown.
**Recommended option:** A.
**User decision:**

### O-15 — Multiple positions
**Question:** The directive establishes that one person may hold multiple positions. Must each position assignment have its own scope, start date, end date, supervisor, and approval record?
**Why it matters:** Without assignment history, access cannot be safely removed or explained later.
**Options:** A) Yes, all fields; B) Scope and dates only; C) No assignment history; D) Unknown.
**Recommended option:** A.
**User decision:**

### O-16 — Individual accounts
**Question:** Must every human operator use a personal account, with no shared reception, finance, or manager accounts?
**Why it matters:** Individual accounts are required for trustworthy audit trails and accountability.
**Options:** A) Individual accounts only; B) Shared accounts allowed for low-risk work; C) Shared accounts allowed with operator selection; D) Unknown.
**Recommended option:** A.
**User decision:**

### O-17 — Employment and access end
**Question:** When a person leaves a position or employment, should access stop immediately, at a scheduled time, or after a manager confirms handover?
**Why it matters:** Delayed deactivation creates security risk; immediate deactivation may interrupt handover.
**Options:** A) Immediately; B) Scheduled end time; C) Manager confirmation, with a hard maximum; D) Unknown.
**Recommended option:** C with an automatic hard end date.
**User decision:**

## Owners and sensitive authority

### O-18 — Equal Owner permissions
**Question:** The three Owners are equal. Should each Owner have the same maximum permission set, with restrictions coming only from explicit scope or approval policy?
**Why it matters:** It prevents an accidental primary-owner hierarchy while retaining control over sensitive actions.
**Options:** A) Same maximum permissions; B) Same authority but different operational scopes; C) Different permissions; D) Unknown.
**Recommended option:** A plus explicit approval rules for sensitive actions.
**User decision:**

### O-19 — Owner-to-Owner administration
**Question:** May any Owner create, suspend, reset, or change another Owner’s account and assignments?
**Why it matters:** Owner administration is a high-risk privilege and must have a clear safeguard.
**Options:** A) Any Owner may do so, fully audited; B) Requires two Owners; C) Requires all three Owners; D) Unknown.
**Recommended option:** B for sensitive changes; urgent suspension may be one Owner with later review.
**User decision:**

### O-20 — High-risk approvals
**Question:** Which actions require two-person or multi-Owner approval? Select all that apply: refunds, large discounts, financial adjustments, payroll approval, scholarship approval, sponsorship/funding allocation, bank/cash movement, asset disposal, permission escalation, branch closure, and data export.
**Why it matters:** Approval requirements protect money, access, historical records, and organizational continuity.
**Options:** A) None; B) All listed; C) Only selected actions — list them; D) Thresholds differ by action; E) Unknown.
**Recommended option:** D with explicit thresholds and no self-approval.
**User decision:**

## Permissions and scope

### O-21 — Scope hierarchy
**Question:** Confirm the allowed access scopes: organization, campus, branch, department, program, academic period, financial period, class, student, teacher, employee, financial resource, document, room, inventory location, report, and relationship-based access.
**Why it matters:** Every permission needs a boundary; unscoped access risks cross-branch exposure.
**Options:** A) All listed; B) Remove selected scopes — list them; C) Add scopes — list them; D) Unknown.
**Recommended option:** A, subject to resource-specific authorization.
**User decision:**

### O-22 — Cross-branch access
**Question:** When someone needs to work across branches, should access be granted explicitly for named branches, or should a broad position automatically cover all branches?
**Why it matters:** Broad defaults are a major source of horizontal privilege escalation.
**Options:** A) Named branches only; B) Campus-wide if assigned to a campus; C) Organization-wide only for explicitly approved positions; D) Unknown.
**Recommended option:** A, with narrowly approved organization-wide roles.
**User decision:**

### O-23 — Scope change authority
**Question:** Who may grant, change, or remove a person’s campus, branch, department, class, financial, or resource scope?
**Why it matters:** Scope changes are access changes and require approval, expiry, and audit.
**Options:** A) System Administrator; B) Direct manager; C) HR plus responsible department manager; D) Owners/authorized security administrator; E) Different by scope type; F) Unknown.
**Recommended option:** E.
**User decision:**

### O-24 — Profile projections
**Question:** Confirm that each position receives only the information needed for its work rather than a full person/student/employee profile.
**Why it matters:** Server-side projections reduce privacy exposure and prevent frontend-only hiding.
**Options:** A) Required everywhere; B) Full profiles for managers; C) Full profiles for Owners only; D) Unknown.
**Recommended option:** A, with explicitly approved expanded projections.
**User decision:**

## Workspaces

### O-25 — Workspace priority
**Question:** Rank the first workspaces to design: Owner/Executive, General Manager, Campus/Branch Manager, Reception/Admissions, Finance, Academic Management, Teacher, HR/Payroll, Test Officer, Marketing, Library/Books, Operations/Facilities, Security/Guard, System Administration, Student, Parent/Guardian.
**Why it matters:** Workspace design must follow real daily work, queues, exceptions, and decisions rather than a generic dashboard.
**Options:** A) Provide a ranking; B) All equal; C) Start with the three most urgent; D) Unknown.
**Recommended option:** C, then expand by operational dependency.
**User decision:**

### O-26 — Workspace queues
**Question:** For each of your top three workspaces, what must be visible first: urgent exceptions, approvals waiting for the user, today’s tasks, messages, KPIs, or search?
**Why it matters:** This determines the primary work surface and reduces errors and cognitive load.
**Options:** A) Approvals; B) Exceptions/alerts; C) Today’s task queue; D) KPIs; E) Search; F) Different per workspace — specify.
**Recommended option:** F.
**User decision:**

### O-27 — Workspace actions
**Question:** For each top workspace, list the three actions users perform most often and the three actions they must never be able to perform.
**Why it matters:** Workspaces must make frequent work easy and forbidden actions impossible, not merely hide menu items.
**Options:** A) Provide lists per workspace; B) Use recommended operational defaults; C) Unknown.
**Recommended option:** A.
**User decision:**

### O-28 — Student and parent access
**Question:** Should students and parents/guardians have separate portals, and what may each see or do?
**Why it matters:** Their identity, relationship, privacy, payments, academic information, and communication rights differ.
**Options:** A) Student portal only; B) Parent/guardian portal only; C) Separate portals; D) One portal with relationship-based views; E) No portal initially; F) Unknown.
**Recommended option:** C or D with separate relationship permissions.
**User decision:**

## Required answer format

Please reply using the IDs, for example:

`O-01: A — one organization now, future-ready.`

For list questions, provide the list directly. Unanswered questions remain `UNKNOWN` and block the affected Gate 0 decisions.

---

## HISTORICAL SOURCE: `foundation/08-requirements-registry.md`

# Foundation Requirements Registry

**Status:** Gate 1 review — blocked pending requirements remediation

| ID | Requirement | Source | Classification | Status |
|---|---|---|---|---|
| REQ-ORG-001 | Support Organization → Campus → Branch → Department/Operational Unit. | Directive | DECIDED requirement | OPEN: details pending |
| REQ-ORG-002 | Support multiple campuses and branches from day one. | Directive | DECIDED requirement | OPEN: policy pending |
| REQ-ORG-003 | Three Owners have equal authority; no artificial primary Owner. | Directive | DECIDED requirement | DECIDED |
| REQ-ORG-004 | Structural changes use an explicit operation-specific approval matrix. | O-10 | AUTHORITATIVE BUSINESS DECISION | OPEN: remaining operation rows pending |
| REQ-ORG-005 | The General Manager prepares creation requests for campuses and branches; at least two Owners must approve before creation. | O-35 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-008 | Permanent campus or branch closure requires a General Manager request and approval from at least two Owners. | O-36 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-009 | Reopening a closed campus or branch requires a General Manager request and approval from at least two Owners. | O-37 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-010 | Transferring a branch between campuses requires a General Manager request and approval from at least two Owners. | O-38 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-011 | After transfer, records may be visible under the new campus for current operations, but historical campus, branch, date, and attribution remain immutable. | C-F-001 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-012 | Every formally established active branch must have a designated Branch Manager for daily operations. | O-43 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-013 | A Branch Manager may manage daily operations and branch-level operating rules within approved limits; those limits must be discovered. | O-45 | AUTHORITATIVE BUSINESS DECISION | OPEN: limits pending |
| REQ-FIN-001 | A Branch Manager may approve routine branch financial actions within formally defined limits; higher-risk or higher-value actions require Finance or Owner approval. | O-46 | AUTHORITATIVE BUSINESS DECISION | OPEN: financial limits pending |
| REQ-FIN-002 | Eligible routine Branch Manager financial actions include expenses, discounts, and refunds, subject to formally defined limits. | O-47 | AUTHORITATIVE BUSINESS DECISION | OPEN: limits and exclusions pending |
| REQ-ORG-014 | Head of Department authority varies by department; each head operates only within the explicitly assigned department scope. | O-76 | AUTHORITATIVE BUSINESS DECISION | OPEN: department scopes pending |
| REQ-ACCESS-004 | Heads of Department may recommend appointments and replacements, but higher authority must approve them. | O-77 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-015 | Heads of Department may change routine duties; changes affecting pay, position, branch, or access require separate approval. | O-78 | AUTHORITATIVE BUSINESS DECISION | OPEN: approval details pending |
| REQ-ACCESS-005 | Every cross-branch Head of Department assignment has an automatic end date and requires renewal. | O-79 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ACCESS-006 | Access outside a department requires responsible-department review and General Manager approval, with stronger Owner rules for sensitive or organization-wide access. | O-80 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ACCESS-007 | A Head of Department may not approve a decision that directly benefits themselves. | O-81 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-006 | Sensitive actions affecting another Owner require at least two Owner approvals. | O-19 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ORG-007 | Sensitive approvals use risk/value/action-based thresholds; exact thresholds must be discovered and never invented. | O-20 | AUTHORITATIVE BUSINESS DECISION | OPEN: thresholds pending |
| REQ-ACCESS-001 | Scope administration uses a differentiated scope-assignment authority matrix. | O-23 | AUTHORITATIVE BUSINESS DECISION | OPEN: authority rows pending |
| REQ-ACCESS-002 | Authorization is server-enforced, default-deny, minimum-necessary, and scope-aware. | Directive | DECIDED requirement | OPEN: business matrix pending |
| REQ-ACCESS-003 | A person may hold multiple positions and work across explicitly authorized scopes. | Directive | DECIDED requirement | OPEN: lifecycle pending |
| REQ-WORK-001 | Workspace design follows the accepted priority order, without treating priority as workflow specification. | O-25 | AUTHORITATIVE BUSINESS DECISION | OPEN: responsibilities pending |

| REQ-ACCESS-008 | HR gives final approval for team-member appointments or replacements recommended by a Head of Department; senior or sensitive positions may require higher approval. | O-82 | AUTHORITATIVE BUSINESS DECISION | OPEN: senior-position rules pending |
| REQ-HR-001 | Pay, allowance, bonus, or compensation changes require HR and Finance review, then General Manager approval; higher-risk cases escalate. | O-83 | AUTHORITATIVE BUSINESS DECISION | OPEN: thresholds pending |
| REQ-ORG-016 | Staff transfers between branches or departments require current and receiving authority review, then General Manager approval. | O-84 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-ACCESS-009 | Duty-change access updates require department confirmation and approval by the General Manager or authorized security administrator according to scope. | O-85 | AUTHORITATIVE BUSINESS DECISION | DECIDED |
| REQ-HR-002 | A Head of Department may temporarily remove a team member for an urgent safety, misconduct, or serious operational concern, with documentation and mandatory HR/General Manager review. | O-86 | AUTHORITATIVE BUSINESS DECISION | OPEN: review period pending |
| REQ-ORG-017 | Acting Heads of Department are recommended by the department and appointed by the General Manager for defined start and end dates. | O-87 | AUTHORITATIVE BUSINESS DECISION | DECIDED |

No requirement in this registry authorizes production implementation.

## Gate 1 quality status

The registry is materially incomplete for an implementation contract. See `26-gate-1-requirements-completeness-review.md` for missing acceptance, lifecycle, authority, scope, financial, academic, HR, privacy, reporting, and resilience requirements.

## Gate 1 blocker decisions

| ID | Requirement | Classification | Status |
|---|---|---|---|
| G1-REQ-001 | Approval thresholds are action-specific, configurable, and fail closed when missing. | USER-DECISION | DECIDED |
| G1-REQ-002 | Out-of-policy refunds and discounts require Finance review and General Manager approval; higher-risk cases escalate. | USER-DECISION | DECIDED |
| G1-REQ-003 | Compensation changes, restricted-fund allocations, material asset disposal, and financial-period reopening require at least two Owner approvals. | USER-DECISION | DECIDED |
| G1-REQ-004 | Routine Branch Manager expenses are approved by Finance Manager; General Manager is the only substitute when Finance is unavailable. | G1-C-001 | USER-DECISION | DECIDED |


## Gate 2 business-rule decisions

- G2-REQ-001: Refunds and cancellations require documented conditions, approval, immutable source payment, and Finance recording.
- G2-REQ-002: Discounts require published or separately approved eligibility, dates, audit, and controlled reversal.
- G2-REQ-003: No automatic progression occurs without a program-specific rule; Academic Management must decide.
- G2-REQ-004: Contract-silent payroll entries are held for HR and Finance review.
- G2-REQ-005: Contract-silent restricted funds remain on hold and restricted.

## Gate 3 domain-model requirements

The Gate 3 remediation package establishes explicit requirements that every material money, authority, identity, academic, employment, privacy, historical, reporting, and reconciliation concept has a canonical owner, source record, relationship, lifecycle, authority path, and lineage rule. See artifacts `29`–`40` and the independent disposition in `41`.


## Gate 4 architecture-readiness requirements

The Foundation must translate to architecture through explicit domain boundaries, one authoritative owner per critical fact, authorized mutations, deterministic lifecycles, non-circular contracts, derived-only reporting, configuration isolation, privacy/audit history, and resilience requirements. These are documented in artifacts `42`–`49` and reviewed in `50`.


## Gate 5 architecture implications

Requirements are mapped to owner-bound modules, server authorization, transaction boundaries, financial invariants, append-only history, privacy gates, resilient integration contracts, and future test categories in `docs/architecture/25-architecture-traceability.md`. No business requirement was changed.


## Gate 6 disposition

Implementation contract mappings are recorded in `docs/implementation/02-implementation-contract.md` and `03-command-query-registry.md`; no business requirement was changed.

## Gate 7 Package 01 execution record

Package 01 established the implementation-contract and verification baseline without changing requirements or business behavior. See `docs/implementation/20-package-01-contract-harness-checkpoint.md`.

---

## HISTORICAL SOURCE: `foundation/09-authority-and-scope-matrices.md`

# Authority and Scope Matrices — Foundation Draft

**Status:** STRUCTURE DECIDED; ROWS OPEN

## Structural-change approval matrix

O-10 establishes the matrix requirement. Exact approvers and thresholds are not yet decided.

| Operation | Initiator | Required authority | Minimum approvals | Scope | Status |
|---|---|---|---:|---|---|
| Create organization | UNKNOWN | UNKNOWN | UNKNOWN | Organization | OPEN |
| Rename organization | UNKNOWN | UNKNOWN | UNKNOWN | Organization | OPEN |
| Deactivate/reactivate organization | UNKNOWN | UNKNOWN | UNKNOWN | Organization | OPEN |
| Create campus | General Manager | At least two Owners | General Manager after approval | Organization | DECIDED by O-35 |
| Rename/transfer campus | General Manager | At least two Owners for official rename; campus transfer remains open | General Manager after approval | Organization | DECIDED for official rename; campus transfer remains open |
| Close/reopen campus | General Manager | At least two Owners for closure or reopening | General Manager after approval | Organization | DECIDED for closure and reopening |
| Create branch | General Manager | At least two Owners | General Manager after approval | Campus | DECIDED by O-35 |
| Rename/transfer branch | General Manager | At least two Owners for official rename or campus transfer | General Manager after approval | Campus/Organization | DECIDED |
| Close/reopen branch | General Manager | At least two Owners for closure or reopening | General Manager after approval | Campus/Organization | DECIDED for closure and reopening |
| Create/change department | General Manager | At least two Owners for creation; other changes remain open | General Manager after approval | Branch/Campus/Organization | DECIDED for creation; other changes open |
| Appoint/replace Branch Manager | General Manager | At least two Owners | General Manager after approval | Branch | DECIDED by O-44 |
| Approve Branch Manager routine expense | Branch Manager requests | Finance Manager; General Manager is only substitute when Finance unavailable | Finance Manager or General Manager | Branch/financial resource | DECIDED by O-51/O-52; expense limits open |
| Approve Branch Manager refund/discount | Branch Manager requests | General Manager | General Manager | Branch/student financial resource | DECIDED by O-50 |
| Approve Branch Manager non-financial high-impact change | Branch Manager requests | Relevant department head reviews; General Manager approves | General Manager after review | Branch | DECIDED by O-54 |

Department placement may exist at organization, campus, and branch levels, including a parent department with scoped units.

## Scope-assignment authority matrix

O-23 establishes differentiated administration by scope type. The responsible authority for each row must be discovered.

| Scope type | Who may request | Who may approve | Who may execute | Expiry required? | Status |
|---|---|---|---|---|---|
| Organization | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Campus | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Branch | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Department | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Program | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Academic Period | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Financial Period | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Class | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Student | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Teacher | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Employee | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Financial Resource | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Document | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Room | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Inventory Location | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Report | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |
| Relationship | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | OPEN |

## Owner-sensitive administrative control

- Any sensitive action affecting another Owner requires at least two Owner approvals.
- A single Owner cannot independently perform such an action.
- Emergency suspension is not yet a complete workflow. It requires a separate definition covering permitted trigger, time limit, audit record, notification, and mandatory subsequent review.
- Risk/value thresholds are intentionally not populated until discovered through business questioning.

---

## HISTORICAL SOURCE: `foundation/10-domain-and-workspace-register.md`

# Domain and Workspace Register — Foundation Draft

## Domain status

| Domain | Current status | Missing discovery |
|---|---|---|
| Organization/Campus/Branch/Department | IN PROGRESS | Actual inventory, lifecycle, authority, transfer/closure |
| Identity/Positions/Roles/Permissions/Scopes | IN PROGRESS | Assignment lifecycle, matrices, delegation, emergency controls |
| Workspaces | PRIORITY DECIDED | Responsibilities, queues, actions, visibility, exceptions |
| People/Visitors/Admissions/Students | NOT STARTED | Actors and workflows |
| Academic domains | NOT STARTED | Programs, classes, scheduling, attendance, assessment |
| HR/Payroll | NOT STARTED | Employment and compensation policy |
| Finance/Accounting | NOT STARTED | Accounting and approval policy |
| Remaining domains | NOT STARTED | Progressive discovery |

## Workspace priority authority

The following is an ordered design priority only, not a completed specification:

1. Owner / Executive
2. General Manager
3. Finance
4. Reception / Admissions
5. Academic Management
6. Teacher
7. HR / Payroll
8. Test Officer
9. Campus / Branch Manager
10. Marketing / Social Media
11. Library / Books
12. Operations / Facilities
13. Security / Guard
14. Student
15. Parent / Guardian
16. System Administration

Every workspace still requires discovery of responsibilities, common tasks, waiting work, urgency, abnormal conditions, approvals, editable data, prohibited actions, one-click information, scope, permissions, and cognitive-load risks.

---

## HISTORICAL SOURCE: `foundation/11-rules-authority-invariants-traceability.md`

# Rules, Authority, Invariants, and Traceability — Foundation Draft

## Business-rule registry

| Rule ID | Rule | Authority | Enforcement | Audit | Test | Status |
|---|---|---|---|---|---|---|
| BR-ORG-001 | Structural changes require operation-specific authority through an approval matrix. | O-10 | To be defined after matrix decisions | Required | Planned | OPEN |
| BR-ORG-002 | Campus and branch creation is prepared by the General Manager and requires approval from at least two Owners before creation. | O-35 | Approval workflow | Required | Planned | DECIDED |
| BR-ORG-003 | Permanent campus or branch closure is prepared by the General Manager and requires approval from at least two Owners. | O-36 | Approval workflow | Required | Planned | DECIDED |
| BR-ORG-004 | Reopening a closed campus or branch is prepared by the General Manager and requires approval from at least two Owners. | O-37 | Approval workflow | Required | Planned | DECIDED |
| BR-ORG-005 | Transferring a branch between campuses is prepared by the General Manager and requires approval from at least two Owners. | O-38 | Approval workflow | Required | Planned | DECIDED |
| BR-ORG-006 | A branch transfer changes current operational visibility from its effective date but never rewrites historical campus, branch, date, or attribution. | C-F-001 | Historical record policy | Required | Planned | DECIDED |
| BR-ORG-007 | An official campus or branch name change is prepared by the General Manager and requires approval from at least two Owners. | O-40 | Approval workflow | Required | Planned | DECIDED |
| BR-ACCESS-001 | Sensitive action affecting another Owner requires at least two Owner approvals. | O-19 | To be defined in authorization/approval design | Required | Planned | DECIDED |
| BR-ACCESS-002 | Emergency Owner suspension, if allowed, must be time-limited, audited, and reviewed afterward. | O-19 | To be defined | Required | Planned | OPEN |
| BR-ACCESS-003 | Approval thresholds vary by action type, amount, risk, scope, or consequence; unknown thresholds must fail closed. | O-20 | To be defined | Required | Planned | DECIDED / THRESHOLDS OPEN |
| BR-FIN-001 | A Branch Manager may request eligible routine financial actions but must not approve their own request. | O-48 | Approval workflow | Required | Planned | DECIDED |
| BR-SCOPE-001 | Scope assignment authority differs by scope type and is controlled by an explicit matrix. | O-23 | To be defined | Required | Planned | DECIDED / ROWS OPEN |

| BR-FIN-002 | Refund/cancellation requires documented conditions, immutable source payment, approval, and Finance recording. | G2-B01 | Controlled financial workflow | Required | Planned | DECIDED |
| BR-FIN-003 | Discounts require published or separately approved eligibility, dates, and audit; reversal is a controlled correction. | G2-B02 | Student finance policy | Required | Planned | DECIDED |
| BR-ACAD-002 | Without a program-specific progression rule, no student advances automatically; Academic Management must decide. | G2-B03 | Academic decision workflow | Required | Planned | DECIDED |
| BR-HR-002 | Contract-silent payroll treatment is held for HR and Finance review; no charge or payment is invented. | G2-B04 | Payroll control | Required | Planned | DECIDED |
| BR-FUND-002 | Contract-silent restricted funds remain restricted and on hold pending authorized clarification. | G2-B05 | Funding control | Required | Planned | DECIDED |

## Source-of-truth registry

| Concept | Owner | Authoritative record | Readers | Writers | Forbidden duplicate | Test | Status |
|---|---|---|---|---|---|---|---|
| Structural approval authority | Organization/Access governance | Structural approval matrix | Organization, security, audit | Authorized governance process | Per-route permission guesses | Planned | OPEN |
| Campus/branch creation approval | Organization governance | Approved structural change request | Organization, security, audit | General Manager request + two Owners approval | Direct creation bypass | Planned | DECIDED |
| Owner-sensitive approval | Access governance | Owner-sensitive approval policy and approval records | Security, audit, affected workflow | Authorized Owners under two-Owner rule | Single-owner bypass | Planned | DECIDED |
| Scope assignment authority | Access governance | Scope-assignment authority matrix | Identity, authorization, workspaces | Authorized scope administrators | Position-title-only grants | Planned | OPEN |
| Workspace priority | Workspace governance | Workspace priority register | Product/discovery team | Governance decision | Generic dashboard assumptions | Planned | DECIDED PRIORITY |

## Initial invariants

| Invariant ID | Invariant | Purpose | Enforcement layer | Failure scenario | Recovery | Status |
|---|---|---|---|---|---|---|
| INV-ORG-001 | No structural organization change is valid without the authority required for that operation. | Prevents uncontrolled structural mutation. | Application policy + audit; final layer TBD | Unauthorized branch closure | Reject; preserve prior state; audit attempt | OPEN |
| INV-ORG-002 | No campus or branch may be created until the General Manager's request has approval from at least two Owners. | Protects organizational boundary creation. | Approval workflow + audit; final layer TBD | Direct single-user creation | Reject and record denied attempt | DECIDED |
| INV-ORG-003 | No campus or branch may be permanently closed until the General Manager's request has approval from at least two Owners. | Protects historical and organizational continuity. | Approval workflow + audit; final layer TBD | Single-user closure | Reject and preserve active state | DECIDED |
| INV-ORG-004 | No closed campus or branch may be reopened until the General Manager's request has approval from at least two Owners. | Prevents uncontrolled reactivation. | Approval workflow + audit; final layer TBD | Single-user reopening | Reject and preserve closed state | DECIDED |
| INV-ORG-005 | No branch may be transferred between campuses until the General Manager's request has approval from at least two Owners. | Protects reporting and ownership boundaries. | Approval workflow + audit; final layer TBD | Direct transfer | Reject and preserve current campus | DECIDED |
| INV-ORG-006 | A branch transfer must not alter historical campus, branch, date, or attribution on completed records. | Protects historical truth and reporting integrity. | Historical record policy + audit; final layer TBD | Bulk rewrite after transfer | Reject rewrite; preserve original attribution | DECIDED |
| INV-ACCESS-001 | One Owner cannot complete a sensitive action affecting another Owner. | Prevents unilateral Owner abuse. | Authorization + approval workflow; final layer TBD | Single-owner approval attempt | Reject and record denied attempt | DECIDED |
| INV-ACCESS-002 | Emergency suspension cannot remain active beyond its approved time limit without mandatory review. | Prevents permanent emergency bypass. | Workflow + scheduled control; final layer TBD | Review not completed | Escalate, suspend further action, preserve audit | OPEN |
| INV-SCOPE-001 | A scope assignment must be authorized according to its scope type. | Prevents cross-scope escalation. | Authorization + approval workflow; final layer TBD | User grants own organization-wide scope | Reject and audit | OPEN |

## Traceability seeds

| Requirement | Rule | Domain | Entity/record | Permission/scope | Workspace | Test | Acceptance criterion | Status |
|---|---|---|---|---|---|---|---|---|
| REQ-ORG-004 | BR-ORG-001 | Organization governance | Structural change request/approval | Operation-specific scope | Owner/Executive; General Manager | Planned | Every structural operation has an explicit authority path | OPEN |
| REQ-ORG-005 | BR-ORG-002 | Organization governance | Campus/branch creation request | Organization | Owner/Executive; General Manager | Planned | Creation is impossible without GM request and two Owner approvals | DECIDED |
| REQ-ORG-008 | BR-ORG-003 | Organization governance | Campus/branch closure request | Organization/Campus | Owner/Executive; General Manager | Planned | Permanent closure is impossible without GM request and two Owner approvals | DECIDED |
| REQ-ORG-009 | BR-ORG-004 | Organization governance | Campus/branch reopening request | Organization/Campus | Owner/Executive; General Manager | Planned | Reopening is impossible without GM request and two Owner approvals | DECIDED |
| REQ-ORG-006 | BR-ACCESS-001/002 | Access governance | Owner action approval/emergency review | Organization scope | Owner/Executive; System Administration | Planned | Single Owner cannot complete sensitive peer action | OPEN |
| REQ-ORG-007 | BR-ACCESS-003 | Approval governance | Threshold policy | Action/resource scope | Relevant workspace | Planned | No threshold is invented or bypassed | OPEN |
| REQ-ACCESS-001 | BR-SCOPE-001 | Access governance | Scope assignment | Scope-specific | System Administration | Planned | Each scope type has named authority | OPEN |
| REQ-WORK-001 | — | Workspace governance | Workspace priority record | Position scope | All listed workspaces | Planned | Priority order is preserved without implying workflow completion | OPEN |

## Gate 1 traceability finding

The existing seeds are not a complete traceability matrix. Every critical domain still requires business-level links from requirement to rule, entity, authority, scope, workspace, report, notification, acceptance criterion, exception, and recovery. Implementation links remain prohibited until later architecture gates.

## Gate 2 rule-completeness finding

Global control invariants are established, but domain-level rules for refunds, discounts, academic progression fallback, contract-silent payroll treatment, and silent restricted-fund agreements remain non-deterministic. These are business rules, not technical implementation details, and block Gate 2.

## Gate 3 remediation extensions

The authoritative domain package is now `29` through `40`. It supersedes only the incompleteness identified by the initial Gate 3 review; prior decisions and historical evidence remain retained. Key controls include:

- canonical financial source entities and no mutable authoritative balances;
- immutable evidence and official academic decision separation;
- contractual entitlement, payroll calculation, payroll result, and actual payment separation;
- Position + Assignment + Permission + Scope + Policy authority;
- append-only correction/reversal, effective dating, consent/disclosure evidence, and derived-data lineage;
- explicit domain ownership and forbidden direct mutation across boundaries.

Traceability for the remediation is recorded in `41-gate-3-remediation-review.md`. Implementation tests and technical enforcement remain outside this gate.


## Gate 4 architecture contract extensions

Artifacts `42`–`49` define business-level boundary contracts, authoritative flows, forbidden writers, financial invariants, authorization/scope enforcement, lifecycle preconditions, metric lineage, privacy/audit controls, and resilience requirements. No technology-specific enforcement is selected at Gate 4.


## Gate 5 architecture mapping

Architecture enforcement points for Foundation rules and invariants are recorded in `docs/architecture/24-architecture-invariant-registry.md` and traced end-to-end in `docs/architecture/25-architecture-traceability.md`.


## Gate 6 disposition

Executable enforcement and future test obligations are specified in `docs/implementation/14-testing-implementation-contract.md` and traced in the implementation package.

## Gate 7 Package 01 execution record

No rule or invariant was reinterpreted or implemented in Package 01. Verification and future test-category obligations remain governed by the implementation contract.

---

## HISTORICAL SOURCE: `foundation/12-open-questions-and-risks.md`

# Open Questions and Risks — Gate 1

## Non-blocking unknowns

- Future campus/branch names and activation dates.
- Exact legal ownership succession documents before ownership implementation.
- Agreement-specific scholarship/funding terms before configuration.
- Additional compensation contract variations before use.

## Genuine Gate 1 blockers

- Exact financial/risk approval classification and threshold policy. No monetary thresholds are invented.
- Complete, unambiguous lifecycle acceptance rules for critical student, academic, HR, payroll, financial, funding, document, and asset workflows.
- Complete business-level traceability from requirements to entities, authority, scope, reports, notifications, and acceptance criteria.

## Documentation defects

- Decision Ledger contains duplicate identifiers in historical entries and requires renumbering.
- Decision Ledger and older open-question records contain stale or superseded entries.
- Foundation state snapshot requires current commit evidence.

## Risk posture

Gate 1 is BLOCKED. No architecture or implementation may begin. Unknowns must remain explicit and fail closed.

## Gate 1 blocker resolution

- **G1-C-001:** Resolved. Routine Branch Manager expenses remain with Finance Manager approval; the General Manager is the only substitute when Finance is unavailable.

## Ongoing non-blocking modeling risks

- Detailed acceptance and lifecycle records must be expanded before implementation authorization.
- Exact financial/value thresholds remain configurable and must be populated through approved policy before affected actions are enabled.


## Gate 2 blockers

- Standard refund and cancellation eligibility.
- Standard discount eligibility, expiry, and reversal.
- Minimum progression rule when a program-specific rule is absent.
- Minimum payroll treatment when a contract is silent.
- Minimum restricted-fund treatment when an agreement is silent.

Exact numeric thresholds remain configurable and fail closed; they are not being invented.


## Gate 2 disposition

The Gate 2 blocker set is resolved. Remaining risks concern detailed rule catalogs, policy configuration values, acceptance cases, and agreement-specific inputs; no new business-policy blocker was identified.


## Gate 3 findings

- CRITICAL: financial transaction, allocation, refund, adjustment, reversal, journal, and reconciliation entities are not individually authoritative in the model.
- HIGH: per-entity lifecycle transitions, relationship cardinality/effective dating, access authority rows, academic evidence/decision records, HR/payroll contracts, privacy relationships, and derived-data lineage require modeling repair.

Gate 3 initial FAIL is resolved by artifacts 29–40. Independent re-review is recorded in artifact 41 as PASS WITH NON-BLOCKING OPEN ITEMS. Gate 4 is not automatically started.


## Gate 4 disposition

Gate 4 passed with non-blocking open items. Remaining risks are organization-specific RPO/RTO and backup retention values, detailed metric catalog/acceptance examples, and policy/configuration inputs already identified as deferred. No critical or high architecture ambiguity remains. Gate 5 is not automatically started.


## Gate 5 disposition

Gate 5 passed with non-blocking open items. RPO/RTO and retention values, detailed metric/acceptance catalogs, and the legacy migration requirement remain explicitly deferred. Gate 6 is not automatically started.


## Gate 6 disposition

Gate 6 passed with non-blocking open items. Production implementation remains prohibited pending separate authorization.


## Gate 7 / Package 01

Package 01 established the contract harness and confirmed deferred items without inventing policy. No implementation tooling or business behavior exists in this checkout; Package 02 has not started.

---

## HISTORICAL SOURCE: `foundation/13-batch-processing-record.md`

# Discovery Processing Record — Department Leadership Cluster

**Processed:** 2026-08-24
**Answers:** O-76 through O-81 and O-82 through O-93
**Status:** Processed; department leadership remains open where limits or position categories are not yet defined.

## Decisions recorded

- Head of Department scope varies by department and explicit assignment.
- Heads recommend appointments; HR gives final approval for ordinary appointments, with Owner approval for senior, sensitive, or organization-wide positions.
- Routine duty changes are allowed; changes affecting pay, position, branch, or access require separate approval.
- Cross-branch Head assignments expire automatically and require renewal.
- Outside-department access requires responsible department review and General Manager approval, with stronger Owner rules for sensitive or organization-wide access.
- Heads cannot approve decisions benefiting themselves.
- Compensation changes require HR and Finance review, then General Manager approval; higher-risk cases escalate.
- Staff transfers require current and receiving authority review, then General Manager approval.
- Duty-change access requires department confirmation and General Manager or authorized security approval.
- Temporary urgent removal is permitted with documentation and mandatory HR/General Manager review within 24 hours; only protective restrictions may occur before review.
- Acting Heads are recommended by the department and appointed by the General Manager for defined dates.
- Routine team leave, scheduling, and duty swaps may be approved within staffing rules; material impacts require higher review.
- Heads may evaluate team members; HR and General Manager review consequential decisions.

## Dependency results

Unlocked: detailed HR authority, staff assignment lifecycle, department-specific scopes, and approval separation.

Still blocked: exact limits, senior-position categories, full temporary-removal review period, financial thresholds, and the complete scope-assignment matrix.

No contradiction was detected in this processing cycle. No production implementation was created.

---

## HISTORICAL SOURCE: `foundation/14-batch-processing-record.md`

# Discovery Processing Record — Department Leadership and HR Controls

**Processed:** 2026-08-24
**Answers:** O-76–O-81 and O-82–O-99
**Status:** Processed; department and HR authority remain open where limits, categories, or escalation rules are not yet defined.

## Results

Head of Department scope varies by department and explicit assignment. Heads recommend appointments; HR approves ordinary appointments and Owners approve the identified sensitive leadership/control positions. Routine duty changes and routine staffing changes are allowed within rules, while material changes require approval. Cross-branch assignments expire. Self-approval is prohibited. Compensation requires HR and Finance review. Staff transfers require current/receiving review and General Manager approval. Temporary urgent removal requires prompt review and cannot silently change permanent pay or employment status. Acting Heads are time-limited General Manager appointments. Performance consequences receive HR review.

Branch Manager financial authority remains limited to routine expenses, discounts, and refunds; no thresholds have been invented. Financial disagreement, self-benefit, cross-branch access, and historical attribution controls remain recorded.

## Open dependencies

- Complete sensitive-position list.
- Exact staffing-plan exception rules.
- Full temporary-removal investigation and appeal procedure.
- Exact limits and risk/value thresholds.
- Detailed scope-assignment authority rows.
- Department-specific scope and workspace behavior.

No production implementation was created.

---

## HISTORICAL SOURCE: `foundation/15-agent-decided-defaults.md`

# Agent-Decided Defaults

**Status:** Active defaults for ordinary enterprise mechanics
**Authority:** Best-practice recommendation; may be overridden by explicit TOEFL House business decisions

| Decision ID | Decision | Agent recommendation | Basis | Impact | Status |
|---|---|---|---|---|---|
| AD-001 | Continuity after disputed teacher reassignment | Permit Academic Management to appoint a temporary replacement while an HR appeal is reviewed; preserve the appealed assignment decision, limit the temporary assignment by date and scope, and notify affected parties. | Student-service continuity, safeguarding, separation of appeal from operations | Prevents students losing instruction without deciding the appeal in advance | SUPERSEDED by O-117 user decision |
| AD-002 | Effective dating of assignments | Position, department, branch, class, and access assignments have start/end dates; future changes do not rewrite past assignment history. | Auditability and historical integrity | Supports correct scope at a given date | RECOMMENDED-DEFAULT |
| AD-003 | Self-approval prevention | A requester cannot be the final approver for a decision that benefits the requester; the rule applies generally unless an explicit higher-control exception exists. | Segregation of duties and fraud prevention | Applies across HR, finance, access, and operations | RECOMMENDED-DEFAULT |
| AD-004 | Expired access | Access derived from an expired assignment ends automatically; exceptions require a separate, dated, approved assignment. | Least privilege and reliable offboarding | Prevents forgotten access | RECOMMENDED-DEFAULT |
| AD-005 | Approval records | Every approval records requester, approver, decision, time, reason, affected scope, and related records; rejection and cancellation are first-class outcomes. | Mature workflow and audit practice | Makes decisions explainable and recoverable | RECOMMENDED-DEFAULT |

These defaults do not override an explicit TOEFL House business decision. No production implementation has been created.

---

## HISTORICAL SOURCE: `foundation/16-batch-processing-record.md`

# Discovery Processing Record — Roles and Permission Governance

**Processed:** 2026-08-24
**Answers:** O-118–O-123

## Recorded decisions

New positions require a General Manager proposal and approval by at least two Owners. Permission changes require an Owner request and responsible business review; sensitive or organization-wide increases require two Owners. Any Owner may assign an existing role. Assignments end automatically with the underlying position, employment, or scope. Exceptions are temporary, separately approved, scoped, dated, justified, and audited. Multiple roles are allowed with separate assignments and conflict review.

## Agent-decided default applied

If multiple roles create incompatible powers, the assignment is blocked until an independent review resolves the conflict. This is a standard segregation-of-duties safeguard and does not override the user’s authority over valid business role combinations.

No production implementation was created.

---

## HISTORICAL SOURCE: `foundation/17-master-decision-questionnaire.md`

# TOEFL House — Master Business Decision Questionnaire

**Phase:** Foundation Discovery
**Status:** Awaiting consolidated user response
**Implementation:** Forbidden

## How to use this questionnaire

Answer only the business-policy items. For each item, select one option or write `OTHER:`. `UNKNOWN` is acceptable but remains open. The agent will apply established enterprise defaults to ordinary mechanics and will not ask technical implementation questions.

The host’s structured-question control supports a maximum of six selectable questions per interaction, so this consolidated document is the authoritative single questionnaire record for all remaining material policy decisions. No application implementation is authorized by answering it.

Previously decided matters are intentionally omitted, including: TOEFL House as the organization; three equal Owners; the Organization → Campus → Branch → Department hierarchy; future-ready campuses and branches; two-Owner approval for sensitive Owner actions and structural creation/closure/reopening/transfer/name changes; risk/value-based thresholds; differentiated scope authority; accepted workspace priority; multiple positions; authorized cross-branch work; server-side default-deny authorization; separate positions and roles; English interface; Solar Hijri business calendar; Gregorian technical dates; AFN; student accounts; and the decisions recorded through D-F-071.

---

## A. Ownership and organization

### MD-001 — Owner succession
**Question:** If an Owner leaves, dies, becomes permanently unable to act, or voluntarily gives up ownership, who decides the replacement or succession process?
**Why:** Ownership continuity and authority cannot depend on an unrecorded personal arrangement.
**Recommended:** Record the legal ownership process separately and require approval by the remaining Owners plus any legally required authority.
**Options:** A. Remaining two Owners; B. All three Owners where possible; C. Legal/ownership agreement controls; D. OTHER/UNKNOWN.

### MD-002 — Owner conflict of interest
**Question:** When an Owner has a personal or financial interest in a decision, must that Owner be excluded from requesting, reviewing, and approving it?
**Why:** Prevents self-dealing and protects equal-owner governance.
**Recommended:** Yes; the conflicted Owner declares the conflict and does not participate.
**Options:** A. Yes; B. Only for money; C. Owners decide case by case; D. OTHER/UNKNOWN.

### MD-003 — Campus operating authority
**Question:** When campuses are created, who manages campus-wide operations across its branches?
**Why:** Campus authority is needed for staffing, academic coordination, resources, and cross-branch decisions.
**Recommended:** A designated Campus Manager appointed through the same controlled leadership process as other sensitive positions.
**Options:** A. Campus Manager; B. General Manager; C. Head of Department; D. Shared authority by matter; E. OTHER/UNKNOWN.

### MD-004 — Campus Manager appointment
**Question:** Who appoints or replaces a Campus Manager?
**Why:** Campus-level authority affects multiple branches and access boundaries.
**Recommended:** General Manager proposes; at least two Owners approve.
**Options:** A. General Manager; B. General Manager proposes and two Owners approve; C. Owners directly; D. OTHER/UNKNOWN.

### MD-005 — Management delegation
**Question:** May the General Manager delegate authority to a Campus Manager or Branch Manager for a defined period?
**Why:** Delegation supports continuity but can create hidden authority if not limited.
**Recommended:** Yes, only in writing, with exact scope, start/end dates, and audit.
**Options:** A. Yes, dated and scoped; B. No delegation; C. Yes, without expiry; D. OTHER/UNKNOWN.

## B. Positions, roles, and access governance

### MD-006 — Role assignment to Owners
**Question:** Should an Owner be allowed to assign a sensitive or organization-wide role to their own account?
**Why:** This conflicts with the two-Owner organization-wide access rule and segregation of duties.
**Recommended:** No; another Owner must approve, and sensitive role combinations require independent review.
**Options:** A. No self-assignment; B. Ordinary roles only; C. Any existing role; D. OTHER/UNKNOWN.

### MD-007 — Permission conflict override
**Question:** May the General Manager approve a permission combination that allows one person to request, approve, and record the same financial action?
**Why:** This is a direct financial-control exception.
**Recommended:** No; separate duties should remain separate. If a genuine emergency exists, use a time-limited emergency process with Owner review.
**Options:** A. Never; B. Only emergency, time-limited, and Owner-reviewed; C. Yes; D. OTHER/UNKNOWN.

### MD-008 — System Administrator authority
**Question:** Should the System Administrator be allowed to create or grant their own permissions?
**Why:** Technical administration must not become an uncontrolled business authority.
**Recommended:** No; System Administration operates approved changes but cannot approve its own privilege elevation.
**Options:** A. No; another authority approves; B. Yes, for ordinary roles; C. Yes, all roles; D. OTHER/UNKNOWN.

### MD-009 — Access review owner
**Question:** Who is responsible for reviewing whether sensitive access is still needed each year?
**Why:** Annual review requires a named business owner.
**Recommended:** The relevant manager confirms the business need; an authorized access administrator records the review; sensitive exceptions escalate to Owners.
**Options:** A. Direct manager; B. HR; C. System Administration; D. Manager plus access administrator; E. OTHER/UNKNOWN.

### MD-010 — Person identity duplicates
**Question:** If the same person appears twice in institutional records, should the institution merge the records or keep them separate until verified?
**Why:** Incorrect merging can corrupt student, employee, financial, and audit history.
**Recommended:** Keep records separate until identity is verified by an authorized reviewer; preserve a merge history if merged.
**Options:** A. Verify before merge; B. Merge automatically by matching contact details; C. Never merge; D. OTHER/UNKNOWN.

## C. People, admissions, and student policy

### MD-011 — Parent/guardian authority
**Question:** Should a parent or guardian be allowed to view and act on a student’s account automatically, or only after the relationship is verified?
**Why:** Family relationships can be complex and student privacy must be protected.
**Recommended:** Verified relationship first, with student-specific permissions and the ability to revoke them.
**Options:** A. Verified relationship required; B. Automatic for minors; C. Student decides in every case; D. OTHER/UNKNOWN.

### MD-012 — Student age and consent
**Question:** When a student is an adult, should parent/guardian access continue automatically?
**Why:** Legal and privacy expectations may change when the student reaches adulthood.
**Recommended:** Parent access requires the adult student’s explicit consent unless a legal exception applies.
**Options:** A. Consent required; B. Continue automatically; C. Institution decides case by case; D. OTHER/UNKNOWN.

### MD-013 — Admission decision authority
**Question:** Who makes the final decision to admit a new student?
**Why:** Admission creates academic, financial, and identity records.
**Recommended:** Admissions recommends; Academic Management confirms academic eligibility; authorized management gives final approval.
**Options:** A. Reception/Admissions; B. Academic Management; C. General Manager; D. Different by program; E. OTHER/UNKNOWN.

### MD-014 — Required admission documents
**Question:** Which documents are mandatory before admission can be finalized?
**Why:** Missing identity or consent documents create operational and legal risk.
**Recommended:** Define a configurable minimum identity/consent set, with documented exceptions approved by Admissions management.
**Options:** A. Identity and contact documents; B. Identity, guardian/consent, and academic documents; C. No mandatory documents; D. Different by student type; E. OTHER/UNKNOWN.

### MD-015 — Visitor-to-student conversion
**Question:** May Reception convert a visitor or applicant into a student, or must another role approve the conversion?
**Why:** Conversion creates a permanent student identity and may create financial obligations.
**Recommended:** Reception prepares the record; Admissions or Academic Management approves; Finance confirms any financial setup.
**Options:** A. Reception alone; B. Reception prepares, Admissions approves; C. Academic Management approves; D. Different by program; E. OTHER/UNKNOWN.

### MD-016 — Student withdrawal
**Question:** Who may approve a student’s permanent withdrawal?
**Why:** Withdrawal affects academic history, future reactivation, communication, and financial obligations.
**Recommended:** Student/guardian requests; Admissions and Academic Management review; Finance confirms outstanding obligations; authorized management approves.
**Options:** A. Reception; B. Academic Management; C. General Manager after relevant reviews; D. Different by reason; E. OTHER/UNKNOWN.

### MD-017 — Student suspension and reactivation
**Question:** Who may suspend or reactivate a student, and should the student/guardian be notified before it takes effect?
**Why:** Suspension affects access, attendance, academic progress, and potentially money.
**Recommended:** Authorized Academic Management approves after documented reason; Finance and relevant management review financial consequences; notification is required except where immediate safety requires delay.
**Options:** A. Academic Management; B. General Manager; C. Academic Management with management review; D. Different by reason; E. OTHER/UNKNOWN.

## D. Academic policy

### MD-018 — Academic year and terms
**Question:** How should TOEFL House define its academic year and terms in the Solar Hijri calendar?
**Why:** Academic periods drive enrollment, classes, attendance, progression, payroll, and reports.
**Recommended:** Configurable Solar Hijri academic year with named, dated terms; published periods cannot be silently rewritten.
**Options:** A. One annual period; B. Two terms; C. Three terms; D. Configurable by program; E. OTHER/UNKNOWN.

### MD-019 — Placement requirement
**Question:** Which students must complete placement before joining a level or class?
**Why:** Placement determines academic suitability and prevents incorrect enrollment.
**Recommended:** New students without an approved equivalent result require placement; authorized academic exceptions must be recorded.
**Options:** A. All new students; B. Only students without prior verified results; C. Only selected programs; D. OTHER/UNKNOWN.

### MD-020 — Passing and progression
**Question:** What determines whether a student progresses to the next level?
**Why:** Progression affects academic history, class placement, and completion.
**Recommended:** Configurable program rules using attendance, assessment, examination, and required skill outcomes, with approved exceptions recorded.
**Options:** A. Final exam only; B. Combined attendance and assessment; C. Program-specific rules; D. Academic Management decides case by case; E. OTHER/UNKNOWN.

### MD-021 — Academic appeals
**Question:** Who reviews a student’s appeal of a grade, placement result, or progression decision?
**Why:** Appeals require independence from the original decision.
**Recommended:** Academic Management assigns an uninvolved reviewer; serious or repeated appeals escalate to General Management.
**Options:** A. Original teacher; B. Academic Management; C. Independent reviewer appointed by Academic Management; D. OTHER/UNKNOWN.

### MD-022 — Graduation eligibility
**Question:** What must a student complete before being eligible for graduation or a certificate?
**Why:** Certificates and diplomas are permanent academic outputs.
**Recommended:** Required program levels, assessments, attendance, financial clearance where policy requires it, and document verification; exceptions require approval.
**Options:** A. Level completion only; B. All program requirements; C. Program-specific requirements; D. OTHER/UNKNOWN.

## E. Classes, scheduling, and attendance

### MD-023 — Class creation authority
**Question:** Who may propose and approve a new class?
**Why:** Classes consume teachers, rooms, schedules, and branch capacity.
**Recommended:** Academic Management proposes; Branch Manager confirms local feasibility; General Manager or approved academic authority approves.
**Options:** A. Academic Management; B. Branch Manager; C. Academic Management with Branch Manager review; D. OTHER/UNKNOWN.

### MD-024 — Class capacity exceptions
**Question:** May a class exceed its approved capacity?
**Why:** Over-capacity affects teaching quality, safety, scheduling, and student experience.
**Recommended:** Only through a documented, time-limited exception approved by Academic Management and the Branch Manager.
**Options:** A. Never; B. Limited approved exception; C. Teacher may decide; D. OTHER/UNKNOWN.

### MD-025 — Teacher substitution
**Question:** Who may approve a substitute teacher when the assigned teacher is unavailable?
**Why:** Substitution affects student access, payroll, responsibility, and academic records.
**Recommended:** Academic Management assigns; Branch Manager confirms local availability; access is limited to the substitute period.
**Options:** A. Teacher; B. Branch Manager; C. Academic Management; D. Academic Management with Branch Manager confirmation; E. OTHER/UNKNOWN.

### MD-026 — Attendance correction
**Question:** Who may correct a submitted attendance record?
**Why:** Attendance affects academic status, payroll, and reporting.
**Recommended:** Teacher requests correction; Academic Management approves; changes retain the original value, reason, actor, and time.
**Options:** A. Teacher alone; B. Academic Management; C. Teacher request plus Academic Management approval; D. OTHER/UNKNOWN.

### MD-027 — Class cancellation
**Question:** Who may cancel a scheduled class or session?
**Why:** Cancellation affects students, teachers, make-up sessions, and possible fees.
**Recommended:** Branch Manager may handle operational emergencies; Academic Management approves academic cancellations; affected students and teachers are notified.
**Options:** A. Teacher; B. Branch Manager; C. Academic Management; D. Different by reason; E. OTHER/UNKNOWN.

## F. Teachers and HR

### MD-028 — Teacher employment model
**Question:** Should teachers be employees, contractors, or may both models exist?
**Why:** This determines payroll, contracts, benefits, tax/legal treatment, and access.
**Recommended:** Support both only if the institution genuinely uses both, with separate contracts and compensation rules.
**Options:** A. Employees only; B. Contractors only; C. Both with separate policies; D. OTHER/UNKNOWN.

### MD-029 — Teacher compensation basis
**Question:** Which compensation models does TOEFL House use?
**Why:** Compensation drives payroll, budgeting, teacher assignments, and financial obligations.
**Recommended:** Support only models actually approved by the institution; do not mix models without an explicit contract.
**Options:** A. Fixed salary; B. Hourly/class-based; C. Both; D. Fixed, hourly, class-based, and approved combinations; E. OTHER/UNKNOWN.

### MD-030 — Leave authority
**Question:** Who approves ordinary employee or teacher leave?
**Why:** Leave affects staffing, payroll, and class continuity.
**Recommended:** Department/Branch Manager recommends; HR verifies entitlement; General Manager or delegated authority approves according to policy.
**Options:** A. Direct manager; B. HR; C. Manager plus HR; D. Different by leave type; E. OTHER/UNKNOWN.

### MD-031 — Termination authority
**Question:** Who approves termination of an employee or teacher?
**Why:** Termination affects employment history, final settlement, access, and legal risk.
**Recommended:** Manager recommends; HR investigates and advises; General Manager approves; sensitive leadership positions follow Owner approval.
**Options:** A. Manager; B. HR; C. General Manager after HR review; D. Owners for every termination; E. OTHER/UNKNOWN.

### MD-032 — Final settlement
**Question:** Must Finance and HR both clear final salary, leave, advances, loans, and other obligations before termination is closed?
**Why:** Prevents unpaid obligations and incorrect historical records.
**Recommended:** Yes, both HR and Finance clearance are required.
**Options:** A. Yes; B. HR only; C. Finance only; D. No clearance; E. OTHER/UNKNOWN.

## G. Finance and accounting policy

### MD-033 — Accounting basis
**Question:** Should TOEFL House use formal double-entry accounting with a chart of accounts and period closing?
**Why:** It provides explainable income, expense, asset, liability, equity, receivable, and cash records.
**Recommended:** Yes, formal double-entry accounting with controlled periods and reconciliation.
**Options:** A. Formal double-entry; B. Simple cashbook only; C. Both separate systems; D. OTHER/UNKNOWN.

### MD-034 — Financial periods
**Question:** Who may open, close, or reopen a financial period?
**Why:** Closed periods protect historical financial truth.
**Recommended:** Finance prepares; General Manager approves routine closing; reopening requires documented reason and at least two Owners for material periods.
**Options:** A. Finance Manager; B. General Manager; C. Finance prepares and General Manager approves; D. Owners approve reopening; E. OTHER/UNKNOWN.

### MD-035 — Cash drawers
**Question:** Should each cashier or branch use a separately reconciled cash drawer?
**Why:** Separate drawers make cash responsibility and discrepancies traceable.
**Recommended:** Yes, each physical cash point has an assigned custodian and end-of-period reconciliation.
**Options:** A. Separate drawers; B. One shared cash pool; C. Separate drawers only for Finance; D. OTHER/UNKNOWN.

### MD-036 — Payment acceptance
**Question:** Which payment methods may TOEFL House accept?
**Why:** Each method requires reconciliation and fraud controls.
**Recommended:** Accept only methods the institution can reconcile, such as cash, bank transfer, and approved electronic payments.
**Options:** A. Cash only; B. Cash and bank transfer; C. Cash, bank, and approved electronic payments; D. OTHER/UNKNOWN.

### MD-037 — Refund policy
**Question:** Under what general conditions may a student payment be refunded?
**Why:** Refunds affect students, cash, receivables, revenue recognition, and fraud risk.
**Recommended:** Refund only against a verified original payment, within an approved policy, never above the refundable source, with General Manager approval and Finance recording.
**Options:** A. Any time on request; B. Only documented policy conditions; C. Case-by-case management decision; D. OTHER/UNKNOWN.

### MD-038 — Discount policy
**Question:** Which types of discounts may be offered?
**Why:** Discounts change student obligations and revenue and must not be arbitrary.
**Recommended:** Configured discounts with eligibility, expiry, approval, and no self-approval; exceptional discounts require escalation.
**Options:** A. No discounts; B. Standard published discounts; C. Standard plus approved exceptional discounts; D. OTHER/UNKNOWN.

### MD-039 — Financial adjustments
**Question:** May staff manually reduce or increase a student obligation or ledger balance?
**Why:** Manual adjustments can create phantom money and destroy reconciliation.
**Recommended:** No direct balance editing; use documented adjustment or reversal transactions with approval and audit.
**Options:** A. No direct editing; controlled adjustment only; B. Finance may edit; C. Managers may edit; D. OTHER/UNKNOWN.

## H. Fees, scholarships, sponsorship, and funding

### MD-040 — Fee categories
**Question:** Which fee categories does TOEFL House charge: tuition, registration, placement, books, ID card, examination, certificate/diploma, late fee, or other?
**Why:** Fees determine obligations, invoices, reports, and refund rules.
**Recommended:** Include only approved categories and make them configurable by program, level, branch, and effective period.
**Options:** A. Tuition and registration; B. Tuition, registration, placement, and exams; C. All listed where applicable; D. OTHER/UNKNOWN.

### MD-041 — Installments and late fees
**Question:** Should students be allowed to pay approved obligations in installments, and may late fees apply?
**Why:** Payment plans affect receivables and student communication.
**Recommended:** Configurable approved plans; late fees only if explicitly adopted and disclosed before becoming due.
**Options:** A. No installments/no late fees; B. Installments only; C. Installments and approved late fees; D. OTHER/UNKNOWN.

### MD-042 — Scholarship eligibility
**Question:** Who may qualify for a scholarship and who approves it?
**Why:** Scholarships transfer financial support and require fairness and funding control.
**Recommended:** Published eligibility categories; application review; Finance confirms funding; General Manager or Owners approve according to risk/value.
**Options:** A. Need-based; B. Merit-based; C. Need, merit, sponsorship, and approved categories; D. OTHER/UNKNOWN.

### MD-043 — Scholarship revocation
**Question:** When may a scholarship be reduced, suspended, or revoked?
**Why:** Changes affect student obligations and donor/funding promises.
**Recommended:** Only for predefined conditions, with notice, reason, effective date, appeal path, and approval.
**Options:** A. Never; B. Attendance/academic/eligibility conditions; C. Management discretion; D. OTHER/UNKNOWN.

### MD-044 — Restricted funding
**Question:** Should donor or sponsor funds be restricted to named programs or students?
**Why:** Restricted funds cannot be spent as general money without violating the funding purpose.
**Recommended:** Support restricted and unrestricted funds, with written purpose and reconciliation.
**Options:** A. Unrestricted only; B. Restricted only; C. Both; D. OTHER/UNKNOWN.

### MD-045 — Unused funding
**Question:** What should happen to unused restricted sponsorship or donor funds when the student/program ends?
**Why:** Unused funds require a documented ethical and financial treatment.
**Recommended:** Follow the written agreement; if silent, hold funds until an authorized decision, never silently convert them.
**Options:** A. Return to funder; B. Transfer to another eligible student/program with consent; C. Convert to general funds; D. Agreement-specific; E. OTHER/UNKNOWN.

## I. Books, inventory, assets, and facilities

### MD-046 — Book issuance model
**Question:** Are books issued as loans that must be returned, sold to students, or both?
**Why:** Lending and sales have different ownership, fees, inventory, and loss rules.
**Recommended:** Support only the models actually used, with separate issuance and sale records.
**Options:** A. Loans only; B. Sales only; C. Loans and sales; D. OTHER/UNKNOWN.

### MD-047 — Lost or damaged books
**Question:** Who decides whether a student or employee must pay for a lost or damaged book?
**Why:** Responsibility and replacement charges must be fair and auditable.
**Recommended:** Library/Book Custodian records the incident; responsible manager reviews; Finance applies an approved replacement policy.
**Options:** A. Automatic charge; B. Library decides; C. Review plus approved policy; D. OTHER/UNKNOWN.

### MD-048 — Asset disposal
**Question:** Who approves disposal, sale, donation, or write-off of an institutional asset?
**Why:** Asset disposal affects ownership, financial records, and fraud risk.
**Recommended:** Custodian recommends; Finance verifies records; at least two Owners approve material disposal.
**Options:** A. Asset custodian; B. Finance Manager; C. Finance review and two Owners for material disposal; D. OTHER/UNKNOWN.

### MD-049 — Maintenance work orders
**Question:** Who may approve maintenance or facilities work that creates a financial obligation?
**Why:** Work orders affect safety, operations, vendors, and expenses.
**Recommended:** Operations identifies the need; Branch Manager confirms urgency; Finance reviews money; approval follows financial limits.
**Options:** A. Operations; B. Branch Manager; C. Operations plus Branch Manager and Finance; D. OTHER/UNKNOWN.

## J. Security, privacy, communication, and documents

### MD-050 — Student privacy
**Question:** May staff share student information with parents, sponsors, donors, or external parties without student/guardian consent?
**Why:** Student information must be limited to legitimate, verified purposes.
**Recommended:** No, except for documented legal, safety, or explicitly consented purposes.
**Options:** A. No without verified consent/legal basis; B. Parents always; C. Sponsors may receive reports; D. OTHER/UNKNOWN.

### MD-051 — Sensitive documents
**Question:** Which documents require restricted access beyond ordinary student or employee records?
**Why:** Medical, identity, disciplinary, financial, and contract records may create heightened privacy risk.
**Recommended:** Restrict identity, medical, disciplinary, financial, payroll, and legal/contract documents to role and purpose.
**Options:** A. Identity only; B. Identity and financial; C. All listed sensitive categories; D. OTHER/UNKNOWN.

### MD-052 — Communication consent
**Question:** Which communication channels may TOEFL House use for students and parents?
**Why:** Messages can create privacy, consent, cost, and recordkeeping obligations.
**Recommended:** Use approved channels with consent, opt-out handling, template ownership, and communication history.
**Options:** A. Phone/SMS; B. SMS and email; C. SMS, email, and approved messaging; D. OTHER/UNKNOWN.

### MD-053 — Local-language communication
**Question:** Should student/parent messages support Dari or other local languages in addition to English?
**Why:** Communication must be understandable to recipients even though the system interface remains English.
**Recommended:** Yes, configurable message language by recipient and template, while keeping the administrative interface English.
**Options:** A. English only; B. English and Dari; C. English, Dari, and additional approved languages; D. OTHER/UNKNOWN.

### MD-054 — Security incidents
**Question:** Who may declare and manage an urgent security, privacy, or safety incident?
**Why:** Incidents require immediate containment and accountable follow-up.
**Recommended:** Any authorized staff member may report; designated security/management authority coordinates; Owners are notified for serious incidents.
**Options:** A. Guard/Security; B. General Manager; C. Security lead plus General Manager; D. OTHER/UNKNOWN.

### MD-055 — Document retention
**Question:** How long should student, employee, financial, academic, and audit records be retained after the relationship ends?
**Why:** Retention must balance legal, operational, historical, and privacy requirements.
**Recommended:** Define retention by record category; retain financial, academic, and audit history longer than routine operational material; never silently delete required history.
**Options:** A. One universal period; B. Category-specific periods; C. Keep everything permanently; D. OTHER/UNKNOWN.

## K. Testing, marketing, events, reporting, integrations, recovery

### MD-056 — Placement result release
**Question:** Who approves and releases a placement test result to a student?
**Why:** Placement results control level assignment and may be disputed.
**Recommended:** Test Officer records; Academic Management approves; student receives the approved result and appeal route.
**Options:** A. Test Officer; B. Academic Management; C. Test Officer prepares and Academic Management approves; D. OTHER/UNKNOWN.

### MD-057 — Marketing consent
**Question:** May TOEFL House use a student’s name, photograph, video, or success story in marketing?
**Why:** Public use of student identity requires consent and revocation handling.
**Recommended:** Only with specific, recorded consent, purpose, channel, and expiry/revocation handling.
**Options:** A. Never; B. With specific consent; C. General enrollment consent is enough; D. OTHER/UNKNOWN.

### MD-058 — Event approval
**Question:** Who approves events that involve students, external guests, a budget, or public communication?
**Why:** Events create safety, consent, cost, and reputational responsibilities.
**Recommended:** Event owner prepares; relevant department and Branch Manager review; financial and high-risk events escalate.
**Options:** A. Marketing; B. Branch Manager; C. Event owner plus relevant management and Finance; D. OTHER/UNKNOWN.

### MD-059 — Required management reports
**Question:** Which reports are mandatory for Owners and General Manager every period?
**Why:** Mandatory reports define management accountability and reconciliation.
**Recommended:** Finance, cash, receivables, payroll, enrollment, attendance, academic outcomes, staffing, branch performance, audit exceptions, and funding reports.
**Options:** A. Finance only; B. Finance and student operations; C. Full operational, academic, HR, finance, funding, and audit set; D. OTHER/UNKNOWN.

### MD-060 — External integrations
**Question:** Which external services are required in the first operational release?
**Why:** Integrations create cost, privacy, failure, and support obligations.
**Recommended:** Start only with services that have a confirmed operational need, such as SMS/email and approved payment or banking connections.
**Options:** A. None initially; B. SMS/email; C. SMS/email plus payment/banking; D. OTHER/UNKNOWN.

### MD-061 — Acceptable data loss
**Question:** In a serious failure, how much newly entered data may TOEFL House accept losing?
**Why:** This determines recovery priority and backup frequency.
**Recommended:** No loss of completed financial transactions; at most one day of low-risk operational entries if restoration is necessary.
**Options:** A. No data loss; B. Up to one day; C. Up to one week; D. OTHER/UNKNOWN.

### MD-062 — Acceptable downtime
**Question:** How quickly must the system be restored after a serious failure?
**Why:** Recovery targets affect operational planning and cost.
**Recommended:** Critical finance, reception, and academic operations restored within one business day; non-critical reporting may follow.
**Options:** A. Four hours; B. One business day; C. Three business days; D. OTHER/UNKNOWN.

### MD-063 — Bulk export authority
**Question:** Who may approve an export containing many student, employee, or financial records?
**Why:** Bulk exports can create major privacy and fraud exposure.
**Recommended:** Business owner requests; responsible manager confirms purpose; sensitive or cross-branch exports require General Manager or Owner approval.
**Options:** A. Any manager; B. General Manager; C. Responsible manager plus higher approval for sensitive exports; D. OTHER/UNKNOWN.

### MD-064 — Complaints and appeals
**Question:** Who owns formal complaints from students, parents, staff, or external parties?
**Why:** Complaints need a neutral owner, response deadline, escalation, and history.
**Recommended:** General Manager assigns an uninvolved reviewer; HR handles employment complaints; Academic Management handles academic complaints; serious matters escalate to Owners.
**Options:** A. Reception; B. General Manager; C. Topic-specific reviewer assigned by General Manager; D. OTHER/UNKNOWN.

### MD-065 — Manual exceptional decisions
**Question:** When no written policy covers an unusual case, who may authorize an exception?
**Why:** Exceptions must not become hidden policy or uncontrolled precedent.
**Recommended:** Responsible manager recommends; General Manager decides; financial, ownership, privacy, or high-risk exceptions escalate to the required Owner approval level; record whether the exception creates a future policy question.
**Options:** A. Any manager; B. General Manager; C. General Manager with escalation by risk/value/consequence; D. OTHER/UNKNOWN.

## Response format

Return one consolidated response using the IDs. You may answer only the items where TOEFL House has a specific policy; the agent will classify unanswered items as UNKNOWN or apply a documented recommended default where safe.

```text
MD-001: C — Legal ownership agreement controls.
MD-002: A — Conflicted Owner does not participate.
MD-003: B — General Manager.
MD-018: D — Configurable by program, within Solar Hijri.
MD-050: A — No sharing without verified consent or legal basis.
MD-061: B — Up to one day for low-risk operational data, no loss for completed finance.
```

After submission, the agent will process the complete response together, reconcile contradictions, update all Foundation registries, identify only genuine blockers, and continue with modeling. No ordinary technical or workflow mechanics will be returned as user questions.

---

## HISTORICAL SOURCE: `foundation/18-master-processing-record.md`

# Master Business Decisions — Processing Record

**Processed:** 2026-08-24
**Authority:** Consolidated user-authorized defaults
**Phase:** Foundation Discovery
**Implementation:** Forbidden

## Processing result

The consolidated decision set has been reconciled against the Foundation decision ledger. Decisions were generalized into reusable policies rather than duplicated per workflow. No technical implementation decision was escalated unnecessarily.

## Precedence and conflict resolution

1. The consolidated user-authorized decisions supersede earlier recommendations where they differ.
2. Earlier explicit user decisions remain authoritative where the consolidated set does not replace them.
3. Agent defaults are used only for ordinary mechanics and never override user policy.
4. Unknown thresholds and policy details remain unknown; no amount, legal rule, or institutional fact was invented.

### Resolved conflicts

- The earlier self-assignment ambiguity is resolved by MD-006: an Owner cannot independently assign a sensitive or organization-wide privilege to themselves.
- The earlier permission-combination exception is resolved by MD-007: one person must never initiate, approve, record, and reconcile the same financial transaction. The earlier permissive answer is superseded.
- The earlier teacher-access conflict is resolved by O-117 and MD-026-related policy: the reassignment appeal pauses reassignment. The agent default allowing a temporary replacement is superseded by the user decision; no replacement may be assumed without a later explicit policy decision.
- Historical branch transfer attribution is preserved: current visibility may change, but historical campus, branch, date, and attribution remain immutable.

## Classification

- **USER-DECIDED:** TOEFL House-specific ownership, authority, organization, academic, HR, finance, scholarship, privacy, communication, reporting, recovery, and operational policies in the consolidated questionnaire and earlier confirmed records.
- **AGENT-DECIDED / RECOMMENDED-DEFAULT:** ordinary mechanics such as audit structure, effective dating, approval-record structure, controlled corrections, default-deny enforcement mechanics, and standard lifecycle handling.
- **NON-BLOCKING UNKNOWN:** exact future campus/branch inventory, legal succession document details, exact scholarship agreements, and exact contract variations where the policy already requires configurable or agreement-specific handling.
- **BLOCKED:** none identified for Foundation business discovery at this point.

## Coverage determination

Business-policy coverage now exists for organization and ownership, positions and roles, permissions and scopes, people, admissions, students, academic policy, classes, teachers, HR, payroll direction, finance/accounting direction, fees, scholarships, sponsorship/funding, books, assets, facilities, security, testing, marketing, events, communication, documents, reporting, calendar policy, audit governance, configuration, integrations, import/export, recovery, privacy, and exceptional decisions.

This is a business-discovery completion statement only. It is not Foundation Certification and does not authorize implementation.

## Residual open items

| ID | Item | Classification | Blocking? |
|---|---|---|---|
| OPEN-01 | Future campus and branch official names, locations, and activation dates | UNKNOWN; future operational data | No |
| OPEN-02 | Exact legal ownership succession agreement | UNKNOWN; legal artifact | No for modeling; must be supplied before ownership implementation |
| OPEN-03 | Specific scholarship/funding agreements | UNKNOWN; agreement-specific | No for generic model; required before each agreement is configured |
| OPEN-04 | Additional compensation contract variations | UNKNOWN; contract-specific | No for generic model; required before each contract type is configured |

## Gate disposition

**Foundation Business Discovery:** `COMPLETE — residual non-blocking unknowns recorded`

**Gate 0:** `READY FOR FOUNDATION MODELING / GATE REVIEW`

**Production implementation:** `FORBIDDEN until Gates 1–16 are independently evidenced and Foundation Certification is approved.`

---

## HISTORICAL SOURCE: `foundation/19-canonical-domain-model.md`

# Canonical TOEFL House Domain Model

**Phase:** Foundation Modeling
**Status:** Draft model — business authority only
**Implementation:** Forbidden

## Modeling conventions

- A domain owns its business truth and exposes outcomes to other domains through controlled business contracts.
- A record has one owner, one lifecycle, explicit scope, effective dates where applicable, and an auditable history.
- Current state is a projection of approved facts; historical facts are not silently rewritten.
- `UNKNOWN` means the institution has not supplied a specific policy. It is not a permission to invent one.

## Domain map

| Domain | Owns | Consumes | Material outputs |
|---|---|---|---|
| Organization | organization, campus, branch, department, structural lifecycle | ownership, governance | organizational scope and attribution |
| Identity & Access | person identity, accounts, positions, roles, permissions, assignments | HR, organization | authorized subject context |
| Governance & Approvals | requests, reviews, decisions, delegations, conflicts | all sensitive domains | approved/rejected decisions |
| People | person, relationships, contact identity | identity, admissions, HR | verified person context |
| Admissions | visitors, applicants, admission decisions | people, programs, placement, finance | applicant/student conversion |
| Students | student identity, lifecycle, guardians, status | admissions, academics, finance | student record and status |
| Academic Structure | programs, versions, levels, courses, periods | organization, configuration | valid academic offering |
| Academic Delivery | classes, sessions, schedules, teachers, enrollment, attendance | academic structure, people | academic participation facts |
| Assessment & Testing | placement, exams, assessments, results, appeals | academic delivery | approved academic outcomes |
| Progression & Completion | progression, repeat, completion, graduation, certificates | assessment, attendance, finance policy | academic completion facts |
| HR & Payroll | employment, contracts, leave, performance, compensation, payroll | people, organization, finance | employment and payroll obligations |
| Finance & Accounting | accounts, journals, receivables, payables, cash, periods, reconciliation | all financial events | financial truth |
| Fees & Student Finance | fee policies, obligations, payments, allocations, discounts, refunds | finance, students, academics | student financial obligations |
| Funding | scholarships, sponsorship, donors, restrictions, allocations | finance, students | restricted/unrestricted funding truth |
| Resource Operations | books, inventory, assets, facilities, maintenance | organization, finance | custody and resource facts |
| Communication | approved messages, templates, recipient history | all event owners | controlled communication |
| Documents | owned documents, versions, verification, retention | all domains | authorized evidence |
| Reporting | definitions, periods, scope, metrics | authoritative domains | reconciled reports |
| Audit & Security | audit facts, incidents, access reviews, data classifications | all domains | accountability and security evidence |
| Resilience & Integrations | recovery objectives, external exchanges, integration contracts | governance and operations | controlled external effects |

## Cross-domain authority rules

- Finance and Accounting owns all monetary truth.
- Academic Structure owns programs, levels, periods, and academic policy definitions.
- Academic Delivery owns class participation, session, attendance, and teacher assignment facts.
- Assessment owns assessment attempts, results, moderation, and release status.
- HR owns employment status, contracts, leave, and employee history.
- Identity & Access owns effective access; HR and organization provide assignment facts.
- Organization owns structural identity and effective organizational attribution.
- Reporting never creates a competing calculation or status.
- Audit records are append-only evidence and are not business state substitutes.

## Required aggregate boundaries

1. Organization aggregate: organization/campus/branch/department structure.
2. Person aggregate: person identity and verified relationships.
3. Access assignment aggregate: position, role, permission, scope, delegation, effective dates.
4. Student aggregate: student lifecycle and identity history.
5. Academic offering aggregate: program/version/level/course/period/class.
6. Enrollment aggregate: student participation, transfer, freeze, withdrawal.
7. Assessment aggregate: attempt, response, score, moderation, result release.
8. Employment aggregate: employee, contract, assignment, leave, performance.
9. Payroll aggregate: period, calculation, approval, payment, correction.
10. Financial transaction aggregate: obligation, payment, allocation, refund, journal, reconciliation.
11. Funding aggregate: fund, agreement, restriction, allocation, disbursement.
12. Resource custody aggregate: book/item/asset, location, custodian, movement.
13. Document aggregate: document identity, version, verification, retention.
14. Approval aggregate: request, review, decision, conflict, evidence.

Each aggregate requires a complete lifecycle specification before implementation.

---

## HISTORICAL SOURCE: `foundation/20-entity-relationship-registry.md`

# Entity and Relationship Registry

**Status:** Foundation model draft

## Canonical business entities

| Entity | Owning domain | Key relationships | Historical requirement |
|---|---|---|---|
| Organization | Organization | contains campuses and departments | preserve structural history |
| Campus | Organization | belongs to organization; contains branches/units | effective attribution |
| Branch | Organization | belongs to campus; has manager and units | transfer history immutable |
| Department / Unit | Organization | scoped to organization/campus/branch; has head | scope history |
| Person | People | may have accounts, roles, relationships | verified merge only |
| User Account | Identity | belongs to person; has assignments | access history |
| Position Assignment | Identity/HR | person + position + scope + dates | immutable assignment history |
| Role Assignment | Access | person/account + role + scope + dates | approval and expiry |
| Delegation | Governance | delegator + recipient + authority + scope + dates | approval and audit |
| Visitor / Applicant | Admissions | person/contact; may become student | conversion trace |
| Student | Students | person; guardians; enrollments; obligations | academic/financial history |
| Guardian Relationship | People/Students | guardian + student + permissions | verification and revocation |
| Program / Version | Academic Structure | levels, courses, rules | published version immutable |
| Academic Period | Academic Structure | program/classes/payroll/reporting | published period immutable |
| Class / Session | Academic Delivery | period, level, teacher, room | schedule and attendance history |
| Enrollment | Academic Delivery | student + class + period | state history |
| Attendance Fact | Academic Delivery | student + session | correction history |
| Assessment / Result | Assessment | student + class/attempt | result and correction history |
| Employment / Contract | HR | person + position + compensation | employment history |
| Leave / Performance Record | HR | employee + period + approver | decision history |
| Payroll Period / Entry | Payroll | employee/teacher + financial period | locked history |
| Account / Journal / Transaction | Finance | financial events and periods | immutable financial fact |
| Obligation / Payment / Allocation | Student Finance | student + source + financial records | reconciliation |
| Discount / Refund | Student Finance | obligation/payment + approval | no silent mutation |
| Scholarship / Fund / Sponsorship | Funding | student/program + restriction | agreement history |
| Book / Inventory Item / Asset | Resources | location, custodian, movement | custody history |
| Facility / Work Order | Facilities | branch/campus + requester | operational history |
| Document / Version | Documents | owner resource + permissions | retention and version history |
| Approval Request / Decision | Governance | resource + actor + approvers | append-only decision history |
| Audit Event / Security Incident | Audit/Security | actor + resource + context | append-only |
| Report Definition / Run | Reporting | authority source + period + scope | reproducible output |
| Notification / Communication | Communication | event + recipient + template | delivery history |

## Relationship principles

- Relationships are explicit records when they affect authority, privacy, money, or history.
- Scope is not inferred from a person's home branch when a narrower or different assignment exists.
- A student, teacher, employee, guardian, donor, or sponsor may have multiple relationships without duplicate person identities.
- A record may have a current owner and historical owners; current ownership never erases prior attribution.
- Derived reporting relationships consume authoritative records and do not become new authorities.

---

## HISTORICAL SOURCE: `foundation/21-governance-and-access-model.md`

# Governance, Authority, Scope, and Access Model

**Status:** Foundation model draft

## Authority model

Authority is evaluated as: person/account + effective position + role + permission + scope + resource relationship + action + context. The result is default deny.

| Decision class | Requester | Reviewer/approver | Control |
|---|---|---|---|
| Create campus/branch | General Manager | At least two Owners | approved request required |
| Close/reopen/rename/transfer campus/branch | General Manager | At least two Owners | history preserved |
| Create department/unit | General Manager | At least two Owners | scoped unit |
| Appoint sensitive leadership | General Manager | At least two Owners | includes Heads of Department |
| Organization-wide access | Owner | At least two Owners | no self-approval |
| Campus-wide access | General Manager | At least two Owners | dated assignment |
| Branch-wide access | Branch Manager for own branch; GM for cross-branch request | General Manager or two Owners for cross-branch | explicit expiry |
| Department access | Relevant Head | General Manager | department confirmation |
| Role/permission design | Owner request | business review; two Owners for sensitive increase | conflict review |
| Existing role assignment | Any Owner may assign | assignment must obey scope and conflict controls | effective dates |
| Money request | authorized requester | Finance review + responsible approver | requester cannot self-approve |
| Owner emergency suspension | two unaffected Owners | two unaffected Owners within seven days | time-limited, audited |

## Scope types

Organization, campus, branch, department, program, academic period, financial period, class, student, teacher, employee, financial resource, cash drawer, bank account, document, room, inventory location, report, work queue, and relationship are supported scope concepts. Each scope assignment has a reason, effective period, authority, and audit record.

## Segregation of duties

| Conflict | Required control |
|---|---|
| Requester = approver | reject; independent approver required |
| Initiator = approver = recorder = reconciler | prohibited for financial actions |
| Owner self-assignment of sensitive access | prohibited without independent Owner approval |
| Head evaluates and decides own consequence | HR/General Manager review |
| Branch Manager requests own financial benefit | independent approval |
| System Administrator authorizes own elevation | prohibited |

## Delegation and acting authority

Delegation is explicit, scoped, dated, attributable, and auditable. Acting positions have a named appointing authority, start/end dates, and no authority beyond the stated assignment. Expiry automatically removes derived access. Historical assignments remain reconstructable.

## Agent-decided mechanics

The implementation will use standard policy evaluation, immutable approval history, effective dating, least-privilege projections, automatic expiry, explicit conflict detection, and append-only audit evidence. These are **AGENT-DECIDED DEFAULTS**, reversible at the modeling level, and do not create new TOEFL House policy.

---

## HISTORICAL SOURCE: `foundation/22-lifecycle-and-control-model.md`

# Lifecycle and Control Model

**Status:** Foundation model draft

## Universal lifecycle pattern

`Draft → Submitted → Under Review → Approved | Rejected | Cancelled → Effective → Superseded | Reversed | Archived`

Not every domain uses every state. No transition is implicit. Each transition has actor, authority, scope, reason, timestamp, side effects, audit, and recovery behavior.

## Student lifecycle

`Prospect → Applicant → Admitted → Enrolled → Active → Deferred/Frozen | Withdrawn | Suspended → Completed → Graduated/Alumni`

Placement, admission approval, enrollment, financial obligations, academic progression, withdrawal, suspension, reactivation, and completion remain separate facts.

## Academic lifecycle

`Configured → Published → Offered → Class Planned → Active → Completed → Archived`

Published program, level, period, assessment, and result history is not silently rewritten. Corrections create attributable correction history.

## Financial lifecycle

`Proposed → Approved → Posted → Reconciled → Period Closed`

Corrections use reversal/adjustment/refund transactions. Closed periods cannot be silently changed; reopening requires documented authority. Every payment has source, method, allocation, financial posting, and reconciliation evidence.

## Employee lifecycle

`Candidate → Hired → Active → Leave/Suspended → Transferred/Promoted → Terminated → Settled → Archived`

HR owns employment facts; Finance owns financial consequences; access follows effective assignments.

## Resource lifecycle

`Catalogued → Located → Assigned/Custodied → Issued/In Use → Returned/Maintained → Transferred/Disposed`

Loss, damage, disposal, custody, and replacement remain explicit events.

## Document lifecycle

`Draft → Submitted → Verified/Rejected → Versioned → Active → Expired/Archived`

Document download and disclosure always require current authorization; a URL is never authority.

## Exception model

An exception must state: requested rule, reason, requester, approver, affected resource, scope, start/end, financial/academic/security impact, notification, audit, and whether a permanent policy is needed. Unknown critical behavior fails closed.

## Agent-decided workflow mechanics

Approvals, rejections, cancellations, retries, idempotency, concurrency handling, validation, pagination, notifications, audit structure, and recovery mechanics will follow mature enterprise patterns. These are **AGENT-DECIDED DEFAULTS**, reversible before implementation, and are not business policy claims.

---

## HISTORICAL SOURCE: `foundation/23-finance-academic-hr-controls.md`

# Financial, Academic, and HR Control Model

**Status:** Foundation model draft

## Financial control

Finance and Accounting are the sole authority for money. The model includes chart of accounts, double-entry journal/ledger, receivables, payables, cash drawers, bank accounts, student obligations, payments, allocations, refunds, discounts, scholarships, sponsorship, payroll, assets, periods, closing, reopening, reconciliation, and reporting.

Operational domains may request financial events but cannot create independent balances. No direct balance edits. Payment replay, duplicate submission, concurrent settlement, over-refund, wrong branch, wrong period, partial failure, unauthorized discount, payroll duplication, and cross-period mutation are mandatory attack cases.

## Academic control

Academic Structure owns programs, versions, levels, courses, prerequisites, periods, and progression policy. Academic Delivery owns classes, sessions, enrollment, teacher assignments, schedules, and attendance. Assessment owns placement, examinations, grading, moderation, result approval, release, correction, and appeals. Completion owns graduation and certificates.

Program-specific policies are configurable. Placement exceptions, capacity overrides, attendance corrections, progression decisions, and academic appeals are attributable, approved, and historically reconstructable.

## HR/payroll control

HR owns people’s employment, contracts, positions, leave, performance, discipline, transfer, promotion, termination, and final employment history. Payroll calculates approved compensation obligations; Finance records financial effects. Compensation supports fixed, hourly/class-based, and approved combinations according to contract. HR and Finance clear final settlement.

## Privacy and disclosure

Student, guardian, employee, payroll, financial, medical, disciplinary, identity, legal, and contract records are classified by sensitivity. Disclosure requires verified relationship, legitimate purpose, consent or legal/safety basis, minimum necessary projection, and audit. Bulk export is a controlled privilege.

## Reporting authority

Reports consume authoritative domain facts and centralized Solar Hijri periods. They may not redefine balance, revenue, expense, attendance, student status, payroll, or financial totals. Every report has definition, period, scope, source, filters, permission, reconciliation, and reproducibility requirements.

---

## HISTORICAL SOURCE: `foundation/24-data-privacy-resilience-model.md`

# Data, Privacy, Audit, and Resilience Model

**Status:** Foundation model draft

## Data classification

| Class | Examples | Default handling |
|---|---|---|
| Public | approved public institution information | approved publication only |
| Internal | ordinary operations and schedules | authorized staff scope |
| Confidential | student, employee, guardian, academic, operational | role and relationship scope |
| Restricted | financial, payroll, identity, disciplinary, medical, legal, security | named purpose, narrow scope, explicit audit |

## Audit model

Material actions record actor, account, action, resource, scope, time, reason, approval, correlation, before/after projection where safe, origin, and outcome. Secrets and credentials are never recorded. Audit history is protected against unauthorized alteration.

## Retention

Retention is category-specific. Financial, academic, employment, ownership, approval, and audit history remains reconstructable. Routine communications and operational drafts may have shorter approved retention. Deletion is replaced by archive, cancellation, reversal, or redaction where historical truth requires it.

## Recovery business objectives

- Completed financial transactions: no accepted loss.
- Low-risk operational data: up to one day accepted under the user decision.
- Critical finance, reception, and academic operations: restore within one business day.
- Recovery priority: financial integrity, identity/access, student safety and academic continuity, then general operations and reporting.

Technical backup, restore, encryption, monitoring, and deployment mechanics are **AGENT-DECIDED DEFAULTS** to be selected during technical architecture modeling.

## Integration boundary

Initial confirmed business integration need is SMS/email communication. Payment/banking integrations are not mandatory until an operational requirement is confirmed. Every external exchange requires purpose, owner, scope, consent/security basis, failure handling, reconciliation, and audit.

---

## HISTORICAL SOURCE: `foundation/25-foundation-modeling-review.md`

# Foundation Modeling Review Report

**Date:** 2026-08-24
**Phase:** Foundation Modeling
**Result:** `PASS WITH RECORDED NON-BLOCKING OPEN ITEMS`
**Foundation Certification:** Not yet claimed

## Evidence reviewed

- `00-foundation-state.md`
- `04-decision-ledger.md`
- `08-requirements-registry.md`
- `09-authority-and-scope-matrices.md`
- `10-domain-and-workspace-register.md`
- `11-rules-authority-invariants-traceability.md`
- `12-open-questions-and-risks.md`
- `15-agent-decided-defaults.md`
- `18-master-processing-record.md`
- `19-canonical-domain-model.md`
- `20-entity-relationship-registry.md`
- `21-governance-and-access-model.md`
- `22-lifecycle-and-control-model.md`
- `23-finance-academic-hr-controls.md`
- `24-data-privacy-resilience-model.md`

## Review results

| Review | Result | Basis |
|---|---|---|
| Contradiction audit | PASS with known supersessions | Self-assignment, segregation of duties, teacher appeal, and historical attribution conflicts are recorded and resolved by authority precedence. |
| Missing-domain audit | PASS at model level | Minimum directive domains are mapped; additional governance, funding, resilience, privacy, and resource domains are included. |
| Authority audit | PASS with open detail rows | Organization, Owner, management, HR, Finance, Academic, and access authority principles are modeled. |
| Scope audit | PASS with open assignment rows | Organization through relationship/resource scopes are named; exact assignment actors remain an open detail where not user-decided. |
| Financial-control audit | PASS at business-model level | Double-entry, no direct balances, segregation of duties, approval, immutable history, and reconciliation are defined. |
| Lifecycle audit | PASS at model level | Student, academic, financial, employee, resource, document, and exception lifecycles are defined; detailed state catalogs remain planned. |
| Invariant audit | PASS for established hard invariants | Core Owner, self-approval, historical, financial, calendar, access, and audit invariants are recorded. |
| Traceability audit | PASS at seed level | Critical requirements have initial rule/domain/authority/invariant/test acceptance links; detailed implementation traceability is prohibited until architecture exists. |
| Assumption-vs-decision audit | PASS | Agent defaults, user decisions, unknowns, and superseded decisions are separated. |
| Legacy-contamination audit | PASS | Active tree contains only Foundation artifacts; legacy implementation is preserved only in Git history. |

## Non-blocking open items

- Future campus/branch inventory and official names.
- Exact legal ownership succession documentation.
- Agreement-specific scholarship/funding terms.
- Contract-specific compensation variations.
- Exact financial/value thresholds.
- Detailed state transition catalogs, acceptance cases, and test plans.

These are not silently invented. They must be resolved before the affected configuration or implementation is finalized.

## Gate disposition

**Foundation Modeling:** `PASS`

**Gate 0:** `PASS — Discovery and Modeling ready for formal Gate 1 review`

**Foundation Certification:** `PENDING` until the complete gate evidence package, adversarial foundation review, and certification report are produced.

## Implementation boundary

No code, database, migration, API, UI, framework scaffolding, production configuration, or application implementation was created. The next work is documentation-only gate review and adversarial Foundation verification.

---

## HISTORICAL SOURCE: `foundation/26-gate-1-requirements-completeness-review.md`

# Gate 1 — Requirements Completeness Review

**Date:** 2026-08-25
**Phase:** Foundation Requirements Review
**Scope:** Business requirements only; no implementation judgment
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`

## 1. Executive Result

The Foundation contains a useful technology-independent business model and a large set of user-authorized policies. It is not yet an authoritative requirements contract for implementation. The principal deficiency is not document absence; it is that several material business decisions remain represented as broad principles or configurable placeholders rather than explicit, testable requirements.

Gate 1 is therefore **PASS WITH NON-BLOCKING OPEN ITEMS**. Approval-threshold policy is now explicitly configurable and fail-closed; exact amounts remain configuration inputs and are not invented.

No code, database, schema, migration, API, UI, framework, seed, or implementation test was created.

## 2. Coverage Matrix

| Domain | Actors | Capability | Authority/scope | Lifecycle | Finance | Privacy/audit | Reporting | Requirement quality |
|---|---|---:|---:|---:|---:|---:|---:|---|
| Organization/campus/branch/department | Partial | Yes | Partial | Partial | Partial | Partial | Partial | AMBIGUOUS |
| Ownership/governance | Yes | Yes | Partial | Partial | N/A | Yes | Partial | PASS WITH DETAIL OPEN |
| Identity/positions/roles/scopes | Yes | Yes | Partial | Partial | N/A | Yes | Partial | AMBIGUOUS |
| People/guardians/admissions | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Students | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Academic structure/delivery | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Assessment/placement/completion | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| HR/payroll | Partial | Yes | Partial | Partial | Yes | Yes | Partial | INCOMPLETE |
| Finance/accounting/student finance | Yes | Yes | Partial | Partial | Yes | Yes | Partial | PASS WITH NON-BLOCKING DETAIL OPEN |
| Funding/scholarship/sponsorship | Partial | Yes | Partial | Partial | Yes | Yes | Partial | INCOMPLETE |
| Books/inventory/assets/facilities | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Security/privacy/documents | Partial | Yes | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |
| Testing/marketing/events/communication | Partial | Yes | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Reporting/resilience/integrations | Partial | Yes | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |

`Partial` means the concept exists but does not yet meet the required explicitness for all material workflows.

## 3. Requirements Quality Findings

### RQ-001 — Requirements are unevenly specified

The registry contains high-level requirements and traceability seeds, but not a complete requirement record for each material domain. Many entries do not state all actors, preconditions, outputs, rejection behavior, cancellation, reversal, recovery, or acceptance criteria.

**Classification:** Missing requirement structure; material but repairable through modeling.

### RQ-002 — Configurable does not define valid behavior

“Configurable by program,” “within limits,” “according to risk,” and “where applicable” are useful design directions but do not define the valid range, owner, effective-date behavior, approval, or failure behavior of the policy.

**Classification:** AMBIGUOUS.

### RQ-003 — Agent defaults are not consistently traceable

Agent defaults are documented, but some are not linked to individual requirement IDs, affected domains, risks, and acceptance criteria. They must remain visibly distinct from user decisions.

**Classification:** Traceability gap.

## 4. Contradictions

| ID | Contradiction | Disposition |
|---|---|---|
| C-G1-001 | Earlier O-124 permitted any Owner to assign any existing role to themselves; later MD-006 prohibits independent self-assignment of sensitive or organization-wide privilege. | Resolved by later consolidated user decision; earlier entry must be marked superseded in all registries. |
| C-G1-002 | Earlier O-126 allowed the General Manager to approve incompatible financial powers; MD-007 prohibits one person controlling initiation, approval, recording, and reconciliation. | Resolved by later consolidated user decision; earlier entry must be marked superseded everywhere. |
| C-G1-003 | Teacher reassignment appeal and temporary replacement defaults conflict. | Resolved by O-117; user decision supersedes AD-001. |
| C-G1-004 | Decision Ledger contains duplicate decision identifiers D-F-042–D-F-047 and D-F-078–D-F-081 with different meanings. | BLOCKING documentation integrity defect; identifiers must be renumbered and cross-references repaired before Gate 1 can pass. |
| C-G1-005 | `00-foundation-state.md` still reports HEAD as `e9322b2`, although later Foundation commits exist. | Documentation evidence defect; update snapshot. |
| C-G1-006 | `12-open-questions-and-risks.md` retains questions and conflicts already resolved by later decisions. | Documentation synchronization defect; mark resolved or superseded. |

## 5. Ambiguities

- Exact financial/risk thresholds are not defined.
- “Material,” “sensitive,” “routine,” “senior,” “serious,” “high-risk,” and “organization-wide” do not have operational classification rules.
- Approval matrix rows do not consistently identify requester, reviewer, approver, escalation, expiry, and rejection behavior.
- Campus/branch/department state transitions are not fully enumerated.
- Program-specific progression and admission policies have no minimum required rule set.
- Category-specific retention has no approved category periods.
- Compensation combinations are permitted but contract precedence is not fully specified.
- Agreement-specific scholarship and restricted-fund behavior has no minimum required agreement fields.

## 6. Missing Requirements

The requirements contract still needs explicit records for, at minimum:

1. Complete structural operation matrix, including department changes and reopening.
2. Material-action classification and threshold authority.
3. Full role/permission/scope assignment lifecycle.
4. Student identity, guardian, admission, withdrawal, suspension, and reactivation acceptance rules.
5. Academic period, enrollment, class, attendance, assessment, progression, and completion state transitions.
6. HR employment, contract, leave, discipline, termination, and final-settlement requirements.
7. Payroll period, correction, reversal, and locking requirements.
8. Financial period, journal, reconciliation, and recovery requirements.
9. Scholarship, sponsorship, restricted-fund, and unused-fund requirements.
10. Resource custody, loss, damage, disposal, and facilities work-order requirements.
11. Document verification, disclosure, retention, and expiry requirements.
12. Report definitions, period rules, scope rules, and reconciliation expectations.

## 7. Authority Gaps

The following remain insufficiently explicit:

- Who approves each class of permission increase beyond the general Owner rule.
- Which positions count as senior or sensitive in every domain.
- Who approves reopening or escalation when a reviewer disagrees.
- Exact authority for program, academic period, financial period, cash drawer, bank account, document, room, inventory, report, and relationship scopes.
- Authority and appeal outcomes for employee and student decisions.

## 8. Scope Gaps

Scope types are named, but assignment rules are incomplete for program, academic period, financial period, cash drawer, bank account, document, room, inventory location, report, work queue, and relationship scopes. The scope matrix needs named requesters, approvers, executors, expiry behavior, and revocation behavior for each.

## 9. Lifecycle Gaps

The model gives lifecycle patterns, but several entities lack complete creation, activation, modification, suspension, closure, reopening, cancellation, reversal, correction, concurrency, and recovery requirements. This is especially material for enrollment, assessment results, employment, payroll, financial periods, funding, documents, and assets.

## 10. Financial Gaps

The financial model is directionally strong: double-entry, no direct balance editing, controlled transactions, reconciliation, immutable history, and segregation of duties are present. It is not yet complete enough to implement because:

- approval thresholds are unknown;
- financial period state and reopening authority are broad;
- advances, deductions, liabilities, payables, and payroll correction rules are not fully specified;
- opening balances and close/reopen acceptance rules are missing;
- restricted-fund accounting and unused-fund treatment depend on agreements;
- report definitions and reconciliation tolerances are not defined.

## 11. Academic Gaps

Program-specific rules are allowed, but the minimum required configuration and exception behavior are not defined for admission, placement, attendance, grading, progression, repetition, transfer, withdrawal, suspension, completion, and appeals. The model is sufficient as a boundary, not as a complete implementation contract.

## 12. HR/Payroll Gaps

Both employee and contractor models are allowed, but required contract fields, precedence between contract and assignment, leave categories, attendance sources, overtime, deductions, advances, payroll correction, termination settlement, and approval thresholds remain incomplete.

## 13. Privacy/Security Gaps

The classification and disclosure principles are strong. Missing requirements include consent withdrawal effects, guardian dispute handling, retention periods by category, incident severity levels, external disclosure approval, document verification authority, and emergency access review outcomes.

## 14. Reporting Gaps

A mandatory report family is named, but each critical report still needs definition, authoritative source, period, scope, filters, responsible owner, reconciliation expectation, and historical behavior. “Full set” is not sufficient as a report requirement.

## 15. Exception Gaps

The exception structure is defined, but escalation, expiry, appeal, precedent handling, and approval authority are not specified consistently across academic, HR, financial, funding, security, and structural exceptions.

## 16. Resilience Gaps

Acceptable loss and downtime are defined. Financial transaction recovery, in-flight operation treatment, audit-history restoration verification, integration replay, notification failure, and recovery acceptance evidence are not yet explicit enough for a complete requirements contract.

## 17. Traceability Findings

Traceability currently has seed rows rather than complete coverage. Critical requirements do not yet consistently trace through:

`Requirement → Rule → Domain → Entity → Authority → Scope → Workspace → Report → Notification → Acceptance Criterion`

Implementation-level database/service/API links are correctly deferred, but business-level entity, acceptance, exception, and report links are still missing in many domains.

## 18. Legacy Contamination Findings

No active legacy implementation was found in the current tree. Historical references are clearly labeled in the legacy report. However, stale historical paths and old decision records could be mistaken for current authority unless the registry synchronization defects above are repaired.

**Result:** No source contamination; documentation contamination risk remains controlled but open.

## 19. Non-Blocking Unknowns

These do not block the generic requirements model when explicitly represented as configuration or agreement-specific inputs:

- future campus/branch names and activation dates;
- exact legal succession documents, before ownership implementation;
- agreement-specific scholarship/funding terms, before each agreement is configured;
- additional contract variations, before each contract type is used;
- routine technical mechanics handled by agent-decided defaults.

## 20. Genuine Blocking Decisions

1. **Decision-ledger identity integrity:** repaired during review; identifiers are now unique.
2. **Minimum acceptance rules for material academic, HR, and financial lifecycles:** detailed acceptance catalogs remain required before implementation authorization, but can be completed as Foundation modeling work without a new business decision.

No genuine user blocker remains for Gate 1.

## 21. Gate Decision

**PASS WITH NON-BLOCKING OPEN ITEMS**

Gate 1 passes at the requirements-contract level; remaining detailed lifecycle, report, and acceptance artifacts are required before implementation authorization but do not require new business-policy decisions. Requirements are substantially modeled; remaining detailed lifecycle, report, exception, and acceptance artifacts are required before implementation authorization but do not require new business decisions.

## Required follow-on modeling before implementation gates

- Maintain unique decision identifiers and synchronized open-question records.
- Expand lifecycle, report, exception, and traceability records for every critical domain during subsequent foundation gates.
- Preserve the configurable threshold requirement; no amounts are invented.
- Continue Gate 2 business-rule completeness review.

**STOP:** No architecture or implementation work is authorized.


## Gate 1 blocker resolution — 2026-08-25

G1-B01, G1-B02, and G1-B04 were processed as user decisions. G1-B03 conflicted with O-51/O-52 and was resolved by G1-C-001: routine expenses remain with Finance Manager approval, with General Manager as the only substitute. The authority contradiction is closed.

---

## HISTORICAL SOURCE: `foundation/27-gate-2-business-rule-completeness-review.md`

# Gate 2 — Business Rule Completeness Review

**Date:** 2026-08-25
**Phase:** Foundation Business Rule Review
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`
**Implementation:** Forbidden

## 1. Executive Result

The Foundation contains strong global control principles, especially for ownership, access, segregation of duties, historical truth, financial integrity, and auditability. The five genuine Gate 2 business-rule blockers were resolved through the consolidated blocker set. Remaining gaps are detailed rule-catalog and configuration work, not unresolved TOEFL House policy.

## 2. Rule Coverage Matrix

| Domain | Explicit rules | Authority | Scope | Exceptions | Reversal/history | Testable | Result |
|---|---:|---:|---:|---:|---:|---:|---|
| Ownership/governance | Partial | Partial | Yes | Partial | Yes | Partial | INCOMPLETE |
| Organization | Partial | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |
| Identity/access/scope | Strong global rules | Partial detail | Partial detail | Yes | Yes | Partial | INCOMPLETE |
| People/admissions/students | Partial | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Academic structure/delivery | Partial | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |
| Assessment/progression/completion | Partial | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |
| HR/payroll | Partial | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Finance/student finance | Strong principles | Partial detail | Partial detail | Partial | Yes | Partial | BLOCKED |
| Funding | Partial | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |
| Resources/facilities | Partial | Partial | Partial | Partial | Yes | Partial | INCOMPLETE |
| Privacy/security/documents | Strong principles | Partial detail | Partial detail | Partial | Yes | Partial | INCOMPLETE |
| Reporting/communication/resilience | Partial | Partial | Partial | Partial | Partial | Partial | INCOMPLETE |

## 3. Rule Classification Audit

Classification is conceptually defined, but the registry does not yet classify every rule consistently as USER-DECISION, AGENT-DECIDED-DEFAULT, CONTROL-INVARIANT, OPEN-QUESTION, or LEGACY-EVIDENCE. Several decision-ledger entries are marked DECIDED without the required rule, trigger, postcondition, exception, and test fields.

**Finding:** Registry structure exists; complete rule population does not.

## 4. Rule Consistency Audit

### Resolved conflicts

- Owner self-assignment is controlled by the later consolidated decision.
- Financial self-approval and combined initiation/approval/recording/reconciliation are prohibited.
- Teacher assignment appeal precedence is resolved by the explicit user decision; the earlier agent default is superseded.
- Expense approval is Finance Manager normally, General Manager only as substitute.
- Historical organization attribution remains immutable after transfer.

### Remaining consistency risks

- Earlier rule records and later consolidated decisions must be cross-linked as superseded, not merely described in narrative.
- “Routine,” “material,” “sensitive,” “senior,” and “high-risk” are not uniformly classified.
- Some authority rows say “final layer TBD,” so the rule cannot yet be independently tested.

## 5. Rule Dependency Analysis

Primary dependency chain:

`Organization → Scope → Position → Role → Permission → Workflow Authority → Approval → Financial/Academic Effect → Audit → Reporting`

`Person → Relationship → Student/Employee identity → Admission/Employment → Academic/Payroll/Finance → Access`

`Program → Level → Period → Class → Enrollment → Attendance/Assessment → Progression → Completion`

`Fee policy → Obligation → Payment → Allocation → Refund/Discount → Journal/Ledger → Reconciliation → Reporting`

No circular business dependency was proven, but several dependencies terminate in undefined policy placeholders. Those terminations are the main Gate 2 gaps.

## 6. Authority Rule Audit

Strongly established: two-Owner Owner-sensitive actions, structural creation/closure/reopening/transfer, no self-approval, GM/Finance separation, department and branch scope principles, dated delegation, and emergency review.

Incomplete: exact approver and escalation for many program, period, document, asset, report, and funding actions; appointment categories; emergency exception classes; and the full scope-assignment matrix.

## 7. Scope Rule Audit

Scope vocabulary is broad and appropriate. Assignment, inheritance, revocation, expiration, and cross-scope visibility rules are not fully expressed for financial resources, cash drawers, bank accounts, documents, rooms, inventory locations, reports, work queues, and relationships.

## 8. Financial Rule Audit

### Established controls

- Finance and Accounting own monetary truth.
- Double-entry accounting is required.
- Direct balance editing is prohibited.
- Corrections use controlled transactions.
- Refunds cannot exceed refundable source.
- Discounts cannot silently rewrite history.
- Cash is attributable and reconciled.
- Periods are controlled.
- Financial self-approval and combined incompatible duties are prohibited.
- Completed financial history is reconstructable.

### Incomplete rules

- Refund eligibility conditions are not defined beyond “documented policy conditions.”
- Discount eligibility and revocation behavior are not sufficiently deterministic.
- Payroll advances, deductions, overtime, absence, and correction precedence are incomplete.
- Opening balances, period reopening evidence, and reconciliation exception treatment are incomplete.
- Restricted-fund disbursement and unused-fund treatment depend on agreements without a minimum rule set.

## 9. Academic Rule Audit

Program-specific configuration is authorized, placement exceptions are recorded, and academic history is protected. The minimum required rules for admission, placement equivalence, attendance consequences, grading corrections, progression, repetition, transfer, suspension, withdrawal, completion, and appeals are not yet explicit enough to prevent divergent implementations.

## 10. HR/Payroll Rule Audit

Employment models and basic separation of HR and Finance are established. Contract precedence, pay-period treatment, overtime, deductions, advances, benefits, absence, payroll correction, and termination settlement rules remain incomplete or contract-specific without a minimum common rule.

## 11. Identity/Authorization Rule Audit

Default deny, minimum necessary, explicit scope, dated assignments, cross-branch expiry, no self-approval, and Owner controls are coherent. Detailed rules for role conflicts, system administration execution, relationship access, document access, and access review outcomes remain incomplete.

## 12. Lifecycle Rule Audit

Generic lifecycle patterns exist for student, academic, financial, employee, resources, documents, and exceptions. They do not yet specify all valid and invalid transitions, transition actors, rejection behavior, cancellation, reversal, recovery, and concurrent decision behavior for each material entity.

## 13. Exception Rule Audit

The required exception fields are defined. Qualifying conditions and approvers are not defined consistently. The terms “emergency,” “serious,” “material,” and “high-risk” need a reusable classification rule or explicit configured policy.

## 14. Privacy/Security Rule Audit

Classification and minimum-necessary disclosure are strong. Consent withdrawal, guardian disputes, incident severity, emergency disclosure, document verification, retention exceptions, and external disclosure rules remain incomplete.

## 15. Reporting Rule Audit

Reporting sovereignty is established. The rule registry lacks complete metric definitions, period behavior, scope behavior, reconciliation expectations, and ownership for each mandatory report family.

## 16. Historical Integrity Audit

The global historical-integrity rule is strong and consistent with transfers, corrections, reversals, academic records, financial facts, employment history, and audit records. Domain-specific immutable fields and correction rules remain to be enumerated.

## 17. Rule Testability Audit

An independent auditor could verify the global invariants, but not many domain rules. “According to policy,” “where applicable,” “within limits,” and “program-specific” are not independently testable until the owning policy, version, effective period, and acceptance conditions are identified.

## 18. Ambiguous Rules

- Standard refund eligibility.
- Standard discount eligibility and revocation.
- Minimum academic progression conditions.
- Payroll treatment where contracts do not specify a case.
- Definition of material/high-risk/sensitive actions.
- Approval of scope types not yet populated in the matrix.
- Exception review, appeal, and expiry behavior.

## 19. Missing Rules

- Minimum common rule for refund requests and cancellations.
- Minimum common rule for discount application and reversal.
- Minimum academic rule for an approved result and progression decision.
- Minimum payroll rule for unprovided contract details.
- Minimum restricted-fund rule when an agreement is silent.
- Full rule records for report calculation and reconciliation.
- Full rule records for document verification and disclosure.
- Full rule records for asset custody, loss, damage, and disposal.

## 20. Conflicting Rules

| ID | Conflict | Status |
|---|---|---|
| G2-C-001 | Prior consolidated rule MD-007 prohibits incompatible financial duties, while earlier O-126 allowed a General Manager override. | Resolved by MD-007; earlier record must remain marked superseded. |
| G2-C-002 | Earlier agent temporary-replacement default conflicts with teacher appeal pause. | Resolved by user decision; agent default superseded. |
| G2-C-003 | Decision Ledger history previously contained duplicate identifiers. | Repaired before this review; uniqueness verified. |

## 21. Superseded Rules

- The agent default permitting temporary teacher replacement during an appeal is superseded by O-117.
- The earlier permissive incompatible-financial-power interpretation is superseded by MD-007.
- Any legacy repository rule remains LEGACY-EVIDENCE unless explicitly promoted.

## 22. Agent-Decided Defaults

The following may be safely derived as enterprise mechanics and are not user-policy claims:

- append-only audit evidence;
- explicit approval records;
- effective dating;
- automatic expiry of dated access;
- rejection/cancellation as distinct outcomes;
- controlled reversals rather than destructive edits;
- fail-closed handling of missing policy;
- standard idempotency, concurrency, retry, pagination, search, notification, and recovery mechanics.

Each remains reversible before implementation and must be linked to the affected rule and risk.

## 23. Non-Blocking Open Items

- Future campus/branch names and activation dates.
- Exact legal succession documents before ownership implementation.
- Agreement-specific scholarship/funding terms before configuration.
- Additional compensation contract variations before use.
- Exact numeric thresholds, because the threshold framework is now configurable and fail-closed.
- Technical enforcement choices, which are deferred to later architecture gates.

## 24. Genuine Blocking Decisions — Resolved

The following business-rule decisions are genuinely blocking because two competent implementers could otherwise create materially different outcomes:

1. Standard refund and cancellation eligibility.
2. Standard discount eligibility, expiry, and reversal.
3. Minimum academic rule for an approved progression decision when a program-specific rule is absent.
4. Minimum payroll treatment when a contract does not specify absence, overtime, advances, or deductions.
5. Minimum restricted-fund treatment when an agreement is silent.

These were resolved in the consolidated Gate 2 blocker set. Exact financial amounts remain configurable and were not invented.

## 25. Gate Decision

**PASS WITH NON-BLOCKING OPEN ITEMS**

The Foundation now has deterministic governing rules for the formerly blocking refund, discount, progression, payroll, and restricted-fund cases. Detailed rule catalogs, configuration values, and acceptance cases remain required before implementation authorization. No architecture or implementation work is authorized.

## Required next action

Continue Gate 3 — Domain Model Completeness Review. Expand detailed rule records, lifecycle catalogs, acceptance criteria, and traceability during the remaining Foundation gates.

---

## HISTORICAL SOURCE: `foundation/28-gate-3-domain-model-completeness-review.md`

# Gate 3 — Domain Model Completeness Review

**Date:** 2026-08-25
**Phase:** Foundation Domain Model Audit
**Gate status:** `FAIL`
**Implementation:** Forbidden

## 1. Gate status

Gate 3 fails. The Foundation has a coherent top-level domain map and strong ownership principles, but the current business domain model is not sufficiently complete or explicit for an implementation team to represent the institution without inventing material behavior.

This is a model completeness failure, not a technical implementation failure. No code, database, schema, migration, API, UI, framework, production configuration, seed, or implementation test was created.

## 2. Scope

This audit tested whether business concepts, entities, relationships, source-of-truth ownership, lifecycle transitions, authority, scope, financial effects, historical facts, privacy, configuration, and derived values are representable and non-duplicative.

## 3. Inputs audited

All current files under `docs/foundation/`, including the Gate 0–2 records, decision ledger, requirements registry, rules/invariants/traceability, canonical domain model, entity/relationship registry, governance/access model, lifecycle model, finance/academic/HR controls, data/privacy/resilience model, and agent defaults.

## 4. Domain inventory

The inventory covers organization, ownership, identity/access, people, admissions, students, academic structure/delivery, assessment, completion, HR/payroll, finance/student finance, funding, resources/facilities, communication, documents, reporting, audit/security, and resilience/integrations.

**Result:** Domain names are present. Domain specifications are not yet complete enough at entity and transition level.

## 5. Canonical entity registry result

The registry identifies the principal entities but is incomplete for the required business model. Missing or insufficiently separated concepts include:

- ownership agreement and succession event;
- structural change request and effective organizational assignment;
- position definition versus position assignment;
- permission definition versus permission grant and access review;
- approval request, review, decision, conflict, and escalation;
- applicant and admission decision as separate from visitor/person;
- student status history as separate from student identity;
- guardian consent and disclosure authorization;
- program version, level completion, progression decision, repetition decision, and academic appeal;
- class membership as separate from enrollment and class;
- scheduled session as separate from class;
- teacher assignment and substitution assignment;
- attendance correction and approval;
- assessment attempt, result, moderation, release, and correction;
- employment contract, compensation assignment, payroll calculation, payroll approval, and settlement;
- obligation, payment, allocation, refund, discount, adjustment, reversal, journal, and reconciliation exception;
- funding agreement, restriction, allocation, eligible use, disbursement, and return/hold decision;
- book issue, return, loss/damage, replacement obligation, and custody transfer;
- asset custody, maintenance, disposal approval, and disposal fact;
- document verification decision, disclosure, retention decision, and consent withdrawal;
- incident, complaint, appeal, report definition, report run, export request, and notification delivery.

**Severity:** HIGH.

## 6. Source-of-truth registry

### Strengths

The model explicitly assigns ownership to Finance for monetary truth, Academic Structure for academic definitions, Academic Delivery for participation facts, Assessment for results, HR for employment, Identity/Access for effective access, Organization for structural identity, and Reporting as a consumer.

### Findings

The source-of-truth registry is not complete for the critical facts required by the directive. It lacks an authoritative entity and rule record for many of the entities listed above. A prose statement that a domain “owns” a concept is not enough to prevent duplicate writers.

**Severity:** HIGH.

## 7. Relationship audit

The relationship principles are correct, but cardinality, effective dating, end-date behavior, transfer behavior, deletion/deactivation behavior, and authority implications are not enumerated for all required relationships.

High-risk incomplete relationships:

- Person ↔ User Account
- Person ↔ Employment and Student
- Student ↔ Guardian Relationship
- Student ↔ Enrollment ↔ Class Membership
- Class ↔ Session ↔ Teacher Assignment
- Payment ↔ Obligation ↔ Allocation ↔ Ledger
- Refund ↔ Payment
- Discount ↔ Obligation
- Scholarship/Funding ↔ Student/Program/Restriction
- Document ↔ Owner/Disclosure/Consent
- Approval ↔ Action/Resource/Scope
- Audit Event ↔ Actor/Target

**Severity:** HIGH.

## 8. Lifecycle audit

The lifecycle model provides generic patterns but not complete per-entity state machines. It does not yet enumerate valid and invalid transitions, actors, prerequisites, rejection, cancellation, reopening, reversal, concurrency, and terminal-state behavior for each critical entity.

This is material for student status, admission, enrollment, class membership, assessment result, employment, payroll, obligation, payment, refund, scholarship, funding, document, incident, complaint, and asset disposal.

**Severity:** HIGH.

## 9. Authority and access audit

The model correctly rejects title-only authority and requires position, role, permission, scope, context, and approval. However, the authority matrix does not yet define every material action and resource scope. It is not yet possible to determine consistently who may create, modify, approve, reverse, disclose, export, or close every critical entity.

Open matrix areas include program, academic period, financial period, cash drawer, bank account, document, room, inventory location, report, work queue, guardian relationship, and student disclosure.

**Severity:** HIGH.

## 10. Financial-domain audit

The financial boundary is conceptually sound: Finance owns truth, balances are derived, direct edits are prohibited, and transactions are reconciled. The entity model still needs separate authoritative records and relationships for obligation, payment, allocation, refund, discount, adjustment, reversal, journal, cash position, payroll settlement, funding restriction, and reconciliation exception.

Without those distinctions, two implementers could incorrectly treat a payment as a balance update or a refund as deletion.

**Severity:** CRITICAL until repaired.

## 11. Academic-domain audit

The academic chain is present and conceptually separated. The model still needs explicit identity and lifecycle boundaries for admission, placement, enrollment, class membership, session, attendance fact, assessment attempt, result, moderation, progression, repetition, transfer, completion, graduation, certificate, and appeal.

Progression is correctly not inferred from one score, but the model does not yet define the authoritative progression decision record and its relation to component evidence.

**Severity:** HIGH.

## 12. HR/payroll audit

HR and Finance ownership is separated. The model does not yet fully distinguish contract terms, compensation assignments, work basis, leave, attendance inputs, payroll calculation, payroll approval, payment, correction, reversal, termination, and final settlement.

Contract-silent behavior is defined as hold for review, but the entity and state needed to hold and resolve the item are not modeled.

**Severity:** HIGH.

## 13. Privacy audit

Data classes and minimum-necessary disclosure are modeled. Guardian consent, adult-student consent, disclosure authorization, revocation, document verification, incident disclosure, export approval, and retention decisions are not yet complete relationship/entity models.

**Severity:** HIGH.

## 14. Domain-boundary audit

The proposed domains are coherent and avoid obvious duplication at the conceptual level:

- Organization & Governance
- Identity & Access
- Admissions & Student
- Academic
- HR & Payroll
- Finance
- Library & Inventory
- Facilities & Operations
- Communication
- Security & Audit
- Reporting

Boundary contracts are not yet explicit enough to identify every allowed read, command, event, and forbidden direct write. This is a medium structural gap that becomes high for Finance, Access, Academic results, and Payroll.

## 15. Derived-data audit

The model states that balances, KPIs, student status summaries, attendance percentages, progression summaries, and reporting totals are derived. It does not provide a complete derived-data catalog containing source facts, formula authority, recalculation behavior, historical behavior, and override prohibition.

**Severity:** HIGH for financial balances, student status, attendance, progression, payroll totals, and reports.

## 16. Historical-integrity audit

Global historical preservation is strong. Effective-dated relationships and immutable fact requirements are not enumerated for every transfer, correction, assignment, status, result, obligation, funding, document, and resource relationship.

**Severity:** HIGH.

## 17. Legacy-contamination audit

The active tree contains Foundation artifacts only. Legacy implementation remains in Git history and is classified as LEGACY-EVIDENCE. No legacy structure has been promoted as active authority.

**Result:** PASS.

## 18. Findings by severity

| Severity | Count | Summary |
|---|---:|---|
| CRITICAL | 1 | Financial entity/source-of-truth model is not sufficiently explicit. |
| HIGH | 8 | Entity registry, source-of-truth, relationships, lifecycles, authority, academic, HR/payroll, privacy, derived data, and historical relationships remain incomplete. |
| MEDIUM | 2 | Domain contracts and configuration/entity separation need formalization. |
| LOW | 0 | No low-severity issue was used to conceal a material gap. |

## 19. Repairs performed

- Audited the canonical domain map against the full minimum domain list.
- Confirmed and documented domain ownership boundaries.
- Confirmed global financial, historical, privacy, access, and segregation-of-duties principles.
- Identified the entity and relationship gaps that prevent implementation-safe modeling.
- Preserved legacy contamination controls.

These are audit and clarification repairs. They do not repair the missing detailed domain model itself.

## 20. Remaining open items

The following are modeling work items, not requests for technical implementation:

1. Expand the entity registry into one authoritative row per critical business entity.
2. Add cardinality and effective-date behavior to critical relationships.
3. Add per-entity state transition registries.
4. Complete source-of-truth ownership and forbidden-writer records.
5. Complete authority/scope rows for every material action and resource.
6. Add financial transaction, allocation, reconciliation, and funding restriction models.
7. Add academic result/progression evidence and decision models.
8. Add HR/payroll contract and settlement models.
9. Add disclosure, consent, document verification, and retention decision models.
10. Add derived-data authority catalog and report lineage.
11. Add domain contract and cross-domain dependency records.

## 21. Traceability

The current traceability is seed-level and does not cover all entities, relationships, lifecycle transitions, source-of-truth records, derived calculations, reports, notifications, and acceptance criteria. No implementation traceability is required at this gate, but business-level traceability is incomplete.

## 22. Verification results

- Domain inventory: PASS at naming level; FAIL at detailed model level.
- Canonical entities: FAIL.
- Relationships: FAIL.
- Source of truth: FAIL.
- Lifecycle representation: FAIL.
- Authority and scope representation: FAIL.
- Financial model representation: FAIL pending explicit transaction entities.
- Academic model representation: FAIL pending explicit evidence/decision entities.
- HR/payroll representation: FAIL pending contract/calculation/settlement entities.
- Privacy representation: FAIL pending consent/disclosure entities.
- Derived data: FAIL.
- Historical integrity: PASS in principle; FAIL in per-relationship specification.
- Legacy contamination: PASS.

## 23. Gate decision

# FAIL

A CRITICAL financial domain-model gap and multiple HIGH domain-model gaps remain. The model must be repaired and independently re-audited before Gate 3 can pass.

**Next required gate:** None yet. Gate 3 remediation and re-review must complete first.

**Implementation boundary:** No code, database, schema, migration, API, UI, framework, production configuration, seed, or implementation test may be created.

---

## HISTORICAL SOURCE: `foundation/29-canonical-entity-registry.md`

# Canonical Entity Registry

**Status:** Gate 3 remediation model

| Entity | Type | Owning domain | Authoritative responsibility | Immutable facts |
|---|---|---|---|---|
| Organization/Campus/Branch/Department | domain entities | Organization | structure and current scope | identity, historical attribution |
| Person | domain entity | People | verified human identity | identity evidence and merge history |
| User Account | domain entity | Identity | authentication identity | account creation/deactivation history |
| Position/Position Assignment | entity + relationship | Identity/HR | job and effective responsibility | assignment dates and approver |
| Role/Permission/Scope Grant | entity + relationship | Access | effective authorization | grant, scope, dates, approver |
| Delegation | relationship entity | Governance | temporary authority | delegator, recipient, limits, dates |
| Guardian Relationship/Consent/Disclosure | relationship entities | Student/Privacy | relationship and disclosure authority | verification, consent, revocation |
| Visitor/Applicant/Admission Decision | entities | Admissions | prospect and admission outcome | decision, reason, evidence |
| Student/Student Status | entity + history | Students | student identity and lifecycle | identity, status transitions |
| Program/Version/Level/Academic Period | entities | Academic Structure | academic definitions | published definitions and dates |
| Enrollment/Class Membership/Class/Session | entities | Academic Delivery | participation and delivery | membership, schedule, attendance facts |
| Teacher Assignment/Substitution | relationship entities | Academic Delivery | teaching responsibility | assignment scope and dates |
| Attendance Record | fact entity | Academic Delivery | attendance fact | original mark and correction history |
| Assessment Attempt/Evidence/Result | entities | Assessment | evidence and result | submitted evidence and approved result |
| Progression Decision/Academic Appeal | decision entities | Academic | progression and appeal outcomes | decision, reason, reviewer |
| Employment/Contract/Compensation | entities | HR | employment terms and entitlement | signed terms, effective dates |
| Leave/Performance/Termination/Settlement | entities | HR | employee events and closure | approvals and history |
| Payroll Period/Calculation/Result/Adjustment | entities | Payroll | calculated entitlement and payment instruction | approved calculation and correction |
| Account/Financial Period/Journal/Journal Line | entities | Finance | accounting truth | posted financial facts |
| Obligation/Payment/Allocation/Refund/Discount/Adjustment/Reversal | entities | Student Finance | receivable lifecycle | source, amount, approval, reversal links |
| Cash Drawer/Cash Movement/Reconciliation | entities | Finance | physical cash and variance | custody and observed counts |
| Funding Source/Fund/Restriction/Scholarship Award | entities | Funding | restricted funding truth | agreement and restriction |
| Book/Issuance/Return/Loss/Damage/Asset/Custody | entities | Resources | resource ownership and custody | custody and movement history |
| Facility/Maintenance Request/Work Order | entities | Facilities | operational work | request, approval, completion |
| Document/Version/Verification/Retention Decision | entities | Documents | evidence and retention | original/version/verification history |
| Incident/Complaint/Report/Export/Notification | entities | Security/Reporting/Communication | controlled operational outputs | actor, purpose, outcome |
| Approval Request/Review/Decision/Conflict | entities | Governance | decision evidence | approvers and decisions |
| Audit Event | append-only evidence | Audit | accountability | actor, target, before/after, time |

No entity above owns another domain's truth. States, events, configuration, value objects, and projections are not substituted for domain entities.

---

## HISTORICAL SOURCE: `foundation/30-source-of-truth-registry.md`

# Source-of-Truth Registry

| Fact | Canonical source | Allowed writer | Forbidden writer | Derived? | Historical rule |
|---|---|---|---|---|---|
| Person identity | Person + verified identity evidence | People authority | Student/HR screens | No | merge only by verified decision |
| User identity | User Account | Identity authority | Person profile | No | deactivate, never erase |
| Employment | Employment/Contract | HR | Payroll/manager shortcuts | No | append effective terms |
| Position assignment | Position Assignment | HR/governance | Role editor | No | close prior assignment |
| Role/permission/scope | Role/Permission/Scope Grant | Access governance | UI or domain consumer | No | dated, approved, auditable |
| Delegation | Delegation | Governance authority | recipient | No | expires automatically |
| Student identity/status | Student + Student Status History | Students | Enrollment/report | Status summary derived | preserve transitions |
| Guardian relationship | Guardian Relationship | Student/People authority | parent portal | No | verification/revocation history |
| Admission | Admission Decision | Admissions/Academic authority | Reception conversion alone | No | decision and evidence retained |
| Enrollment/class membership | Enrollment + Class Membership | Academic Delivery | Student balance/report | No | transfer closes old membership |
| Academic period | Academic Period | Academic Structure | report/payroll | No | published period immutable |
| Class/session/attendance | Class, Session, Attendance Record | Academic Delivery | Teacher profile | Attendance metrics derived | corrections append history |
| Assessment/placement/progression | Evidence, Result, Progression Decision | Assessment/Academic | score widget/report | summaries derived | original decisions retained |
| Graduation/certificate | Graduation Eligibility Decision + Certificate | Completion | level/status summary | No | issued record immutable |
| Compensation/payroll | Contract/Compensation; Payroll Calculation/Result | HR/Payroll | manager or report | totals derived | corrections reverse/adjust |
| Financial obligation | Obligation/Obligation Line | Student Finance | balance field | Balance derived | original charge retained |
| Payment/allocation/refund | Payment, Allocation, Refund | Finance | student UI | Balance derived | source never deleted |
| Discount/adjustment/reversal | Discount, Adjustment, Reversal | Finance under approval | direct balance writer | No | original obligation preserved |
| Journal/ledger | Journal + Journal Lines | Finance | operational modules | Ledger balance derived | posted journals immutable |
| Cash/reconciliation | Cash Drawer, Movement, Reconciliation | Finance | cashier summary | position derived | observed variance retained |
| Scholarship/funding | Award, Fund, Restriction, Allocation | Funding + Finance | student profile | utilization derived | agreement and restriction retained |
| Book/asset custody | Issuance/Custody/Movement | Resources | student or branch summary | stock derived | movement history retained |
| Document/consent/disclosure | Document Version, Consent, Disclosure | Documents/Privacy | arbitrary URL/UI | No | retention and revocation retained |
| Audit event | Audit Event | Audit subsystem | end users | No | append-only |
| Report | Report Definition + authoritative source facts | Reporting | dashboard copy | Metrics derived | reproducible by period/scope |

---

## HISTORICAL SOURCE: `foundation/31-relationship-registry.md`

# Relationship Registry

| Parent ↔ Child | Cardinality | Required | Owner | Effective dates | History/transfer/deletion |
|---|---|---|---|---|---|
| Organization ↔ Campus | 1:N | campus requires one | Organization | active dates | close, never delete history |
| Campus ↔ Branch | 1:N over time | branch has one active campus | Organization | from/to required | transfer closes prior link |
| Organization/Campus/Branch ↔ Department Unit | 1:N | unit has one scope | Organization | from/to | scope change appends link |
| Person ↔ User | 1:0..N | account requires person | Identity | account dates | deactivate, no duplicate verified account |
| Person ↔ Position Assignment | 1:N | assignment requires person/position | HR | required | close prior assignment |
| Position ↔ Role | N:M via role policy | role may have many | Access | policy version dates | version, do not rewrite active history |
| Role ↔ Permission | N:M | permission required | Access | policy dates | grant history |
| Permission ↔ Scope Grant | 1:N | grant has scope | Access | required | expiry/revocation retained |
| Person ↔ Student/Employee | 1:0..N | identity required | People/Students/HR | status dates | no silent merge |
| Student ↔ Guardian | N:M via relationship | verification required | Students | from/to | revoke, retain history |
| Student ↔ Admission | 1:N | applicant/admission | Admissions | decision date | prior decisions retained |
| Student ↔ Enrollment | 1:N | period required | Academic | from/to | transfer closes old enrollment |
| Enrollment ↔ Program/Level/Period | N:1 each | required | Academic Structure | effective period | published references immutable |
| Enrollment ↔ Class Membership | 1:N over time | class required | Academic Delivery | from/to | old membership retained |
| Class ↔ Session | 1:N | session needs class | Academic Delivery | scheduled date | cancellation retained |
| Class ↔ Teacher Assignment | N:M via assignment | dates required | Academic Delivery | from/to | substitution is separate |
| Teacher/Employee ↔ Branch | N:M via assignment | explicit scope | HR/Academic | from/to | branch history retained |
| Employee ↔ Contract | 1:N | effective contract | HR | from/to | prior contract closed |
| Contract ↔ Compensation | 1:N | contract basis | HR | from/to | entitlement immutable once used |
| Employee ↔ Leave/Payroll | 1:N | period required | HR/Payroll | period dates | correction/reversal retained |
| Obligation ↔ Payment | N:M via Allocation | source required | Finance | posting date | allocation cannot exceed source |
| Payment ↔ Refund | 1:N via Refund Line | source required | Finance | refund date | original payment retained |
| Obligation ↔ Discount | 1:N | approval required | Finance | effective date | original charge retained |
| Adjustment ↔ Journal | 1:N source link | journal required | Finance | posting date | source and reversal linked |
| Journal ↔ Journal Lines | 1:N | balanced journal | Finance | period | posted lines immutable |
| Fund ↔ Restriction/Allocation | 1:N | restriction as applicable | Funding | agreement dates | no silent reclassification |
| Scholarship ↔ Student | N:1 | award required | Funding | award dates | revocation retains history |
| Book ↔ Issuance/Inventory | 1:N | item/source required | Resources | movement dates | custody history retained |
| Asset ↔ Custody | 1:N | custodian/location | Resources | from/to | disposal closes custody |
| Document ↔ Person/Student/Employee | N:1 target | owner required | Documents | version dates | archive, do not expose by URL |
| Consent ↔ Subject/Purpose | N:1 per purpose | purpose required | Privacy | from/to/revoked | revocation retained |
| Disclosure ↔ Recipient/Purpose | N:1 event | authority required | Privacy | disclosure time | append-only |
| Approval ↔ Action/Resource | 1:N reviews | action required | Governance | request/decision dates | rejection/cancel retained |
| Audit Event ↔ Actor/Target | N:1 each | actor/target as applicable | Audit | event time | append-only |

---

## HISTORICAL SOURCE: `foundation/32-lifecycle-transition-registry.md`

# Lifecycle Transition Registry

**Rule:** No boolean or mutable label substitutes for a business transition. Every transition records actor, authority, scope, effective date, reason, evidence, and audit.

| Entity | States | Allowed transitions | Forbidden/repair |
|---|---|---|---|
| Organization/Campus/Branch/Department | Draft, Active, Suspended, Closed | Draft→Active; Active↔Suspended; Active→Closed; Closed→Reopened→Active | close requires approval; no delete |
| Position Assignment/Delegation | Proposed, Active, Expired, Revoked | Proposed→Active→Expired/Revoked | expired access cannot continue |
| Student/Admission | Prospect, Applicant, Admitted, Active, Suspended, Withdrawn, Completed, Alumni | verified ordered transitions; reactivation only by approval | no silent status overwrite |
| Enrollment/Membership | Requested, Active, Frozen, Transferred, Withdrawn, Completed | requested→active; active→freeze/transfer/withdraw/complete | no duplicate active seat |
| Class/Session | Planned, Published, Active, Cancelled, Completed, Archived | planned→published→active→completed; cancellation preserves record | no attendance on cancelled session without correction |
| Placement/Assessment | Draft, Started, Submitted, Scored, Moderated, Approved, Released, Appealed, Corrected | ordered review and release | score is not decision automatically |
| Progression/Graduation | Proposed, Reviewed, Approved, Rejected, Appealed, Superseded | review→approved/rejected; appeal→new decision | original decision retained |
| Employment/Contract | Candidate, Active, Leave, Suspended, Transferred, Terminated, Settled, Archived | approved employment transitions | payroll cannot invent silent terms |
| Payroll | Draft, Calculated, Reviewed, Approved, Paid, Corrected, Reversed, Locked | ordered period transitions | locked period mutation prohibited |
| Financial Period | Open, Closing, Closed, Reopened | open→closing→closed; closed→reopened only approved | reopen audited |
| Obligation/Payment | Proposed, Posted, Partially Settled, Settled, Cancelled, Reversed | source-controlled posting and settlement | no balance-only change |
| Refund/Discount/Adjustment | Requested, Reviewed, Approved, Posted, Rejected, Reversed | approval before posting; reversal links source | source cannot be deleted |
| Scholarship/Funding | Proposed, Approved, Active, Suspended, Revoked, Completed, Held | agreement and restriction controlled | silent unrestricted conversion prohibited |
| Issuance/Asset/Work Order | Requested, Approved, Issued/In Progress, Returned/Completed, Lost/Disposed/Cancelled | custody and work evidence required | disposal requires approval |
| Document/Consent | Draft, Submitted, Verified, Active, Expired, Revoked, Archived | verification and effective periods | revocation does not erase history |
| Incident/Complaint/Appeal | Open, Assigned, Investigating, Resolved, Rejected, Escalated, Closed | outcome and evidence required | no silent closure |

Terminal states retain history. Corrections append a new fact or decision; reversals point to the original.

---

## HISTORICAL SOURCE: `foundation/33-authority-scope-operation-registry.md`

# Authority and Scope Operation Registry

| Operation family | Initiator | Reviewer | Approver | Scope | Forbidden |
|---|---|---|---|---|---|
| Structure create/rename/transfer/close/reopen | General Manager | affected manager | two Owners | organization/campus/branch | single actor |
| Owner admin/emergency suspension | unaffected Owners | unaffected Owners | two unaffected Owners | organization | affected Owner; single Owner |
| Permission/scope change | authorized requester | business owner/access administrator | per sensitivity; two Owners for org-wide | named scope | self-grant, title-only grant |
| Admissions/conversion | Reception/Admissions | Academic/Admissions | policy owner | branch/program | Reception-only permanent conversion |
| Withdrawal/suspension/reactivation | student/guardian or manager | Academic/Finance as relevant | Academic/management authority | student/enrollment | silent obligation deletion |
| Progression/grade correction | teacher/Academic | independent Academic reviewer | Academic Management | student/class/period | original decision-maker final appeal reviewer |
| Payroll/compensation | HR/manager | HR + Finance | GM or Owners by risk | employee/period | beneficiary self-approval |
| Payment/refund/discount/adjustment | authorized operator | Finance | configured approver | student/branch/period | direct balance edit |
| Scholarship/funding | Funding/Academic | Finance + agreement owner | GM/Owners by risk | fund/student/program | restricted use without authority |
| Asset disposal/export | custodian/manager | Finance/privacy | two Owners when material | asset/location/report | bulk or material action without approval |

All temporary authority is dated, scoped, reasoned, and auditable. Missing policy fails closed. Exact monetary thresholds are configuration, not invented here.

---

## HISTORICAL SOURCE: `foundation/34-financial-domain-model.md`

# Canonical Financial Domain Model

## Financial entities and ownership

| Entity | Authority and purpose | Immutable core |
|---|---|---|
| Financial Obligation | amount owed by a liable party for an approved charge | source, original amount, debtor, period |
| Obligation Line | atomic charge within obligation | source, amount, category |
| Payment | money received from an external source | source, amount, method, received time, payer |
| Payment Allocation | allocation of payment to obligation lines | payment, obligation, amount |
| Refund / Refund Line | authorized return of received money | source payment, amount, reason |
| Discount | approved reduction of an obligation | eligibility, amount/rate, reason, effective dates |
| Adjustment | approved non-payment financial correction | source, amount, reason, authority |
| Reversal | transaction negating a prior posted transaction | original link, reason, amount |
| Journal / Journal Line | balanced accounting record | period, source, debit/credit, posting |
| Account | chart-of-accounts classification | code, type, effective definition |
| Financial Period | controlled reporting/posting window | dates, status, close authority |
| Cash Drawer / Cash Movement | accountable physical cash custody and movement | custodian, observed amount, movement |
| Reconciliation | comparison of expected and observed state | source set, observation, variance, explanation |
| Expense | approved business cost request and financial source | supplier, purpose, amount, approval |
| Funding Source/Fund/Restriction | money origin, pool, and permitted use | agreement, restriction, dates |
| Scholarship Award/Allocation | approved student benefit and funding application | award rule, student, fund, period |

Balances, receivables, cash positions, funding utilization, and payroll totals are derived from posted source facts. No entity stores an authoritative mutable balance.

## Mandatory financial rules

A payment posts only once, allocations cannot exceed payment or obligation, refunds cannot exceed refundable source, discounts preserve original obligation, adjustments and reversals retain source links, journals balance, cash movements reconcile to drawers, closed periods reject mutation, and restricted funds cannot be reclassified without authorized evidence. Every material transaction has actor, authorization context, scope, source, period, reason, and audit.

---

## HISTORICAL SOURCE: `foundation/35-academic-evidence-decision-model.md`

# Academic Evidence and Decision Model

| Concept | Classification | Authority | Rule |
|---|---|---|---|
| Placement Attempt | raw evidence container | Test Officer | immutable submission history |
| Placement Component Result | calculated evidence | scoring process | source responses retained |
| Placement Result | approved calculated result | Academic Management | release only after approval |
| Assessment Attempt/Evidence | raw evidence | Teacher/Assessment | corrections append history |
| Assessment Result | calculated result | Assessment | not automatically progression |
| Attendance Record | operational evidence | Academic Delivery | correction requires reason/approval |
| Progression Evaluation | evidence synthesis | Academic Management | consumes authoritative evidence |
| Progression Decision | official decision | Academic Management | explicit, dated, appealable |
| Academic Exception | authorized exception | Academic authority | reason, scope, expiry, audit |
| Academic Appeal | independent review | assigned reviewer | original decision retained |
| Graduation Eligibility Decision | official completion decision | Academic Management | program requirements plus approved exception |
| Certificate/Diploma | issued output | Completion authority | immutable issuance record |

Admission, placement, program, level, period, enrollment, class membership, session, attendance, assessment, progression, repetition, withdrawal, suspension, transfer, completion, and certification remain distinct facts.

---

## HISTORICAL SOURCE: `foundation/36-hr-payroll-domain-model.md`

# HR and Payroll Domain Model

| Entity | Owner | Purpose | Historical rule |
|---|---|---|---|
| Employment | HR | relationship and status | status history retained |
| Contract | HR | agreed terms | signed terms immutable once used |
| Position Assignment | HR/Access | effective job responsibility | close prior, start new |
| Compensation Rule/Component | HR | contractual entitlement | effective-dated |
| Work/Teaching Basis | HR/Academic | hours, classes, workload evidence | source evidence retained |
| Leave | HR | approved absence | approval/history retained |
| Payroll Period | Payroll/Finance | calculation window | controlled closing |
| Payroll Calculation | Payroll | computed entitlement | recalculation audited |
| Payroll Result | Payroll | approved payable result | correction/reversal, not overwrite |
| Payroll Adjustment | Payroll | approved signed correction evidence | source-linked and immutable |
| Finance Payroll Liability | Finance | recognized monetary liability sourced from approved Payroll evidence | one unique append-only fact per Payroll source |
| Advance/Loan | HR/Finance | employee obligation where applicable | balance derived from transactions |
| HR/Finance Clearance | HR/Finance | termination checks | both clear before closure |
| Settlement Proposal | Payroll | staged termination evidence | matching Finance fact required before approval |
| Employment Settlement | Finance | recorded termination settlement fact | immutable, branch-scoped, source-linked |
| Termination | HR | employment closure | access ends, history retained |

Contractual entitlement, calculated payroll, recognized liability, settlement, and actual payment are separate. Contract-silent absence/overtime/advance/deduction cases are held for HR and Finance review. Payroll never writes Finance liability or settlement facts; Finance recognizes approved signed sources and records the settlement.

---

## HISTORICAL SOURCE: `foundation/37-privacy-consent-disclosure-model.md`

# Privacy, Consent, and Disclosure Model

| Entity | Purpose | Required facts |
|---|---|---|
| Consent Purpose | defines requested use | purpose, channel, category |
| Consent | subject authorization | subject, purpose, status, effective period, evidence |
| Revocation | withdraws consent | actor, time, scope, effect |
| Disclosure | records information release | subject, recipient, purpose, authority, scope, time |
| Document Classification | sensitivity label | category, owner, access class |
| Document Verification | validates evidence | verifier, result, reason, time |
| Retention Rule | controls retention | category, period, legal/operational basis |

Guardian access requires verified relationship and relationship-specific permission. Adult student access requires consent unless a valid legal/safety basis applies. Marketing and communication consent are separate. Sensitive documents and bulk exports require purpose-based authorization, minimum disclosure, and audit. Revocation stops future use without erasing historical consent or disclosure evidence.

---

## HISTORICAL SOURCE: `foundation/38-derived-data-lineage-registry.md`

# Derived Data Lineage Registry

| Derived value | Source facts | Rule authority | History/override |
|---|---|---|---|
| Student balance | posted obligations, allocations, refunds, adjustments | Finance | recompute; no manual override |
| Outstanding obligation | obligation lines minus valid allocations/credits | Finance | as-of period |
| Branch revenue | posted income journals scoped to branch | Finance | period/as-of |
| Cash balance | posted cash movements and reconciliations | Finance | observed variance retained |
| Payroll total | Finance-recognized payroll liability facts; approved Payroll results and adjustments are source evidence | Finance (Payroll source recognition) | period/as-of/source lineage |
| Attendance percentage | attendance records and period definition | Academic | correction history retained |
| Progression status | approved progression decisions | Academic | never infer from score alone |
| Enrollment counts | active membership facts | Academic Delivery | as-of period |
| Academic/financial KPIs | authoritative domain facts | Reporting catalog | reproducible |
| Funding utilization | approved allocations and fund restrictions | Funding/Finance | agreement/as-of |
| Dashboard metrics/reports | registered source metrics | Reporting | no independent truth |

All derived values are calculated from canonical facts, use the central period authority, are scoped, reproducible, and cannot be manually edited as truth.

---

## HISTORICAL SOURCE: `foundation/39-domain-contracts.md`

# Domain Contracts — Business Level

| Domain | Owns | Exposes | Forbidden direct mutation |
|---|---|---|---|
| Organization & Governance | structure, approvals, delegation | effective structure and decisions | consumer-created structure |
| Identity & Access | account, assignment, role, permission, scope | effective access | UI-only grants |
| Admissions & Student | applicants, admission, student, guardian | verified identity/status | academic/finance-owned copies |
| Academic | programs, periods, classes, evidence, decisions | approved academic facts | report-derived status |
| HR & Payroll | employment, contract, entitlement, payroll | approved employment/payroll facts | manager balance edits |
| Finance | accounts, journals, obligations, payments, reconciliation | posted financial facts | operational money truth |
| Library & Inventory | books, custody, stock, assets | custody and movement facts | student/branch stock copies |
| Facilities & Operations | facilities, requests, work orders | operational status | direct financial posting |
| Communication | templates, messages, delivery history | approved communication output | arbitrary sends |
| Security & Audit | incidents, audit, classification | security evidence | audit mutation |
| Reporting | definitions, metrics, runs | derived reports | alternate calculations |

Cross-domain commands identify their owner, accepted input, authority, scope, outcome, audit, and failure behavior. Events notify consumers but do not transfer source-of-truth ownership.

---

## HISTORICAL SOURCE: `foundation/40-configuration-domain-classification.md`

# Configuration and Domain Classification

| Concept | Classification | Owner | Effective/history rule |
|---|---|---|---|
| Organization, campus, branch, department | Domain entity | Organization | structural history |
| Person, student, employee, book, asset | Domain entity | owning domain | identity/custody history |
| Fees, discounts, approval thresholds | Effective-dated configuration | Finance/Governance | published versions; no silent rewrite |
| Academic progression and grading rules | Effective-dated configuration | Academic | program/version scoped |
| Payroll rules | Effective-dated configuration + contract terms | HR/Payroll | contract precedence |
| Scholarship/funding rules | Configuration/agreement | Funding/Finance | agreement authority |
| Retention periods | Configuration/policy | Governance/Privacy | category and effective date |
| Branch operating policies | Effective-dated configuration | Organization/Branch | approved scope |
| Communication templates | Configuration | Communication | versioned and approved |
| Account, amount, date, percentage, code | Value object | owning domain | validated, no business truth alone |
| Status change, payment posting, approval, disclosure | Business event/fact | owning domain | immutable evidence |
| Balance, KPI, utilization, summary | Derived data | Reporting from source owner | no manual authority |
| Audit record | Audit record | Audit | append-only |

Configuration is authorized, validated, scoped, effective-dated, versioned where historical interpretation requires it, and never used to duplicate domain state.

---

## HISTORICAL SOURCE: `foundation/41-gate-3-remediation-review.md`

# Gate 3 Remediation and Independent Re-Review

**Date:** 2026-08-25
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`
**Independent posture:** Review repeated after remediation as a fresh audit.

## Repairs completed

1. Added canonical entity registry covering all critical domains.
2. Added source-of-truth registry with allowed/forbidden writers and historical rules.
3. Added relationship registry with cardinality, ownership, effective dates, transfer, deactivation, and deletion behavior.
4. Added lifecycle transition registry with valid transitions and prohibited behavior.
5. Added authority/scope operation registry.
6. Added explicit financial entity model and financial invariants.
7. Separated academic evidence, calculated results, official decisions, exceptions, and appeals.
8. Separated HR contracts, compensation, work basis, payroll calculation, payroll result, payment, and settlement.
9. Added consent, disclosure, verification, classification, and retention entities.
10. Added derived-data lineage.
11. Added domain contracts and forbidden direct mutations.
12. Added configuration-versus-domain classification.
13. Updated Gate 3 findings, state, index, requirements, rules, risks, and traceability.

## Independent re-review

| Audit | Result | Evidence |
|---|---|---|
| Entity completeness | PASS | `29-canonical-entity-registry.md` |
| Source of truth | PASS | `30-source-of-truth-registry.md` |
| Relationships/cardinality | PASS | `31-relationship-registry.md` |
| Effective dates | PASS | relationship and lifecycle registries |
| Lifecycle transitions | PASS with detailed catalogs pending | `32-lifecycle-transition-registry.md` |
| Authority/scope | PASS with policy values pending | `33-authority-scope-operation-registry.md` |
| Financial integrity | PASS at model level | `34-financial-domain-model.md` |
| Academic evidence/decisions | PASS | `35-academic-evidence-decision-model.md` |
| HR/payroll | PASS at model level | `36-hr-payroll-domain-model.md` |
| Privacy/consent | PASS at model level | `37-privacy-consent-disclosure-model.md` |
| Historical integrity | PASS | effective-dated relationships and immutable facts |
| Derived data | PASS | `38-derived-data-lineage-registry.md` |
| Domain boundaries | PASS | `39-domain-contracts.md` |
| Configuration | PASS | `40-configuration-domain-classification.md` |
| Legacy contamination | PASS | purity report and active-tree verification |
| Contradiction audit | PASS | superseded decisions retained as superseded |
| Financial invariant attack | PASS at model level | direct balance mutation, over-refund, duplicate allocation, unlinked journal, restricted-fund misuse are explicitly prohibited |

## Findings by severity after re-review

- CRITICAL: 0
- HIGH: 0
- MEDIUM: 4 — detailed state acceptance catalogs, report lineage expansion, scope row expansion, and domain contract examples remain planned.
- LOW: 0

## Remaining non-blocking unknowns

- Future campus/branch names and activation dates.
- Legal succession documents before ownership implementation.
- Agreement-specific scholarship/funding terms before configuration.
- Contract-specific compensation variations before use.
- Exact configurable financial thresholds before affected actions are enabled.
- Detailed acceptance examples and report definitions before later gates.

These are explicitly scoped and do not require inventing business policy at Gate 3.

## Verification conclusion

The repaired model now has one canonical representation for critical business facts, explicit relationships and lifecycles, authority and scope boundaries, financial source facts, academic evidence/decision separation, HR/payroll separation, privacy records, derived-data lineage, domain contracts, and configuration classification.

**GATE 3: PASS WITH NON-BLOCKING OPEN ITEMS**

**Gate 4:** Authorized for formal review only after this result is accepted. No implementation is authorized by this report.

---

## HISTORICAL SOURCE: `foundation/42-architecture-boundary-model.md`

# Architecture Boundary Model

**Purpose:** business architecture boundary only; no technology, schema, API, UI, or implementation decision is made here.

| Boundary | Owns | May consume | Must not own |
|---|---|---|---|
| Organization/Governance | structure, ownership, policy decisions, approvals | identity and audit evidence | operational facts owned by domains |
| Identity | person identity, account linkage, authentication identity | verified person evidence | permissions or business status |
| Access/RBAC/Scope | position, assignment, permission, policy, scope, delegation | organization structure and approvals | identity, financial, academic, or HR facts |
| Admissions/Students/Guardians | applications, admission, student and verified relationship facts | identity, academic and financial outcomes | grades, balances, access grants |
| Academic/Placement/Classes/Attendance/Assessment | academic evidence and official decisions | student, staffing, program configuration | financial truth or payroll truth |
| HR/Teachers/Payroll | employment, contracts, work basis, payroll calculation/results | assignments, attendance/work evidence, finance posting | academic decisions or payment truth |
| Finance/Receivables/Payments/Refunds/Discounts/Funding | posted financial transactions, obligations, settlement, funds, reconciliation | approved business facts | mutable dashboard balances |
| Books/Inventory/Assets/Facilities | custody, stock, asset, and operational work facts | people, locations, approvals, finance outcomes | financial journal truth |
| Communication/Documents/Privacy | documents, consent, disclosure, delivery, verification, retention | authoritative domain facts and authorization | recipient authority or source business facts |
| Audit | append-only accountability evidence | all material domain events and approvals | business-state ownership |
| Reporting | metric definitions, derived calculations, report runs | canonical facts and period/scope definitions | any authoritative fact |
| Infrastructure | availability, storage, transport, recovery controls | none as business authority | business policy and domain meaning |

Application/service orchestration may coordinate commands and transactions, but cannot become an additional source of truth. Authorization evaluates every material operation before the owning domain accepts it. Configuration is versioned input; it never rewrites facts.

## Architecture readiness conclusion

The boundary is translatable without importing legacy semantics. Unresolved numeric thresholds and agreement-specific rules remain explicit configuration/policy inputs and fail closed where absent.

---

## HISTORICAL SOURCE: `foundation/43-domain-boundary-contract-registry.md`

# Domain Boundary Contract Registry

| Source → receiver | Fact exchanged | Owner | Direction and mode | Failure/audit |
|---|---|---|---|---|
| Organization → Access | active structure and assignments | Organization/Access respectively | access decision reads effective structure | deny if unavailable; audit |
| Identity → Admissions/HR | verified person identity | Identity | command input/read | reject unverified identity; audit |
| Admissions → Students/Academic/Finance | approved admission and liable-party facts | Admissions/Students | decision then notification | no downstream activation without approval |
| Students → Academic/Finance | student/enrollment facts | Students/Enrollment | read/decision input | reject stale/inactive membership |
| Academic → HR/Payroll | approved work basis/teaching evidence | Academic for teaching fact; HR for employment | controlled input | hold disagreement; preserve evidence |
| Academic → Students | approved results and decisions | Academic | decision output | no inference from raw evidence |
| HR → Payroll/Access | contract, assignment, status | HR | decision input | deny expired employment; audit |
| Finance → Reporting | posted journals and transactions | Finance | read/derived | report unavailable or flagged on incomplete close |
| Finance ↔ Funding | restricted fund and allocation facts | Finance/Funding by fact | controlled command/read | hold unauthorized reclassification |
| Finance → Students | obligation/payment status | Finance | derived read | no student-side mutation |
| Assets/Inventory → Finance | approved asset/custody events with financial effect | Assets/Inventory source event; Finance posting | command/event | reject unapproved posting; audit |
| Privacy → Communication | consent and disclosure authorization | Privacy | synchronous authorization/read | deny absent purpose/authority |
| All domains → Audit | material change and approval evidence | Audit | append-only notification/record | operation cannot claim complete without audit evidence |
| All domains → Reporting | canonical facts and metric metadata | each source domain | read/derived | no alternate metric authority |

A contract names owner, allowed input, authority context, scope, effective date, outcome, and failure behavior. Notifications cannot transfer ownership. Circular authority is prohibited: a receiver may validate prerequisites but cannot rewrite the source fact.

---

## HISTORICAL SOURCE: `foundation/44-authoritative-data-flow-model.md`

# Authoritative Data Flow Model

## Flow rule

A command enters through an authorized operation, is validated against configuration and owned invariants, is accepted by exactly one owning domain, and produces an immutable fact/state transition plus audit evidence. Other domains receive a read, decision input, or notification; they do not copy authority.

| Fact family | Authoritative owner | Consumers | Derived outputs |
|---|---|---|---|
| Person/student/guardian identity | Identity/Students | Admissions, Academic, HR, Finance | directories |
| Enrollment and membership | Students/Academic delivery | Academic, Finance, Reporting | active counts |
| Evidence/results/decisions | Academic | Students, Reporting | progression metrics |
| Employment/contracts/work basis | HR/Academic by fact | Payroll, Access | staffing metrics |
| Payroll calculation/result/payment | Payroll/Finance by fact | HR, Reporting | payroll totals |
| Obligations/charges | Finance | Students, Reporting | receivables |
| Payment/allocation/refund/adjustment/reversal | Finance | Students, Reporting | balance |
| Cash/journal/reconciliation | Finance | Reporting, Audit | cash position |
| Authority/permission/scope | Access | all operation gates | effective access |
| Consent/disclosure/document verification | Privacy/Documents | Communication, domains | compliance reports |

A historical fact carries actor, time, reason, authority, scope, effective date, and source. Corrections append; they never mutate the prior fact. Financial balances and report metrics are projections from canonical facts, not stored authorities.

---

## HISTORICAL SOURCE: `foundation/45-authorization-and-scope-architecture-contract.md`

# Authorization and Scope Architecture Contract

Authorization input is **Position + Assignment + Permission + Scope + Policy**, evaluated with subject, object, operation, effective dates, and conflict rules. Default is deny; position title alone grants nothing.

Required controls: permission catalog owned by Access Governance; organization→campus→branch→department scope hierarchy; explicit cross-branch assignments; dated delegations; expiry enforcement; two-Owner approval for applicable Owner-sensitive actions; separation of requester/reviewer/approver/beneficiary; self-approval prohibition; emergency suspension with time limit, review, and audit.

A branch transfer changes current visibility from its effective date only. Historical records retain their original scope and attribution. Missing policy, expired assignment, scope mismatch, conflict of interest, or unavailable required approval denies or holds the operation. Exact thresholds remain policy/configuration values and are not invented here.

---

## HISTORICAL SOURCE: `foundation/46-financial-architecture-contract.md`

# Financial Architecture Contract

Finance alone owns posted financial truth. The following are distinct authoritative records: charge/obligation, obligation line, payment, payment allocation, refund, discount, scholarship award/allocation, compensating correction, reversal, cash movement, journal, financial period, reconciliation, Finance-recognized Payroll liability, and Finance-recorded employment settlement. Payroll owns calculation and adjustment evidence, and settlement proposal workflow; it does not own a monetary liability or settlement fact. Funding owns agreement restrictions; Finance owns posting and reconciliation.

Balances, receivables, utilization, cash position, and financial reports are derived from posted transactions. There is no authoritative mutable balance. Every allocation references one payment and obligation; an allocation cannot exceed either. A payment cannot be allocated twice. A refund references its source and cannot exceed refundable value. Discounts preserve original charges. Adjustments and reversals append linked history. Journals balance and retain source links. Closed periods reject mutation; restricted funds cannot be reclassified without authorized evidence. Reconciliation owns comparison and variance evidence, not an alternate cash truth.

All financial commands require authority, scope, period, reason, source, approval where required, and audit. UI, student, payroll view, reporting, or application orchestration cannot post financial truth directly.

---

## HISTORICAL SOURCE: `foundation/47-lifecycle-architecture-contract.md`

# Lifecycle Architecture Contract

Every controlled entity uses the transition registry as its state machine. A transition requires current state, permitted next state, preconditions, authorized actor/scope, effective date, reason, and audit. Rejection leaves the prior state unchanged and records the denied attempt where material.

| Family | Architectural requirement |
|---|---|
| Student, enrollment, employment | status transition owned by Students or HR; no downstream status inference |
| Class, session, attendance, assessment | ordered publication/delivery/evidence/scoring/review states; cancellation and correction preserve history |
| Progression, graduation, appeal | official decision is separate from evidence; appeal supersedes outcome without erasing original |
| Payroll, financial period | ordered calculate/review/approve/pay/close states; correction/reversal is linked append-only history |
| Payment, refund, discount, adjustment | source reference and approval preconditions; no balance-only transition |
| Consent, disclosure, document | effective/revoked/expired states; revocation affects future use, not evidence |
| Asset, work order, incident | custody/work/outcome evidence and approval before terminal disposition |

Effective-dated configuration and relationships are evaluated as-of the business period. Transfer, deactivation, closure, cancellation, and reopening create explicit transitions; they do not delete or rewrite historical facts. Unknown transitions fail closed and are held for the owning authority.

---

## HISTORICAL SOURCE: `foundation/48-reporting-and-derived-data-contract.md`

# Reporting and Derived Data Contract

Reporting owns metric definitions, calculation specifications, scope filters, period semantics, and report runs—not source facts. Every metric declares source owner, source entities, calculation, period authority, scope, historical/as-of behavior, refresh/completeness status, and reconciliation requirement.

| Metric family | Source | Period and control |
|---|---|---|
| Balance/receivables/revenue/cash | Finance posted transactions, journals, reconciliations | Financial Period; reconcile before certification |
| Payroll totals | approved Payroll results and Finance payment facts | Payroll/Financial Period |
| Enrollment/attendance/progression | Students and Academic facts/decisions | Academic period; preserve as-of history |
| Funding utilization | approved Funding allocations and Finance postings | agreement and Financial Period |
| Inventory/assets | custody, movement, work, and approved financial facts | effective movement date |

Dashboards cannot write source records or define a competing balance. They cannot silently mix periods, scopes, current ownership with historical attribution, or incomplete data. Divergence is detected by reconciliation and reported as an exception to the source owner. Metric configuration versions remain available so historical reports retain their original definition.

---

## HISTORICAL SOURCE: `foundation/49-privacy-audit-resilience-architecture-contract.md`

# Privacy, Audit, and Resilience Architecture Contract

## Privacy

Personal and sensitive information has classification, owner, purpose, minimum necessary scope, retention rule, and access audit. Consent is an explicit, purpose-specific, effective-dated record; communication and marketing consent are separate. Disclosure records recipient, purpose, authority, scope, time, and disclosed category. Verification records verifier, outcome, evidence, and reason. Guardian access requires verified relationship and applicable permission. Revocation prevents future use without erasing historical consent/disclosure evidence.

## Audit and history

Material operations preserve who, what, when, why, previous value, new value, authority, approval, scope, effective date, source, and outcome. Audit is append-only and does not own business state. Historical relationships, transactions, decisions, configuration versions, and branch attribution remain immutable; corrections and reversals append linked records.

## Resilience boundary

Architecture must preserve committed financial transactions and append-only audit evidence across backup and recovery. Recovery requirements must specify, before operational design, acceptable data loss (RPO), acceptable downtime (RTO), backup coverage, restoration verification, period/reconciliation integrity, and recovery audit evidence. These are requirements, not infrastructure-product choices. Until organization-specific RPO/RTO values are supplied, design must use conservative documented targets and mark values configurable; no financial transaction may be discarded to meet availability goals.

---

## HISTORICAL SOURCE: `foundation/50-gate-4-architecture-readiness-review.md`

# Gate 4 — Architecture Readiness and System Boundary Review

**Date:** 2026-08-25
**Review type:** formal documentation-only adversarial review
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`

## Audit disposition

| Audit | Result |
|---|---|
| Architecture boundary | PASS |
| Domain boundaries and contracts | PASS |
| Source-of-truth attack | PASS; one owner identified for every critical fact |
| Forbidden writers | PASS |
| Financial architecture | PASS; no critical ambiguity |
| Authorization and scope | PASS |
| Lifecycle determinism | PASS |
| Cross-domain contracts | PASS |
| Configuration boundary | PASS |
| Reporting/derived data | PASS |
| Audit/historical integrity | PASS |
| Privacy/consent | PASS |
| Resilience boundary | PASS at requirements boundary |
| Legacy contamination | PASS; Foundation prevails over repository implementation |

## Adversarial attacks

| Attack | Expected prevention | Owner/evidence | Architecture result |
|---|---|---|---|
| unauthorized approval or self-approval | operation authority and SoD | Access + approval/audit | prevented/denied |
| Owner conflict or emergency abuse | two-Owner rule, time limit, review | Governance/Access audit | prevented/contained |
| cross-branch leakage or expired delegation | effective scope and expiry | Access scope history | denied |
| double allocation or over-refund | source links and amount invariants | Finance transactions/reconciliation | prevented |
| phantom/magic balance | derived balance only | Finance posted facts | prevented |
| branch transfer corruption | effective dating, immutable attribution | Organization history | prevented |
| payroll/Finance disagreement | separated contract, calculation, payment | HR/Payroll/Finance records | held and auditable |
| academic result/evidence conflict | decision separate from evidence | Academic decision/appeal | deterministic |
| duplicate identity | Identity-owned verification and linkage | Identity audit | rejected/exceptioned |
| revoked consent or disclosure | purpose/consent check and disclosure record | Privacy audit | denied/auditable |
| dashboard/report divergence | registered metric and period definition | Reporting reconciliation | detected/flagged |
| configuration rewriting facts | versioned effective configuration | owning domain history | prevented |

## Architecture readiness answers

1. Every critical fact has one authoritative domain: **Yes**.
2. Every critical mutation has an authorized operation: **Yes**.
3. Cross-domain dependencies avoid circular authority: **Yes**.
4. Financial truth avoids duplicated balances: **Yes**.
5. RBAC and scope avoid hard-coded organizational assumptions: **Yes**.
6. Lifecycle behavior is deterministic from the transition registry: **Yes**.
7. Historical truth remains immutable: **Yes**.
8. Reporting consumes rather than owns facts: **Yes**.
9. Configuration is separate from historical facts: **Yes**.
10. Architecture can proceed without inventing unresolved policy: **Yes**, subject to the open items below.

## Findings

- Critical: 0
- High: 0
- Medium: 3 non-blocking — organization-specific recovery targets, detailed metric catalog expansion, and detailed operation acceptance examples.
- Low: 0

These do not affect authority, financial truth, ownership, lifecycle semantics, privacy boundaries, or source-of-truth integrity. They must be resolved before the relevant operational architecture is finalized.

## Exact remaining unknowns

- Organization-specific RPO/RTO and backup retention targets.
- Full report/metric catalog and its approved consumers.
- Detailed acceptance examples for every operation and transition.
- Future agreement-specific funding terms and policy threshold values already recorded in Foundation risks.

## Exact implementation boundary

No application code, database, schema, migrations, API, UI, framework scaffolding, production configuration, seeds, implementation tests, framework selection, or production setup was performed or authorized by this review.

**GATE 4: PASS WITH NON-BLOCKING OPEN ITEMS.**

The next authorized activity is formal architecture design under the Foundation boundaries. Gate 5 is not automatically started.
