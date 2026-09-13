# TOEFL House — Domain & Capability Registry

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `main`  
**Last reassessed:** 2026-09-13  
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
| Library & Resources | Resources | Library workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34713414829 (head `e603db9`, branch `arena/01a09629-toefl-house`) — backend suite/invariants/concurrency, frontend, static and real-Chromium jobs all green; Library browser journey 24/24 across three sessions (lifecycle, withdrawals, provoked denials, staged approvals, canonical-route and session-hygiene records). Certification still requires merge and a Verification run on the actual `main` HEAD. |
| Documents | Documents | Documents workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34732090672 (tree `5f32839`) — all four jobs green; Documents browser journey 26/26 across three isolated Chromium sessions (full lifecycle, separation-of-duties denial, terminal state, immutable history, no storage reference in any read model, 14 canonical mutations / 0 violations). Evidence: `docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`. Certification still requires merge and a Verification run on the actual `main` HEAD. |
| Privacy | Privacy | Privacy workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34741595867 (tree `05aecf6`, branch `arena/01a098fa-toefl-house`) — all four jobs green; Privacy browser journey 38/38 across four isolated Chromium sessions (purpose catalog, full consent lifecycle, provoked denials, withdrawal evidence, disclosure evidence, subject dossier, direct release, staged organization-wide export with two distinct approvers, subject-side boundary, canonical-POST-only mutations / 0 violations). Erasure is lifecycle-only: no delete path exists in model, transport or UI. Evidence: `docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md`. Certification still requires merge and a Verification run on the actual `main` HEAD. |
| Audit | Audit | Legacy / partial | **PARTIAL** | Provide scoped evidence search/read surfaces without moving authority client-side. |
| Communication | Communication | Communication + workspace surfaces | **PARTIAL** | Close message/thread/search/status and notification/work integration. |
| Reporting | Reporting | Reporting workspace | **PARTIAL** | Close report-run lifecycle, evidence/status actions and remaining replay/snapshot depth. |
| Management / Work | Work Management | Workspace / management | **PARTIAL** | Close queue membership and complete work lifecycle surfaces. |
| Integrations / queues / projections / DB machinery | Outbox / infrastructure / DB | Operator-only as required | **BACKEND-ONLY / OPERATOR** | Do not duplicate business authority in normal UI. Add observability only when approved. |

## 3. Active domain — Privacy & Consent (closed)

One material domain at a time. Library & Resources and Documents & Evidence are
closed and were not reopened; Privacy & Consent was the active domain and has
converged. Confirmed implementation includes:

- purposes of personal-data use as an organization-rooted catalog, with
  communication and marketing as separate definitions and a duplicate
  definition refused as a business rejection rather than a database error;
- consent capture as a born-draft, write-once fact with its evidence reference;
- one open consent per (subject, purpose), enforced by a partial unique index
  and attacked under savepoints in the canonical suite;
- the single consent lifecycle table (`ConsentLifecycle`) consulted by the
  command, the transport and the projection alike, so no surface re-derives it:
  draft → submitted → verified → active → expired/revoked → archived;
- expiry as the passage of the recorded window, decided by the CalendarAuthority
  and never by a client clock;
- withdrawal as append-only revocation evidence attached to the consent it ended;
- disclosure as immutable release evidence with recipient, purpose, authority
  and declared scope, the scope re-resolved server-side so a forged one is
  refused;
- subject access: one dossier projection (identity, complete consent history
  with its withdrawals, every disclosure, every export request) that the subject
  may read about themselves without holding any staff capability;
- subject-data release in two shapes — a direct single-subject export inside a
  non-organization scope, and a staged organization-wide export that needs two
  distinct approver signatures, excludes its requester, and executes only at
  organization scope (`ExportApprovalChain`);
- erasure as lifecycle only: `Consent::delete()` and `forceDelete()` throw
  `privacy.consent_immutable`, the database guards refuse rewriting export
  history, and no delete/erase/purge control exists in the UI;
- server-owned authorization/scope (`PrivacyScopePolicy`), fail-closed denials
  that are audit-recorded with their code and capability reason, idempotency on
  every mutation, and concurrency races on the consent tables;
- one canonical operator surface: `/privacy` renders the React workspace over
  `/api/v1/privacy`; the Blade privacy index and the `/governance/privacy` alias
  are retired, and the legacy web POST endpoints remain thin adapters.

Release closure remains blocked until current-main Verification proves the
applicable gates. Do not begin the next material domain before that closure.

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
