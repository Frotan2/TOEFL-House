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

> **Reading the certification citations.** Each closed domain's row cites the commit and Verification run at which *that domain's* closure was certified. Those are lineage, not current release authority. Current release authority is the single `main` HEAD recorded in `docs/OPERATING-CONTROL.md` §1 — as of this record, `eb67300` — and it lapses with the next commit to `main`. A domain staying `VERIFIED` does not mean an older commit is still the certified one.

| Domain | Authority | Frontend surface | Current status | Required closure / next evidence |
|---|---|---|---|---|
| Organization / Scope | Organization | Organization workspace | **COVERED / STRONG** | Preserve topology, scope and adversarial tests. |
| Identity | Identity | Identity workspace | **COVERED / STRONG** | Preserve identity/account lifecycle and provenance. |
| Access / RBAC | Access | Access workspace | **PARTIAL** | Complete grants, revoke, delegation, approval and denial-path parity. |
| Applicants & Students | Students / Admissions | Students workspace | **PARTIAL** | Re-evaluate every lifecycle transition, scope, financial gate, evidence and E2E path. |
| CRM / Front Office | CRM | CRM + Reception Desk (`/crm`, `/crm?view=front-office`) | **PARTIAL — ACTIVE DOMAIN (baseline confirmed; implementation not yet written)** | The single active material domain, selected by the 2026-09-13 reassessment of this section. The mandatory baseline gate in `docs/OPERATING-CONTROL.md` §4 was executed green at `main` HEAD `eb67300` (Verification run 34750406256, all four jobs; CRM Browser E2E run 34750406270), so closure work is unblocked — but a confirmed baseline is a precondition for starting, not evidence that any gap is closed, and no CRM code has been written yet. Closure must cover the measured inventory in §3.3 (G1–G8): React parity for the 8 of 21 CRM API routes that have no operator surface (source define/retire, campaign define/retire, visitor `PATCH` update, automation-rule read/define/retire); automation-rule lifecycle and evaluation evidence; conversion and conversion-handoff workflow, including `VisitorConversionHandoff` which has no test reference and no surface; Reception Desk depth, presently a read-only projection making no CRM mutation; and browser-journey depth beyond the current 10 checks. |
| Academic | Academic | Academic workspace | **PARTIAL** | Close corrections, appeals, progression, graduation, transcript, waitlist and terminal-state actions. |
| Placement | Placement / Academic boundary | Placement workspace | **PARTIAL** | Close moderation/release/appeals/content and report decision surfaces. |
| Teachers | HR / Academic boundary | Teachers workspace | **PARTIAL** | Close qualification, availability, assignment, transfer and workload actions. |
| HR | HR | HR workspace | **PARTIAL** | Close employment, contracts and leave lifecycle actions. |
| Finance | Finance | Finance workspace | **PARTIAL** | Close approvals, reversals, corrections, cash, scholarships, settlement and GL controls. |
| Payroll / Settlement | Payroll calculation; Finance settlement truth | Payroll workspace | **PARTIAL** | Close calculate/approve/held-resolution/clearance/settlement parity. |
| Library & Resources | Resources | Library workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34713414829 (head `e603db9`, branch `arena/01a09629-toefl-house`) — backend suite/invariants/concurrency, frontend, static and real-Chromium jobs all green; Library browser journey 24/24 across three sessions (lifecycle, withdrawals, provoked denials, staged approvals, canonical-route and session-hygiene records). **Certified on `main`**: merged as PR #26; Verification run 34735425882 at main HEAD `0e46611` green on all four jobs with the Library journey 24/24, CRM Browser E2E run 34735426000 alongside. |
| Documents | Documents | Documents workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34732090672 (tree `5f32839`) — all four jobs green; Documents browser journey 26/26 across three isolated Chromium sessions (full lifecycle, separation-of-duties denial, terminal state, immutable history, no storage reference in any read model, 14 canonical mutations / 0 violations). Evidence: `docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md`. **Certified on `main`**: merged as PR #26; Verification run 34735425882 at main HEAD `0e46611` green on all four jobs with the Documents journey 26/26, CRM Browser E2E run 34735426000 alongside. |
| Privacy | Privacy | Privacy workspace | **VERIFIED** | Closure gates executed and observed: Verification run 34742746987 (tree `55fc461`, branch `arena/01a098fa-toefl-house`) — all four jobs green; Privacy browser journey 38/38 across four isolated Chromium sessions (purpose catalog, full consent lifecycle, provoked denials, withdrawal evidence, disclosure evidence, subject dossier, direct release, staged organization-wide export with two distinct approvers, subject-side boundary, canonical-POST-only mutations / 0 violations). Erasure is lifecycle-only: no delete path exists in model, transport or UI. Evidence: `docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md`. **Certified on `main`**: merged as PR #29 (merge commit `27b96d8`); Verification run 34744897784 at that HEAD green on all four jobs with the Privacy journey 38/38, CRM Browser E2E run 34744897779 alongside. Superseded as current release authority by documentation-only PR #30 (merge commit `b824efd`, verified green there by Verification run 34745363765 with CRM Browser E2E run 34745363720) and then by documentation-only PR #31 (merge commit `eb67300`, verified green there by Verification run 34750406256 with CRM Browser E2E run 34750406270). Neither merge changed any source, test, migration, workflow or route file. Not reopened by the CRM reassessment. |
| Audit | Audit | Legacy / partial | **PARTIAL** | Provide scoped evidence search/read surfaces without moving authority client-side. |
| Communication | Communication | Communication + workspace surfaces | **PARTIAL** | Close message/thread/search/status and notification/work integration. |
| Reporting | Reporting | Reporting workspace | **PARTIAL** | Close report-run lifecycle, evidence/status actions and remaining replay/snapshot depth. |
| Management / Work | Work Management | Workspace / management | **PARTIAL** | Close queue membership and complete work lifecycle surfaces. |
| Integrations / queues / projections / DB machinery | Outbox / infrastructure / DB | Operator-only as required | **BACKEND-ONLY / OPERATOR** | Do not duplicate business authority in normal UI. Add observability only when approved. |

