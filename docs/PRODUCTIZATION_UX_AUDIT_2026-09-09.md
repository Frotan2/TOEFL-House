# TOEFL House — Productization, Workspace & Operational UX Final Audit

**Audit date:** 2026-09-09  
**Audited ref:** `arena/01a08580-toefl-house` @ `556fe4b807cedec94176e6d894e03e25ffec73e2`  
**Implementation ref:** `productization/p0-p1-workspaces`  
**Scope:** UI / UX / information architecture / workspace / dashboards / settings / operational flows only. Backend domain authority is explicitly out of scope.

## 1. Executive Verdict

**VERDICT: PARTIALLY PRODUCTIZED — technical foundation is strong, operational product experience is not yet complete.**

The repository has a substantial canonical API/domain surface and several React workspaces, but the primary experience remains console-first rather than responsibility-first. The owner is initially presented with a generic personal workspace and a small set of module links instead of a command center that explains institute state, exceptions, readiness and next actions.

The most important product gap is not missing CRUD. It is the missing chain:

**User → Context → Task → Action → Server Authority → Result → Feedback → Next Action**

The branch already contains the raw material for that chain: a read-only employee workspace projection, management reporting/health projection, work items, notifications, academic workspace, student lifecycle workspace, finance workspace, reporting workspace, identity/access/organization/HR/payroll/placement surfaces, and canonical API commands. The product layer has not yet composed these capabilities into a coherent role-oriented operating model.

A structural P0/P1 pass was implemented on an isolated branch without changing backend authority: the global IA labels are more task-oriented, the management screen now functions as an operational Command Center plus Administration/Setup hub, the shell exposes personal work and administration entry points, and the personal work queue now has a stable anchor.

**Runtime verification of the implementation branch remains pending.** The available local execution environment cannot reach the GitHub repository/application runtime, and the latest pre-implementation Verification workflow on the audited branch is `action_required`, not a passing build result. No claim is made that the new branch is runtime-certified.

## 2. Current Product Reality

### What already exists

- React/TypeScript front-end entry points for the employee console plus dedicated Finance, Reporting, HR, Payroll, Placement, Identity, Access and Organization bundles.
- A shared AppShell, responsive layout layer, focus behavior, tables, status chips, loading states and reusable visual primitives.
- `/api/v1` is the canonical interactive transport; the API file comments explicitly describe it as session-authenticated, server-authorized and delegated to owning commands/queries.
- Employee workspace projection already composes assigned follow-ups, admission reviews, payroll exceptions, academic appeals, work-management projections and notifications.
- Management projection already provides scoped counts, reporting metrics and latest report evidence.
- Student workspace already exposes a rich lifecycle: admissions, placement, status, holds, transfers, guardians, enrollments, attendance, assessments, progression, obligations, payments, documents, messages and audit events.
- Academic workspace already exposes classes, offerings, timetable/session data, enrollments, waitlist, attendance, assessments, progression, graduation, transcripts, appeals, periods, program versions, levels, skills, rooms and teachers.
- Finance and Reporting have explicit authority-boundary language and server-owned data loading.

### Why it still feels dry / fragmented

1. The shell is a flat module list rather than a work model.
2. `/workspace` is useful as a personal queue but does not establish an institute-level operating context for an owner.
3. `/management` is decision support, not a true command center: it reports counts and report runs but does not connect the operator to a prioritized operational agenda.
4. Academic `Setup` is embedded as a class-definition area, not a lifecycle workspace that explains what is configured, what is missing, and what comes next.
5. The system has a real work-item projection, but completing a work-management item is only coordination state; the UI does not guarantee that the corresponding source-domain business action was completed. That distinction must remain explicit.
6. The shell does not receive an aggregated server-provided navigation capability model, so it cannot yet legitimately personalize visibility by role. Client-side role inference should not be added as a shortcut.
7. Organization/Identity/Access/HR/Payroll/Placement exist as separate surfaces but are absent from the primary navigation and have weak contextual integration.
8. Several workspaces are administrative/operator consoles rather than persona workspaces. The clearest example is Teacher: it manages teacher capability records but does not tell a teacher what class is next, what attendance is due, or what grading is pending.
9. Reporting is evidence-aware but mostly analytical. It does not systematically drill a metric into the underlying operational record/action.
10. Many pages are capable of displaying source facts but do not consistently present a single obvious next action.

