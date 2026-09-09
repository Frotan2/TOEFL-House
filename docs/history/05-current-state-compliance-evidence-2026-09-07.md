# Current-State Compliance Evidence Snapshot

**STATUS: SUPPORTING EVIDENCE — NOT PRIMARY NORMATIVE AUTHORITY**

This is a historical/current-state evidence snapshot produced during the prior convergence phase. It is preserved because it contains detailed implementation-vs-target evidence. It may cite paths that existed at the time of the snapshot; those paths are historical provenance, not live navigation. For current rules use `../README.md` and the canonical documents at the docs root.

> **Status note (2026-09-09):** where this snapshot says *runtime certification
> status: NOT READY — ENVIRONMENT BLOCKER*, that blocker no longer exists: the
> locked runtime is reproducible via `scripts/runtime/provision.sh` and the
> system is **certified production-ready at commit `96925d3`** — see
> [`../AUDIT-2026-09-09-FINAL-CERTIFICATION.md`](../AUDIT-2026-09-09-FINAL-CERTIFICATION.md).
> The rows below remain the 2026-09-07 documentation-vs-implementation
> snapshot and are not a release statement.

---

# TOEFL House — Current-State Documentation → Implementation Compliance

**Date:** 2026-09-07 (Asia/Kabul)  
**Purpose:** authoritative current-state reconciliation between the project's normative documents and the implementation actually present in the repository.  
**Scope:** documentation/specification/policy/target-state convergence only. This document is **not** a runtime certification.  
**Runtime certification status:** **NOT READY — ENVIRONMENT BLOCKER** (official PHP 8.2.27 / PostgreSQL 18.4 / Node 22.23.1 / Composer runtime was not available in the certification environment).  
**Governing authority:** `docs/MASTER_ENGINEERING_CONTRACT.md`, then accepted target-architecture ADRs/reviews, then current implementation contracts and current operational procedures; historical reports remain evidence and do not override current authority.

## 0. Canonical documentation entry point

For the consolidated current documentation system, start at [`docs/README.md`](../README.md). The canonical governance set is under `docs/canonical/`. This document remains the evidence-backed current-state reconciliation and must not be treated as a replacement for the domain-specific canonical documents.

## 1. Executive conclusion

### Overall target-state assessment

**TARGET STATE PARTIALLY REACHED**

The implementation has reached and in several areas exceeded the project's documented architectural maturity: modular Laravel monolith boundaries, centralized authorization, branch-aware provenance, Finance-owned monetary facts, staged approvals, database-backed invariants, PostgreSQL temporal enforcement, outbox/consumer coordination, Employee/Management Workspace projections, branch-safe Search, and a React/TypeScript operator boundary are all present in the current tree.

The target is not fully reached because the current accepted architecture explicitly retains material deferred work: full React migration of remaining Blade interactive surfaces; complete event-fed/rebuildable projection paths and operator replay/dead-letter tooling; complete notification channel/device/recipient policy depth; further Scheduling/Enrollment boundary extraction; policy-complete donor/sponsorship/bank/procurement/AP/inventory/asset breadth; complete payroll-liability-to-journal/reconciliation package; and operational/runtime verification. Some of these are product-breadth gaps rather than architectural defects, but they prevent an honest claim that the full documented target has been reached.

### Runtime certification assessment

**NOT READY — ENVIRONMENT BLOCKER**

Static conformance must not be conflated with runtime certification. The final runtime gate remains separately open.

## 2. Authority order used

1. `docs/MASTER_ENGINEERING_CONTRACT.md` — permanent master engineering constitution.
2. `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md` — mandatory accepted target graph.
3. `docs/architecture/decisions/2026-09-05-unified-platform-architecture.md` and other accepted 2026-09-05/06 ADRs.
4. `docs/architecture/2026-09-05-unified-platform-reconciliation.md` — accepted target reconciliation and explicit deferred-work register.
5. Current implementation contracts under `docs/implementation/` and current operational procedures under `docs/operations/`.
6. Historical package checkpoints, audits, WP records, and foundation discovery artifacts — evidence only unless explicitly re-adopted by a current authority.
7. Legacy System A/old architecture material — capability/reference evidence only.

## 3. Current repository evidence snapshot