## 3. Active domain — CRM / Front Office (baseline confirmed, implementation not yet written)

One material domain at a time. Library & Resources, Documents & Evidence and
Privacy & Consent are closed, certified and were **not reopened** by this
reassessment. CRM / Front Office is the single active material domain: it was
selected by this reassessment, its mandatory baseline gate has since been
executed green at `main` HEAD `eb67300`, and **no implementation has been
written yet**.

### 3.1 Selection basis

Reassessed against `main` HEAD `b824efd`, and re-confirmed at `eb67300`: the two
intervening merges (PR #30, PR #31) were documentation-only and changed no CRM
source, test, route, migration or workflow file, so the measurement below is
still the measurement of the current tree. CRM / Front Office is selected because
it is the `PARTIAL` domain whose backend authority is already substantially
present while its operator-facing proof is the thinnest of any domain adjacent to
closure. Closing it therefore converts *existing* authority into proven
capability rather than inventing new authority — the smallest coherent next step
available under the one-domain rule, and the one least likely to disturb the
three certified closures.

Measured at that HEAD, the backend is not the gap:

- `app/Modules/Crm` ships 9 commands, 7 domain authorities (`CrmAccess`,
  `VisitorStatus`, `VisitorInteractionCatalog`, `VisitorInteractionLineage`,
  `CrmInteractionTraceRecorder`, `VisitorContactKey`, `VisitorConversionRecorder`),
  8 models and 2 queries;
- 5 CRM test files carry 24 distinct test methods covering branch-provenance
  scoping and its immutability; fail-closed behaviour on unknown legacy
  provenance; contact de-duplication and key normalization; the
  one-open-lead-per-verified-person rule; state-machined transitions with a
  required loss reason; immutable interaction append; follow-up
  completion/cancellation serialized against competing status writers;
  automation scheduling a follow-up and re-checking assignee authority at the
  triggering visitor's branch; catalog lifecycle and campaign window; capture
  idempotency hashing; and conversion recorded through admissions, with manual
  conversion explicitly *not* a CRM write authority.

### 3.2 Mandatory precondition — SATISFIED

`docs/OPERATING-CONTROL.md` §4 governs and holds the run-by-run record; it is not
restated here. The gate required Verification and CRM Browser E2E both green at
the *actual current* `main` HEAD, with that baseline recorded against its run
identifiers. Merging PR #31 advanced `main` to `eb67300` and lapsed the
`b824efd` certification, so the gate was re-executed against that merge commit
rather than inherited — `GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`. It passed:
Verification run **34750406256** (all four jobs) and CRM Browser E2E run
**34750406270**, both at `eb673007c42abaacb047ed1d2c341b6b8bf08b73`.

Two consequences follow, and neither may be dropped. First, closure work is now
unblocked. Second, the gate is a **standing** precondition rather than a one-off
start permission: it is re-evaluated at every commit, so each material CRM change
must be verified green at its own HEAD before it can be called certified, and a
domain is promoted only by proven closure — never by a green baseline.

### 3.3 Measured closure gaps

Recorded as measured at `b824efd` and re-confirmed unchanged at `eb67300` — the
intervening merges were documentation-only — not as assumption. Each row is a
closure obligation for the active domain, and each is discharged only by executed
evidence at a current `main` HEAD.

| # | Gap | Measured evidence | Required closure |
|---|---|---|---|
| G1 | **React parity: 8 of 21 CRM API routes have no operator surface.** | `routes/api.php` declares 21 routes under `/api/v1/crm`. `resources/js/crm.tsx` (186 lines) and `resources/js/front-office.tsx` (107 lines) between them reach 13. Unreached: `POST /crm/sources`, `POST /crm/sources/{id}/retire`, `POST /crm/campaigns`, `POST /crm/campaigns/{id}/retire`, `PATCH /crm/visitors/{id}`, `GET /crm/automation-rules`, `POST /crm/automation-rules`, `POST /crm/automation-rules/{id}/retire`. | Every route carrying a normal operator capability gets a React surface with its UX and error states, or is explicitly reclassified as operator/backend-only with the reason recorded here. |
| G2 | **The canonical transport cannot express `PATCH` at all.** | `resources/js/core/api.ts` exposes an `ApiClient` of exactly `getJson` and `postJson`. `crm.tsx` contains zero `patch`/`put` calls. `PATCH /crm/visitors/{id}` (`api.crm.visitors.update`) is therefore unreachable from the canonical client. | Either extend the canonical transport (a frontend-contract decision in its own right) or retire/replace the `PATCH` route with a canonical `POST`. Do not leave a declared mutation route no operator can call. |
| G3 | **Automation rules have no operator surface whatsoever.** | Zero references to `automation-rules` in either React surface, against `DefineVisitorAutomationRule` and three API routes on the backend, and backend tests that already prove automation schedules a follow-up and re-checks assignee authority. | Read, define and retire automation rules from the React workspace, with rule evaluation and its follow-up effect proven end-to-end in a real browser. |
| G4 | **Conversion handoff is unproven and unsurfaced.** | `VisitorConversionHandoff` is referenced only inside `app/` (`VisitorConversionRecorder`, `Visitor`, `VisitorConversion`, `VisitorTimelineQuery`). It has **no test reference** and **no frontend reference**. `routes/api.php` declares **zero** conversion routes, so CRM owns no conversion write endpoint. | Prove the handoff at the CRM↔Admissions boundary with tests, keep CRM explicitly *not* a conversion write authority (already asserted by `test_manual_conversion_is_not_a_crm_write_authority`), and surface conversion state read-only in the timeline. |
| G5 | **Reception Desk is a read-only projection, not a front-office workspace.** | `front-office.tsx` calls only `GET /crm/visitors?limit=200`, `GET /academic/workspace` and `GET /students`. It performs no CRM mutation: no capture, no interaction, no follow-up, no transition. Navigation registers it as a separate destination (`/crm?view=front-office`, "Reception Desk"). | Decide and record whether Reception Desk is an operational surface that must carry capture/interaction/follow-up capability, or an explicitly approved read-only projection. Either is acceptable; leaving it undeclared is not. |
| G6 | **Browser-journey depth is the shallowest in the repository.** | `scripts/runtime/crm-browser-e2e.mjs` statically records 10 checks: authenticated session, explicit branch contract on capture, capture via the canonical API, reopen from the authorized directory, stage transition round-trip, interaction append, follow-up scheduling, follow-up terminal transition, no uncaught console errors, no failed network requests. It asserts **nothing** for automation rules, conversion, campaigns, catalog maintenance, evidence/provenance, denial paths or separation of duties. For scale, the certified closures documented run totals of 24 (Library), 26 (Documents) and 38 (Privacy). | Extend the journey to assert the workflows the domain actually claims — including provoked denials, cross-branch refusal, idempotent replay and terminal-state refusal — and report a real-browser total, not a source-level count. |
| G7 | **No `verify:browser:crm` npm alias.** | `package.json` defines `verify:browser`, `:structure`, `:library`, `:documents` and `:privacy`. The CRM journey is invoked in CI only by direct `node scripts/runtime/crm-browser-e2e.mjs` in `.github/workflows/crm-browser-e2e.yml`. | Add the alias so the CRM journey is locally runnable on the same contract as its siblings. CI coverage exists; local parity does not. |
| G8 | **Security/scope matrix not yet proven at the CRM boundary in a browser.** | Backend tests cover branch provenance, organization scope and fail-closed unknown provenance. No CRM browser evidence exists for manager cross-branch denial, unauthorized mutation, invalid lifecycle transition, idempotency conflict/replay or concurrent mutation as *observed operator behaviour*. | Execute the full §5 matrix against CRM and record it, per `docs/OPERATING-CONTROL.md` §5. A UI hiding a control is never a substitute for server authorization — and server authorization already proven in PHPUnit still needs the browser-layer denial evidence. |

G1–G8 are the closure inventory. They are derived from this registry and the
operating control state, and they are not a second source of truth: if inspection
at the then-current HEAD contradicts a row, the row is wrong and must be
corrected before it is relied on.

### 3.4 Closed predecessors

Their per-dimension evidence chains live in their own audits and are deliberately
not restated here, because a registry row is not a closure report:

| Domain | Closed by | Evidence owner |
|---|---|---|
| Library & Resources | PR #26 | `docs/AUDIT-2026-09-12-LIBRARY-RESOURCES-CLOSURE.md` |
| Documents & Evidence | PR #26 | `docs/AUDIT-2026-09-13-DOCUMENTS-CLOSURE.md` |
| Privacy & Consent | PR #29 (`27b96d8`); re-verified at `b824efd` after documentation-only PR #30 and at current HEAD `eb67300` after documentation-only PR #31 | `docs/AUDIT-2026-09-13-PRIVACY-CLOSURE.md` |

Privacy's closure remains the reference standard for what "closed" means in this
repository: consent as a lifecycle fact and never a boolean flag; erasure as
revocation/expiry/archive over retained evidence and never a row deletion; and an
organization-wide release requiring two distinct approver signatures with its
requester excluded and execution decided at organization scope. CRM closure is
held to the same evidentiary standard, not a lighter one.

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