## 3. Existing Capabilities Successfully Exposed

| Capability | Backend | UI | Navigation | Workflow | Role UX | Settings | Dashboard | Status |
|---|---|---|---|---|---|---|---|---|
| Personal work / notifications | Yes | Yes | Yes | Partial | Partial | N/A | No | **B** |
| Students + admissions | Yes | Yes | Yes | Strong | Partial | No | Partial | **A/B** |
| Student lifecycle/detail | Yes | Yes | Yes | Strong | Partial | No | Partial | **A** |
| Academic delivery | Yes | Yes | Yes | Strong within workspace | Partial | Partial | Partial | **B** |
| Teacher capability administration | Yes | Yes | Yes | Strong admin flow | Weak teacher-day UX | No | No | **B** |
| Finance operations | Yes | Yes | Yes | Strong command boundary | Weak settings UX | Partial | Weak | **B** |
| Reporting | Yes | Yes | Yes | Run/report/dashboard definition | Weak operational drill-through | Partial | Yes | **B** |
| HR | Yes | Yes | No in primary shell | Strong internal lifecycle | Weak persona separation | Partial | No | **B** |
| Payroll | Yes | Yes | No in primary shell | Strong internal lifecycle | Weak landing experience | Partial | No | **B** |
| Organization topology | Yes | Yes | No | Read-only only | Weak admin journey | Weak | No | **C** |
| Identity | Yes | Yes | No | Internal admin flow | Weak | Partial | No | **B/C** |
| Access governance | Yes | Yes | No | Internal governance | Weak | Partial | No | **B/C** |
| Placement | Yes | Yes | No | Internal governed flow | Weak front-office integration | No | No | **B/C** |
| Command Center | Partial | **Implemented in branch** | Yes | Partial | Owner/management targeted | Yes | Yes | **P1 improved** |
| Settings/control plane | Partial | **Administration hub implemented** | Yes | Partial | Admin oriented | **Conceptual hub** | No | **P1 improved** |

## 4. Existing Capabilities Hidden / Orphaned

### D/F — backend exists but the primary product does not expose it coherently

- Payroll workspace and payroll commands are not part of primary global navigation.
- HR workspace is not part of primary navigation.
- Access governance, Identity administration and Organization structure are not part of primary navigation.
- Placement has its own workspace but no first-class front-office journey into/from applicant/student operations.
- Finance has many authoritative commands in the API, but the visible overview exposes only a small subset of the finance authority surface.
- Academic contains a much larger setup/configuration model than its `Setup` tab communicates.

### B/C — UI exists but the capability is poorly connected

- Work queue items point to source routes but do not consistently surface the exact source-domain next command.
- Reporting runs do not systematically provide a drill-through to the record cohorts that explain the metric.
- Organization page is read-only and framed as topology, so the operator still has to know which other admin page creates/changes the structure.
- Teacher workspace exposes qualifications, skill authority and dated assignments but not the teacher's daily operational workload.

## 5. Existing Capabilities Connected Incorrectly

1. **Management is positioned as a generic module rather than the operator's command center.** The underlying management projection is already suitable for decision support, but the UI was not using it to build an action-oriented home.
2. **People / Faculty and HR are split in a way that obscures the distinction between person identity, teacher capability and employment lifecycle.** The domain separation is correct; the navigation is not explanatory enough.
3. **Academic Setup is too close to class CRUD.** The server already exposes period/program/level/offering/class/room/session structures, but the UX does not present the lifecycle as a sequence.
4. **Work Management completion is easy to confuse with completion of the source business action.** The UI must state that Work Management coordinates ownership; the owning module completes the actual business transition.
5. **Reporting is a destination rather than a diagnostic link from operational exceptions.** A metric should lead to the records that require action where the underlying domain supports that path.

