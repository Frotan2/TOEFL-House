# TOEFL House — Domain & Capability Registry

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reassessed:** 2026-09-09  
**Purpose:** Single source of truth for domain ownership, current maturity, backend↔frontend parity, closure gaps and requirement evidence.

> This file replaces duplicated current-state, parity, reassessment and requirement-traceability documents. Historical audits remain historical evidence only.

## 1. Authority and maturity model

The backend is authoritative for business truth, lifecycle legality, persistence, authorization, scope, accounting, audit/provenance, idempotency and concurrency. React is the operational presentation/interaction boundary and must use the canonical API transport.

| Status | Operational meaning |
|---|---|
| `COVERED / STRONG` | Material scope is implemented with authoritative ownership established; preserve and verify regressions. |
| `IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED` | Source/parity inspection is converged; current runtime evidence is still required. |
| `PARTIAL` | Material implementation exists, but one or more closure dimensions remain. |
| `MISSING` | Required user-facing capability lacks an adequate implementation. |
| `BACKEND-ONLY / OPERATOR` | Infrastructure/control-plane capability intentionally has no normal employee-facing parity requirement. |
| `DEFERRED` | Explicitly approved future scope, not a current defect. |

## 2. Current domain registry

| Domain | Authority | Frontend surface | Current status | Required closure / next evidence |
|---|---|---|---|---|
| Organization / Scope | Organization | Organization workspace | **COVERED / STRONG** | Preserve topology, scope and adversarial tests. |
| Identity | Identity | Identity workspace | **COVERED / STRONG** | Preserve identity/account lifecycle and provenance. |
| Access / RBAC | Access | Access workspace | **PARTIAL** | Complete grants, revoke, delegation, approval and denial-path parity. |
| Applicants & Students | Students / Admissions | Students workspace | **PARTIAL** | Re-evaluate every lifecycle transition, scope, financial gate, evidence and E2E path. |
| CRM / Front Office | CRM | CRM/front-office workspaces | **PARTIAL** | Close follow-up, automation, evidence and conversion workflow gaps. |
| Academic | Academic | Academic workspace | **PARTIAL** | Close corrections, appeals, progression, graduation, transcript, waitlist and terminal-state actions. |
| Placement | Placement / Academic boundary | Placement workspace | **PARTIAL** | Close moderation/release/appeals/content and report decision surfaces. |
| Teachers | HR / Academic boundary | Teachers workspace | **PARTIAL** | Close qualification, availability, assignment, transfer and workload actions. |
| HR | HR | HR workspace | **PARTIAL** | Close employment, contracts and leave lifecycle actions. |
| Finance | Finance | Finance workspace | **PARTIAL** | Close approvals, reversals, corrections, cash, scholarships, settlement and GL controls. |
| Payroll / Settlement | Payroll calculation; Finance settlement truth | Payroll workspace | **PARTIAL** | Close calculate/approve/held-resolution/clearance/settlement parity. |
| Library & Resources | Resources | Library workspace | **IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED** | **ACTIVE:** current-main runtime, browser, security/scope, concurrency and evidence gates. |
| Documents | Documents | Legacy / partial | **PARTIAL / HIGH PRIORITY NEXT** | Converge identity/version/classification/verification/retention workflows in React after Library closure. |
| Privacy | Privacy | Legacy / partial | **PARTIAL** | Modernize operational controls while retaining server policy authority. |
| Audit | Audit | Legacy / partial | **PARTIAL** | Provide scoped evidence search/read surfaces without moving authority client-side. |
| Communication | Communication | Communication + workspace surfaces | **PARTIAL** | Close message/thread/search/status and notification/work integration. |
| Reporting | Reporting | Reporting workspace | **PARTIAL** | Close report-run lifecycle, evidence/status actions and remaining replay/snapshot depth. |
| Management / Work | Work Management | Workspace / management | **PARTIAL** | Close queue membership and complete work lifecycle surfaces. |
| Integrations / queues / projections / DB machinery | Outbox / infrastructure / DB | Operator-only as required | **BACKEND-ONLY / OPERATOR** | Do not duplicate business authority in normal UI. Add observability only when approved. |

## 3. Active domain — Library & Resources

The selected domain has converged in source inspection. Confirmed implementation includes:

- books and copies;
- circulation issue/return/loss;
- assets and custody;
- facilities work lifecycle;
- staged disposal request → approval → execution, with requester-only withdrawal while the request is unapproved (withdrawal is terminal and frees the asset for a corrected request);
- explicit consequential actor selection;
- evidence capture;
- irreversible-action confirmation;
- server-owned authorization/scope/lifecycle/audit controls;
- idempotency and concurrency protections.

Release closure remains blocked until current-main Verification proves the applicable gates. Do not begin Documents before that closure.

## 4. Domain closure trace

For each material workflow, record the smallest applicable trace:

`Requirement → Authority → Lifecycle → Data Model → DB Invariant → Command/Service → Authorization → Scope → API → React → UX/Error States → Audit/Provenance → Idempotency → Concurrency → Tests → Browser/E2E → Operational Evidence`

A page or endpoint list is not parity.

## 5. Security/scope acceptance matrix

Every domain with scoped mutations must prove, where applicable:

- organization isolation;
- campus scope;
- branch scope;
- class scope;
- own-record scope;
- manager cross-branch denial;
- valid campus-scoped manager access;
- owner/global access where explicitly authorized;
- teacher/employee own-record and cross-record behavior;
- unauthorized mutation denial;
- invalid lifecycle denial;
- separation-of-duties and self-approval controls;
- idempotent retry/conflict behavior;
- concurrent mutation behavior;
- auditable rejection as well as success for sensitive actions.

## 6. Requirement traceability

Material requirements are tracked as:

`Requirement → Canonical Authority → Implementation Evidence → Test Evidence → Current Runtime Status`

Runtime status is **only** updated from actual current-main evidence. Historical certification does not carry forward automatically.

### Standing requirements

| ID | Requirement | Canonical authority | Evidence anchor | Current status |
|---|---|---|---|---|
| R-01 | One authority per material business fact | Master contract + authority map | domain modules / DB / architecture | **ENFORCED** |
| R-02 | Server-side scoped authorization | Access | Access module + scope tests | **ENFORCED; domain parity still partial in places** |
| R-03 | Finance owns monetary truth | Finance | Finance domain + ledger invariants | **ENFORCED; workflow breadth partial** |
| R-04 | Payroll proposes; Finance records settlement | Payroll + Finance | settlement authority / payroll module | **ENFORCED; workflow breadth partial** |
| R-05 | Academic lifecycle has guarded transitions | Academic | Academic/Enrollment | **ENFORCED; workflow breadth partial** |
| R-06 | Critical invariants are durable at DB boundary where practical | PostgreSQL + domain authority | migrations + invariant verification | **ENFORCED** |
| R-07 | Temporal assignment overlaps are prevented | DB/domain authority | temporal constraints + concurrency tests | **ENFORCED** |
| R-08 | Transactional outbox and idempotent consumers remain non-authoritative projections | Outbox/Integrations | Outbox/Integrations modules | **ENFORCED** |
| R-09 | Workspace composes and never owns domain truth | Work Management / domain authorities | workspace modules + API | **ENFORCED** |
| R-10 | React is the target operator frontend | Frontend/API contract | `resources/js/*` + API v1 | **IMPLEMENTED; domain parity partial** |
| R-11 | Deployment/recovery are schema-aware | Operations | deploy scripts + runtime checks | **DOCUMENTED; current runtime evidence governs release** |
| R-12 | Release certification requires current authoritative runtime evidence | Runtime/Release | Verification workflow | **ENFORCED** |

## 7. Backend-only boundaries

Do not recreate these as frontend business logic:

- migrations, schema constraints and transactional persistence;
- domain commands/services and lifecycle invariants;
- authorization and scope calculation;
- idempotency/replay protection;
- audit/event/outbox creation;
- accounting and ledger computation;
- concurrency/temporal guards;
- projections/materializations;
- queue/retry/scheduler execution;
- integration transport and secrets.

## 8. Promotion rule

A domain can move from `PARTIAL` to `COVERED / STRONG` only after current evidence proves the applicable closure formula in `docs/OPERATING-CONTROL.md`. A domain can move to `RELEASE CERTIFIED` only through `RUNTIME-RELEASE.md`.

The next-domain queue is not a second source of truth; it is derived from this registry and the operating control state.