| Fact | Current evidence | Classification |
|---|---|---|
| Laravel | `composer.lock`, `composer.json`; Laravel `12.67.0` dependency resolution | Current implementation |
| PHP contract | `.env.example`, environment documentation: PHP `8.2.27` | Normative/current contract |
| PostgreSQL target | environment/architecture documents: PostgreSQL `18.4` | Normative/current contract |
| Node contract | package/deployment/runtime documents specify `22.23.1` | Normative/current contract |
| npm contract | package/deployment/runtime documents specify `10.9.2` | Normative/current contract |
| Modules | `app/Modules/`: **24 modules** | Current implementation |
| Migrations | `database/migrations/`: **185** | Current implementation |
| Controllers | `app/Http/Controllers/`: **31** | Current implementation |
| Test files | `tests/`: **134 `*Test.php` files** | Current implementation |
| React/TS sources | `resources/js/`: **4 TS/TSX application files** | Current implementation |
| Blade views | `resources/views/`: **32 views** | Current implementation / migration residue |
| API surface | versioned `/api/v1` groups present in `routes/api.php` | Current implementation |
| Workspace/Search | `Workspace`, `ManagementWorkspace`, `SearchQuery`, corresponding API controllers | Current implementation |
| CRM | `app/Modules/Crm/*` plus API/React surface | Current implementation |
| Outbox | `app/Modules/Outbox/*`, transactional event/consumer structures | Current implementation |
| Scheduling boundary | `app/Modules/Scheduling`, but some lifecycle responsibility remains under Academic | Partial target conformance |

## 4. Documentation inventory

There are **187 textual project documents** (`.md`, `.mdx`, `.txt`, `.rst`) in the current repository, excluding `.git` metadata. The complete inventory is listed in Appendix A. Classification is based on directory role, document status/date, explicit supersession language, and cross-document authority—not filename alone.

### Current normative / governing

- `docs/MASTER_ENGINEERING_CONTRACT.md`
- `docs/governance/supreme-technical-governance-mandate.md`
- `docs/governance/employee-workspace-principle.md`
- `docs/architecture/2026-09-05-unified-platform-reconciliation.md`
- `docs/architecture/decisions/2026-09-05-unified-platform-architecture.md`
- `docs/architecture/decisions/2026-09-05-integrated-system-architecture-graph.md`
- the accepted 2026-09-05/06 architecture decisions
- current architecture contracts under `docs/architecture/01–27-*` and architecture invariant/traceability artifacts
- current implementation contracts under `docs/implementation/02–16-*` where not explicitly historical
- `docs/operations/production-deployment.md`

### Current operational / current-state evidence

- `docs/architecture/current-state-compliance.md` (this document)
- `docs/implementation/00-implementation-state.md` (historical evidence plus current-authority notice)
- current implementation contracts, current package acceptance records, and current operational procedures

### Historical / superseded evidence

The older foundation discovery records, package checkpoints, Gate 0–6 review records, early WP-1 matrix/roadmap, earlier repository conformance audit, and earlier phase certifications are retained as evidence. They are not current completion claims when they conflict with the accepted 2026-09-05 target architecture or later implementation state.

### Stale / contradictory current-state documentation identified

At minimum:

- `docs/foundation/00-foundation-state.md` still describes the repository as an Express/TypeScript/SQLite legacy implementation and says Laravel/PostgreSQL production implementation was not authorized; this is historical foundation evidence and is no longer a description of the current implementation.
- `docs/foundation/03-document-index.md` still says most foundation artifacts are planned/blocked even though the repository contains the completed foundation, architecture, implementation, and operational packages. Its historical table is useful evidence but is stale as a current inventory.
- `docs/implementation/WP-1-capability-gap-matrix.md` and `WP-1-roadmap.md` contain pre-convergence counts and missing-capability claims (for example 125 migrations and CRM/placement gaps) that no longer describe the current repository. They remain historical WP-1 snapshots.
- `docs/environment/P02-environment-baseline.md` contains older environment/test expectations and should be treated as a historical baseline rather than the current certification record.
- `docs/implementation/43-phase-4-production-assurance.md` contains a historical production-certification statement but the later unified architecture explicitly defers runtime certification; it is historical evidence, not a current readiness claim.
- `docs/implementation/40-package-17-production-readiness-employee-interface-checkpoint.md` contains a historical PASS statement and later amendment; it must not override the current architecture's explicit runtime-validation deferral.

## 5. Documentation → Implementation compliance matrix