## 6. Dead-End Workflows

| Entry | Dead end | Correct next step |
|---|---|---|
| Management count | Read the number only | Drill to the relevant source workspace and action queue |
| Management report run | Evidence/status only | Open report source / underlying record cohort |
| Student detail | Many facts but no persistent “next best action” pattern | Show lifecycle-specific next action at the top |
| Academic period | Setup concepts are scattered | Lead into program → level → offering → class sequence |
| Class detail | Teacher/section actions exist but room/schedule are not orchestrated as one progression | Present delivery readiness rail |
| Teacher admin | Administrative record only | Teacher daily workspace needs today/next/attendance/assessment data |
| Finance record | Payment/obligation actions exist but no end-to-end “what changed?” summary | Return user to resulting financial state and related student |
| Organization topology | Read-only structure view | Explicit links into Identity/Access/People/Academic setup responsibilities |
| Reporting dashboard | Configuration-focused | Provide operational drill-through where source ownership permits |

## 7. Missing Workspaces

### P0/P1

- **Owner / Management Command Center** — implemented structurally in `management.tsx`.
- **Administration & Setup Control Plane** — implemented as a responsibility-based hub in `management.tsx`.
- **Academic Setup Workspace** — still needed as a dedicated lifecycle-oriented composition of existing Academic projections.
- **Teacher Day Workspace** — still needed; requires server data for today's classes / next class / pending attendance / pending grading if not already exposed by an existing endpoint.
- **Reception / Front Office Workspace** — still needed; should combine visitors, applications, placement, admissions, payment/enrollment readiness and follow-ups.
- **Finance Operations Command Center** — current finance workspace exists but needs exceptions/approvals/period context and source-owned balances/obligations where APIs support them.

## 8. Missing Dashboards

The product needs two distinct dashboard classes:

1. **Landing / task dashboards**: “what do I need to do now?”
2. **Analytical dashboards**: “what is happening and why?”

The current Management and Reporting surfaces mostly implement the second class. The P0 Owner experience requires the first class.

The Owner dashboard should expose only server-provided metrics: students, classes, pending admissions, financial exceptions, held payroll, work items, readiness signals, notification signals, recent report evidence. Every metric should have a drill target.

## 9. Missing Settings / Control Plane

Implemented structurally as an Administration hub, but underlying control pages remain distributed:

- Organization: `/organization`
- Identity: `/identity`
- Access: `/access`
- Academic: `/academic`
- People/Teacher: `/teachers`
- Finance: `/finance`
- HR: `/hr`
- Payroll: `/payroll`
- Reporting: `/reporting`
- Placement: `/placement`

The hub is intentionally a navigator, not a second configuration authority.

## 10. Missing Role-Based Experiences

The current shell is not truly role-personalized because it receives no aggregated navigation capability projection. Do not solve this in React by guessing from role names or positions.

Required target state:

- **Owner:** Command Center first; institute health; approvals/exceptions; setup readiness; configuration hub.
- **Reception:** today's front office; visitors; applicants; placement; admission decisions; payments; enrollment readiness; follow-ups.
- **Academic Manager:** delivery health; current period; setup readiness; classes; teacher/room/schedule gaps; attendance/outcomes exceptions.
- **Teacher:** today / next class; roster; attendance due; pending assessments; schedule; own assignments.
- **Finance:** money work queue; obligations; payments; exceptions; approvals; period state; receipts; authoritative balances if supported by an API.

Only server-provided capabilities may control privileged action visibility. Navigation personalization should use a server-provided capability model once available.

## 11. Missing Onboarding

There is no sufficiently explicit first-run operator sequence in the main product experience.

Recommended server-backed readiness sequence:

1. Organization structure
2. Branch/campus readiness
3. People/roles/access
4. Academic period
5. Program/version
6. Levels/rules/skills
7. Finance configuration
8. People/teacher capability
9. Rooms/resources
10. Offerings/classes
11. Schedule/delivery readiness
12. Student intake/enrollment readiness

The UI must derive each readiness state from server projections, not replicate policy in browser code.

## 12. Information Architecture Deficiencies

### Current model

Mostly **module-first**, with some object-first workspaces and a generic personal work queue.

### Target model

**Hybrid: role-first landing + task-first workspaces + object/context navigation + module-owned detail pages.**

Proposed top-level structure:

- Home
- Students & Admissions
- Academic Operations
- People & Faculty
- Front Office / CRM
- Finance
- Reports
- Command Center
- Administration (utility, not business-module authority)

Within workspaces, prefer contextual subnavigation rather than adding more top-level modules.

This follows the core interaction principle used by mature enterprise systems: a workspace is an activity-oriented starting point containing the most relevant information and frequent tasks, not merely a list of database objects. Microsoft documents Dynamics 365 workspaces as activity-oriented pages that answer pressing activity questions and initiate frequent tasks. citeturn141353search4turn141353search5

## 13. Design System Deficiencies

### Existing strengths

- coherent design tokens
- responsive breakpoints
- focus states and reduced-motion support
- common panels/buttons/forms/tables
- consistent source-authority language
- evidence/status vocabulary

### Gaps

- no shared task-card primitive with standardized primary action / reason / due state / source
- no standardized readiness-step component
- no standardized contextual breadcrumb component
- no standardized drill-through metric card
- no standardized “next action” hierarchy across detail pages
- no consistent role/area context badge in the shell
- no shared empty-state taxonomy: `not configured`, `no records`, `filtered zero`, `blocked by prerequisite`, `waiting for upstream workflow`, `not in scope`
- no unified notification/task center pattern

## 14. Benchmark Findings

### Adopt

**SAP Fiori:** role-based landing areas, curated pages/spaces, app discovery/search, and task indicators. SAP explicitly positions the launchpad home page as the central entry point and states that role determines which relevant apps are shown; spaces/pages are curated around a business role rather than exposing the entire application catalog. citeturn265315search0turn265315search7

**ServiceNow:** a landing page that orients the user to current work, critical/new tasks, prioritized work, approvals and useful resources. This is a strong model for TOEFL House's Owner and role-based landings. citeturn265315search2turn265315search10

**Dynamics 365:** activity-oriented workspaces with sections that answer pressing operational questions and initiate frequent tasks. TOEFL House should adopt this definition of “workspace.” citeturn141353search4turn141353search5

**Odoo:** dashboards should centralize frequently consulted views, support filters, and allow navigation to underlying records; this supports the requirement that TOEFL House dashboards remain actionable rather than decorative. citeturn141353search0turn141353search1turn141353search6

**Do not adopt:** giant all-module launchers, spreadsheet-like configuration as the default home, or dashboard customization complexity before operational basics are solved. TOEFL House needs a curated operating experience first. Visual copying is not a goal.

## 15. P0 Implementation Plan

### P0-01 — Replace generic management landing with Command Center

**Screen:** `/management`  
**Component:** `resources/js/management.tsx`  
**Backend capability:** existing `/api/v1/management` and `/api/v1/workspace` projections  
**Change:** action center, operational metrics, readiness, notifications, recent report evidence, quick actions  
**User goal:** know what is happening and what to do next  
**Next action:** click metric/task into source workspace  
**Dependency:** none  
**Status:** **IMPLEMENTED on productization branch**

### P0-02 — Establish task-oriented shell IA

**Screen:** global shell  
**Component:** `resources/js/ui.tsx`  
**Change:** Home, Students & Admissions, Academic Operations, People & Faculty, Front Office / CRM, Finance, Reports, Command Center; utility entry points for My Work and Administration  
**Dependency:** none  
**Status:** **IMPLEMENTED on productization branch**

### P0-03 — Preserve personal work queue deep link