| ID | Document / authority | Requirement / policy | Type | Current implementation | Evidence | Status | Gap / action |
|---|---|---|---|---|---|---|---|
| C-01 | Master Engineering Contract | Modular, production-grade, multi-branch Laravel ERP/EdTech with one authoritative owner per material fact | Architecture | Laravel modular monolith with 24 modules and centralized domain commands | `app/Modules/*`; master contract §1/§6 | **FULLY ACHIEVED** | Runtime proof remains separate |
| C-02 | Master Contract + integrated graph | One source of truth; projections must never become authorities | Architecture / data governance | Finance, Academic, Access, Students, Placement, Reporting, Workspace, Communication and Integration roles are separated | `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`; current modules | **ACHIEVED — EXCEEDED** | Continue writer inventory/runtime verification |
| C-03 | Master Contract | Controller is transport adapter, not business authority | Architecture | Controllers delegate into module commands/queries; current API/web surfaces use canonical commands | `app/Http/Controllers/*`; command inventory | **FULLY ACHIEVED** | Runtime/API parity still unverified |
| C-04 | Master Contract §10–11 | Organization/campus/branch scope and historical provenance | Security / data | Branch provenance fields, branch-scope links, effective structure queries, branch-safe reporting/search, command-time scope checks | migrations `000121+`; `RecordBranch`; reporting/search/workspace queries | **ACHIEVED — EXCEEDED** | Runtime branch-isolation certification remains open |
| C-05 | Authorization contract | Central Access decision, effective identity, fail-closed scope, SoD | Security | `AccessResolution`, `AccessDecision`, staged approvals, delegation and scope logic | `app/Modules/Access/*`; authorization contracts | **FULLY ACHIEVED** | Runtime proof remains open |
| C-06 | Lifecycle contract | Every stateful aggregate has one guarded transition graph | Lifecycle | Lifecycle classes/commands across Academic, Students, Admissions, Finance, Payroll, HR, CRM, Resources | `docs/foundation/47-lifecycle-architecture-contract.md`; `app/Modules/*/Domain/*Lifecycle.php` | **ACHIEVED — EXCEEDED** | Runtime transition coverage remains open |
| C-07 | Financial architecture | Finance owns monetary truth; Payroll proposes/calculates, Finance records liabilities/settlements | Finance authority | Finance owns payment, obligation, allocation, journal, correction, payroll liability, employment settlement | `app/Modules/Finance/*`; `app/Modules/Payroll/Domain/SettlementProposalApproval.php`; migrations `000143+` | **ACHIEVED — EXCEEDED** | Runtime financial certification remains open |
| C-08 | Financial policy | Idempotency, immutable facts, ledger linkage, DB-backed monetary invariants | Finance controls | Unique payer references, immutable/correction paths, ledger/journal structures, checks/triggers | Finance migrations/commands | **FULLY ACHIEVED** | PostgreSQL runtime verification required |
| C-09 | Concurrency policy | Critical mutations safe under retry/concurrency; materially sensitive races demonstrated | Reliability | Locking/idempotency patterns and dedicated concurrency test suite exist | `tests/Feature/Reliability/ConcurrencyRaceTest.php`; commands/domain classes | **PARTIALLY ACHIEVED** | Official PostgreSQL runtime execution is still required |
| C-10 | Academic target | Term → program/version → level → offering → class → capacity → enrollment → assessment/progression/graduation/transcript | Academic | Broad lifecycle exists; levels, offerings, waitlist, placement, progression, graduation, transcripts present | `app/Modules/Academic/*`, `Enrollment`, `Scheduling`; migrations through `000190` | **PARTIALLY ACHIEVED** | Remaining target boundary extraction/offering lifecycle/consumer depth |
| C-11 | Placement decision package | Evidence-backed placement, scoring/moderation/approval/release/recommendation | Academic / Placement | Full placement tree with evidence, scoring, moderation, approval, signed eligibility snapshot | `app/Modules/Academic/Placement/*`; related migrations | **FULLY ACHIEVED** | Runtime verification required |
| C-12 | Identity/admissions/student policy | Distinct identity, admission workflow, student lifecycle, provenance | Identity / Admissions / Students | Dedicated modules, staged admission, student provenance/status/history, React/API surfaces | `app/Modules/Identity`, `Admissions`, `Students`; routes/api.php | **FULLY ACHIEVED** | Runtime verification required |
| C-13 | Organization governance | Roles, positions, scope grants, delegation, sensitive approval and effective employment | Governance | Dynamic Access model with staged org-wide grants and effective employment checks | `app/Modules/Access/*`; migrations `000116+` | **FULLY ACHIEVED** | Annual review automation/open governance detail remains documented debt |
| C-14 | Payroll/HR | Separate calculation/result/proposal/settlement authority; no duplicate settlement cash fact | HR/Payroll/Finance | Payroll calculation/result/proposal; Finance `employment_settlements`; old `final_settlements` authority consolidated | `app/Modules/Payroll/*`, `Finance/*`; migration `000143` | **ACHIEVED — EXCEEDED** | Full accounting/reconciliation completion remains |
| C-15 | CRM target | Visitor/lead/follow-up/conversion capability feeding Admissions | CRM | CRM module with visitor, follow-up, interaction, conversion, versioned API and React CRM surface | `app/Modules/Crm/*`; routes/api.php; `resources/js/*` | **ACHIEVED — EXCEEDED** | Deeper automation/recipient policy remains |
| C-16 | Reporting contract | Reporting is read-only, source-linked, rebuildable and never a ledger | Reporting | Metric catalog/calculators/projections/reconciliation/report runs; no finance authority | `app/Modules/Reporting/*`; reporting contract | **PARTIALLY ACHIEVED** | Event-fed/replayable materialized projector chain and broader immutable snapshots remain |
| C-17 | Integrated graph | Transactional outbox, idempotent consumers, distinct event/workflow/task/notification concepts | Integration | Outbox, event envelope/context, allowlisted relay, consumer receipts, Work Management and Notification projections | `app/Modules/Outbox`, `WorkManagement`, `Communication`; migrations `000150+` | **PARTIALLY ACHIEVED** | Replay tooling, dead-letter operator surface, richer external subscriptions remain |
| C-18 | Employee Workspace Principle | Work-first/exception-first, role-aware not role-locked, projection only, command-time reauthorization | UX / architecture | Employee + Management Workspace queries/APIs and React console; canonical command links | `app/Modules/Workspace/*`; `resources/js/app.tsx`; workspace governance doc | **PARTIALLY ACHIEVED** | Remaining React migration, runtime composition/accessibility/efficiency verification |
| C-19 | React architecture | One React/TypeScript operator boundary; remaining Blade is migration residue | Frontend architecture | React app exists and CRM/Students/Admissions have crossed over; many Blade views remain | `resources/js/*`; `resources/views/*`; fourth-convergence review | **PARTIALLY ACHIEVED** | Retire remaining interactive Blade surfaces; runtime browser verification |
| C-20 | API architecture | Versioned `/api/v1`, same authority as web, no permanent unversioned duplicate | API | `/api/v1` routes/controllers over canonical commands | `routes/api.php`; unified architecture review | **FULLY ACHIEVED** | Typed/OpenAPI contract still deferred; runtime contract tests open |
| C-21 | Search | Governed, branch-safe read projection, never an authority | Search | `SearchQuery` + API; branch-safe student/visitor search | `app/Modules/Workspace/Queries/SearchQuery.php`; `SearchApiController.php` | **FULLY ACHIEVED** | Ranking/pagination/index/rebuild are deferred enhancements |
| C-22 | Notifications | Notification is distinct from domain event; recipient/read state is projection, not business truth | Communication | Notification model/query/consumer; recipient/read projection migration | `app/Modules/Communication/*`; migration `000150` | **PARTIALLY ACHIEVED** | Channel/device preferences, expiry/retry and richer recipient policies remain |
| C-23 | Work Management | Tasks/work items coordinate work without becoming authority | Operations/workflow | Workflow/work-item/history + queue membership and projection consumers | `app/Modules/WorkManagement/*`; migration `000151` | **PARTIALLY ACHIEVED** | SLA timers, automatic queue assignment, escalation/adapters remain |
| C-24 | Database invariants | DB should enforce invariants where technically practical | Database | Extensive CHECK/FK/UNIQUE/triggers/exclusion constraints; temporal and financial hardening | `database/migrations/*.php`; `000170`, `000190`, earlier guards | **ACHIEVED — EXCEEDED** | Clean PostgreSQL execution still unverified |
| C-25 | Calendar | Authoritative Kabul/Solar Hijri semantics with effective operational window | Academic/Calendar | `Calendar` authority, Solar Hijri domain, ratified series and fail-closed range | `app/Modules/Calendar/*`; F4 verification docs | **FULLY ACHIEVED** | Runtime verification remains open |
| C-26 | Documents/Privacy | Private document authority, privacy/retention/access controls | Security/privacy | Dedicated Documents + Privacy modules, lifecycle and consent controls | `app/Modules/Documents`, `Privacy`; package checkpoint | **FULLY ACHIEVED** | Runtime storage/access verification remains open |
| C-27 | Audit | Append-only historical evidence; denied operations are evidence, not successful events | Governance/audit | Audit recorder, attempted/rejected operation records, immutable evidence patterns | `app/Modules/Audit/*`; audit architecture | **FULLY ACHIEVED** | Runtime tamper tests remain open |
| C-28 | Backup/recovery | Backup, restore, recovery and deployment procedures are documented and executable | Operations | `deploy/backup.sh`, `deploy/restore.sh`, recovery docs, deployment gates | `deploy/*`; `docs/operations/production-deployment.md` | **PARTIALLY ACHIEVED** | Current official-environment drills still required |
| C-29 | Deployment | Reproducible install/build, preflight, migration, activation, health/readiness, safe rollback semantics | Operations | Hardened deployment preflight/rollback and documentation | `deploy/deploy.sh`; production deployment doc | **PARTIALLY ACHIEVED** | `package-lock.json` and official runtime execution remain release blockers |
| C-30 | Observability | Logging/diagnosis/monitoring/readiness are part of production readiness | Operations | Security headers, structured error handling, health endpoint, deployment health gate | config/logging + `HealthController` | **PARTIALLY ACHIEVED** | Full metrics/alerts/operational dashboards remain target work |
| C-31 | Testing contract | Material capability dimensions include lifecycle, authz, DB, concurrency, API/UI, adversarial, E2E and operations evidence | QA | Large unit/feature/adversarial/concurrency suite and E2E scripts exist | `tests/*`, `e2e-*.php`, testing architecture | **PARTIALLY ACHIEVED** | Official runtime execution is outstanding; frontend/browser/accessibility/deployment execution remains open |
| C-32 | Product breadth | Full approved ERP/EdTech breadth is complete across required lifecycles | Product scope | Core school operations are broad, but explicit deferred breadth remains | unified reconciliation §20 and explicit deferred capabilities | **PARTIALLY ACHIEVED** | Donor/sponsorship policy depth, bank reconciliation, procurement/AP, inventory/asset depth, portal, inbox, richer reporting, etc. |
| C-33 | Documentation governance | Current docs must record architecture, decisions, requirements, implementation state, operations, recovery, known limitations and evidence | Governance | Large documentation corpus exists; several historical documents remain stale as current inventory | this document + document index + current contracts | **PARTIALLY ACHIEVED** | Keep one authoritative current-state document and mark stale historical records |
| C-34 | Reproducibility | Material behavior, dependencies, priorities and completion gates must be reproducible | Governance/release | Composer lock exists; npm lockfile is absent; runtime stack not currently executable in certification environment | `composer.lock`, `package.json`, deployment docs, runtime certification report | **PARTIALLY ACHIEVED** | Generate/commit real `package-lock.json` in an environment with registry access; official runtime certification |
| C-35 | Release status | Completion claims must be evidence-backed; runtime and target-state are separate judgments | Governance | Historical PASS claims retained as evidence; current state correctly records runtime deferral in newer architecture docs | master contract §48; unified reconciliation; implementation-state current notice | **FULLY ACHIEVED** | Keep historical claims explicitly historical |

## 6. Area-by-area maturity assessment

| Area | Target | Current | Gap | Evidence | Confidence |
|---|---|---|---|---|---|
| Architecture | Unified Laravel modular monolith with single owners and typed cross-domain edges | Core shape is implemented and hardened | Some target edges remain deferred (especially full frontend migration and remaining boundary extraction) | architecture graph + current modules | HIGH |
| Backend | Command/domain authority, canonical queries, server enforcement | Broad command/query/domain architecture exists | API typing/OpenAPI and some workflow adapters remain deferred | `app/Modules`, API routes | HIGH |
| Frontend | One React/TS operator boundary, work-first workspace | React boundary + workspace + CRM/Student surfaces exist | 32 Blade views remain; browser/accessibility not runtime-certified | `resources/js`, `resources/views` | HIGH |
| Database | PostgreSQL-enforced business invariants | 185 migrations and extensive guards/constraints | Official clean DB execution unavailable in certification environment | `database/migrations` | HIGH |
| Security | Central Access, scope/SoD, fail closed | Strong centralized model | Runtime verification open | Access module + governance docs | HIGH |
| RBAC | Effective identity, scope, delegation, approvals | Implemented | Annual review automation and some policy configuration remain | Access + governance | HIGH |
| Finance | Finance sole monetary authority; ledger/corrections/reconciliation | Core authority is strong and more complete than early documents | Full bank/procurement/AP/aid policy breadth and runtime reconciliation remain | Finance module + ADRs | HIGH |
| Academic | Complete governed lifecycle | Broad lifecycle implemented | Scheduling/Enrollment separation and some offering/packaging depth remain | Academic/Enrollment/Scheduling | HIGH |
| Organization | Multi-branch, provenance, historical correctness | Strong branch topology/provenance implementation | Some scope propagation still needs runtime proof | Organization/Reporting/Search | HIGH |
| Placement | Evidence-based assessment/recommendation | Strong and explicit | Runtime proof remains | Academic/Placement | HIGH |
| Payroll/settlement | Separate calculation/proposal and Finance-recognized money | Implemented | Accounting/reconciliation package remains | Payroll/Finance + migrations | HIGH |
| CRM | Visitor/lead/follow-up/conversion | Implemented and React/API surfaced | Further automation/policy depth remains | Crm module | HIGH |
| Reporting | Rebuildable read models, no write authority | Governed read model/calculators exist | Complete event-fed projector/replay and snapshot breadth remain | Reporting module + architecture | HIGH |
| Audit | Append-only evidence and provenance | Implemented | Runtime tamper verification remains | Audit module | HIGH |
| Concurrency | DB-backed safe races and idempotency | Design + tests exist | Official PostgreSQL execution remains open | concurrency tests + DB guards | HIGH |
| Deployment | Reproducible production operation | Hardened scripts/docs exist | Node lockfile + official-runtime deployment simulation remain | deploy + operations docs | HIGH |
| Operations | Backup/recovery/readiness/observability | Significant foundation exists | Full current-environment drills, metrics/alerts, DR objectives remain | deploy/docs | MEDIUM-HIGH |
| Documentation/governance | One authoritative current truth + historical evidence separation | Major improvement now established by this document | Existing stale docs still need clear historical labeling | docs corpus | HIGH |