**Screen:** `/workspace`  
**Component:** `resources/js/workspace.tsx`  
**Change:** persistent `#work-queue` anchor and explicit link from shell  
**Dependency:** none  
**Status:** **IMPLEMENTED**

## 16. P1 Implementation Plan

### P1-01 — Academic Setup lifecycle workspace

Create a guided workspace that visually sequences existing projections: Organization → Period → Program → Levels → Offerings → Classes → Teachers → Rooms → Schedule → Enrollment → Sessions → Attendance → Assessment → Progression.

**Primary file:** `resources/js/academic.tsx` or a dedicated route-level composition if the existing workspace becomes too dense.  
**Backend dependency:** existing Academic workspace query appears sufficient for the first pass; no new domain authority is required merely for orchestration.

### P1-02 — Server-provided navigation capability projection

Add one server read projection containing authorized navigation areas and relevant action capabilities. This is a genuine backend dependency because the current shell does not receive a complete permission/capability model.

**Do not** infer role from UI, route names or position labels.

### P1-03 — Teacher Day Workspace

Expose today's classes, next class, relevant roster, attendance due, assessment/grading queue and schedule. First inspect whether the existing Academic `/academic/sessions` or another query already exposes sufficient scoped data. If not, add a read projection only; do not create UI-derived teaching authority.

### P1-04 — Reception Workspace

Compose existing CRM + Students + Placement + Finance projections into one front-office journey. Preferred entry: `/workspace` role variant or a dedicated `/front-office` workspace once navigation capability modeling exists.

### P1-05 — Operational drill-through

For each Command Center metric, ensure the destination opens the relevant filtered source workspace. Never calculate a new KPI in React.

### P1-06 — Empty-state taxonomy

Standardize all major empty states with explicit categories and next actions. Do not use a generic “No records found” for setup blockers.

### P1-07 — Detail-page next action standard

Add a consistent top-level `Next action` block to student, applicant, class, teacher, finance and work-item-related detail views using server-provided state/capability facts.

## 17. P2 Implementation Plan

- server-backed context breadcrumb / scope header
- better reporting drill-through and source cohorts
- shared task card / readiness components
- server-provided finance period selection where supported
- stronger list filtering/pagination consistency
- saved views/recent records where appropriate
- role-specific dashboard personalization after capability projection exists
- contextual help and explanatory copy per workspace

## 18. P3 Implementation Plan

- visual polish / icon refinements
- micro-spacing and density tuning
- richer empty-state illustrations only where they improve comprehension
- optional personalization of quick actions
- non-essential animation refinements

## 19. Files / Components / Routes to Change

### Implemented

- `resources/js/ui.tsx` — global IA labels, My Work and Administration utilities.
- `resources/js/management.tsx` — Command Center + Administration/Setup hub.
- `resources/js/workspace.tsx` — stable work queue anchor.
- `resources/js/experience.css` — command-center, readiness, admin and responsive patterns.
- `docs/PRODUCTIZATION_UX_AUDIT_2026-09-09.md` — this audit/backlog.

### Next P1 targets

- `resources/js/academic.tsx` — setup lifecycle composition.
- `resources/js/teacher.tsx` / Academic read projections — teacher day experience.
- `resources/js/students.tsx` — next-action pattern and reception integration.
- `resources/js/finance.tsx` — exceptions/approval context and drill-through.
- `resources/js/reporting.tsx` — source drill-through.
- `resources/js/hr.tsx`, `payroll.tsx`, `placement.tsx`, `access.tsx`, `identity.tsx`, `organization.tsx` — contextual entry and IA integration.
- `resources/views/workspace.blade.php` — only if route/view composition needs additional role/workspace mount points.

## 20. Backend Dependencies — ONLY where genuinely unavoidable

1. **Server-provided navigation capabilities** — required for legitimate permission-aware shell personalization; current `AppShell` has no capability projection.
2. **Teacher daily operational data** — only if no existing authorized query can provide today's sessions/next class/pending attendance/assessment scope.
3. **Operational drill-through cohorts** — only where a report metric currently cannot identify its authoritative source records via existing endpoints.