## 7. Implementation ahead of documentation

The implementation is stronger than several early project documents in these areas:

1. **Financial ledger authority.** Current Finance contains authoritative journals, ledger derivations, corrections, payroll liability recognition and employment settlements that were absent or less mature in earlier Foundation/WP-1 documents.
2. **PostgreSQL invariant enforcement.** Current migrations contain extensive trigger/check/exclusion protections, including temporal assignment and the offering/class capacity invariant in `000190`.
3. **Branch provenance.** Current application paths now populate and consume originating/current-home branch context much more broadly than the original WP-1 foundation snapshot stated.
4. **CRM.** Current CRM is implemented, including visitors, follow-ups, interactions, conversion, API and React surfaces; old WP-1 records that say CRM is missing are historical.
5. **Workspace/Search/Work Management/Notifications/Outbox.** These were explicitly added in the fourth convergence and are current architecture capabilities.
6. **Staged approvals and SoD.** Refunds, admissions, progression, settlement and organization-wide grants have evolved to explicit staged workflows with database backstops.
7. **Calendar Authority.** Solar Hijri/Kabul calendar authority has moved from an open/blocked foundation question to an implemented and verified design record.

## 8. Documentation ahead of implementation

These documented target promises are not yet fully represented in code or are explicitly deferred:

1. Full React migration and retirement of the remaining interactive Blade boundary.
2. Complete OpenAPI/typed API contract and runtime contract verification.
3. Fully replayable/materialized Reporting/Workspace/Search/Communication projector lifecycle and operator rebuild/dead-letter tooling.
4. Notification channel/device/recipient policy depth.
5. Work Management SLA timers, automatic queue assignment and escalation.
6. Full Scheduling/Enrollment boundary separation where required by the final graph.
7. Policy-complete donor restriction, sponsorship agreement/claim/clawback, bank reconciliation, procurement/AP, inventory/asset depth and related ERP breadth.
8. Student/guardian portal, inbox/reminder capability and richer reporting/immutable report snapshots.
9. Complete payroll-liability journal/disbursement lineage and correction/reversal reconciliation package.
10. Full production observability, metrics/alerts and disaster-recovery objective validation.
11. Official runtime verification across PHP 8.2.27, PostgreSQL 18.4 and Node 22.23.1.

## 9. Obsolete requirements / historical claims

The following are **not current requirements** merely because they remain in the repository:

- the original Express + TypeScript + SQLite implementation model;
- Foundation-era statements that production Laravel/PostgreSQL implementation was prohibited;
- WP-1 capability counts and missing-capability statements from before fourth-architecture convergence;
- old migration counts (99, 107, 112, 118, 125, etc.) when presented as current state;
- old cumulative test counts when presented as current certification;
- package-specific PASS statements that predate the current unified architecture and its runtime-validation deferral;
- old references to retired `final_settlements` authority in contexts that conflict with the later Finance `employment_settlements` decision.

Historical documents remain valuable evidence and should not be deleted merely because they are obsolete as current requirements.

## 10. Runtime-unverified items

The following remain explicitly outside documentation-conformance proof and require the official runtime certification process:

- Composer dependency installation and autoload/container resolution;
- PostgreSQL 18.4 clean migration of all 185 migrations;
- actual database constraints/triggers/indexes/exclusion behavior;
- PHPUnit full suite and exact current counts;
- PHPStan and Pint on the official PHP runtime;
- Node 22.23.1 clean `npm ci` and production build;
- browser/UI/API execution and React/Blade migration behavior;
- real multi-session PostgreSQL concurrency;
- backup/restore/deployment simulation;
- queue/outbox/consumer retries and replay;
- performance/query plans;
- accessibility and employee-efficiency validation.

## 11. Current documentation governance decisions

1. This document is the authoritative current-state compliance view until superseded by a later explicitly approved current-state reconciliation.
2. Historical reports must not be rewritten to manufacture current compliance.
3. Stale planning documents should be marked historical/superseded at their top-level status when practical.
4. Current operational/deployment documentation must contain current runtime versions and actual deployment behavior.
5. Runtime certification remains a separate gate and may not be inferred from documentation compliance.
6. No migration is to be created merely to fill historical numbering gaps.

## 12. Final target-state assessment

**TARGET STATE PARTIALLY REACHED**

The implementation has crossed the architectural convergence threshold and exceeds several early documented baselines. It has **not** yet reached the full intended product/operational maturity described by the current master contract plus accepted target architecture graph because several explicitly deferred product, projection, frontend, and operational capabilities remain, and runtime certification is not complete.

## 13. Final runtime certification status

**NOT READY — ENVIRONMENT BLOCKER**

This is intentionally separate from the target-state assessment. A later official-runtime certification may change the runtime status without changing this documentation verdict unless implementation evidence changes.

## 14. Priority actions

### P0 — Documentation truth

- Keep this document as the current-state authority.
- Mark stale Foundation/WP-1/P04-era files as historical/superseded rather than allowing them to look current.

### P1 — Target-state completion

- Complete the remaining React migration boundary.
- Finish the deferred projection/replay/operator tooling where required by the accepted target.
- Complete remaining finance/ERP breadth and Scheduling/Enrollment boundary work according to the approved target graph.

### P1 — Runtime certification

- Execute the separate official-runtime certification package exactly as specified by the release certification mandate.
- Do not report the target state as runtime-certified until the official environment passes.

---

# Appendix A — Complete textual-document inventory

The table below lists every textual project document in the repository and assigns its current documentation role.

| Path | Classification | Authority role |
|---|---|---|
| `ACADEMIC_AUDIT_REPORT.md` | Historical audit report | Historical evidence |
| `SETUP.md` | Current informational/installation guidance | Operational reference |
| `docs/MASTER_ENGINEERING_CONTRACT.md` | Current normative requirement / policy | Governing authority |
| `docs/architecture/00-architecture-state.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/01-system-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/02-architectural-style-decision.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/03-module-and-boundary-map.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/04-dependency-graph.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/05-transaction-boundary-model.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/06-financial-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/07-authorization-and-scope-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/08-approval-workflow-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/09-lifecycle-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/10-academic-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/11-hr-payroll-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/12-reporting-and-derived-data-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/13-audit-and-history-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/14-privacy-and-document-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/15-integration-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/16-background-processing-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/17-concurrency-consistency-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/18-error-exception-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/19-observability-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/20-resilience-security-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/2026-09-05-unified-platform-reconciliation.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/21-testing-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/22-legacy-migration-boundary.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/23-architecture-decision-records.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/24-architecture-invariant-registry.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/25-architecture-traceability.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/26-gate-5-architecture-review.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/27-teacher-faculty-authority-architecture.md` | Current architectural specification | Current architecture contract |
| `docs/architecture/current-state-compliance.md` | Current normative requirement | Current-state authority |
| `docs/architecture/decisions/2026-09-05-broad-read-authorization.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-class-session-branch-provenance.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-employee-workspace-principle.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-enrollment-constraint-boundary.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-finance-payroll-liability-reporting.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-integrated-system-architecture-graph.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-notification-projection-boundary.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-production-authority-and-correction.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-scheduling-constraint-boundary.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-transactional-domain-events-and-delivery.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-unified-platform-architecture.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-05-work-management-boundary.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-06-finance-fixed-point-money.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-06-financial-coverage-serialization.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/2026-09-06-payroll-held-resolution.md` | Current architectural decision | Accepted target / ADR |
| `docs/architecture/decisions/WP2-approved-decisions.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-appeal-resolution-semantics.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-branch-scope-doctrine.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-eligibility-snapshot.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-enrollment-completion.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-gradesheets.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-graduation-integrity.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-level-progression-and-packaging.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-offering-operations.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-offerings-waitlist.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-operational-completion.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-progression-lifecycle.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-progression-rules.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-room-section-timetable-operations.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-rooms-sections-timetable.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-teacher-assignment-lifecycle.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-terminal-guard-doctrine.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-transcript-issuance.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-academic-waitlist-operations.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-enrollment-financial-gate.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-placement.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/decisions/wp-visitor-crm.md` | Historical / superseded architectural decision | Historical evidence unless re-adopted |
| `docs/architecture/review/2026-09-05-cross-module-consolidation-audit.md` | Historical architectural review | Historical evidence |
| `docs/architecture/review/2026-09-05-final-platform-architecture-blueprint.md` | Historical architectural review | Historical evidence |
| `docs/architecture/review/2026-09-05-fourth-architecture-convergence.md` | Historical architectural review | Historical evidence |
| `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md` | Current architectural specification | Mandatory target architecture |
| `docs/architecture/review/2026-09-05-repository-conformance-audit.md` | Historical architectural review | Historical evidence |
| `docs/architecture/review/2026-09-06-academic-class-api-convergence.md` | Current architectural review | Current evidence |
| `docs/architecture/review/academic-redteam-2026-09-05.md` | Historical architectural review | Historical evidence |
| `docs/design/P16-skill-scale-contract-payroll-DESIGN.md` | Informational / project evidence | Reference |
| `docs/environment/P02-environment-baseline.md` | Historical environment baseline / recovery evidence | Historical operational evidence |
| `docs/foundation/00-foundation-state.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/01-legacy-system-intelligence-report.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/02-organization-discovery.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/03-document-index.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/04-decision-ledger.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/05-risk-register.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/06-foundation-purity-verification.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/07-batch-01-organization-discovery.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/08-requirements-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/09-authority-and-scope-matrices.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/10-domain-and-workspace-register.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/11-rules-authority-invariants-traceability.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/12-open-questions-and-risks.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/13-batch-processing-record.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/14-batch-processing-record.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/15-agent-decided-defaults.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/16-batch-processing-record.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/17-master-decision-questionnaire.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/18-master-processing-record.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/19-canonical-domain-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/20-entity-relationship-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/21-governance-and-access-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/22-lifecycle-and-control-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/23-finance-academic-hr-controls.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/24-data-privacy-resilience-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/25-foundation-modeling-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/26-gate-1-requirements-completeness-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/27-gate-2-business-rule-completeness-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/28-gate-3-domain-model-completeness-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/29-canonical-entity-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/30-source-of-truth-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/31-relationship-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/32-lifecycle-transition-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/33-authority-scope-operation-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/34-financial-domain-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/35-academic-evidence-decision-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/36-hr-payroll-domain-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/37-privacy-consent-disclosure-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/38-derived-data-lineage-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/39-domain-contracts.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/40-configuration-domain-classification.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/41-gate-3-remediation-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/42-architecture-boundary-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/43-domain-boundary-contract-registry.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/44-authoritative-data-flow-model.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/45-authorization-and-scope-architecture-contract.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/46-financial-architecture-contract.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/47-lifecycle-architecture-contract.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/48-reporting-and-derived-data-contract.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/49-privacy-audit-resilience-architecture-contract.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/foundation/50-gate-4-architecture-readiness-review.md` | Foundation specification / historical governance evidence | Foundation evidence; later current docs supersede where conflicting |
| `docs/governance/employee-workspace-principle.md` | Current normative requirement / policy | Governing authority |
| `docs/governance/supreme-technical-governance-mandate.md` | Current normative requirement / policy | Governing authority |
| `docs/implementation/00-implementation-state.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/01-implementation-readiness.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/02-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/03-command-query-registry.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/04-module-implementation-contracts.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/05-financial-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/06-authorization-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/07-lifecycle-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/08-academic-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/09-hr-payroll-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/10-reporting-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/11-integration-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/12-concurrency-idempotency-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/13-error-exception-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/14-testing-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/15-migration-implementation-contract.md` | Current implementation contract / mixed historical record | Current contract where later convergence has not superseded it |
| `docs/implementation/16-legacy-disposition-registry.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/17-implementation-sequence.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/18-implementation-risk-register.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/19-gate-6-review.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/20-package-01-contract-harness-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/21-implementation-quality-directive.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/22-environment-blocker-report.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/23-environment-readiness.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/24-package-02-identity-organization-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/25-package-03-authorization-scope-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/26-package-04-documents-privacy-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/27-package-05-students-admissions-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/28-package-06-academic-delivery-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/29-package-07-academic-decisions-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/30-package-07-hr-teachers-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/31-package-09-payroll-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/32-package-10-finance-core-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/33-package-11-payments-funding-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/34-package-12-assets-operations-communication-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/35-package-13-reporting-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/36-package-14-integrations-jobs-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/37-final-system-review-p02-p14-certification.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/38-package-15-opening-financial-state-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/39-package-16-skill-scale-contract-payroll-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/40-package-17-production-readiness-employee-interface-checkpoint.md` | Historical / superseded implementation evidence | Historical evidence; not current readiness authority |
| `docs/implementation/41-phase-3-employee-coverage-matrix-checkpoint.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/42-phase-3-certification.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/43-phase-4-production-assurance.md` | Historical / superseded implementation evidence | Historical evidence; not current readiness authority |
| `docs/implementation/44-e2e-business-journey.md` | Historical runtime/e2e evidence | Evidence only; not current official-runtime certification |
| `docs/implementation/WP-1-capability-gap-matrix.md` | Historical / superseded implementation evidence | Historical evidence; not current readiness authority |
| `docs/implementation/WP-1-roadmap.md` | Historical / superseded implementation evidence | Historical evidence; not current readiness authority |
| `docs/implementation/WP-1.5-architecture-decision-package.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/WP-2-F4A-calendar-authority-verification.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/WP-2-F4A.3-solar-hijri-reference-series-ratification.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/WP-2-F4B-calendar-authority-implementation-design.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/WP-2-F4C-calendar-authority-implementation-verification.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/implementation/WP-2-S1-governed-config-registry.md` | Historical implementation checkpoint / evidence | Historical evidence |
| `docs/operations/production-deployment.md` | Current operational procedure | Current deployment authority |
| `public/robots.txt` | Informational / project evidence | Reference |
| `tests/Support/PgWire/README.md` | Informational / project evidence | Reference |

## Appendix B — Key evidence locations

- Master authority: `docs/MASTER_ENGINEERING_CONTRACT.md`
- Mandatory target graph: `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`
- Current target reconciliation: `docs/architecture/2026-09-05-unified-platform-reconciliation.md`
- Unified architecture ADR: `docs/architecture/decisions/2026-09-05-unified-platform-architecture.md`
- Current implementation state: `docs/implementation/00-implementation-state.md`
- Production deployment procedure: `docs/operations/production-deployment.md`
- Core Finance: `app/Modules/Finance/`
- Access/authorization: `app/Modules/Access/`
- Academic/Enrollment/Scheduling: `app/Modules/Academic/`, `app/Modules/Enrollment/`, `app/Modules/Scheduling/`
- Placement: `app/Modules/Academic/Placement/`
- CRM: `app/Modules/Crm/`
- Workspace/Search: `app/Modules/Workspace/`
- Work Management: `app/Modules/WorkManagement/`
- Communication/Notifications: `app/Modules/Communication/`
- Outbox/consumers: `app/Modules/Outbox/`
- Database authority/invariants: `database/migrations/`
- Concurrency evidence: `tests/Feature/Reliability/ConcurrencyRaceTest.php`