No backend dependency is required for the Command Center composition already implemented: it consumes existing workspace/management/academic/organization projections.

## 21. Acceptance Criteria

### Owner

- Login opens on a page that communicates context, institute state, active work and readiness.
- Owner can reach Students, Academic, Finance, People and Reports without prior training.
- Every attention item has a clear destination.
- Setup gaps have meaningful remediation destinations.
- Dashboard metrics are sourced from server projections.
- No authoritative financial/academic/access decision is calculated by React.

### Reception

- Can move visitor → applicant → placement → admission → payment/enrollment with explicit next action states.

### Academic Manager

- Can inspect current period and move through setup/delivery readiness without hunting through unrelated screens.

### Teacher

- Can identify today's next class and outstanding attendance/assessment work once the required read data is exposed.

### Finance

- Can identify outstanding operational exceptions and move to payment/approval/detail actions with visible resulting state.

### Structural

- Hidden buttons are treated as UX only; server remains authority.
- No duplicate API transport layer is introduced.
- No direct DB writes from UI.

## 22. Verification Results

### Repository evidence inspected

- Shared shell and current navigation: `resources/js/ui.tsx`.
- Shared experience styles: `resources/js/experience.css`.
- Personal workspace composition: `resources/js/workspace.tsx`.
- Management projection and authority boundaries: `app/Http/Controllers/Api/WorkspaceApiController.php`, `app/Modules/Workspace/Queries/ManagementWorkspaceQuery.php`.
- Employee task projection: `app/Modules/Workspace/Queries/EmployeeWorkspaceQuery.php`.
- Academic, Student, Teacher, Finance, Reporting and Organization workspaces.
- Canonical API routes across Students, Academic, Finance, Payroll, Workspace, Search and Work Management.
- Browser E2E harness, which explicitly requires genuine Chromium and checks mount/network/console/logout behavior.

### Runtime limitation

The implementation branch was written through the connected GitHub repository because the local runtime cannot clone or reach the GitHub-hosted source from the execution environment. Therefore:

- **No claim of live-browser verification of the new branch is made.**
- The latest audited-branch Verification run is recorded by GitHub as `completed / action_required`, not as a pass. The run head was the audited branch before this implementation branch was created.
- The existing repository browser E2E harness is valuable and should be executed in a real application environment as the first verification gate for this branch.

### Required verification commands in a real checkout

```bash
npm run typecheck
npm run build
npm run test:frontend
npm run verify:browser
```

Then perform the persona acceptance tests manually in Chromium.

## 23. Remaining Risks

1. The shell still cannot legitimately hide/show navigation by role until a server-provided capability projection is exposed.
2. Academic Setup remains the largest high-value product gap.
3. Teacher and Reception experiences are not yet true day-to-day workspaces.
4. Work Management is coordination state; the UI must not imply that “Complete coordination” equals completion of the underlying domain transaction.
5. Finance currently avoids deriving balances client-side, which is correct; the product needs an authoritative balance/obligation summary endpoint if a true outstanding-balance dashboard is required and no current endpoint provides it.
6. Some read-only administration pages remain isolated from the main operational journey.
7. Current CI/runtime status does not yet prove this implementation branch is build/browser clean.

## 24. Final Product Readiness Verdict

**Current audited branch before this productization pass:** approximately **60–65% product-ready**. The engineering foundation is materially stronger than the user experience suggests.

**After the implemented P0 structural pass:** the product has a credible Command Center and better task-oriented shell, but it is **not yet fully operational-product ready** because the Academic Setup, Reception, Teacher Day and server-capability-driven role landing gaps remain.

**Release posture:**

- Backend/domain authority: **preserve**
- Product shell / owner landing: **P1 structure materially improved**
- Academic setup journey: **P1 open**
- Role-based workspace personalization: **P1 backend dependency**
- Runtime/browser verification of implementation branch: **OPEN**
- Final productization verdict: **NOT CLOSED — continue with P1 workflow composition, then verify in real browser**
