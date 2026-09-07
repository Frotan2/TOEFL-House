# Architecture Evolution, ADRs & Reviews History

STATUS: HISTORICAL — NOT NORMATIVE

This consolidated volume preserves historical source documents verbatim by section. The source path is retained before each section. Current project authority lives in the canonical documentation set, not here.


---

## HISTORICAL SOURCE: `architecture/00-architecture-state.md`

# Architecture State

**Governing contract:** `docs/MASTER_ENGINEERING_CONTRACT.md` (TOEFL HOUSE ERP — WORLD-CLASS MASTER ENGINEERING CONTRACT, v3.0, Canonical Project Engineering Constitution) is the highest-authority project engineering directive. Its approved permanent Employee Workspace addendum is maintained at `docs/governance/employee-workspace-principle.md`, and its mandatory/non-negotiable Integrated Enterprise System Architecture Graph is maintained at `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`; the architecture artifacts below must remain consistent with all three.

**Current gate:** Gate 5 — PASS WITH NON-BLOCKING OPEN ITEMS
**Current implementation-readiness gate:** Gate 6 — PASS WITH NON-BLOCKING OPEN ITEMS
**Updated:** 2026-09-05

The fourth-architecture convergence remains a static, not-production-ready implementation state. The repository now contains owner-bound contexts, server-side authorization, Finance-only monetary writes, explicit provenance, transactional domain events with an allowlisted relay/consumer boundary, Work Management coordination, recipient/read Notifications, rebuildable projection contracts, and a standalone React workspace boundary. Runtime and resultant-schema verification remain intentionally deferred.

Gate 6 contracts remain governing. The current convergence report at `docs/architecture/review/2026-09-05-fourth-architecture-convergence.md` is the latest actual/gap/decision/implementation record; it supersedes earlier conformance baselines without claiming production readiness.

---

## HISTORICAL SOURCE: `architecture/01-system-architecture.md`

# System Architecture

## Logical topology

Clients and administrative interfaces call an application boundary. The application layer exposes commands, queries, workflow orchestration, and contract validation; it never owns domain truth. Inside one deployable logical system, bounded contexts have explicit ownership and communicate through domain contracts. The domain layer enforces invariants and lifecycles. A server-side authorization policy boundary evaluates identity, position, assignment, permission, scope, policy, and approval before commands reach an owner.

Persistence is behind repository/unit-of-work boundaries owned by each context. Finance has a separate logical boundary and transaction rules. Audit receives append-only material-operation records independently of mutable operational logs. Reporting reads published canonical facts and metric definitions into projections. Document storage is opaque content plus metadata/verification owned by Documents and Privacy. Integrations are anti-corruption boundaries. Background jobs run post-commit work only, with durable job state.

This is a logical topology, not a technology selection. UI, files, logs, projections, and external systems are never authorities. The mandatory connected enterprise graph and typed relationship vocabulary are defined in `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`; any conflicting topology or edge is a conformance defect.

The employee workspace is a first-class product and application capability over this topology. It composes effective authority, relevant work context, canonical queries, and canonical actions into role-aware but not role-locked employee and management work environments. It may aggregate cross-module work, tasks, approvals, deadlines, notifications, and exceptions, but it never becomes a source of business truth or authorization. Workspace composition must be server-derived, lifecycle-aware, scope-aware, and safe under authority changes.

---

## HISTORICAL SOURCE: `architecture/02-architectural-style-decision.md`

# ADR-001: Architectural Style

**Decision:** modular monolith with strict bounded-context boundaries, one controlled deployment unit initially, and explicit seams for future extraction.

**Context:** TOEFL House has strong cross-domain transaction and audit requirements, a single institutional business model, substantial financial consistency needs, and no authorized scale/team assumptions requiring distributed deployment.

**Alternatives:** Microservices improve independent scaling/failure isolation but add distributed transactions, operational cost, contract/version overhead, and more reconciliation. SOA offers service boundaries but similar coordination cost without a present organizational benefit. A layered unbounded monolith risks hidden coupling and duplicate authority.

**Rationale:** modular monolith minimizes deployment complexity while preserving domain ownership, atomic financial operations, deterministic tests, and auditability. Context contracts, separate persistence ownership, and asynchronous post-commit integration prevent it becoming a shared-data monolith.

**Consequences:** one failure domain initially; module boundaries and contracts must be enforced structurally. Extraction is possible later only with evidence and a new decision. No framework or vendor is selected.

---

## HISTORICAL SOURCE: `architecture/03-module-and-boundary-map.md`

# Module and Boundary Map

| Module/bounded context | Owns | Commands | Forbidden |
|---|---|---|---|
| Organization/Governance | structure, ownership, policy approvals | create, transfer, close, policy approve | operational facts |
| Identity | people, accounts, verification | establish/link/deactivate identity | grants or student status |
| Authorization/Scope | positions, roles, permissions, assignments, delegation, scope | grant, revoke, delegate, resolve | self-authority |
| Admissions/Students/Guardians | applications, admission, student, verified relationships | apply, admit, convert, withdraw, guardian management | enrollment membership, grades, balances |
| Enrollment | membership/registration lifecycle and reusable seat/prerequisite constraints; current extraction reads Academic canonical facts until lifecycle authority moves | request, activate, freeze, transfer, complete, withdraw | grades, balances, source-domain delivery facts |
| Academic (Placement, Classes, Attendance, Assessment) | programs, periods, classes, delivery facts, evidence, decisions | place, define, record, score, approve, appeal | scheduling planning authority, finance/payroll truth |
| Scheduling | planning constraints, availability/reservation/conflict projections, room/teacher viability | plan, reserve, release, reconcile | Academic delivery/session truth, enrollment membership, finance/payroll truth |
| HR/Teachers/Payroll | employment, contracts, work basis, leave, calculations, results | employ, assign, calculate, approve, settle | academic decisions, payment posting |
| Finance (Receivables, Payments, Refunds, Discounts, Funding) | obligations, transactions, journals, funds, reconciliation | post, allocate, refund, adjust, reconcile | mutable balances |
| Books/Inventory/Assets/Facilities | catalog, custody, stock, assets, work orders | issue, return, move, dispose, complete | accounting journals |
| Work Management | workflow coordination, source-linked tasks/approvals/exceptions, assignments, queue membership, SLA state, history | start, assign, claim, transition, escalate | domain approvals, source facts, permissions, money, notifications |
| Communication | message intent/delivery and recipient-scoped notification/read projection | send, read, dismiss, deliver, retry | business fact authority, task/approval truth |
| Documents/Privacy | metadata/content, verification, consent/disclosure | verify, disclose, revoke, retain | business fact authority |
| Audit | immutable audit evidence | record/verify | state mutation |
| Reporting | metric definitions and projections | define, run, reconcile | source facts |
| Employee Workspace | effective work context composition, source-linked task/approval presentation, personal productivity preferences | compose, prioritize, save presentation preferences | domain truth, authorization, lifecycle, financial facts, and independent shadow workflow state |

The Employee Workspace is an orchestration and presentation capability, not a replacement bounded context for business facts. It may query multiple owner contexts and link employees to canonical actions, but it cannot create a competing authority.

Each module owns lifecycle, invariants, persistence interface, and public contracts. The integrated graph is mandatory for every module boundary and relationship: `OWNS`, `CREATES`, `READS`, `COMMANDS`, `AUTHORIZES`, `VALIDATES`, `CONSUMES_EVENT`, `EMITS_EVENT`, `PROJECTS`, `DOCUMENTS`, `ASSIGNS`, `ESCALATES`, `CORRELATES`, and `REFERENCES` must be made explicit where applicable. Application orchestration coordinates but does not own entities. Events are emitted only for durable business facts or justified post-commit notifications.

---

## HISTORICAL SOURCE: `architecture/04-dependency-graph.md`

# Dependency Graph

Acyclic authority direction:

```text
Organization -> Authorization/Scope
Identity -> Admissions/Students, HR
Admissions/Students -> Enrollment, Academic, Finance
Enrollment -> Academic (canonical delivery facts/read constraints), Finance (eligibility gate)
Academic -> Students, Scheduling (planning constraints), HR/Payroll, Reporting
Scheduling -> Academic, Enrollment, Organization/Resources (read/constraint contracts only)
HR -> Payroll, Authorization; Payroll -> Finance (approved posting input)
Finance -> Reporting, Students, Funding
Funding -> Finance (restriction input)
Assets/Inventory/Facilities -> Finance (approved financial-effect input)
Privacy/Documents -> Communication and every disclosure gate
All source contexts -> Outbox -> Work Management, Communication/Notifications, Reporting, Search, Integrations
All contexts -> Audit and Reporting (read/record only)
Effective identity/authority/lifecycle + all relevant contexts -> Employee Workspace (composition/read/action links only)
Employee Workspace -> canonical owner commands (authorized command entry; no direct writes)
Work Management -> canonical source commands only through explicit action links; never direct source writes
```

Employee Workspace is an orchestration sink for effective context and canonical work entry. It must not become an upstream authority or a dependency of domain truth.

Dependencies are fact-oriented and must use the integrated graph's typed relationship vocabulary: the receiver reads an owner-owned fact, submits a command to the owner, validates a precondition, consumes an event, or projects a read representation as explicitly classified. Synchronous calls are used for authorization, preconditions, and decisions requiring immediate outcome. Asynchronous notifications are used after commit for reporting refresh, communication, and integrations. No receiver writes another context's tables or storage. This eliminates circular authority; application coordination may call multiple contexts only through commands and compensating/reversal operations.

---

## HISTORICAL SOURCE: `architecture/05-transaction-boundary-model.md`

# Transaction Boundary Model

Every command has: authenticate, authorize, validate current state/configuration, enforce invariant, commit owner facts and audit atomically, then publish an outbox/post-commit notification. Rejected commands write no business state; material denied attempts are audited.

| Operation | Atomic owner writes | Post-commit |
|---|---|---|
| enrollment/conversion | admission/student/enrollment facts and audit | notify Academic/Finance |
| class assignment | membership and capacity reservation | schedule/report refresh |
| attendance correction | corrected record plus linked history | notify Academic/reporting |
| result approval/appeal | decision and audit | release notification |
| payroll calculation/approval | calculation/result for period | finance posting input |
| payment/allocation | payment and allocation with invariant lock | receipt/report refresh |
| refund/discount/adjustment | approved source-linked transaction | statement/report refresh |
| journal/reconciliation | balanced journal or reconciliation evidence | close/alert |
| approval/authority change | immutable approval or effective assignment | authorization cache refresh |

Cross-context operations use a coordinator with explicit outcomes, never a distributed hidden transaction. Partial outcomes are held, retried, or reversed using domain rules.

Workspace composition is not a business transaction boundary. A workspace request may read effective authority and owner projections; a workspace-originated mutation re-enters the owning command boundary and repeats authentication, authorization, current-state, invariant, SoD, and scope checks. Saving a workspace preference is isolated presentation state and cannot commit domain facts.

---

## HISTORICAL SOURCE: `architecture/06-financial-architecture.md`

# Financial Architecture

Finance owns fee definitions as configuration, obligations/charges, payments, allocations, refunds, discounts, scholarships/funding postings, adjustments, reversals, cash movements, journals, periods, and reconciliation. Funding owns restrictions and awards; Finance owns monetary posting. Reporting derives balances and totals from posted facts.

Transactions are immutable after posting. Idempotency keys are required at command and external-integration boundaries; duplicate keys return the original result. Allocation and refund commands use serialized per-source/obligation concurrency control and re-check amounts at commit. Student-level uncovered-balance decisions additionally serialize on the canonical student row through `FinancialCoverageLock` before competing approvals, allocations, corrections, obligation posting, or enrollment activation derive and commit coverage. Payment, allocation, and refund are separate commits with source references. Corrections append adjustments/reversals. Finance recognizes approved Payroll results/adjustments as unique signed liability facts and records employment settlements; Payroll remains source/proposal evidence only. Journals require balanced lines, source reference, period, and posting authority. Closed periods reject mutation and permit only controlled reopening.

A payment cannot be allocated twice; allocations cannot exceed payment or obligation; refunds cannot exceed refundable source; restricted funds cannot be reclassified without approval. Reconciliation compares independent observed/source sets and records variance, never edits balances. No UI, report, payroll view, or mutable balance can post truth.

Employee and management workspaces may surface Finance-owned tasks, approvals, deadlines, source-linked status, reconciliation exceptions, and canonical links. They cannot calculate, cache as authority, approve, post, allocate, refund, correct, or otherwise mutate monetary truth outside Finance commands and their server-side SoD, scope, period, and lifecycle checks.

---

## HISTORICAL SOURCE: `architecture/07-authorization-and-scope-architecture.md`

# Authorization and Scope Architecture

The policy decision point evaluates subject, operation, target, **Position + Assignment + Permission + Scope + Policy**, effective time, delegation, approval state, and conflict-of-interest rules. Position is organizational responsibility; role groups permissions; permission names an operation; scope limits objects; authority is the evaluated result; delegation is dated transfer of specified authority, never identity.

Default deny, server enforcement, least privilege, explicit organization→campus→branch→department hierarchy, expiration checks, two-Owner approval, requester/approver separation, and emergency time limits are mandatory. Scope resolution preserves both current effective visibility and historical attribution. Authorization decisions include policy version and scope evidence for audit. Unavailable policy, expired assignment, conflict, or missing approval denies/holds. Client controls are advisory only.

The employee workspace consumes effective authorization; it does not grant or cache authority. Workspace sections, tasks, approvals, shortcuts, record links, and action affordances must be composed from current identity, employment eligibility, positions, assignments, grants, delegations, lifecycle state, and scope. Every action is reauthorized server-side at command time, so suspension, termination, expiry, transfer, delegation revocation, or capability changes cannot leave stale workspace power.

---

## HISTORICAL SOURCE: `architecture/08-approval-workflow-architecture.md`

# Approval Workflow Architecture

A reusable approval record contains subject operation, target, policy version, required stages/independent approvers, requester, scope, effective/expiry times, state, decision reason, and immutable actions. Templates express one-person, sequential, independent, two-Owner, delegated, and emergency patterns; business modules select approved policy rather than embedding generic engine meaning.

The engine excludes requester, beneficiary, conflicted actor, expired/delegated actor, and unauthorized scope. Concurrent approvals use a compare-and-commit rule so required approval is counted once. Rejection, cancellation, expiry, and resubmission create immutable actions and a new attempt where required. Approval completion is not posting: the owning domain revalidates state and invariant before its transaction. Audit records every action and relationship.

Employee and management workspaces may prioritize pending approvals and provide context or a direct route to the approval workflow. They must not approve, count, bypass, or infer approval state; approval authority, SoD, delegation, expiry, scope, and final domain revalidation remain in the canonical workflow and owning domain.

---

## HISTORICAL SOURCE: `architecture/09-lifecycle-architecture.md`

# Lifecycle Architecture

Each context owns a transition table/state machine, not a universal status service. A command names current state, requested transition, actor, effective date, preconditions, and reason. The owner validates and atomically records the new state, audit, and history. Unknown or unauthorized transitions fail closed. Cancellation, expiry, reversal, and appeal are explicit transitions; correction appends linked history.

Critical state machines use the Gate 3 registry: admission/student/enrollment; class/session/attendance/assessment; progression/graduation/appeal; employment/leave/termination; payroll and financial period; obligation/payment/refund/discount/adjustment; funding; document/consent/disclosure; asset/work order/incident. Derived states (balances, metrics, percentages) are never transitioned as facts. Effective-dated configuration and relationships are evaluated as-of the relevant period.

Employee Workspace composition must consume authoritative lifecycle state. Suspension, termination, assignment expiry, position change, branch-scope change, or delegation expiry must remove or change affected work and actions; a cached workspace must never preserve access after the underlying lifecycle authority changes. The workspace may explain blocked or pending work but cannot override a lifecycle transition.

---

## HISTORICAL SOURCE: `architecture/10-academic-architecture.md`

# Academic Architecture

Academic owns program/level versions, periods, placement attempts/results, classes, schedules, teacher assignment as an academic delivery fact, the teaching skill catalog, session skill attribution, attendance, assessment evidence/results, progression decisions, appeals, eligibility, and certificate issuance. Students owns enrollment; HR owns employment; Finance owns obligations.

The teaching skill catalog (what is taught, independent of student level) is registered and retired under control and never deleted; assignments carry their skills as append-only evidence, and a session delivers at most one skill so delivery remains attributable by skill identity. Payroll consumes skill-attributed delivery evidence as a controlled input; it never owns it.

Raw responses, submissions, attendance, and teacher assessment are immutable evidence. Calculated results remain evidence until authorized moderation/review and official decision. Progression, graduation, and certification are explicit decisions with preconditions and appeal/supersession; no dashboard or score automatically changes status unless an approved rule says so. Class capacity and membership are concurrency-controlled. Cross-domain inputs use effective enrollment, verified teacher assignment, and approved program configuration.

---

## HISTORICAL SOURCE: `architecture/11-hr-payroll-architecture.md`

# HR and Payroll Architecture

HR owns person employment, contracts, contract approval, compensation scales, and compensation rules. Academic owns teaching reality, skill assignment, and attendance facts. Payroll owns period calculation, approved payroll result, and source-linked adjustment evidence. Finance owns recognized Payroll liability facts, journal/payment posting, and recorded employment settlements. Contractual entitlement, calculation, result, recognized liability, settlement, and actual payment remain separate.

## Skill and Scale

Skill (what a teacher teaches) is an Academic-owned catalog: registered with a stable key, retired under control, never deleted, and independent of student level. Teaching assignments carry their skills through append-only assignment-skill rows, and each class session delivers at most one skill, so delivered teaching volume is attributable by skill identity rather than free text.

Scale (the teacher's compensation rank) is an HR-owned catalog with the approved initial set S1 Junior, S2 Standard, S3 Senior, and S4 Expert. It is registered with a unique key and rank order, retired under control, never deleted, and independent of both skill and academic level — scale is a compensation rank, not a teaching classification. A contract version pins at most one scale; changing a teacher's scale is a governed contract amendment, and approved payroll never depends on the catalogs remaining active.

## Versioned contracts and FM-GM approval

Contracts are chains of immutable versions through the lifecycle draft → submitted → approved → active → superseded/expired. The Finance Manager prepares a draft version with its terms evidence reference, effective window, pinned scale, and compensation rules, and submits it; the General Manager approves it. The approver may never be the preparer and may never be the beneficiary, enforced in the command layer and in the schema (identity CHECKs plus immutability and no-delete triggers). Approval records approver, timestamp, and a digest over the version and its rules, so exactly what was approved can be reproduced. A withdrawal is possible only before approval. An amendment is a new version: at approval the prior in-force version is superseded with its window closed the day before the new version starts; approved terms and rules are never rewritten and never deleted.

## Compensation rules and rate resolution

Compensation rules attach to a contract version and are the single authoritative compensation model, addressable by method plus optional skill and optional scale dimensions: fixed monthly salary, labeled allowances, and per-unit session or hourly rates. Per-unit rates of one version share a single resolution space with a deterministic precedence ladder — exact skill x scale, then skill only, then scale only, then generic — with no overlap allowed inside the space (unique index). A delivered skill with no matching rule holds the calculation; nothing is silently paid at zero.

Fixed monthly and allowance lines are prorated by calendar days: payable = contract amount x active days / period days, where the active window is the overlap of the version's effective window and the payroll period (both inclusive). Full-period coverage pays the full amount; partial coverage is computed in exact integer cent arithmetic with round half up, so monetary precision never depends on float ordering. Per-unit rates are not prorated — a session paid in a period is paid in full for that period.

## Teaching volume and calculation

Teaching volume derives from authoritative academic delivery evidence. A session is a payable unit only when it is scheduled with one skill, covered by the teacher's effective assignment for that class and skill, and qualified by final attendance: at least one attendance fact whose status is present or late and which is the uncorrected tip of its authoritative corrects_id chain. Corrections resolve through the chain, never by timestamp ordering — a correction that flips the status retires the prior fact from qualification; absent and excused never qualify, and cancelled or never-held sessions carry no qualifying attendance and are not payable. Sessions outside assignment coverage and sessions without skill attribution hold the calculation for review.

Each qualifying session is claimed exactly once by a teaching delivery fact carrying the qualifying attendance fact as its evidence reference; the unique session claim makes double payment across periods, calculations, or reruns impossible at the database level, and a claim may migrate only from a superseded calculation of the same period and employment.

Payroll calculation resolves the in-force contract version for the exact period, then rules, then skill/scale, then authoritative volume, and stores a complete immutable snapshot: contract/version identity, scale, consumed rules and rates with rates, per-skill session and hour volumes, delivery claims with evidence references, additive lines with their contract amount and proration window (active days / period days), and the resulting amount. Once a result is approved, later contract, skill, scale, rate, assignment, attendance correction, or session changes cannot alter it — previously approved payroll reproduces exactly from the snapshot; corrections append adjustment/reversal history. Contract-silent periods (no in-force version) and rule-missing cases are held for HR/Finance; no invented charge, no silent zero, no legacy fallback. Payroll period closure and Finance period closure coordinate through explicit status checks; disagreement creates an exception, not silent overwrite. Approved Payroll results and adjustments cross into Finance only through `RecognizePayrollLiability`, which creates a unique, signed, branch-provenanced Finance liability fact after an independent Finance recognition decision; reporting reads those Finance facts rather than Payroll totals. Finance may later post or disburse through its own journal/payment commands; there is no second accounting engine.

The retired per-kind compensation-component architecture and its manual/academic work-basis evidence tables are removed from the active system, its schema, and its tests; the contract version with its compensation rules is the only compensation path.

Employee and management workspaces may surface HR/Payroll assignments, lifecycle actions, pending preparation or approval work, period deadlines, holds, and source evidence links. They do not own employment eligibility, compensation, payroll calculation, approval, settlement, or payment truth; those remain in HR, Payroll, and Finance with current server-side lifecycle, scope, and SoD checks.

---

## HISTORICAL SOURCE: `architecture/12-reporting-and-derived-data-architecture.md`

# Reporting and Derived Data Architecture

Reporting has metric definitions, versioned calculation specifications, scope/period parameters, projection refresh, report runs, completeness markers, and reconciliation status. It reads canonical domain outputs; it cannot write them. Operational queries use owner contexts; analytical projections are rebuildable and carry source/version/as-of metadata.

Financial metrics consume Finance posted transactions, journals, and reconciliation under Financial Period semantics. Enrollment, attendance, progression, payroll, funding, inventory, and asset metrics use their registered owner facts and effective dates. A report cannot redefine month, revenue, receivable, cash, payroll, or academic success. Stale/incomplete projections are labeled or withheld, never silently presented as authoritative.

Employee and management workspaces may surface governed metrics, deadlines, exceptions, and operational context, but workspace cards and work queues are presentation projections rather than business facts. A workspace must link to the authoritative report or domain workflow and must not maintain an independent balance, enrollment state, payroll state, approval state, or exception truth.

---

## HISTORICAL SOURCE: `architecture/13-audit-and-history-architecture.md`

# Audit and History Architecture

A material audit event records actor/person, authority decision and policy version, scope, operation, target, previous value/state, resulting value/state, reason, effective timestamp, recorded timestamp, correlation/reference ID, approval relationship, source, and outcome. It is append-only, access-controlled, retained by policy, and independent of mutable operational logs.

Owner transactions commit business fact and required audit evidence together. Cross-context notifications carry correlation IDs and produce receiving audit evidence. Historical records are immutable; current state, transfer, configuration, correction, reversal, appeal, and deactivation append linked facts. Audit does not become a business source of truth.

Workspace composition, personalization changes, task/notification delivery, and workspace-originated command attempts must remain attributable through correlation and appropriate audit evidence without turning workspace state into business truth. A workspace must provide enough context to explain an action while preserving the canonical owner's immutable history.

---

## HISTORICAL SOURCE: `architecture/14-privacy-and-document-architecture.md`

# Privacy and Document Architecture

Documents stores opaque content plus metadata: owner, classification, version, linked entity, verification state/evidence, retention rule, effective/revocation state, and access history. The file never becomes the authoritative student, payment, employment, or academic fact. Access requires purpose, scope, authorization, and minimum disclosure.

Privacy owns consent purpose, consent, revocation, disclosure, classification, retention, and export authorization. Communication and marketing consent are separate. Guardian access requires verified relationship. Reports, exports, admin screens, and integrations call the same privacy authorization boundary; bulk export requires explicit operation, scope, purpose, approval where policy requires, and audit. Revocation blocks future use without erasing history.

---

## HISTORICAL SOURCE: `architecture/15-integration-architecture.md`

# Integration Architecture

Future SMS, email, payment/banking, external identity, file storage, messaging, and reporting/export systems connect through anti-corruption adapters and versioned business contracts. No external system becomes internal source of truth without an explicit Foundation decision.

Each adapter has authenticated credentials held outside domain data, request/response mapping, correlation and idempotency key, timeout, retry/backoff policy, dead-letter/manual-review state, and audit. External payment ambiguity is reconciled by status inquiry/webhook and never blindly retried as a new payment. Webhooks are authenticated, deduplicated, ordered where needed, and source-linked. Integration failure leaves the owned internal fact pending/failed and visible for reconciliation; it does not fabricate success.

Workspace notifications, tasks, reminders, and exception indicators may be fed by committed domain events and authoritative queries. They must be idempotent, traceable, retry-safe, and disposable/rebuildable presentation state; an integration or notification failure must not create a hidden workflow authority or preserve unauthorized workspace actions.

---

## HISTORICAL SOURCE: `architecture/16-background-processing-architecture.md`

# Background Processing Architecture

Asynchronous work is limited to post-commit notifications, communication delivery, report projection/generation, scheduled reconciliation, payroll preparation, document processing, backup/restore verification, and integration retries. Ordinary domain commands remain synchronous when the caller needs a definitive decision.

Each job has trigger, durable status, idempotency key, retry limit/backoff, failure/dead-letter state, correlation ID, structured metrics/logs, audit where material, and user-visible status. Jobs consume committed facts and may issue an owner command only with authorization and idempotent reference. Repeated delivery is safe; a failed notification cannot roll back a committed financial or academic fact.

Workspace task, reminder, notification, and exception projections may be built asynchronously from committed authoritative facts. They must expose freshness or failure where relevant, support idempotent rebuild/replay, and never be treated as permission grants or substitutes for current server-side authorization.

---

## HISTORICAL SOURCE: `architecture/17-concurrency-consistency-architecture.md`

# Concurrency and Consistency Architecture

Business invariants are protected at the owner boundary by atomic commit, current-state revalidation, unique/idempotency constraints, and serialized conflict domains—not by vague database reliance.

| Operation | Protected invariant | Mechanism |
|---|---|---|
| payment/allocation/refund | no duplicate/excess amounts | idempotency + per-source serialized commit |
| reconciliation | one period/source observation | unique run/version + approval lock |
| payroll/period close | stable calculation and closed-period immutability | period lock and state check |
| approvals/authority | counted once; no conflicted actor | compare-and-commit and policy recheck |
| enrollment/capacity | no duplicate active seat/over-capacity | membership uniqueness + capacity reservation |
| attendance correction | append-only correction | version/current-state check |
| scope assignment | no overlapping unauthorized effective assignment | dated conflict validation |

Conflict returns a retryable business conflict, never silently merges or overwrites history.

---

## HISTORICAL SOURCE: `architecture/18-error-exception-architecture.md`

# Error and Exception Architecture

Validation failure means malformed/incomplete input; authorization failure means denied authority/scope; business rejection means valid request violates a rule/state; concurrency conflict means state changed and requires retry/review; integration failure means external uncertainty/failure; system failure means infrastructure inability; emergency exception is a specifically authorized, time-limited path.

Responses expose stable business error categories and reference IDs, not stack traces or internal details. Material failures and emergency actions are audited with actor, authority, scope, reason, and outcome. Financial ambiguity is held for reconciliation; no retry may duplicate a transaction. System recovery replays idempotent post-commit work, not business commands without keys.

Workspace failures must fail closed for action access and must distinguish stale, unavailable, incomplete, blocked, and unauthorized work context without fabricating success or hiding an exception. A task or notification delivery failure may degrade presentation, but it cannot approve, post, mutate, or preserve a domain operation.

---

## HISTORICAL SOURCE: `architecture/19-observability-architecture.md`

# Observability Architecture

Structured operational telemetry includes correlation/reference ID, context, operation, outcome, latency, retry, actor class (not unnecessary personal data), and failure category. Distributed tracing follows a command across contexts and integrations. Metrics cover availability, latency, job backlog/failure, authorization denials/anomalies, projection freshness, financial reconciliation variance, and period-close exceptions.

Health checks distinguish process health, dependency health, and business readiness. Audit records are authoritative history; logs and metrics are diagnostic and must not replace audit. Financial alerts route to Finance and remain linked to reconciliation evidence. Sensitive values are redacted and telemetry access is scoped and audited.

Workspace observability must distinguish composition latency, source-query failure, projection freshness, task/notification backlog, stale context, blocked work, and completed canonical commands. Measure employee-efficiency outcomes such as unnecessary steps, time to actionable work, completion time, error/recovery rate, and discoverability without turning telemetry into a second business authority or exposing unnecessary personal data.

---

## HISTORICAL SOURCE: `architecture/20-resilience-security-architecture.md`

# Resilience and Security Architecture

## Resilience

Durable committed facts, financial transactions, audit evidence, configuration versions, and document metadata require backup coverage and restoration verification. Recovery preserves ordering, idempotency, period integrity, and reconciliation evidence. Degraded operation may read cached non-authoritative data but must not accept unsafe financial or authority mutations. RPO, RTO, retention, and disaster priorities remain explicit operational requirements awaiting organization-specific values; no infrastructure vendor is selected.

## Security

Authentication establishes an account identity; authorization is a separate server-side policy boundary. Sessions/tokens are bounded by expiry, revocation, secure transport, and audience; passwords/secrets require protected handling and rotation policy. Privilege escalation is prevented by default deny, server checks, immutable authority history, SoD, and conflict exclusion. Sensitive data is encrypted in transit/at rest according to policy, minimized in logs, and protected by purpose/scope. Exports and integrations use the same authorization, consent, classification, and audit controls.

The Employee Workspace is untrusted presentation and orchestration state. Cached workspace content may be stale, incomplete, or unavailable, and must not be used as authorization. Workspace responses must be minimized to the employee's effective scope, redact sensitive context by purpose, fail closed for actions, and recheck current authority at command time. Lifecycle revocation, branch transfer, delegation expiry, IDOR, replay, direct-write bypass, and cross-employee data leakage require explicit tests.

---

## HISTORICAL SOURCE: `architecture/2026-09-05-unified-platform-reconciliation.md`

# TOEFL House — Unified Platform Reconciliation and Architecture

**Date:** 2026-09-05 (Asia/Kabul)  
**Status:** Architecture decision and implementation plan — accepted as the target architecture; not a production-readiness certification  
**Working branch:** `arena/01a07134-toefl-house`  
**Comparison ref:** `origin/arena/01a03298-toefl-house`  
**System names in this document:** System A is the comparison ref; System B is the current Laravel checkout.

## Executive decision

Neither source system is accepted unchanged.

The unified platform will use a **Laravel modular monolith with PostgreSQL** as the transactional backend and a **React/TypeScript feature frontend** as the operator and student experience. System B supplies the backend boundary discipline, PostgreSQL-oriented relational integrity, command/lifecycle patterns, authorization decisions, provenance controls, and source-linked corrections. System A supplies the broader operational product surface, the placement and academic engines, the financial subledger capabilities, the invariant-audit discipline, the reporting/backup/readiness posture, and the mature React experience.

System A's SQLite schema and route implementation are capability evidence, not the target persistence implementation. System B's existing Laravel schema is also evidence, not a reason to preserve every table or route. The final platform has one owner for each business fact, one write path for each mutation, and query/reporting projections that cannot write domain truth.

The mandatory, non-negotiable connected target architecture is defined by `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md` and ADR `2026-09-05-integrated-system-architecture-graph.md`. This reconciliation must be read as a conformance candidate against that graph, not as proof that the repository already satisfies every graph edge, authority, lifecycle, event, workflow, workspace, document, notification, search, reporting, concurrency, or failure requirement.

The most important authority decisions are:

- **Access:** Access/Identity owns authentication, actor status, permissions, delegations, expiry, and scope decisions.
- **Organization:** Organization owns the hierarchy and branch lifecycle. A branch identifier is never a wildcard when absent or unknown.
- **Student identity:** Students owns the student profile and profile lifecycle; Admissions owns applicant/admission workflow; Academic owns enrollment and delivery state.
- **Placement:** Placement owns assessment content, attempts, scoring, and placement recommendation evidence.
- **Money:** Finance owns obligations, invoices, payments, allocations, journals, corrections, funds, budgets, books, assets, debt, and recorded settlements. Payroll calculates and proposes; Finance records settlement facts.
- **Reporting:** Reporting owns read projections and invariant audits only. It never becomes a second ledger.
- **Integration:** Events and automation carry committed facts through a transactional outbox. They do not own a business state.
- **Lifecycle:** Every stateful aggregate has one transition graph and one guarded command boundary. Coarse UI status is derived, not independently written.

This document deliberately distinguishes **capability preservation** from **implementation preservation**. No production-data shape is treated as a permanent constraint. The current work remains subject to the deferred runtime validation listed at the end.

---

## Evidence, method, and limits

The comparison used source-tree inspection, route/module inventories, schema inspection, and targeted reading of domain services, lifecycle engines, financial invariant checks, authentication bootstrap, and frontend structure. The comparison ref was inspected through `git show` and `git ls-tree`; the working branch was not switched.

System A's own completion documents report 125 tables, 43 mounted route groups, 22 financial invariants, readiness/backup checks, 223 test files, and a broad ERP surface. These are useful claims about intended scope and implementation evidence, not independently certified facts in this phase. The SQLite schema and source were also inspected directly. System B contains a larger Laravel migration chain, modular domains, PostgreSQL-oriented constraints, command classes, API/web controllers, and feature/security/schema tests.

The runtime is intentionally unavailable for this phase. PHP, Composer/vendor dependencies, PostgreSQL, Node dependencies, test runners, migrations, and static analysis were not installed or executed. Therefore this is an architecture and source-reconciliation verdict, not a claim that either implementation or the new migration has passed execution.

Evaluation criteria were:

1. business correctness and invariant strength;
2. security, scope isolation, and separation of duties;
3. immutability, provenance, correction, and auditability;
4. concurrency, idempotency, retries, and partial failure behavior;
5. completeness of the user journey for every defined role;
6. API/controller/console parity;
7. database enforcement and recoverable migrations;
8. performance, reporting, observability, accessibility, and maintainability;
9. ability to evolve without retaining duplicate authorities.

---

## 1. Complete capability map — System A (comparison ref)

### 2.1 Organization, configuration, and operations

System A contains organization, campus, branch, branch-scoped operational records, policy/configuration catalogs, permission catalogs, workflow/rule versions, notifications, audit logging, health/readiness checks, database backup scheduling and verification, search, and export/report infrastructure. It carries branch provenance on most operational rows and has explicit organization-wide versus branch-scoped decisions.

The useful capability is not its SQLite choice. The useful capability is the explicit operational contract: a system should report readiness, verify backups, expose health separately from readiness, and refuse unsupported policy-dependent operations rather than fabricate defaults.

### 2.2 Identity and access

System A implements authenticated sessions, password-change quarantine, session-version revocation, scoped RBAC, permission resolution, role expiry/deny behavior, global-owner handling, branch scope resolution, class-ownership checks, student-level access checks, delegation, and audit middleware. The UI exposes settings, users/roles, workflows, rules, and audit views.

The principal risk is that route-level authorization and raw SQL are spread through a large Express route surface. The final platform must retain the policy semantics but centralize their execution behind the Access decision service and database scope predicates.

### 2.3 Admissions, visitors, and CRM

The product includes visitor/lead capture, applicant intake, duplicate-aware conversion, admission decisions, enrollment conversion, student records, visitor history, communications, and operational dashboards. The experience covers reception and admissions staff rather than presenting a finance-only administration console.

### 2.4 Placement

Placement is a major System A capability: test and content banks, test versions, blueprints, question/rubric content, attempts, answer capture, scoring, recommendations, placement reports, and branch-scoped access. It is designed as a content engine rather than a single hard-coded exam form.

The scoring and recommendation evidence should be preserved. The final system must separate immutable attempt/answer evidence from mutable recommendation display and must prevent a stale or unauthorized attempt from being associated with another student or branch.

### 2.5 Academic administration and delivery

System A covers academic setup, programs/levels, classes, class generation, class lifecycle, schedules, sessions, enrollments, attendance, homework, exams, assessments, grade lock/review/publish/lock workflow, progression, completion, graduation/certificates, teacher/class ownership, waitlists, and student portal views.

Its strongest design ideas are explicit transition graphs, teacher edit limits for locked grades, and a distinction between a coarse UI status and a detailed lifecycle stage. The final version must remove aliases and duplicate state vocabularies rather than carrying every historical spelling indefinitely.

### 2.6 Students and portal

The student experience includes profile and status, enrollment/class view, timetable, attendance, assessment/grade visibility, documents, communications, fee/invoice views, payment history, and a student portal. System A also models student operational eligibility separately from profile status in parts of the finance and student workflows.

This separation is retained: a historical student status is not rewritten merely to gate a new delivery, billing, or document operation.

### 2.7 Finance and economics

System A has the broadest economic surface discovered in the comparison:

- invoices and line items with explicit purpose;
- tuition obligations and term position;
- payments and payment allocation;
- overpayment/underpayment handling, refunds, discounts, credits, installment plans, and write-offs;
- income/expense classification, budgets, budget movements, branches, and savings/accounts;
- journals and financial transactions;
- books inventory, stock receipts, sales, and acquisition evidence;
- supplier invoices, payables, returns, and refunds;
- loans, principal repayment, interest facts, and positions;
- assets, custody, depreciation, disposal, and portfolio reporting;
- funding, donations, scholarships, sponsorships, restricted funds, impact, and clawback obligations;
- payroll calculations, clearances, withholdings, settlements, teacher-related financial calculations;
- bank reconciliation, aging, daily cash statements, P&L/operating reporting, and invariant checks.

The strongest financial ideas are the independent invariant checker, purpose-specific invoices, obligation-keyed payment allocation, explicit non-operating classifications, evidence identities for subledgers, and refusal of unrepresentable policy choices. The final system adopts those capabilities but moves them into PostgreSQL Finance commands and append-only facts.

A material architectural risk remains: the source implementation maintains some operational account balances alongside financial transaction evidence. Conditional updates and invariant checks reduce risk, but a balance cache is not itself historical truth. The final system treats balances as projections rebuilt from the journal/subledgers and proves them with reconciliation, rather than allowing independent monetary truths.

### 2.8 HR and payroll

System A includes employee/teacher records, employment contracts and versions, payroll periods, calculations, approvals, clearances, adjustments/reversals, teacher history, and settlement workflows. It also models withholding and reporting gates.

The calculation/proposal versus recorded-money distinction is retained. A Payroll route must not be able to create a Finance settlement merely because it has a route named `settle`.

### 2.9 Reporting, automation, and governance

System A includes dashboards, financial reports, operational reports, impact views, exports, workflow/rule versions, automation triggers/actions, event bus behavior, notifications, audit views, and extensive forensic/static/mutation tests.

The final system retains transactional outbox events, versioned rules, idempotent automation, audit correlation, and read-only reports. An in-memory event bus is not sufficient for a production commit boundary and will not be the final integration authority.

### 2.10 Frontend capability

System A has the more complete product interface: React 19/Vite pages for dashboard, finance, books, funding, impact, academic setup, classes, students, visitors, teachers, reports, rules, workflows, audit, settings, and student portal. It provides a credible role-oriented ERP experience instead of exposing only API/admin forms.

The final frontend preserves this breadth, but routes all mutations through generated contracts and server decisions. Client-side hiding is never treated as authorization.

---

## 2. Complete capability map — System B (current Laravel checkout)

### 3.1 Foundation and domain structure

System B is a Laravel/PHP modular monolith with modules for Academic, Access, Admissions, Audit, Calendar, Communication, CRM, Documents, Finance, Governance, HR, Identity, Integrations, Organization, Payroll, Privacy, Reporting, Resources, and Students. It uses migrations, models, domain commands, queries, lifecycle classes, API controllers, web controllers, and feature/security/schema tests.

Its main strength is explicit bounded-context structure: behavior is easier to locate and transactions can be made local to a domain command. Its main product gap versus System A is less complete operational UI and fewer broad subledger capabilities.

### 3.2 Identity, access, organization, privacy, and audit

System B has actor/person identity, access resolution, actor/branch structures, scoped permissions and delegations, organization structures and lifecycle, privacy controls, audit recording, idempotent execution, and branch-aware authorization. It has explicit work on stale permissions, inactive actors, scope failures, direct route bypasses, and denied-operation recording.

The current architecture still requires a single final policy evaluator and a complete inventory proving every controller, route, command, query, and job uses it. A policy object existing in the module is not proof that every writer is guarded.

### 3.3 Admissions and students

System B supports applicant registration, admission decisions, admitted-applicant enrollment, student profiles, guardians, communications preferences, student status transitions, branch transfers, holds, and operational eligibility. Student profile lifecycle and enrollment/delivery lifecycle are modeled separately.

This is a sound foundation. The unified design expands it with System A's reception/visitor pipeline, portal experience, duplicate-aware intake, document flows, and placement conversion.

### 3.4 Academic and calendar

System B has academic access rules, skill/assessment handling, attendance, enrollment maintenance, progression decisions, class waitlists, class/period and calendar structures, branch records, and lifecycle guards. Its current transition work distinguishes detailed state from coarse status and prevents invalid terminal transitions.

System A has the more complete teacher/class/exam/grade product surface. The final design keeps System B's command and transition discipline while importing System A's assessment, grade-lock, class session, teacher ownership, and student portal capabilities.

### 3.5 Documents and communication

System B has documents and communication modules with privacy and student-facing concerns. System A provides broader portal and operational exposure. The unified version uses one document metadata/authorization authority with object-level branch/student checks, private storage, malware/content validation, signed short-lived download grants, and an audit trail for every access decision.

### 3.6 Finance

System B currently has Finance commands and models for obligations, payment allocation, payments, funds, discounts, credits, installment plans, journals, financial gates, reporting queries, corrections, and a Finance-owned employment settlement. It uses PostgreSQL-oriented foreign keys, numeric monetary columns, database triggers, open financial periods, source-linked compensating corrections, idempotency, and branch provenance.

The current work also separates Payroll settlement proposals from Finance settlement recording. The remaining architectural defect identified during reconciliation was the presence of the old Payroll `final_settlements` authority. The new consolidation migration removes that table and model from the final runtime schema instead of preserving it as a compatibility writer or projection.

System B does not yet contain System A's complete books, supplier debt, loans, asset lifecycle, funding/impact, bank matching, or rich invoice-purpose experience. These are selected capabilities to be implemented inside Finance, not copied as a second Express finance system.

### 3.7 HR and payroll

System B has employment lifecycle, payroll calculations/results/adjustments, payroll periods, clearances, settlement proposals, and API/controller workflows. Payroll has stronger separation from Finance after the current reconciliation work, but route parity and all direct-write paths remain to be verified at runtime.

Payroll remains the calculation and proposal authority. Finance records the settlement fact and all cash/journal consequences.

### 3.8 Reporting, APIs, web UI, and tests

System B has API and web route surfaces, finance/student/placement/payroll controllers, Blade views, feature tests, schema tests, security tests, and direct SQL attack tests. It has useful adversarial coverage but not the complete System A operator/student frontend.

The final system will expose one command contract through API and console/web adapters. Controllers will not contain alternative business rules. Reports will call query objects/projections and will never write balances or lifecycle state.

---

## 3. Capability-by-capability reconciliation

| Capability | System A contribution | System B contribution | Final decision and authority |
|---|---|---|---|
| Identity | Sessions, revocation, password quarantine, role/permission catalog | Modular Identity/Access, actor status, idempotency, audited decisions | **Combine and centralize.** Access/Identity owns authentication and authorization; no route-local RBAC. |
| Organization/branch | Organization-campus-branch hierarchy and broad branch-carrying schema | PostgreSQL structure lifecycle, branch provenance, explicit scopes | **Choose B foundation; add A hierarchy/read models.** Unknown/null branch scope fails closed. |
| Admissions/reception | Visitors, leads, duplicate-aware intake, conversion | Applicant/admission/enrollment commands | **Combine.** Admissions owns applicant state; Students owns created profile. |
| Placement | Content engine, attempts, rubrics, scoring, recommendations | Placement domain boundary and access checks | **Combine.** Placement owns evidence and recommendation; no duplicate student placement state. |
| Student profile | Portal and operational views | Profile lifecycle, guardians, holds, branch transfer, operational eligibility | **Choose B domain truth; import A UX.** Profile status is not billing/delivery eligibility. |
| Calendar/classes | Rich class/session/schedule frontend and lifecycle | Calendar/domain command structure and guards | **Combine.** Calendar owns schedule; Academic owns class/enrollment state. |
| Progression/grades | Detailed academic workflow and grade locks | Progression commands and lifecycle checks | **Combine.** One grade-lock transition graph; published/locked records are append-only or compensated. |
| Documents | Student-facing document experience | Documents/privacy module | **Combine.** Documents owns metadata and secure object access. |
| Finance obligations | Invoice purpose, tuition term position, aid, installments, write-offs | PostgreSQL obligations, payment allocations, corrections, periods | **Redesign on B storage.** Finance owns one obligation and one allocation position. |
| Payments/refunds | Payment channels, refunds, payer detail, overpayment/return controls | Idempotent command patterns and database guards | **Combine.** Payments are immutable facts; refunds/corrections append compensating facts. |
| Journals/ledger | Broad classifications, reports, invariant checker | Journal command, period/provenance/immutability controls | **Choose B transaction boundary and A invariant catalog.** One monetary authority. |
| Books/inventory | Books, stock receipts, sales, acquisition/reconciliation | No equivalent complete surface | **Import and redesign in Finance/Resources.** Inventory is not a cash ledger. |
| Suppliers/payables | Supplier invoices, returns, refunds, payables | No equivalent complete surface | **Import into Finance.** Every payable and cash event is source-linked. |
| Loans | Principal, interest evidence, repayment, positions | No equivalent complete surface | **Import only after policy gates.** Principal and interest are separate facts; no invented rates. |
| Assets | Custody, lifecycle, depreciation, disposal | No equivalent complete surface | **Import into Resources/Finance.** Custody is operational; depreciation/disposal are Finance facts. |
| Funding/impact | Donations, aid, sponsorship, restricted funds, clawbacks, impact | Funds and allocations foundation | **Combine.** Funding source and student obligation allocation remain distinct. |
| Payroll | Calculation, withholdings, clearances, settlement evidence | Employment lifecycle and Finance-owned settlement boundary | **Combine with strict ownership.** Payroll proposes; Finance records. |
| Reporting | Rich reports, aging, reconciliation, invariant checker | Query objects and audit/reporting module | **Combine.** Reports are projections; invariant auditor reads raw facts independently. |
| Automation/events | Rules, workflows, event bus, notifications | Integrations/audit/idempotency foundations | **Redesign around transactional outbox.** Automation cannot mutate facts without commands. |
| Audit/observability | Audit coverage, readiness, backup, health | Audit module and denied-operation evidence | **Combine.** Add correlation, metric, trace, backup-restore evidence. |
| Frontend | Broad React operator/student product | Laravel web/API and domain contracts | **Choose React/TypeScript experience; B remains backend authority.** |
| Database | Broad capability schema, triggers, SQLite WAL | PostgreSQL migrations, commands, relational constraints | **Choose PostgreSQL.** Port capabilities, not SQLite tables. |

---

## 4. Best capabilities selected from each system

### From System A

1. The role-complete React experience for reception, admissions, teachers, management, finance, operations, and students.
2. Placement content/version/attempt/rubric/scoring capability.
3. Explicit class, enrollment, attendance, assessment, progression, grade-lock, and portal product flows.
4. Invoice purpose and obligation settlement semantics that refuse ambiguous documents.
5. Broad Finance subledgers: books, suppliers, loans, assets, funding, aid, impact, and clawbacks.
6. An independent invariant checker that does not trust the same projection code used by request handlers.
7. Health/readiness separation, backup verification, operational dashboards, and honest `POLICY REQUIRED` refusals.
8. Versioned rules/workflows and automation as configurable operational capabilities.
9. Detailed financial reporting, aging, bank reconciliation, and evidence-oriented subledger positions.

### From System B

1. Laravel module boundaries and domain command/query separation.
2. PostgreSQL as the transactional financial database.
3. Migration-based evolution with relational foreign keys, numeric money, row locks, and database constraints.
4. Explicit access decisions, branch scope objects, delegation/expiry/inactive-actor handling, and denial audit.
5. Append-only financial facts, source-linked compensating corrections, open-period controls, and idempotent commands.
6. Separate Payroll calculation/proposal from Finance settlement recording.
7. Student operational eligibility without rewriting historical profile state.
8. API/console parity as a domain requirement rather than separate controller behavior.
9. Lifecycle engines and guarded commands for students, classes, enrollments, progression, and finance instruments.
10. Schema/security/direct-SQL tests as executable specifications.

---

## 5. Capabilities redesigned from scratch

The following are not copied from either implementation:

- **Canonical identity and scope contract:** one AccessDecision result containing actor status, effective permissions, valid time, organization scope, branch scope, and denial reason; all commands consume it.
- **Finance fact model:** one append-only journal and source-linked subledger facts with projections rebuilt or reconciled from those facts. No writable balance table can be an independent monetary truth.
- **Correction model:** typed compensating instruments tied to exactly one source fact, with independent approval, open-period checks, remaining-correctable amount, idempotency, and immutable recorded state.
- **Unified lifecycle kernel:** one transition primitive with explicit graph, prerequisites, actor capability, idempotency, transition event, audit, and database guard. No controller-specific state machine.
- **Transactional integration:** domain fact and outbox message commit atomically; delivery is retryable and idempotent; automation invokes commands and cannot write tables directly.
- **Frontend contract layer:** generated API types and capability-aware screens from one contract. A disabled control is a usability aid only; the server always decides.
- **Reporting semantic layer:** report definitions map to approved query objects and include scope/provenance metadata, period basis, as-of time, and reconciliation status.
- **Secure document access:** metadata authorization is separate from object storage; download grants are short-lived and audit-correlated.
- **Operational readiness:** health, readiness, migration state, backup freshness/restore evidence, invariant status, queue/outbox lag, and dependency status are separate signals.
- **Policy gate registry:** unsupported economic policies are represented as explicit refused capabilities with required owner decisions, never hidden defaults.

---

## 6. Capabilities intentionally removed

These are removed from the final design, not merely hidden:

1. Payroll-owned `final_settlements` storage as a competing monetary fact. The final schema has only Finance `employment_settlements`; the old model is deleted and migration `2026_09_05_000143_consolidate_employment_settlement_authority.php` removes the duplicate table after replacing its proposal guard.
2. Controller-local financial calculations that disagree with Finance queries.
3. Writable report/dashboard balances that can diverge from financial facts.
4. Null/unknown branch scope treated as organization-wide access.
5. Implicit invoice purpose, implicit tuition term, silent quantity/default/discount substitution, and ambiguous payment allocation.
6. Direct writes from automation, imports, seeders, or API handlers that bypass domain commands.
7. In-memory events as the only record of a business event.
8. Duplicate aliases for the same lifecycle state once the canonical vocabulary is migrated and the API compatibility window ends.
9. SQLite as the production system of record for multi-user financial operations.
10. “Legacy”, “temporary”, or “compatibility” models that remain writable or silently preserve a second authority. Historical migration files may describe the migration path, but runtime code must not expose the retired concept.

---

## 7. Duplicate authorities eliminated

| Concept | Retired competing path | Final authority |
|---|---|---|
| Employment settlement | Payroll `FinalSettlement`/`final_settlements` | Finance `EmploymentSettlement` |
| Settlement approval | A single request carrying both identities | Payroll proposal plus authenticated Finance approval |
| Student operational eligibility | Rewriting `students.status` to gate every operation | `StudentOperationalEligibility` policy plus specific lifecycle state |
| Branch scope | Controller fallback or absent branch interpreted globally | Access/Organization scope decision; unknown fails closed |
| Financial correction | Destructive edits or generic adjustment rows | Typed Finance compensating correction linked to source |
| Payment allocation | Manual balance subtraction and invoice-local assumptions | Finance allocation and obligation-position authority |
| Coarse class status | Independent status writes | Derived from canonical class lifecycle stage |
| Grade editability | Frontend role checks | Grade-lock lifecycle and server authorization |
| Reports | Per-controller SQL totals | Reporting query objects over Finance facts |
| Event processing | Direct automation mutation | Outbox delivery invoking an authorized command |

The settlement consolidation is implemented in the working tree as a migration and model removal. Other rows are the target architecture and require the remaining work listed below.

---

## 8. Final architecture

### 9.1 Topology

The first production topology is a modular Laravel application with:

- PostgreSQL primary database;
- stateless HTTP/API workers;
- queue workers for outbox delivery, notifications, report generation, and document processing;
- object storage for private documents and verified backups;
- Redis or equivalent for queues, short-lived locks, rate limiting, and cache only;
- React/TypeScript frontend served separately or through the web edge;
- an Employee Workspace composition layer deriving effective work context, tasks, approvals, deadlines, notifications, and exceptions from canonical authorities;
- centralized logs, metrics, traces, audit storage, and alerting.

The application may begin as a modular monolith. Module boundaries are enforced in code review, dependency rules, namespaces, route registration, and architecture tests. Splitting a module into a service is deferred until measured load or team ownership justifies the operational cost.

### 9.2 Request path

`HTTP/API/console adapter -> authentication -> AccessDecision -> command/query -> domain transaction -> PostgreSQL facts/projections/outbox -> audit/metrics -> response`.

A controller may normalize transport input and render a response. It may not own a business invariant, calculate a financial balance, or directly update a domain table.

### 9.3 Data path

Financial writes use a transaction that locks the source aggregate and relevant position rows, validates the invariant, appends the fact, updates only derived projections, records audit/outbox evidence, and commits. Retry keys bind the operation name and canonical payload. A replay with a different payload is rejected.

---

## 9. Final domain and module boundaries

- **Identity:** people, credentials, authentication sessions, actor lifecycle, password/session invalidation.
- **Access:** roles, capabilities, scope grants, delegations, expiry, deny rules, decision explanations.
- **Organization:** organization/campus/branch hierarchy, lifecycle, current scope, cross-branch legitimate operations.
- **Admissions/CRM:** visitor, lead, applicant, duplicate review, admission decision, conversion.
- **Students:** profile, guardians, preferences, documents relationship, student status, portal identity.
- **Placement:** content, blueprint, attempt, answers, scoring, recommendation evidence.
- **Calendar:** terms, periods, holidays, rooms, schedule slots, session time.
- **Academic:** programs, classes, teachers, enrollment, attendance, assessments, grades, progression, completion.
- **Documents/Privacy:** document metadata, classification, retention, access grants, private storage, privacy requests.
- **HR:** person employment, contracts, employment lifecycle, clearances, employee eligibility.
- **Payroll:** periods, calculations, adjustments, proposals, payslip/withholding evidence; no recorded settlement cash fact.
- **Finance:** obligations, invoices, payments, allocations, refunds, credits, funds, budgets, journal, corrections, suppliers, loans, assets, books, funding, and recorded settlements.
- **Reporting:** read models, reports, exports, invariant auditor, reconciliation status.
- **Communication:** templates, consent, delivery attempts, notifications; never a domain state owner.
- **Integrations:** outbox, inbound idempotency, external reference mapping, retries, dead-letter review.
- **Governance/Audit:** policy decisions, audit records, attempted operations, retention, release evidence.
- **Resources/Operations:** rooms, equipment, inventory custody, backup/readiness/health.

Module dependencies point inward toward shared primitives and explicit contracts. Finance may read HR/Academic source facts through query contracts but owns its recorded money facts. Reporting may read all approved projections but may write none.

---

## 10. Final frontend architecture and rationale

React/TypeScript is selected because System A already demonstrates the broadest product experience and because dynamically composed, role-aware but not role-locked responsive workflows are easier to compose as feature slices than as a growing set of server-rendered administrative pages. The final frontend will be a single product shell with feature modules. Its primary employee experience is the first-class Employee Workspace, which composes authorized work across these modules rather than replacing them with static role dashboards:

- reception/admissions;
- placement;
- academic/classroom/teacher;
- students/portal;
- documents/communications;
- finance/payroll;
- reporting/management;
- operations/settings/audit.

Rules:

1. API contracts are generated or type-checked from the backend contract; no hand-maintained duplicate DTO truth.
2. Server state uses query caching with explicit invalidation after committed commands; local state is limited to drafts and view state.
3. Permission/capability checks improve navigation and accessibility but never replace server authorization.
4. Every mutation shows idempotency/retry-safe behavior, conflict/stale-state messaging, and a meaningful audit/reference result.
5. Finance screens display source, period, branch, status, correction history, and reconciliation state, not only a number.
6. Keyboard navigation, visible focus, labels, contrast, responsive tables/cards, screen-reader status, and mobile reception/teacher workflows are acceptance criteria.
7. Student portal views expose only object-authorized records and never infer branch access from a client-provided identifier.

System A's visual/page surface is preserved as a product baseline, but its route-local assumptions and SQLite-specific data access are not.

### Employee Workspace acceptance

The Employee Workspace is a first-class operational work environment, not a generic dashboard. At request time it composes effective employee identity, employment eligibility, all current positions, assignments, capabilities, organizational/branch scope, lifecycle state, assigned work, approvals, deadlines, notifications, authoritative context, and exceptions. It is work-first and exception-first, supports management-specific decision workspaces, and gives direct routes to canonical workflows.

A multi-position employee is not statically role-locked: the workspace may expose several legitimate work areas, while each action is independently authorized and scope-checked on the server. Personalization changes layout, shortcuts, filters, and reminders only; it cannot grant authority, alter financial truth, bypass lifecycle rules, or create a shadow task/approval/business authority. Workspace state is rebuildable and freshness/failure is visible where relevant.

### Role experience acceptance map

| Role | Complete experience required | Hard boundary |
|---|---|---|
| Administrator | organization, branches, users/roles, policy/rules, audit, readiness, configuration | cannot use a generic admin role to bypass Finance SoD or object scope |
| Reception | visitor/lead intake, applicant registration, duplicate review, placement booking, enrollment, receipts, document capture | cannot approve own admission/discount/refund or view another branch without explicit scope |
| Teacher | assigned classes, calendar, sessions, attendance, homework, assessments, grade submission/review status, student communication | cannot edit published/locked grades, payroll, invoices, or unassigned classes |
| Management | cross-branch approved dashboards, KPIs, staffing/class capacity, reports, controlled approvals | cross-branch views are explicit and marked as organization scope; no direct fact mutation through reports |
| Finance | invoices/purposes, obligations, collections, allocation, refunds, corrections, periods, journals, books, debt, assets, funding, payroll settlement approval, reconciliations | sole monetary writer; independent approval and source-linked history are mandatory |
| Student | application status, placement attempt, timetable, class, attendance, grades, documents, invoices, payment history, communication preferences | sees only own authorized objects; cannot infer or alter scope from IDs or client state |
| Operations | rooms/resources, books/inventory custody, documents, notifications, queues, backups/readiness, incident controls | operational projections cannot silently create financial or academic facts |

Responsive and accessible behavior is part of acceptance: keyboard-complete workflows, visible focus, labels, screen-reader state announcements, high contrast, no color-only status meaning, usable tables/cards at mobile widths, safe retry/conflict messages, and no destructive action hidden behind an ambiguous button.

---

## 11. Final backend architecture and rationale

Laravel modular monolith is selected over preserving the Express route implementation because System B already provides the stronger transactional/domain boundary and PostgreSQL-oriented migration model, while System A's breadth can be ported as bounded capabilities. TypeScript remains the frontend contract language; it is not necessary to maintain two independent backend implementations.

Backend rules:

- commands own mutations and enforce invariants;
- queries own projections and are read-only;
- policies/AccessDecision own authorization;
- repositories/adapters isolate persistence details where needed;
- controllers, console commands, jobs, seeders, imports, and listeners call the same application commands;
- all money is represented as fixed-scale decimal/validated value objects, never binary floating point;
- all external commands accept an idempotency key where a retry could create a fact;
- transaction boundaries are explicit and documented;
- outbox records commit with the fact;
- exceptions map to stable problem codes without leaking sensitive existence information;
- logging contains correlation IDs and safe identifiers but not credentials or unnecessary personal data.

System A's large route files are treated as a capability inventory. They are not copied as a second backend or allowed to reintroduce raw SQL business rules.

---

## 12. Final database architecture and rationale

PostgreSQL is the production database. It provides transactional row locking, numeric precision, foreign keys, partial/conditional indexes, exclusion/uniqueness constraints, trigger-level enforcement where justified, explainable query plans, mature backup/replication tooling, and safer multi-user concurrency than a single SQLite file.

The database design separates:

- **facts:** immutable or controlled lifecycle rows that state what happened;
- **workflow evidence:** proposals, approvals, clearances, and attempts;
- **projections:** balances, dashboard totals, search indexes, and report materializations;
- **audit/outbox:** evidence of who/what/when and delivery state.

Money facts are source-linked and append-only after recording. Corrections are new rows with direction and source identity. A database trigger is used for non-negotiable invariants that must survive direct SQL; it is not a substitute for the command/policy layer. All trigger rules have a matching adversarial specification.

Migration policy:

- forward migrations are versioned and reversible where safe;
- destructive consolidation requires an explicit preflight, backup, reconciliation, and deployment gate;
- migration rollback never silently fabricates financial history;
- schema and code deploy compatibility is documented;
- the final schema must not contain retired runtime authorities;
- a clean-schema build and an upgrade-path build are both required before release.

The System A SQLite `schema.sql` is mined for capability and constraint requirements. It is not copied into production and is not treated as a migration system.

---

## 13. Final security architecture

### Authorization and scope

Every protected operation evaluates authenticated actor status, capability, organization scope, branch scope, object ownership, lifecycle state, and delegation validity. Unknown actor, inactive employment, expired grant, missing branch provenance, malformed scope, or stale session fails closed. Organization-wide access is an explicit capability, not the result of `NULL`.

### Separation of duties

Preparation, approval, beneficiary, posting, refund, correction, and settlement roles are distinct where the policy requires them. The server derives the actor identity from the authenticated session. A request body cannot nominate a second identity to satisfy SoD.

### Threats explicitly covered

- IDOR across student, invoice, document, class, branch, report, and audit identifiers;
- privilege escalation via stale roles, expired delegation, inactive employment, role mutation, or session replay;
- branch/null-scope fail-open and cross-branch reporting;
- API versus web/controller authorization drift;
- direct SQL writes bypassing application checks;
- duplicate/replayed payment, refund, correction, payroll, and settlement requests;
- concurrent allocation, settlement, approval, and balance races;
- lifecycle bypasses such as graduated/suspended student billing or locked-grade mutation;
- unsafe file access, path traversal, MIME confusion, and unbounded downloads;
- automation/import/seed paths bypassing commands;
- audit tampering and missing attempted-denial evidence;
- backup exposure and sensitive log leakage.

### Controls

Use secure password/session handling, CSRF protection for browser mutations, rate limits for authentication and payment endpoints, parameterized queries, validated uploads, private object storage, signed short-lived access, encryption in transit/at rest, secret management, dependency scanning, security headers, audit correlation, and least-privilege database roles. A database role used by the application must not be able to casually disable audit/immutability triggers.

---

## 14. Final financial architecture

Finance is the sole monetary authority.

### Canonical model

1. A charge becomes an obligation with source, student/organization, branch provenance, period, amount, purpose, and lifecycle.
2. An invoice is a document over an explicitly declared purpose; a tuition invoice names one term/obligation.
3. A payment is an immutable cash fact with idempotency identity, channel, payer detail where known, source branch, and date.
4. Allocation links a payment to an obligation or permitted non-obligation purpose. It cannot exceed payment, source capacity, or obligation position.
5. Journal/subledger facts capture economic classification. Operational balances are projections.
6. Refunds, write-offs, aid reversals, allocation reversals, settlement corrections, and clawbacks append compensating evidence linked to their source.
7. Reports calculate positions from facts and expose reconciliation status.

### Settlement

Payroll calculates a settlement proposal with amount, basis, employment, preparer, and supporting clearances. Finance independently approves and records one `employment_settlements` fact after locking the proposal/employment and verifying termination, branch provenance, active status, independent actors, and idempotency. The old Payroll settlement table/model has been removed from the target runtime schema.

### Concurrency and failure

Lock the source obligation/payment/settlement aggregate in a deterministic order. Use unique idempotency keys, unique source constraints, conditional remaining-capacity checks, and serializable or appropriately locked transactions. If an outbox insert fails, the fact transaction rolls back. If external delivery fails, the fact remains and delivery retries. A partial report read never writes a compensating adjustment.

### Policy gates

Cash drawer variance, supplier credit policy, loan/interest policy, write-off authority, withholding, FX, capital return, restricted-fund enforcement level, and depreciation policy remain explicit owner decisions until defined. The system refuses those operations with a named policy code rather than inventing a classification.

---

## 15. Final lifecycle architecture

All transitions follow:

`current state -> command -> actor/scope check -> prerequisite check -> row lock -> transition graph -> append transition/audit/outbox -> derived projection -> commit`.

Canonical lifecycle families:

- applicant: captured -> under_review -> admitted/rejected/withdrawn;
- student profile: active/inactive/suspended/graduated, with graduation terminal and resume workflow for suspension;
- class: draft -> scheduled -> enrollment_open/closed -> activated -> in_progress/suspended -> grading -> completed -> archived/cancelled;
- enrollment: pending/reserved/confirmed -> active -> frozen/transfer/drop/withdraw/complete -> graduate/retake;
- grade assessment: draft -> submitted -> reviewed -> approved -> published -> locked, with a separate gated unlock/correction path;
- financial period: open -> closing -> closed, with no uncontrolled post-close facts;
- correction: proposed -> approved/recorded, then immutable;
- settlement proposal: proposed -> approved; Finance recording is a separate authenticated command;
- employment: active/suspended -> terminated, with settlement prerequisites after termination;
- document: draft -> verified -> published/expired/withdrawn, while stored bytes remain protected and audited.

Terminal states cannot be reopened by generic update endpoints. A state that needs a reversal has a named compensating workflow with its own permission, reason, approval, and audit evidence. Display status fields are projections of the canonical state.

---

## 16. ADRs created or superseded

1. **Created:** this document, `2026-09-05-unified-platform-reconciliation.md`, establishes the third architecture and supersedes any assumption that either source system should be mechanically preserved.
2. **Superseded in part:** `docs/architecture/decisions/2026-09-05-production-authority-and-correction.md` remains authoritative for provenance and correction semantics, but its historical Payroll compatibility approach is superseded by settlement consolidation migration `000143`.
3. **Retained and extended:** existing access, lifecycle, finance-period, idempotency, audit, and branch-isolation decisions remain valid only where they agree with the one-authority rules in this document.
4. **Required next ADRs:** PostgreSQL deployment/backup, React contract/versioning, outbox delivery, document storage/security, supplier/loan/FX/withholding policy gates, and migration cutover/reconciliation.

Existing decisions are not silently deleted. When a later decision changes an authority, the superseding document names the replaced authority and the runtime migration removes it.

---

## 17. Major refactors performed in this reconciliation

The current working tree contains a broad prior hardening wave covering authorization, branch scope, lifecycle, finance provenance/corrections, payroll settlement proposal/Finance recording, reporting, and UI/controller parity. The changes relevant to this reconciliation include:

- Finance employment settlement command/model and database enforcement were added as the recorded settlement authority.
- Finance correction types, source shape, open-period and immutable-recording guards were added.
- Branch provenance and fail-closed access behavior were strengthened across the affected controllers and domain commands.
- Student operational eligibility was separated from historical student profile status.
- Payroll proposal/settlement paths were aligned toward Finance ownership.
- The failed assumption that a read-only Payroll compatibility model was an acceptable final architecture was corrected: `FinalSettlement.php` is removed, and migration `000143` drops `final_settlements` after replacing the settlement-proposal guard with the Finance table.
- Settlement approval was moved to explicit Finance web/API routes and `MaintainEmploymentSettlement`; Payroll now owns only clearance and proposal preparation.
- The architecture and capability reconciliation is recorded here rather than hidden in implementation comments.

These changes have not been executed against a runtime in this phase.

---

## 18. Dead and duplicate code removed or scheduled for removal

Removed now:

- Payroll `FinalSettlement` model as a runtime authority;
- the final-schema `final_settlements` table through migration `000143`;
- the old proposal guard dependency on that table through the same migration.

Scheduled for removal during implementation waves:

- duplicate Express/SQLite backend route implementations from the product runtime;
- controller-local finance totals and direct financial table writes;
- unreferenced lifecycle aliases after API compatibility migration;
- duplicate permission catalogs and route-specific role checks;
- report code that writes or “repairs” financial facts;
- direct automation/import/seed mutations that do not call commands.

Migration history may retain the old creation migration as historical deployment context. That does not make its table a final runtime authority.

---

## 19. New architectural risks discovered

1. **Integration scope:** React System A screens cannot be connected safely by copying endpoints; a typed contract and semantic mapping are required.
2. **Schema breadth:** importing books/assets/loans/funding into System B without a bounded subledger model could recreate a finance monolith with hidden duplicate balances.
3. **SQLite assumptions:** System A's WAL/busy-timeout behavior is not an adequate substitute for PostgreSQL deployment, failover, backup, and multi-worker testing.
4. **Migration cutover:** dropping the old settlement table is safe for the declared clean-schema redesign, but a real deployment with historical rows needs an export/reconciliation gate before `000143`.
5. **Policy incompleteness:** unrecorded owner policy for cash variance, withholding, FX, loan interest, write-off, depreciation, and restricted funds can tempt future developers to invent classifications.
6. **Scope drift:** legitimate organization-wide operations could be incorrectly narrowed if every payroll/report query is forced into a branch without an ownership decision.
7. **Projection trust:** dashboard/cache/account balances can silently diverge unless rebuild and reconciliation are first-class operations.
8. **Outbox correctness:** retries, dead letters, and poison messages need operational ownership; an event table alone does not guarantee delivery.
9. **Document security:** database row authorization and object-store authorization must be tested together.
10. **Review independence:** same-agent or same-team tests are not a substitute for independent security/financial review.
11. **Performance:** PostgreSQL row locks and invariant queries need indexes and measured contention tests once dependencies are available.
12. **Accessibility/product parity:** System A's broad UI is a baseline, not proof that every workflow is usable at mobile widths or with assistive technology.
13. **Workspace authority drift:** a cross-module work environment could become a shadow authority if it stores independent tasks, approvals, balances, permissions, or lifecycle state; composition, command-time authorization, rebuildability, and owner links must be enforced together.

---

## 20. Remaining implementation work

### Foundation

- finalize the PostgreSQL target schema and bounded subledger tables;
- add contract versioning/OpenAPI or an equivalent typed API boundary;
- create the transactional outbox and delivery/retry/dead-letter operations;
- implement migration preflight, backup, restore, and reconciliation gates;
- complete a route/controller/command/query writer inventory.

### Product capabilities

- port the React System A feature slices to the unified API;
- implement visitor/CRM, placement content/attempts, teacher/class/session, grade lock, portal, books, suppliers, loans, assets, funding, impact, bank reconciliation, and advanced reports within the final module boundaries;
- implement secure document storage/access and communication delivery;
- implement the first-class Employee Workspace and management workspaces with work-first/exception-first composition, canonical workflow links, responsive/mobile workflows, API/web parity, and measurable employee-efficiency acceptance.

### Security and correctness

- finish API/console/web parity for every command;
- prove branch isolation for every object and report;
- complete stale permission, inactive employee, delegation, replay, IDOR, direct SQL, race, and rollback test matrices;
- add database guards where application-only rules would be bypassable;
- define and register unresolved owner policies before enabling their commands.

### Operations

- backup/restore drills, readiness dependency checks, metrics/alerts, queue/outbox monitoring, log redaction, incident runbooks, and disaster recovery objectives;
- performance plans for obligations, payment allocation, reports, invariant checks, and branch-scoped dashboards;
- independent security and financial review.

---

## 21. Tests created or updated, and tests deliberately not executed

The working tree includes or updates feature, payroll, schema-invariant, direct-SQL attack, controller, branch-scope, placement, student-lifecycle, finance, and settlement workflow specifications from the current hardening wave. The settlement consolidation requires additional specifications for:

- absence of the Payroll settlement model/table in the final schema;
- proposal insertion and approval after the Finance table is authoritative;
- direct SQL attempts against the removed table/model path;
- Finance settlement idempotency and concurrent duplicate approval;
- historical migration preflight behavior when old settlement rows exist;
- source-linked compensating settlement corrections;
- API, web, and console parity;
- Employee Workspace composition for multi-position employees, effective employment/lifecycle changes, scope/capability changes, management-specific context, task/deadline/approval/exception prioritization, personalization isolation, projection rebuild/freshness, and command-time reauthorization.

These tests are executable specifications only at this stage. No PHPUnit, PHPStan, Pint, migration, PostgreSQL, Node, Vitest, or frontend test command was run. The absence of execution is intentional and must not be reported as a pass.

---

## 22. Work deliberately deferred to final runtime validation

The following are explicitly deferred:

1. PHP syntax/autoload/container resolution and Laravel boot;
2. migration application on a clean PostgreSQL database;
3. migration upgrade/cutover with representative historical data;
4. migration rollback and restore behavior;
5. PostgreSQL trigger ordering, locking, isolation, and concurrent races;
6. API/web/console authorization behavior in a running server;
7. React build, generated contract alignment, browser navigation, and accessibility checks;
8. dependency vulnerability and license scans;
9. queue/outbox retry and crash recovery;
10. backup restore and readiness failure drills;
11. performance/query plans under realistic branch, student, finance, and report volumes;
12. independent security, financial, and operational review;
13. Employee Workspace runtime composition, API/web parity, freshness, lifecycle revocation, scope isolation, concurrency, accessibility, usability, and employee-efficiency validation.

**Verdict:** the repository now has a documented third architecture and an explicit authority consolidation, but it is **not certified production-ready**. The architecture is suitable for implementation; production readiness remains blocked on runtime execution, migration/recovery evidence, complete capability implementation including the Employee Workspace, and independent review.

---

## HISTORICAL SOURCE: `architecture/21-testing-architecture.md`

# Testing Architecture

Future tests are specified, not implemented here. Domain rule and invariant tests cover state transitions, financial amount/source rules, academic evidence/decision separation, payroll separation, privacy, and historical immutability. Authorization/scope tests cover default deny, hierarchy, cross-branch, expiry, delegation, Owner approval, SoD, and conflict.

Contract tests cover each module boundary, allowed direction, failure behavior, idempotency, and event schema. Transaction/concurrency tests cover payment, allocation, refund, close, enrollment capacity, approvals, authority changes, and corrections. Audit tests verify complete immutable evidence. Reporting reconciliation tests compare metrics to owner facts and periods. Migration tests cover mapping, rejection, reconciliation, rollback, and history preservation. End-to-end tests cover critical workflows. Technical migration and deployment tests remain future architecture/implementation work. The mandatory Integrated Enterprise System Architecture Graph also requires conformance specifications for one owner per fact, typed relationship edges, explicit command/query/event paths, workflow/domain-state separation, document/search/reporting projection boundaries, and defined duplicate/concurrent/stale/unauthorized/provenance/retry behavior.

Employee Workspace specifications must verify that workspace composition is derived from effective identity, employment state, positions, assignments, capability grants, scope, tasks, approvals, deadlines, notifications, and authoritative context; that multi-position employees are not statically role-locked; and that management workspaces expose decision context without forcing operational screens. They must also verify work-first and exception-first prioritization, direct links to canonical workflows, personalization boundaries, rebuildable task/notification projections, API/web parity, and fail-closed behavior after suspension, termination, expiry, transfer, delegation revocation, or capability changes. Workspace visibility must never substitute for command authorization or create a shadow authority.

---

## HISTORICAL SOURCE: `architecture/22-legacy-migration-boundary.md`

# Legacy Migration Boundary

Migration is not assumed until a business decision confirms whether legacy data must be preserved. If required, it is a one-way controlled import into Foundation-owned boundaries: inventory and classify legacy data as evidence, map only with approved canonical mappings, validate identity/relationships/statuses, reconcile financial totals and historical attribution, and quarantine unmappable or conflicting records.

Legacy tables, APIs, routes, services, naming, calculations, permissions, and workflows are not architecture authorities. Cutover requires parallel reconciliation, acceptance by domain owners, a documented point-in-time boundary, audit of imports, and rollback by quarantining/reversing the import rather than rewriting Foundation facts. Existing code may be inspected only for constraints and data evidence.

---

## HISTORICAL SOURCE: `architecture/23-architecture-decision-records.md`

# Architecture Decision Records

| ADR | Decision | Alternatives/reason |
|---|---|---|
| ADR-001 | Modular monolith with strict contexts | microservices/SOA rejected as premature operational/distributed complexity |
| ADR-002 | Module ownership follows canonical domains | table/screen modules rejected as duplicate authority |
| ADR-003 | Owner-bound commands with post-commit notifications | shared writes/distributed hidden transactions rejected |
| ADR-004 | Transactional source facts; derived projections | mutable balances and report authority rejected |
| ADR-005 | Policy-based Position+Assignment+Permission+Scope+Policy | generic role-only ACL rejected |
| ADR-006 | Reusable approval records with domain-selected templates | hard-coded generic business workflows rejected |
| ADR-007 | Context-owned state machines and append-only correction | universal status and overwrites rejected |
| ADR-008 | Academic evidence separate from decision; HR/payroll/finance separated | inferred status and payroll accounting shortcuts rejected |
| ADR-009 | Versioned metric projections from canonical facts | independent dashboard calculations rejected |
| ADR-010 | Append-only audit committed with material facts | mutable logs as sole history rejected |
| ADR-011 | Anti-corruption integration adapters, idempotent jobs | vendor coupling and unsafe retries rejected |
| ADR-012 | RPO/RTO and migration values deferred as explicit operations | invented policy and legacy reuse rejected |
| ADR-013 | Application technology: PHP with Laravel; persistence: PostgreSQL; remains a modular monolith per ADR-001 | Express/TypeScript + SQLite (legacy stack) rejected as untrusted legacy with trigger-heavy cross-domain schema; Node/TypeScript + PostgreSQL rejected by user decision; distributed services rejected per ADR-001 |
| ADR-014 | Academic offerings/availability lifecycle, offering-targeted enrollment capacity, and ordered class waitlists stay in Academic (see `decisions/wp-academic-offerings-waitlist.md`) | offering-less soft seats and silent waitlist auto-activation rejected |
| ADR-015 | Academic rooms, class sections and the timetable/scheduling surface stay in Academic (see `decisions/wp-academic-rooms-sections-timetable.md`) | ad-hoc room strings and unconstrained per-class session lists rejected |
| ADR-016 | Released Placement recommendations produce an Academic-owned signed, versioned, immutable eligibility snapshot (see `decisions/wp-academic-eligibility-snapshot.md`) | live recomputation and unsigned downstream references rejected |
| ADR-017 | Enrollment activation is gated by an authoritative Finance assessment (payment/discount/waiver/funding, credit, installment, approved exception) — see `decisions/wp-enrollment-financial-gate.md` | Academic-side balances and off-DB frontend state rejected |
| ADR-018 | Level-aware progression, level prerequisites, immutable per-level academic history, and an offering-linked Finance charge reference — see `decisions/wp-academic-level-progression-and-packaging.md` | auto-progression from scores, Academic-owned fee amounts, and free-text prerequisite strings rejected |
| ADR-019 | Employee Workspace is a first-class, dynamically composed, role-aware but not role-locked operational work environment over canonical authorities — see `decisions/2026-09-05-employee-workspace-principle.md` | generic/static dashboards, workspace-owned business truth, and visibility-as-authorization rejected |
| ADR-020 | Integrated Enterprise System Architecture Graph is the mandatory, non-negotiable connected target architecture — see `decisions/2026-09-05-integrated-system-architecture-graph.md` | disconnected modules, untyped edges, multiple authorities, workflow/business-state confusion, and undefined failure behavior rejected |
| ADR-021 | Held Payroll calculations require explicit evidenced replacement resolution — see `decisions/2026-09-06-payroll-held-resolution.md` | silent held-row supersession and ungoverned exception completion rejected |
| ADR-022 | Finance uses one fixed-point two-decimal money representation — see `decisions/2026-09-06-finance-fixed-point-money.md` | binary floating-point acceptance and transport/storage representation drift rejected |
| ADR-023 | Student-level Finance coverage decisions serialize on one canonical student-row lock — see `decisions/2026-09-06-financial-coverage-serialization.md` | independent coverage approvals, allocations, corrections, obligation posting, and enrollment activation racing on the same uncovered balance rejected |
| ADR-013-A | **REJECTED/WITHDRAWN 2026-08-25.** Original amendment text (historical record): "PHP + PostgreSQL remain; the Laravel framework is replaced by a framework-free modular monolith implementing the same approved architecture intent" | Historical alternatives text: "Laravel/Composer/Packagist are unreachable in the build environment (SSL connect failures; see `docs/implementation/22-environment-blocker-report.md`); user decision selected the framework-free variant". **Correction:** no user decision selected the framework-free variant; the environment blocker is not a technology decision. See the ADR-013-A rejection record below. ADR-013 remains authoritative. |

## ADR-013 record — technology selection

- **Decision:** The application is implemented in PHP with the Laravel framework; the database is PostgreSQL. The system remains a modular monolith with strict contexts (ADR-001) and module ownership per canonical domain (ADR-002).
- **Authority:** User decision, 2026-08-25, resolving previously open decision `D-F-003` and legacy findings L-002/L-003 in `docs/foundation/01-legacy-system-intelligence-report.md`.
- **Consequences:**
  - Schema ownership stays per module (ADR-002); migrations are owned by the owning module.
  - The database enforces structural invariants only (directive clause 15); it is not a competing business-rule engine. The legacy trigger-heavy schema is not reused.
  - Verification gates (typecheck, lint, static analysis, tests, migration validation) run under `docs/implementation/21-implementation-quality-directive.md` with the tooling chosen in each package plan.
  - No UI-first sequencing is authorized; package order follows `docs/implementation/17-implementation-sequence.md`.

## ADR-013-A amendment — environment-forced framework substitution — REJECTED/WITHDRAWN

> **Status: REJECTED/WITHDRAWN (2026-08-25) — does not take effect.**
> The text below is preserved verbatim as the historical record of the withdrawn amendment.
> The correction is recorded in the ADR-013-A rejection record that follows.

- **Decision:** PHP + PostgreSQL remain the application technology and persistence. The Laravel framework is substituted by a **framework-free modular monolith** that implements the approved architecture intent directly: module-owned contexts and persistence (ADR-001/002), owner-bound commands with atomic fact-plus-audit commits (ADR-003/010), policy-based Position + Assignment + Permission + Scope + Policy authorization (ADR-005), context-owned state machines (ADR-007), and contract-grade error/idempotency/audit behavior per `docs/implementation/`.
- **Authority:** Environment blocker verified 2026-08-25 (Composer/Packagist/getcomposer.org/raw.githubusercontent.com unreachable — SSL connection failures; evidence in `docs/implementation/22-environment-blocker-report.md`); user decision 2026-08-25 selecting the framework-free variant; Decision Ledger `D-F-102`.
- **What is preserved unchanged:** modular monolith with strict contexts (ADR-001); module ownership per canonical domain (ADR-002); owner-bound commands and post-commit notification model (ADR-003); transactional source facts (ADR-004); policy-based authorization (ADR-005); reusable approval records (ADR-006); context-owned state machines (ADR-007); separated domain boundaries (ADR-008); versioned projections (ADR-009); append-only audit (ADR-010); anti-corruption adapters (ADR-011); all module, authorization, lifecycle, error, idempotency, testing, and migration implementation contracts in `docs/implementation/`.
- **What changes:** the framework layer (Laravel's container, ORM, HTTP kernel, service providers) is replaced by a minimal, framework-free kernel implemented in this repository: PSR-4-style autoloader, typed PDO persistence, module-owned migrations, deterministic error taxonomy, idempotency store, and a custom test/verification harness. No business rule, lifecycle, authorization decision, or audit semantic is altered by the substitution.
- **Consequences:**
  - Verification tooling is framework-free: syntax lint (`php -l`), a repository static-analysis/typecheck tool, the package test harness, and migration up/down validation — all runnable with the installed PHP 8.2.27 CLI only.
  - No third-party PHP package is required; dependency discipline (directive clause 20) is satisfied by construction.
  - The substitution is environment-forced, not a preference; if Composer/Packagist ever become reachable in a maintained environment, this amendment must be re-reviewed before any framework reintroduction.

## ADR-013-A rejection record — environment blocker is not a technology decision

- **Status:** REJECTED/WITHDRAWN — the amendment does not take effect and does not override ADR-013.
- **Date:** 2026-08-25 (user correction).
- **Authority:** User correction, 2026-08-25; recorded in Decision Ledger `D-F-103`.
- **Decision:** ADR-013 is authoritative and remains in force: **PHP + Laravel** application technology, **PostgreSQL** persistence, **strict modular monolith** (ADR-001). The framework-free PHP variant is not adopted. No framework substitution is authorized — neither a custom framework nor Node.js, Express, Symfony, another PHP framework, or any other framework.
- **Reason:** The inability to obtain Laravel/Composer/Packagist in the current build environment is an **environment blocker**, not authorization to replace the approved technology. The statement in the withdrawn amendment that a "user decision selected the framework-free variant" is not an approved user decision and must not be treated as such.
- **Consequences:**
  - The environment blocker report `docs/implementation/22-environment-blocker-report.md` remains as evidence that Laravel could not currently be obtained; it records a blocker, not a technology decision.
  - Package 02 (Identity and Organization) **MUST NOT begin production implementation** while the approved Laravel dependency cannot be reproducibly obtained in the build environment. Status: **IMPLEMENTATION BLOCKED BY ENVIRONMENT**.
  - Business rules, architecture, module boundaries, authorization contracts, lifecycle contracts, persistence contracts, and implementation contracts are unchanged; none were rewritten to accommodate the environment limitation.
  - No production code exists in this repository, and none is created by this correction.
  - If the environment becomes able to provide Composer/Packagist reproducibly, implementation may resume under ADR-013 without a further technology decision.

Consequences of ADR-001–012 are captured in artifacts `01`–`22`; those ADRs select no technology. ADR-013 (user decision, 2026-08-25) selects PHP + Laravel + PostgreSQL and is authoritative. ADR-013-A was proposed as an amendment on 2026-08-25 and was rejected/withdrawn on 2026-08-25; it does not take effect.

---

## HISTORICAL SOURCE: `architecture/24-architecture-invariant-registry.md`

# Architecture Invariant Registry

| Foundation invariant/control | Architectural enforcement | Future test category |
|---|---|---|
| one source of truth | owner context and no cross-context writes | ownership/contract |
| derived balances | one `FinancialBalanceQuery` over immutable Finance facts and recorded compensations | financial invariant |
| Finance monetary authority | Finance owns obligations, payments, allocations, journals, corrections, recognized Payroll liabilities, and recorded employment settlements; Payroll supplies evidence only | ownership/financial |
| payroll recognition lineage | approved Payroll source, exact signed amount, distinct Payroll approver and Finance recognizer, unique source, immutable employee-branch provenance, Finance scope decision, append-only fact | payroll/financial/scope/SoD |
| settlement authority sequencing | Finance inserts the immutable settlement fact; Payroll closes only its own matching proposal through `SettlementProposalApproval` in the same transaction | ownership/lifecycle/transaction |
| allocation/refund limits | serialized source transaction and recheck | concurrency/financial |
| student coverage decisions | canonical `students` row lock acquired before competing uncovered-balance derivation and commit; `FinancialBalanceQuery` remains the only arithmetic authority | concurrency/financial/academic |
| immutable history | append-only correction/reversal/audit and one active lifecycle guard per staged Finance fact | history/financial |
| default deny and explicit scope | server policy decision point | authorization/scope |
| temporary authority expiry | effective-time policy evaluation | authorization |
| Owner two-person and SoD | approval template and conflict exclusion | approval |
| branch transfer history | effective-dated scope/attribution resolved as of the effective day; future transfers are not current topology | scope/history |
| academic evidence ≠ decision | separate commands/states | academic |
| entitlement ≠ calculation ≠ payment | HR/Payroll/Finance boundaries | payroll/contract |
| configuration ≠ fact | versioned effective configuration | configuration/history |
| privacy purpose/consent | disclosure gate and export policy | privacy |
| journals traceable/balanced | source link and posting invariant | accounting |
| reports cannot redefine metrics | registered metric/period definitions | reconciliation |
| idempotent external work | keys, dedupe, retry state | integration |
| workspace is not authority | composition over owner queries/commands; no workspace-owned business fact | workspace/ownership |
| workspace visibility is not authorization | current server-side authorization at every command | workspace/authorization |
| effective employee context | identity, employment, positions, assignments, capability, scope, lifecycle evaluation | workspace/lifecycle |
| workspace projections are rebuildable | committed events, authoritative queries, freshness/failure state | workspace/integration |
| audited branch events carry explicit organization provenance | event envelope, active topology constraint, and fail-closed branch projection; no intent-based sibling fallback after provenance declaration | event/provenance/scope |
| workflow projections are source-closed | catalog definition, expected source type, existing canonical source row, explicit intent, and idempotent projection key | workflow/ownership/idempotency |
| work-first and exception-first operations | task, deadline, approval, context, and exception prioritization | workspace/usability |
| personalization cannot change authority | presentation preferences isolated from policy and domain truth | workspace/security |
| one connected enterprise graph | every major fact, context edge, command, event, projection, workflow, document, notification, search, report, and management path has an explicit owner and typed relationship | architecture/conformance |
| defined critical failure behavior | duplicate, concurrent, stale, unauthorized, wrong-branch, missing-provenance, downstream-failure, retry, replay, and duplicate-event outcomes are specified | resilience/concurrency |

No listed critical Foundation, Employee Workspace, or Integrated Enterprise Graph invariant lacks a stated architectural enforcement point; runtime and repository-conformance proof remains outstanding.

---

## HISTORICAL SOURCE: `architecture/25-architecture-traceability.md`

# Architecture Traceability

| Foundation requirement/rule | Entity/domain | Architecture component | Command/boundary | Audit | Future test |
|---|---|---|---|---|---|
| canonical ownership | all contexts | module owner/repository boundary | owner command | material audit | contract |
| financial transaction integrity | Finance | financial boundary plus student coverage serialization | post/allocate/refund/adjust/approve/enrollment gate | transaction audit | financial/concurrency |
| authority and scope | Access/Organization | policy decision point | authorize operation | decision/approval audit | auth/scope |
| effective topology provenance | Organization/Access/Reporting/Resources | effective-dated campus attribution and matching provenance guards | resolve as-of day / reject future or stale topology | scope and correction audit | scope/provenance |
| immutable history | all material facts | history/audit boundary | correct/reverse/appeal | before/after/effective | history |
| academic evidence/decision | Academic | evidence and decision components | submit/approve/appeal | academic audit | academic |
| payroll separation | HR/Payroll/Finance | period/calculation/posting boundaries | calculate/approve/pay | payroll/finance audit | payroll |
| privacy | Privacy/Documents | purpose/consent/export gate | disclose/revoke/export | disclosure audit | privacy |
| derived reporting | Reporting | metric registry/projection | run/reconcile | run/source metadata | reporting |
| cross-domain contracts | all | application coordinator/adapters | command/event | correlation audit | contract/integration |
| resilience | Infrastructure boundary | backup/recovery/job durability | restore/replay | recovery evidence | resilience |
| employee workspace composition | employee identity, employment, positions, assignments, scope, capabilities | workspace composition/orchestration | compose/link/execute canonical action | correlation and material action audit | workspace contract |
| workspace authorization | Access/Organization plus owning domain | server policy decision point and owner command | authorize/recheck/execute | decision and owner audit | workspace/auth |
| workspace tasks and exceptions | owning domain facts plus outbox/projections | task/notification/work-context projection | acknowledge/link/route, never redefine | projection and source correlation | workspace/integration |
| workspace productivity | presentation preferences | isolated personalization layer | save/apply preference | preference audit where material | workspace/usability |
| integrated enterprise graph | all major facts, contexts, commands, events, projections, workflows, documents, notifications, search, reports, and management decisions | owner/boundary/typed-edge registry | canonical command/query/event path | correlation and source lineage | architecture/conformance |
| critical failure behavior | duplicate, concurrent, stale, unauthorized, provenance, downstream, retry, replay conditions | command/event failure contract | idempotent/retry/conflict handling | failure evidence | resilience/concurrency |

Broken chains: **none identified** at the documented target level. Detailed operation acceptance examples and organization-specific resilience targets remain future inputs, not broken ownership chains. Integrated graph repository conformance, Employee Workspace runtime, API/web parity, freshness, concurrency, and authorization proof remain deferred validation.

---

## HISTORICAL SOURCE: `architecture/26-gate-5-architecture-review.md`

# Gate 5 — Formal System Architecture Design Review

**Date:** 2026-08-25
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`

## Decision

The approved Foundation translates into a coherent technical architecture without inventing authority, financial truth, lifecycle behavior, scope semantics, privacy rules, or legacy-derived policy. The selected style is a strict modular monolith with owner-bound contexts, server authorization, transactional source facts, append-only audit, rebuildable reporting projections, and anti-corruption integration boundaries.

## Critical findings

0. Financial architecture is coherent: obligations, charges, discounts, scholarships, payments, allocations, refunds, adjustments, reversals, cash, journals, reconciliation, and reporting remain distinct and source-linked. No mutable balance authority exists.

## High findings

0. No high business-boundary ambiguity remains.

## Medium findings / non-blocking open items

- Organization-specific RPO/RTO, retention, and disaster priorities require operational confirmation.
- Detailed report metric catalog and acceptance examples remain to be expanded before implementation.
- Legacy preservation/migration requirement remains a separately governed business decision.

## Adversarial architecture attack

All 20 required attacks were modeled in the review matrix below. Each has an invariant, owner, control, failure/recovery behavior, audit evidence, and future test category.

| # | Scenario | Control and recovery | Evidence/test |
|---:|---|---|---|
| 1 | simultaneous payments | idempotency/source serialization; reconcile ambiguous result | Finance audit/concurrency |
| 2 | refund twice | source refund limit/idempotency; reject second | refund audit/financial |
| 3 | payment allocated twice | allocation uniqueness and amount recheck; reject | allocation audit/concurrency |
| 4 | concurrent two-Owner approvals | compare-and-commit policy; count once | approval audit |
| 5 | Owner conflict | conflict exclusion; deny/record | auth/approval |
| 6 | expired delegation | effective-time deny; escalation if needed | scope audit |
| 7 | cross-branch access | explicit scope evaluation; deny | auth/scope |
| 8 | assignment expires during session | reauthorize operation; hold/deny mutation | scope/history |
| 9 | branch transfer | effective dating; preserve historical attribution | organization/history |
| 10 | historical report after transfer | as-of scope/attribution and period definition | reporting reconciliation |
| 11 | teacher submits during authority change | command-time authorization and assignment snapshot | academic/auth |
| 12 | payroll disagrees with contract | hold for HR/Payroll review; no payment | payroll/contract |
| 13 | payroll during period close | period lock/state recheck; hold or retry | payroll/concurrency |
| 14 | duplicate identity after transactions | identity exception and linked history; no merge overwrite | identity/history |
| 15 | consent revoked after disclosure | block future use; retain disclosure evidence | privacy |
| 16 | dashboard/finance same period | shared Financial Period and metric registry | reporting/reconciliation |
| 17 | configuration changes historically | effective version snapshot; no rewrite | configuration/history |
| 18 | payment timeout | idempotent status inquiry/reconciliation; no duplicate retry | integration/finance |
| 19 | duplicate webhook/event | authenticated dedupe key; safe replay | integration |
| 20 | failure during financial transaction | atomic owner commit/retry idempotently; reconcile | resilience/financial |

## Legacy contamination result

**PASS.** Existing implementation remains untrusted legacy evidence. It determines neither architecture, modules, persistence, authorization, financial behavior, nor workflows. Foundation records win in conflict.

## Exact implementation boundary

No production code, database, schema, migration, API, UI, framework scaffolding, dependency installation, production configuration, or legacy modification was performed. This gate creates architecture documentation only.

## Exact remaining unknowns

RPO/RTO/retention values, detailed report catalog and acceptance examples, and whether/how legacy data must be migrated. These do not block architecture design; they must be resolved before their affected implementation plans are approved.

**GATE 5: PASS WITH NON-BLOCKING OPEN ITEMS.**

**Next authorized gate:** none automatically. Gate 6 requires separate explicit authorization.

## Subsequent governing addendum — Employee Workspace

The approved `docs/governance/employee-workspace-principle.md` is incorporated after this dated Gate 5 review as a permanent product and architecture requirement. It does not convert this historical gate into a production-readiness claim. Before any later gate or implementation approval, the architecture must demonstrate first-class, dynamically composed, role-aware but not role-locked employee and management workspaces; canonical-authority orchestration; work-first and exception-first flows; lifecycle- and scope-aware server authorization; API/web parity; rebuildable task and notification projections; personalization isolation; and measurable employee efficiency. Runtime, security, concurrency, freshness, and usability evidence remain required.

---

## HISTORICAL SOURCE: `architecture/27-teacher-faculty-authority-architecture.md`

# Teacher / Faculty authority architecture

Status: static convergence target, 2026-09-06. This document records the canonical boundary implemented by the Teacher authority changes in this checkout. It is not a production-readiness claim; runtime, database, migration, browser, queue, integration, and automated verification remain deferred by instruction.

## 1. Boundary decision

Teacher is a capability and academic-delivery authority, not a second identity or HR system.

| Fact | Sole write authority | Teacher consumes | Required proof
|---|---|---|---|
| Human identity, legal name, verification | Identity / Person | Person identity | verified Person for activation and assignment
| Employment, contracts, status, leave | HR | Employment, status history, approved leave | active effective employment and no approved leave on the academic date
| Position / access grants | Access | capability decision and structure scope | explicit server-side capability; position title alone is never enough
| Teacher profile and lifecycle | Teacher | verified Person and active Employment | pending → active → suspended/retired, with approval and audit
| Teacher qualification | Teacher | evidence reference and independent reviewer | verified qualification effective on the date
| Teacher branch provenance | Teacher | Organization branch/campus topology | effective active branch authorization with reason and approver
| Subject/course authority | Teacher | Academic Skill catalog | effective `teach`, `assess`, or `moderate` authority plus evidence
| Availability | Teacher | branch and timetable dates | effective weekly window; approved HR leave overrides availability
| Class, offering, period, session | Academic / Classes | Teacher authority | class/period lifecycle remains Academic-owned
| Teacher assignment | Teacher domain command surface, using Academic class facts | class, period, branch, profile, qualification, leave | effective profile, branch provenance, period containment, no forbidden overlap
| Enrollment and attendance | Academic | teacher assignment for teacher actions | same class/session/enrollment, branch, assignment date, idempotency
| Assessment evidence/result chain | Academic | teacher assignment and subject authority for teacher scoring | assignment-aware submit/score; moderation/approval independence
| Teaching delivery/payroll evidence | Payroll derived fact | immutable session, assignment-skill, attendance, Teacher capability | no identity-only claim; Finance remains monetary authority
| Audit and attempted operations | Audit | all commands and denial context | correlation, actor, before/after, scope and evidence references

Academic remains authoritative for class, offering, session, enrollment, attendance, assessment, result, certificate, transcript, and progression lifecycle. Teacher does not copy or mutate those aggregates.

## 2. Canonical Teacher records

The consolidated pre-production Teacher authority migration introduces:

- `teacher_profiles`: one profile per Person and Employment, with `pending`, `active`, `suspended`, and `retired` states. Activation requires verified identity, active employment, a current verified qualification, effective branch provenance, and an independent approver. `teacher_profile_statuses` retains append-only lifecycle history.
- `teacher_profile_branches`: effective branch authorizations with provenance reason and approver. This supports campus/branch transfers without changing historical assignment snapshots.
- `teacher_qualifications`: independently evidenced records with issuer, validity, verification actor/time, and append-only lifecycle semantics.
- `teacher_skill_authorities`: effective subject/course authority by Academic Skill and authority kind (`teach`, `assess`, `moderate`). Assignment-skill attribution is delivery evidence and is not a substitute for this authority.
- `teacher_availabilities`: effective weekly availability in a branch. New session scheduling requires a covering availability window and rejects timetable collisions.
- `teacher_workload_limits`: effective weekly hour ceilings by profile and branch. Timetable admission sums the teacher's governed sessions for the academic week before accepting a new session.
- `teacher_assignments` provenance and lifecycle columns: `teacher_profile_id`, class-matching branch, campus, organization, assigning actor, reason, and `planned`/`active`/`ended`/`cancelled` state. Historical legacy rows remain identifiable for remediation; newly written assignments require canonical profile and provenance.

## 3. Lifecycle and workflow rules

### Profile intake and activation

1. Identity verifies the Person.
2. HR opens and hires the Employment under its existing lifecycle and contract rules.
3. Teacher registers a pending profile against that exact Employment and Person.
4. Teacher records qualification evidence.
5. An independent teacher approver verifies each qualification.
6. An approver activates the profile only after current qualification, active employment, verified identity, and effective branch authorization are present.
7. Suspension or retirement is an explicit audited transition. It does not rewrite assignment history; delivery and teacher actions fail closed while the profile is inactive.

### Assignment

The canonical assignment path is `MaintainTeacherAssignment`. The Academic Classes transport and older Blade routes are compatibility façades only; all policy resolves through `TeacherAuthority` before the assignment row is written. It requires:

- schedule capability on the class branch;
- verified Person and active Teacher profile;
- active effective Employment and latest effective status;
- no approved leave overlap;
- current verified qualification;
- effective Teacher branch authorization matching the class branch;
- `effective_from`/`effective_to` inside the class academic period;
- one non-overlapping assignment window for the class/person;
- server-derived class/campus/organization provenance.

Handover ends the outgoing row and creates a separately audited incoming row after the successor passes the same checks. End and extension are dated mutations with reasons; deletion is never a lifecycle operation. HR termination ends assignments covering the termination date and explicitly cancels future-effective assignments, so a stale future row cannot block replacement or appear deliverable.

### Delivery, attendance, and assessment

- A new session requires an effective canonical assignment, active employment/profile, class branch match, attributed assignment skill plus effective teach authority when a skill is present, availability, no approved leave, and no overlapping session for the same teacher.
- Attendance resolves the session's authoritative class and scheduled date. A Teacher-profile actor must have an effective assignment to that class; an explicit governance actor must pass the server capability path. Enrollment/class/session matching and append-only correction rules remain Academic-owned.
- Assessment attempt submission and scoring use the same Teacher assignment plus effective `assess` subject authority for Teacher-profile actors; an assignment skill must be attributed before teacher assessment evidence is accepted. Moderation, approval, release, and correction independence remain Academic result-chain rules; no frontend state promotes a result.
- Gradesheet reads use the canonical profile and effective assignment, including a distinct read-only historical window for ended assignments while the academic term remains open.

### Leave, inactivity, and transfers

HR remains the only writer of leave and employment state. Teacher assignment/session policy consumes the employment status effective on the academic date and rejects delivery on approved leave, suspended/on-leave/terminated employment, or inactive Teacher profile. Future-effective termination is represented by the append-only HR status date; current assignments end at that date and future assignments are cancelled, so pre-termination academic evidence remains date-valid without treating the current aggregate label as historical truth. HR termination orchestration calls the Teacher-owned assignment command to end current delivery assignments and cancel future-effective assignments with Teacher audit evidence; incomplete legacy rows remain non-deliverable remediation candidates. A branch transfer closes the previous current-home authorization, opens a dated destination authorization, updates current provenance, and refuses transfer while incompatible open assignments remain; an explicit handover or end operation is required.

## 4. Database and concurrency invariants

The additive migrations add database final guards for:

- profile state, identity/employment/qualification prerequisites, verified-evidence provenance, active branch topology, approved subject authority, and non-overlapping effective branch/skill authority windows;
- assignment-to-profile/person consistency, non-overlapping class/person assignment windows, class branch and academic-period containment, effective branch authorization, approved leave conflict, campus/organization provenance, and legacy-row remediation boundaries;
- session delivery requiring an effective canonical assignment, subject authority, availability, and no overlapping teacher sessions;
- an active class requiring an effective canonical teacher assignment;
- assignment lifecycle state preventing cancelled future rows from satisfying active-class, session, assessment, or payroll delivery guards, with assignment-skill inserts requiring effective teach authority across the full assignment window;
- a database workload trigger that serializes weekly candidate-teacher admission and enforces the effective workload ceiling for direct SQL/concurrent session writes;
- payroll teaching-delivery evidence requiring canonical profile, assignment, assignment-skill, and effective subject authority in addition to existing session, attendance, duration, and period guards;
- terminal qualification evidence, profile identity/provenance, branch/skill authority identity, availability identity, workload identity, and canonical assignment identity guards; legal lifecycle transitions are explicit and terminal authority states cannot be reopened;
- deferred profile lifecycle-history enforcement: profile registration and every subsequent lifecycle transition must settle with matching append-only status evidence, while direct history UPDATE/DELETE is rejected.

All application mutation paths retain transaction locks, idempotency keys, audit events, and attempted-operation denial records. The database remains the race arbiter for direct SQL and concurrent writers. Assignment and authority `effective_to` dates are exclusive across the new Teacher policy (an assignment covering the final period day ends on the following date); payroll derivation was corrected to use the same exclusive boundary.

## 5. Transport and workspace

`/teachers` is the React Teacher/Faculty workspace. `/api/v1/teachers/workspace` returns only server-derived profiles within the actor's own profile or effective `academic.teacher_manage` branch scope. It exposes lifecycle, employment state, qualifications, subject authority, branch provenance, availability, assignment history, and approval capability as facts; it does not invent transitions or permission state.

Teacher mutation endpoints validate transport shape only and delegate to canonical Teacher commands, including `MaintainTeacherAssignment` for assignment lifecycle and attribution. The workspace is therefore an orchestration/projection surface, not a second business authority.

## 6. Legacy and remediation boundary

Existing assignment rows without `teacher_profile_id` or stored branch snapshot are not silently backfilled. They remain historical evidence and can be ended through the compatibility path, but they cannot be used to create new canonical assignments, new subject attribution, canonical teacher workspace authority, new sessions, or payroll delivery claims. A controlled remediation workflow must establish the missing profile, qualification, branch authorization, and provenance before any legacy relationship is reopened or replaced.

No identity, job title, employment label, current position, browser state, client-supplied capability, or stale assignment object can independently confer authority.

## 7. Deferred verification and residual risks

The following were intentionally not run: PHP, Composer, PostgreSQL, migrations, PHPUnit, PHPStan, Pint, Node, builds, browser validation, queues, integrations, and runtime checks. Consequently this static convergence does not prove compilation, dependency resolution, migration application order, trigger behavior against live data, route registration, frontend rendering, or performance.

Residual risks requiring runtime verification include migration compatibility with any already-materialized pre-production data, PostgreSQL trigger ordering, query plans for timetable/payroll checks, generated frontend type/build compatibility, and assignment remediation UX. These risks are explicitly deferred rather than represented as resolved.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-broad-read-authorization.md`

# ADR: broad console reads require explicit root authority

- **Status:** Accepted
- **Date:** 2026-09-05

## Context

Several console and JSON read methods returned broad tables while only mutation commands invoked branch-aware authorization. `null` scope is fail-closed for operational targets and cannot be a wildcard.

## Decision

Use branch-scoped query objects when a model has canonical branch provenance. Where a console intentionally returns an organization-wide administrative result set, require `AccessDecision` with a concrete organization-wide capability before executing the query. The base controller exposes `requireOrganizationRead`; denial is audited. This pass applies the gate to the identified Academic, Payroll, Reporting, Home, Organization, CRM, and Payroll JSON bulk reads, while Academic session and CRM detail paths use branch-scoped checks.

## Consequences

- A branch grant cannot read an unscoped administrative table merely because a mutation command would reject its writes.
- Some existing UI workflows may need branch-scoped query replacements rather than broader grants.
- Read authorization is explicit and testable rather than hidden in navigation or frontend filtering.

## Rejected alternative

Using `hasReadAuthority()` as a boolean gate was rejected: it proves only that the actor has some authority, not that the actor may see every branch or organization record.

## Validation required later

Exercise branch grants, organization grants, delegations, inactive employment, null provenance, API/web parity, and denial audit behavior.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-class-session-branch-provenance.md`

# ADR: class provenance is the canonical scope for sessions

- **Status:** Accepted
- **Date:** 2026-09-05
- **Supersedes:** implicit class scope derived from an optional room, optional offering, or an organization-wide null scope

## Context

`offerings` and `academic_rooms` had direct branch ownership. Enrollments carried originating/current-home snapshots. `classes` had no branch column, so a room-less class/session could not be assigned a canonical branch. A read path that filtered only roomed sessions therefore exposed room-less operational records across branches, while some class commands passed null to branch authorization and failed to distinguish unknown provenance from global governance.

## Decision

Add `classes.branch_id` as immutable operational provenance. New class inserts require it through a database trigger and `MaintainClass`; historical nulls remain explicitly unknown and are not fabricated. A session inherits its class branch. A room used by a session must belong to the same branch. Timetable branch queries and Academic API/web session reads scope through the class, not through room presence.

Classes can still be created without an offering where the delivery design requires it; the class branch is the canonical scope in that case. Offering, enrollment, student, and room provenance remain useful related facts but do not compete with class operational scope.

## Consequences

- Room-less sessions can be safely filtered.
- Class transition, section, teacher assignment, and session commands can authorize against one target branch.
- Historical rows need remediation or deliberate quarantine; they cannot be guessed from a current room.
- The UI and transport contract must supply `branch_id` for new class definitions.

## Rejected alternatives

1. **Derive every class from room:** room is optional and can change; it is not class ownership.
2. **Derive every class from offering:** the existing class path does not require an offering, and an offering is a registration/delivery opportunity rather than the complete class identity.
3. **Treat null as organization-wide:** this would turn missing provenance into a privilege escalation and contradict fail-closed access semantics.

## Validation required later

Test direct SQL inserts, concurrent branch/room scheduling, historical null behavior, class IDOR, web/API parity, and migration upgrade behavior.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-employee-workspace-principle.md`

# ADR: Employee Workspace is a first-class operational work environment

- **Status:** Accepted governing addendum; implementation and runtime validation incomplete
- **Date:** 2026-09-05
- **Governing text:** `docs/governance/employee-workspace-principle.md`
- **Related:** `docs/MASTER_ENGINEERING_CONTRACT.md` approved Employee Workspace addendum; `docs/governance/supreme-technical-governance-mandate.md` §37

## Context

A generic dashboard or static role dashboard does not provide an effective operational environment for employees who hold multiple legitimate positions, operate within changing organizational scope, carry assigned work, or need immediate context for approvals, deadlines, notifications, and exceptions. A workspace that stores its own business state or permission decisions would create a competing authority and could preserve stale power after lifecycle or authorization changes.

The platform needs a product capability that reduces real employee effort while preserving canonical domain ownership, Finance monetary authority, branch isolation, lifecycle integrity, SoD, API/web parity, auditability, and transactional integration behavior.

## Decision

Treat Employee Workspace as a first-class platform capability and product requirement. It is the employee's primary operational work environment, not merely a dashboard.

At composition time, the workspace derives effective employee identity, employment eligibility, positions, assignments, organizational and branch scope, capabilities, lifecycle state, assigned responsibilities, tasks, approvals, deadlines, notifications, authoritative domain context, and exceptions. It is role-aware but not statically role-locked. A multi-position employee may see multiple legitimate work areas, while each action remains independently authorized and scope-checked by the server.

The workspace is an orchestration, presentation, and workflow-entry layer over canonical domain authorities. It may aggregate cross-module information, prioritize work, link to owner commands, and retain presentation preferences. It must not own balances, enrollment state, payroll state, approval truth, lifecycle truth, permissions, or an independent business task authority. Personalization changes presentation and productivity only.

Work-first and exception-first behavior is required. The composition should answer what needs attention, what can be done now, what is overdue, what is blocked or waiting, what changed, and which canonical workflow completes the work. Management workspaces must emphasize decisions, risks, trends, accountability, and exceptions rather than forcing management through employee-level operational screens.

Visibility is never authorization. Every sensitive action is reauthorized at command time against current employment, capability, delegation, lifecycle, and scope state. Workspace projections, task/notification state, and outbox-fed context must be idempotent, traceable, freshness-aware where relevant, rebuildable, and unable to grant or preserve authority.

## Consequences

- Authorization and lifecycle architecture must expose effective context suitable for workspace composition without moving policy into the frontend.
- Domain modules remain owners of facts and commands; workspace aggregation cannot become a cross-module write path.
- API, web, and any console transports must provide equivalent authorized workflow entry points.
- Reporting, notifications, tasks, approvals, search, events/outbox, audit, personalization, and frontend design must state their workspace boundaries.
- Intended-behavior specifications must cover multi-position composition, management context, work/exception prioritization, stale-authority revocation, scope isolation, projection rebuild, idempotency, accessibility, and employee efficiency.
- Runtime migration, concurrency, security, browser, freshness, and usability validation remains required. This ADR does not certify production readiness.

## Non-goals

- replacing canonical domain modules with a workspace service;
- implementing one static dashboard per role;
- treating client visibility or cached workspace state as authorization;
- duplicating financial, academic, HR, Payroll, Finance, approval, lifecycle, or reporting truth;
- defining business policy solely to make a workspace card convenient.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-enrollment-constraint-boundary.md`

# ADR: Enrollment constraint boundary

- Status: accepted for convergence
- Date: 2026-09-05

## Context

Academic still owns the current `Enrollment` lifecycle writer and the canonical `enrollments` representation. The enrollment command nevertheless contained reusable checks for active students and classes, prerequisites, offering compatibility, branch provenance, and class/offering seat capacity. Leaving those checks embedded makes a future Enrollment boundary harder to extract and encourages Academic to absorb planning policy.

## Decision

Create an `EnrollmentConstraints` domain service under the target Enrollment module boundary. It owns reusable admission-to-seat planning constraints and reads Academic/Students canonical facts. Academic remains the sole current writer for `Enrollment`, financial gates remain Finance-owned, and this change does not create a second enrollment table or transfer lifecycle authority prematurely.

The service is fail-closed for inactive students/classes, unsatisfied prerequisites, closed or mismatched offerings, missing offering provenance fallback, and exhausted class/offering capacity. Capacity checks retain row locks for the existing transaction-level invariant.

## Consequences

- Enrollment constraint policy has one extraction seam before lifecycle writes move.
- Academic orchestrates the command and remains the only `Enrollment` writer in this phase.
- Runtime concurrency, container wiring, authorization seeds, and migration behavior remain unverified because runtime execution is intentionally deferred.
- Availability reservations, teacher conflicts, waitlist orchestration, and a fully materialized Enrollment module remain future work.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-finance-payroll-liability-reporting.md`

# ADR: Finance recognition is required before Payroll amounts enter monetary reporting

- **Status:** Accepted
- **Date:** 2026-09-05
- **Supersedes:** `payroll_total` being calculated directly from Payroll results and adjustments as if Payroll were the monetary reporting authority

## Context

Payroll correctly owns deterministic calculation, approval, and source-linked adjustments. Finance is the sole monetary authority. The reporting calculator nevertheless summed approved Payroll results and adjustments directly. That made a derived report an implicit second monetary authority and did not define recognition, period close, evidence, correction, or downstream posting semantics.

## Decision

Payroll results and adjustments remain immutable calculation evidence. Finance recognizes each approved source through `RecognizePayrollLiability`, producing an append-only `payroll_liability_facts` row with source identity, exact amount (including a signed reversal), period, employment, immutable originating employee-branch provenance, Finance actor, evidence reference, and correlation. The Finance fact is unique per source and is the only source used by `payroll_total`. Recognition fails closed unless the source is approved, the amount matches exactly, the Finance actor is distinct from the Payroll source approver, employee branch provenance is known and active, and the Finance capability is allowed for that branch scope.

A later Finance posting/disbursement design may project recognized liabilities into journals or payment workflows; this ADR does not make Payroll a ledger and does not silently treat an unrecognized Payroll result as money.

## Consequences

- A report can intentionally be lower than approved Payroll source evidence while Finance recognition is pending.
- Finance owns monetary authorization, evidence, corrections, and recognition timing.
- Payroll remains usable as a calculation and review surface without acquiring posting authority.
- Operations need a Finance recognition workflow and reconciliation monitoring.
- The report's period key remains resolved by the Payroll period authority because that is the source period; ownership of the monetary fact is still Finance.

## Rejected alternatives

1. **Keep summing Payroll tables:** rejected because it violates the Finance-only monetary boundary.
2. **Make Reporting post or reconcile liabilities:** rejected because reporting is derived data, not a financial command surface.
3. **Reuse termination settlements for monthly payroll:** rejected because an employment settlement is a different aggregate and lifecycle.

## Validation required later

Validate exact decimal behavior, adjustment signs and the signed transport rule, source uniqueness, active branch provenance, Finance scope authorization, period closure, corrections after recognition, journal/disbursement lineage, report reproducibility, and reconciliation of source evidence to recognized facts. Migration ordering and direct-SQL trigger behavior remain unverified until runtime validation is explicitly permitted.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-integrated-system-architecture-graph.md`

# ADR: Integrated Enterprise System Architecture Graph

- **Status:** Accepted governing target architecture; mandatory and non-negotiable
- **Date:** 2026-09-05
- **Normative specification:** `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`
- **Governing references:** `docs/MASTER_ENGINEERING_CONTRACT.md` approved graph addendum; `docs/governance/supreme-technical-governance-mandate.md` §38
- **Conformance status:** not yet established; static repository audit and runtime validation remain required

## Decision

Treat TOEFL House as one enterprise organism with a connected logical architecture rather than as unrelated modules. The integrated graph is mandatory for all subsequent architecture, implementation, schema, API, workflow, authorization, frontend, workspace, event/outbox, reporting, documentation, and acceptance decisions.

The graph requires:

- one authoritative owner per major fact;
- typed cross-domain relationships rather than unclassified dependencies;
- explicit authentication, authorization, command, validation, transaction, audit, event, projection, document, notification, search, reporting, and management paths;
- logical bounded contexts without requiring one physical service per context;
- Finance as the sole monetary authority;
- Enrollment as the enrollment decision authority consuming governed academic and financial eligibility;
- Placement as the measurement/evidence/result authority, not a class-assignment authority;
- Workflow as process/work-item coordination, not domain-state ownership;
- workspace as an authorized projection/orchestration surface, never a business fact owner;
- separate Task, Approval, WorkItem, Exception, and Notification concepts;
- server-side resource-, relationship-, scope-, lifecycle-, delegation-, SoD-, concurrency-, and idempotency-aware authorization;
- preserved historical branch/provenance context and compensating corrections rather than silent rewriting;
- transactional outbox publication with idempotent consumers;
- defined behavior for normal, duplicate, concurrent, stale, unauthorized, wrong-branch, missing-provenance, downstream-failure, retry, duplicate-event, and replay conditions.

## Conformance law

Every important implementation field or state must be answerable by: authoritative owner, changing command, permitting authorization, actor and context, invariant boundary, concurrent behavior, repeat behavior, emitted event, consuming projections, workflow/document/notification consequences, historical reconstruction, provenance retention, unauthorized-attempt evidence, direct-write prevention, correction path, and traceability to management reporting.

If a repository artifact conflicts with the graph, it is a conformance defect. It must be corrected or explicitly rejected by a superseding ADR; existing code, schemas, routes, screens, reports, indexes, workflow artifacts, documents, events, notifications, or implementation convenience do not override the graph.

## Consequences

- The next repository audit must map every major repository concept and cross-domain edge to an owner and typed relationship.
- Existing modular-monolith decisions remain compatible; the graph governs logical responsibility, not mandatory physical decomposition.
- Employee Workspace implementation must be assessed as part of the complete enterprise graph, not as an isolated dashboard feature.
- Architecture acceptance requires explicit failure/concurrency/idempotency behavior and historical/provenance evidence.
- This ADR is a target specification only. It does not certify conformance, runtime correctness, migration safety, or production readiness.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-notification-projection-boundary.md`

# ADR: Notification projection and event intent boundary

- Status: accepted for convergence
- Date: 2026-09-05

## Context

Communication messages and domain work are different concepts. A notification informs one recipient and needs independent read/dismiss state, but it must not acknowledge a domain action, complete a work item, or become a global unread authority. Inferring recipients from the event actor or exposing branchless rows to branch actors would create scope and privacy defects.

## Decision

Communication owns a recipient-scoped `notifications` projection. Its consumer accepts only explicit event payload intent containing a recipient and title, records a dedupe key, and never mutates the source aggregate. Notification read/dismiss transitions are owned by Communication, require recipient identity plus the `communication.notification.read` capability and exact branch or organization scope authorization, and are idempotent under row lock. Organization scope is resolved directly through `StructureScope` and the canonical Access resolver; it does not depend on a current visible branch assignment.

Notifications use event context branch provenance when an intent does not override it. Missing provenance is unknown, not organization-wide. Employee Workspace composes the projection and React may call only the versioned read/dismiss commands. Message delivery, Work Management state, Audit evidence, and source-domain facts remain separate authorities.

## Consequences

- Replay-safe recipient/read state exists without a second task or message authority.
- Domain producers must opt in with explicit recipient notification intents; no broad event-to-recipient guessing is performed.
- If an envelope supplies both branch and organization provenance, the active campus assignment must prove that they belong to the same active organization; otherwise projection is rejected.
- Channel/device preferences, expiry/retry operations, and comprehensive event-specific notification policy remain deferred.
- Runtime migration, consumer, authorization seed, API, and browser behavior remain unverified under the governing static-only review posture.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-production-authority-and-correction.md`

# ADR: Production authority, provenance, lifecycle gates, and financial corrections

**Date:** 2026-09-05  
**Status:** Accepted for implementation; settlement-storage subsection superseded by `2026-09-05-unified-platform-architecture.md`; verification blocked in this workspace  
**Scope:** F-A, F-B, F-C1, F-C2, F-D, F-E, F-H

## Independent validation

The reported findings are related, not six independent controller defects:

- `AccessResolution` resolved active access rows but did not consume the HR
  employment eligibility fact. Suspension and leave therefore left role-derived
  assignments effective, while direct grants and delegations remained usable.
  Termination's best-effort assignment revocation was not a complete authority
  boundary. **F-A confirmed.**
- Organization lifecycle commands changed the structure row, but the policy
  decision point never evaluated the lifecycle of the target branch, campus, or
  organization. An active grant could therefore operate against a closed or
  suspended branch. **F-B confirmed.**
- Academic delivery commands had received a branch resolver, but Students
  commands still called the policy decision point with `null`. `null` means
  "any capability grant" in the canonical resolver. `RecordBranch` also
  intentionally collapsed unknown provenance to that global path, and API
  lists/read checks included null-provenance rows. **F-C1/F-C2 confirmed.**
- Student status was append-only and enrollment activation was guarded, but
  status was not a cross-context operational gate. Attendance/attempt and
  arbitrary Finance obligation routes could continue after withdrawal.
  **F-D confirmed.**
- Posted journals had a reversal path, but obligations, allocations, and the
  rest of the financial source-fact surface did not have a complete,
  source-linked correction instrument. Finance history was immutable, but the
  correction model was incomplete. **F-E confirmed in part.**
- `IdempotentExecution` selected before a row existed, ran the business
  transaction, and inserted the idempotency record after that transaction.
  Two first submissions could race on the unique index, and a crash between
  the business commit and the idempotency insert could repeat a business fact.
  **F-H confirmed.**

## Decisions

### 1. Authorization remains owned by Access

Authorization is still resolved only by `AccessResolution` through the
existing `AccessDecision` port. HR does not grant, revoke, or resolve
capabilities. HR owns employment status; Access consumes it as an eligibility
predicate at the policy decision point:

- a person with no employment record may hold explicitly granted non-employee
  governance authority;
- once a person has an employment record, only the `active` employment state
  is eligible for human authority; candidate, leave, suspended, and terminated
  states fail closed;
- the predicate applies uniformly to position authority, direct grants, and
  delegated authority, so there is no alternate route around employment;
- the existing HR termination cleanup remains useful history/housekeeping, but
  it is not the security boundary.

### 2. Structure lifecycle is an authorization precondition

A scoped operation is allowed only when its entire organization → campus →
branch path is operationally active. The policy decision point enforces this
for every scoped operation, including direct grants and delegation resolution.
Structure lifecycle maintenance is the only explicit exception: its existing
multi-party `StructureDecision` passes a maintenance marker so authorized
owners can close, suspend, and reopen a unit. No ordinary academic, student,
finance, payroll, or API operation receives that exception.

### 3. Unknown provenance is not global scope

`null` is retained only for intentionally branchless governance operations;
`AccessResolution` requires an organization-rooted grant for that path. A
branch-homed target with no resolvable branch is represented as unknown and
fails closed. The shared branch-scoped access adapter is used by Academic and
Students; it resolves branch from locked server-side records and never accepts
actor/client branch claims. Organization-wide grants continue to operate on
known descendants and on explicit branchless governance; they do not turn
unknown operational provenance into a guessed scope.

Admission registration requires an active branch and checks that branch
through canonical Access; admission decisions and applicant conversion use
that persisted provenance rather than the current actor's scope. New Student,
enrollment, and Finance source facts must carry provenance from a verified
linked record. Existing null historical rows are preserved and are
read-only/remediation candidates; they are never silently backfilled.

### 4. Student status is the operational gate; history is not rewritten

Students remains the owner of append-only status. A shared read-side
`StudentOperationalEligibility` predicate is the only cross-context gate:
new delivery facts, waitlist admission, enrollment activation, and new
obligations require current `active` status. Withdrawal does not rewrite old
enrollments, attendance, results, obligations, or payments. Historical
corrections, result release, collection of an existing balance, and refunds
remain possible where their owning lifecycle permits them.

### 5. Payroll and Finance settlement boundary

Payroll may calculate and stage a termination settlement proposal and collect HR and Finance clearance evidence. The recorded settlement is a Finance-owned `employment_settlements` fact, approved through a branch-scoped Finance capability and source-linked to exactly one matching proposed Payroll settlement. The database guard requires terminated employment, both clearance domains, independent preparation/approval identities, beneficiary separation, and known active employee branch provenance. The Payroll `final_settlements` table and model are not part of the target runtime; migration `2026_09_05_000143_consolidate_employment_settlement_authority.php` removes that competing authority.

### 6. Finance corrections are compensating facts

Finance source facts remain immutable. A Finance-owned, staged
`financial_corrections` instrument records a source type/id, signed effect,
reason, requester, independent approver, period, and audit evidence. It may
reduce/increase an obligation or reverse a payment/fund allocation; it cannot edit or
delete a source row and it is counted in derived balances. Journal reversal
also becomes source-linked and one-per-source. This is not an Academic or
Student balance and no other module may post a correction.

### 7. Idempotency is one atomic owner boundary

The idempotency row is claimed, the owner command executes, and the outcome is
stored in one database transaction. The unique key remains the concurrency
arbiter. A losing concurrent transaction retries its read after the winner
commits; same payload returns the winner's outcome, different payload is
rejected. A failed command rolls back both business facts and the idempotency
claim. This applies to every existing command using `IdempotentExecution` and
is not reimplemented per module.

## Consequences and non-decisions

- This deliberately does not create a second RBAC or a Student-owned access
  table.
- Existing audit/history rows are retained. Null historical provenance is not
  fabricated.
- Student withdrawal does not silently cancel financial obligations or erase
  academic history; future operational commands are gated at their owners.
- Financial correction approval is separate from posting and obeys Finance
  period and segregation-of-duties rules.
- The hardened Finance lifecycle migration replaces, rather than layers over,
  the first-pass correction, discount, credit, installment, gate-exception,
  and refund guards. The active schema has one lifecycle authority per fact
  boundary. Because the migration replaces function bodies as well as trigger
  names, its rollback is intentionally one-way until a reviewed baseline is
  produced; a partial rollback must not leave an old trigger name executing a
  hardened body.
- Payroll settlement proposal closure remains a Payroll-owned transition
  requested through `SettlementProposalApproval`; Finance inserts the matching
  immutable settlement fact first in the same transaction. A failed proposal
  closure rolls the Finance fact back rather than leaving an impossible
  recorded-settlement/proposed-proposal state.
- Console and API routes remain adapters over the same commands; no transport
  is permitted to bypass these decisions.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-scheduling-constraint-boundary.md`

# ADR: Scheduling owns planning constraints; Academic owns delivery facts

**Date:** 2026-09-05  
**Status:** Accepted for static implementation

## Decision

Create a Scheduling constraint boundary inside the modular monolith. Scheduling validates:

- active class lifecycle;
- class branch provenance;
- valid session time window;
- active teaching skill;
- open section belonging to the class;
- available room belonging to the class branch.

Academic Delivery remains the only writer of `class_sessions` and remains responsible for delivery lifecycle, attendance, assessment, and academic evidence. Database uniqueness/exclusion/locking constraints remain the final concurrent race arbiter.

## Rejected alternatives

- Keeping every planning constraint embedded in `Academic\\Commands\\MaintainClass`: rejected because it makes Scheduling a hidden Academic responsibility.
- Adding a second session/schedule table: rejected because it duplicates the delivery fact.
- Letting the frontend resolve conflicts: rejected because UI checks are advisory only.

## Implementation

`App\\Modules\\Scheduling\\Domain\\SchedulingConstraints` is called from `MaintainClass::scheduleSession` before the existing Academic session write. The service reads canonical Academic/Organization facts but does not write them. Existing Academic section/skill management remains in Academic because those are their respective catalog/fact authorities.

Enrollment remains a separate unresolved boundary: the existing `enrollments` authority and command are still in Academic, and no duplicate enrollment table was added in this static slice.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-transactional-domain-events-and-delivery.md`

# ADR: transactional domain events are distinct from audit and endpoint delivery

- **Status:** Accepted
- **Date:** 2026-09-05
- **Supersedes:** the implicit assumption that `audit_events` or `integration_deliveries` alone can serve both domain-event publication and delivery retry concerns

## Context

Successful commands already wrote append-only `audit_events` in their owning transaction. `integration_deliveries` was the only outbox-like queue, but it is endpoint-scoped: its uniqueness, payload digest, retry, dead-letter, and delivery evidence are about sending one contract to one endpoint. It cannot represent a durable domain event once, independent of endpoint fan-out. Audit evidence also includes denied attempts and must not be interpreted as a business event stream.

## Decision

Create `domain_events` as an immutable transactional event log. A successful operation writes its material fact, audit evidence, and one domain event in the same transaction. `AuditRecorder` invokes `TransactionalEventRecorder`; denied operations remain audit-only. The event carries the audit source, actor, aggregate, operation/version, correlation, payload, digest, and explicit scope/provenance context; legacy events are backfilled as unknown rather than treated as global.

Commit is the publication boundary. The recorder never performs network I/O. A later projector may create endpoint-specific `integration_deliveries` rows with deterministic idempotency. Delivery progress remains mutable only within its own guarded state machine.

## Consequences

- Rollback must remove the material fact, audit row, and domain event together.
- Integration fan-out no longer changes domain truth.
- Event consumers can be retried without rewriting audit history.
- The repository now has a real event boundary, an allowlisted at-least-once relay, consumer receipts, explicit event context, and projection invalidations; concrete projection rebuilders, external subscriptions, and runtime guarantees remain to be implemented and validated.
- Existing denied-operation audit evidence does not produce false business events.

## Rejected alternatives

1. **Use `audit_events` as the bus:** rejected because denials are evidence and audit schema is not a consumer contract.
2. **Use `integration_deliveries` as domain truth:** rejected because endpoint delivery is not a domain fact and one event may have many endpoint deliveries.
3. **Dispatch synchronously from commands:** rejected because network failure would couple business commit to an external system and break rollback/failure isolation.

## Validation required later

PostgreSQL append-only triggers, transaction rollback, duplicate command idempotency, projector fan-out, endpoint retries, dead-letter requeue, ordering, and payload digest behavior require runtime validation.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-unified-platform-architecture.md`

# ADR: Unified TOEFL House Platform Architecture

- **Status:** Accepted target architecture; implementation and runtime validation incomplete
- **Date:** 2026-09-05
- **Scope:** Full product architecture and authority reconciliation
- **Detailed report:** `docs/architecture/2026-09-05-unified-platform-reconciliation.md`
- **Comparison:** `origin/arena/01a03298-toefl-house`

## Decision

Build a third architecture rather than preserving either source implementation:

- Laravel modular monolith for backend application boundaries;
- PostgreSQL for transactional persistence and financial integrity;
- React/TypeScript frontend for administrator, reception, teacher, management, finance, operations, and student experiences;
- one authoritative owner per business concept;
- Finance as the sole monetary authority;
- append-only source-linked financial facts with compensating corrections;
- transactional outbox for integration and automation;
- centralized AccessDecision and fail-closed organization/branch scope;
- lifecycle commands with one transition graph per aggregate;
- read-only reports/projections with independent financial invariant auditing;
- first-class Employee Workspace and management workspaces dynamically composed from effective identity, authority, scope, lifecycle, assigned work, deadlines, approvals, notifications, context, and exceptions, without becoming a shadow authority;
- the mandatory, non-negotiable Integrated Enterprise System Architecture Graph at `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md` as the connected conformance target for every domain, edge, workflow, projection, event, document, notification, search, report, management, and failure path.

System A's broad ERP capabilities, React product surface, financial subledgers, invariant checker, readiness/backup posture, placement engine, and workflow concepts are selected as capabilities. Its SQLite database and route-level implementation are not selected. System B's PostgreSQL-oriented schema, modular domain commands, access/lifecycle controls, provenance, idempotency, audit, and correction architecture are selected as foundations. System B's missing operational capabilities are implemented within the final boundaries rather than by copying System A's backend.

## Authority consequences

- Payroll calculations and settlement proposals are workflow evidence only.
- Finance `employment_settlements` is the sole recorded employment-settlement fact.
- The Payroll `FinalSettlement` model and `final_settlements` runtime table are removed by migration `2026_09_05_000143_consolidate_employment_settlement_authority.php`.
- No report, dashboard, account cache, automation handler, import, or controller may create a competing monetary fact.
- Unknown/null branch scope is denied, not treated as organization-wide.
- A historical or terminal lifecycle state cannot be bypassed by a generic update endpoint.
- Workspace visibility is not authorization; every workspace-originated sensitive action is reauthorized server-side at command time.
- Multiple legitimate positions are composed into work-first, exception-first work environments rather than static role locks.

## Supersession

This ADR supersedes any source-system assumption that either implementation, database, UI, route layout, or compatibility model must be preserved. It supersedes the historical Payroll compatibility part of `2026-09-05-production-authority-and-correction.md`; that ADR remains valid for provenance and compensating-correction semantics.

Existing migrations that create the retired table remain historical migration records. They are not the final runtime schema after migration `000143`.

## Rejected alternatives

1. **Keep System A unchanged:** rejected because SQLite/single-schema deployment and route-level implementation are insufficient as the final multi-user financial backend.
2. **Keep System B unchanged:** rejected because it lacks the complete product experience and several valuable financial/academic/operational capabilities.
3. **Merge both backends:** rejected because it creates duplicate identity, organization, finance, reporting, lifecycle, and authorization authorities.
4. **Keep Payroll settlement as a read-only compatibility model:** rejected for the final redesign because the user explicitly permits schema redesign and a runtime compatibility concept invites future duplicate writes.
5. **Use frontend restrictions as security:** rejected; every server command remains authoritative.
6. **Call one generic or static role dashboard an employee workspace:** rejected; the target is a dynamically composed operational work environment with direct canonical workflow entry and exception visibility.

## Consequences

Positive:

- one monetary truth and one authority per concept;
- stronger relational concurrency and recovery posture;
- broader role-complete product experience;
- explicit migration path for removing duplicate authorities;
- clear separation between facts, workflow evidence, projections, and integrations;
- an operational employee experience that composes canonical work without duplicating domain authority.

Costs and risks:

- substantial contract and schema migration work;
- React frontend/API integration work;
- PostgreSQL deployment and restore validation;
- capability ports must be redesigned rather than copied;
- no production-readiness claim is possible until deferred runtime and independent review gates pass.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-05-work-management-boundary.md`

# ADR: Work Management coordinates; domains decide

**Date:** 2026-09-05  
**Status:** Accepted for static implementation  
**Scope:** workflow instances, actionable work items, assignment, lifecycle, and workspace orchestration

## Decision

Add a small Work Management module as the canonical owner of coordination state:

- workflow instance lifecycle;
- distinct `task`, `approval`, and `exception` work-item kinds;
- assignment to an actor or governed queue, with explicit time-bounded queue membership for claims;
- due date/priority fields;
- claim/in-progress/completed/cancelled/expired coordination lifecycle;
- append-only work-item history;
- source type/id/action key and optional source version;
- workflow-to-source correlation;
- persisted organization provenance for every workflow and work item, with optional branch provenance.

Work Management **does not** own:

- the approval decision or exception amount;
- enrollment, admission, academic, payroll, Finance, permission, or notification state;
- the source aggregate lifecycle;
- authorization to execute a source command;
- message delivery or read state.

Completing a Work Management approval item means only that the coordination item was completed. The linked `action_key` must lead to the owning domain command, which rechecks current authorization, lifecycle, separation of duties, provenance, and concurrency.

Event-projected workflow intents are the default path for source-domain producers. `StartWorkflow` remains only as an explicit operator/recovery command for a governed source; it cannot transition the source fact and is not a second workflow authority. If a compatible manual instance exists when its source event arrives, projection binds that instance to the source event; conflicting scope or event ownership fails closed.

## Why

The prior repository had domain-specific staged approvals but no coordination authority, so the Workspace either had to query every domain directly or risk inventing a generic task table without semantics. The target architecture distinguishes Task, Approval, WorkItem, Exception, and Notification. A coordination module closes the missing boundary without creating a shadow business authority.

## Invariants

1. Every work item has a source type/id and action key.
2. Every workflow and work item has explicit organization provenance; branch provenance is optional only for an explicitly organization-scoped item.
3. Branch and organization provenance must agree through active campus topology; branch actors cannot discover branchless organization items.
4. Every work item has an actor or queue assignment.
5. Work-item history is append-only.
6. Work-item transitions are idempotent and locked; terminal items cannot be reopened through the generic path.
7. A domain action is never executed by `MaintainWorkItem`.
8. Domain commands remain responsible for source authorization and state transition.
9. Workspace may show work items but cannot change them except through the server command boundary.
10. Notifications remain a separate Communication recipient/read projection and never become a work item.
11. A direct assignee must be an active employee with `workflow.work` authority in the item scope; queue membership is not a substitute for source authorization.
12. Actor, branch, organization, and creator references in coordination, history, queue-membership, and notification tables are database-constrained; workflow/item, history/item, and queue-membership branch/organization provenance is database-checked; a null queue branch is one explicit organization scope, never a global wildcard; source-version freshness remains an explicit producer/consumer contract rather than an inferred timestamp.

## Implementation evidence

- `app/Modules/WorkManagement/Domain/WorkflowCatalog.php`
- `app/Modules/WorkManagement/Domain/WorkItemLifecycle.php`
- `app/Modules/WorkManagement/Domain/WorkQueueCatalog.php`
- `app/Modules/WorkManagement/Models/WorkflowInstance.php`
- `app/Modules/WorkManagement/Models/WorkItem.php`
- `app/Modules/WorkManagement/Models/WorkItemHistory.php`
- `app/Modules/WorkManagement/Commands/StartWorkflow.php` (explicit operator/recovery bridge only)
- `app/Modules/WorkManagement/Commands/MaintainWorkItem.php`
- `app/Modules/WorkManagement/Domain/WorkflowProjectionConsumer.php`
- `app/Modules/WorkManagement/Domain/WorkflowCompletionConsumer.php`
- `app/Modules/WorkManagement/Queries/WorkItemQuery.php`
- `app/Modules/WorkManagement/Queries/QueueMembershipQuery.php`
- `app/Modules/WorkManagement/Commands/MaintainQueueMembership.php`
- `routes/api.php` work-item and queue-membership routes
- `EmployeeWorkspaceQuery` composition of assigned/claimable work items
- migrations `2026_09_05_000148_create_work_management_coordination.php`, `2026_09_05_000151_create_work_queue_memberships.php`, and `2026_09_05_000152_add_work_organization_provenance.php`

Runtime migration, authorization, concurrency, API contract, and browser verification remain deferred by the governing runtime restriction.

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-06-finance-fixed-point-money.md`

# ADR: Finance accepts one fixed-point money representation

**Date:** 2026-09-06  
**Status:** Accepted for static implementation  
**Scope:** Finance monetary command boundaries and HTTP validation

## Decision

Ordinary Finance monetary inputs use the canonical two-decimal fixed-point representation accepted by `MoneyAmount`: unsigned digits with up to twelve integer digits and zero, one, or two decimal places. Signed values are limited to the explicit signed Payroll-adjustment boundary. Finance command validation and the HTTP `money`/`signed_money` rules use this same representation.

Commands must compare monetary values with BCMath at scale two. Binary floating-point conversion is not a valid authority for acceptance, positivity, non-negativity, or variance decisions.

## Why

The database stores `decimal(14,2)`, while several Finance commands previously used binary floating-point comparisons before insertion. That could accept scientific notation or third-decimal values that later round differently at the database boundary and could make variance decisions dependent on floating-point behavior. A shared boundary keeps transport, command, and storage semantics aligned without creating a second monetary authority.

## Invariants

1. Finance never accepts a value that cannot be represented by its ordinary two-decimal `decimal(14,2)` columns.
2. Positive, non-negative, and signed-money decisions use fixed-point comparison, never `(float)` conversion.
3. Reconciliation variance explanation requirements compare expected and observed values at the stored two-decimal scale.
4. Finance remains the sole monetary authority; this decision changes representation validation only, not ownership or lifecycle.
5. Runtime tests, database rounding behavior, and migration verification remain deferred under the repository runtime restriction.

## Implementation evidence

- `app/Support/MoneyAmount.php`
- `app/Support/Providers/AppServiceProvider.php`
- Finance monetary commands under `app/Modules/Finance/Commands/`
- `app/Modules/Finance/Domain/OpeningEntryContract.php`
- `docs/architecture/review/2026-09-05-fourth-architecture-convergence.md`

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-06-financial-coverage-serialization.md`

# ADR: Serialize student-level Finance coverage decisions

**Date:** 2026-09-06  
**Status:** Accepted for static implementation  
**Scope:** Finance coverage-affecting approvals, allocations, corrections, obligation posting, and Academic enrollment activation

## Decision

Finance coverage decisions use one canonical database lock: the authoritative `students` row for the affected student. A coverage-affecting command acquires this row with `FOR UPDATE` inside its transaction before it derives an uncovered balance or records the decision. `FinancialCoverageLock` is the shared application boundary; it is not a second balance authority and it does not replace command authorization, lifecycle, provenance, or idempotency checks.

The boundary covers obligation posting, payment and fund allocations, discount approval, correction approval, credit approval, installment-plan approval, gate-exception approval, and Academic enrollment activation. Financial corrections resolve the student through their obligation, payment-allocation, or fund-allocation source. Paths that also lock command or source rows acquire the student lock first where the student can be resolved before those row locks, reducing cross-command deadlock risk.

## Why

The uncovered-balance decision is a student-level derived fact, while its inputs are spread across immutable obligations and compensating or coverage facts. Locking only the individual approval or source row permits two concurrent commands for the same student to each observe the same uncovered remainder and both pass an amount check. A single student-row lock provides one serialization point without introducing a Finance-owned cached balance or shadow aggregate.

## Invariants

1. `FinancialBalanceQuery` remains the only derived-balance authority.
2. Coverage commands must acquire the canonical student lock before deriving and committing a competing decision.
3. The lock is held only inside the existing transaction; a caller outside a transaction does not receive an implicit durability guarantee.
4. Authorization, branch/organization provenance, lifecycle, separation of duties, and idempotency are still rechecked by the owning command.
5. Runtime lock behavior, transaction scope, deadlock detection/retry policy, query plans, migration compatibility, and concurrent test evidence remain deferred.

## Implementation evidence

- `app/Modules/Finance/Domain/FinancialCoverageLock.php`
- Coverage-affecting Finance commands under `app/Modules/Finance/Commands/`
- `app/Modules/Academic/Commands/MaintainEnrollment.php`
- `docs/architecture/review/2026-09-05-fourth-architecture-convergence.md`

---

## HISTORICAL SOURCE: `architecture/decisions/2026-09-06-payroll-held-resolution.md`

# ADR: Held Payroll calculations require explicit evidenced resolution

**Date:** 2026-09-06  
**Status:** Accepted for static implementation  
**Scope:** Payroll calculation exceptions and Work Management completion

## Decision

A `held` Payroll calculation is a source exception, not a silently supersedable live row. A later calculation may replace a prepared calculation automatically, but it must not close a held predecessor merely because recalculation occurred.

A held predecessor becomes terminal only through `ResolveHeldPayrollCalculation`, which must name:

- the same-period, same-employment prepared or resulted replacement calculation;
- a non-empty resolution/evidence reference; and
- the durable resolver actor, authorized by `payroll.resolve_held` at the employee's active home-branch organization scope and distinct from both the beneficiary and replacement preparer.

The transition is `held → superseded`; a held calculation can never become a payable result. The replacement remains the canonical current calculation, while the predecessor remains immutable history with explicit resolution provenance.

## Why

The previous partial unique index treated `held` as the same live state as `prepared` and caused recalculation to supersede the exception without recording whether HR/Finance had reviewed the source reason. That erased the distinction between a new calculation and an exception decision and left the Work Management exception without a governed completion contract.

## Invariants

1. Recalculation supersedes prepared rows only.
2. A held row remains visible and blocks Payroll period closure until explicitly resolved.
3. Resolution evidence is append-only provenance on the held predecessor; it cannot be supplied on calculation insertion or attached to another lifecycle transition.
4. The database guard requires a distinct same-period/employment replacement in `prepared` or `resulted` state.
5. Payroll remains the calculation/exception authority; Finance remains the monetary authority.
6. Work Management may close only the rebuildable coordination item after the Payroll resolution source event; it never mutates the calculation.
7. Runtime queue, relay, authorization, concurrency, and migration verification remain deferred under the repository runtime restriction.

## Implementation evidence

- `app/Modules/Payroll/Commands/ResolveHeldPayrollCalculation.php`
- `app/Modules/Payroll/Commands/CalculatePayroll.php`
- `app/Modules/Payroll/Domain/PayrollLifecycle.php`
- `app/Modules/Payroll/Models/PayrollCalculation.php`
- `app/Modules/WorkManagement/Domain/WorkflowCompletionCatalog.php`
- `database/migrations/2026_09_06_000157_resolve_held_payroll_calculations.php`
- `app/Http/Controllers/Api/PayrollApiController.php` and `routes/api.php`
- `docs/architecture/review/2026-09-05-fourth-architecture-convergence.md`

---

## HISTORICAL SOURCE: `architecture/decisions/WP2-approved-decisions.md`

# WP-2 — Approved Architecture Decisions

**Status:** APPROVED — records the WP-1.5 decision package acceptance and authorizes WP-2 foundation implementation.
**Approval date:** 2026-09-03
**Authorizing input:** WP-1.5 Architecture Decision Package (`docs/implementation/WP-1.5-architecture-decision-package.md`), owner-approved.
**Branch:** `arena/01a062e3-toefl-house`

These decisions are formally approved architecture decisions. Their authority, consequences, and invariants are as described in the WP-1.5 package and summarized below. WP-2 implementation is authorized **only** to the foundation scope (F1–F4 + S1) described here and in the WP-2 execution rules. Downstream WP-3 (academic), WP-4 (financial), WP-5 (experience) capabilities are **not** authorized by this record.

---

## Decision ledger

### WP2-DEC-01 (F1) — Multi-branch operational provenance & financial branch semantics
- **Approved choice:** Immutable `originating_branch_id` + nullable designation `current_home_branch_id` on branch-originating operational records; a typed relational `branch_scope_links` junction with real foreign keys for cross-branch affected-scope propagation. **No fabricated historical backfill.** Home branch is a designation and **never becomes financial truth.**
- **Rejected alternatives:** (a) a single `branch_id` used as both provenance and financial truth; (b) bulk-backfilling ambiguous records to an arbitrary branch; (c) branch-awareness only through untyped scope grants.
- **Rationale / consequences:** Enables D-F-014 (post-transfer historical attribution immutable) and per-branch finance/reporting without a second ledger. Unknown/ambiguous provenance stays a first-class `NULL`/`unassigned` state; provenance is immutable after write.
- **Dependencies:** F3 (offerings per branch), F4 (term hosting), Access (scopes).

### WP2-DEC-02 (F2) — ProgramVersionLevel / CEFR
- **Approved choice:** `ProgramVersionLevel` is the authoritative academic level/version model — an ordered level child of an immutable `ProgramVersion`, optional CEFR reference. Classes re-point to a level (nullable backfill for pre-existing rows only).
- **Rejected alternatives:** free-text level strings; levels shared globally across versions.
- **Rationale / consequences:** Level becomes the target for progression (G2-D-003), placement, and fee packaging while preserving program-version immutability.
- **Dependencies:** F3, WP-3 G/J.

### WP2-DEC-03 (F3) — BranchAvailability + Term + Offering
- **Approved choice:** Co-dependent `Offering + BranchAvailability + Term` model with relational constraints; enrollment targets an **Offering**.
- **Rejected alternatives:** independent linear tables with no mutual constraint; availability folded into an enum flag.
- **Rationale / consequences:** An enrollment exists only when a matching open offering exists; availability is branch-scoped; the offering (branch × level × term) is the packaging unit finance consumes.
- **Dependencies:** F1 (branch), F2 (level).

### WP2-DEC-04 (F4) — Calendar authority / Shamsi–Gregorian
- **Approved choice:** **Shamsi-first business semantics with a single canonical Gregorian stored date and authoritative, versioned Shamsi derivation.** No dual stored date truths.
- **Rejected alternatives:** Gregorian-only business truth; dual-calendar authority storing two canonical dates.
- **Rationale / consequences:** Solar Hijri is the operational business calendar; storage and DB range arithmetic stay on one canonical Gregorian date; all Shamsi display/derivation is a pure function of the canonical date plus a ratified calendar-algorithm version, keeping snapshots reproducible.
- **Dependencies:** all period authorities and print/report snapshots.

### WP2-DEC-05 (S1) — Governed configuration
- **Approved choice:** Typed, versioned, audited `governed_configs`. Configurable: approval thresholds/limits, expense-approval routing, annual-review cycle. **Hard-coded invariants:** separation of duties, default-deny, financial immutability/cap rules, calendar scheme, provenance immutability.
- **Rejected alternatives:** a free-form `settings` key/value blob; hard-coding all thresholds.
- **Rationale / consequences:** Ratifies OPEN governance thresholds/limits with fail-closed-on-absence behavior and append-only effective versions; every change is an audited, authorized event.
- **Dependencies:** Audit; downstream WP-4 M.

---

## F4-A Calendar Authority ratification (D1–D4) — recorded 2026-09-03

The architecture owner ratified the following decisions that operationalize
**WP2-DEC-04 (F4 / G2)** by fixing the authoritative Solar Hijri reference
series and civil-clock rule, and that flip the F4-A verification gate from
`BLOCKED` to `VERIFIED`. These ratifications **do not alter** WP2-DEC-04 (G2) —
Shamsi-first business semantics over a single canonical Gregorian stored date
with authoritative, versioned Shamsi derivation remains the approved decision.
Recorded here for auditability; the full series data, vectors, and provenance
live in `docs/implementation/WP-2-F4A.3-solar-hijri-reference-series-ratification.md`
and the F4-A record (`WP-2-F4A-calendar-authority-verification.md`, §F4-A.4).

- **D1 — Operational range.** Active operational window SH 1399–1415
  (~2020–2037); full supported deterministic range SH 1336–1425 (1957
  fixed-structure → ~2047); pre-1336 dates fail closed / require manual
  handling; no fabrication of historical calendar or branch/date provenance
  outside verified evidence.
- **D2 — Reference civil clock.** Kabul local civil time, **AFT = UTC+04:30**,
  is the TOEFL House Calendar Authority reference clock, with the documented
  noon-cutoff rule applied against Kabul civil time. **This is a TOEFL House
  product/architecture decision, not a claim that a currently published Afghan
  government source mandates this exact computational rule.** The 1408
  divergence (Nowruz 2029: Kabul noon ⇒ 1 Hamal 1408 = 2029-03-21, where a
  Tehran-noon rule would give 2029-03-20) is preserved as the documented reason
  D2 matters.
- **D3 — Reference-series authority.** The annual equinox/reference series in
  the F4-A.3 spec is ratified as the **version-1 reference dataset** and is
  authoritative for supported dates. Any arithmetic algorithm (33-year,
  2820-year, ICU/Persian, generic Jalali) is only an implementation mechanism
  that **must** be validated against the ratified reference series — never the
  sole authority.
- **D4 — Acceptance vectors.** F4-A.3 vectors **T01–T17** are the initial
  acceptance/test-vector set with provenance tags (EQUINOX / DERIVED /
  ATTESTED / REQ-D2); under D2 the REQ-D2 rows resolve to the Kabul branch
  (T12 = 1 Hamal 1408 = 2029-03-21). Implementation must satisfy the vectors
  plus the round-trip invariants.

**F4-A status:** `F4-A VERIFIED` (F4 production implementation remains a
separate, later phase and remains **pending**; this record authorizes no code or
schema change and does not implement the Calendar Authority).

---

## F4-C active-window completion ratification (2026-09-04)

The architecture owner ratified **`1 Hamal 1416 = 2037-03-20`** as a version-1
Calendar Authority anchor (2037 vernal equinox `2037-03-20 06:50 UTC`; 11:20 AFT,
before Kabul noon under D2). This completes the D1 active operational window
**SH 1399–1415** without expanding the supported deterministic range
**SH 1336–1425**. The 1416–1425 and 1336–1398 tails remain **not ratified** and
must continue to fail closed until separately pinned and ratified under F4-B
§2.5. No other F4 decision or Calendar Authority boundary is changed.

---

## Authorization statement

The five decisions above are APPROVED and **authorize WP-2 foundation implementation** (schema, migrations, models, domain invariants, authorization/scope behavior, focused tests, and architecture/implementation documentation) strictly within the WP-2 foundation scope and the WP-2 execution rules. Any implementation detail that would require changing an approved decision must STOP and request a new architecture decision.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-appeal-resolution-semantics.md`

# WP-ACAD-APPEAL-RESOLVE — Appeal resolution semantics

**Status:** approved for implementation · **Date:** 2026-09-05
**Trigger:** independent red-team finding — appeal `resolve()` was terminal theater
(appeal row flipped to `resolved` with zero defined effect on, or linkage to,
the contested subject), and `file()` accepted nonexistent subjects and
wrong-student bindings.

## Context

The appeal registry (`academic_appeals`, `AppealLifecycle`) tracks a grievance:
`open → assigned → investigating → resolved / rejected / escalated → closed`.
Remediation primitives already exist under their OWNING authorities and are
console-wired:

- assessment results: `released → appealed → corrected` and
  `released → corrected` (`AssessmentResultLifecycle`); `proposeCorrection` +
  `approveCorrection` (moderator + approver, distinct actors) exist, but NO verb
  performs `released → appealed` — the state is unreachable;
- progression decisions: `approved|rejected → appealed → superseded`
  (`ProgressionLifecycle`); `markAppealed` (conscious act) and `supersede`
  (reviewer + approver, independence-enforced, new decision row, original kept
  in history) exist and are console-wired;
- placement profiles: `released → superseded` (`DecidePlacement::supersede`);
  a retake requires the old profile to leave the open set first
  (`placement.profile_open_exists`), so the retake path also passes through
  `superseded`/`retired`.

What never existed: the LINK between the registry verdict and the subject, and
any verification of the subject at filing time.

## Decision

**`resolved` MEANS: the grievance was upheld AND redress is recorded on the
contested subject. `rejected` MEANS: no merit; the subject stands untouched.**

Concretely:

1. `resolve()` (→ `resolved`) is REFUSED unless the locked subject already
   carries its remediation marking:
   - `assessment_result` ∈ {`appealed`, `corrected`},
   - `progression_decision` ∈ {`appealed`, `superseded`},
   - `placement_profile` ∈ {`superseded`, `retired`}.
   `appealed` = remediation ordered and pending (an honest, visible state);
   `corrected`/`superseded`/`retired` = remediation completed. Resolving an
   untouched subject is rejected with `academic.appeal_subject_untouched`.
2. `reject()` (→ `rejected`) needs no subject effect — the subject standing IS
   the outcome. `escalate()`/`close()` are unchanged bookkeeping.
3. The missing result verb is added: `ManageAssessmentResult::markAppealed`
   (`academic.moderate`, released → appealed, audited) — the conscious,
   attributable act that orders remediation, mirroring progression's
   `markAppealed`. It is the SOLE writer of `appealed` on results.
4. `file()` verifies before creating: subject row EXISTS, is in an appealable
   state (`released` result; `approved|rejected` progression; `released`
   placement profile), and belongs to the appeal's student (for placement the
   student is derived from the profile, as today). Violations are
   `BusinessRejection` (`academic.appeal_subject_unknown`,
   `academic.appeal_subject_not_appealable`,
   `academic.appeal_subject_student_mismatch`).
5. `assign()` fails fast when the reviewer cannot act: the reviewer must hold
   `academic.appeal_manage` in the SUBJECT's branch scope (this also implies
   person existence — a nonexistent person holds no grants). The original
   decision-maker exclusion is unchanged.
6. Separation of duties is PRESERVED, not flattened: the reviewer decides
   (appeal verbs); record owners remediate (result/progression/placement
   verbs with their existing capabilities and independence rules). `resolve()`
   VERIFIES redress; it never performs it. No second authority over any
   subject row is created.

## Why not resolve-performs-remediation

Performing remediation inside `resolve()` would require stuffing two actors
(supersede needs reviewer + approver), remediation vocabularies (advance /
repeat, new scores), and three owning commands into one call — collapsing the
review/approve separation the lifecycle machines were built to enforce. The
precondition design keeps one code path per subject mutation and makes
"resolved but untouched" unrepresentable.

## Consequences

- Console flow becomes investigate → remediate (Mark appealed / Supersede /
  Propose+Approve correction, by authorized owners) → resolve → close. The
  denial message on premature resolve names the required prior step.
- Existing tests that resolve appeals against untouched subjects encode the
  old theater behavior and must be updated to remediate first (intended
  behavior change).
- Branch scope for every appeal verb is the SUBJECT's branch (separate
  branch-isolation decision); `file()` derives it from the verified subject,
  later verbs from the locked appeal's subject.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-branch-scope-doctrine.md`

# WP-ACAD-SCOPE — Academic branch-scope doctrine

**Status:** approved for implementation · **Date:** 2026-09-05
**Trigger:** independent red-team finding — every core Academic command
resolved authorization with `scope: null`, and `AccessResolution` treats null
scope as "any grant of the capability suffices", so a branch-narrow grant
authorized any branch's records. The access model intends the opposite:
direct grants are branch-narrow by construction (`GrantScopePermission`
refuses organization scope without the staged org-wide approval chain), and
Placement already enforces record-branch scope correctly.

## Root cause (not a controller bug)

Scope was defaulted to `null` at ~30 call sites across all 13 Academic
commands. No controller, route, or query can fix that: commands own
authorization (all transports funnel through them), so the repair belongs in
the commands — one choke point per verb, unbypassable by construction.

## Decision

**Scope follows the target's owning branch, resolved server-side from locked
rows or verified inputs — never from client-supplied branch fields, never
from the actor (the actor carries no branch). The single authority stays
`AccessDecision`; modules only resolve scope.**

1. **Two tiers, matching the data model:**
   - DELIVERY layer (branch-bound): availability, offering, room, enrollment,
     attendance, assessment attempt/result/correction, waitlist, progression,
     graduation, transcript, appeal, and every printable/API-readable
     artifact. Verbs resolve the target branch and pass its `StructureScope`
     (via `Branch::structureScope()`) to `decide()`.
   - GOVERNANCE layer (branchless by design): programs, versions, levels,
     periods, skills, classes, session scheduling. These records carry no
     branch anywhere (form, command, table); curriculum definition is
     organization-global, so the global check is CORRECT here and is kept
     explicitly — not by accident. Grant hygiene for structure capabilities
     (issue org-wide through the approval chain) is an operator duty,
     recorded here.
2. **Branch derivation per target** (first hit wins; stored provenance beats
   live derivation; nothing is fabricated — derivation only reads linked
   rows):
   - availability / offering / room: own `branch_id` (non-null — always scoped).
   - enrollment: own `current_home_branch_id` → own `originating_branch_id` →
     offering `branch_id` → student home/originating → global. `request()`
     STAMPS `originating_branch_id` from offering/student at creation
     (provenance capture from linked records, not fabrication); conversion
     stamps students from the applicant's placement profile branch.
   - attempt / result / correction / attendance fact: via the enrollment chain.
   - waitlist entry: offering branch → student home/originating → global.
   - progression / graduation / transcript / certificate: student
     home (`current_home_branch_id`) → originating → global. A transferred
     student's SEATS stay with their delivery branch (delivery owns the
     seat); new seats scope to the new branch. Deliberate.
   - appeal: the SUBJECT's branch (result → enrollment chain; progression →
     student; placement → profile `originating_branch_id`), resolved from the
     verified/locked subject row.
   - printable/API artifacts: receipt/invoice → obligation/payment
     `originating_branch_id` → student; transcript/certificate/enrollment/ID →
     as above; payroll slip → employment → person `home_branch_id`.
   - unknown branch id on any path → `BusinessRejection` (fail-closed).
3. **Read rule (document production + bulk JSON):** printing all seven
   artifacts and the API read endpoints disclose only records whose branch is
   in the actor's visible set (`ActorBranches`: active branch/campus/org
   grants mapped through the campus-assignment topology, role-derived orgs,
   scoped delegations), or — while provenance is being populated — records
   with NULL branch to actors holding at least one effective authority key.
   Out-of-scope reads are denied (403) and denial-audited; every production
   is audit-logged. NO new read capabilities are invented: no read vocabulary
   exists anywhere, writer capabilities do not map to readers
   (issuer ≠ re-printer), and a parallel read-RBAC would be a second
   authority. Within-branch role separation is an explicit follow-up, not
   part of this repair.
4. **Console HTML lists are NOT re-scoped in this slice** (known residual):
   28 inline list queries with no read-capability context; branch-filtering
   them without the read-RBAC vocabulary would fake complete mediation while
   class-level reads are ill-defined anyway (branchless classes carry
   multi-branch seats — seat-level enforcement is the finer, correct grain
   and IS implemented). Mutations, official documents, and bulk JSON are the
   enforced boundaries.
5. **Topology is part of the contract:** a branch resolves to an organization
   path ONLY through an open campus assignment (`Branch::structureScope()`).
   Org-less branches are manageable solely by exact branch grants
   (fail-closed for org-wide holders — correct). Fixtures and seeders must
   model production topology (branch → campus → organization); bare branches
   in tests were an artifact of the null-scope era.

## Consequences

- A shared `AcademicAccess` scope-resolver (mirroring `PlacementAccess`,
  delegating to the same `AccessDecision`) is threaded through every
  delivery-layer verb; `ActorBranches` + `RecordBranch` back reads.
- Cross-branch negative tests per verb; org-wide holders keep working
  (ancestor covers descendants — valid behavior preserved and tested).
- `TransferStudentHomeBranch` needs no cascade (seat stays with delivery).

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-eligibility-snapshot.md`

# Academic Eligibility Snapshot — Architecture Decision (AC1)

Status: Accepted
Date: 2026-09-04
Scope: Signed, versioned, immutable academic-context snapshot produced by a
released Placement recommendation.

## Context

The Placement Decision System (WP-P) already produces a released
`PlacementProfile` and an immutable `PlacementRecommendation` with an
explainable score snapshot, recommended `ProgramVersionLevel`, and
operational recommended `Offering`/`Class`. The WP-1 gap matrix still reports
the signed `AcademicEligibilityResult` / academic-context snapshot as missing:
downstream systems (Admissions, Enrollment, Academic, Finance) can only see
the fingerprint of what was released; they cannot consume one authoritative,
integrity-signed object that reproduces exactly the academic context the
release was based on.

## Decision

The signed eligibility snapshot is an **Academic authority** fact, created
atomically with a Placement profile's `released` transition. It is never a
separate downstream decision and never changes the role separation of the
existing placement decision chain.

### Entity

`academic_eligibility_snapshots` stores one immutable signed record per
released profile:

- `placement_profile_id`, `placement_recommendation_id`, `person_id`,
  `visitor_id`, and the released recommendation's authoritative identities:
  program version, recommended level, recommended class, recommended
  offering, academic period (when present), and branch provenance.
- `snapshot_schema_version` (contract version, `academic-context-snapshot-v1`)
  and a `version_no` per profile for history.
- `payload` (JSONB) plus `payload_canonical_json` (TEXT) — the exact bytes that
  were signed — and `payload_sha256`.
- `signature_algorithm` `hmac-sha256`, `signature`, `signing_key_version`,
  `signed_by`, `signed_at`, and `supersedes_snapshot_id` for retake chaining.
- DB trigger blocks UPDATE and DELETE after insertion; the row is append-only
  history.

### Signing and verification

- Canonicalization is deterministic: associative keys are recursively sorted;
  lists keep order; strings use `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`.
- `payload_sha256` = SHA-256 over the canonical JSON bytes.
- `signature` = HMAC-SHA256 over that canonical JSON bytes using a key derived
  from `config('app.key')` plus the snapshot contract name. The key is outside
  domain data; only the signature and key version are stored.
- Verification recomputes canonicalization + digest + HMAC and compares with
  `hash_equals`; a rotated key makes old snapshots fail closed (unverifiable,
  never silently accepted).

### Consumption boundaries

- **Admissions**: registration with a `placement_profile_id` requires a
  released profile that has a verifiable eligibility snapshot; the applicant
  stores the snapshot reference. Conversion copies it to the Student record.
- **Enrollment**: a new enrollment row carries the Student's current
  eligibility snapshot reference when one exists; Academic never re-derives
  the recommendation from live catalogs at seat time.
- **Academic**: `PlacementProfile` records its latest eligibility snapshot;
  full snapshot/history queries expose the signed payload and verified state.
- **Finance**: consumption is read-only through the Academic snapshot query
  (the same `PlacementFinanceLinkQuery` lineage). Academic never creates or
  mutates Finance facts from this snapshot.
- A retake creates a new profile; a later release creates a new snapshot whose
  `supersedes_snapshot_id` points to the prior snapshot, preserving an
  append-only per-person chain.

### Capabilities

- Snapshots are produced inside the existing `placement.release` operation;
  no new standalone capability is introduced. Reading/verifying is a read
  query.

## Consequences

- Migration `2026_09_04_000133_add_academic_eligibility_snapshots.php` adds the
  snapshot table, the profile/applicant/student/enrollment snapshot
  references, and the append-only trigger.
- New model: `AcademicEligibilitySnapshot`.
- New domain/support: `CanonicalJson`, `AcademicEligibilitySigner`,
  `AcademicEligibilitySnapshotBuilder`, `AcademicEligibilitySnapshotQuery`.
- `DecidePlacement::release` creates the snapshot transactionally with the
  release. `RegisterApplicant`, `EnrollAdmittedApplicant`, and
  `MaintainEnrollment` carry the snapshot reference.
- Tests cover release/sign/verification, immutability, retake chaining,
  Admissions/Student/Enrollment propagation, tamper detection, and the
  Finance read lineage.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-enrollment-completion.md`

# Enrollment Completion Lifecycle — Architecture Decision (AC5)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-04
**Applies to:** Academic enrollment freeze / unfreeze / withdraw / complete.
**Supersedes:** nothing. Composes with ADR-016 (eligibility snapshot), ADR-017
(enrollment financial gate), ADR-018 (level progression / prerequisites /
history, AC4) and WP-AR (rooms / sections / timetable).
**Related decisions:** WP2-DEC-02 (level is the target for progression),
WP2-DEC-03 (offering is the packaging unit finance consumes), G2-D-003 (no
automatic progression without a program rule — extended here to no automatic
completion).

## Problem

`MaintainEnrollment` owns the seat lifecycle `requested → active ⇄ frozen`
plus terminal `transferred / withdrawn / completed`, but the exit half is
unfinished (WP-1 matrix row H, roadmap WP-3 AC3 remainder):

- `freeze` and `withdraw` take no reason: a seat can be frozen or withdrawn
  with no recorded human justification, and the row carries no trace of why.
- There is **no `unfreeze` command**: the lifecycle registry allows
  `frozen → active`, but no public command exposes it, so a frozen seat is a
  dead end — a frozen student can never return through the domain.
- `complete` takes no basis and no evidence: any approver can mark any active
  seat completed with no attestation and no assessed delivery behind it. On a
  level-aware class this bypasses the entire AC4 chain in spirit — a
  completion with zero released assessment or approved progression behind it
  is not world-class academic truth. `complete` additionally has no console
  route and no callers: the completion half of the lifecycle is unreachable
  end to end (the same dead-end class the PHASE_3 transport audit closed for
  seat request/activation).
- None of freeze / withdraw / complete records the Finance standing at exit:
  the roadmap's "freeze-with-financial-implications" remainder is unaddressed.
  Activation is financially gated (ADR-017), but a seat that freezes while
  unpaid and later returns would re-enter without any re-assessment — a gate
  bypass the moment an unfreeze path exists.

Transfer provenance (F1 command paths, Organization/Access owned) and the
FIN4 per-case aid model (Finance owned) are explicitly **out of scope** here;
they remain WP-2-F1 / WP-4 work.

## Decision

1. **Reasoned freeze / withdraw.** `freeze` and `withdraw` require a
   non-empty human reason (`academic.enrollment_reason_required` otherwise).
   The reason is stored on the row (`enrollments.state_reason`, current-state
   projection) and frozen into the audit event payload. History stays in the
   append-only audit trail; the row carries the current state's reason.
   Capabilities are unchanged: `academic.enroll_approve` for freeze,
   `academic.enroll` for withdraw.
2. **Unfreeze with financial re-gate.** New `unfreeze` command
   (`academic.enroll_approve`): `frozen → active` re-checks student active,
   class active, class capacity and offering capacity, then re-runs the
   Finance gate exactly like activation — fresh signed evidence is frozen on
   the row and an unsatisfied gate refuses the return with
   `academic.enrollment.financial_gate` plus the denied-gate audit. The frozen
   seat holds no capacity claim while frozen (capacity counts `active` seats
   only — unchanged), so a full class refuses the return (`academic.class_full`
   / `academic.offering_full`). `state_reason` is cleared on return.
3. **Finance-read exit snapshots.** Freeze, withdraw and complete embed a
   Finance-authoritative exit snapshot — `satisfied`, `remaining`, digest and
   signature from the existing `FinancialGateQuery::assess` — into their audit
   events. This is read-only consumption through the ADR-017 boundary:
   Academic writes no Finance facts, freezes no second balance ledger, and
   never overwrites the activation gate evidence on the row (that history is
   preserved; exit snapshots live in audit). Refunds/credits/installments
   remain exclusively Finance-owned workflows.
4. **Evidenced completion.** `complete` (`academic.enroll_approve`,
   `active → completed`, terminal) requires an explicit non-empty
   `completion_basis` (human attestation — G2-D-003 extended: no automatic
   completion) and verified evidence pinned on the row
   (`completion_evidence_kind/id`):
   - on a **level-aware class** evidence is mandatory
     (`academic.enrollment_completion_evidence_required`): either a
     `released` assessment result belonging to the enrollment, or an
     `approved` progression decision for the same student and class;
   - on a **legacy (non-level) class** the certified basis-only path is
     preserved: evidence is optional but, when supplied, is verified the same
     way;
   - unknown kinds (`academic.enrollment_completion_evidence_unknown`) and
     foreign evidence (`academic.enrollment_completion_evidence_mismatch`)
     fail closed.
   Completion ordering falls out of the existing invariants: progression
   requires an active/frozen enrollment and results attach to active
   enrollments, so assessed delivery necessarily precedes the terminal mark.
5. **End-to-end integration.** The four transitions are exposed on the
   employee web console (routes + controller + views) with the same
   session-bound actors, idempotency keys and error-code surfacing as the
   request/activate transport. No new JSON API surface: the enrollment
   lifecycle stays console-operated by the request/activate precedent.

## Consequences

- Migration `2026_09_04_000136_enrollment_completion_lifecycle.php` adds
  `state_reason`, `completion_basis`, `completion_evidence_kind` (CHECK) and
  `completion_evidence_id` (paired-null CHECK) to `enrollments`. No trigger:
  reasons are attested text, not monetary invariants; authority stays in the
  commands + audit.
- `ClassRosterQuery` seat rows carry `state_reason` so rosters show why a
  seat is frozen.
- Existing `freeze`/`withdraw` callers pass explicit reasons (2 test
  call sites); no production callers exist outside the commands.
- Tests cover reason/basis requirements, capability separation, unfreeze
  re-gate (deny-then-pay-then-return), capacity interplay, completion
  evidence rules (mandatory on level classes, optional on legacy, foreign
  evidence refused), exit-snapshot audit payloads, and HTTP transport.

## Rejected alternatives

- Silent (reason-free) state changes — rejected; unattributed lifecycle moves
  are not auditable academic history.
- Unfreeze without re-gate — rejected; re-entry without assessment re-opens
  the ADR-017 gate it took activation to satisfy.
- Academic-written refunds/credits on withdraw/freeze — rejected; Finance is
  the sole financial authority (06-financial-architecture, D-G3-001).
- Overwriting the activation gate evidence at exit — rejected; it would
  destroy the activation-time history the gate denial path relies on.
- Auto-completion from a released score or rule alone — rejected; violates
  the G2-D-003 control model extended to completion.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-gradesheets.md`

# Class Gradesheets — Architecture Decision (AC-gradesheets)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-05
**Applies to:** per-class grade compilation reads; teacher-side access to
assigned classes; console presentation of the certified result chain.
**Supersedes:** nothing. Completes the row commanded by the gap matrix
("Class gradesheet: per-class result entry, teacher view, corrections" —
STATUS: GAP) and the transcript ADR's explicit deferral ("Class
gradesheets are transcript consumers, a separate slice").
**Related decisions:** WP-academic-results-accuracy (certified result
chain: submit → score → moderate → approve → release, staged corrections,
SoD exclusions, attestations); prior console slices
(`AcademicController` row-mutation transport); Finance as sole financial
authority (no Finance touch here: gradesheets are reads over academic
history).

## Problem

The result chain is domain-certified and console-operable, but only as
**global work queues** on the academic index: any employee sees every
submitted/scored/moderated/approved result and every open correction
regardless of class. There is **no per-class compilation** (roster ×
attempts × live result × correction lineage), **no teacher scoping** (row
mutations are keyed only on `academic.assess` — the employee flag is
per-row, not per-class), and correction lineage plus released-truth parity
with transcripts/progression is data-present but **unsurfaced per class**.
In production a teacher cannot open "my class's gradesheet", and an
academic officer has no compiler view to confirm a class is fully
entered, moderated, approved, and released.

## Decision

1. **No domain change.** The certified row-level chain
   (`submit/score/moderate/approve/release`, `propose/approveCorrection`,
   attestations, SoD, `corrects_id` supersede-by-reference) stays the only
   mutation path. The gradesheet is a **read compilation**, never a writer.
2. **New read surface: `GradesheetQuery`.** `forClass(Actor, classId)`
   compiles: class header (id, state, capacity, program version, period,
   level if any), open teachers, seats (all enrollment states except
   `requested` — a seat that never joined has no grades), per-seat attempts
   with live result (latest non-`corrected` row, exactly the transcript's
   resolution), full correction history per attempt (including `corrected`
   rows with `corrects_id` + reason), and open corrections. Official lines
   are the released subset — **parity with `TranscriptComposer::results` is
   a tested invariant**, not an aspiration.
3. **Viewer rule (the only new authorization).** A viewer may open a class
   gradesheet iff they hold an **open teacher assignment on that class**
   (identity match `teacher_person_id == actor.actorId`, `effective_to`
   null — in Legacy the teacher assignable IS the subject teacher) **or**
   academic oversight: any of `academic.assess|moderate|approve_result|
   release|structure`. Denials are thrown as `AuthorizationDenied` and
   recorded via the standard `AttemptedOperation::deniedByActor` audit
   (reads deny loudly, exactly like commands).
   `accessibleClasses(Actor)` scopes the index class selector by the same
   rule (assigned classes for teachers; all recent for oversight).
4. **One GET console route + view, mutating only through the certified
   chain.** `GET /academic/gradesheets/{classId}` reuses the existing
   controller redirect/flash/denial handler; the page embeds the existing
   score / propose-correction / release forms keyed by attempt/result id
   (no new commands, no new POST routes). Released truth stays
   visually distinct (official lines) from in-flight work (queues).
   Roster/queue tables already on the index are untouched.

Out of scope: any domain/lifecycle/capability/migration/seeder change;
print/export of gradesheets; derived or stored official marks (released
results remain the single source of truth); Finance consumers (gradesheets
carry no financial meaning).

## Consequences

- Tests prove over HTTP: teacher opens own class, denied elsewhere
  (redirect-home + governed error, audited denial); officer sees all;
  submitted→scored→moderated→approved→released flow reflected per seat;
  correction lineage shown and supersede-by-reference preserved;
  gradesheet official lines equal transcript official lines for the same
  students; released result after seats complete feeds progression.
- No migration, no seeder change, no API change, no new capabilities.

## Rejected alternatives

- Teacher-scoped mutation capabilities (`assess` per class) — rejected;
  row-level SoD + chain order is the certified control, class-scoped
  capabilities would fork the authority model.
- Storing compiled marks on the class/enrollment — rejected; append-only
  academic history forbids derived-state writes, the compilation is a read.
- Class selector reusing the global unscoped class list — rejected; the
  selector is scoped by the same viewer rule so teachers are never offered
  classes they cannot open.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-graduation-integrity.md`

# Graduation Integrity & Certification Outputs — Architecture Decision (AC6)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-04
**Applies to:** graduation decisions, certificate issuance, student alumni
transition, Finance clearance visibility, certificate document governance.
**Supersedes:** nothing. Composes with ADR-016 (eligibility snapshot), ADR-017
(enrollment financial gate), ADR-018 (level progression / prerequisites /
history, AC4) and AC5 (enrollment completion lifecycle).
**Related decisions:** MD-022 (graduation eligibility recommendation),
foundation 30-source-of-truth-registry (Graduation Eligibility Decision +
Certificate; issued record immutable), G2-D-003 (no automatic academic
outcomes), 06-financial-architecture + D-G3-001 (Finance sole authority).

## Problem

AC5 closed the seat-terminal half of the Academic lifecycle. The
student-terminal half is decoupled from it (WP-1 matrix rows H-remainder, J,
U; roadmap WP-3 AC3 remainder):

- `TransitionStudentStatus::graduate()` mints **alumni** with only
  `students.manage` plus free text. The governed `DecideGraduation` chain
  (propose → independent review → independent approval, SoD-enforced) and the
  issued certificate are never consulted: a student can become alumni while
  still seated, with no decision and no certificate behind the mark.
- An `eligible` graduation approval does not check whether the student still
  holds open seats in the program version — approval can certify completion
  while delivery is still in flight.
- Certificates are permanent academic outputs (MD-022: "Certificates and
  diplomas are permanent academic outputs") yet live only as rows plus
  live-derived prints: they never enter the managed-Documents lifecycle, so
  there is no submission → verification → activation governance and no
  independent verification path (MD-022 recommends document verification).
- Graduation and issuance record no Finance standing. MD-022 recommends
  "financial clearance where policy requires it" — but no ratified rule
  refuses graduation on debt, so any clearance input must stay read-only and
  human-consumed, never an invented refusal.

Transfer provenance (F1 command paths, Organization/Access owned), the FIN4
per-case aid model (Finance owned), and official transcripts as a certified
record output remain **out of scope**; they are separate slices.

## Decision

1. **Eligible approval requires closed seats.** `DecideGraduation::approve`
   with outcome `eligible` refuses (`academic.graduation_open_seats`) while
   the student holds a non-terminal (`requested` / `active` / `frozen`)
   enrollment in the decision's program version. `not_eligible` is unaffected:
   recording ineligibility while seated is coherent. `propose` stays
   draft-friendly; the gate bites at the decision point.
2. **Issuance re-checks at the point of no return.** `issueCertificate`
   re-runs the open-seat check: an approval that went stale (a seat opened
   afterwards) cannot produce a certificate.
3. **Finance clearance is read-only visibility, not a refusal.**
   `issueCertificate` embeds a Finance-authoritative student-clearance
   snapshot — `satisfied`, `remaining`, digest, signature — in the issuance
   audit through a new Finance-owned read method
   (`FinancialGateQuery::assessStudent`, same derivation as the enrollment
   gate; seat-scoped exceptions excluded without a seat context). Academic
   never re-derives a balance and never refuses on debt: no ratified rule
   authorizes that, and MD-022 leaves clearance to policy. The signed truth
   is put in front of the human issuer instead.
4. **Every certificate becomes a governed document.** In the same issuance
   transaction, `issueCertificate` registers a managed Document (subject =
   the student person, classification `academic.certificate` resolved by
   category, title = certificate serial, content hash = SHA-256 of the
   canonical certificate payload, storage locator `certificates:{id}`) and
   submits it for verification. The issuer therefore also needs
   `documents.register`; missing Documents capability or a missing
   classification fails closed (`documents.register_denied` /
   `documents.submit_denied` propagate; `academic.certificate_classification_missing`
   when the registrar has not defined the classification). Verification and
   activation stay registrar acts through the existing Documents transport,
   and the uploader≠verifier rule keeps the issuer from self-verifying.
   `certificates.document_id` (nullable, unique) pins the linkage at INSERT,
   which complies with the certificates immutability trigger.
5. **Alumni only through the governed chain.** `TransitionStudentStatus::graduate`
   requires, via the new Academic-owned `GraduationCertificationQuery`, an
   approved eligible graduation decision for the student
   (`students.graduation_decision_required` otherwise) and its issued
   certificate (`students.graduation_certificate_required` otherwise). Status
   history mechanics are unchanged; only the entry guard is added. Students
   owns the transition, Academic owns the graduation truth — the same
   cross-module read direction as the Finance gate.
6. **End-to-end integration on existing transport.** No new routes: the
   graduation, certificate, Documents (register/submit/verify/activate),
   student-status, and certificate-print routes already exist. The registrar
   verifies and activates the submitted certificate document through the
   existing Documents console; the certificate print stays live-derived from
   the immutable issuance row.

## Consequences

- Migration adds `certificates.document_id` (nullable, unique, no FK: the
  document has its own lifecycle; the column is a locator pin like the
  storage reference, set once at INSERT).
- `FinancialGateQuery::assessStudent` is Finance-owned read API; Academic
  consumes it exactly like the enrollment gate assessment.
- Existing `graduate()` callers must now travel the governed chain (test
  call sites updated); the registrar defines the `academic.certificate`
  classification once via the existing Documents console.
- Tests cover the open-seat gate (approve + issuance re-check, `not_eligible`
  exempt), classification-missing fail-closed, document register+submit with
  SoD on verify, clearance snapshot content with and without debt (issuance
  still succeeds), capability separation, alumni gating (decision-then-
  certificate), and HTTP transport of issuance and graduation.

## Rejected alternatives

- Alumni requiring only the approved decision without the certificate —
  rejected; per the source-of-truth registry the certificate is part of the
  graduation truth, and a graduate without the permanent output is
  half-recorded.
- Refusing issuance on unpaid obligations — rejected; no ratified rule
  authorizes it (invented business policy). Read-only signed visibility is
  the MD-022-faithful maximum.
- Academic-written document rows bypassing `RegisterDocument` — rejected;
  that would bypass Documents governance, versioning, audit, and the
  uploader≠verifier SoD.
- Academic-computed clearance from Finance models — rejected; Academic never
  re-derives a balance (ADR-017 boundary).

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-level-progression-and-packaging.md`

# Level Progression, Prerequisites, Academic History & Offering-Linked Fee Packaging — Architecture Decision (AC4)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-04
**Applies to:** Academic progression/prerequisites/history + the finance-facing Academic packaging link.
**Supersedes:** nothing. Composes with ADR-016 (eligibility snapshot) and ADR-017 (enrollment financial gate).
**Related decisions:** G2-D-003 (no automatic progression without a program rule), WP2-DEC-02 (level is the target for progression/placement/fee packaging), WP2-DEC-03 (offering is the packaging unit finance consumes).

## Problem

ProgramVersionLevel and Offering exist, but levels are not yet consumed by progression, prerequisites, academic history, or finance-facing packaging. `ProgressionDecision` is class-scoped with only `advance`/`repeat`; there is no record of which level a student advanced from/to, no prerequisite or repeat/advance policy, no immutable per-level history fact, and no academic link on the financial obligation that would make a charge level-aware.

## Decision

1. **Academic owns level prerequisites and level progression rules** as configuration under the existing `academic.structure` capability. They are active/retired, audited, and never a derived projection.
2. **No automatic progression remains (G2-D-003 persisted).** The student never moves because of a score or report. Rules are preconditions that an Academic decision-maker must still consciously propose, review, and approve; violations fail closed and the decision records the basis/evidence.
3. **Progression becomes level-aware when its class/offering targets a level.** `progression_decisions` records `from_level_id`, `to_level_id`, `repeat_count`, `basis`, and an optional `assessment_result_id`. `advance` moves to the next level ordinal in the same program version; `repeat` stays at the same level. A level-aware `advance` past the last level is not legal here — completion/graduation is a separate decision.
4. **Immutable academic history.** An approved level-aware decision produces one immutable `level_progress_facts` row (student, program version, from/to level, class, offering, period, decision, optional result, outcome, repeat_count, achieved_at). Corrections to progression are existing appeal/supersede flow; the superseded original remains.
5. **Prerequisites are enforced at enrollment request and rechecked at level-aware progression.** A level's active prerequisites are satisfied when the student has an approved `level_progress_fact` with `outcome = advance` for each required level, or a released placement snapshot recommends that level/offering (placement-based placement override). Academic refuses the request when prerequisites are unsatisfied.
6. **Repeat/advance rules.** A per-level `level_progression_rule` may define a `minimum_passing_score` (required passed result for an advance) and `max_repeats` (a repeat beyond the cap is refused). Absence of a rule is not permission to invent a boundary — it means the Academic decision-maker decides without that numeric gate, still fully audited.
7. **Finance remains the sole financial authority; Academic stores no monetary truth.** The only financial-facing extension is the *Academic packaging link*: `obligations.offering_id` (nullable, Finance-owned, immutable after posting) references the Academic `Offering` (branch × level × term) that the charge belongs to. `PostObligation` validates the offering exists, is not cancelled, and belongs to an active enrollment of that student. Fee definitions/categories/amounts stay in Finance; Academic never produces an amount.
8. **Read-only consumers.** `AcademicHistoryQuery` and a level-aware packaging read model expose approved academic history and the current/latest level to Student, Placement, Enrollment, Documents, Reporting, and Finance for validation — they never become financial truth and never decide status.

## Consequences

- Existing pre-level classes keep NULL levels and the legacy class-scoped progression path (no level facts, no prerequisite gate).
- Prerequisite configuration is additive and fail-closed when it exists; no fabricated backfill for pre-existing students.
- Level-aware decisions are auditable, appealable, and historically reconstructable; supersession preserves the original fact.
- Finance obligation posting gains a validated academic packaging reference without a second accounting engine.
- Reporting/Documents consume verified history facts; a report cannot redefine progression.

## Rejected alternatives

- Auto-advance from a released score or rule alone — rejected; violates G2-D-003 and the frozen Academic control model.
- Storing fee amounts/categories/payment state in Academic — rejected; Finance owns fee definitions and all monetary truth.
- Free-text prerequisite strings — rejected; relational, version-scoped level prerequisites are required.
- Rewriting progression decisions on correction — rejected; append/supersede/history pattern is preserved.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-offering-operations.md`

# Offering & Availability Console Operations — Architecture Decision (AC8)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-04
**Applies to:** employee-console transport for branch availability and
offering lifecycle; seat requests targeting an offering.
**Supersedes:** nothing. Completes the F3 remainder ("availability/offering
lifecycle is not modeled [in operations]; no branch×term availability
query/UI surface") without touching the already-certified domain.
**Related decisions:** WP2-DEC-03 (offering is the packaging unit finance
consumes), WP-3 AC3 (enrollment→offering re-point, domain-certified).

## Problem

The branch-availability + offering domain is complete and certified
(`MaintainAcademicStructure::declareBranchAvailability/openOffering`,
`ManageAcademicOffering` close/reopen/cancel/complete/resize with guards,
`OfferingCatalogQuery::catalogue`), but it has **zero operational surface**:
no controller, route, view, or API endpoint references it. In production no
availability can be declared and no offering opened — and the console seat
request does not even accept the domain-supported `offering_id`, so the
enrollment→offering→Finance-obligation packaging link is dormant
end to end despite being domain-certified.

## Decision

1. **Console transport only; no domain change.** Ten POST actions on the
   existing `AcademicController` + routes + an "Availability & offerings"
   console card: declare availability, open offering, close/reopen
   availability, close/reopen/cancel/complete offering, resize offering
   capacity. All authorization, guards, idempotency, and audit stay in the
   commands; the capability is the existing `academic.structure` (already in
   the owner bootstrap — no seeder change).
2. **Seat requests may target an offering.** `requestEnrollment` accepts an
   optional `offering_id` (empty = none) and passes it to the certified
   domain path, which already validates open/matching offering + capacity.
   The Seats card request form gains an offering selector.
3. **Read surface from existing queries.** The index passes branches,
   levels, availabilities, and offerings for selects and tables; no new
   query surface (the certified `catalogue()` stays the programmatic read
   path).

Out of scope: any domain/lifecycle change, new capabilities, gradesheets
(queued next capability slice), per-branch finance/reporting consumers
(WP-2 F1 / WP-4).

## Consequences

- Tests prove the full operational arc over HTTP: declare → open →
  request-with-offering → activate → close refuses new seats → resize floor
  on active seats → cancel/availability-close guards with open seats →
  terminalize seats → cancel → close → reopen, plus capability denial and
  the offering-less request path.
- No migration, no seeder change, no API change.

## Rejected alternatives

- New `offering.*` capabilities — rejected; structure authority already
  governs these operations and is bootstrapped.
- Auto-opening offerings from class activation — rejected; availability is
  a conscious branch×level×term declaration (F3 co-dependency), never
  inferred.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-offerings-waitlist.md`

# Academic Offerings, Availability and Class Waitlist — Architecture Decision (WP-AO)

Status: Accepted
Date: 2026-09-04
Scope: Academic branch availability and offering lifecycle, offering-targeted
enrollment capacity, and ordered class/offering waitlists.

## Context

The Academic module already owned program versions, levels, periods, classes,
enrollment (Student-owned), attendance, assessments, progression and
appeals. `docs/implementation/WP-1-capability-gap-matrix.md` and the certified
Package 06/07 checkpoints identify delivery gaps:

- branch×level×term availability was a packaging fact with no lifecycle, so a
  branch/term could not be opened and closed through the term without losing
  the availability invariant.
- an `Offering` was an immutable packaging concept only; there was no
  open→closed/cancelled/completed lifecycle, no resize, and no relationship
  between an enrollment seat and the offering it was filled under.
- a class could be full while the same level/term had breathing space in a
  sibling offering, but there was no offering-scoped capacity accounting and
  no fair ordered queue of students waiting for a freed seat.

## Decision

Availability and Offering remain inside the **Academic** authority per
`docs/architecture/10-academic-architecture.md` and
`docs/implementation/08-academic-implementation-contract.md`. Enrollment stays
Student-owned; the offering is a controlled academic target attached to a
Student enrollment seat and is immutable on that row once created (a transfer
opens a new seat).

### Lifecycles

- `BranchAvailability`: `active ↔ closed`. Closing is refused while any
  related offering is still `open`; reopening re-checks the term is
  `published` and the level is `active`.
- `Offering`: `open ↔ closed`, `open → cancelled/completed`,
  `closed → open/cancelled/completed`; `cancelled`/`completed` terminal.
  Cancel/complete is refused while any non-terminal enrollment seat
  (`requested`/`active`/`frozen`) still references the offering. Capacity can
  only change while the new capacity is at least the current active seat
  count.
- `ClassWaitlistEntry`: `waiting → offered/withdrawn/expired/enrolled`,
  `offered → enrolled/expired`; `enrolled`/`withdrawn`/`expired` terminal.

### Rules

- A `request()`/`transfer()` enrollment may target an offering. The offering
  must be `open` and must exactly match the class period and level.
- Activation counts against both class capacity and offering capacity; the
  first exhausted cap rejects.
- A waitlist join is permitted only when the class or offering capacity is
  currently exhausted. A student holds at most one open entry per class;
  positions are unique among open entries and ordered.
- Promotion never creates a silent `active` seat: it produces a normal
  `requested` enrollment through `MaintainEnrollment::request`, then marks the
  entry `enrolled`. Activation still follows the normal approval path.
- Withdraw/expire are terminal state changes. Identity fields and positions
  are immutable; the DB guards enforce them together with the open-state
  partial uniqueness indexes.

### Capabilities

- `academic.structure` — availability/offering lifecycle and capacity resize.
- `academic.enroll` — waitlist join/withdraw (student-side queue action).
- `academic.enroll_approve` — waitlist offer/expire/promote (staff-side).

## Consequences

- Migration `2026_09_04_000131_extend_academic_offerings_and_waitlist.php`
  adds the lifecycle CHECKs, removes the term-open requirement from
  non-defining offering updates while keeping the branch×level×term
  immutability and packaging guard on the defining triple, and adds
  `enrollments_offering_guard` + `class_waitlist_entries_*` triggers.
- New command surface: `ManageAcademicOffering`, `ManageClassWaitlist`;
  `MaintainEnrollment` becomes offering-aware.
- New queries: `OfferingCatalogQuery`, `ClassWaitlistQuery`.
- New lifecycle domains: `OfferingLifecycle`, `BranchAvailabilityLifecycle`,
  `WaitlistLifecycle`.
- Tests cover the offering/availability lifecycle, offering-targeted
  enrollment capacity, and the full waitlist join/offer/promote/withdraw
  journey.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-operational-completion.md`

# Academic Operational Completion — Architecture Decision (AC14)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-06
**Applies to:** employee-console transport closing the four remaining
Academic operational gaps: level definition (R1), level targeting on
class creation (R2), seat transfer (R3), attendance correction (R4).
**Supersedes:** nothing. Lifts the AC12 deferral of "level define/retire
transport" for the define half; there is no retire-level command by
design (levels are append-only history, like program versions).
**Related decisions:** AC4 (level-aware progression), AC5 (enrollment
completion/transfer domain), AC10 (sessions/attendance transport),
AC12 (rules/prerequisites governance), G2-D-003, BR-ACAD-002; Finance
authority untouched (transfer re-enters the financial gate at
activation; corrections carry no financial meaning).

## Problem

Four certified domain behaviors have zero employee surface: levels can
only be created by command-line/tests, so no level-aware delivery can
be stood up in production; classes cannot target a level at creation
(the command accepts `programVersionLevelId`, the console drops it);
`MaintainEnrollment::transfer` closes history into a fresh requested
seat but no employee can invoke it; `RecordAttendance::correct` keeps
append-only correction lineage but attendance errors are unfixable in
production and feed payroll evidence permanently.

## Decision

1. **Console transport only; no domain change.** Four additions on the
   existing `AcademicController` + routes + views, all delegating to
   the certified commands with existing capabilities (no seeder
   change): `defineLevel` (`academic.structure`), level-targeted
   `defineClass` (optional `program_version_level_id`, same
   `academic.schedule` authority), `transferEnrollment`
   (`academic.enroll_approve`), `correctAttendance`
   (`academic.attendance`, reason mandatory).
2. **History stays append-only.** Transfer never mutates: the old seat
   closes as `transferred` (active seats only, per the lifecycle) and
   the new seat starts `requested`, re-entering approval and the
   financial gate at activation. Corrections append a new fact linked
   by `corrects_id`; the original row is never edited.
3. **No provenance fabrication.** Transfer populates only what the
   certified command already populates (snapshot id, offering); F1
   branch-designation wiring stays out of scope.
4. **Governed refusals surface, not new rules.** Duplicate level
   key/ordinal, archived-program levels, cross-version class levels,
   same-class transfer, transfer of non-active seats, full target
   classes, and reason-less corrections all surface as redirects with
   the certified error codes.

Out of scope: any domain/lifecycle change, new capabilities, level
retirement (no such command by design), placement catalog remainder
(R6), appeal-linkage enforcement (R7), calendar integration (R8),
API changes.

## Consequences

- Tests prove over HTTP: level define (incl. duplicate-key/ordinal
  and archived-program refusals) → level-targeted class define (incl.
  cross-version refusal) → seat transfer with lineage into a requested
  seat (incl. same-class and frozen-seat refusals) → activation of the
  transferred seat → attendance correction with lineage (incl.
  reason-required and cross-enrollment refusals) → capability denials
  governed and audited.
- No migration, no seeder change, no API change, no new capabilities.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-progression-lifecycle.md`

# Progression Decision Lifecycle — Architecture Decision (AC13)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-05
**Applies to:** employee-console transport completing the progression
decision lifecycle: evidence-carrying proposals, reject, appeal marking,
and appeal-resolution supersession with lineage.
**Supersedes:** nothing. Completes the `DecideProgression` remainder
(propose/review/approve are already operational via
`TransportWorkflowFeatureTest`) without touching the certified domain.
**Related decisions:** AC4 (level-aware progression + facts),
AC12 (rules/prerequisites governance), G2-D-003, BR-ACAD-002 (no
automatic advance); Finance authority untouched (completion evidence
already validates approved decisions).

## Problem

The progression lifecycle is certified end to end
(`AcademicDecisionFeatureTest`: staged SoD, level gates, facts,
appeal → supersede lineage), but the console exposes only
propose/review/approve — and proposals carry no evidence fields. In
production a reviewer cannot refuse a bad proposal, an approved decision
cannot be marked appealed, an appeal outcome cannot supersede the
original, and level-aware proposals cannot cite their basis or result —
so the appeal/supersede lineage and the completion-evidence close-out
are unreachable.

## Decision

1. **Console transport only; no domain change.** Three POST actions +
   routes: reject a reviewed decision, mark an approved/rejected
   decision appealed, supersede an appealable decision. All
   authorization, independence checks, gate revalidation, fact writing,
   idempotency, and audit stay in `DecideProgression`; capabilities are
   the existing `academic.progression_review` (mark appealed,
   supersede-as-reviewer) and `academic.progression_approve` (reject,
   supersede-as-approver) — both already in the owner bootstrap, no
   seeder change. The certified `reject` and `markAppealed` calls take
   no reason/context parameter, so transport adds none: the signer and
   the audit event are the record. The certified `supersede` call
   returns an array (`decision_id`, `superseded_id`), not a model.
2. **Proposals carry evidence.** The existing propose form and action
   accept optional `assessment_result_id`, `basis`, and `repeat_count`
   (empty = none) and pass them to the certified path, which resolves
   levels, requires the basis on level classes, and refuses level-aware
   fields on legacy non-level classes with governed errors.
3. **Supersession signs in one session, governed by the domain.** The
   certified call takes reviewer + approver; the console passes the
   session employee for both (no colleague-id fields anywhere in
   transport, per the ratified transport rule). The domain still bars
   the original proposer (`academic.appeal_not_independent`), requires
   both capabilities, writes the successor approved with its fact, and
   links `superseded_by_id` lineage. Distinct reviewer/approver pairs
   stay covered at command level.
4. **Read surface from existing data.** The index additionally passes
   decided rows (approved/rejected/appealed/superseded) with per-state
   actions and successor linkage; the certified `AcademicHistoryQuery`
   stays the programmatic read path.

Out of scope: any domain/lifecycle change, new capabilities, appeal
commands themselves (already operational), staged two-session
supersession (no domain primitive), API changes.

## Consequences

- Tests prove the full arc over HTTP: evidence proposal (incl.
  basis-required and open-decision refusals) → review (incl.
  self-review refusal) → approve with fact → appeal filed → marked
  appealed → superseded with lineage + successor fact → seat completed
  on the successor evidence (and refused on the superseded original) →
  reject path → proposer-supersede SoD refusal → capability denials
  governed and audited.
- No migration, no seeder change, no API change, no new capabilities.

## Rejected alternatives

- New `progression.*` appeal/supersede capabilities — rejected; review
  and approve authority already govern these operations.
- Two-employee console supersession via a colleague-id field — rejected;
  transport never types a colleague's id; the domain's
  proposer-exclusion is the certified SoD control.
- Auto-marking decisions appealed on appeal filing — rejected; marking
  is a conscious reviewer act on the certified call.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-progression-rules.md`

# Level Progression Rules & Prerequisites — Architecture Decision (AC12)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-05
**Applies to:** employee-console transport for level prerequisites
(define/retire) and level progression rules (define/retire), plus the
review surface over both.
**Supersedes:** nothing. Completes the level-governance remainder
(`definePrerequisite/retirePrerequisite/defineProgressionRule/
retireProgressionRule` — domain-certified, zero operational surface)
without touching the already-certified domain, mirroring the
AC8/AC10/AC11 pattern.
**Related decisions:** AC4 (level-aware progression: rules consumed by
`DecideProgression` — minimum passing score gate, repeat cap, completed
prerequisites); WP2-DEC-02 (program-version levels); G2-D-003.

## Problem

Level governance is complete and certified
(`LevelProgressionFeatureTest`: same-version constraint, self-require
refusal, active-cycle refusal, score 0–100 bound, repeat floor,
one-active-rule-per-level, active-only retire, rule consumption by
progression decisions), but it has **zero operational surface**: no
controller, route, or view references prerequisites or progression
rules. In production no officer can declare that Elementary requires
Starter, set a pass mark, or cap repeats — the progression engine runs
without configurable governance.

## Decision

1. **Console transport only; no domain change.** Four POST actions on the
   existing `AcademicController` + routes + a "Level progression rules
   & prerequisites" card on the academic index: define/retire
   prerequisite, define/retire progression rule. All authorization,
   validation, cycle detection, idempotency, and audit stay in
   `MaintainAcademicStructure`; the capability is the existing
   `academic.structure` — already in the owner bootstrap, no seeder
   change.
2. **Review from existing data.** The index passes levels with their
   active rules and prerequisites for display; no new query surface.
3. **Level lifecycle stays domain-only.** There is no level retire
   command in the certified domain, so the console offers none — rules
   require active levels and the domain refuses otherwise with a
   governed error.

Out of scope: any domain/lifecycle change, new capabilities, level
define/retire transport, progression-decision transport (already
operational), API changes.

## Consequences

- Tests prove the full operational arc over HTTP: define rule with
  score + repeat bounds → duplicate refused → out-of-range score /
  zero repeats refused → retire → re-define allowed; define prerequisite
  → self-require refused → cycle refused → cross-version refused →
  duplicate refused → retire → retired re-retire refused; capability
  denial redirects governed with an audited denial.
- No migration, no seeder change, no API change, no new capabilities.

## Rejected alternatives

- New `level.*` governance capabilities — rejected; structure authority
  already governs these operations and is bootstrapped.
- Client-side cycle pre-check — rejected; the recursive-CTE guard is the
  certified control and a transport check would race it.
- Hiding retire in favor of delete — rejected; history is append-only,
  retirement is the ratified terminal state.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-room-section-timetable-operations.md`

# Room, Section & Timetable Console Operations — Architecture Decision (AC10)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-06
**Applies to:** employee-console transport for academic rooms, class
sections, room/section-aware session scheduling, and the timetable read
surface.
**Supersedes:** nothing. Completes the WP-AR remainder ("rooms, sections
and timetable" — domain-certified, zero operational surface) without
touching the already-certified domain, mirroring the AC8 pattern.
**Related decisions:** WP-AR (rooms/sections/timetable domain:
`MaintainRoom`, `MaintainClass::defineSection/transitionSection`, room/
section-aware `scheduleSession`, `TimetableQuery`, migration `000132`
triggers); roadmap AC2; gap-matrix row I.

## Problem

The rooms/sections/timetable domain is complete and certified
(`AcademicRoomsAndSectionsFeatureTest`: room and section lifecycles,
room/section-aware scheduling, overlap rejection, timetable projection),
but it has **zero operational surface**: no controller, route, view, or
API endpoint references rooms, sections, or the timetable. In production
no room can be defined, no section opened, and no session can carry a
room or section — so the anti-double-booking invariant protects nothing
and there is no day view of who teaches where.

## Decision

1. **Console transport only; no domain change.** Five POST actions on the
   existing `AcademicController` + routes + cards on the session-calendar
   page: define room, transition room, resize room, define section,
   transition section. All authorization, guards, idempotency, and audit
   stay in the commands; capabilities are the existing
   `academic.structure` (rooms) and `academic.schedule` (sections,
   scheduling) — both already in the owner bootstrap, no seeder change.
2. **Scheduling gains the certified options.** The existing schedule form
   and `scheduleSession` action accept optional `room_id`/`section_id`
   (empty = none) and pass them to the certified domain path, which
   already validates open-section/available-room/class-match plus the
   trigger-level overlap guard. Session rows display room and section.
3. **Read surface from the existing query.** The sessions page passes
   rooms, sections, and branches, and renders a branch×day timetable via
   the certified `TimetableQuery::forBranch` (GET filter on the same
   page). Overlap rejection stays exactly where it is certified — the
   `000132` trigger raising `QueryException` — and is not re-implemented
   in transport.

Out of scope: any domain/lifecycle change, new capabilities, waitlist
operations (separate slice), progression rule/prerequisite management
(separate slice), API changes, print/export of timetables.

## Consequences

- Tests prove the full operational arc over HTTP: define → maintain →
   retire room; define → open section; schedule with room+section;
   branch×day timetable shows the booking; duplicate room code / section
   name, cross-class section, non-open section, unavailable room, and
   future-session close/retire blocks surface with governed error codes
   instead of state changes; capability denials redirect governed.
- No migration, no seeder change, no API change, no new capabilities.

## Rejected alternatives

- New `room.*` / `section.*` / `timetable.*` capabilities — rejected;
   structure and schedule authority already govern these operations and
   are bootstrapped.
- Pre-checking overlap in the controller for a friendly error —
   rejected; the trigger is the certified control and a transport
   pre-check would race it. Overlap stays domain-tested only.
- A separate timetable page — rejected; the day view belongs with the
   session calendar where scheduling happens.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-rooms-sections-timetable.md`

# Academic Rooms, Class Sections and Timetable — Architecture Decision (WP-AR)

Status: Accepted
Date: 2026-09-04
Scope: Academic rooms, class-level sections and the room/timetable scheduling
surface.

## Context

The Academic Delivery checkpoint certified classes, one-off sessions, teacher
assignment and attendance, but the WP-1 gap matrix (row I) lists three
delivery gaps that the previously delivered offering/availability slice does
not close: no room resource, no named section within a class, and no
room/timetable scheduler beyond a single `ClassSession` on a `ClassModel`.

## Decision

Rooms and sections remain inside the **Academic** authority. A room is a
branch-owned physical/timetable resource. A section is a named operational
group within a class; it carries its own capacity and lifecycle so a class
can run parallel delivery groups without inventing a new enrollment authority
(class and offering remain the seat-accounting targets). A class session may
optionally target a room and a section.

### Lifecycles

- `AcademicRoom`: `available ↔ maintenance`, `available/maintenance → retired`;
  `retired` terminal.
- `ClassSection`: `planned → open/cancelled`, `open → closed/cancelled`,
  `closed → archived`, `cancelled → archived`; `archived` terminal.

### Scheduling invariants

- A session references a room only if the room is `available`.
- A session references a section only if the section is `open` and belongs to
  the session's class.
- A room cannot host overlapping sessions (same day, overlapping time).
- A class cannot run overlapping whole-class sessions (no section), and a
  section cannot run overlapping sessions with itself.
- `room_id`, `section_id`, scheduled date and times are immutable on an
  existing session after scheduling (a correction is a rebooking through a
  new session after cancelling/archiving the old one).

### Capabilities

- `academic.structure` — define and transition rooms/sections, resize room
  capacity.
- `academic.schedule` — schedule sessions with optional room/section.

## Consequences

- Migration `2026_09_04_000132_add_rooms_sections_and_timetable.php` adds
  `academic_rooms`, `class_sections`, room/section columns on
  `class_sessions`, and the timetable/immutability triggers.
- New command surface: `MaintainRoom`; `MaintainClass` gains
  `defineSection`/`transitionSection` and room/section-aware `scheduleSession`.
- New query: `TimetableQuery` (by room, by class/section, by branch/day).
- Tests cover room and section lifecycles, scheduling with room/section,
  double-book rejection, retired-room rejection, and timetable projection.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-teacher-assignment-lifecycle.md`

# Teacher Assignment Lifecycle — Architecture Decision (AC15)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-06
**Applies to:** ending, extending, and handing over teacher assignments;
post-end read access; console transport.
**Supersedes:** nothing. Implements the decided D-F-058 and D-F-061–069
assignment requirements; D-F-059 (Owner access removal) stays with the
Access module, D-F-070 (teacher appeal) with HR, push notification
(D-F-069) with WP-5 Q.
**Related decisions:** gradesheets viewer rule (amended, not replaced);
assessment/attendance capability authorities (unchanged); Finance
untouched (no financial meaning in assignments).

## Problem

Assignments are create-only: an open assignment can never be ended, so
D-F-061–065 (handover approval, management continuance, reasoned
extensions with explicit end dates) have no path; ended teachers lose
all gradesheet access although D-F-058/066 keep read-only viewing
until term end; and handover is two unlinked calls with no integrity.

## Decision

1. **Three additive primitives on `MaintainClass` (same
   `academic.schedule` authority, no new capabilities):**
   `endAssignment` (open → dated, reason mandatory, date after start),
   `extendAssignment` (dated → later date, reason mandatory), and
   `handoverAssignment` (single transaction: end outgoing on the
   handover date + open the successor from that date, reason
   mandatory, successor must exist and hold no open assignment on the
   class). Every verb is idempotent, audited with before/after, and
   denial-audited. No migration: reasons live in audit events.
2. **"Open" keeps its certified meaning** (`effective_to IS NULL`):
   class activation, the duplicate guard, and the append-only trigger
   are untouched. Ending the last open assignment of an active class
   is allowed (D-F-062: management decides continuance procedurally);
   no class auto-transition is added.
3. **Gradesheet viewer gains the decided read-only tier** (D-F-058,
   D-F-066): an open assignment views as before; an ended assignment
   views while its class period has not ended (`ends_on >= today`),
   otherwise denied; oversight unchanged. Nothing is removed: no
   previously viewable gradesheet becomes denied.
4. **Mutation authority stays capability-based and unchanged**
   (D-F-067/068): scoring/moderation/approval/release/correction keep
   their capabilities, independence checks, and reason/audit rules; a
   viewer rule never grants mutation (already proven). Post-end edits
   remain possible only through these governed paths, and corrections
   always carry reason + audit.
5. **D-F-069 is met as audit + visible history:** every end/extend/
   handover records actor, reason, and before/after; the console shows
   the full dated history. Push notification belongs to WP-5 Q (no
   messaging primitive is invented here).
6. **Console transport** for end/extend/handover on the assignments
   table with per-state forms; dated history stays visible.

Out of scope: any assessment/attendance/class-lifecycle change, new
capabilities, Owner removal (Access), HR appeals, push notification,
API changes.

## Consequences

- Tests prove over HTTP: end → in-term read continuity → extend →
   handover with successor lineage and full history; refusals (end
   dated, extend open, bad date order, duplicate successor,
   post-term read denial); capability denials governed and audited.
- No migration, no seeder change, no API change, no new capabilities;
   no certified behavior altered.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-terminal-guard-doctrine.md`

# Class & Period Terminal Guards — Architecture Decision (audit blocker 2)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-05
**Applies to:** `MaintainClass::transition` (class → cancelled/completed),
`MaintainAcademicStructure::transitionPeriod` (period → closed).
**Supersedes:** nothing.
**Related decisions:** wp-academic-offering-operations (offering
cancel/complete guards), wp-academic-graduation-integrity (graduation
open-seat gates), wp-academic-enrollment-completion (seat completion
evidence), wp-academic-operational-completion.

## Problem

The class and period terminal transitions were state-machine checks only:
a class with live (requested/active/frozen) seats could move to
cancelled/completed, and a period carrying live classes and seats could
close. The stranded seats keep no defined delivery semantics — scheduling
and attendance already require an active class, assessment attempts
require an active seat — so terminalizing the parent orphans the seats it
carries and breaks the evidenced completion chain (assessment →
progression → graduation → transcript) that downstream authorities read.

## Decision

1. **Fail-closed, no exceptional path.** Class → cancelled/completed is
   refused with `academic.class_open_seats` while any enrollment on the
   class is requested/active/frozen. Period → closed is refused with
   `academic.period_open_classes` while any class in the period is
   planned/published/active, and with `academic.period_open_seats` while
   any enrollment on the period's classes is requested/active/frozen. An
   audited override was considered and rejected: it would create a second
   authority contradicting the seat lifecycle, while every legitimate
   flow already has a legal path — withdraw (reasoned), transfer, or
   evidence-bound complete each seat, then transition the class; close
   the period once its classes are terminal.
2. **Same doctrine as the ratified precedents.** Offering cancel/complete
   refuses on open seats (`academic.offering_open_seats`); graduation
   approval/issuance refuses on open seats
   (`academic.graduation_open_seats`). The live-seat set
   (requested/active/frozen vs withdrawn/transferred/completed) is the
   same set the duplicate-seat, transfer, and waitlist guards already use.
3. **Guards run inside the transition transaction on locked rows.** The
   class row is already locked; the guard additionally locks the live
   seat rows (`FOR UPDATE`) so a concurrent seat mutation serializes
   against the guard instead of slipping past it. `transitionPeriod`
   additionally re-reads and locks the period row like every other
   transition command. Class-row reads in the period guard are safe
   without locks because class terminality is monotonic (cancelled /
   completed / archived have no outgoing transitions except
   cancelled/completed → archived, which stays terminal); a stale read
   can only refuse conservatively, never wrongly permit. Seat terminality
   is likewise monotonic.
4. **No guard on non-terminal or inheriting transitions.**
   planned/published/active, period published, and archived (reachable
   only from already-guarded cancelled/completed) are unchanged.
   Downstream readers (attendance, assessment scoring/release,
   progression, graduation, transcripts, audit history) are untouched:
   they keep working on terminal classes because they key off terminal
   seats and pinned evidence, not class liveness.

## Consequences

- Cancelling/completing a class or closing a period with live delivery
  outstanding is a governed 409 with a stable code, on every transport
  (command, console, API) since the check lives in the commands.
- The legal terminal arc is: terminalize seats → complete/cancel class →
  archive; close the period once its classes are terminal. Tests pin the
  refusals, the arc, irreversibility, delivery freeze after completion,
  and history preservation.
- Residual, accepted: a seat *insert* racing the guard commit (new
  request landing between the guard read and the state write) is not
  closed — the identical characteristic the ratified offering guard
  carries, and closing it would require rewiring seat creation, which is
  out of scope for this blocker.

## Rejected alternatives

- Audited exceptional path (override + audit event) — rejected; second
  authority contradicting the seat lifecycle, new bypass surface, no
  legitimate flow needs it.
- Guarding archived or non-terminal transitions — rejected; archived
  inherits cleanliness from the guarded states, and gating delivery
  states would break scheduling/activation.
- Cascading the transition onto seats (auto-withdraw) — rejected;
  terminal seat states carry required reasons/evidence that only the
  seat-level commands can attest; silent mass-withdrawal would fabricate
  history.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-transcript-issuance.md`

# Official Transcript Issuance — Architecture Decision (AC7)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-04
**Applies to:** Academic transcript composition, issuance, printing, document governance.
**Supersedes:** nothing. Composes with ADR-016 (eligibility snapshot), ADR-017
(enrollment financial gate), ADR-018 (level progression / history, AC4), AC5
(enrollment completion lifecycle) and AC6 (graduation integrity / certification).
**Related decisions:** WP2-DEC-02 (level is the target for progression),
WP2-DEC-03 (offering is the packaging unit), G2-D-003 (no automatic academic
outcomes), 06-financial-architecture + D-G3-001 (Finance sole authority),
MD-022 (permanent academic outputs + document verification).

## Problem

AC1–AC6 built the complete Academic evidence→decision chain (placement with
signed snapshots, attendance, assessed results with corrections, level-aware
progression with immutable history, evidenced completion, governed graduation
with certificate issuance) — but the school still cannot produce the one
certified record every student, parent, and receiving institution asks for:
the **official transcript**. Matrix rows J ("official transcripts/gradesheets
as reporting output"), U (verification lifecycle) and the facet "immutable
reporting / print snapshots: Missing" all point here, and AC6 explicitly
deferred it as a separate slice. Today the only outputs are the certificate
(a terminal award, not a record of study) and live-derived prints.

A transcript differs from the certificate in one structurally decisive way:
its source facts keep accumulating. A live re-derivation at print time would
show post-issuance achievements — historically false. The issuance must
therefore **freeze its content** at issuance time.

## Decision

1. **Academic-owned issuance, like certificates.** The transcript is a
   certified extract of immutable Academic truth, not a derived Reporting
   metric: it is issued by the Academic `IssueTranscript` command
   (`academic.transcript_issue`, single-step — the underlying facts are
   already governed), stored as an immutable `transcripts` row, and governed
   as a managed document. Reporting's closed metric catalog is untouched.
2. **Frozen payload, hashed and pinned.** Issuance composes the canonical
   payload (schema `transcript/v1`), stores it as JSONB on the row, and pins
   `content_hash = SHA-256(canonical(payload))`. Prints render the STORED
   payload, never a re-derivation. Re-issuance after new achievements
   produces a NEW record; the old one keeps evidencing history as of its
   issue date. `transcripts` gets the same BEFORE UPDATE OR DELETE
   immutability trigger as `certificates`.
3. **Content is pinned text over immutable facts, per student + program
   version:** student code + legal name; program/version names; entry
   placement (snapshot id, recommended level title/CEFR, digest) when present;
   level progress facts in order (level titles/ordinals/CEFR, class, period
   name, outcome, repeat count, decision, linked released score, achieved
   date); current released (non-superseded) result per attempt with score;
   terminal seats with state (+ completion basis/evidence kind when
   completed); in-progress seats in a clearly marked separate section;
   attendance totals per seat over latest-per-session facts
   (present/absent/late/excused); graduation decision + certificate serial
   when present; issuer + issued-at. Display names are pinned as text so
   later renames cannot rewrite issued history. No Finance content:
   financial standing is not academic truth (MD-022 clearance was
   graduation-scoped; the transcript records study, not accounts).
4. **Document governance mirrors certificates.** Issuance resolves the
   registrar-defined `academic.transcript` classification (fail closed
   `academic.transcript_classification_missing`), registers + submits the
   managed document (title carries student code, program, issue date;
   locator `transcripts:{id}`), and pins `transcripts.document_id` at
   INSERT. The issuer needs `documents.register`; verification/activation
   stay registrar acts through the existing Documents transport.
5. **Transport on existing patterns.** Console issue action + route
   (`academic.transcript.issue`), a Transcripts section in the Academic
   console, and a print route/view rendering the stored payload. No new JSON
   API surface (certified outputs stay console-operated by precedent).

Out of scope: class gradesheets (teacher-side output, separate slice),
transfer-provenance F1 paths (Organization owned), FIN4 aid cases (Finance
owned), QR codes (WP-5 U), transcript delivery/notification (WP-5 Q/P).

## Consequences

- Migration creates `transcripts` (+ immutability trigger): locator
  `document_id` nullable unique, no FK (same rationale as certificates).
- New `Transcript` model, `TranscriptComposer` (Domain, pure read over
  immutable facts), `IssueTranscript` command, `TranscriptQuery`
  (issued-record reads for print/verification).
- Tests cover content assembly across all sections, corrected-result
  exclusion, attendance correction supersede, payload frozenness across
  re-issue, hash recomputation from stored payload, classification-missing
  fail-closed, capability separation (issue vs documents.register vs
  verify), idempotent replay, and HTTP issue + print.

## Rejected alternatives

- Reporting-owned transcript metric — rejected; a certified extract of
  Academic facts is Academic authority, and the metric catalog is
  deliberately closed.
- Live-derived transcript print — rejected; post-issuance facts would leak
  into earlier records (historically false).
- Finance standing on the transcript — rejected; accounts are not academic
  truth and no ratified rule puts them on the study record.
- Multi-step staged issuance — rejected; nothing is decided at issuance
  time (all inputs are already governed), so staging adds ceremony without
  control. The single capability + idempotency + audit matches document
  registration precedent.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-academic-waitlist-operations.md`

# Class Waitlist Operations — Architecture Decision (AC11)

**Status:** APPROVED (implementation authorized for the scope described below)
**Date:** 2026-09-05
**Applies to:** employee-console transport for the ordered class waitlist:
join, offer, promote (accept), withdraw / expire (decline paths), and the
open-entries read surface.
**Supersedes:** nothing. Completes the WP-AO remainder ("ordered
class/offering waitlists" — domain-certified, zero operational surface)
without touching the already-certified domain, mirroring the AC8/AC10
pattern.
**Related decisions:** WP-AO (waitlist domain: `ManageClassWaitlist`,
`WaitlistLifecycle`, `ClassWaitlistQuery`, migration `000131` guards);
roadmap waitlist item; Finance as sole financial authority (promotion
creates a normal `requested` enrollment — activation still passes the
existing financial gate; no Finance touch here).

## Problem

The waitlist domain is complete and certified
(`AcademicOfferingAndWaitlistFeatureTest`: join only when full, one open
entry per student, ordered positions, offer requires a free seat,
promote-into-`requested`, withdraw/expire terminal), but it has **zero
operational surface**: no controller, route, view, or API endpoint
references waitlist entries. In production a full class simply refuses
seats — students cannot queue, freed seats cannot be offered to the next
in line, and offers cannot be accepted or declined.

## Decision

1. **Console transport only; no domain change.** Five POST actions on the
   existing `AcademicController` + routes + a "Class waitlist" card on
   the academic index: join, offer, promote, withdraw, expire. All
   authorization, guards, idempotency, ordering, and audit stay in
   `ManageClassWaitlist`; capabilities are the existing
   `academic.enroll` (join/withdraw — student-side queue action) and
   `academic.enroll_approve` (offer/promote/expire — staff-side) — both
   already in the owner bootstrap, no seeder change.
2. **Accept/decline run the certified edges.** Accept is promote
   (`offered → enrolled`, creating a normal `requested` enrollment that
   activates through the existing approval path and financial gate —
   never a silent active seat). Decline is withdraw from `waiting` or
   expire from `waiting`/`offered`; every legal lifecycle edge is
   exposed, no new transition is invented.
3. **Read surface from existing data.** The index passes open entries in
   class × position order (fairness is displayed, positions stay
   domain-assigned and immutable); no new query surface (the certified
   `ClassWaitlistQuery::forClass` stays the programmatic read path).

Out of scope: any domain/lifecycle change, new capabilities, automatic
promotion on seat freeing (offers stay a conscious staff act),
head-of-line enforcement beyond displayed positions, API changes.

## Consequences

- Tests prove the full operational arc over HTTP: fill → join with
  ordered positions → duplicate/seat-holder joins refused → join refused
  while not full → offer refused while full → free seat → offer →
  decline-by-expire → promote-into-requested → activate through the
  normal gate → join/withdraw the next entry, plus capability denials
  with governed errors and audited denials.
- One corrective migration (`000139`): the `000131` trigger forbade
  `offered → expired`, contradicting both this decision's ratified
  lifecycle and the certified `WaitlistLifecycle`. The guard now allows
  `offered → enrolled/expired`; all other edges are unchanged. No
  seeder change, no API change, no new capabilities.

## Rejected alternatives

- New `waitlist.*` capabilities — rejected; enroll / enroll_approve
  already govern the queue and staff sides and are bootstrapped.
- Auto-promoting the head entry when a seat frees — rejected; an offer
  is a conscious staff decision (the student may have left), and
  promotion stays capacity-checked at action time.
- Hiding the promote-from-`waiting` shortcut — rejected; it is a
  certified legal edge (`waiting → enrolled`, capacity-checked), so the
  console exposes the lifecycle exactly as ratified.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-enrollment-financial-gate.md`

# Enrollment Financial Gate — Architecture Decision (AC3)

Status: Accepted
Date: 2026-09-04
Scope: Server-authoritative Finance gate over Academic enrollment activation,
including payment, discount/waiver, sponsorship/funding, credit, installments,
and approved exceptions.

## Context

The Academic enrollment chain (`MaintainEnrollment::request` →
`::activate`) currently gates only student/class/offering activity and
capacity. The WP-1 gap matrix reports the **enrollment financial gate** as
missing: activation is not financially gated and there is no per-case aid
model. The Master Engineering Contract (§17 PAYMENT GATES) requires:

- any financial gate controlling an enrollment to be **server-authoritative**;
- frontend state to **never** constitute payment authorization;
- legitimate exceptions (scholarship, sponsorship, authorized waiver,
  approved credit, alternative approved settlement) to be backed by
  **explicit evidence and authorization**.

Finance is already the sole authority for money facts (obligations, payments,
allocations, refunds, discounts, restricted-fund allocations, journals) and
all posted facts are immutable or append-only with derived balances. Academic
must consume that authority without duplicating it.

## Decision

A **Finance-owned gate assessment** is computed at enrollment activation from
the authoritative Finance facts for the student. Academic freezes the
resulting evidence on the enrollment row and refuses activation when the gate
is unsatisfied. Academic never writes Finance facts and Finance never writes
Academic facts.

### Financial sources

The gate starts from the student's posted obligations. `uncovered` is the sum
of the Finance-computed `obligationRemaining` across those obligations (which
already nets payment allocations, approved discounts/waivers, and restricted
fund/sponsorship allocations). The following Finance-owned, approved facts
can additionally satisfy the gate:

- **Credit / advance** — a Finance-approved `financial_credits` row for the
  student.
- **Installment plan** — a Finance-approved `enrollment_installment_plans`
  row (alternative approved settlement; the remainder is agreed to be paid in
  installments).
- **Approved exception** — a Finance-approved `financial_gate_exceptions`
  row scoped to the student and, when present, the offering/class, with a
  valid effective window and an explicit reason.

The gate is satisfied when `uncovered` is fully covered by the combination of
those approved facts (or is already zero). An unsatisfied gate blocks the
transition to `active`; `requested` remains a pending seat.

### Boundary contract

- **Finance owns:** obligation/payment/discount/fund truth; credit,
  installment, and exception approval lifecycle; the computation that turns
  those facts into an assessment; the assessment digest/signature.
- **Academic owns:** the enrollment transition and the frozen evidence
  columns it stamps onto the enrollment row.
- **No financial truth is duplicated:** only the assessment evidence (ids and
  amounts, not a second balance ledger) is frozen on the enrollment.
- **Signed evidence is the only consumer input:** `satisfied`, `uncovered`,
  `remaining`, and `assessed_at` in a query transport envelope are discarded
  at the Academic boundary and normalized from the HMAC-protected evidence.
  A substituted envelope therefore cannot change what Academic stores or
  audits.

### Historical correctness and auditability

- `enrollments.financial_gate_evidence` stores the exact evidence payload,
  `financial_gate_evidence_sha256` its digest, and `financial_gate_signature`
  a server HMAC over the deterministic canonical payload (same canonicalizer
  and key-derivation discipline as AC1's eligibility snapshot).
- A denied gate is recorded as an append-only `academic.enrollment.financial_gate.denied`
  audit event with the evidence and error code; the underlying enrollment is
  left untouched (`requested`).
- Approved credits, installment plans, and exceptions are immutable after
  approval (database trigger), so re-verification of historical evidence is
  deterministic against the immutable Finance facts.
- The enrollment database boundary rejects an `active` seat without complete,
  satisfied evidence structurally bound to that seat and freezes the activation
  snapshot against later rewrites. PostgreSQL deliberately does **not** receive
  the application HMAC key; the command verifies the HMAC and database roles
  must not grant arbitrary enrollment DML to untrusted principals. This is an
  explicit deployment trust boundary, not a claim that a raw database
  superuser cannot forge application-held cryptographic evidence.

### Capabilities

Finance gets separate propose/approve capabilities for credits, installment
plans, and gate exceptions (`finance.credit{,_approve}`,
`finance.installment{,_approve}`, `finance.gate_exception{,_approve}`), with
the approver required to differ from the proposer (separation of duty).
Reading/assessing the gate is a Finance query, not a new Authorization.

## Consequences

- Migration `2026_09_04_000134_add_enrollment_financial_gate.php` adds
  `financial_credits`, `enrollment_installment_plans`,
  `financial_gate_exceptions`, and the frozen evidence columns on
  `enrollments`, plus approval-immutability triggers and CHECK constraints.
- New Finance models/commands for credits, installments, and gate exceptions;
  new `FinancialGateEvidence` signer and `FinancialGateQuery` assessor.
- `MaintainEnrollment` consumes the Finance assessment before `active`; it
  stores evidence/signature and records a denied gate audit on rejection.
- A student with no posted obligation has a zero uncovered amount, so
  activation remains possible (nothing is financially required); once any
  obligation is posted it must be covered or explicitly approved before
  activation.
- Tests cover unpaid denial, payment/discount/fund settlement, credit,
  installment, approved exception, approval SoD, evidence immutability, and
  tamper detection.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-placement.md`

# Placement Decision System — Architecture Decision (WP-P)

Status: Accepted
Date: 2026-09-04
Scope: Placement Decision System (test bank → attempt → evidence → scoring → moderation → recommendation → release → appeal → retake)

## Context

The master engineering contract makes Placement a first-class academic
subsystem (contract §18. PLACEMENT CONTRACT) on the pipeline
`Visitor → Placement → Recommendation → Registration → Payment → Enrollment`.
The current Academic module only records a generic `assessment_attempt`
(`kind='placement'|'assessment'`) against an *active enrollment* and a
single `score` through a scored→moderated→approved→released chain. That
covers an in-program assessment, but not the placement decision lifecycle:
there is no authoritative test bank (tests, immutable versions, sections,
the five canonical V1 components Grammar/Reading/Listening/Writing/Speaking,
questions, media, rubrics), no server-authoritative digital/physical
delivery, no timing/answers, no component weighting, no CEFR mapping to
`ProgramVersionLevel`, no recommendation to `Class`/`Offering`, no
pre-enrollment placement profile, no retake/supersession, and no
anti-tamper evidence.

## Decision

Placement remains inside the **Academic** authority (Academic owns
placement attempts/results per `docs/implementation/08-academic-implementation-contract.md`).
It is implemented as `App\Modules\Academic\Placement\...` so the canonical
single-source-of-truth map is preserved:
`placement result → Placement authority`, and `placement recommendation →
Academic authority`, with the authoritative recommendation targeting
`ProgramVersionLevel` and an operational recommended `Class`/`Offering`
being an assignment that never replaces the academic recommendation.

Placement is **person-centric** (the subject may still be a visitor with a
linked Person; Applicant/Student not required yet), because the ratified
pipeline requires placement to happen before Registration and Enrollment.
An existing active enrollment is not a precondition for a placement
attempt. When the person already has a student record, it may be recorded
for traceability only.

### Domain boundaries

- Placement never creates Person, Applicant, Student, Enrollment, Payment,
  Obligation, Message, or Document. It consumes the authoritative
  Identity/Admissions/Student/Academic/Finance/Document facts and records
  evidence/traces only.
- Placement test catalog, sections, questions and rubrics are
  version-scoped, immutable once published, and never silently rewritten.
- Placement responses/answers and submitted attempts are immutable
  evidence; corrections are linked appends.
- A placement profile is the decision object: `draft → scored →
  recommended → reviewed → approved → released`, with `superseded` and
  `retired` closing a profile. At most one open/live profile per person.
- Retakes are explicit: the current live profile is superseded (history
  retained) and a new profile/attempt opens.
- Anti-tamper: submitted attempts carry a server-computed HMAC over the
  canonical evidence payload, duration envelope checks, unknown-question
  rejection, and checksummed media.
- Scoring is deterministic and explainable: section score → rubric band →
  CEFR; component weights → overall CEFR; overall CEFR → active
  `ProgramVersionLevel` of the target `ProgramVersion`; an open
  `Offering`/`Class` at that level is recommended as an operational
  assignment only.
- Finance/Admissions/Documents integration is via caller-authoritative
  downstream commands and trace recorders; Placement never delegates a
  downstream decision.

### Capabilities

- `placement.catalog` — maintain test bank, versions, sections, questions,
  media, rubrics.
- `placement.conduct` — open profile, start/submit digital or physical
  attempts (server-authoritative).
- `placement.score` — manual/professional scoring of non-auto-scored
  sections.
- `placement.moderate`, `placement.approve`, `placement.release` — staged
  decision chain with separation of duties.
- `placement.recommend` — generate CEFR/level/class recommendation.
- Retake and appeal reuse `placement.conduct` (retake) and the existing
  `academic.appeal_manage` (appeal), with `placement_profile` added as an
  appealable subject.

## Consequences

- New schema: `placement_*` tables with lifecycle CHECKs, provenance
  immutability, one-open-profile-per-person, immutable evidence, and
  append-only response/recommendation history.
- New command surface in `App\Modules\Academic\Placement\Commands`.
- Extension of `visitor_interactions` (migration 127) with a
  `placement_attempt_id` reference so the CRM/Finance/Communication trace
  recorder can follow placement evidence back to a lead.
- Reporting registry gains placement pipeline metrics.
- Tests cover the full placement decision journey and the guardrails.

---

## HISTORICAL SOURCE: `architecture/decisions/wp-visitor-crm.md`

# AD-2026-09-04 / WP — Visitor / Lead / CRM Domain

Status: **recorded before implementation** (WP-1 rule 6); user directive `./goal` explicitly authorizes the Visitor build.

## Decision

Build a first-class **Visitor/Lead/CRM domain** as the **CRM bounded
context**. CRM is the canonical authority for `visitor_sources`,
`visitor_campaigns`, `visitor`, `visitor_interaction`, `visitor_followup`,
`visitor_conversion`, and `visitor_automation_rule`.

The CRM module owns the acquisition and lead-conversion pipeline **only**. It
never creates a Person, Applicant, Student, Message, Document, or Money
movement itself. Those entities remain owned by their authoritative modules;
CRM observes and traces them.

## Why

- The Legacy/New acquisition paths existed as separate reception notes and
  spreadsheet leads with duplicated contacts and no audit/identity trace.
- The frozen pipeline is `Visitor → Placement → Recommendation → Registration
  → Payment → Enrollment`. CRM is the entry point of that pipeline and must
  carry provenance, attribution, ownership, follow-up, and conversion evidence
  so every downstream step can be audited back to its source.
- Leads are often anonymous before identity verification. The system must
  support anonymous records without fabricating an identity, and must
  cross-link them only on evidence (Applicant/Student creation).

## Scope

In scope (this domain):

- `visitor_sources` and `visitor_campaigns` — acquisition metadata only.
- `visitors` — the lead record: contact/identity linkage, source/campaign,
  provenance branch, status, rating, interest, assignment.
- `visitor_interactions` — immutable evidence/timeline.
- `visitor_followups` — scheduled next actions, manual or automated.
- `visitor_conversions` — one immutable terminal trace per lead to an
  applicant/student created by the authoritative Admissions/Students workflow;
  `visitor_conversion_handoffs` preserves a later Applicant-to-Student
  authority event without rewriting the original conversion.
- `visitor_automation_rules` — deterministic follow-up scheduling on
  interaction outcome.
- CRM commands, API endpoints, reporting metrics, and tests.

Not in scope: defining or executing Admissions/Student/Finance/Communication
commands. CRM calls their authoritative command paths and consumes results.

## Boundaries and invariants

1. **CRM never creates People/Applicants/Students.** `RegisterApplicant` and
   the admission conversion orchestrator create the downstream participation
   through their owning boundaries (`StudentAdmissionRegistrar` owns Student
   and initial status inserts); CRM records the trace afterwards.
2. **Anonymous leads are first-class.** `person_id` is nullable. No synthetic
   Person is created for a walk-in.
3. **One open lead per verified/linked identity** and **one open lead per
   normalized primary contact** (email, else phone). Partial unique indexes
   enforce this.
4. **Branch provenance is immutable once set** (`origin_branch_id`). The guard
   follows the same semantic as the WP2-DEC-01 operational anchors. NULL is
   never a branch wildcard: directory reads require concrete authorized
   branches unless an organization-scoped unassigned read is explicitly
   requested, and the API advertises that unassigned capture affordance from
   server authorization rather than frontend inference.
5. **Interactions are append-only** — corrections are new facts.
6. **The lead conversion is terminal and one per lead.** After conversion
   the lead is read-only pipeline-wise; a later Applicant-to-Student handoff
   is a separate immutable CRM trace, not a rewrite or second lead conversion.
   Re-opening is a deliberate, audited revival from `lost`, never from
   `converted/archived`.
7. **Automation is deterministic and in-transaction.** An `interaction_outcome`
   rule creates the same follow-up type an operator would schedule manually.
8. **Authorization** uses the single `AccessDecision` authority plus CRM
   capabilities (`crm.catalog`, `crm.visitor`, `crm.followup`,
   `crm.automation`). A record with branch provenance is checked against that
   branch's structure scope (ancestor grants cover descendants). Conversion
   evidence is written only by the authoritative Admissions/Students workflow
   and binds to that workflow's immutable audit event; downstream interaction
   traces use the same authority-event binding.
9. **Audit + idempotency** mirror every other domain command: `AuditRecorder`,
   `AttemptedOperation`, `IdempotentExecution` in every mutating command.
10. **Calendar correctness** — effective dates in CRM use the application
    date; any Shamsi conversion must go through the Calendar Authority, never
    an ad-hoc conversion.
11. **Reporting** registers CRM metrics in the canonical
    `MetricCatalog` with the authoritative academic period; no manual metric
    values exist.
12. **Documents/Search/Communication integration** — a visitor interaction may
    reference a `messages.id` or `documents.id`; the list/search read model
    includes visitor sources/campaigns/status. Document search and
    Communication consent are never bypassed by CRM.

## Authority matrix

| Operation | Capability | Scope |
|---|---|---|
| Define/retire source/campaign | `crm.catalog` | global |
| Capture/update/transition visitor | `crm.visitor` | record provenance branch |
| Record interaction / schedule follow-up | `crm.followup` / `crm.visitor` | record provenance branch |
| Define automation | `crm.automation` | global |
| Record conversion evidence | Admissions/Students authority | same downstream branch/person provenance |
| CRM conversion UI/API | not exposed | CRM cannot create or assert downstream entities |

## Integration points

- **Identity:** a Visitor may remain anonymous until evidence supports a
  verified Person link; an unverified Person cannot be supplied as the lead
  identity and is never enough to become an Applicant.
- **Admissions:** `RegisterApplicant` records the applicant conversion on the
  lead as a side effect of the same Admissions authority — the CRM trace
  cannot be split from the Admissions transaction.
- **Students:** `EnrollAdmittedApplicant` records either the direct student
  conversion or, when the visitor already has an immutable applicant
  conversion, an immutable Applicant-to-Student handoff trace in the same
  Admissions/Student-authority transaction. CRM has no direct student
  conversion path. The visitor/person and originating-branch lineage is
  revalidated before a trace is appended.
- **Communication:** interactions can link `message_id`; consent purposes
  remain owned by Communication.
- **Documents:** interactions can link `document_id`.
- **Academic/Placement and Finance:** assessment, placement, and payment
  traces require the authoritative record, verified person lineage, compatible
  originating branch, and the matching immutable authority audit event.
- **Reporting:** `visitor_capture_count`, `visitor_conversion_count`,
  `visitor_conversion_rate` are registered in `MetricCatalog` grouped by
  academic period, scoped global/branch. Global results include unassigned
  provenance without inventing a branch; branch results exclude it and expose
  the exclusion in metric metadata.
- **Automation:** deterministic follow-up scheduling only. Branchless
  follow-ups may be assigned under explicit organization authority, but they
  never emit fabricated branch notifications; the workspace exposes them only
  to the assigned actor with that same organization-scoped authority.
- **Authorization/Audit:** every command is capability-checked, idempotent,
  and audited; material denials are also recorded.

## Weaknesses intentionally fixed

- Legacy duplicated leads → partial unique active indexes on person/contact.
- No provenance → immutable `origin_branch_id` guard.
- No evidence → append-only interactions with `correlation_id`.
- No owner/due-date discipline → follow-up lifecycle with assignment and
  complete/cancel evidence.
- No automation accountability → rule registry with deterministic action and
  active-state guard.
- No reporting lineage → metrics registered in canonical `MetricCatalog`.
- No CRM↔Admissions traceability → conversion recorder within same
  transaction.

---

## HISTORICAL SOURCE: `architecture/review/2026-09-05-cross-module-consolidation-audit.md`

# Cross-module consolidation audit

**Date:** 2026-09-05  
**Role:** Supreme Technical Owner / Principal Architect  
**Scope:** repository-wide static authority, boundary, security, lifecycle, finance, transport, frontend, reporting, and integration review  
**Verdict:** **Not production-ready.** This pass is a historical consolidation baseline; the current static convergence state is recorded in `2026-09-05-fourth-architecture-convergence.md`. Runtime, database, concurrency, route, browser, and deployment validation remain deferred.

## 1. Method and decision standard

Both implementation lines present in the checkout were compared rather than treating either as the answer. The comparison considered correctness, security, usability, completeness, integrity, scalability, performance, failure behavior, concurrency, auditability, and maintainability. Capability was preserved where possible; implementation was not preserved merely because it existed. Where neither line provided a safe single authority, the third-system decision is recorded below.

This is a static audit. PHP, Composer, PostgreSQL, migrations, PHPUnit, PHPStan, Pint, Node, browser, HTTP, queue, and integration execution were deliberately not run in this phase.

## 2. Authority inventory

The following is the normative inventory after the consolidation decisions. “Read authority” means the query/projection that may present the concept; “authorization authority” is the server-side decision authority, not a controller check. A blank lifecycle/history entry means the concept is a value object or projection rather than an independent state machine.

| Concept | Canonical model/table | Command/service and write path | Read authority | Authorization authority | Lifecycle authority | Historical authority | Competitors / disposition |
|---|---|---|---|---|---|---|---|
| Organization | `Organization` / `organizations` | `CreateStructureUnit`, `RenameStructureUnit`, `TransitionStructureUnit` | `EffectiveStructureQuery` and organization console | `AccessResolution` + `StructureDecision` | `OrganizationLifecycle` | `audit_events`, domain events | No second structure root; organization-wide reads require organization-rooted capability |
| Campus | `Campus` / `campuses` | `CreateStructureUnit`, transfer command | `EffectiveStructureQuery` | `AccessResolution` | `OrganizationLifecycle` | `CampusAssignment`, audit | Campus attribution history is canonical; no controller-owned structure mutation |
| Branch | `Branch` / `branches` | `CreateStructureUnit`, transfer command | `EffectiveStructureQuery`, `ActorBranches` | `AccessResolution`, `BranchScopedAccess` | `OrganizationLifecycle` | `CampusAssignment`, audit | Branch is the operational isolation key; null is unknown, not wildcard |
| Person | `Person` / `people` | `RegisterPerson`, `VerifyPerson` | `PersonDirectoryQuery` | `AccessResolution` | Identity verification state | audit and append-only identity facts | No employee/person duplicate; employee is a relationship to Person |
| Account | `UserAccount` / `user_accounts`; Finance `Account` / `accounts` | Identity account commands; `MaintainChartOfAccounts` for Finance | identity directory; Finance queries | `AccessResolution`; Finance command capabilities | account/finance lifecycle respectively | audit; journal history | Same name, different bounded concepts; not merged |
| Employee | Person + `Employment` / `employments` | `MaintainEmployment` | HR console and employment reads | `AccessResolution` checks HR eligibility | `EmploymentLifecycle` | employment/contract history | No `Employee` duplicate table |
| Employment | `Employment`, `Contract`, `ContractVersion` | HR commands | HR contract queries | Access + HR command capabilities | HR lifecycle and contract version state | immutable contract versions, audit | Payroll consumes; it does not own employment |
| Position | `Position`, `PositionAssignment` | Access assignment commands | Access console | `AccessResolution` | `AccessLifecycle` | dated assignments and audit | Position is authorization topology, not an HR title duplicate |
| Authorization | `AccessPolicy`, `Role`, `ScopeGrant` | policy/grant commands | access console | `AccessResolution` only | access lifecycle | effective-dated grants/policies | Removed implicit controller/policy competitors |
| Delegation | `Delegation` / `delegations` | `DelegateAuthority`, `RevokeDelegation` | access console and `ActorBranches` | `AccessResolution` with delegator eligibility and dates | `AccessLifecycle` | immutable dated delegation/audit | No separate “temporary permission” mechanism |
| Student | `Student` / `students` | `Students\Domain\StudentAdmissionRegistrar` through Admissions conversion plus Student commands | `StudentRecordQuery`, `StudentLifecycleQuery` | branch provenance + `AccessResolution` | student status commands | status rows, branch transfers, holds | Student Lifecycle owns aggregate/status inserts; Admissions orchestrates the approved conversion |
| Applicant | `Applicant` / `applicants` | `RegisterApplicant`, `DecideAdmission`, `EnrollAdmittedApplicant` | Students/admissions queries | admissions command authority + branch visibility | admission lifecycle | decisions and provenance snapshots | No CRM conversion-owned applicant table |
| CRM / Lead | `Visitor` / `visitors` | CRM commands | `VisitorListQuery`, `VisitorTimelineQuery` | `CrmAccess` for writes; branch/global read gate for reads | `VisitorStatus` | interactions and conversion trace | CRM does not create Person, Applicant, Student, Message, or Document |
| Placement | Placement aggregate tables under Academic (`placement_tests`, profiles, attempts, recommendations) | Placement commands | placement queries | placement capabilities + branch provenance | placement state machines | attempts, scores, recommendations | Placement remains Academic-owned; no top-level duplicate module |
| Eligibility | `AcademicEligibilitySnapshot` / `academic_eligibility_snapshots` | placement/admission integration commands | `AcademicEligibilitySnapshotQuery` | Academic/Admissions authorities | snapshot state | immutable source snapshot | Snapshot is evidence, not a competing live admission rule |
| Program | `Program`, `ProgramVersion` | `MaintainAcademicStructure` | academic catalog queries | Academic structure authority | structure lifecycle | published versions | No program table in Reporting or Placement |
| Level | `ProgramVersionLevel` | `MaintainAcademicStructure::defineLevel` | academic catalog / progression queries | Academic structure authority | level lifecycle | immutable version-level facts | No independent Level aggregate; class-level FK must match version |
| Offering | `Offering` / `offerings` | `ManageAcademicOffering` | `OfferingCatalogQuery` | branch-scoped Academic structure authority | offering lifecycle | availability, waitlist, audit | Offering is the branch delivery anchor, not Class |
| Enrollment | `Enrollment` / `enrollments` | `MaintainEnrollment`, waitlist command | `ClassRosterQuery`, history queries | Academic access + Finance gate + branch provenance | `EnrollmentLifecycle` | branch snapshots, gate evidence, corrections | No admissions or reporting enrollment writes |
| Class / Section | `ClassModel` / `classes`, `ClassSection` / `class_sections` | `MaintainClass` | `GradesheetQuery`, class roster, timetable | Academic schedule authority against immutable class branch | class/section lifecycle | assignments, sessions, enrollment history | Class now has required provenance for new inserts; no offering/session inference for new truth |
| Room | `AcademicRoom` / `academic_rooms` | `MaintainRoom` | timetable/room queries | branch-scoped Academic structure authority | room lifecycle | audit and booking evidence | Canonical room table; no generic Resources room authority found |
| Timetable | `ClassSession` / `class_sessions` + `TimetableQuery` | `MaintainClass::scheduleSession` | Timetable query and session controllers | class branch plus same-branch room | session fact is append-only | session/attendance evidence | Calendar conversion is not timetable authority |
| Attendance | `AttendanceFact` / `attendance_facts` | `RecordAttendance` | attendance/academic queries | session/class branch and Academic attendance capability | fact/correction semantics | append-only correction chain | No mutable attendance total is authoritative |
| Assessment | attempts/results/corrections tables | `ManageAssessmentResult` | `GradesheetQuery`, transcript composition | separate assess/moderate/approve/release capabilities | assessment result lifecycle | result correction lineage | Reporting never edits assessment truth |
| Progression | `ProgressionDecision`, `LevelProgressFact` | `DecideProgression` | progression queries | staged Academic progression authority | progression lifecycle | decisions and evidence | No frontend transition bypass permitted |
| Completion | Academic completion evidence and terminal enrollment/class states | completion paths in enrollment/class/graduation commands | academic history | Academic completion capability | terminal guards | immutable terminal records | No duplicate `completion` monetary or student module |
| Graduation | `GraduationDecision` | `DecideGraduation` | `GraduationCertificationQuery` | proposal/review/approval/certification separation | graduation lifecycle | decision and certificate linkage | Certificate issuance follows graduation authority |
| Certificate | `Certificate` / `certificates` | graduation/certificate path | certification/printing queries | Academic certification authority + branch | certificate lifecycle | immutable issuance and document link | No Documents-owned certificate truth |
| Alumni | No canonical table/model | none | none | none | none | none | Missing concept, not a duplicate. Future design must choose a governed projection of graduated students or a separate aggregate; no shadow table is permitted now |
| Obligation | `Obligation`, `ObligationLine` | `PostObligation` | Finance balance/read queries | Finance capability + branch provenance | financial lifecycle | journals, corrections, audit | Finance sole monetary authority |
| Payment | `Payment`, `PaymentAllocation` | `RecordPayment`, `AllocatePayment` | Finance balance queries | Finance capability + branch provenance | payment/allocation lifecycle | allocation and reconciliation history | No Academic/Payroll payment table |
| Allocation | payment/fund allocation models | Finance allocation commands | Finance queries | Finance | allocation state | append-only allocation/reversal | Corrections compensate source facts |
| Refund | `Refund` | `RefundPayment` | Finance console | request/approve SoD | refund lifecycle | source-linked refund history | No destructive payment mutation |
| Discount | `Discount` | `MaintainDiscount` | Finance console | propose/approve SoD | discount lifecycle | proposal/approval audit | No Academic price calculation |
| Credit | `FinancialCredit` | `MaintainFinancialCredit` | Finance gate/balance queries | Finance propose/approve | credit lifecycle | source evidence and audit | No CRM or Admissions credit authority |
| Installment | `EnrollmentInstallmentPlan` | `MaintainInstallmentPlan` | Finance queries | Finance propose/approve | installment lifecycle | plan revisions/history | no duplicate enrollment payment plan |
| Financial Gate | `FinancialGateException`, gate query | `MaintainFinancialGateException`, `FinancialGateQuery` | gate read model | Finance gate authority | gate/exception lifecycle | gate decisions and source facts | Academic consumes gate; cannot override it |
| Financial Correction | `FinancialCorrection` | `MaintainFinancialCorrection` | Finance correction queries | independent Finance approval | proposed -> recorded; recorded immutable | source-linked compensating fact | no direct source rewrite |
| Journal | `Journal`, `JournalLine` | `PostJournal` | Finance journal queries | Finance journal capability | posted/reversal semantics | source-linked reversal history | no Payroll or Reporting journal |
| Payroll | Payroll calculation/result/adjustment tables | Payroll calculation/approval commands | Payroll console; Finance recognition reads source evidence | Payroll capabilities and SoD | payroll period/result lifecycle | immutable result and adjustment history | Payroll calculates; Finance recognizes monetary liability |
| Settlement | Finance `EmploymentSettlement` / `employment_settlements` | `MaintainEmploymentSettlement` | Finance settlement queries | Finance branch-scoped approval | immutable recorded fact | source-linked proposal and audit | Payroll `SettlementProposal` is workflow evidence only; duplicate Payroll final settlement removed |
| Document | Documents aggregate tables | document commands | `DocumentHistoryQuery` | document capabilities, branch/subject authority | document/retention lifecycle | versions, verification, retention decisions | no file path authority in controllers |
| Notification | `Message` / communication tables | `SendMessage` | Communication console/integration reads | communication capability | queued/sent/delivered/failed | delivery attempts/audit | no command emits ad-hoc notifications |
| Outbox | `domain_events` (new immutable transaction log); `integration_deliveries` endpoint projection | `AuditRecorder` -> `TransactionalEventRecorder`; `DispatchDelivery` for endpoint delivery | domain event and delivery queries | source command authority plus integration capability | events immutable; delivery progress lifecycle | event/audit evidence and delivery attempts | `audit_events` is evidence, not the bus; no generic `outbox_events` duplicate remains |
| Reporting | metric catalog/versions/projections/report runs | Reporting commands | catalog calculators and report runs | `reporting.*` plus organization/branch scope | metric/version/run lifecycle | reproducibility hash and metric reconciliation | calculators may not become source authority |
| Audit | `AuditEvent` / `audit_events` | `AuditRecorder`, attempted/rejected operation recorders | Audit console/query | audit access | append-only | immutable audit evidence | not conflated with domain events |

### Inventory conclusions

* There is one authoritative model per business fact after the changes above. Where two records remain, their roles are explicitly different evidence versus fact (Payroll proposal versus Finance settlement; Payroll result versus Finance liability recognition; audit evidence versus domain event; eligibility snapshot versus live admission decision).
* There is no Alumni implementation to consolidate. It is recorded as an intentional gap rather than invented during this pass.
* Calendar remains a distinct conversion/value authority for Solar Hijri dates. Academic owns periods, class sessions, rooms, sections, and timetable facts.

## 3. Duplicate authority inventory and dispositions

| Conflict | Evidence | Decision |
|---|---|---|
| Payroll `SettlementProposal` versus Payroll/Finance final settlement | `SettlementProposal`, Finance `EmploymentSettlement`, migrations `000056`, `000142`, `000143` | Finance `employment_settlements` is the only recorded monetary settlement. Payroll proposal remains non-monetary workflow evidence. Payroll `FinalSettlement` model is removed; migration `000143` prevents recreation. |
| Audit event versus outbound delivery versus domain event | `AuditRecorder`, `audit_events`, `IntegrationDelivery`, `integration_deliveries` | Audit stays append-only evidence. New `domain_events` is the transactional event/outbox boundary. `integration_deliveries` remains endpoint-specific progress and retry state. |
| Payroll total calculator versus Finance monetary authority | `PayrollTotalCalculator` previously summed Payroll tables | Finance now owns `payroll_liability_facts`; Payroll results/adjustments are source evidence and require Finance recognition. `payroll_total` catalog ownership is Finance. |
| Class branch inferred from room/offering versus no class provenance | `classes` had no branch; room and offering had branch | New class inserts require immutable `branch_id`; session scope comes from the class, and a room must match it. Historical nulls remain quarantined, not guessed. |
| Placement as a separate top-level domain versus Academic Placement | Placement models/commands are nested under Academic | Placement remains Academic-owned; Admissions consumes released evidence. |
| Generic room versus Academic room | `AcademicRoom` and no competing generic room model | `academic_rooms` is canonical. |
| Person versus Employee | Identity `Person` plus HR `Employment` | Preserve two facts with a relationship; do not add Employee table. |

## 4. Schema conflicts and implemented invariants

1. **Class provenance:** migration `2026_09_05_000144_add_class_branch_provenance.php` adds a nullable historical-compatible `classes.branch_id`, foreign key/index, an insert guard requiring new provenance, and an immutability guard. `MaintainClass` resolves an explicit branch (or a single unambiguous visible branch for programmatic callers), authorizes it, and persists it. This avoids fabricating branch attribution for existing rows while making new truth safe.
2. **Session/resource consistency:** scheduling authorizes the class branch and rejects a room from another branch. `TimetableQuery::forBranch` uses class provenance, so room-less sessions no longer evade branch filtering.
3. **Finance source linkage:** `payroll_liability_facts` is append-only, source-unique, amount-checked, branch-provenanced, and records period, employment, Finance actor, correlation, and evidence reference. Finance recognition requires an approved Payroll source, exact signed amount match, a Payroll/Finance actor split, active employee branch provenance, and branch-scoped Finance authorization.
4. **Event immutability:** `domain_events` has a unique audit source, actor/aggregate/correlation identity, payload digest, and database append-only protection.
5. **Existing migration concern:** historical settlement migrations still reference `final_settlements`; `000143` is intentionally one-way. Clean-schema and upgrade paths are unresolved until runtime validation and data-reconciliation preflight exist.

## 5. API and transport conflicts

* Web and JSON routes remain separate transports over shared command authorities. That is acceptable only when validation, error mapping, idempotency, read scope, and response contracts are compared explicitly.
* The web/API Academic session reads now use class branch provenance. The prior JSON path admitted room-less sessions without a branch condition; this was an IDOR/isolation defect.
* Payroll console and API period/calculation bulk reads now require organization-rooted `payroll.period`/`payroll.calculate` authority. Reporting, Organization, Home, Academic bulk console, and CRM bulk console reads likewise require explicit organization-wide authority where the query intentionally remains broad.
* CRM JSON visitor detail/timeline reads now invoke `requireBranchVisible`; the bulk endpoint and web console use organization-rooted authority. Mutating commands continue to own branch authorization.
* Students and Finance already had branch-derived bulk filters. Their parity is retained rather than replaced by controller-local authorization.
* New Finance Payroll Liability recognition is exposed through both web and API routes and delegates to the same Finance command.
* Remaining route parity risks include differences in error serialization, controller `findOrFail` timing before command denial, omitted API fields, and unreviewed high-risk read methods outside the named consolidation set. They are not claimed resolved by shared commands alone.

## 6. Lifecycle conflicts

* **Class/session:** class transitions and terminal-seat guards remain in `MaintainClass`; session creation is not a class transition and inherits class provenance.
* **Enrollment:** requested/active/frozen/transferred/withdrawn/completed remains `EnrollmentLifecycle`; Finance gates and branch snapshots are inputs, not competing state machines.
* **Assessment:** submit, score, moderate, approve, release, and correction are distinct authority stages. Released results are never rewritten.
* **Graduation/certificate:** graduation approval/certification precedes certificate issuance; transcripts are read projections of certified evidence.
* **Finance corrections:** source facts remain immutable; corrections are proposed then independently recorded and source-linked.
* **Payroll/settlement:** Payroll result and adjustment history is immutable. A proposal is not a settlement. Finance recognition/settlement is the monetary boundary.
* **Delivery:** domain events are immutable facts; integration delivery status may progress under its delivery state machine and never rewrites event identity.

## 7. Security findings and decisions

### Findings fixed in this pass

* Broad controller reads did not consistently invoke `ActorBranches`; write command authorization did not protect read paths. The base controller now supports an explicit organization-rooted read gate, and the named broad consoles use it.
* Room-less Academic sessions had no canonical branch provenance. Class provenance and class-based read filtering now close that fail-open path for new truth.
* Timetable branch reads previously depended on room ownership and therefore omitted room-less sessions. They now use class branch provenance.
* CRM detail/timeline API methods could resolve a visitor without a read-side branch gate. They now fail closed through `requireBranchVisible`.
* Organization structure read capability was missing from the bootstrap capability set even though structure decisions used its three capabilities. The three canonical capabilities are now seeded for the Owner role.
* Payroll reporting could expose Payroll monetary sums as if Finance owned them. Finance recognition is now explicit and auditable.

### Remaining security risks

* Historical null-provenance rows remain inaccessible to branch-scoped paths but need a governed remediation/reporting process.
* Some controllers and API detail methods outside the reviewed set still query first and rely on commands later; static IDOR review must continue.
* `ActorBranches` and `AccessResolution` both traverse access topology. They intentionally share lifecycle/date semantics but still need executable contract tests proving no stale delegation, inactive employment, or organization mapping divergence.
* Direct SQL can bypass command-level contracts until every high-risk table has database protection; the migrations cover important facts but not every aggregate.
* File/document authorization and path traversal require runtime and storage-adapter validation.
* Organization-rooted read gates may be stricter than some branch-operational workflows. A later usability review should add explicit branch-scoped query objects, not weaken the fail-closed gate.

## 8. Finance findings

* Finance remains the sole monetary authority for obligations, payments, allocations, refunds, credits, discounts, installment plans, corrections, journals, employment settlements, and recognized payroll liabilities.
* Payroll remains responsible for deterministic calculation and source-linked approved adjustments. It cannot write `payroll_liability_facts`.
* `payroll_total` now sums only Finance-recognized liability facts. An approved Payroll result that Finance has not recognized is intentionally absent from the monetary report; this prevents a report from silently becoming a second posting authority.
* The new Finance recognition command enforces approved source state, exact amount, source uniqueness, Finance organization-wide capability, evidence reference, idempotency, audit, and transactional domain event creation.
* No claim is made that recognition is yet wired to a disbursement/GL posting policy. That downstream lineage is an unresolved runtime/business validation item.

## 9. Frontend findings

* Blade views were generally thin transport surfaces, but Academic classes offered no branch input even though branch is now required. The class form and controller now carry `branch_id` and remove the misleading “legacy class” wording.
* Academic session selectors and resource lists are now expected to contain only visible class branches. Empty results for a user without visible branch authority are intentional fail-closed behavior.
* Reporting and Payroll views continue to use their command endpoints; their broad index reads now have server authorization rather than relying on hidden navigation.
* Web/API input contracts for Finance payroll-liability recognition are aligned at field level: source type, source id, amount, and evidence reference.
* Runtime rendering, accessibility, responsive layout, stale route links, browser behavior, and JavaScript/API consumers were not executed. Frontend correctness is therefore not signed off.

## 10. Outbox and event findings

Before this pass, `integration_deliveries` was the only outbox-like table. It correctly carried endpoint, idempotency, source, payload digest, retries, dead-letter progress, and delivery evidence, but it was not a generic transactional domain-event stream. `audit_events` was append-only evidence and was not a bus.

The implemented third boundary is:

1. a successful command writes its fact;
2. `AuditRecorder` writes immutable audit evidence;
3. in the same transaction, `TransactionalEventRecorder` writes one immutable `domain_events` row linked to that audit event;
4. commit is the publication boundary;
5. an eventual integration projector may turn a domain event into endpoint-specific `integration_deliveries` rows with their own idempotency and retry state;
6. no event recorder performs network I/O or mutates audit evidence.

Denied attempts remain audit evidence and do not become business domain events. This prevents conflating security evidence with a successful state transition.

Unresolved: no projector/consumer contract is implemented yet, and no runtime proof exists for rollback, duplicate delivery, poison payloads, dead-letter requeue, or ordering.

## 11. Reporting findings

* Metric ownership is registered in `MetricCatalog`; report runs carry metric version/period/scope and reproducibility metadata.
* Finance owns `student_outstanding_balance`, fund utilization inputs, and now `payroll_total` monetary recognition. Academic, CRM, Placement, and Reporting do not post monetary facts.
* Payroll total lineage changed from “approved Payroll results plus adjustments” to “Finance-recognized Payroll liability facts.” The old calculation was a duplicate monetary authority and has been removed from the calculator path.
* Reporting still contains derived calculators that need runtime reconciliation against source facts, especially period boundaries, branch scopes, and correction semantics.
* No report is a source of truth. A metric catalog entry without a valid source-period contract must fail, not invent a Reporting period.

## 12. Changes implemented

* Added immutable `domain_events` schema/model/recorder and wired successful `AuditRecorder` operations to it transactionally.
* Added immutable class branch provenance migration, command support, room/class branch consistency, class-scoped timetable reads, and UI/controller branch input.
* Added Finance-owned `payroll_liability_facts`, model, recognition command, bootstrap capability, web route, API route, and Finance-backed payroll total calculation.
* Added base-controller organization-wide read authorization and applied it to the identified broad Academic, Payroll, Reporting, Home, Organization, and CRM console reads.
* Added API payroll bulk read protection, CRM detail/timeline branch protection, and class-based Academic API session filtering.
* Updated the metric catalog specification to Finance ownership and updated the direct-class schema specification to include branch provenance.
* Preserved command-centric mutations; no controller-side domain mutation authority was introduced.

## 13. Deleted or retired obsolete architecture

* The duplicate Payroll `FinalSettlement` model is deleted in the consolidation changes; the Finance `EmploymentSettlement` fact is canonical.
* `final_settlements` is not recreated by the current settlement consolidation migration. Its one-way rollback is deliberate because recreating a competing authority would be unsafe.
* The Payroll total calculator no longer treats Payroll result/adjustment tables as the monetary reporting authority.
* No duplicate Alumni, room, level, employee, placement, or generic outbox implementation was invented or retained.

## 14. Unresolved risks and decisions requiring later work

1. Reconcile historical `final_settlements` rows before any upgrade is considered safe; prove migration `000056`/`000103`/`000112` interactions on clean and upgraded schemas.
2. Define and implement the Finance recognition-to-journal/disbursement lineage, including corrections after recognition and period close behavior.
3. Build the domain-event projector to endpoint deliveries and prove idempotency/concurrency under retries.
4. Complete branch provenance remediation for historical classes and other null anchors without fabricating ownership.
5. Replace remaining broad high-risk reads with named branch-scoped query objects where organization-wide access is not the intended UX.
6. Compare every web/API route pair for validation, response shape, authorization timing, error code, idempotency key, and audit correlation.
7. Add a governed Alumni decision: projection from graduated students versus a separate aggregate with explicit ownership and history.
8. Validate file access, document subject authorization, queue workers, scheduler behavior, and frontend contracts.
9. Perform the mandatory Integrated Enterprise System Architecture Graph conformance audit: map every major fact, context, typed relationship, command/query/event path, workflow, task, approval, work item, exception, notification, document, search projection, report, management decision, and critical failure behavior to repository evidence.

## 15. Later runtime validation plan

This plan is specification only and was not executed:

* run clean-schema and upgrade migrations against PostgreSQL, including trigger/index/FK checks;
* run PHP syntax, static analysis, formatting, and the full test suite;
* test concurrent class creation/session scheduling, class branch mismatch, room mismatch, and historical null fail-closed reads;
* test command rollback proves no fact, audit event, or domain event survives a failed transaction;
* test duplicate idempotency keys and unique-source Finance payroll recognition;
* test Payroll adjustment recognition, Finance period close, correction lineage, and report reproducibility;
* exercise web/API parity for Academic sessions, CRM visitor detail, Payroll reads, Finance liability recognition, and reporting;
* run browser/accessibility/responsive tests for changed class/session/finance views;
* test domain-event projection retries, duplicate endpoint delivery, dead-letter/requeue, payload digest, and ordering;
* perform security tests for IDOR, inactive employment, expired delegation, stale grants, unknown/null provenance, direct SQL writes, SoD, and unsafe document access.

## 16. Employee Workspace findings

The approved Employee Workspace Principle is now incorporated into the Master Engineering Contract addendum, the Supreme Technical Governance Mandate §37, the architecture state, system topology, module boundaries, dependency graph, transaction, Finance, authorization, workflow, lifecycle, reporting, audit, integration, background-processing, error, observability, resilience/security, testing, invariant, traceability, gate-review, implementation-state, employee-interface, and coverage-matrix documents. ADR `2026-09-05-employee-workspace-principle.md` records the architectural decision.

The requirement is first-class and remains unimplemented as a concrete workspace product surface. A generic dashboard, static role dashboard, cached task list, or frontend-only hidden menu would not satisfy it. The target must dynamically compose effective identity, employment eligibility, positions, assignments, capabilities, scope, lifecycle, tasks, approvals, deadlines, notifications, authoritative context, and exceptions; support multiple positions and management-specific work; optimize real employee efficiency; and route every mutation through canonical server-authorized domain commands. Workspace visibility, personalization, aggregation, and projections must not create authority, permission, financial truth, lifecycle truth, or independent approval/task state.

The current frontend/API artifacts are historical transport coverage, not proof of workspace readiness. Runtime validation must cover command-time revocation after suspension/termination/expiry/transfer/delegation changes, branch isolation, API/web parity, freshness and rebuildability, projection failure, accessibility, usability, exception visibility, and efficiency metrics.

## 17. Final static contract review

The pre-runtime contract review covered changed route/action mappings, imported application classes, Payroll source fields, Finance recognition boundaries, migration ordering, model fillable fields, and audit integration call sites:

* Changed web/API controller action references resolve to existing public methods by static inventory: 356 route controller actions checked, with no unresolved action or import; changed `App\\...` imports resolve to repository files.
* Payroll result compatibility is explicit: approved result source fields are `lifecycle_state`, `period_id`, `employment_id`, and `amount`; approved adjustment source fields are `result_id` and `amount`. Finance recognition derives period and employment from the result, requires approved state, rejects zero sources, requires exact amount equality, and the database trigger repeats source existence, approval, identity, and amount checks.
* `PayrollLiabilityFact` fillable fields match migration columns and the reporting calculator reads only the Finance table. `ClassModel` fillable/provenance fields match the class migration and gradesheet output includes `branch_id`.
* `AuditRecorder::record()` retains the existing required six-argument call shape and adds only an optional correlation argument; a static argument scan covered 234 application call sites and found only six- or seven-argument calls (trailing commas excluded). Successful audit writes create a linked domain event; denied-attempt recording remains audit-only by operation convention.
* Migrations `000140`–`000146` are ordered so Finance settlement storage precedes the one-way Payroll settlement consolidation, class provenance precedes event/liability additions, and Payroll source tables precede Finance liability guards. The historical settlement upgrade path, one-way rollback behavior, trigger ordering/locking, and clean-schema execution remain unverified.

This review is static only. No PHP/Composer/PostgreSQL/Node execution, migrations, tests, builds, commits, or pushes were performed.

## 18. Final architectural verdict

The repository is materially closer to one coherent third-system architecture: command writes are retained, authorities are named, Finance monetary recognition is explicit, class/session provenance is no longer inferred for new truth, audit evidence is separated from transactional domain events and endpoint delivery progress, the Employee Workspace requirement is governed across the architecture, and the mandatory Integrated Enterprise System Architecture Graph is now the target conformance contract.

The graph is mandatory and non-negotiable, but repository conformance has not yet been established. It requires a subsequent enterprise-wide code/schema/API audit covering every major fact owner, typed relationship, lifecycle, command, projection, event, workflow, document, notification, search path, reporting path, and failure behavior.

It is **not production-ready**. The remaining risks are substantive conformance, runtime, usability, security, and data-reconciliation risks, not documentation gaps. No production-readiness claim should be made until the graph conformance audit and deferred validation plan pass and the unresolved Employee Workspace implementation/validation, settlement, event projection, provenance remediation, route parity, and Finance posting decisions are closed.

---

## HISTORICAL SOURCE: `architecture/review/2026-09-05-final-platform-architecture-blueprint.md`

# TOEFL House — Final Platform Architecture Blueprint

**Date:** 2026-09-05 (Asia/Kabul)  
**Status:** Final target architecture for implementation; static reconstruction complete; runtime validation and production-readiness certification deliberately deferred  
**Working branch:** `arena/01a07134-toefl-house`  
**Repository:** `alfrotan-glitch/TOEFL-House`

> This is an architecture decision, not a claim that the current checkout is complete or production-ready. No PHP, Composer, PostgreSQL, migration, PHPUnit, PHPStan, Pint, Node build, browser, queue, integration, or runtime command was executed for this blueprint.

---

## 1. Scope, identity map, evidence, and limits

### 1.1 The three systems

The earlier comparison documents used an A/B shorthand that became ambiguous after the archive on `origin/main` was discovered. This blueprint establishes the identity map that governs all subsequent work:

| Identity | Static source | Actual system | Evidence boundary |
|---|---|---|---|
| **System A** | `origin/arena/01a03298-toefl-house`, commit `b2886a4` | The latest React/Vite + TypeScript frontend and Express/TypeScript backend using SQLite; broadest feature surface and most mature financial/academic hardening | `src/`, `server/src/`, `server/src/db/schema.sql`, route/core/test/document trees |
| **System B** | `origin/main:ERP.zip`, extracted only to a temporary inspection directory | An earlier packaged React/Vite + TypeScript + Express/TypeScript + SQLite ERP snapshot; same product lineage as A, materially smaller and less hardened | Archive manifest, 96-table schema, 32 route groups, older UI/core/docs; not a separate production authority |
| **System C** | Current checkout `HEAD e399ca3`, branch `arena/01a07134-toefl-house` | Laravel 12 modular monolith, PostgreSQL-oriented migrations, Blade transport/frontend, current authority and architecture work | `app/Modules/`, `database/migrations/`, `routes/`, `resources/views/`, current architecture/review documents |

System B is not treated as an independent backend to merge with System A. It is a historical/product-line evidence set: it proves earlier intended screens, naming, workflows, and capability experiments, while A is the later expression of the same React/Node design. System C is the current third system and the only repository being changed.

### 1.2 Inspection method

The reconstruction used static tree, source, route, schema, migration, controller, command, query, permission, frontend, test-name, and architecture-document inspection. Git refs were inspected without switching branches. `ERP.zip` was listed and extracted outside the repository solely for static reading.

Important source facts:

- A contains 138 declared SQLite business tables, 33 route files, domain cores for academic, books, calendar, configuration, dashboard, events, finance, funding, impact, journey, observability, operations, payroll, placement, RBAC, reporting, students, and visitors, plus a React feature frontend.
- B contains 96 declared tables, the same broad route family, and the same general product lineage, but lacks much of A's later financial subledger, placement content, lending, asset, supplier, loan, notification-read, and correction surface.
- C contains a large Laravel modular architecture, 144 migration-created table names in the inspected migration chain, substantial command/domain/query separation, PostgreSQL-oriented integrity, and a Blade frontend. Its own conformance audit remains **NOT CONFORMING** to the prior target graph.
- A and B source documentation claims tests and release gates, but those claims are not runtime evidence in this phase.

### 1.3 Decision standard

A capability is selected only if it is useful to TOEFL House, has a clear owner, can be authorized and audited, has a coherent lifecycle, and can be represented without a duplicate monetary or academic authority. A source implementation is not selected merely because it exists. A missing capability is not added merely because an ERP feature list names it.

---

## 2. Executive decision and non-negotiable principles

Neither source system is accepted unchanged. TOEFL House will build a **fourth approach**: a redesigned, capability-complete modular monolith that uses C's stronger domain/relational discipline, A's validated product breadth, and explicit redesign where both source systems are weak or contradictory.

### 2.1 Final technology decision

- **Backend:** Laravel 12 modular monolith on PHP 8.3+ with explicit module application services, commands, domain policies, queries, transport adapters, and PostgreSQL persistence.
- **Database:** PostgreSQL as the sole transactional database. No SQLite production authority and no second operational database.
- **Frontend:** One React + TypeScript application built with Vite, route-based feature slices, a shared design system, typed API contracts, server-state caching, responsive employee/management/student shells, and first-class English plus Persian/Dari RTL support.
- **Transport:** Versioned JSON HTTP API (`/api/v1`) used by React, with API and web/print adapters invoking the same application commands. Blade is not the final interactive product frontend; backend-rendered print/PDF documents are a rendering service, not a second application UI.
- **Integration:** Transactional outbox in PostgreSQL, an idempotent relay, internal queue delivery, notification projections, and explicit integration endpoints. No domain state is delegated to a message broker.
- **Accounting:** Finance is the sole monetary authority. A double-entry journal and bounded subledgers are the canonical accounting model; all operational money facts link to source evidence and journal postings.
- **Authorization:** Central AccessDecision evaluation using authenticated identity, employment/account state, permissions, delegation, lifecycle, object ownership, organization scope, branch scope, and separation-of-duties rules. Unknown scope is denial, never “all.”
- **Workspace:** Employee Workspace and Management Workspace are first-class composition layers over canonical domain facts. They are not a static role dashboard and never own permissions, balances, approvals, enrollments, payroll, or lifecycle state.

### 2.2 Architectural laws

1. One major fact has one owner, one write authority, one lifecycle authority, one authorization path, and one canonical database representation.
2. Read models, dashboards, search indexes, workspace cards, notifications, and reports are projections. They may be stale, rebuilt, or deleted without changing business truth.
3. A route/controller may parse and authorize; it may not become a second domain writer.
4. Corrections are source-linked compensating facts. Destructive deletes are not financial correction semantics.
5. Domain state and its outbox event commit atomically. Event delivery is at-least-once; consumers are idempotent.
6. A coarse status is derived from the detailed lifecycle when both would otherwise be independent writers.
7. Payroll calculates and proposes. Finance records liabilities and settlement facts.
8. Placement recommends; Academic admits/enrolls; Finance bills and settles.
9. Funding describes donor intent, awards, restrictions, and impact. Finance records money and enforces spendability.
10. Workspace visibility is not authorization. Every action is reauthorized at command time.

---

## 3. Master A/B/C capability inventory and classifications

Classification vocabulary: **A-only/B-only/C-only** means materially present only in that system; **A+B**, **A+C**, or **B+C** means shared by that pair; **A+B+C** means all three contain a meaningful implementation; **missing** means not materially present in any source or not sufficient for the target; **duplicated** means a source contains more than one competing authority; **incomplete** means a capability exists but cannot safely support the target lifecycle; **conflicting** means the sources implement incompatible ownership or semantics.

| Capability family | System A | System B | System C | Classification | Final disposition |
|---|---|---|---|---|---|
| Organization, campuses, branches | Organizations, campuses, branches, branch scope | Same basic hierarchy | Organization, campuses, branches, scope links | A+B+C | Preserve as Foundation; C/PostgreSQL authority, fail-closed scope |
| Organization lifecycle and branch availability | Branch guards and profiles | Basic branch administration | Organization lifecycle and branch availability domains | A+B+C, incomplete in C | Keep; one Organization owner and one scope resolver |
| People/person identity | Student/teacher/employee rows, linked accounts | Same role-linked identity model | `people`, identity accounts and participation-oriented models | A+B+C, duplicated in A/B | Rebuild canonical Person; domain participations reference it |
| Authentication and sessions | HttpOnly session, password quarantine, session version | Same lineage, older | Laravel identity/account/auth flows | A+B+C | Keep C foundation; add MFA/recovery/session revocation policy |
| RBAC | Permission catalog, roles, scoped user roles | Same, older role vocabulary | Access positions, roles, scope grants, delegations | A+B+C, conflicting | Access owns canonical capability and ABAC decision; no role checks in domain code |
| Delegation and expiry | Event/role delegation concepts | `role_delegations` | `delegations`, expiry and grant workflows | A+B+C, incomplete | Keep explicit temporary delegation with SoD and audit |
| CRM sources/campaigns/visitors | Visitor pipeline, campaigns, follow-ups, duplicate lookup | Same | CRM visitor/source/campaign/follow-up/interactions | A+B+C | CRM owns prospect and interaction history |
| Admissions/applicant case | Visitor conversion to student | Visitor conversion | Applicant, admission decision, register/initiate/review/approve/enroll | A+B+C, boundary conflict | Admissions owns application/decision; Students owns student creation |
| Households/guardians | Households and student staff relations | Limited/no household model | Guardian relationships | A+C | Students owns relationships; Person remains identity authority |
| Student master and status | Students and lifecycle service | Students and statuses | Students module, status/hold events, operational eligibility | A+B+C, stronger C lifecycle | Students owns profile/status; no Admissions direct student insert |
| Student branch transfer | A table and guarded route | Limited/older | C table/command | A+C | Students owns home-branch transfer; historical provenance immutable |
| Placement content bank | Tests, sections, questions, rubrics, media | Basic placement rules/profile | Placement tests/questions/sections/rubrics/media | A+B+C, incomplete B/C | Placement owns versioned content and attempts; persist evidence |
| Placement attempts/scoring/results | Full attempt/response/result engine | Modal/basic attempt surface | Attempts/results/recommendations and appeals | A+C, incomplete B | Preserve A's content/evidence ideas; redesign scoring/result lifecycle |
| Placement recommendation and eligibility | Recommendation/decision engine | Placement rules | Eligibility snapshots and recommendation domain | A+B+C, conflicting | Placement owns evidence; Academic consumes signed eligibility snapshot |
| Program/course/subject/module catalog | Programs, versions, subjects, modules, levels | Programs, versions, subjects, modules | Programs, versions, levels and offerings | A+B+C | Academic Planning owns versioned immutable published catalog |
| Academic terms/calendar/holidays | Terms, time slots, academic holidays, Shamsi date helpers | Same | Calendar/period authority and academic periods | A+B+C, duplicated calendar rules | Calendar owns time; one Jalali/Gregorian conversion service |
| Skills and teacher rates | Skills, level skill rates, assignment validation | Same | Skills and assignment skills | A+B+C | Workforce owns skill/compensation inputs; Academic owns teaching assignment |
| Rooms and facilities scheduling | Rooms/time slots/classes/sessions | Same | Academic rooms, availability, timetable queries | A+B+C, scheduling embedded inconsistently | Separate Scheduling & Facilities context; Academic references reservations |
| Offerings, class generation, capacity | Generation engine, offerings, classes, lifecycle | Same | Offerings/classes/sections/lifecycle | A+B+C, incomplete C | Academic Delivery owns sections/capacity; derive occupancy and offering capacity |
| Enrollment, freeze, transfer, waitlist | Detailed transition engine and waitlist | Same | Enrollment/waitlist/freeze/transfer commands | A+B+C, conflicting lifecycle variants | Academic Enrollment owns all enrollment transitions; Finance gate is a policy check |
| Sessions and attendance | Session/roster/attendance and policy services | Same | Sessions/class sessions/attendance facts | A+B+C | Academic Delivery owns attendance; one session provenance path |
| Homework/quizzes/continuous assessment | Tables and UI concepts | Tables | Assessment attempts/results, less delivery breadth | A+B, incomplete C | Required academic capability; phase with gradebook redesign |
| Exams and certificates | Exams/results/certificates | Same | Assessment/certificate/transcript/graduation models | A+B+C | Academic Assessment and Certification own results and credentials |
| Gradebook/grade lock/history | Gradebook, grade lock and history | Same | Assessment results, correction/appeal models | A+B+C, duplicate correction risk | Academic owns grades; lock/publish/correct through one command graph |
| Progression/prerequisites/graduation | Promotion engine and rules | Promotion rules | Progression decisions/rules, graduation decisions, transcripts | A+B+C, incomplete C | Academic Progression owns rules and decisions; no grade/report writer |
| Student portal | React student view | React student view | Blade/API student surfaces, less complete | A+B+C, frontend conflict | Rebuild in single React app; self-scope by Access |
| Documents and printing | Receipt/invoice/certificate printing and audit | Same | Documents, versions, classifications, retention, print routes | A+B+C, incomplete A/B security | Documents owns metadata/version/retention; object access uses Access |
| Privacy/consent/disclosure/export | Limited policy/read controls | Limited | Consent, revocation, disclosures, subject export | C-only | Preserve as Privacy platform context |
| Communication/messages | Notifications and templates | Notifications | Message queue/delivery state | A+B+C, incomplete C | Communication owns delivery; Notifications owns recipient/read projection |
| Generic notification read state | Per-user read receipts in latest A | Shared read flag/older | No complete authority | A-only, missing C | Adopt A's per-user read state, branch scoped and idempotent |
| Finance obligations/invoices | Tuition obligations, invoices/items, installments, allocations | Invoices/items, no complete obligations | Obligations/lines/installments | A+C, incomplete B | Finance owns AR and invoice purpose; one obligation authority |
| Payments/refunds/allocations | Payment, refunds, LIFO/contra and allocation hardening | Payments/refunds | Payment allocations/refunds/corrections | A+B+C, conflicting semantics | Finance owns immutable payment and compensating refund/allocation |
| Discounts/credits/write-offs | Discount authorization, student credits, tuition/advance write-offs | Discounts, less complete | Discounts/financial credits/corrections | A+C, incomplete B | Finance owns approval and source-linked correction facts |
| Double-entry chart/journals/periods | Partial transaction ledger and categories; journals limited | Basic transaction/ledger | Accounts, journals/lines, periods, opening states, reconciliations | C-leading; A+B incomplete | Adopt C shape but strengthen into full Finance posting engine |
| Budgets/expense workflow | Categories, channels, budget lines, expense requests/movements | Budget, expense, savings | Budget/fund allocation and gate evidence, less complete | A+B+C, incomplete C | Finance owns budget and commitment; approval through Workflow, posting through Finance |
| Books inventory/sale | Catalog, receipts, adjustments, sales/refunds, lending/returns | Catalog, restock, sales | Book copies/issuances | A+B+C, conflicting scope | Resources owns stock/custody/lending; Finance posts sale/purchase money |
| Assets/custody/depreciation/disposal | Full fixed asset lifecycle and evidence | Not materially present | Assets/custodies/disposals | A+C, incomplete C | Resources owns physical custody; Finance owns valuation and journal entries |
| Suppliers/AP/returns/terms | Supplier invoices/payments/returns/terms | Not materially present | Not materially present | A-only, missing C | Required Phase 2; Resources owns supplier/receipt; Finance owns AP/payment |
| Loans/interest | Loans, repayments, interest facts and evidence | Not present | Not materially present | A-only, missing C | Required only under signed financing policy; Finance owns liability/interest |
| Donors/campaigns/donations | Donors, campaigns, donations, restrictions and entries | Donors/campaigns/donations | Funding sources/allocations but no complete donor domain | A+B, incomplete C | Funding owns donor/grant intent; Finance records cash and journals |
| Scholarships | Fundings, awards, aid allocation and reprice | Awards, less complete funding | No complete scholarship award | A+B, missing C | Funding owns award eligibility; Finance owns obligation credit/settlement |
| Sponsorships | Agreements, receipts, allocations and terminal lifecycle | Agreements | No complete sponsor domain | A+B, missing C | Funding owns agreement/commitment; Finance owns receipt and allocation |
| Restriction/release/clawback | Donor restrictions, restricted exposure, clawbacks | Limited sponsorship semantics | Fund allocations but no complete restriction enforcement | A+B, incomplete C | Required architecture; Finance enforces restricted spend and release |
| Impact reporting | Impact metrics/reports and derived reporting | Metrics/reports/success stories | Reporting projections/metrics | A+B+C, duplicated definitions | Reporting owns metric catalog; Funding supplies approved program facts |
| Workforce/HR contracts/employment | Employees, teachers, history, evaluations, rates | Employees/teachers, older | HR contracts, employment, leave, scales/statuses | A+B+C, conflicting teacher/employee boundaries | Workforce/HR owns employment; Academic owns teaching work; Payroll owns calculation |
| Payroll calculations/results | Teacher/employee payroll, salary ledgers, corrections/withholding | Teacher salary ledger/basic payroll | Payroll periods/calculations/results/liabilities/settlement proposals | A+B+C, settlement conflict | Payroll calculates; Finance owns liability, posting, and settlement fact |
| Employment settlement | A latest finance settlement semantics | Not materially present in older snapshot | Finance settlement plus retired Payroll compatibility history | A+C, conflicting historical authority | Finance `employment_settlements` is sole fact; remove compatibility authority |
| Rules/policies/versioning | Rule engine, policy catalog, policy versions | Same | Governed configuration and policy registry | A+B+C, scope conflict | Domain policy remains in owner context; cross-cutting governance only in Governance |
| Generic workflows/approvals | Workflow definitions/instances/history and automations | Same older | No complete generic workflow | A+B, missing C | Add Work Management; workflows coordinate, domain commands decide |
| Events and automation | Event registry/bus/handlers/subscriptions | Same | Domain events/outbox partial | A+B+C, incomplete C | Transactional outbox is platform authority; automation allowlisted |
| Audit and forensic evidence | Audit logs/failures, invariant checker | Audit logs | Audit events/attempted/rejected operations | A+B+C | Audit owns audit evidence; domain rows remain source truth |
| Search | Permission/branch-scoped global search | Search | No canonical search | A+B, missing C | Add Search projection with authorization-filtered queries |
| Dashboards/BOS/work queues | Executive BOS, operational work queue, dashboards | Same older | Dashboards/metrics/reporting but not work-first workspace | A+B+C, incomplete/conflicting | Rebuild as Reporting + first-class Workspace |
| Backup/readiness/observability | Backup verification, readiness, logs, invariants | Startup/readiness docs | Integrations/jobs/observability architecture | A+B+C, operationally incomplete C | Add production Operations context and prove restore/runtime |
| Employee Workspace | Role dashboards/queues, not full dynamic workspace | Same | Architecture principle only | A+B incomplete, C-only decision | Required redesign; dynamic, work-first, non-authoritative |
| Management Workspace | BOS/executive dashboards | Same | Reporting/dashboard intent | A+B+C, incomplete | Management decision-support context over canonical metrics |
| API/web parity | Express API + React | Same | Laravel API + Blade, uneven parity | A+B, C incomplete/conflicting | React consumes versioned API; commands shared by web/console |
| Migration/recovery discipline | SQLite schema/convergence scripts | Fresh schema/seed docs | Laravel migration chain, consolidation in progress | A+B+C, incomplete | Clean pre-production baseline; production path uses expand/contract |

---

## 4. Capability quality decisions

### 4.1 Preserve and strengthen

- A's placement content/evidence model, academic policy/lifecycle engines, waitlist/capacity controls, book lending, financial aging, restricted exposure, asset/economic evidence, and invariant mindset.
- C's explicit commands, domain boundaries, PostgreSQL constraints, branch provenance, governed configuration, audit separation, privacy model, Finance correction semantics, employment settlement boundary, and integration job concepts.
- B's earlier product vocabulary, screen composition, and low-friction operational flows only where they map to the final authority model.

### 4.2 Redesign rather than copy

- Finance: replace A/B's SQLite `financial_transactions`-centered approach with a PostgreSQL double-entry posting core plus source-linked subledgers. Keep the capability, not the table shape.
- Identity/access: merge A/B role catalogs and C positions/grants into one AccessDecision service. No frontend or route-local role inference.
- Academic setup: unify A/B catalog/generation with C's versioned program/level/eligibility/progression decisions.
- Funding: retain A's donor/restriction/award depth but make Finance the monetary posting authority.
- Workflows: retain A/B definitions and history but permit only approved internal command adapters, not arbitrary mutation scripts.
- Reporting: retain A/B report breadth and C metric definitions, but centralize formulas, period windows, access, reconciliation, and export.
- Frontend: port the best React product surfaces to a new typed API; do not wrap the existing Blade pages or copy A's monolithic `App.tsx` contract.

### 4.3 Required now, architecturally required, future, optional, or rejected

| Decision class | Capabilities |
|---|---|
| **Required for core launch** | Organization, identity/access, people, CRM/admissions, student lifecycle, placement minimum, academic setup, scheduling, enrollment, class/session/attendance, gradebook/assessment, documents, communication, finance AR/payments/periods/journals, HR/workforce, payroll calculation/liability, audit, reporting, API, React employee/student shells |
| **Architecturally required now; phased operational enablement** | Transactional outbox, Work Management, Search, Notifications, restricted funds, fund dimensions, asset boundary, privacy/retention, backup/restore, management workspace, accessibility, migration reconciliation |
| **Important Phase 2** | Full placement test-bank authoring, scholarships/sponsorships, supplier/AP, books lending and sales, fixed assets/depreciation/disposal, advanced progression/appeals, bank reconciliation, impact/grant reporting, payroll withholding and employee advances |
| **Important future** | LMS content delivery, mobile/PWA offline attendance, external payment gateways, advanced procurement/PO, alumni, transport, hostel, multi-currency/FX, advanced data warehouse |
| **Optional** | Campaign marketing automation, public self-service donor portal, biometric attendance, complex visual workflow designer, AI recommendations, endowment accounting |
| **Not required / rejected** | Microservices at launch, a second SQLite backend, micro-frontends, independent CRM student master, generic ledger in Funding, role-name security, frontend-only permissions, separate Payroll settlement authority, a dashboard that owns tasks/approvals, arbitrary workflow code execution, full LMS before core SIS/ERP integrity |

A phase label is not permission to omit the architecture. The boundaries, owner contracts, tables, events, and authorization decisions are defined now even when an operational UI arrives later.

---

## 5. Final business capability hierarchy

```text
TOEFL HOUSE ENTERPRISE
├── Foundation and control
│   ├── Organization and structure
│   ├── People and identity
│   ├── Calendar and periods
│   ├── Governance and configuration
│   └── Access, privacy, audit, and operations
├── Student and academic mission
│   ├── CRM and admissions
│   ├── Placement and assessment entry
│   ├── Student lifecycle and learner services
│   ├── Academic planning and curriculum
│   ├── Scheduling and facilities
│   ├── Academic delivery
│   │   ├── Enrollment and waitlist
│   │   ├── Classes, sessions, attendance
│   │   ├── Assessments, gradebook, exams
│   │   └── Progression, completion, transcripts, credentials
│   └── Student/guardian portal
├── Workforce and operations
│   ├── HR and employment
│   ├── Teacher work and evaluations
│   ├── Payroll calculation and clearance
│   ├── Resources, books, assets, custody
│   └── Procurement and suppliers
├── Economic control
│   ├── Finance and accounting
│   │   ├── Accounts, journals, periods, reconciliations
│   │   ├── Receivables, obligations, invoices, payments
│   │   ├── Payables, payroll liabilities, settlements
│   │   ├── Budgets, expenses, funds, restrictions
│   │   └── Assets, loans, interest, corrections
│   └── Funding, grants, scholarships, sponsorships, impact
└── Coordination and insight
    ├── Work Management and approvals
    ├── Documents and records
    ├── Communication and notifications
    ├── Search
    ├── Reporting and analytics
    ├── Employee Workspace
    └── Management Workspace
```

This hierarchy is a product hierarchy, not a table hierarchy. A capability may read another context, but it does not own the other's fact merely because it appears in the same workspace.

---

## 6. Final bounded contexts and module map

| Context/module | Owns | May read/command | Must not own |
|---|---|---|---|
| **Organization** | organization/campus/branch/department/cost-center hierarchy and lifecycle | all scoped contexts | student, employment, accounting, or permission facts |
| **People & Identity** | Person, contact methods, relationships, account links, authentication lifecycle | Access, Students, Workforce, CRM | student status, employment state, role grants |
| **Access** | roles, permissions, positions, scope grants, delegations, effective authorization decisions | Organization, Workforce, audit | domain business transitions |
| **Calendar & Governance** | canonical calendar versions, periods, governed configuration and policy metadata | all contexts | operational state or money |
| **CRM & Admissions** | prospects, visitors, campaigns, interactions, applications, admission decisions | People, Placement, Students, Documents, Workflow | student profile creation after approved registrar command; enrollment state |
| **Placement** | test bank, versions, attempts, responses, scoring evidence, recommendations, eligibility snapshots | Admissions, Academic Planning, Documents | class assignment, tuition, admission approval |
| **Students** | student participation/profile lifecycle, guardians, home branch, holds, communications preferences | People, Admissions, Academic, Finance, Documents | academic grade/enrollment state, payments |
| **Academic Planning** | programs, versions, subjects/modules, levels, prerequisites, progression policy, academic setup | Calendar, Placement, Scheduling, Finance fee policies | attendance, payment, payroll |
| **Scheduling & Facilities** | rooms, availability, time slots, reservations, calendar conflicts | Organization, Academic Planning/Delivery, Workforce | class enrollment, grade, monetary value |
| **Academic Delivery** | offerings, sections/classes, class lifecycle, enrollment, waitlist, sessions, attendance, assessments, gradebook, progression, completion, credentials | Scheduling, Students, Placement, Finance, Workforce | invoices/payments, employee salary, donor funds |
| **Workforce & HR** | employee participation, employment, contracts, positions, leave, skills, evaluations, work eligibility | Organization, Access, Academic Delivery, Payroll | payroll result posting, student state |
| **Payroll** | payroll periods, calculations, adjustments, result approval, clearance/proposal | Workforce, Academic teaching facts, Finance | cash/journal settlement, employment settlement fact |
| **Finance & Accounting** | chart of accounts, periods, journals, AR/AP, payments, allocations, budgets, funds, restrictions, payroll liabilities, settlements, asset/loan accounting, corrections | every business source through typed commands | academic grade/enrollment or donor program truth |
| **Funding & Impact** | donors, funders, campaigns, grant agreements, restrictions/intent, scholarship/sponsorship awards, program outcomes and narratives | Finance, Students, Academic, Reporting, Documents | cash, journal, payment, invoice, or bank balance |
| **Resources & Procurement** | book catalog/stock/lending, supplier identity/receipt, physical assets/custody, work orders | Finance, Organization, Students, Documents | accounting balance, payment, financial classification |
| **Work Management** | generic work item, assignment, SLA, approval routing, workflow instance/history | all command endpoints and events | domain approval truth where the domain owns it; permissions |
| **Documents & Records** | document metadata, versions, classification, verification, retention, secure object references | all contexts | underlying student, financial, or authorization fact |
| **Communication & Notifications** | templates, messages, delivery attempts, recipient/read projections | all event sources, Access | business state, permission, balance |
| **Search** | permission-filtered search index and query contract | projections from all contexts, Access | source fact, access grant |
| **Reporting & Analytics** | metric catalog/version, report definitions/runs, projections, reconciliations | all source contexts, Finance journal read model | any source fact or correction |
| **Employee/Management Workspace** | composition, presentation preferences, links to canonical work, freshness metadata | all read APIs, Work Management, Access | tasks/approvals/balances/permissions/lifecycle facts |
| **Integrations & Operations** | outbox relay, inbox/idempotency, delivery endpoints, jobs, readiness, backup/restore telemetry | all events and configured external systems | domain truth or authorization bypass |

The context map is a modular monolith boundary map. It is intentionally not a microservice deployment map. Deployment may be split later only after observed load, ownership, and operational maturity justify it.

---

## 7. Major-fact authority matrix

The following is the minimum authority contract. `DB` names the canonical representation; projections, caches, search rows, and workspace cards are explicitly non-canonical.

| Major fact | Owner/write authority | Lifecycle authority | Authorization path | Canonical database representation |
|---|---|---|---|---|
| Organization/branch | Organization commands | Organization lifecycle | Access + Organization scope | `organizations`, `campuses`, `branches`, `departments`, `cost_centers` |
| Person identity | People & Identity | Person lifecycle | Access identity administration | `people`, contacts, relationships |
| User account/session | People & Identity | Account/session commands | authentication + account status | `user_accounts`, sessions, revocation records |
| Role/permission/scope grant | Access | Access lifecycle | privileged AccessDecision + SoD | `roles`, `permissions`, `position_assignments`, `scope_grants`, `delegations` |
| Applicant/application | CRM & Admissions | Applicant/application state graph | Admissions permission + object scope | `applicants`, `applications`, requirements, `admission_decisions` |
| Visitor/lead | CRM | Lead lifecycle | CRM permission + branch scope | `visitors`, interactions, follow-ups, campaigns |
| Student | Students | Student status/hold/branch-transfer commands | Student permission + current scope | `students`, `student_statuses`, holds, branch transfers |
| Guardian/household relation | Students/People | relationship lifecycle | student/privacy scope | relationship tables linked to Person |
| Placement content | Placement | version/publish/archive graph | curriculum/test-bank permission | test/version/section/question/rubric/media tables |
| Placement attempt/evidence | Placement | attempt/submit/score/review/release graph | Placement + student/object scope | attempts, responses, section results, recommendations |
| Eligibility snapshot | Placement creates; Academic consumes | snapshot supersession | Placement/Academic authorization | signed `academic_eligibility_snapshots` |
| Program/curriculum | Academic Planning | draft → review → published → retired | curriculum-author permission | programs, program versions, subjects, modules, levels, prerequisites |
| Academic term/calendar | Calendar | draft → active → closed | governance/calendar permission | calendar versions, academic periods, holidays |
| Room/availability/reservation | Scheduling & Facilities | availability/reservation lifecycle | facilities + branch scope | rooms, branch availability, reservations |
| Offering/class/section | Academic Delivery | detailed class lifecycle | Academic permission + scope | offerings, class sections/classes, capacity derivation |
| Enrollment/waitlist | Academic Delivery | one enrollment transition graph | enrollment permission + finance gate + object scope | enrollments, enrollment events, waitlist entries |
| Session/timetable | Academic Delivery/Scheduling | scheduled → open → completed/cancelled | teacher/academic scope | sessions, session reservations, provenance links |
| Attendance | Academic Delivery | session attendance correction/lock | teacher own-class or authorized override | attendance facts and correction history |
| Assessment/grade | Academic Delivery | draft → submitted → moderated → published → locked/corrected | teacher/academic + lock/SoD policy | assessments, attempts/results, grade history |
| Progression/graduation/certificate | Academic Delivery | review → approved → issued/revoked if policy permits | academic approval + SoD | progression decisions, graduation decisions, transcripts, credentials |
| Employment/position | Workforce & HR | employment/contract/leave lifecycle | HR + organization scope | employments, contracts/versions, statuses, positions, leaves |
| Teacher assignment/work fact | Workforce + Academic Delivery | assignment/teaching lifecycle | teacher/academic scope | teacher assignments, skills, teaching delivery facts |
| Payroll calculation/result | Payroll | period → calculated → reviewed → approved → cleared | Payroll + SoD | payroll periods/calculations/results/adjustments/clearances |
| Payroll liability | Finance | open → posted → settled/corrected | Finance + Payroll evidence | liability subledger and linked journal lines |
| Employment settlement | Finance | proposed → approved → recorded/corrected | Finance authority + SoD | `employment_settlements` only |
| Obligation/invoice | Finance | draft → issued → open/partial/settled/discharged/corrected | Finance + source gate | obligations, lines, invoices, source links |
| Payment | Finance | received → posted → allocated/refunded/corrected | Finance + payment permission | payments, journal postings, idempotency record |
| Allocation/credit/refund | Finance | proposed → approved → posted/reversed | Finance + SoD | allocations, credits, refunds, correction records |
| Chart/account/journal | Finance | account lifecycle; journal posted/reversed | Finance + period close | accounts, journals, journal lines, posting periods |
| Budget/expense | Finance | draft → approved → committed/posted/rejected | budget/expense SoD | budgets, budget lines, expense requests, commitments |
| Fund/restriction/release | Finance owns monetary dimension; Funding owns intent | restriction → active → released/expired/clawed back | Finance + Funding evidence + SoD | funds, restrictions, fund transactions, releases |
| Donor/grant/scholarship/sponsor intent | Funding & Impact | agreement/award lifecycle | Funding + privacy/document authorization | donors, campaigns, awards, agreements, restrictions metadata |
| Book stock/lending | Resources | catalog/receipt/issue/return/loss/adjustment | resource + student scope | books, copies/receipts/adjustments, issuances/returns |
| Asset custody | Resources | acquired → assigned → transferred/lost/retired | resource + branch scope | assets, custody, transfer/loss/disposal requests |
| Asset accounting value | Finance | capitalized → depreciated → disposed | Finance + asset evidence | asset accounting subledger + journal lines |
| Supplier/AP | Resources creates supplier/receipt; Finance owns payable/payment | supplier/invoice/payment lifecycle | procurement + Finance SoD | suppliers, supplier invoices, AP/payment subledger |
| Loan/principal/interest | Finance | proposed → issued → repayment/interest/closed | Finance + SoD | loans, repayment/interest subledgers, journals |
| Workflow instance/task | Work Management | definition/instance/task/approval lifecycle | command-time domain authorization | workflow definitions/instances/history/work items |
| Domain approval | Owning domain | domain-specific approval graph | domain permission + SoD | domain approval/proposal table; workflow links only |
| Document/version/retention | Documents | registered → submitted → verified → active → expired/archived | Access + document classification | documents, versions, verifications, retention decisions, object refs |
| Message/delivery/read state | Communication/Notifications | queued → sent/delivered/failed/read | recipient scope + Access | messages, attempts, notification/read projections |
| Audit event | Audit | append-only | audit writer; read permission | audit events, rejected/attempted operations |
| Domain/outbox event | Integrations & Operations records; source domain emits | pending → claimed → delivered/dead-letter | internal service authorization | `domain_events`/outbox, inbox/delivery records |
| Metric/report/projection | Reporting | definition/version/run/reconcile | report permission + scope | metric definitions/versions/projections/report runs |
| Search index | Search | build/update/delete/reindex | query-time Access filter | projection index only; rebuildable |
| Workspace composition | Workspace | freshness/assignment/personalization | Access decision on every command | rebuildable workspace projection/preferences; never source authority |

---

## 8. Student, admissions, and placement lifecycles

### 8.1 Admissions and student creation

```text
lead → contacted → qualified → applicant case → requirements pending
     → ready for decision → admitted / waitlisted / rejected / withdrawn
     → student registration command → active student
```

- CRM owns the lead and interaction history.
- Admissions owns applications, requirements, decisions, and admission workflow.
- Students owns the Student aggregate and initial student status creation. Admissions calls a Students registrar port after an approved admission; it does not insert `students` directly.
- A Person may have multiple applications and one student participation. Duplicate detection proposes candidates; only an authorized command resolves identity.
- Admission does not silently enroll or post money. Enrollment and Finance commands are explicit and idempotent.

### 8.2 Placement

```text
requested → scheduled → in_progress → submitted → scored
         → moderated → recommended → reviewed → approved → released
         ↘ appealed / superseded
```

Attempt evidence (questions, responses, media, scoring inputs, assessor identity, timestamps, version) is immutable after submission except through an explicit correction/appeal path. Recommendations are evidence-backed and versioned. Academic consumes a signed eligibility snapshot; placement does not assign a class or bypass an academic/financial gate.

### 8.3 Student lifecycle

```text
registered/active → inactive
active → suspended → active
active → graduated
active → withdrawn/closed (if policy enables it)
```

Student status is a historical lifecycle, not a convenient operational flag. Academic operational eligibility (hold, outstanding required gate, class/period terminal state) is evaluated separately. A student must not be made “inactive” merely to bypass an academic or financial hold.

### 8.4 Enrollment lifecycle

```text
requested → pending_gates → offered/waitlisted → active
active → frozen/paused → active
active → transferred/completed/withdrawn/cancelled
```

One Academic Enrollment command writes enrollment state, enrollment event/history, roster effect, and any required Finance request in one coherent transaction or an explicit outbox follow-up. The Finance gate verifies a canonical obligation/eligibility position; it does not create a second enrollment status. Waitlist ordering, seat capacity, term/program/version correlation, and branch scope are enforced at command and database boundaries.

---

## 9. Complete academic setup and delivery model

Academic setup is not just “programs and levels.” The final model is:

```text
Organization
 └── Campus / Branch
      ├── Academic calendar version
      │    ├── academic year / Jalali periods
      │    ├── terms and holidays
      │    └── active/closed windows
      ├── Facilities
      │    ├── rooms / capacity / type
      │    ├── time slots / weekdays
      │    └── availability / reservations
      ├── Curriculum
      │    ├── Program
      │    ├── Program Version (published immutable snapshot)
      │    ├── Subject / Module
      │    ├── Level / CEFR mapping
      │    ├── ordered version-level membership
      │    ├── prerequisites / progression rules
      │    ├── grading scale / attendance policy
      │    └── placement requirement/profile
      ├── Offering plan
      │    ├── term + branch + program version + level
      │    ├── fee policy reference/snapshot
      │    ├── target capacity and generation rules
      │    └── open/closed lifecycle
      └── Delivery
           ├── class sections and teacher assignments
           ├── room/time reservations
           ├── enrollment and waitlist
           ├── sessions and rosters
           ├── attendance and make-up rules
           ├── assessments/exams/gradebook
           ├── progression/completion
           └── transcript/certificate/diploma output
```

### 9.1 Setup ownership rules

- **Program/version/level/subject/module/prerequisite:** Academic Planning.
- **Term/holiday/calendar conversion:** Calendar.
- **Room/time slot/availability/reservation:** Scheduling & Facilities.
- **Fee schedules and financial policy:** Finance; Academic references a versioned fee policy and snapshots it at the financial command boundary.
- **Placement requirement:** Placement owns assessment profile; Academic validates the released eligibility snapshot.
- **Class/section/capacity/teacher assignment:** Academic Delivery, with Workforce validation for employment/skill eligibility.
- **Attendance/grades/progression/certificate:** Academic Delivery.

Published curriculum versions are immutable. A correction creates a new version or an explicit academic correction record; it does not rewrite historical enrollments and transcripts. Capacity is derived from linked sections and seat-consuming enrollment states, not maintained as a second editable total. Time and branch correlation are database-backed where possible and command-validated everywhere.

### 9.2 Academic financial boundary

Academic can request a tuition/fee quote and require an eligibility gate. Only Finance creates obligations, invoices, payments, discounts, credits, and allocations. Enrollment stores no monetary authority beyond a stable reference to the Finance obligation/quote snapshot. A transfer preserves the historical obligation and does not fabricate a new charge; a retake creates a new explicitly priced financial fact.

---

## 10. Employee, HR, payroll, and management architecture

### 10.1 Workforce lifecycle

```text
person → candidate/employee participation → employed
       → active assignment(s) → leave/suspended → active
       → terminated/settled
```

Workforce/HR owns contracts, contract versions, positions, organizational assignment, leave, skills, evaluations, and employment eligibility. Academic owns the teacher's actual teaching assignment and delivery facts. A person may hold several legitimate positions; Access resolves the effective capabilities and scope from all active assignments.

### 10.2 Payroll lifecycle and settlement

```text
period open → inputs collected → calculated → reviewed → approved
            → liability recognized by Finance → paid/cleared → period closed
```

- Payroll calculates from approved employment/teaching facts and produces a reproducible result with input references and policy version.
- Finance recognizes the liability, posts journals, controls cash, and records payment/settlement.
- `employment_settlements` in Finance is the only final settlement fact.
- Payroll's proposal and clearance are workflow evidence, not a second monetary ledger.
- Salary advances, deductions, bonuses, withholding, write-offs, and corrections must each have explicit policy, source linkage, period treatment, SoD, and compensating-entry semantics. No developer may invent rates or classifications from a UI requirement.

### 10.3 Employee Workspace

The Employee Workspace is the primary operational environment. It composes, at request time, from:

- current Person/account and employment status;
- all active positions and assignments;
- current organization/branch/department scope;
- effective permissions and delegations;
- assigned work items and domain proposals;
- deadlines, SLAs, approvals, blocked items, exceptions, and notifications;
- canonical links to student, academic, HR, Finance, document, and communication commands;
- freshness and provenance labels.

It is **work-first and exception-first**: “what needs attention, what is overdue, what is blocked, what changed, what can I do now, and which canonical command completes it?” A multi-position employee sees combined legitimate work without a static role lock. Every sensitive action is reauthorized against current state; a stale card cannot preserve authority after termination, scope removal, delegation expiry, or branch transfer.

Workspace may own presentation preferences and rebuildable aggregation rows. It may not own a task outcome, approval decision, financial balance, enrollment, grade, employment status, or permission.

### 10.4 Management Workspace

Management views are separate from employee queues and emphasize:

- trend and period comparison;
- enrollment, attendance, outcomes, retention, staffing, cash, receivables, restricted-fund exposure, budget, payroll, and risk;
- exceptions, unresolved approvals, policy breaches, and branch comparison within authorized scope;
- drill-through to the owning command or source evidence;
- as-of timestamp, freshness, formula/version, scope, and reconciliation status.

Management cannot bypass domain authorization because a metric is visible. Sensitive drill-through is individually authorized.

---

## 11. Donor, funding, scholarship, sponsorship, and restricted-fund decision

### 11.1 Decision

Funding is an **architecturally required bounded context and a Phase 2 operational capability**. The static evidence in A/B is coherent enough to justify donor, campaign, scholarship, sponsorship, and impact workflows, especially for NGO/scholarship-supported learners. It is not permitted to become a second monetary subsystem.

### 11.2 Ownership split

| Fact | Funding & Impact | Finance |
|---|---|---|
| Donor identity, contact, relationship | owns | reads |
| Campaign, program, grant agreement, narrative outcome | owns | references |
| Donor restriction/eligibility intent | owns the legal/business intent and evidence | enforces spendability in monetary posting |
| Donation receipt/cash/bank movement | requests/links | owns payment, journal, cash, and reconciliation |
| Fund/balance/restricted net position | reads and explains | owns fund subledger and journal |
| Scholarship eligibility/award | owns | receives an approved allocation command |
| Sponsorship agreement/seat commitment | owns | records sponsor receipt and obligation settlement |
| Tuition waiver/credit/obligation allocation | requests with award evidence | owns financial credit/allocation |
| Expense charged to a program/fund | supplies program linkage | posts and rejects restriction violations |
| Impact report | supplies approved outcomes/narrative | supplies reconciled financial facts; Reporting publishes |
| Clawback/release | proposes under agreement/policy | posts monetary reversal/release |

Campaign target amounts and award commitments are not bank balances. Actual cash, receivable, revenue classification, fund balance, obligation, payment, and release are Finance facts.

### 11.3 Fund model

A fund is a Finance dimension/subledger, not a separate bank account and not a Funding-owned ledger. Each restricted contribution stores structured restriction type, purpose/program target, time window, source agreement/document, and allowed dimensions. A restricted expense or scholarship allocation is accepted only when Finance can prove eligibility. Release/reclassification is an explicit source-linked fact with evidence. Restricted and unrestricted balances are reported separately even if physical cash is pooled.

No free-text “restricted” flag, campaign sum, scholarship total, or sponsorship total may be treated as financial truth. Every funding action has an idempotency key, branch/organization scope, document/evidence link, and audit event.

---

## 12. Finance, accounting, budgets, resources, and operational economics

### 12.1 Canonical finance model

Finance uses AFN as the launch currency. Amounts are stored as integer minor units through a currency value object; AFN is configured with the approved local scale. Non-AFN transactions are rejected until an explicit FX policy, rate source, valuation date, and gain/loss treatment are approved. No floating-point money is accepted.

The accounting core is:

```text
source fact (invoice/payment/payroll/AP/asset/fund/loan)
        ↓ source-linked Finance command
subledger position + journal entry
        ↓ posted period
accounts / journal lines / reconciliations
        ↓ read-only
P&L, balance, aging, fund exposure, budget, management projections
```

- `journals` and `journal_lines` are the accounting authority.
- Subledgers are the operational authority for their fact and must link to journal entries.
- Account balances are derived from posted journal lines or a rebuildable materialized projection; a mutable balance cache is never the only truth.
- Posted facts are immutable. Corrections reverse or compensate with a reason, actor, source ID, period, and audit trail.
- Period close prevents unapproved posting; a controlled reopening creates an auditable event.

### 12.2 Finance capabilities

**Receivables:** obligations, invoice purpose/line items, due dates, installments, discounts, credits, payment allocation, refunds, write-offs/discharges, aging, and student balances.

**Payables:** supplier, invoice, goods receipt, payment terms, due/overdue status, return/refund, payable aging, and settlement. A supplier receipt without a payable is not silently classified as an expense.

**Payroll liabilities:** Payroll result approval creates a Finance liability; payment reduces liability and cash; withholding creates a liability until remitted; settlement is Finance-owned.

**Budgets:** annual/period budget, branch/program/fund/cost-center dimensions, commitments, approved expense, available balance, transfers/returns, and budget variance. Budget is a control projection tied to journal/commitment facts, not a second cash store.

**Assets:** Resources owns physical asset identity, custody, transfers, loss, and condition. Finance owns capitalization, carrying value, depreciation, impairment policy when approved, and disposal proceeds/gain/loss through journal evidence.

**Books:** Resources owns catalog/copies/stock/issue/return/loss. Finance owns acquisition cost, sale revenue, refund, and inventory journal treatment. A book sale is not a generic payment with an inferred category.

**Loans:** Finance owns principal, lender, terms, repayments, interest facts, outstanding position, and journal classification. This capability remains disabled until policy supplies lender/rate/schedule semantics.

### 12.3 Financial invariants

At minimum, runtime validation must prove:

- journal debits equal credits per entry and period;
- cash/account derived position reconciles to posted journals and approved opening state;
- every payment/refund/allocation is idempotent and source-linked;
- no allocation exceeds an obligation, fund, payment, or award position;
- no restricted expense or scholarship allocation bypasses restriction eligibility;
- no closed period mutation bypasses correction/reopen policy;
- no payroll payment exceeds approved due/liability and settlement is not duplicated;
- asset disposal cannot produce cash or income without a custody/value fact;
- budget availability and commitments remain non-negative under concurrent commands;
- every report number identifies its metric, formula version, scope, period, and source projection.

---

## 13. Documents, communication, notifications, workflow, and approvals

### 13.1 Documents and records

Documents owns metadata, classification, versions, verification state, retention decisions, legal hold, and secure object references. Business contexts own whether a document is required and what decision it supports. The object store is private; access is granted through a short-lived authorized download/preview path. File MIME, size, malware, hash, version, uploader, and retention policy are recorded. A printed invoice, transcript, certificate, or payroll slip renders an authoritative source snapshot and never creates a new source fact.

### 13.2 Communication and notifications

Communication owns message templates, recipient resolution, queued message, delivery attempts, provider response, retry, and failure. Notifications are event-derived user/branch alerts with per-user read receipts. A notification is never a command authorization or a source of truth. Sensitive messages are minimized, redacted, and retention-controlled.

### 13.3 Work Management and workflow

Workflow definitions, instances, tasks, assignments, approvals, deadlines, escalation, and history are centralized in Work Management, but a domain-specific approval remains owned by its domain. For example, a Finance correction approval is a Finance fact; Workflow records the work and route around it.

Allowed workflow effects are explicit typed adapters:

```text
workflow trigger → authorize current actor → call registered domain command
                 → domain transaction commits → outbox event → projection/notification
```

No arbitrary PHP/JavaScript expression, direct table mutation, hidden financial transition, or undeclared webhook is allowed. Automatic actions start with internal notifications and safe work assignment; domain mutations require a registered command with its own authorization, idempotency, and audit behavior.

---

## 14. Security, privacy, audit, and separation of duties

### 14.1 Authorization

AccessDecision evaluates `(actor, action, resource, organization, branch, department, object ownership, employment/account lifecycle, delegation, time, SoD, purpose)` on every sensitive command and protected read. A user may have multiple positions; effective permission is the deterministic union/intersection policy declared by Access, with explicit denies and narrower-scope restrictions. A missing or unknown branch is denied. Cross-branch organization authority is explicit, not inferred from `null`.

Frontend `tabAccess` is a usability projection only. API routes, controller commands, queries, object storage, background jobs, and integrations all call the same policy service. Queries must scope at the database/query-builder boundary, not merely filter arrays after broad retrieval.

### 14.2 Separation of duties

Examples enforced by policy and validated at command time:

- admission decision, student registration, and financial receipt may be performed by different capabilities;
- teacher records attendance/grades but cannot approve their own grade override or payroll result;
- payroll prepares/calculates while Finance approves/post/settles;
- expense requester cannot be the sole approver where threshold policy requires separation;
- donor/funding operator can record intent but cannot release restricted cash without Finance authority;
- reconciliation observer cannot approve their own reconciliation;
- Access administrator cannot silently grant self-escalating organization-wide permission;
- audit readers cannot mutate audited records.

### 14.3 Privacy and threat controls

The platform requires password hashing, secure same-origin cookies or short-lived tokens as appropriate, CSRF protection, rate limiting, session revocation, secure headers, secrets outside source, structured redacted logs, input/schema validation, SQL parameterization, object-level authorization, signed document access, data export approval for bulk sensitive data, retention/legal hold, and audit of rejected attempts.

Threat matrices must include IDOR, branch/campus leakage, stale delegation, inactive employee access, replay/double post, direct SQL bypass, mass assignment, report export leakage, document URL leakage, search leakage, workflow privilege escalation, queue retry duplication, and cross-tenant/cross-organization access.

---

## 15. Reporting, analytics, search, and management control

### 15.1 Reporting

Reporting defines a versioned metric catalog. Each metric declares:

- stable ID and business definition;
- owner/source tables or projection;
- period/calendar semantics and timezone;
- unit, currency, precision, and null behavior;
- scope dimensions and required permission;
- formula version and reconciliation rule;
- freshness expectation and export policy.

A report run captures metric versions, filters, as-of timestamp, actor/scope, source snapshot/projection version, and output hash. Dashboard, CSV, print, workspace cards, and management views consume the same report/metric executor. Browser reducers never define enrollment, revenue, payroll, or financial truth.

### 15.2 Search

Search begins as a PostgreSQL-backed projection with normalized names, identifiers, phones, branch/object type, and access metadata. It is queried with a server-side access predicate and bounded result cap. Search results link to authorized source views; they do not reveal restricted fields simply because an index contains them. OpenSearch/Elasticsearch is a future read-scale option, not a second business database.

### 15.3 Management controls

The management workspace exposes finance and operating measures only through Reporting and Finance read contracts. It shows reconciliation status, stale projections, outbox backlog, failed deliveries, overdue receivables/payables, restricted-fund exposure, budget variance, payroll liability, academic outcomes, staffing, and unresolved work. It provides drill-through to evidence and source owners, not hidden direct writes.

---

## 16. System topology and technology decisions

```text
Browser / responsive React application
        │ same-origin HTTPS, typed JSON, accessibility/i18n shell
        ▼
Laravel API + web/print adapters
        │ authentication, rate limit, request validation, AccessDecision
        ▼
Application services / commands / queries
        │ module contracts; transaction boundary; idempotency
        ├── bounded domain modules
        ├── PostgreSQL repositories and constraints
        ├── transactional outbox + audit write
        └── projection/report query services
                  │ commit
                  ▼
PostgreSQL primary
        ├── canonical domain tables
        ├── journal/subledger tables
        ├── audit and outbox tables
        └── rebuildable projections/index tables
                  │ async relay
                  ▼
queue/relay → notifications, search, reporting projections, documents, integrations
```

### 16.1 Why modular monolith

A single deployable application keeps Finance, Academic, Student, Payroll, Access, and workflow commands transactionally consistent while the institution is small enough that service-network failure and distributed data ownership would be overengineering. Modules have strict dependency direction and contracts. If scale later proves a split necessary, the outbox/API contracts permit extraction without first allowing duplicate authorities.

### 16.2 Why React rather than Blade plus React fragments

A single React application provides consistent workspace composition, route-level permission projections, mobile/reception/teacher flows, shared form validation, RTL, accessible stateful controls, server-state caching, and API/web parity. Blade remains only for backend-rendered print/PDF output where it is a document renderer. A second interactive Blade frontend would duplicate navigation, authorization presentation, and validation.

### 16.3 Frontend decisions

- React + TypeScript + Vite.
- React Router for explicit route boundaries.
- TanStack Query or equivalent typed server-state cache; no business truth in global UI state.
- OpenAPI-derived TypeScript contracts or an equivalent generated contract package; no hand-maintained A/B/C response shapes.
- Shared design system with semantic HTML, keyboard operation, focus management, form errors, loading/empty/error/permission states, responsive tables, 44px practical touch targets, and WCAG 2.2 AA target.
- English and Persian/Dari translations with first-class RTL, locale-aware dates, and a single calendar formatting policy.
- Feature folders aligned to bounded contexts, not one monolithic `App.tsx` or server route file.

---

## 17. Data architecture and database ownership

### 17.1 PostgreSQL schema organization

Use one PostgreSQL database with schema ownership conventions and module prefixes only where helpful; do not create separate databases per module at launch. Every table has:

- UUID/opaque ID and created/updated metadata as appropriate;
- organization/branch provenance when the fact is scoped;
- actor/source/correlation/idempotency fields on commands;
- foreign keys and unique constraints for structural invariants;
- check constraints for enum/value bounds;
- explicit effective dates and lifecycle columns rather than overloaded booleans;
- append-only history or correction records for sensitive state;
- indexes designed for scope, lifecycle, period, and source lookups.

Canonical table groups include `foundation`, `identity_access`, `crm_admissions`, `students`, `placement`, `academic`, `scheduling`, `workforce`, `payroll`, `finance`, `funding`, `resources`, `workflow`, `documents`, `communication`, `reporting`, `audit`, `outbox`, and `operations`. These are ownership conventions, not permission bypasses.

### 17.2 No duplicate representations

- `students.status` is not duplicated in Admissions or CRM; their views project student status.
- `enrollments` do not own payment totals; Finance obligations do.
- Payroll results do not own cash or final settlements.
- Funding totals do not replace Finance funds/journals.
- Dashboard balances, report projections, search rows, and workspace cards are rebuildable.
- `financial_transactions` as a generic catch-all is not the final accounting authority. Legacy rows are mapped to typed source facts and journals during migration.
- Historical migration names may remain in Git history, but obsolete final-schema tables are removed from the target baseline.

### 17.3 Concurrency

PostgreSQL transactions use appropriate row locks and unique/idempotency constraints for payment allocation, enrollment seats, waitlist offers, budget movement, payroll approval, fund allocation, corrections, and workflow actions. Projection consumers use inbox/deduplication records. Long reports use consistent snapshot/as-of semantics or a versioned projection, never an unbounded live join that silently mixes periods.

---

## 18. API, command, query, event, and frontend contract

### 18.1 API shape

The final API is `/api/v1` with resource reads and command endpoints. Examples:

```text
GET  /api/v1/me
GET  /api/v1/students/{id}
POST /api/v1/admissions/applications/{id}/approve
POST /api/v1/students/{id}/register
POST /api/v1/enrollments/{id}/freeze
POST /api/v1/finance/payments
POST /api/v1/finance/payments/{id}/refund-proposals
POST /api/v1/finance/settlements/{proposalId}/approve
POST /api/v1/payroll/calculations/{id}/approve
POST /api/v1/academic/placement-attempts/{id}/submit
POST /api/v1/work-items/{id}/complete
GET  /api/v1/reports/catalog
POST /api/v1/reports/runs
GET  /api/v1/search?q=...
```

Every mutation accepts a client idempotency key where replay is possible and returns a stable command/result envelope containing correlation ID, source ID, lifecycle outcome, and relevant projection freshness. Errors have stable code, message, field errors, authorization outcome, and retryability.

### 18.2 Transport rules

Controllers/FormRequests handle syntax, authentication context, request limits, and response serialization. Application commands perform authorization, lifecycle, business rules, transaction, source write, audit, and outbox recording. Queries return DTOs from canonical sources or named projections. Console/web/API/queue handlers invoke the same command/query layer.

No frontend endpoint may directly expose a table or let the browser calculate permissions, balances, academic eligibility, finance categories, or lifecycle transitions. Every response declares scope and, where applicable, as-of/freshness metadata.

### 18.3 Compatibility

A/B route names are not preserved as authority. A temporary compatibility adapter may translate a legacy client to `/api/v1` only if it calls the same command and carries no duplicate write. Legacy aliases are removed after the React cutover and contract tests prove no consumers remain.

---

## 19. Dependency and data graph

### 19.1 Dependency direction

```text
Organization / People / Calendar
          ↓
Access ───────────────┐
          ↓           │ authorization only
CRM → Admissions → Placement
  │          │          ↓ eligibility evidence
  └──────────┴────→ Students
                         ↓
Academic Planning → Scheduling → Academic Delivery
       │                 │              │
       └──────────────→ Finance gate ←──┘

People → Workforce → Payroll → Finance
Students / Academic / Resources / Funding ───────→ Finance
All contexts ─────────→ Documents / Communication / Work Management
All canonical facts ──→ Outbox → Reporting / Search / Notifications / Integrations
Reporting / Finance / Work Management ───────────→ Employee & Management Workspace
```

### 19.2 Typed edges

- `OWNS`: domain writes canonical fact.
- `COMMANDS`: one context requests a state transition in another through a typed application port.
- `VALIDATES`: a context checks an invariant without writing the other context.
- `REFERENCES`: stable source ID/link only.
- `CONSUMES_EVENT`: asynchronous projection/notification/integration reaction.
- `PROJECTS`: rebuildable read model.
- `DOCUMENTS`: renders evidence from a source fact.
- `ASSIGNS`: Work Management routes human work; it does not decide domain truth.

A dependency may not point from Reporting, Search, Workspace, Notification, or UI back into a source table to mutate it.

---

## 20. Event, outbox, automation, and integration graph

```text
Domain command
  ├─ transaction: canonical source fact
  ├─ transaction: audit event
  └─ transaction: outbox event {id, type, version, aggregate, actor, scope,
                                  correlation, causation, occurred_at, payload}
                         │ commit
                         ▼
                outbox relay / claim lease
                         │ at-least-once
       ┌─────────────────┼──────────────────┐
       ▼                 ▼                  ▼
Reporting projection  Search index    Notification/work item
       │                 │                  │
       └───────┬─────────┴──────────┬───────┘
               ▼                    ▼
          management UI        external integrations
```

### 20.1 Event rules

- Domain events describe committed facts, not requests that might fail.
- Event schemas are versioned and contain tenant/organization/branch scope, aggregate ID, actor, correlation/causation, and idempotency identity.
- The outbox row is written in the same transaction as the source fact.
- Relay claims are leased; retries back off; dead letters are visible; poison events do not block unrelated events.
- Consumers use an inbox/processed-event key and idempotent upserts.
- Per-aggregate order is preserved where state transitions require it; global ordering is not assumed.
- Audit events and outbox events are different: audit answers “who attempted/changed what,” outbox answers “what committed and needs delivery.”
- Workflow automations consume only registered event types and call allowlisted commands. No handler may write Finance/Academic/HR tables directly.

### 20.2 Representative event vocabulary

`BranchActivated`, `ApplicantAdmitted`, `StudentRegistered`, `PlacementReleased`, `ProgramVersionPublished`, `EnrollmentActivated`, `SessionCompleted`, `AttendanceRecorded`, `GradePublished`, `GraduationApproved`, `PayrollResultApproved`, `FinanceLiabilityRecognized`, `PaymentPosted`, `RefundPosted`, `FundRestrictionCreated`, `RestrictedAllocationPosted`, `AssetTransferred`, `DocumentVerified`, `WorkflowTaskAssigned`, `ReportProjectionRebuilt`.

---

## 21. Finance and end-to-end business graphs

### 21.1 Student money path

```text
Academic fee policy snapshot
       ↓
Enrollment command requests Finance obligation
       ↓
Finance obligation + invoice (purpose and source enrollment)
       ↓
Payment received → journal cash/AR → allocation to obligation
       ├─ scholarship/sponsorship allocation (approved Funding evidence)
       ├─ installment position
       ├─ refund/credit/correction
       └─ discharge/write-off under Finance policy
       ↓
student balance / aging / academic gate / reports
```

The academic screen can display the Finance position, but it cannot subtract a payment from a local total.

### 21.2 Workforce money path

```text
Employment/teaching facts
       ↓
Payroll calculation + review + approval
       ↓
Finance liability recognition + journal
       ↓
payment / withholding remittance / correction / employment settlement
       ↓
ledger, cash, payroll reports, management projections
```

### 21.3 Funding money path

```text
Donor/grant agreement + restriction evidence
       ↓ Funding intent
Donation receipt / grant receivable command
       ↓ Finance payment/journal → restricted fund dimension
       ↓ eligible program/student expense or scholarship allocation
       ↓ explicit release/reclassification/clawback + impact evidence
```

No Funding total may update cash. No Finance expense may ignore a structured restriction. No impact report may claim outcomes from an unapproved or unreconciled source.

### 21.4 Resource money path

```text
Supplier / book receipt / asset acquisition
       ↓ Resources physical fact
Finance payable/capitalization/inventory journal
       ↓ payment / return / sale / depreciation / disposal
       ↓ reconciled resource and accounting projections
```

---

## 22. Migration, schema consolidation, and data cutover strategy

### 22.1 Pre-production baseline

The target is a clean, consolidated PostgreSQL schema after the architecture stabilizes. Do not preserve obsolete migration history as a runtime design merely because it is historically present. The current C chain is evidence and migration input, not the final schema.

Sequence:

1. freeze the authority matrix and target table catalog;
2. classify every current C migration/table as **retain**, **reshape**, **merge**, **project**, **archive**, or **drop**;
3. create a new target baseline migration set grouped by bounded context with explicit foreign keys, check constraints, indexes, outbox/audit/idempotency tables, and journal/subledger rules;
4. remove obsolete authorities, including retired Payroll settlement tables/models and any duplicate compensation/work-base/final-settlement authority;
5. define deterministic seed data only for roles, permissions, calendar defaults, accounting chart templates, and policies whose ownership is approved;
6. run static schema lint and migration preflight before any runtime application;
7. build import mappings for A/B/C source data only after each source field has an owner and reconciliation rule;
8. cut frontend/API contracts to `/api/v1` after source mappings are stable.

### 22.2 Migration classes

- **Foundation:** organizations, campuses, branches, departments, people, accounts, access.
- **Academic:** programs/version/levels, calendar, rooms, offerings, classes, enrollment, attendance, assessments, progression.
- **Economic:** accounts, periods, chart, obligations, payments, journals, funds, budgets, payroll liabilities, assets/AP/loans.
- **Evidence/platform:** documents, audit, outbox, idempotency, workflow, reporting metadata, search projection.

Each migration must be forward-compatible and safe to rerun only where the operation is declared idempotent. Business seeders cannot invent money, students, employees, roles, or approvals.

### 22.3 Clean versus historical cutover

The static source register currently gives no production-data preservation guarantee. If the owner confirms a clean pre-production environment, the target baseline may be destructive and faster. If any historical data exists, cutover requires a read-only source snapshot, row counts, duplicate identity report, branch-scope report, financial trial balance, obligation/payment allocation reconciliation, payroll liability reconciliation, document hash inventory, and signed acceptance before dropping anything.

No migration may silently convert a loan to capital, a restricted donation to unrestricted revenue, a Payroll settlement to a second Finance settlement, or a report projection to source truth.

### 22.4 Backup and recovery

Before each migration rehearsal: PostgreSQL backup, checksum, restore into an isolated database, migration apply, constraint/FK validation, journal trial balance, projection rebuild, search rebuild, outbox replay, and report reconciliation. Runtime restore and disaster-recovery evidence is a release gate, not a documentation claim.

---

## 23. Missing-capability analysis and explicit non-goals

### 23.1 Capabilities missing or materially incomplete in C

The current C checkout does not statically demonstrate a complete final implementation of: A's full placement test bank and scoring breadth; generic Work Management/workflows; Search; per-user Notifications; Employee Workspace; Management Workspace; full donor/restriction/scholarship/sponsorship; books sale/lending parity; supplier/AP; loans/interest; fixed-asset accounting; bank reconciliation; advanced operational report catalog; complete event consumer chain; React frontend; and a fully proven transactional outbox delivery path.

These are implementation gaps against the final architecture, not reasons to copy A's route/table structures wholesale.

### 23.2 Capabilities intentionally postponed

- multi-currency/FX;
- full LMS and digital content delivery;
- hostel/transport/canteen/alumni unless business discovery proves demand;
- biometric or face-recognition attendance;
- arbitrary third-party workflow scripting;
- public donor portal;
- data warehouse/event lake;
- microservice extraction;
- advanced procurement purchase-order commitments until supplier/payment policy is approved.

### 23.3 Policy gates that must not be invented

Before enabling affected commands, the owner must define: cash variance/counting, withholding rate/tax treatment, FX, loan rate/schedule, supplier terms and payable recognition, asset depreciation/impairment, disposal policy, restricted-fund release, write-off thresholds/recovery, data retention/legal hold, and donor gift acceptance. A missing policy is a controlled “not enabled” state, not an invitation to default to `other` income/expense.

---

## 24. Independent research findings and adopted patterns

The source reconstruction was supplemented by independent reference research. The findings are architectural inputs, not legal or accounting advice.

1. **Education ERP/SIS patterns:** mature education platforms converge on an integrated student lifecycle from inquiry/admission through enrollment, attendance, assessment, transcript/graduation, fee management, portals, HR/payroll, communication, and reporting. They also distinguish these operational areas rather than making one student table own every fact. This supports the Academic/Admissions/Students/Finance/Workforce split. Useful comparative references include [OpenEduCat's education ERP capability overview](https://openeducat.org/education-erp/) and its [module overview](https://openeducat.org/open-source-education-erp/).
2. **Transactional outbox:** AWS Prescriptive Guidance describes saving business state and the outbox notification in one local transaction, then relaying committed events; duplicate delivery and ordering require idempotent consumers and explicit sequence handling. The final design adopts exactly that reliability model, rather than claiming exactly-once delivery. See [AWS Transactional Outbox Pattern](https://docs.aws.amazon.com/prescriptive-guidance/latest/cloud-design-patterns/transactional-outbox.html).
3. **Zero-trust authorization:** NIST SP 800-207 rejects implicit trust based on network location or account ownership and requires authorization decisions around the subject, device/resource, and request context. This supports command-time AccessDecision, object/scope checks, deny-by-default unknown scope, and no frontend security. See [NIST SP 800-207](https://csrc.nist.gov/pubs/sp/800/207/final).
4. **Restricted resources:** Donor restrictions must remain distinguishable from unrestricted resources, carry source documentation, constrain eligible spend, and support release/reclassification reporting. Campaign totals or a free-text restriction field are not sufficient. The final architecture therefore makes restrictions structured Finance dimensions with Funding evidence and explicit release. Comparative guidance: [PwC donor-imposed restrictions](https://viewpoint.pwc.com/dt/us/en/pwc/accounting_guides/not-for-profit-entities/Not-for-profit-entities/Nfp06_1/67_Donorimposed_restrictions_14.html) and [IRS Schedule D guidance](https://www.irs.gov/instructions/i990sd).
5. **Accessibility:** WCAG 2.2 adds or emphasizes focus visibility, keyboard/touch operation, consistent help, redundant-entry reduction, and accessible authentication. The React design system targets WCAG 2.2 AA, with keyboard, focus, mobile, RTL, form-error, and accessible-authentication acceptance tests. See [W3C WCAG 2.2 Recommendation](https://www.w3.org/TR/WCAG22/).
6. **ERP/finance control pattern:** mature systems keep operational subledgers close to their source processes while centralizing accounting posting and reporting. This supports Resources owning physical custody, Funding owning intent, Payroll owning calculation, and Finance owning journal/liability/settlement truth.
7. **Research limitation:** these sources benchmark patterns; they do not prove local Afghan legal, tax, donor, employment, currency, or education policy. Local policy gates remain explicit prerequisites.

---

## 25. Rejected alternatives

| Alternative | Rejection reason |
|---|---|
| Keep System A unchanged | SQLite and route-level SQL are not a sufficient final multi-worker financial/authorization platform; large route files encourage hidden writers; its feature breadth does not remove deployment and ownership problems |
| Keep System B unchanged | It is an older packaged subset of A, with materially less academic placement, finance, resource, correction, and accounting depth |
| Keep System C unchanged | C has strong architecture but is incomplete in product capability and uses Blade/uneven transport against the final product requirement |
| Merge all three schemas and routes | Produces duplicate identities, roles, finance balances, lifecycle tables, route semantics, and migration hazards; breadth is not architecture |
| Treat B as an independent product to preserve | Static inspection shows B is an earlier sibling/package in the A lineage, not a separately justified authority |
| Microservices now | Adds network, distributed transaction, deployment, and observability complexity before organizational scale requires it; modular boundaries are sufficient and extractable later |
| Keep SQLite for simplicity | File-level backup/WAL is not the required PostgreSQL concurrency, row-locking, constraint, restore, and reporting posture |
| Use Blade plus React pages as a permanent hybrid | Duplicates navigation, state, accessibility, validation, and permission presentation; only print/PDF rendering remains backend-side |
| Let Funding own donation balances | Creates a second monetary authority and makes restrictions/receipts diverge from Finance |
| Let Payroll own final settlement | Creates a second cash/liability authority and was already identified as an unsafe compatibility path |
| Let Academic own tuition totals | Academic facts and financial facts have different lifecycles; local totals drift and bypass Finance corrections |
| Let Workflow mutate domain tables generically | Generic mutation cannot safely encode domain invariants, SoD, accounting, or lifecycle semantics |
| Let dashboard/workspace/search projections become convenient writers | Projections are stale/rebuildable and must not become shadow authorities |
| Use static roles or browser tab hiding as authorization | Fails with multiple positions, scope, delegation expiry, direct API calls, and stale UI state |
| Preserve every migration for historical purity | Pre-production redesign allows clean baseline consolidation; obsolete runtime authorities must be removed |
| Implement every ERP feature immediately | Transport, policy, and authority quality would be sacrificed to feature count; phase capability by value and proof |

---

## 26. Implementation sequence and release waves

### Wave 0 — Architecture and contract freeze

- ratify this blueprint and the authority matrix;
- freeze bounded contexts, naming, lifecycle graphs, event envelope, API error/idempotency contract, money/currency policy, and policy gates;
- create the target table/command/query/event registries;
- identify any actual data that contradicts the clean-baseline assumption.

### Wave 1 — Platform foundation

- PostgreSQL baseline, organization, People/Identity, AccessDecision, Calendar, Governance;
- audit, idempotency, transactional outbox, correlation, error contract;
- document storage boundary, secret/session/security baseline;
- React shell, design system, locale/RTL, auth/session, typed API client.

### Wave 2 — Student acquisition and identity

- CRM visitors/sources/campaigns/follow-ups;
- Admissions applications/requirements/decisions;
- Students/person links/guardians/status/holds/branch transfer;
- Documents and communication primitives;
- reception and student profile workspaces.

### Wave 3 — Academic setup and delivery

- calendar/terms/holidays, programs/versions/levels/modules/prerequisites;
- placement minimum and signed eligibility snapshot;
- rooms/availability/time slots/scheduling;
- offerings/classes/capacity/teacher assignments/enrollment/waitlist;
- sessions/attendance, assessment/gradebook/grade lock;
- progression, transcripts, graduation, certificates;
- academic and teacher workspaces.

### Wave 4 — Finance core and payroll

- chart of accounts, posting periods, journals/lines, opening/reconciliation;
- obligations/invoices/payments/allocations/refunds/discounts/installments;
- budgets/expenses/commitments and financial approvals;
- Workforce/HR employment and Payroll calculation;
- Finance payroll liability, cash payment, settlement, correction;
- student and finance workspaces with reconciled reports.

### Wave 5 — Resources and funding

- books catalog/stock/issue/return/sale with Finance integration;
- physical assets/custody and Finance asset accounting;
- donor/fund/restriction/award/scholarship/sponsorship/impact;
- supplier/AP/returns/terms;
- enable loan/interest and withholding only after policy gates.

### Wave 6 — Work, insight, and operational scale

- Work Management definitions/instances/tasks/SLAs/escalations;
- notifications/read state, Search, report catalog/projections/reconciliation;
- Employee Workspace and Management Workspace composition;
- API/web/console parity, exports/prints, advanced management controls;
- backup/restore, readiness, monitoring, runbooks, retention, incident operations.

### Wave 7 — Migration and independent assurance

- source data mapping and reconciliation;
- clean or expand/contract cutover rehearsal;
- runtime test matrix, accessibility/security/financial review;
- performance/load, backup restore, outbox crash/retry, browser usability;
- owner acceptance and production-readiness decision.

Every wave must finish with authority review, migration/schema review, API contract review, threat review, projection reconciliation, and a documented rollback/disable path. “Screen exists” is not completion.

---

## 27. Runtime-only validation plan

Runtime work is intentionally deferred. When dependencies and an approved environment are available, execute the following in a controlled validation phase; do not report static completion as runtime success.

### 27.1 Foundation and migration

- clean PostgreSQL migration apply and seed idempotency;
- migration upgrade from a representative C snapshot and mapped A/B fixtures;
- rollback/restore rehearsal and backup checksum verification;
- foreign-key, check, unique, trigger, index, and partition verification;
- migration preflight rejection for duplicate identities, orphan rows, unknown branches, invalid money, unbalanced journals, duplicate settlements, and unsupported policy facts.

### 27.2 Domain and lifecycle

- admissions → student registration → placement → academic eligibility → enrollment → class/session/attendance → assessment/grade → progression/graduation end-to-end;
- exact invalid transition matrix for every aggregate;
- terminal guards, hold/transfer/freeze/waitlist/capacity races;
- grade lock, correction, appeal, transcript/certificate immutability;
- workforce/leave/teaching assignment/payroll calculation/liability/payment/settlement;
- scholarship/sponsorship/restriction/eligible allocation/release/clawback;
- book, asset, supplier, loan, interest, and disposal paths where enabled.

### 27.3 Security and privacy

- login rate limit, session revocation, inactive employee, expiry/delegation, MFA policy;
- branch/campus/department/object IDOR matrix across API, web, console, jobs, exports, documents, search, reports, and workspace;
- multi-position capability composition and SoD attacks;
- direct SQL bypass and mass assignment attempts;
- document signed URL, retention, export approval, consent/revocation, redacted logging;
- workflow privilege escalation and stale workspace command reauthorization.

### 27.4 Finance and concurrency

- debit/credit balance and period close;
- payment/refund/allocation idempotency under replay and concurrent requests;
- budget movement and restricted fund overspend races;
- payroll double-pay/settlement races and correction/reversal;
- AP terms/returns, asset depreciation/disposal, loan principal/interest if enabled;
- trial balance, cash position, AR/AP/payroll/fund/asset subledger reconciliation;
- report/dashboard/workspace equality against source queries and projection rebuild.

### 27.5 Events and operations

- crash between source commit and relay; prove committed facts produce an outbox row and failed transaction produces none;
- relay retry, duplicate delivery, ordering, poison/dead-letter recovery, inbox deduplication;
- notification/read isolation, search projection rebuild, report projection rebuild;
- queue backlog, readiness dependency failure, backup age, restore drill, log/metric/trace correlation;
- external integration timeout/signature/replay/credential rotation behavior.

### 27.6 Frontend and usability

- React build/typecheck and API generated-contract alignment;
- route navigation, deep links, empty/loading/error/permission/freshness states;
- keyboard-only, focus visibility, screen reader semantics, contrast, accessible authentication, touch targets, RTL and Persian/Dari layout;
- mobile/reception/teacher/finance/HR/management/student workflows;
- employee-efficiency study: task completion time, error rate, context switching, unresolved exception rate, and user comprehension of scope/freshness;
- browser print/PDF and secure document access.

### 27.7 Independent review gates

Runtime validation must include independent security review, independent financial/control review, migration/recovery review, accessibility review, and representative employee/student acceptance. Production readiness is blocked until all critical and high findings have dispositions and evidence.

---

## 28. Final self-critique, risks, and growth assessment

### 28.1 Capabilities deliberately not carried forward

The final architecture does not preserve every convenience in A/B: single-file SQLite assumptions, direct route SQL, static role dashboards, generic residual income/expense categories, loosely modeled success stories, `saving_accounts` as an independent balance, legacy IDs/aliases, arbitrary automation actions, or broad UI claims unsupported by an owner contract. Some source features will look smaller initially because they are now constrained by Finance, Access, and lifecycle authority.

### 28.2 Ambiguous boundaries that still need disciplined implementation

- Funding versus Finance: Funding intent/award/restriction evidence versus Finance money must remain explicit.
- Resources versus Finance: physical stock/custody versus valuation/journal must not drift.
- Academic Planning versus Delivery versus Scheduling: catalog, operation, and physical reservation are distinct.
- Workflow versus domain approvals: generic work routing must not become approval truth.
- Reporting versus Management Workspace: a composed decision surface must retain metric/source/freshness identity.
- Person, Student, Applicant, Employee, and User Account: one person can participate in many contexts without one context owning all identity facts.

### 28.3 Underserved users and risks

Reception staff need fast low-error capture, cash receipt context, duplicate warnings, and daily work queues. Teachers need mobile attendance and grade entry without exposing finance/HR. Finance needs reconciled subledgers, not colorful totals. HR needs effective-dated employment/leave/settlement context. Donor managers need restriction evidence and impact traceability. Students need accessible self-service and clear holds without revealing internal notes. Management needs exception-oriented decisions, not merely KPIs. The blueprint addresses these, but only runtime usability work can prove them.

### 28.4 Accounting, audit, security, and database risks

The strongest remaining risks are policy incompleteness, migration mapping errors, unbalanced legacy finance, reporting projection drift, row-lock contention, object-store authorization, outbox poison messages, overbroad organization-scope grants, and developer pressure to use a residual category when a fact lacks a policy. The final architecture reduces these risks with explicit gates but does not remove the need for review and operations.

### 28.5 5× and 10× growth

At 5×, a modular PostgreSQL monolith with indexed branch/period/source queries, queue projections, and a React client is appropriate. At 10×, audit/outbox/message tables and report workloads may require partitioning, read replicas, a dedicated search engine, a reporting warehouse, queue sharding, and extraction of the heaviest bounded context. Those are deployment evolutions, not reasons to pre-split ownership into microservices now. The event envelope and API boundaries preserve that option.

### 28.6 Overengineering check

The design is intentionally more rigorous than A/B in Finance, Access, outbox, migration, and Workspace because those are high-consequence facts. It avoids premature microservices, arbitrary workflow languages, broad LMS/transport/hostel scope, and multi-currency complexity. A feature is postponed when its business policy, owner, or operational value is not proven. The main overengineering risk is a too-large initial academic/report catalog; wave sequencing and feature flags must keep the first release operationally focused.

### 28.7 Decisions postponed, not hidden

FX, tax/withholding rates, depreciation/impairment, loan policy, donor gift acceptance, cash counting, write-off thresholds, supplier terms, legal retention, and advanced external integrations remain explicit policy gates. The architecture does not pretend they are resolved.

---

## 29. Exact final architecture verdict

The three inspected systems are evidence, not authorities. System A supplies breadth and several strong operational/financial patterns. System B is an older packaged sibling of A and is useful for lineage and product intent, not for independent schema preservation. System C supplies the strongest starting point for PostgreSQL, modular commands, authority boundaries, lifecycle controls, privacy, audit, and corrections, but it is incomplete and not conforming to the full target.

TOEFL House should therefore build one React/TypeScript frontend over one Laravel modular monolith backed by PostgreSQL, with explicit bounded contexts, one authority per fact, Finance as the sole monetary owner, Academic as the owner of learning delivery, Payroll as calculator/proposer rather than settlement authority, Funding as restricted-intent/award/impact owner without a second ledger, Access as command-time authorization, Work Management and Workspace as non-authoritative orchestration, Reporting/Search/Notifications as rebuildable projections, Documents as secured evidence, and a transactional outbox for reliable integration.

This blueprint is the final target. It is not a production-readiness declaration. Production readiness begins only after the runtime-only validation plan, migration/recovery evidence, independent review, and policy gates pass.

**THIS IS THE FINAL ARCHITECTURE TOEFL HOUSE SHOULD BUILD.**

---

## HISTORICAL SOURCE: `architecture/review/2026-09-05-fourth-architecture-convergence.md`

# Fourth Architecture Convergence Review

**Date:** 2026-09-05 (Asia/Kabul)  
**Repository:** `alfrotan-glitch/TOEFL-House`  
**Branch:** `arena/01a07134-toefl-house`  
**Status:** Static convergence implementation and architecture review; **not production readiness**  
**Runtime posture:** Deliberately deferred. No PHP, Composer, PostgreSQL, migrations, PHPUnit, PHPStan, Pint, Node install/build, browser, queue, or integration execution was performed.

## 1. Executive decision

The fourth architecture is a **Laravel modular monolith with a single React/TypeScript operator boundary**, backed by the current PostgreSQL-oriented relational model and governed by canonical domain commands, lifecycle rules, authorization decisions, provenance, audit, and Finance-owned monetary facts.

System A is retained as capability evidence: its broader ERP/product surface, placement and academic breadth, financial subledger ideas, invariant/readiness discipline, reporting posture, and mature React experience. Its SQLite persistence and route-level implementation are not retained as the target.

System B is retained as the implementation foundation: modular Laravel boundaries, relational constraints, command/lifecycle patterns, Access decisions, scope/provenance, idempotency, audit, Finance corrections, Payroll separation, and the existing source-linked model. System B's missing product/workspace/search capabilities are implemented inside the final boundaries, not copied from System A as a second backend.

The current third system, the present checkout, is treated as **System C** for this review. System C is materially stronger than a blank scaffold, and this convergence adds a real Employee/Management Workspace projection surface, branch-safe Search, one standalone React/TypeScript console root, an allowlisted relay/consumer chain with envelope context, Work Management coordination and queue membership, and recipient/read Notifications. The CRM and Students/Admissions Blade interactive surfaces have now crossed to the shared React boundary and use only versioned APIs; remaining Blade module screens are still migration work, and runtime behavior remains unverified.

The convergence decision is therefore:

1. Keep one owner and one write path for each major fact.
2. Keep Finance as the sole monetary authority. Payroll calculates/proposes; Finance recognizes liability and records settlement.
3. Treat Employee Workspace and Management Workspace as read/orchestration projections. They do not own tasks, approvals, notifications, money, permissions, reporting arithmetic, or lifecycle state.
4. Use effective-dated Access and branch provenance for every sensitive read. Unknown or empty scope fails closed.
5. Use `/api/v1` as the only intended interactive JSON contract. No permanent unversioned compatibility duplicate is introduced.
6. Use one React/TypeScript boundary for the new operator console. The remaining Blade screens are a migration boundary to be retired, not a second permanent product architecture.
7. Preserve the transactional outbox relay, idempotent consumers, and durable notification/read-state projection; complete runtime verification before any readiness claim.

## 2. Evidence and reverse-engineering method

The review combined direct static inspection of the repository with the existing comparison artifacts. The inspection covered:

- System A capability and implementation evidence recorded in `docs/foundation/01-legacy-system-intelligence-report.md` and the unified reconciliation;
- System B/C routes, controllers, commands, queries, models, migrations, seed/reference data, permission/access classes, lifecycle and approval paths, Finance, Payroll, Academic, Admissions, Students, CRM, Placement, Documents, Communication, Reporting, Integrations, Outbox, audit, and configuration;
- the prior integrated graph and repository conformance audit;
- the current API and web transport surfaces;
- the current frontend inventory, which initially had Blade views only and no `package.json`, Vite entry, React tree, or TypeScript source tree;
- migration evidence through the 2026-09-05 production authority, provenance, settlement, class branch, domain-event, and Finance payroll-liability changes.

The existing comparison establishes that System A supplied a broad operational product surface while System B supplied the stronger backend authority and relational discipline. It also records the explicit rejection of preserving either source unchanged. This review verifies those choices against actual current files and records the implementation changes made in this static phase.

Static inspection does not establish that a class autoloads, a migration applies, a query works against the live database, a route returns a valid response, a TypeScript bundle builds, or a consumer is safe under retry. Those are recorded as runtime-only risks below.

## 3. Master capability inventory and disposition

| Capability | System A | System B | System C actual | A/B/C classification | Fourth-architecture disposition |
|---|---|---|---|---|---|
| Organization, campuses, branches, departments | Broad, branch-aware | Relational Organization module and scope topology | Present in models, migrations, web/API paths, `ActorBranches` | **shared** | **Required now; preserve C authority; fail closed on missing branch** |
| Identity and authentication | Sessions, revocation/quarantine, scoped roles/delegation | Access, Identity, effective assignments/grants/delegations | Present in `EnsureEmployeeSession`, Access decisions, audit | **shared** | **Required now; one Access authority** |
| Admissions and student lifecycle | Applicant intake, conversion, admission, student portal | Admissions/Students commands and history | Present; admission staging, student provenance/branch links, versioned lifecycle API, React directory/admissions/detail surfaces | **shared** | **Required now; preserve separate Admissions → Students → Academic edges** |
| Visitor/CRM | Lead capture, pipeline, communication, conversion | Originally incomplete | Visitor, follow-up, provenance, CRM commands, versioned API, React CRM workspace | **A + C; B incomplete** | **Required now; CRM owns acquisition/evidence, React consumes authorized CRM facts** |
| Placement | Content banks, attempts, scoring, recommendations | Placement module added | Present with evidence, eligibility snapshot, appeals | **shared; B/C added** | **Required now; preserve evidence lineage; no UI-only placement state** |
| Academic setup and delivery | Programs, levels, classes, schedules, sessions, enrollment, attendance, assessment, progression | Strong domain commands and constraints | Present, but Scheduling/Enrollment boundaries remain partly embedded in Academic | **shared; boundary incomplete/conflicting** | **Required now; stabilize target boundaries before migration consolidation** |
| Finance/subledger | Broad obligations, payment, allocation, funds, books, assets, budgets, sponsorship | Stronger authority/correction model | Finance owns obligation, payment, allocations, journals, funds, corrections, payroll liability | **shared; authority conflict resolved toward B/C** | **Required now; Finance sole monetary authority** |
| Donor/funding/sponsorship | Restricted funds, donations, scholarships, sponsorship, impact/clawback evidence | Funding model and enrollment gate concepts | `FundingSource`, `FundAllocation`, financial gate/provenance path | **A-led breadth; B/C partial** | **Architecturally required; implement policy-complete restricted-resource workflow later where absent** |
| HR and Payroll | Broad HR/payroll | Effective employment, payroll calculation/result/settlement separation | Present; held calculations and Finance-owned liability/settlement | **shared** | **Required now; preserve non-duplication** |
| Workflow/task/work item | Explicit product concepts in target evidence | Work Management coordination slice now exists; domain-specific staged decisions remain sources | Work Management instances/items/history plus explicit queue claim membership | **shared; C/B coordination added** | **Required now as coordination only; domain commands remain business authorities** |
| Documents/privacy/retention | Broad document, verification, retention | Dedicated Documents module | Present and source-linked, but not fully event-fed | **shared; incomplete delivery** | **Required cross-cutting; complete missing delivery evidence** |
| Communication | Messages and delivery | Message model/command plus explicit Notification projection | Present; channel/device policy and broad recipient rules remain incomplete | **shared; Notification projection C-added** | **Required now; keep messages and notifications distinct** |
| Reporting/analytics | Mature reporting/readiness claims | Metric catalog/calculators/projections/reconciliation/report runs | Present as governed read model, but no demonstrated event-fed rebuild chain | **shared; incomplete rebuild wiring** | **Required; no controller/frontend arithmetic; add replayable projection consumers** |
| Search | Search capability | None | New `SearchQuery`/API, branch-safe student/visitor search | **A evidence + C implementation; B absent** | **Required now as governed projection boundary; index optimization deferred** |
| Employee Workspace | Mature React experience/work context evidence | None | New Workspace queries/API and React console | **A evidence + C implementation; B absent** | **Required now; projection only, not authority** |
| Management Workspace | Dashboards/decision support | Reporting only | New Management query/API over reporting/health sources | **shared evidence; C implementation** | **Required now; scope and source-link every value** |
| Events/outbox/integration | Event/automation evidence | Domain event recorder and endpoint delivery | `DomainEvent`, allowlisted relay, consumer receipts, projection invalidations, and `IntegrationDelivery` endpoint delivery | **shared; relay/consumer wiring incomplete** | **Required now; complete external subscriptions and materialized projectors later** |
| Audit/forensics | Broad audit/invariant discipline | Append-only audit/attempted-operation evidence | Present and distinct from domain events | **shared** | **Required now; keep audit separate from business event stream** |
| Frontend | React product surface | No React tree | Single React/TS boundary now hosts Workspace and CRM; other Blade screens remain migration work | **A evidence + C implementation; B absent; C transitional duplication outside CRM** | **React is final boundary; retire remaining Blade interactivity incrementally** |
| Health/readiness/backup | Explicit capability | Health/config/deploy artifacts | Present in web/deploy artifacts | **shared; runtime unverified** | **Required operationally; validate only in runtime phase** |

### Classification rule

A capability is not selected merely because System A has it. It is selected when it is necessary to operate a modern education ERP/EdTech platform, preserve a strong existing authority, or satisfy the graph's cross-cutting safety law. Optional or policy-dependent capabilities remain explicitly conditional rather than becoming unjustified tables and workflows. The inventory found no B-only capability that should displace the converged boundary: B contributes the strongest authority/lifecycle foundation, while A contributes breadth/evidence and C supplies the current implementation surface. Rows marked incomplete, conflicting, or transitional are explicit gaps, not claims of parity.

## 4. Authority convergence matrix

| Major fact | One owner | Write/lifecycle authority | Authorization path | Canonical representation | Current evidence and decision |
|---|---|---|---|---|---|
| Human identity | Identity | Identity commands; verification history | Access decision plus identity lifecycle | `people` / `Person` | Preserve. No search-wide Person directory for branch actors. |
| Authentication account | Identity/Access | User-account commands and session middleware | `EnsureEmployeeSession` | `user_accounts` / `UserAccount` | Preserve. Session actor is bound to Person. |
| Position assignment | Access/Organization | Position-assignment command | `AccessDecision`, effective dates | `position_assignments` / `PositionAssignment` | Workspace reads active assignments only. |
| Branch visibility | Access/Organization topology | Scope grant/delegation/organization commands | `ActorBranches`, Access resolution | Scope grants + campus/branch topology | Empty/unknown branch scope is not wildcard access. |
| Applicant/admission decision | Admissions | Admission decision staged reviewer/approver commands | command-time Access decision and actor assignment | `admission_decisions` / `AdmissionDecision` | Workspace shows only actor-assigned reviewer/approver work. Admissions have no direct branch field, so no global admission queue is exposed. |
| Student profile/status | Student Lifecycle | Admissions conversion plus Students status/history commands | branch scope and lifecycle policy | `students`, status/history, branch provenance | Preserve current/home/origin branch provenance. |
| Visitor/lead lifecycle | CRM | Capture/link/transition visitor commands | `AccessDecision` on immutable origin branch; null provenance fails closed | `visitors` / `Visitor` | CRM owns acquisition and follow-up only; verified identity and contact dedupe are explicit. |
| Visitor interaction evidence | CRM timeline; linked fact remains downstream-owned | CRM capture plus downstream trace adapters | CRM capability for manual facts; caller authority audit-event binding; person-lineage validator | append-only `visitor_interactions` / `VisitorInteraction` | One vocabulary and one reference-lineage validator; payment/academic/document/message/placement facts remain with their owners. Direct SQL cannot claim downstream origin without the matching immutable authority event. |
| Visitor conversion evidence | CRM trace; Admissions/Students own conversion | Admissions/Students workflow plus `VisitorConversionRecorder` | authoritative downstream audit-event binding, verified person, active lifecycle, branch/provenance match | `visitor_conversions` | CRM conversion UI/API removed; no enquiry or arbitrary downstream-ID assertion. |
| Enrollment/membership | Academic Enrollment | `MaintainEnrollment` lifecycle command; `EnrollmentConstraints` is the reusable planning boundary | command-time Academic capability, branch provenance, prerequisites, and Finance-owned gate | `enrollments` | Requested, active and frozen are live seat claims; transfer creates a new requested row; no duplicate membership table. |
| Offering / class packaging | Academic Structure / Delivery | `ManageAcademicOffering` for offering lifecycle; `MaintainClass` for class lifecycle | branch capability plus matching period/level/branch provenance | `offerings`, `classes.offering_id` | Every new class is anchored to one open offering; offering/class identity is immutable and DB-guarded. |
| Class/session delivery | Academic Delivery with Scheduling validation boundary | `MaintainClass` sole writer for class, section, teacher and session facts; `SchedulingConstraints` validates planning | branch/class/teacher/room/section ownership and temporal conflict constraints | `classes`, `class_sessions`, `class_sections`, `teacher_assignments` | Preserve one writer; the React/API workspace is a projection and never schedules by client state. |
| Placement evidence/recommendation | Placement | Placement commands | student/branch and lifecycle authorization | attempts, answers, results, recommendation/evidence snapshots | Preserve immutable evidence and signed consumption. |
| Employment/contract | HR | HR employment and contract lifecycle commands | Access and HR capability | `employments`, contracts, statuses | Preserve. |
| Payroll calculation/result | Payroll | Payroll calculation/approval commands | Payroll capability and SoD | `payroll_calculations`, `payroll_results` | Held calculations are exceptions, not Finance facts. |
| Monetary obligations/payments/journals | Finance | Finance commands and DB guards | Finance capability, period, SoD, scope | Finance obligation/payment/allocation/journal tables | Finance remains sole monetary authority. |
| Payroll liability/recorded settlement | Finance | Finance recognition/settlement commands; Payroll only proposes | Finance capability and evidence guards | `payroll_liability_facts`, Finance employment settlements | Preserve current production-authority correction. |
| Funding/restriction allocation | Finance/Funding under Finance monetary authority | Fund/obligation/allocation commands | source restriction, scope, period, approval | funding sources, funds, allocations and Finance ledger evidence | No donor/sponsor shortcut may bypass Finance or enrollment gates. |
| Approval facts | Owning domain | Domain-specific staged commands | assigned reviewer/approver plus SoD | each domain's staged decision table | Do not collapse all approvals into a generic Workspace task table. |
| Work item / workflow coordination | Work Management | Work Management commands for instance/item assignment and lifecycle | Access + branch scope + explicit queue membership; linked source command reauthorization | `workflow_instances`, `work_items`, append-only `work_item_history`, `work_queue_memberships` | Coordination only; no domain approval, exception amount, permission, or notification truth. |
| Notification/read state | Communication/Notifications | Notification projection consumer plus recipient read-state command | explicit recipient self-scope; future channel/device policy | `notifications` plus event-consumer receipts | Actor-specific projection; never a task, approval, message, or source-domain authority. |
| Reporting metric | Reporting projection; source owner remains domain-specific | Reporting compute/reconcile commands | reporting capability and scope | MetricCatalog, projections, reconciliations, runs | Reporting never writes Finance/Academic facts. |
| Search result | Search read projection | Future event-fed index/projector | source branch scope and record policy | current query response; future governed index | Branch-safe query now; durable index/rebuild later. |
| Domain event | Outbox | Owning transaction through `TransactionalEventRecorder` | server-side command context | append-only `domain_events` | Distinct from Audit and IntegrationDelivery; relay receipts and invalidations are downstream state. |
| Endpoint delivery | Integrations | delivery processor | endpoint credential/policy | `integration_deliveries` | Never use this as the domain event log. |

## 5. Major capability convergence: TARGET → ACTUAL → GAP → ROOT CAUSE → DECISION → IMPLEMENTATION

### 5.1 Backend style, transport, and frontend

**TARGET** — One Laravel modular monolith, one coherent React/TypeScript operator surface, server-authoritative commands/queries, no parallel A/B/C route or UI authority.

**ACTUAL** — Current C is Laravel modular monolith code with extensive Blade routes/views. Before this phase there was no `package.json`, Vite entry, React feature tree, or TypeScript source. The API surface was unversioned under the framework's `/api` prefix, and the root Blade Home page rendered live navigation counts rather than a workspace.

**GAP** — The new React boundary is now present and CRM plus Students/Admissions have crossed over to it, but the remaining module-specific Blade screens are still a migration boundary and have not been converted or retired. No runtime build verification has been performed.

**ROOT CAUSE** — System B/C was built as a server-rendered console before the final reconciliation selected React/TypeScript; the API and frontend contract were not converged together.

**DECISION** — React/TypeScript is the sole new interactive architecture. Do not build additional Blade features or a second frontend. Existing Blade routes are legacy migration evidence, not a permanent A/B/C product choice.

**IMPLEMENTATION** — Added `package.json`, `vite.config.ts`, `tsconfig.json`, `resources/js/app.tsx`, `resources/js/app.css`, and `resources/views/workspace.blade.php`. The authenticated root now redirects to `/workspace`, which mounts one standalone React boundary without the legacy Blade layout shell. The app consumes `/api/v1/me`, `/api/v1/workspace`, `/api/v1/management`, and `/api/v1/search`; it renders work-first cards, source IDs, canonical command links, scope indicators, recipient notification/read controls, coordination-only work transitions, and accessible loading/error/search states. `/crm` now mounts the same React boundary with an authorized visitor directory, canonical source/campaign selection, capture, stage transition, interaction evidence, follow-up scheduling, and timeline lifecycle controls over `/api/v1/crm`; its former interactive Blade controller/view and duplicate web writes were removed. `/students` and `/students/applicants` now mount the same boundary with authorized student directory, applicant registration, staged admission decision controls, and enrollment actions. `/students/{studentId}` now mounts the lifecycle detail view with status, branch, hold, communication, and guardian controls backed by `/api/v1/students`; the legacy POST adapters remain only as a temporary compatibility bridge. Vite binds to `0.0.0.0`; no browser code uses localhost for API calls.

### 5.2 API contract and versioning

**TARGET** — One stable, versioned JSON contract for the operator/frontend boundary with thin controllers and canonical command/query delegation.

**ACTUAL** — `routes/api.php` had authenticated API routes and module controllers, but no workspace/search contract and no version prefix.

**GAP** — The new controllers and routes are static additions; route loading, serialization, authentication, and contract tests remain unverified.

**ROOT CAUSE** — API growth preceded a coherent frontend boundary and workspace design.

**DECISION** — `/api/v1` is the only interactive API contract. No permanent `/api/*` duplicate is added because compatibility duplication would create a second contract.

**IMPLEMENTATION** — The authenticated route group is now `Route::prefix('v1')->middleware('employee')`, and `/workspace`, `/management`, and `/search` are registered. `/me` is consumed by React and reports the middleware-bound actor.

### 5.3 Employee Workspace

**TARGET** — A dynamic, authorized, role-aware but not role-locked work environment composed from effective identity, assignments, approvals, exceptions, deadlines, context, and source-linked commands. It must never own a task, approval, permission, balance, or lifecycle fact.

**ACTUAL** — The former `HomeController`/`home.blade.php` was a live-count navigation page. It did not compose assigned work and was organization-scoped through `reporting.run`; convergence now supplies a Workspace module/query/API and standalone React contract.

**GAP** — Generalized escalation/SLA processing and complete source-specific action routing remain incomplete. The workspace now has durable Notification and Work Management coordination projections; governed workflow-intent events create the applicable work items, and explicit completion adapters now close admission, academic-appeal, and Finance-correction coordination items without mutating their source facts. Direct source-assignment composition still remains for records not represented by a workflow intent. Academic appeal field/schema alignment and all runtime query behavior must be verified later.

**ROOT CAUSE** — Before convergence, Workspace was treated as a dashboard/navigation problem; canonical domain assignments and staged decisions existed separately. Work Management and Notification boundaries were then added as non-authoritative projections.

**DECISION** — Keep direct canonical-source composition for records that have not yet been bridged, and add Work Management as the coordination authority for generic actionable instances. Work Management distinguishes task, approval, and exception; it never owns the domain decision. Completing a generic approval item does not approve the source record. Notifications are separate recipient/read projections, not work items.

**IMPLEMENTATION** — Added `EmployeeWorkspaceQuery`, `WorkspaceApiController`, and `WorkItemQuery`. Workspace composes:

- active effective-dated `PositionAssignment` and `Position`;
- active actor `Employment` IDs and held `PayrollCalculation` exceptions;
- actor-assigned open `VisitorFollowup`, filtered through visitor origin branches;
- explicitly reviewer/approver-linked proposed/reviewed `AdmissionDecision` fallback work where the source carries that assignment;
- actor-assigned unresolved `AcademicAppeal` review work;
- actor-assigned Work Management `task`, `approval`, or `exception` items with source/action metadata;
- `source_type`, `source_id`, status, due field, and route for every item;
- visible branch scope and recipient-scoped notification/read-state projection.

`StartWorkflow` and `MaintainWorkItem` create/transition coordination state only; owning commands must still perform the business action and server authorization. During relay lag, canonical source fallback rows remain available, but the workspace suppresses a fallback card whenever its source-linked Work Management item is present, preventing one fact from becoming two actionable cards. The manual `StartWorkflow` path is retained only as an explicit operator/recovery command for a governed source; it is not an alternative source lifecycle and has no public workflow-state authority. Event-projected intents are the default producer path. When a compatible manual instance already exists, the projection binds its `source_event_id` instead of creating another instance; incompatible scope or event ownership is rejected. This is a convergence bridge, not a permanent second workflow architecture, and can be removed after all supported producer paths are event-covered.

### 5.4 Work Management, tasks, approvals, and exceptions

**TARGET** — Work Management owns workflow coordination, assignments, SLAs, work-item lifecycle, and history. Task, approval, exception, and notification remain distinct concepts. Domain approvals and exceptions remain owned by their source modules.

**ACTUAL** — C had domain-specific staged approval tables and a facilities `WorkOrder`; convergence now adds generic source-linked workflow instances, work items, append-only history, explicit queue membership, and event-projected workflow intents for every current governed catalog definition. Workspace composes those coordination rows with source assignments; it never owns the source lifecycle.

**GAP** — Additional future workflow definitions, SLA timers, escalation, and runtime authorization/transition verification remain incomplete. Current admission, academic appeal, finance correction, and held Payroll producers are statically wired; completion adapters are explicit for all four current definitions. Source-version freshness and runtime consumer/replay verification remain deferred.

**ROOT CAUSE** — The repository had correctly avoided a shadow task table but had not implemented the target's separate Work Management boundary.

**DECISION** — Add Work Management as a coordination authority, not a business-state authority. `workflow_instances`, `work_items`, append-only `work_item_history`, and explicit time-bounded `work_queue_memberships` are source-linked/operational coordination records. A generic approval item can be completed as coordination only; the owning domain command must perform the actual approval and reauthorize it.

**IMPLEMENTATION** — Added `WorkflowCatalog`, `WorkQueueCatalog`, `WorkItemLifecycle`, `WorkflowInstance`, `WorkItem`, `WorkItemHistory`, `StartWorkflow`, `MaintainWorkItem`, `MaintainQueueMembership`, `WorkItemQuery`, `QueueMembershipQuery`, Work Management API routes, and migrations `2026_09_05_000148_create_work_management_coordination.php`, `2026_09_05_000151_create_work_queue_memberships.php`, and `2026_09_05_000152_add_work_organization_provenance.php`. Every workflow and work item now persists organization provenance; branchless items are organization-scoped rather than global, and unassigned items require active queue membership before claim or transition. Queue memberships now persist organization provenance as well: a null branch means one explicit organization scope, not a platform-wide wildcard, and discovery/claim matching requires the work item's organization or branch to agree; a queue lookup with neither branch nor organization provenance now returns no memberships rather than becoming a cross-organization wildcard.

### 5.5 Management Workspace and reporting

**TARGET** — Management decision support over governed metrics, reconciliation, operational health, capacity and exceptions. It must not calculate duplicate KPIs or become a dashboard-owned business state.

**ACTUAL** — Reporting has `MetricCatalog`, calculators, projections, reconciliations, `ReportRun`, and dashboards. Integration health is represented by endpoint-specific `IntegrationDelivery`. The convergence now has a read-only `/api/v1/management` composition endpoint and an explicit `/management` React workspace route over those source-owned projections.

**GAP** — The current query exposes metric ownership metadata, bounded operational counts, and report runs, but it does not yet expose every target capacity/risk/decision projection or an event-fed materialized metric cache. Reconciliation, integration-delivery, and domain-event health rows currently lack organization provenance, so they cannot safely be shown as organization-local health. The returned source compositions are deterministically recomputable from canonical authorities, so the cache is an acceleration/deferred-operational concern rather than a second authority.

**ROOT CAUSE** — Reporting was implemented as a module console and calculator set, without a decision-support orchestration surface and without a completed event-to-projection chain.

**DECISION** — Add a read-only Management Workspace now, keep metric arithmetic in Reporting calculators, and expose only organization- or branch-provenanced source composition. Do not present platform-wide reconciliation, integration-delivery, or domain-event counts as organization-local health until those tables gain provenance; return an explicit unavailable status instead. Branch actors receive only branch-provenanced counts; payroll/admission global counts remain zero in branch scope because the current records do not carry sufficient branch provenance. Treat this canonical source composition as the rebuildable baseline; a materialized cache is optional and cannot become a new fact owner.

**IMPLEMENTATION** — Added `ManagementWorkspaceQuery` and management API composition over `MetricCatalog`, `ReportRun`, `Student`, `ClassModel`, and Finance/Payroll/admission source tables. Added an explicit `/management` React workspace route using the same shell and versioned API, with scope, source-linked counts, operational health, and latest governed report runs. It is read-only and does not create a parallel dashboard authority. Organization scope requires the existing `reporting.run` Access decision and resolves active organization IDs directly; it now expands those authorized organizations through their active branch topology before computing branch-anchored counts, so an organization grant is not accidentally reduced to the actor's visible branch subset. Otherwise visible branch scope is required and empty scope returns 403. Work counts include only persisted organization/branch provenance, and branch report runs persist the same organization snapshot through migration `000153`. Reconciliation, integration-delivery, and domain-event health is returned as an explicit unavailable status because those operational tables are not organization-provenanced. Platform-global report runs remain explicit global evidence and are excluded from organization Management/Reporting views until an organization-specific scope is modeled. The frontend labels values as projections and does not recalculate them. Branch report execution, projection refresh, and metric reconciliation now reauthorize the concrete branch scope after deriving organization provenance; student- and class-scoped reporting resolves the owning record's current branch before authorization and refreshes the projection's organization provenance on rebuild, while institution-wide fund/global scopes remain explicitly organization-wide; workspace counts and branch-scoped placement metrics use current-home branch provenance first and only fall back to immutable origin when current home is absent, preventing a record transferred outside the authorized organization from being counted through its historical origin; reconciliation rejects stale or absent organization provenance for the current slice; an organization-wide reporting grant for one organization cannot be used to compute or expose a different organization's branch slice.

**STATIC MIGRATION REVIEW** — Migrations `000152`–`000155` now have explicit downgrade assumptions. Work-item organization backfill first derives provenance from its own branch, then from its workflow, so standalone branch-scoped work items are not rejected merely because they have no workflow instance. Downgrades refuse to discard non-representable workflow, report, dashboard, or metric organization/branch provenance and fail with a governed migration error before destructive schema changes. Dashboard migration `000154` also replaces the predecessor global dashboard-name index with an organization/name uniqueness boundary and restores it symmetrically on an empty rollback. When affected rows are absent, each down path restores the predecessor scope constraint, trigger/function contract, index, foreign key, and column shape; runtime application and rollback rehearsal remain deferred.

### 5.6 Search and provenance

**TARGET** — Authorized search/discovery over a rebuildable projection or source query, filtered by the same scope authority as writes. No unscoped global people directory.

**ACTUAL** — System A had search evidence. C now has a `SearchQuery`/API boundary but no materialized index; Students carry `current_home_branch_id`/`originating_branch_id`; Visitors carry immutable `origin_branch_id`; `Person` itself is not a safe branch search anchor.

**GAP** — Search is currently a bounded canonical source query rather than a materialized event-fed index. Ranking, pagination cursor, redaction policy, and an optional index rebuild command remain future work; event receipts and projection invalidations are now present. The source query itself is deterministic and rebuildable without creating a search authority.

**ROOT CAUSE** — Search was considered a UI convenience instead of a governed projection and there was no shared read-scope boundary.

**DECISION** — Fail closed on empty visible branch scope. Search only branch-provenanced Students and Visitors; do not query unscoped `Person` globally. The governed source query is the current rebuildable projection boundary; add an indexed acceleration only when its owner, event source, redaction, rebuild, and authorization contract are explicit.

**IMPLEMENTATION** — Added `SearchQuery` and `SearchApiController`. It obtains `ActorBranches::visibleBranchIds`, evaluates source-specific capabilities per concrete branch, returns no results for empty scope or terms shorter than two characters, filters Students by current home branch with originating-branch fallback and Visitors by origin branch, and returns source type, ID, provenance branch, status, and route. The React search uses the same-origin `/api/v1` contract.

### 5.7 Finance, funding, donor, and sponsorship

**TARGET** — Finance is the only monetary authority. Donor-imposed restrictions, funds, sponsorship/waiver/credit/installment decisions, allocations, obligations, settlement, and correction evidence must remain traceable and distinct from unrestricted resources.

**ACTUAL** — C contains Finance obligations, lines, payments, allocations, journals, periods, funds, corrections, reconciliations, financial gate exceptions, Finance-owned payroll liability facts, and Finance-owned employment settlement authority. Funding sources and fund allocations are present. Enrollment financial-gate migrations link eligibility to Finance-owned evidence. System A contributes broader donor/scholarship/sponsorship capability evidence.

**GAP** — Full policy-specific donor restriction satisfaction/release, sponsor contract/claim/clawback, bank reconciliation, procurement/AP, inventory, and asset depth are not all verified as complete current capabilities. The final blueprint prefers currency-aware integer minor units, while the current pre-production schema and certified balance queries use two-decimal `decimal(14,2)` strings; a schema-wide currency migration would be premature before currency/FX policy is accepted. A workspace must not infer a waiver or sponsorship from a label. Migration execution, trigger ordering, existing-data compatibility, concurrency, and correction/reporting reconciliation remain runtime-only gaps.

**ROOT CAUSE** — System A had broad economic surface while C prioritized core financial authority and gate correctness. The late authority correction introduced layered migration guards and a three-part settlement handoff that required static consolidation before runtime verification.

**DECISION** — Preserve the current Finance boundary. Treat donor/sponsor restrictions as Finance-governed restricted resources and evidence, not as admissions or academic monetary shortcuts. Retain the current two-decimal fixed-point storage until a currency/FX policy and schema-consolidation baseline authorize a currency-aware minor-unit migration; `MoneyAmount` prevents representation drift in the interim. Mark policy-dependent depth as future rather than adding speculative parallel tables. One active guard owns each staged Finance lifecycle: the hardened correction, discount, credit, installment, gate-exception, and refund policies replace their first-pass immutability/balance triggers; rollback-only migrations may restore the prior trigger set.

**IMPLEMENTATION** — `FinancialBalanceQuery` is the single derived-balance read authority; its payment remainder subtracts recorded refunds as well as net allocation reversals, matching the hardened database settlement guard. `MaintainFinancialCorrection` records source-linked proposed → recorded compensating facts; `MaintainEmploymentSettlement` inserts the Finance fact before calling Payroll's proposal-approval port; `RecognizePayrollLiability` recognizes only exact approved Payroll sources and now requires the immutable originating-branch snapshot captured on the approved Payroll result. Migrations `000141`, `000142`, `000143`, and `000146` enforce the same ownership and source-provenance contracts at the database boundary; direct allocation, discount, refund, and fund-allocation writes are rejected when their linked branch evidence is missing or inactive. Negative Payroll reversal amounts use a dedicated signed-money transport rule; ordinary Finance money remains non-negative. `MoneyAmount` is now the shared fixed-point boundary for Finance command validation and the HTTP `money`/`signed_money` rules; Finance no longer uses binary floating-point comparisons for decimal money acceptance, and reconciliation variance checks use `bccomp` at the stored two-decimal scale. `FinancialCoverageLock` now serializes student-level uncovered-balance derivation across obligations, allocations, discounts, corrections, credits, installment plans, gate exceptions, and enrollment activation, preventing independent approved coverage facts from racing past one another in the application transaction boundary. Coverage-affecting paths acquire the student row before their command/source rows where possible, reducing lock-order deadlock risk; the lock is a transaction-local database boundary, not an authorization substitute. ADR-023 records this as a serialization boundary rather than a new balance authority. Workspace and Management read Finance exceptions only through existing Finance-owned sources; no new money is created.

### 5.8 Academic setup, placement, admissions, and delivery

**TARGET** — Admissions owns admission workflow; Students owns student identity/lifecycle; Placement owns assessment evidence/recommendation; Academic/Enrollment owns delivery membership; Scheduling owns planning constraints once separated; Academic owns delivery facts; Finance owns eligibility gates.

**ACTUAL** — C has programs, versions, levels, periods, classes, sections, rooms, offerings, sessions, teacher assignments, enrollments, attendance, assessment, progression, graduation, transcripts, placement profiles/attempts/results/recommendations, eligibility snapshots, appeals, and branch provenance. Current static audits show that Scheduling and Enrollment boundaries are partly embedded in Academic commands; Scheduling planning and Enrollment constraints are now extracted without moving delivery/membership writes prematurely. Admission decisions do not have a direct branch field.

**GAP** — Runtime proof of the final guards, migrations, capability scope and React/API contracts remains intentionally deferred. A future Scheduling boundary may still add persistent reservation/projection facts, but it must not introduce a second `ClassSession` writer. Placement and Enrollment remain module boundaries rather than duplicate tables; the current static target is one Academic enrollment row and one Academic delivery row per fact.

**ROOT CAUSE** — C expanded capability through a modular Academic surface before all target graph boundaries were split; migrations were extended incrementally and the former Blade console exposed state without a coherent API projection.

**DECISION** — Keep one current writer and one canonical table for each fact. `Offering` is the packaging authority and every newly-created delivery class is anchored to one open offering through immutable `classes.offering_id`; `ClassModel` remains the class lifecycle authority; `MaintainEnrollment` remains the enrollment writer while `EnrollmentConstraints` owns reusable seat/prerequisite checks; `SchedulingConstraints` validates delivery planning while `MaintainClass` remains the sole `ClassSession` writer. Requested, active and frozen enrollment rows all claim capacity; waitlist rows never do. Finance remains the sole monetary authority for activation gates. Do not create duplicate `class_memberships`, `schedule`, roster, seat, or admission-queue authorities.

**IMPLEMENTATION** — Added `AcademicPeriodLifecycle`, coordinated period closure across terminal classes, offerings and branch availability, anchored `MaintainClass::defineClass` to a matching open offering, re-read and row-locked the class before scheduling, and require session dates inside the published period. Enrollment request, activation, offering resize, waitlist promotion and read queries now use the same live-seat policy. Migration `2026_09_06_000160_converge_academic_class_authority.php` adds offering provenance, class/period/offering identity and lifecycle guards, database capacity guards with deterministic class→offering lock order, terminal-state safety, teacher-window overlap checks, assessment-attempt/result, attendance, session-scope and progression lifecycle guards, waitlist topology guards, corrected offering closure ordering, and the final live-seat invariant. The new versioned `/api/v1/academic/workspace` projection is capability- and branch-scoped and returns server-derived lifecycle, allowed transitions, provenance, seat counts, waitlist, teacher, section, room and timetable facts; attendance and outcome evidence are further projected only for their corresponding capability scopes. Attendance correction, assessment submission/scoring, result moderation/approval/release/appeal, correction approval, and progression proposal/review/approval now use versioned command endpoints; the React outcomes tab renders only branch-capability-permitted actions and does not expose appeal supersession without two distinct server actors. React `AcademicApp` is the sole `/academic` interactive workspace; mutations reload the projection and delegate to existing commands. Legacy POST routes remain compatibility transports only and do not own state.

**STATIC CORRECTIONS** — Offering and waitlist provenance requires active organization/campus/branch topology plus branch, period and level agreement in application constraints and PostgreSQL guards; class-linked academic audits now carry explicit `organization_id`, `campus_id`, and `branch_id`, and class memberships/waitlist rows reject branchless classes. Capacity is now one policy across command/query/DB layers: `requested`, `active`, and `frozen` are live claims, and `withdrawn`, `transferred`, and `completed` release capacity. Class lifecycle, section/session, offering/period, enrollment, and waitlist transitions are server/database guarded rather than inferred by React. Scheduling validates 24-hour values and period boundaries at the domain boundary; class lookup is re-read under lock before validation. The React workspace hides actions outside authorized branches and exposes Finance as a read-only gate dependency. Runtime migration/query/concurrency/browser/build verification remains unexecuted by instruction.

### 5.9 HR, Payroll, and settlement

**TARGET** — HR owns employment/contract/lifecycle facts; Payroll calculates and approves payroll results; Finance recognizes monetary liability and records settlement; corrections are append-only/compensating.

**ACTUAL** — C has `Employment`, effective statuses, contracts, compensation/rules, payroll periods/calculations/results/adjustments/clearances, Payroll settlement proposals, Finance payroll liability facts, and Finance employment settlements. `PayrollCalculation` held state is a source exception.

**GAP** — Runtime proof of guards, approvals, disbursement, source signatures, and settlement migration correctness is absent. Workspace composition uses the current employment IDs and held calculations; generic Work Management projection now emits a governed workflow intent for held calculations with active employee branch/organization provenance, while the calculation table itself remains a source fact without a duplicated workflow state. Payroll source adjustments can be signed reversals, so transport/API contract coverage for signed source amounts also needs runtime verification.

**ROOT CAUSE** — The target authority correction was implemented late through migrations 140–146, so static schema history is still settling. The settlement lifecycle crosses a Payroll proposal port and a Finance fact insert; the transaction ordering is statically coherent but not executed.

**DECISION** — Preserve the non-duplication boundary. Do not add Payroll monetary balance fields or workspace-owned exception records. Finance recognition requires approved source evidence, exact amount identity, an immutable active branch snapshot captured at Payroll approval, and a Finance capability decision at that branch scope. Payroll remains the source-evidence authority, not the liability authority.

**IMPLEMENTATION** — Employee Workspace queries active actor employments and held calculations; Management shows held payroll only at organization scope because held calculation rows still lack branch provenance. Payroll approval snapshots the active employee home branch into `PayrollResult.originating_branch_id`; the database guard binds that snapshot to the employee branch and active-branch registry. Finance `PayrollLiabilityFact` must match that snapshot, and legacy approved results without it fail closed for recognition until reconciled. The Payroll total calculator filters any non-global branch slice by the Finance snapshot rather than re-deriving current employee location. The database guard also rejects recognition by the Payroll approver. The settlement command creates `employment_settlements` first and invokes `SettlementProposalApproval` so Payroll closes only its own proposal state. A recalculation supersedes only a prior prepared calculation; held rows remain visible exceptions until `ResolveHeldPayrollCalculation` explicitly names a same-period/employment prepared or resulted replacement, evidence reference, and an authorized resolver distinct from the beneficiary and replacement preparer. Migration `000157` allows that replacement to coexist, adds immutable resolution provenance and a database guard, and the `payroll.held_exception` completion adapter closes only the rebuildable Work Management item after the Payroll source event. Static authorization convergence now resolves the person home branch before identity verification/intake, Documents registration/lifecycle/retention, Privacy consent/disclosure/export, Communication message operations, HR employment/contract/contract-version/leave decisions, and Payroll calculation, result approval/adjustment, clearance, settlement proposal, or held-resolution capability decisions; organization grants may cover the concrete branch, while missing or inactive person/employee provenance fails closed, and identity/Documents/Privacy/Communication/HR/Payroll/settlement audit events carry the resolved branch/organization pair.

### 5.10 Events, outbox, relay, and consumers

**TARGET** — Successful domain transaction and durable event are committed atomically; a relay publishes at least once; consumers are idempotent, ordered where required, replayable, and record receipts; endpoint delivery is downstream projection state.

**ACTUAL** — `DomainEvent`, `TransactionalEventRecorder`, `domain_events`, Audit evidence, `IntegrationDelivery`, a delivery processor, and the new allowlisted internal relay/consumer chain exist. `DomainEvent` is append-only; audit and event rows are recorded through the same owning command transaction. The prior conformance audit's missing-relay finding is retained as historical baseline, not current state. `JobCatalog` contains `outbox.relay` and `integrations.retry_sweep`, and `ProcessJobRun` provides a leased handler boundary.

**GAP** — The static relay now has an explicit `routes/console.php` command and Laravel scheduler registration for the allowlisted `outbox.relay` and `integrations.retry_sweep` jobs, but runtime scheduler supervision, queue/process policy, durable operator provisioning, and failure/replay verification remain unperformed. Workflow completion is deliberately source-event allowlisted and receipt-backed: a matching source event remains retryable when its intent projection has not arrived, rather than being permanently recorded as non-applicable; completed/cancelled/expired work is replay-safe. External event subscriptions, optional materialized Search/Workspace/Reporting accelerators, replay commands, and complete ordering policy also remain incomplete. Workspace/Search source-query projections and Reporting metric calculators are already deterministic rebuild baselines; materialization is not permitted to become a second authority. `IntegrationDelivery` is still explicitly endpoint-specific rather than an automatic domain-event fan-out.

**ROOT CAUSE** — Endpoint delivery was implemented independently of the domain-event recorder; audit was correctly kept separate but the connecting durable relay/consumer architecture was not completed.

**DECISION** — Do not repurpose `integration_deliveries` as domain truth. Use an explicit consumer catalog, per-event/per-consumer receipts, expiring claims, bounded retries, dead letters, and projection invalidation rows. Add external subscriptions and concrete projectors only with an explicit contract/action mapping; never infer endpoint fan-out from the existence of an endpoint. Before enabling runtime relay, add an explicit operator/scheduler entry point that supplies a durable authorized `run_by` identity; do not invent a fake system actor.

**IMPLEMENTATION** — Added `EventConsumer`, `EventConsumerCatalog`, `DomainEventRelay`, `DomainEventContext`, `ConsumerReceipt`, `ProjectionCatalog`, `ProjectionInvalidation`, `ProjectionInvalidationConsumer`, explicit `NotificationProjectionConsumer` and `WorkflowProjectionConsumer`, the `outbox.relay` JobCatalog entry, leased `ProcessJobRun`, the explicit `integrations:install-core-schedules` and `integrations:run` console entrypoints, bootstrap provisioning of the allowlisted durable job schedules, per-minute Laravel scheduler registrations, fail-closed `INTEGRATIONS_SCHEDULER_RUN_BY` configuration, and migrations `2026_09_05_000147_create_outbox_consumer_and_projection_state.php`, `2026_09_05_000149_add_domain_event_context.php`, and `2026_09_05_000150_create_notification_projection.php`. Every newly recorded event now carries explicit scope type/provenance in its envelope; legacy rows are marked unknown rather than treated as global, and unknown events cannot create notification/workflow projections. Notification projections persist `branch` versus `organization` scope separately from nullable branch IDs, so null is never a wildcard. `DomainEvent` remains the canonical transactional event log; receipts/invalidation/notification/work-item rows are rebuildable processing state; `IntegrationDelivery` remains endpoint-specific. Core schedule registration is statically provisioned by first-run bootstrap or the explicit installer command; deployment invocation, scheduler supervision, and runtime execution remain deliberately deferred and unverified.

**STATIC CORRECTIONS** — The relay eligibility query now retains an event while any allowlisted consumer is due (OR semantics), rather than incorrectly requiring every consumer to be non-terminal at once; future-backoff and active-lease rows no longer starve newer events. Receipt state constraints now also forbid a processing/succeeded row from carrying a retry timestamp, so an expired lease cannot be masked by stale backoff metadata. Receipt creation handles the PostgreSQL unique-key race between concurrent relays, and consumer claims remain lease-guarded. Workflow projection records the source event ID with a database uniqueness boundary so receipt loss or replay after workflow completion cannot create a duplicate instance; if an explicit manual coordination row already exists for the same compatible source, projection adopts it by binding that event ID rather than creating a parallel workflow. The outbox recorder hashes PostgreSQL's canonical `jsonb` payload representation, while an insert trigger binds that digest, the event actor, and the event envelope to the referenced audit event (with a person foreign key). Migration `000149` now gives `domain_events.context` a default containing the required `unknown` scope and `not_available` provenance, constrains the allowed scope/provenance shapes, and rejects a supplied branch/organization pair unless active campus topology proves they agree; the prior `{}` default/check contradiction is removed. A repository-wide static search found no direct application audit-row writes outside `AuditRecorder`; therefore successful command audits flow through `TransactionalEventRecorder` in the caller's transaction. Event provenance precedence was also tightened at source-linked finance boundaries: payment, obligation, allocation, refund, discount, credit, correction, journal, and employment-settlement audits now declare their resolved branch/organization where a single source scope exists; student home-branch transfers explicitly declare the receiving branch so the transfer event is not misclassified by its historical originating branch. Cross-branch financial allocations remain a deliberately non-single-branch relation and must not be interpreted as a branch-local event merely because one related source has a branch ID. `DomainEventContext` now preserves explicit null/empty audited provenance, blocks all workflow/notification intent fallback once any audited provenance field is declared, and requires a branch event to declare the matching organization pair; otherwise the event remains unknown or is rejected by the database envelope guard. Workflow projection also refuses to reconstruct a missing organization from a branch. Workflow and Notification consumers reject unknown scope, require explicit source identity to match the domain-event aggregate, and validate branch/organization agreement; explicit but malformed workflow/notification intents now remain applicable to the consumer so validation failures are retried and dead-lettered rather than recorded as successful non-applicability. Runtime replay and consumer-contract verification remain deferred. Endpoint delivery was also changed to claim and commit a five-minute lease before transport I/O, then finalize under a locked row; expired claims are retryable and the stable external idempotency key preserves at-least-once safety. Scheduled job execution received the same boundary: `JobRun` leases are committed before handlers run, so relay/retry handlers no longer hold a JobRun transaction across network or long-running work. Expired endpoint/job claims are retryable, while missing durable `run_by` identity is rejected rather than represented by a fake system actor. Retry/dead-letter progress is also auditable without treating a transport outcome as a domain fact. Work Management, queue membership, and Notification projection identities now also have database foreign-key boundaries for people and branches; Work Management also database-checks workflow/item and history/item provenance equality, source-term immutability, governed lifecycle transitions, and terminal completion evidence; projected branch provenance must name an active branch and agree with its stored active organization, while branchless workflow/notification intents require explicit active organization scope rather than treating unknown context as global; branch notifications also require an explicit organization pair. Migration `000152` persists organization provenance on workflows and work items, backfills only active-topology-proven rows, rejects legacy coordination rows without active organization/branch evidence, explicitly replaces the stable `000148` work-item trigger before installing the converged workflow/work-item provenance guards, and database-checks workflow/item organization equality, preventing orphaned or operationally invalid assignment/provenance rows. Report-run migration `000153` likewise suspends the pre-existing immutable trigger only for its one-time provenance backfill and restores it before completion. Workflow starts and event-projected intents now require any direct assignee to be an active employee holding `workflow.work` authority in the item branch/organization scope; queue-only intents remain claimable only through explicit queue membership. Queue discovery and revocation re-resolve stored branch/organization provenance against current active topology, and work-item discovery/transition plus notification projection/read-state paths reject branch rows whose stored organization no longer matches the current branch owner. Workflow source references are now closed over the catalog: direct starts and event-projected intents must use the definition's expected source type and an existing canonical source row before coordination is created. All current catalog definitions now have explicit producer coverage: admission review/approval, academic appeal filing, financial correction proposal, and held Payroll calculation events emit top-level audited branch/organization provenance plus explicit workflow intent. Added the explicit `WorkflowCompletionCatalog` and `WorkflowCompletionConsumer` adapter for admission review/approval, academic appeal resolution/rejection/closure, Finance correction recording, and Payroll held-calculation resolution; these consumers close only the rebuildable Work Management item after the owning source event commits, preserve source-event metadata in work-item history, and never mutate the source aggregate. The completion receipt remains retryable when the intent projection is not present yet, and a transaction-local database marker makes the otherwise-forbidden open-to-completed transition available only to this projection path. `ResolveHeldPayrollCalculation` now carries the explicit predecessor/replacement contract; recalculation alone never completes a held exception. New workflow definitions and source-version freshness adapters also remain deferred. Admission registration/decision/conversion (with the `000140` insert guard and immutable originating-branch trigger now covering new applicants), Academic enrollment/class/session/teacher/assessment/attendance/waitlist/progression/graduation/transcript/placement changes, Identity account/verification, Access position assignment, Documents registration/lifecycle/retention, Privacy consent/disclosure/export, Communication message operations, HR employment/contract/contract-version/leave changes, Finance gate-exception/installment-plan, Student status/hold/communication/transfer, and guardian verification/revocation audit events now carry the highest-confidence active branch and organization provenance; multi-branch Finance targets declare only a common organization, and cross-organization relations remain unknown rather than fabricating one branch. Source-version freshness still depends on explicit producer adapters and remains deferred; the owning command must reauthorize current source state before acting. Legacy Academic and Payroll console payloads were also tightened: branch-bearing Academic records now derive through authorized class/student branch sets, Payroll calculations, clearances, employments, and settlement proposals resolve through authorized employee home branches, immutable Payroll results use their Finance/Payroll branch snapshot, Finance settlement proposal transport now resolves through authorized employee home branches before invoking Finance, and Access administration lists are restricted to authorized organizations, positions, scopes, people, and related delegations. Audit evidence is now fail-closed on recorded organization/branch provenance (including organization/branch targets), rather than exposing the entire append-only event table to any organization-level governance reader. The legacy Resources console is likewise fail-closed for inventory, custody, circulation, disposal, and facilities reads unless immutable asset, book-copy, or work-order root provenance matches the active branch topology and the actor holds the owning capability; it no longer infers scope from a visible person association or treats branchless resource rows as organization-global. Printing now constrains student payloads to the same immutable/source branch that authorized the payment, obligation, certificate, or enrollment print. The hardened allocation guard is now actually installed on payment- and fund-allocation inserts, refunds replace the predecessor immutable trigger with their governed proposed-to-recorded guard, and every insert-side balance check includes NEW.amount (not only rows already visible before the trigger). It also rejects direct-SQL payment/obligation student mismatches, while retaining the command's intentional ability to allocate across distinct authorized branch scopes. These strengthen structural forgery resistance, replay idempotency, stale-topology safety, and transaction hygiene; they do not replace authorization of the source command.

### 5.11 Documents, communication, notifications, audit, and privacy

**TARGET** — Documents own versioned evidence/rendered artifacts and retention; Communication owns message intent/delivery; Notification is distinct from task/approval/exception; Audit is immutable forensic evidence and not a business event stream.

**ACTUAL** — C has Documents, versions, verification, retention, Communication messages, audit events, attempted-operation evidence, privacy views, and now an explicit recipient/read Notification projection.

**GAP** — Device/channel preference policy, notification expiry tooling, and broad event-to-notification policies remain incomplete. Existing domain events do not automatically create notifications unless they carry an explicit recipient intent; this avoids guessing recipients from the event actor.

**ROOT CAUSE** — Communication messages existed, but the target graph deliberately distinguishes message delivery from employee notifications and work state.

**DECISION** — Notifications now have a dedicated recipient/read projection. Message delivery remains Communication; work items remain Work Management; source domain state remains with its owner. Notification consumers require explicit recipient/title intent and are idempotent. No global unread feed is exposed.

**IMPLEMENTATION** — Added `Notification`, `NotificationProjectionConsumer`, `NotificationQuery`, `MaintainNotification`, `NotificationApiController`, migration `2026_09_05_000150_create_notification_projection.php`, the consumer catalog entry, and ADR `2026-09-05-notification-projection-boundary.md`. Employee Workspace returns actor-scoped unread/read notifications; React provides mark-read/dismiss controls. Notification transitions cannot acknowledge the linked domain action.

**STATIC CORRECTIONS** — Notification intents now require a verified person with an active account, a non-empty source link, governed severity, and branch/organization provenance consistent with the event envelope. The projection validates these conditions again during consumption, rejects a branch and organization pair whose active campus topology disagrees, and never silently downgrades malformed intent data to a default notification. Organization notification reads resolve each active organization through `StructureScope` and `AccessResolution` directly, so a valid organization grant still exposes branchless organization notifications when the organization currently has no active campus/branch assignment; branch rows require explicit stored organization provenance, must match the branch's current organization, and remain limited to authorized visible branches. Notification projection schema and consumption now fail closed when a branch intent lacks that organization pair. The person-linked command path now centralizes active home-branch/organization resolution in `PersonBranchScope`; Identity intake requires and persists an active home branch, Identity account/verification changes, Access position assignment, Documents registration/lifecycle/retention, Privacy consent/disclosure/export, and Communication message actions authorize against that concrete scope, and their audit payloads retain the resolved branch/organization pair. Identity, Documents, Privacy, Communication, Students, HR, Access, Academic, and Library console directory/projection queries now filter Person-linked rows by the actor's visible/capability-authorized active branches rather than exposing a global Person list; Library borrower issue/return/loss commands now resolve the borrower scope before authorization while legacy branchless asset/catalog rows remain explicitly unknown until reconciled; the Resources index now reads only immutable root provenance that matches current active topology, and asset/work-order creation plus book-copy creation require explicit branch scope. The legacy Library controller now supplies the branch-selected book-copy creation form and route to the command as a migration bridge; the intended new interactive contract remains the versioned API/React workspace. This is a static fail-closed correction; existing rows without branch provenance remain a migration/runtime reconciliation concern and no runtime compatibility has been asserted.

### 5.12 Visitor / Lead / CRM

**TARGET** — CRM owns visitor acquisition metadata, visitor lifecycle, follow-up coordination, and an immutable interaction timeline. Identity, Admissions, Students, Communication, Documents, Academic Placement, and Finance remain the owners of their facts. CRM may record a conversion or cross-module trace only when the authoritative downstream workflow has already created the linked fact; it must not manufacture an Applicant, Student, Message, Document, Assessment, Placement, or payment.

**ACTUAL** — C has `Visitor`, source/campaign catalogs, follow-ups, immutable `VisitorInteraction`, automation rules, conversion traces, API/console transport, and downstream trace hooks from Admissions, Finance, Communication, Documents, Academic Assessment, and Placement. Existing reads used authorized branch IDs and excluded null provenance in the query path, but organization-root grants could produce an empty branch set. Manual CRM conversion routes also asserted arbitrary enquiry/applicant/student IDs, interaction reference checks proved existence but not person lineage, and automation wrote follow-ups directly rather than using the manual validation path. The interaction vocabulary was duplicated across commands, downstream tracing, migration checks, and UI; Placement added its relation in a later migration.

**GAP** — Organization-wide and explicit-branch reads were not consistently projected from the canonical Access decision; null-origin visitors needed an explicit fail-closed contract. Supplied person IDs, verified identity semantics for linking, follow-up assignee capability, cross-module interaction ownership, automation scope, conversion authority, downstream lifecycle, and branch/person lineage were under-validated. The early CRM schema/type list did not itself include the later placement type/relation, and old manual conversion expectations still included an unsupported enquiry ID.

**ROOT CAUSE** — CRM had been implemented as a capable module boundary, but several integration edges were treated as foreign-key existence checks or convenience UI flows rather than as cross-authority contracts. The initial branch-list helper treated navigation-visible branches as a complete authorization projection, and the conversion recorder was reusable without an explicit authority identity.

**DECISION** — Keep CRM as the acquisition/follow-up/timeline owner only. Resolve every branch read through concrete Access decisions over active temporal topology; organization grants expand to active, effectively assigned branches, while null provenance remains withheld by default and exposed only through an explicit organization-scoped unassigned read. Require supplied identities to exist, require person-linking and assignees to use verified identities, and require an assignee to hold `crm.followup` in the visitor scope. Establish one `VisitorInteractionCatalog` for direction/type/outcome values and one `CrmInteractionLineage` validator for message/document/assessment/payment/placement subject ownership. Manual CRM conversion routes are removed; Admissions/Students are the only downstream conversion authorities, and the recorder requires an explicit authority (`admissions` or `students`) plus verified person, active lifecycle, active originating branch, visitor/person ownership, and branch compatibility. A later Applicant-to-Student handoff is recorded as separate immutable CRM evidence rather than rewriting the terminal visitor conversion.

**IMPLEMENTATION** — `Controller::authorizedBranches()` now evaluates all active branches through `AccessDecision`, allowing organization-wide CRM reads without turning missing topology or null provenance into a wildcard. `VisitorListQuery` gives an explicit `branch_id` precedence over the authorized set and otherwise applies the fail-closed provenanced branch set. `CaptureVisitor` rejects unknown supplied people; `LinkVisitorPerson` requires a verified person. `CreateVisitorFollowup` validates a locked open visitor, verified assignee, and assignee CRM capability; automation calls this same command instead of directly inserting a follow-up, and rule definitions reject unverified assignees. `CrmInteractionLineage` follows each linked record to its authoritative person through its canonical joins and rejects unrelated or multiply linked references. `placement_attempt_id` is now present through the Placement extension and is accepted consistently by API/React/manual capture; all interaction checks use `VisitorInteractionCatalog`, including both schema migrations, the trace recorder, automation, and model accessors. The visitor read model exposes server-derived allowed lifecycle transitions so React does not duplicate the CRM state machine. `VisitorConversionRecorder` rejects enquiry conversions and direct CRM writes, resolves Student status by the append-only `seq` history actually present in the schema, and manual CRM conversion routes/UI are removed. The adjacent `StudentAdmissionRegistrar`/`StudentStatus` model path no longer writes the unsupported `effective_to` column. Capture now rejects active-lifecycle campaigns outside their configured date window, visitor updates can explicitly clear contact values without leaving an anonymous visitor contactless, and person-level CRM trace lookup is deterministic (latest conversion first, then newest open visitor). Organization grants now project only their concrete authorized branches, while the API/React boundary carries an explicit server-derived unassigned affordance instead of inferring it from an empty branch list. Authority-event provenance is rechecked for downstream interaction traces, including Communication/Documents, and Placement's direct-SQL guard covers its added reference. Applicant-to-Student enrollment now appends an immutable handoff trace tied to the original Applicant conversion, exact Student admission decision, Student authority event, verified person, and matching branch; historical list/timeline projections expose it without changing conversion counts. New regression coverage covers organization-wide branch projection, unknown/unverified identities, cross-person interaction references, and the no-direct-conversion boundary.

**STATIC LIMIT** — Catalogs and automation rules remain global facts because their current schema has no organization/branch provenance; their reads therefore require explicit organization authority and must not be presented as branch-local data. Runtime DI, migration ordering, PostgreSQL constraints, API behavior, concurrent locks, Applicant-to-Student handoff behavior, and the new cross-module lineage queries remain deferred under the standing no-runtime instruction.

### 5.13 Students, admissions, and lifecycle detail

**TARGET** — Admissions owns applicant registration and the three-stage decision chain; Students owns student identity, append-only status, branch transfer, hold, communication preference, guardian relationship, and lifecycle composition. Academic owns enrollment/delivery outcomes and Finance owns monetary facts. The operator UI must expose one authorized directory/detail surface without reimplementing these state machines.

**ACTUAL** — System C already had `RegisterApplicant`, `DecideAdmission`, `EnrollAdmittedApplicant`, `TransitionStudentStatus`, `TransferStudentHomeBranch`, `ManageStudentHold`, `MaintainStudentCommunicationPreference`, `MaintainGuardianRelationship`, and `StudentLifecycleQuery`. The former Blade student directory, admissions queue, and detail page were interactive transports over those commands, while the API was incomplete for branch options, registration people, decisions, and lifecycle actions.

**GAP** — The frontend had no student dispatch or detail contract. Student detail reads also needed an explicit Finance visibility decision, and the admissions/detail forms needed server-provided authorized people and branches. Guardian operations lacked a versioned API equivalent. Runtime route ordering, relation serialization, command DI, branch-negative authorization, and React contract/build behavior remain unverified.

**ROOT CAUSE** — Student lifecycle capability was implemented in canonical commands but exposed through a legacy Blade transport; the frontend convergence had only been completed for CRM and the general workspace.

**DECISION** — Converge Students and Admissions on the same React/TypeScript shell and `/api/v1/students` boundary. Keep admissions, Students, Academic, and Finance as separate authorities. Return only branch-authorized directory/applicant rows and form catalogs; fail closed on unknown provenance. The lifecycle query may compose Finance obligations/payments only when the actor has both concrete Finance capabilities. Guardian relationships remain unverified until explicit verification and revocation preserves history.

**IMPLEMENTATION** — `StudentsApiController::index` now returns authorized students/applicants, latest admission decisions, verified people, and active branch options. The controller delegates registration, initiate/review/approve, enrollment, status transitions, branch transfer, holds, communication preferences, and guardian record/verify/revoke to canonical commands. `StudentLifecycleQuery` now exposes available status transitions, branch names/provenance, preserves verified `guardians`, and separately exposes active `guardian_relationships` for the review controls. The API returns capability metadata and only includes Finance facts when both concrete Finance read capabilities are authorized; those obligation/payment rows are additionally filtered by their own current-home-first/originating branch provenance. React renders Finance facts read-only and never calculates balances. Web GET routes for the directory, admissions queue, and detail mount `workspace.blade.php` with student metadata; React dispatches `StudentsApp`, provides directory/admissions tabs, three-stage decision controls, enrollment, and lifecycle detail controls. The prior POST web routes remain explicitly temporary compatibility adapters and are not used by React. The lifecycle read is effective-date aware for status, hold, and branch-transfer history; it returns only the latest document version, suppresses guardian-review workflow details without the guardian capability, and keeps Finance row provenance visible to the client. Student admission lineage is now returned without raw identity keys, guardian disclosures use a canonical permission registry and verified-person boundary, mutation commands serialize on the Student row, and migrations `000158` and `000159` close applicant/student provenance, final-decision, current-home-transfer, same-day append, status-transition, initial-status, one-open-file, and guardian-verification-evidence invariants. The staged admission trigger now finalizes the applicant in an AFTER trigger so the database can prove the applicant state change is backed by a durable final decision. Rejected applications now have an explicit audited `ReopenApplicant` command and API/React path for a new decision cycle rather than an undocumented lifecycle edge; both the command and database guard prevent registering a second file to bypass rejected history. Guardian verification now requires an evidence reference in both API and temporary compatibility transport, records verifier/timestamp/evidence, and the database guard makes those verification facts immutable.

### 5.14 Remaining frontend migration boundary

**TARGET** — One React/TypeScript interactive architecture, with legacy Blade screens retired after their canonical API/UI replacements exist.

**ACTUAL** — CRM and Students/Admissions now use the shared React shell. Other module-specific Blade routes remain present as transitional screens and some legacy POST adapters remain for compatibility coverage.

**GAP** — Academic, Finance, HR, Payroll, Reporting, Library, Documents, Identity, Organization, Privacy, Placement, Communication, and access/configuration screens have not all crossed to React. No build or browser verification was allowed.

**ROOT CAUSE** — The fourth-architecture migration is being performed by capability slice so canonical API contracts and authorization boundaries stabilize before each UI crosses over.

**DECISION** — Do not preserve Blade and React as co-equal product architectures. Continue slice-by-slice migration, retire each interactive Blade route and compatibility adapter once its API/React replacement has static and runtime coverage, and keep canonical commands independent of either UI.

**IMPLEMENTATION** — Shared shell metadata now supports the student view and detail identifier. No new Blade business UI was added; the remaining Blade pages are recorded migration residue with explicit retirement work.

## 6. Workspace and search API contract

The static contract is intentionally small and source-linked. Workspace, management, notification, and search composition responses use the `{data: ...}` envelope; existing resource APIs retain their explicit resource keys while domain failures use the stable error taxonomy:

- `GET /api/v1/me` → authenticated middleware actor identity;
- `GET /api/v1/workspace` → effective positions, visible branches, assigned/claimable canonical work, source IDs/routes, and recipient notification state;
- `GET /api/v1/management` → organization or branch-scoped decision-support composition; global health only for organization-authorized actors;
- `GET /api/v1/search?q=...` → branch-scoped Students/Visitors with provenance;
- `GET /api/v1/students` → authorized student/applicant directory, staged decisions, registration people, and branch options;
- `GET /api/v1/students/{studentId}` → canonical lifecycle composition, with Finance obligations/payments only when the actor has both concrete Finance capabilities;
- `POST /api/v1/students/applicants`, `/applicants/{id}/reopen`, `/initiate`, `/decisions/{id}/review`, `/decisions/{id}/approve`, and `/applicants/{id}/enroll` → canonical Admissions operations;
- `POST /api/v1/students/{studentId}/status/{action}`, `/transfer`, `/hold`, `/communication-preference`, `/guardians`, and `/guardians/{relationshipId}/{verify|revoke}` → canonical Students lifecycle/relationship operations;
- `GET /api/v1/work-items` → assigned or explicitly claimable queue items within branch-safe Work Management scope;
- `POST /api/v1/work-items/{id}/transition` → coordination lifecycle only; it never invokes the linked domain action;
- `POST /api/v1/work-queues/memberships` and `POST /api/v1/work-queues/memberships/{id}/revoke` → explicit queue claim authorization;
- `GET /api/v1/notifications` → recipient-scoped notification projection;
- `POST /api/v1/notifications/{id}/read|dismiss` → recipient read-state only.

The controllers contain transport and scope composition only. They do not write business state, calculate financial balances, decide admissions, approve payroll, resolve permissions, or mutate notification state. Frontend links route to owning module pages, and the only workspace mutation controls call the versioned server commands for notification/work-item state. Static authorization review also closed several read-side gaps: Search and Work Management discovery now evaluate `students.manage`, `crm.visitor`, and `workflow.work` per concrete branch; student/applicant, academic-session, CRM visitor/catalog, placement catalog/profile, payroll-period, and Finance obligation/payment reads use their owning capability rather than generic branch visibility; visitor detail/timeline, student/detail/placement detail, and notification discovery use capability-aware branch checks; Student lifecycle and Placement finance-lineage reads now include Finance obligations/payments only when the actor also holds both Finance read capabilities in the resolved branch scope; Management Workspace branch scope is specifically `reporting.run`, not any unrelated branch grant. Branch report runs now persist organization provenance through migration `000153`; branch metric projections and organization-scoped dashboards are hardened by migrations `000154` and `000155`; Management excludes platform-global report runs and requires branch-run organization provenance. Migrations `000153` and `000155` now also backfill or fail closed for student/class organization provenance, and their triggers verify active branch, class, and student topology; dashboard pins receive the same active student/class/branch organization checks in `000154`. Employee appeal fallback and branch-scoped Management student/gate counts now apply the same current-home-first, immutable-origin-only-when-current-home-is-absent rule as other student projections. Empty or unknown scope remains an empty/denied result, and the shared `ActorBranches` read resolver now applies the same HR employment eligibility rule as `AccessResolution`; terminated/suspended people do not retain read-side branch visibility merely because old grants remain. Transitional Blade module pages that remain reachable during migration now require their owning organization capability before loading broad directory/configuration tables; the React workspace remains the selected interactive root, and legacy pages remain retirement work rather than a second authority. **Additional static scope correction:** `Branch::activeCampusAssignment`, `ActorBranches`, `AccessResolution`, Privacy organization reads, and the organization/report/dashboard/resource provenance guards now resolve campus attribution as of the effective day (`effective_from <= day` and `effective_to > day`), rather than treating a future scheduled transfer as already operational. This keeps current topology, organization provenance, reporting projections, and resource-root validation on one temporal interpretation. Cross-organization branch transfers now require the full structure decision chain to authorize both the current source and destination organization scopes, rather than allowing source authority to move provenance into an unrelated organization. The raw work-order schema probe now creates valid active organization/campus/branch provenance before inserting its deliberately invalid lifecycle state, so the probe isolates the lifecycle constraint. The generic `hasAnyAuthority` read helper no longer treats a role or unscoped delegation as proof of capability; organization-wide reads must prove their concrete Access decision. Delegation creation now requires one named capability and an explicit organization/campus/branch/department scope, verifies that the delegator holds that capability in that scope, and treats legacy null-capability or null-scope rows as unusable rather than wildcard authority. Effective employment checks in Access, Work Management, Actor Branches, and Employee Workspace now inspect the latest employment row including termination, so an old active employment cannot survive a later terminated record. Delegated authority now accepts an ancestor organization/campus grant for an explicitly narrower delegation while returning only the named delegated scope, preventing a branch delegation from widening into the whole organization. Employee Workspace held-Payroll fallback cards now require the actor's concrete `payroll.calculate` branch capability instead of generic visibility, matching the owning Payroll read boundary. The outbox projection, notification, queue-membership, and Work Management provenance migrations now use the same effective campus-assignment window (`effective_from <= CURRENT_DATE` and open-ended or future `effective_to`) instead of treating every historical assignment with a null end date as current. Polymorphic outbox aggregate IDs, notification source IDs, and Work Management source IDs now use string columns consistent with the event contract, which permits governed natural keys as well as UUIDs.

## 7. Graph governance and invariant decisions

The following laws remain binding:

1. **One fact, one owner.** A projection, dashboard, search result, task card, audit event, or endpoint delivery cannot silently become a competing fact.
2. **Workspace is not authority.** It discovers and orchestrates; source commands reauthorize.
3. **Scope is provenance.** A missing branch is unknown, not all branches. A record without sufficient provenance is withheld from branch-scoped views.
4. **Finance owns money.** UI labels, admissions status, academic eligibility, Payroll calculations, and reports cannot mint or rewrite monetary truth.
5. **Audit is not domain events.** Denied attempts are evidence, not successful business events.
6. **Outbox is transactional.** Durable event publication follows the owning transaction; relay delivery is at-least-once and consumers must be idempotent.
7. **Reporting is rebuildable.** Metric definitions, source owner, period authority, scope, evidence, and reproducibility remain explicit.
8. **Lifecycle is command-owned.** A workspace status chip is presentation; lifecycle transitions remain in the owning command and database guards.
9. **Read visibility is not write authorization.** Every workspace/search-originated mutation must pass the canonical server command decision again.
10. **No permanent compatibility architecture.** The versioned API and React boundary are the intended contract; legacy transport/UI is migration residue with a retirement plan.

## 8. Explicitly deferred capabilities

These are deferred intentionally, not silently omitted:

- external event subscriptions, optional materialized Search/Workspace/Reporting/Communication accelerators, replay commands, and operator dead-letter tooling; the allowlisted relay, receipts, invalidations, retry bounds, and `outbox.relay` schedule adapter now exist; source-query/metric-calculator projections remain the deterministic rebuild baseline;
- notification channel/device preferences, expiry/retry operations, and event-specific recipient policies beyond explicit notification intents;
- additional staged approval/exception capabilities not yet represented in the governed workflow catalog and future source-specific action adapters; all current catalog definitions—admission review/approval, academic appeals, financial corrections, and held Payroll calculations—emit explicit workflow intents, while the allowlisted consumer completes only the declared source events, including explicit held Payroll resolution;
- Work Management SLA timers, automatic queue assignment, escalation, and source-specific action adapters. Explicit queue membership/claim authorization now exists; existing domain-specific approvals remain in their owning modules;
- full React migration of the remaining module-specific Blade screens and retirement of their legacy interactive routes/POST adapters; CRM and Students/Admissions have crossed over to the shared React boundary, while the student POST adapters remain temporary compatibility residue and no longer serve the React UI;
- runtime-verified API/React typed-contract tests and accessibility/browser testing;
- explicit Scheduling module boundary and fully separated Enrollment authority where the final graph requires them;
- policy-complete donor restriction satisfaction/release, sponsorship agreement/claim/clawback, scholarship policy, bank reconciliation, procurement/AP, inventory, and asset depth where business policy makes them necessary; legacy Resource rows without provenance still require explicit reconciliation or retirement; migration `000156`, `ResourceScope`, the asset/disposal/work-order commands, and the book-copy command now require explicit branch/organization provenance for new roots and scoped transitions, while the console fails closed for null or topology-inconsistent historical rows;
- automatic journal/disbursement lineage for every Finance-recognized Payroll liability and the full correction/reversal reconciliation package;
- full management capacity/risk/exception projections beyond current Reporting and source-provenanced bounded counts;
- search ranking, cursor pagination, redaction policy, materialized index, and rebuild command;
- migration consolidation after the target schema stabilizes. The current pre-production chain remains evidence until the authority boundary stops changing; guard-hardening `000141` and settlement-authority `000143` are intentionally one-way until a reviewed baseline is produced, rather than offering partial rollbacks that could leave an old trigger name executing a new body.

## 9. Runtime-only risks and verification plan

The following must not be mistaken for completed convergence:

- PHP class loading, constructor dependency resolution, route registration, response serialization, relation/column names, and database compatibility have not been executed;
- `EmployeeWorkspaceQuery`, `ManagementWorkspaceQuery`, and `StudentLifecycleQuery` use current static model/schema evidence but need integration verification, especially staged-decision fields, guardian relationship serialization, Finance gate joins, student status/branch joins, and report-run column names;
- no migration or database operation was run, so branch indexes, resource-root provenance/backfill, Payroll held-resolution/index replacement, constraints, outbox tables, staged Finance trigger replacement, signed Payroll-liability provenance, settlement consolidation, and PostgreSQL behavior are unverified;
- no Composer dependency installation, PHP test, PHPStan, Pint, or PHPUnit run was permitted;
- no Node dependency installation, TypeScript check, Vite build, browser test, preview, or WCAG keyboard/screen-reader check was permitted;
- the Vite preview host/origin, Laravel asset manifest, CSRF/session behavior, API error shape, and same-origin deployment need runtime verification;
- no queue, relay, retry, consumer idempotency, ordering, replay, or dead-letter behavior was executed;
- no concurrency, stale-scope, unauthorized-source, empty-provenance, duplicate, or partial-failure test was executed; the new student coverage lock's transaction scope, deadlock behavior, and database lock plans remain runtime-only verification items;
- no performance/index/query-plan verification was performed;
- no production-readiness claim is made.

The next verification package should be executed only after this static design is accepted: install dependencies, apply the stabilized schema to a disposable database, run route/API contract tests, execute authorization and branch-negative tests, build the React boundary, run accessibility/browser checks, exercise outbox relay/consumer failure and replay cases, and perform migration/backup/readiness verification.

## 10. Final verdict

**Static verdict: converging, not complete.**

The repository now has the key fourth-architecture boundary slices that were previously absent: a versioned API contract, Workspace and Management query/controller surfaces, branch-safe Search, a single React/TypeScript work-first console, React CRM and Students/Admissions surfaces over canonical APIs, student lifecycle detail/guardian controls, Work Management coordination semantics, recipient/read Notifications, and an allowlisted transactional relay with consumer receipts and projection invalidations. The implementation preserves canonical ownership and avoids shadow notification-read-state, global-directory, and Finance authorities.

The repository is not yet production-ready and does not yet satisfy every graph edge. Finance authority is statically consolidated, but migration execution, trigger ordering, existing-data compatibility, concurrent correction/settlement behavior, and signed liability provenance remain unverified. The other deferred architectural work is optional materialized projection accelerators and external event subscriptions, additional workflow definitions/source-version freshness adapters, notification channel/recipient policy depth, SLA/escalation semantics, full React migration, Enrollment lifecycle boundary extraction, and runtime verification of the new static contracts.

---

## HISTORICAL SOURCE: `architecture/review/2026-09-05-integrated-system-architecture-graph.md`

# TOEFL House — Integrated Enterprise System Architecture Graph

**Document:** `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`  
**Date:** 2026-09-05  
**Status:** Target Architecture / Governing Architecture Specification  
**Scope:** Enterprise-wide logical architecture for TOEFL House  
**Primary objective:** One coherent system graph in which every major fact has one authoritative owner, every workflow has an explicit path, every action has enforceable authority, every cross-domain relationship is typed, and every important failure has defined behavior.

> **Important scope note:** This document is the reconstructed target architecture derived from the governing mandate and architectural critique supplied for this review. It is not a claim that an unseen repository already conforms to it. Repository conformance must be established by a subsequent code/schema/API audit.

**A. Executive System Map**

TOEFL House is modeled as **one enterprise organism**, not as a collection of independent modules.

The architecture has four layers of responsibility:

```
TOEFL HOUSE ENTERPRISE
│
├── 1. REALITY / FOUNDATION
│   ├── Organization
│   ├── Organizational structure
│   ├── People / Identity
│   ├── Time / Periods
│   └── Organizational context / provenance
│
├── 2. BUSINESS AUTHORITIES
│   ├── Workforce / Employment
│   ├── CRM / Admissions
│   ├── Placement / Assessment
│   ├── Student Lifecycle
│   ├── Academic Delivery
│   ├── Scheduling / Facilities
│   ├── Enrollment
│   ├── Finance
│   └── Payroll
│
├── 3. PLATFORM COORDINATION / EVIDENCE
│   ├── Authorization
│   ├── Work Management
│   ├── Documents
│   ├── Communication
│   ├── Events / Outbox
│   └── Notifications
│
└── 4. DISCOVERY / CONTROL
    ├── Search
    ├── Reporting / Analytics
    ├── Audit / Forensics
    └── Management Decision Support
```

The fundamental rule is:

```
ONE FACT
      ↓
ONE AUTHORITATIVE OWNER
      ↓
MANY READERS / PROJECTIONS
```

No UI, workspace, workflow engine, report, search index, document, notification, or event may silently become a second business authority.

**Enterprise graph**

```
OWNER / MANAGEMENT
        │
        ▼
ORGANIZATION ───────► ORGANIZATIONAL CONTEXT
        │                         │
        │                         ├── branch
        │                         ├── department / unit
        │                         ├── cost center
        │                         └── reporting scope
        │
        ▼
      PEOPLE
        │
        ├────────► IDENTITY / ACTOR
        │                │
        │                ├── authentication
        │                └── authorization context
        │
        ├────────► WORKFORCE / EMPLOYMENT
        │                │
        │                ├── position
        │                ├── assignment
        │                └── payroll inputs
        │
        └────────► CUSTOMER / ADMISSION / STUDENT PARTICIPATION
                         │
                         ├── CRM
                         ├── Admissions
                         ├── Placement
                         └── Student Lifecycle
                                    │
                                    ▼
                               ACADEMIC
                                    │
                       ┌────────────┴────────────┐
                       ▼                         ▼
                   OFFERING                 SCHEDULING
                       │                         │
                       └────────────┬────────────┘
                                    ▼
                                ENROLLMENT
                                    │
                                    ▼
                                  CLASS
                                    │
                                    ▼
                                 SESSION
                              ┌─────┴─────┐
                              ▼           ▼
                         ATTENDANCE   ASSESSMENT
                              │           │
                              └─────┬─────┘
                                    ▼
                            ACADEMIC RECORD
                                    │
                                    ▼
                               PROGRESSION
                                    │
                             ┌──────┴──────┐
                             ▼             ▼
                         COMPLETION     REPEAT / APPEAL
                             │
                             ▼
                         CREDENTIAL

Business sources ──────► FINANCE ◄────── PAYROLL
                           │
                    ┌──────┼──────┐
                    ▼      ▼      ▼
                  AR/AP   CASH    GL / SUBLEDGER
                    │      │      │
                    └──────┴──────┘
                           │
                           ▼
                    FINANCIAL TRUTH

ALL AUTHORITATIVE DOMAINS
             │
     ┌───────┼────────┬────────┬────────┐
     ▼       ▼        ▼        ▼        ▼
 WORKFLOW DOCUMENT COMMUNICATION SEARCH REPORTING
     │       │        │        │        │
     └───────┴────────┴────────┴────────┘
                      │
                      ▼
                    AUDIT
                      │
                      ▼
                 MANAGEMENT
```

**Typed enterprise relationship vocabulary**

Every architecture edge must be classified. `A → B` alone is insufficient.

```
OWNS                authoritative state / fact ownership
CREATES             originates a business record under domain authority
READS               obtains data without ownership
COMMANDS            requests a state-changing operation
AUTHORIZES          supplies authorization for an action
VALIDATES           checks preconditions / invariants
DEPENDS_ON          architectural prerequisite
CONSUMES_EVENT      reacts to an emitted event
EMITS_EVENT         publishes a domain/integration event
PROJECTS            builds a read representation
DOCUMENTS           renders evidence of an authoritative state/fact
ASSIGNS             routes work to an actor / queue
ESCALATES           moves work toward a stronger authority or SLA path
CORRELATES          links causal work across contexts
REFERENCES          maintains a stable external identity/reference
```

**B. Complete Connected Domain Graph**

**B1. Foundation graph**

```
ORGANIZATION
│
├── organizational units
├── campuses (optional hierarchy)
├── branches / operational locations
├── departments
├── cost centers
└── reporting structures
        │
        ├──────────────► PEOPLE
        ├──────────────► WORKFORCE
        ├──────────────► CRM / ADMISSIONS
        ├──────────────► ACADEMIC
        ├──────────────► FINANCE
        └──────────────► REPORTING
```

**Organization authority:** owns organization identity, organizational relationships, legal/operational identity, and organizational lifecycle.

**Creates:** organizational units, branches, scoped organizational relationships.

**Reads:** foundation consumers and management reporting.

**Changes:** authorized administrators through canonical organization commands.

**Authorizes:** organizational-scope administration, not individual business facts belonging to other domains.

**Depends on:** none beyond system identity and time.

**Produces:** organizational context.

**Events:** `OrganizationCreated`, `BranchCreated`, `BranchActivated`, `BranchSuspended`, `OrganizationalUnitChanged`.

**Consumes:** authorized administrative commands and governed master-data changes.

**Next:** context becomes available to identity, workforce, admissions, academic, finance, and reporting.

**B2. People and identity graph**

```
PERSON
 │
 ├── identity profile
 ├── contact methods
 ├── addresses
 ├── relationships
 └── participations / roles
       │
       ├── Applicant
       ├── Student
       ├── Employee
       ├── Guardian / Contact
       └── External / Authorized Actor
```

`Person` owns human identity facts. It does not own employment, admission, academic, or financial state merely because those records point to a person.

The identity model is:

```
PERSON
  ↓
IDENTITY ACCOUNT
  ↓
AUTHENTICATION
  ↓
ACTOR CONTEXT
  ↓
AUTHORIZATION DECISION
```

A person can participate in multiple domains simultaneously without creating duplicate identity records.

**B3. Workforce graph**

```
PERSON
 ↓
EMPLOYEE PARTICIPATION
 ↓
EMPLOYMENT
 ↓
POSITION
 ↓
ASSIGNMENT
 ↓
ORGANIZATIONAL SCOPE
 ↓
WORK ELIGIBILITY
 ↓
WORK / ATTENDANCE / SESSION FACTS
 ↓
VALIDATED PAYROLL INPUT
 ↓
PAYROLL CALCULATION
 ↓
PAYROLL RESULT
 ↓
PAYROLL LIABILITY
 ↓
FINANCE
```

**Workforce owns:** employment truth, position, assignment, employment state, HR lifecycle.

**Payroll owns:** payroll calculation result and payroll-period process state.

**Finance owns:** monetary liability and accounting truth.

**B4. Customer / Admissions graph**

```
CAMPAIGN / SOURCE
       ↓
PROSPECT / CONTACT
       ↓
LEAD
       ↓
ADMISSION CASE
       ↓
APPLICANT PARTICIPATION
       ↓
APPLICATION
       ↓
REQUIREMENTS / DOCUMENTS
       ↓
PLACEMENT
       ↓
ELIGIBILITY
       ↓
ADMISSION DECISION
       ↓
STUDENT PARTICIPATION
```

Applicant is a participation context; Application is a distinct request/case. One person may have multiple applications while retaining one person identity.

**B5. Placement graph**

```
APPLICATION / PLACEMENT REQUEST
            ↓
PLACEMENT ATTEMPT
            ↓
MEASUREMENT / EVIDENCE
            ↓
SCORING
            ↓
PLACEMENT RESULT
            ↓
CEFR / OTHER CLASSIFICATION
            ↓
RECOMMENDATION
            ↓
ACADEMIC ELIGIBILITY SNAPSHOT
            ↓
ADMISSIONS / ACADEMIC DECISION
```

Placement does not directly assign a class.

**B6. Student graph**

```
PERSON
 ↓
STUDENT PARTICIPATION
 ↓
PROGRAM / LEVEL CONTEXT
 ↓
OFFERING ELIGIBILITY
 ↓
FINANCIAL ELIGIBILITY
 ↓
ENROLLMENT REQUEST
 ↓
ENROLLMENT DECISION
 ↓
CLASS / SECTION
 ↓
SESSION
 ↓
ACADEMIC RECORD
 ↓
PROGRESSION
 ↓
COMPLETION
 ↓
GRADUATION
 ↓
CREDENTIAL
 ↓
ALUMNI PARTICIPATION
```

**B7. Academic graph**

```
PROGRAM
 ↓
PROGRAM VERSION
 ↓
LEVEL
 ├── skills
 ├── prerequisites
 └── progression rules
 ↓
BRANCH / DELIVERY AVAILABILITY
 ↓
OFFERING
 ├── term
 ├── branch
 ├── capacity
 ├── eligibility
 └── delivery constraints
 ↓
ENROLLMENT
 ↓
CLASS / SECTION
 ↓
SESSION PLAN
 ↓
SESSION DELIVERY
 ├── attendance
 ├── assessment
 └── instructional facts
 ↓
ACADEMIC RECORD
 ↓
PROGRESSION
 ↓
COMPLETION
```

**B8. Scheduling graph**

```
ACADEMIC DEMAND
     │
     ├── class
     ├── teacher
     ├── room
     ├── branch
     ├── student constraints
     └── time constraints
            ↓
      SCHEDULING MODEL
            ↓
      CONSTRAINT CHECK
            ├── teacher conflict
            ├── room conflict
            ├── class conflict
            ├── capacity conflict
            ├── availability conflict
            └── period/holiday conflict
            ↓
       SCHEDULE DECISION
            ↓
          SESSION
```

Scheduling is a planning/constraint authority. It does not own academic results or payroll money.

**B9. Enrollment graph**

Enrollment is its own authority for the enrollment decision, while consuming academic and financial eligibility.

```
ENROLLMENT REQUEST
 │
 ├──► academic eligibility query / snapshot
 │
 ├──► offering capacity / availability
 │
 └──► financial eligibility query / gate
              │
              ▼
       ENROLLMENT DECISION
              │
        ┌─────┴─────┐
        ▼           ▼
    ENROLLED      REJECTED / PENDING
        │
        ▼
    CLASS MEMBERSHIP
```

Finance does not decide that a student is enrolled. Academic does not decide the financial gate. Enrollment combines the governed inputs into the enrollment decision.

**B10. Finance graph**

```
BUSINESS SOURCE FACT
 ├── admissions / registration
 ├── enrollment
 ├── services
 ├── payroll
 └── approved financial operations
          ↓
FINANCIAL OBLIGATION
          ↓
BILLING / AR OR AP
          ↓
SETTLEMENT
 ├── payment
 ├── credit
 ├── waiver
 ├── discount
 └── refund
          ↓
ALLOCATION / APPLICATION
          ↓
ACCOUNTING EVENT
          ↓
SUBLEDGER
          ↓
GENERAL LEDGER
          ↓
RECONCILIATION / CLOSE
          ↓
FINANCIAL REPORTING
```

**B11. Platform graph**

```
AUTHORITATIVE DOMAIN CHANGE
          │
          ├──► DOMAIN EVENT
          │       │
          │       ▼
          │   TRANSACTIONAL OUTBOX
          │       │
          │       ▼
          │   EVENT DELIVERY
          │       │
          │   ┌───┼───────────┬───────────┬──────────┐
          │   ▼   ▼           ▼           ▼          ▼
          │ Workflow Communication Reporting Search Audit
          │
          ├──► DOCUMENT REQUEST → DOCUMENT SNAPSHOT
          │
          └──► READ PROJECTION / WORK DISCOVERY
```

**C. Domain Boundaries**

The target logical bounded contexts are:

ContextPrimary responsibilityAuthoritative factsMust not ownOrganizationorganizational realityorg/unit/branch structureenrollment, moneyIdentityperson/account identityperson identity, account identityemployment status, paymentsWorkforceemploymentemployment, position, assignmentaccounting truthAuthorizationaccess decisionspolicy/configuration and decision inputsbusiness stateCRMprospect/customer interactionlead, interaction, follow-upstudent enrollmentAdmissionsapplication/admission caseapplications, admission processacademic records, moneyPlacementplacement measurementattempts, evidence, scores, resultfinal class assignmentStudent Lifecyclestudent participation statestudent lifecycle identity/statuspayment ledgerAcademiccurriculum and academic recordsprogram, level, academic record, progressionmonetary truthSchedulingdelivery planningtimetable, room/teacher schedulingacademic gradesEnrollmentenrollment decision/membershipenrollment statepayment ledger, payrollFinancemonetary truthobligations, transactions, allocation, accountingacademic truthPayrollpayroll calculationpayroll runs/results/input validationgeneral ledger truthWorkflowprocess coordinationprocess instances, work statebusiness factsDocumentsdocumentary evidencerendered/versioned document artifactoriginal business factCommunicationoutbound communicationcommunication intent/delivery statebusiness stateReportinggoverned analytical viewsreporting models/projectionsoperational authoritySearchdiscoverabilitysearch indexsource-of-truth recordsAuditevidence trailaudit/security evidencebusiness-state authority

These are logical boundaries. They do **not** require one microservice per context.

**Boundary rule**

A context may:

```
READ another context's published/query contract
SEND a command through a defined application boundary
CONSUME an event
KEEP a local projection/reference
```

A context may not:

```
UPDATE another context's authoritative tables directly
RE-derive another context's truth as a second authority
ASSUME UI visibility equals authorization
USE a report/search index as business truth
```

**D. Authority Graph**

**D1. Core authority map**

```
ORGANIZATION
  owns → organizational truth

IDENTITY / PEOPLE
  owns → person identity truth

WORKFORCE
  owns → employment truth

AUTHORIZATION
  owns → access policy/decision mechanism

CRM
  owns → prospect/lead relationship truth

ADMISSIONS
  owns → application/admission process truth

PLACEMENT
  owns → placement measurement/evidence/result

STUDENT LIFECYCLE
  owns → student participation lifecycle

ACADEMIC
  owns → curriculum/academic record/progression

SCHEDULING
  owns → scheduling/availability planning

ENROLLMENT
  owns → enrollment membership/decision

FINANCE
  owns → monetary/accounting truth

PAYROLL
  owns → payroll calculation/run truth

WORKFLOW
  owns → process/work state, not domain state

DOCUMENTS
  owns → rendered documentary evidence

COMMUNICATION
  owns → delivery state and communication history

REPORTING
  owns → analytical projection, not source truth

SEARCH
  owns → search representation, not source truth

AUDIT
  owns → evidence of actions/attempts, not source truth
```

**D2. Authority test**

For each field or state in implementation, ask:

```
Who can declare this value authoritative?
Who can change it?
Who can invalidate it?
What command changes it?
What invariant protects it?
What event announces the change?
```

If two contexts answer the first question with the same fact, the architecture has a source-of-truth defect.

**D3. Effective authority**

Effective authority is a decision result, not an independent business-state authority:

```
ACTOR
 │
 ├── identity
 ├── role
 ├── relationships
 ├── scope
 ├── lifecycle state
 ├── delegation
 ├── revocation
 └── request context
          │
          ▼
    AUTHORIZATION POLICY
          │
          ▼
   AUTHORIZATION DECISION
     ┌────────┴────────┐
     ▼                 ▼
   ALLOW              DENY
```

Fine-grained authorization should be resource- and relationship-aware rather than assuming role names are sufficient. Current OpenFGA guidance describes authorization as relationships between users and resources and supports RBAC/ReBAC/ABAC-style modeling. citeturn775489search1turn775489search5turn775489search8

**E. Dependency Graph**

Dependencies are typed and intentionally non-circular.

**E1. Foundation dependencies**

```
Organization
   ↓
Organizational Context
   ↓
People / Identity
   ↓
Actor Context
   ↓
Authorization
```

**E2. Business dependencies**

```
CRM
  ↓
Admissions
  ↓
Placement
  ↓
Academic Eligibility
  ↓
Admission Decision
  ↓
Student Lifecycle
  ↓
Academic / Offering
  ↓
Enrollment
  ↓
Academic Delivery
```

Finance is a cross-domain authority, not an upstream owner of academic truth:

```
Admissions ─────┐
Enrollment ─────┤
Services ───────┤
Payroll ────────┘
       ↓
     Finance
```

**E3. Permitted cycle-like interaction**

A query followed by an event is acceptable:

```
Enrollment ──query──► Finance
Enrollment ◄─event── FinancialEligibilityChanged
```

This is not the same as a circular write dependency.

**E4. Forbidden dependency**

```
Academic ──writes──► Finance
Finance ──writes──► Academic
```

Both are forbidden. They must communicate through contracts.

**F. Data-Flow Graph**

**F1. Write flow**

```
USER / EXTERNAL ACTOR
        ↓
API / APPLICATION COMMAND
        ↓
AUTHENTICATION
        ↓
AUTHORIZATION
        ↓
COMMAND VALIDATION
        ↓
DOMAIN AUTHORITY
        ↓
AGGREGATE / CONSISTENCY BOUNDARY
        ↓
TRANSACTION
        ├── authoritative state change
        ├── audit evidence as required
        └── outbox record
        ↓
COMMIT
```

**F2. Read flow**

```
USER / WORKSPACE
       ↓
AUTHORIZED QUERY
       ↓
QUERY MODEL / READ MODEL
       ↓
CANONICAL / PROJECTION DATA
       ↓
FILTERED RESPONSE
```

**F3. Projection flow**

```
AUTHORITATIVE FACT
       ↓
DOMAIN EVENT
       ↓
OUTBOX
       ↓
EVENT DELIVERY
       ↓
CONSUMER
       ↓
IDEMPOTENCY CHECK
       ↓
READ PROJECTION
```

A projection can be stale and must expose/handle freshness expectations appropriately. It must never be promoted to authoritative business state.

**G. API-Flow Graph**

**G1. Query path**

```
HTTP GET / query
        ↓
Authentication
        ↓
Authorization: can_read(resource, actor, context)
        ↓
Query handler
        ↓
Governed read model
        ↓
Response DTO
```

**G2. Command path**

```
HTTP POST / PUT / PATCH / command
             ↓
Authentication
             ↓
Authorization
             ↓
Idempotency check when required
             ↓
Command handler
             ↓
Domain validation / invariant check
             ↓
Transaction
             ├── state change
             └── outbox
             ↓
Commit
             ↓
202 / 200 / 409 / 422 / 403 / 404 as contractually appropriate
```

**G3. API boundary law**

The API layer must not become a second domain layer.

```
Controller
  ≠ business authority

Service / Application Handler
  = command orchestration

Domain Authority
  = invariant + authoritative state decision
```

**H. Event / Outbox Graph**

**H1. Transactional event model**

```
CANONICAL COMMAND
      ↓
DOMAIN AUTHORITY
      ↓
DB TRANSACTION
      ├── business fact/state
      └── transactional outbox message
      ↓
COMMIT
      ↓
OUTBOX RELAY
      ↓
EVENT BUS / DELIVERY
      ↓
CONSUMERS
```

The transactional outbox pattern is appropriate when the business state change and message publication must be coordinated without distributed two-phase commit; relay delivery can be repeated, therefore consumers must be idempotent. citeturn775489search6

**H2. Event layers**

```
Domain Event
  = internal declaration that something important happened

Integration Event
  = contract intended for another bounded context / integration boundary

Notification Event
  = communication-oriented trigger
```

Do not treat all three as synonyms.

**H3. Event envelope**

Each important event should support:

```
message_id
aggregate_type
aggregate_id
aggregate_version
event_type
event_version
occurred_at
recorded_at
organization_id
operational_context_id
actor_id
correlation_id
causation_id
payload
```

**H4. Consumer safety**

```
EVENT
 ↓
message_id seen?
 ├── YES → return idempotent success / no duplicate effect
 └── NO  → process → record consumer receipt
```

Microsoft Dynamics business-event/workflow patterns likewise distinguish workflow tasks, approvals, automated tasks and generated work items instead of treating every workflow artifact as one concept. citeturn775489search0turn775489search2

**I. Financial Flow Graph**

**I1. Monetary truth**

Finance is the sole monetary authority.

```
BUSINESS SOURCE
     │
     ├── admission / registration
     ├── enrollment
     ├── service delivery
     ├── payroll
     └── approved adjustment source
            ↓
      FINANCIAL OBLIGATION
            ↓
      BILLING / AR OR AP
            ↓
         SETTLEMENT
            │
      ┌─────┼────────────┐
      ▼     ▼            ▼
   PAYMENT CREDIT      REFUND
      │     │            │
      └─────┴────────────┘
              ↓
          ALLOCATION
              ↓
       ACCOUNTING EVENT
              ↓
          SUBLEDGER
              ↓
       GENERAL LEDGER
              ↓
     BANK / RECONCILIATION
              ↓
       PERIOD CLOSE
              ↓
     FINANCIAL REPORTING
```

**I2. Required separation**

```
Payment
  ≠
Accounting Entry

Financial Eligibility
  ≠
Enrollment Decision

Payroll Result
  ≠
Finance Ledger Entry

Receipt Document
  ≠
Payment Truth
```

**I3. Financial correction model**

Never mutate historical money silently.

```
Incorrect Fact
   ↓
Correction / Reversal / Compensating Entry
   ↓
New authoritative fact
   ↓
Event
   ↓
Updated projections
```

**J. Employee Lifecycle Graph**

```
PERSON
 ↓
EMPLOYEE PARTICIPATION
 ↓
EMPLOYMENT OFFER / CREATION
 ↓
ACTIVE EMPLOYMENT
 ↓
POSITION
 ↓
ASSIGNMENT
 ↓
ORGANIZATIONAL SCOPE
 ↓
CAPABILITY / ROLE
 ↓
WORK ASSIGNMENTS
 │
 ├── admissions tasks
 ├── academic tasks
 ├── approvals
 ├── scheduling
 └── finance preparation tasks
 ↓
LEAVE / SUSPENSION / OTHER STATE
 ↓
TERMINATION
 ↓
FINAL PAYROLL / CLEARANCE
 ↓
HISTORICAL EMPLOYMENT RECORD
```

Current employment status must not erase historical assignments.

**Employee authority journey**

```
LOGIN
 ↓
IDENTITY
 ↓
EMPLOYMENT STATE
 ↓
POSITION / ASSIGNMENT
 ↓
SCOPE
 ↓
DELEGATIONS / REVOCATIONS
 ↓
AUTHORIZATION DECISION
 ↓
WORKSPACE
 ↓
ACTION
 ↓
SERVER-SIDE AUTHORIZATION
 ↓
CANONICAL COMMAND
 ↓
DOMAIN FACT
 ↓
EVENT / AUDIT / WORKSPACE UPDATE
```

**K. Student Lifecycle Graph**

```
PROSPECT
 ↓
LEAD
 ↓
ADMISSION CASE
 ↓
APPLICANT
 ↓
APPLICATION
 ↓
DOCUMENT / REQUIREMENT VERIFICATION
 ↓
PLACEMENT ATTEMPT
 ↓
PLACEMENT RESULT
 ↓
ACADEMIC ELIGIBILITY
 ↓
ADMISSION DECISION
 ↓
STUDENT
 ↓
PROGRAM / LEVEL
 ↓
OFFERING
 ↓
ENROLLMENT REQUEST
 ├───────────────► ACADEMIC CHECK
 └───────────────► FINANCIAL CHECK
          ↓
ENROLLMENT DECISION
 ↓
CLASS / SECTION
 ↓
SCHEDULE
 ↓
SESSION
 ├── attendance
 ├── assessment
 └── learning evidence
 ↓
ACADEMIC RECORD
 ↓
PROGRESSION DECISION
 ├── advance
 ├── repeat
 ├── appeal
 └── hold/restriction
 ↓
COMPLETION
 ↓
GRADUATION
 ↓
CREDENTIAL / TRANSCRIPT
 ↓
ALUMNI
```

**Lifecycle transition contract**

Every consequential transition must define:

```
Current state
Command
Actor
Authorization
Preconditions
Atomic transaction boundary
New state
Audit evidence
Domain event
Failure result
```

**L. Branch-Provenance Graph**

Branch is not merely a current `branch_id` field. It is an operational context attached to the fact that was produced.

```
ORGANIZATION
 ↓
BRANCH / OPERATIONAL CONTEXT
 ↓
FACT CREATION
 ├── applicant context
 ├── enrollment context
 ├── class context
 ├── session context
 ├── payment collection context
 ├── employee assignment context
 └── document production context
```

**L1. Current vs historical branch**

```
Student.current_home_branch
        ≠
HistoricalEnrollment.branch
        ≠
Session.operational_branch
        ≠
Payment.collection_context
```

**L2. Provenance envelope**

For material facts:

```
organization_id
operational_branch_id
source_context
actor_id
authority_context
effective_at
recorded_at
correlation_id
source_reference
version
```

**L3. Transfer principle**

A transfer changes future/current context; it does not rewrite prior facts.

```
BRANCH A
  │
  ├── historical enrollment
  ├── historical session
  └── historical payment collection

TRANSFER
  ↓
BRANCH B
  │
  └── future operational facts
```

Unknown or contradictory provenance must fail closed for protected operations.

**M. Calendar / Period Graph**

Time has two distinct meanings: a moment and a governed business period.

```
TIME
├── instant
├── local date/time
├── timezone
└── presentation calendar
      ├── Gregorian
      └── Jalali presentation / conversion
```

Business periods are separate domain objects:

```
PERIOD AUTHORITY
│
├── Academic Term
├── Financial Period
├── Payroll Period
├── Operational Period
└── Other governed periods
```

A period is not created by whichever module happens to need one.

**Period rule**

```
Academic month
Financial month
Payroll month
Operational month
```

are not automatically the same concept.

Each period has:

```
period_type
start_at
end_at
status
closure_state
organization_scope
```

Period closure must prevent unauthorized retroactive mutation.

**N. Workspace Graph**

Workspace is an **experience/application projection**, not a business authority.

```
AUTHORITATIVE DOMAINS
      │
      ├── tasks
      ├── approvals
      ├── exceptions
      ├── deadlines
      ├── assignments
      ├── notifications
      └── recent work
             ↓
        WORK DISCOVERY
             ↓
     AUTHORIZATION FILTER
             ↓
       EMPLOYEE WORKSPACE
             ↓
        RECORD CONTEXT
             ↓
       AUTHORIZED ACTION
             ↓
       CANONICAL COMMAND
```

**N1. Work concepts are separate**

```
Task
  = a unit of work to perform

Approval
  = a controlled decision step requiring an authorized response

WorkItem
  = an actionable instance assigned to an actor/queue

Exception
  = a detected abnormal, blocked, violated or attention-required condition

Notification
  = a delivery artifact informing an actor
```

A workflow may create a work item for a task or approval; Microsoft Dynamics explicitly models tasks, approvals and automated tasks as distinct workflow elements and work items as their runtime actionable instances. citeturn775489search0

**N2. Employee workspace does not write domains directly**

```
Workspace
   ↓
Command
   ↓
Application Handler
   ↓
Authorization
   ↓
Domain Authority
```

**O. Management Graph**

Management consumes projections and then acts through authorized commands.

```
AUTHORITATIVE FACTS
   │
   ├── finance
   ├── academic
   ├── workforce
   ├── admissions
   ├── student
   └── operations
         ↓
   GOVERNED READ MODELS
         ↓
   MANAGEMENT WORKSPACE
         │
         ├── KPIs
         ├── financial health
         ├── branch performance
         ├── academic health
         ├── staffing
         ├── risk
         ├── bottlenecks
         ├── exceptions
         └── pending decisions
         ↓
      MANAGEMENT DECISION
         ↓
  AUTHORIZED COMMAND / APPROVAL
         ↓
      DOMAIN AUTHORITY
```

Management dashboards may aggregate; they may not silently rewrite or redefine business facts.

**P. Security Graph**

**P1. Authorization request**

```
ACTOR
 ↓
RESOURCE
 ↓
ACTION
 ↓
ORGANIZATIONAL CONTEXT
 ↓
RESOURCE OWNERSHIP / RELATIONSHIP
 ↓
ACTOR LIFECYCLE STATE
 ↓
CAPABILITY / ROLE
 ↓
DELEGATION
 ↓
REVOCATION
 ↓
POLICY
 ↓
AUTHORIZATION DECISION
```

**P2. Sensitive boundary checks**

At every protected operation evaluate as applicable:

```
identity
actor status
organization scope
branch scope
resource ownership / relationship
capability
lifecycle state
delegation
revocation
separation of duties
concurrency/version
idempotency
```

**P3. Frontend rule**

```
Hidden UI action
  ≠ authorization

Disabled button
  ≠ authorization

Route guard
  ≠ authorization

Server-side authorization
  = mandatory
```

**P4. Resource-centric model**

Authorization should be expressed in terms of resources and relationships when hierarchy or branch scope matters; role names alone become brittle as resource relationships grow. Current OpenFGA modeling guidance explicitly recommends starting from resources/objects and represents access through relationships, with attribute context where needed. citeturn775489search1turn775489search5turn775489search8

**Q. Failure / Concurrency Graph**

Every important command must define its behavior for:

```
NORMAL
DUPLICATE
CONCURRENT
STALE
UNAUTHORIZED
WRONG BRANCH
INVALID LIFECYCLE
MISSING PROVENANCE
UNIQUE CONSTRAINT
DOWNSTREAM FAILURE
EVENT RETRY
EVENT DUPLICATE
REPLAY
```

**Q1. Command behavior matrix**

ConditionRequired behaviorduplicate idempotent requestreturn the same logical result; no duplicate stateunauthorizedreject; create security evidence where appropriatewrong branchreject closed; no data mutationstale versionreturn conflict; require fresh read/retryinvalid lifecyclereject with domain validation resultmissing provenancereject for facts requiring provenancecapacity raceatomic constraint/locking/conditional update decides winnerdownstream event delivery failurebusiness commit remains; outbox retriesduplicate eventconsumer idempotency prevents duplicate effectdocument rendering failuresource business fact remains authoritative; document job retriescommunication failuresource business fact remains committed; communication retries/fails independentlyreport refresh failureoperational truth remains intact

**Q2. Optimistic concurrency**

Critical aggregates should expose versions:

```
Enrollment.version
Offering.version
FinancialObligation.version
Approval.version
Placement.version
StudentLifecycle.version
```

State-changing commands should use optimistic concurrency unless a stronger transaction/locking strategy is explicitly required.

**Q3. Capacity example**

```
Offering capacity = 20
Current enrollment = 19

Request A ─┐
           ├── atomic capacity decision ──► winner
Request B ─┘
```

A read-then-write check without an atomic concurrency boundary is insufficient.

**R. Reporting Graph**

```
AUTHORITATIVE DOMAIN FACTS
       ↓
CHANGE EVENTS / CONTROLLED QUERIES
       ↓
READ PROJECTIONS
       ↓
GOVERNED REPORTING MODEL
       ├── executive
       ├── branch
       ├── academic
       ├── finance
       ├── workforce
       ├── operations
       ├── audit
       └── analytics
       ↓
MANAGEMENT DECISION
```

**R1. Reporting rules**

Reporting may:

```
aggregate
join governed projections
calculate KPIs
calculate ratios
build trends
```

Reporting may not:

```
repair source facts silently
redefine balances
recalculate source authority differently
write operational truth
```

A calculated KPI must specify its source definitions and period semantics.

**S. Document Graph**

```
AUTHORITATIVE FACT / STATE
        ↓
DOCUMENT REQUEST
        ↓
TEMPLATE VERSION
        ↓
SOURCE FACT VERSION / SNAPSHOT
        ↓
IMMUTABLE RENDER SNAPSHOT
        ↓
DOCUMENT ARTIFACT
        ↓
OPTIONAL VERIFICATION / SIGNATURE
```

A document records evidence of a business state. It is not that state.

**Document metadata**

```
document_id
subject_type
subject_id
template_id
template_version
source_fact_version
generated_at
generated_by
verification_state
signature_state
checksum
```

Old certificates/transcripts/receipts must remain historically reproducible even after current student data changes.

**T. Communication Graph**

```
AUTHORITATIVE EVENT / APPROVED ACTION
             ↓
     COMMUNICATION POLICY
             ↓
    COMMUNICATION INTENT
             ↓
   CONSENT / PREFERENCE CHECK
             ↓
      TEMPLATE VERSION
             ↓
       CHANNEL SELECTION
       ┌──────┼──────┐
       ▼      ▼      ▼
      SMS    EMAIL   PUSH
       │      │      │
       └──────┴──────┘
              ↓
       DELIVERY STATE
              ↓
       RETRY / FAILURE
              ↓
    COMMUNICATION HISTORY
```

Business transaction and outbound communication must be decoupled:

```
Enrollment committed
       ↓
Welcome communication requested
       ↓
SMS fails
       ↓
Enrollment remains committed
SMS delivery retries independently
```

**U. Missing-Capability Analysis**

The objective is not to maximize module count. A capability is added only when a business problem, authority boundary, compliance requirement, or scalable lifecycle justifies it.

CapabilityClassificationArchitectural reasonOrganization / branches / unitsRequired nowfoundational scope and provenanceIdentity / authenticationRequired nowactor identityFine-grained authorizationRequired nowbranch/resource/action protectionCRM / leadsRequired nowadmissions pipelineAdmissions / applicationsRequired nowapplicant lifecyclePlacementRequired nowTOEFL-specific academic entry pathStudent lifecycleRequired nowcore education processAcademic curriculum / offeringRequired nowcourse delivery authorityEnrollmentRequired nowmembership/registration authoritySchedulingRequired nowdelivery viabilityAttendanceRequired nowacademic + operational factAssessmentRequired nowacademic evidenceFinance / ARRequired nowmonetary truthPayrollArchitecturally required if payroll is internalkeeps payroll calculation distinct from finance ledgerDocumentsRequired nowcredentials/receipts/evidenceCommunicationRequired nowoperational/customer messagingWorkflowRequired nowhuman coordination and approvalsEvents / outboxArchitecturally requiredreliable propagationAuditRequired nowaccountability / forensicsSearchRequired now for scale/UXdiscovery without source mutationReportingRequired nowmanagement visibilityCash managementArchitecturally required as finance growspayment/cash controlBank reconciliationLater / required with bank-heavy operationsfinancial controlGeneral ledgerRequired if formal accounting is in scopeaccounting truthCost centersRequired if branch profitability/cost accounting mattersmanagement accountingBudgetingLaterplanning rather than transaction processingProcurementLateronly if TOEFL House operates purchasing workflows internallyVendorsConditionalrequired with procurement/AP vendor managementInventory / books / materialsConditionalrequired only if stock is operationally materialAsset managementLaterrequired when fixed assets need controlled lifecycleScholarshipsConditional, likely laterdependent on business policySponsorshipsConditionaldependent on business modelComplaints / student supportLaterservice-management maturityAppointmentsLateruseful if admissions/support is appointment-centricRoom bookingRequired within scheduling if rooms are shared resourcesfacility conflict controlStaff recruitmentLaterHR expansionLeaveRequired for mature workforce managementpayroll/availability impactTime attendanceConditionalrequired if payroll depends on hours/attendanceStaff performanceLaterHR maturityStaff trainingLaterworkforce developmentHelpdesk / service requestsLatersupport operating modelDocument verificationRequired for controlled credentialsevidence authenticityE-signaturesLater / conditionallegal/policy requirementCompliance managementArchitecturally required as a cross-cutting concernpolicy/evidence/retentionIncident managementLateroperational and security maturityAnomaly detectionLateranalytics maturity; not core authorityWorkflow escalationRequired within workflowSLA/exception managementAI decisioningNot a core authoritymust not replace governed business decisions

**V1 focus**

The first architecture should be complete for:

```
Organization
Identity
Authorization
Workforce
CRM
Admissions
Placement
Student
Academic
Scheduling
Enrollment
Finance
Payroll boundary
Documents
Communication
Workflow
Events/Outbox
Audit
Search
Reporting
```

Advanced ERP capabilities should be added only when requirements justify them.

**V. Research Findings**

The architecture was compared conceptually with established enterprise patterns in ERP/workflow, education management, authorization and reliable event publication.

**V1. Education enterprise pattern**

Large education-management systems commonly connect admissions, academics/enrollment, student accounts and credentials rather than treating them as isolated applications. Oracle's Student Cloud materials describe an integrated student management model spanning admissions, academics, enrollment, student accounts and credentials. The important architectural lesson is **continuous lifecycle traceability**, not a requirement to copy Oracle's module naming.

**V2. Workflow pattern**

Microsoft Dynamics explicitly separates workflow tasks, approvals and automated tasks; runtime work items are created for actionable user steps. This supports maintaining distinct concepts for `Task`, `Approval`, `WorkItem`, `Exception` and `Notification`. citeturn775489search0turn775489search7

**V3. Workflow security**

Microsoft's approval-flow guidance checks that the workflow instance/work item is still valid before allowing completion, demonstrating an important rule for TOEFL House: **a previously issued approval link or work item must not become a bypass around current domain state and authorization**. citeturn775489search2

**V4. Event reliability**

Transactional outbox is a strong fit where a business transaction and event publication must be coordinated. Relay duplicates are possible, therefore consumers require idempotent handling. citeturn775489search6

**V5. Authorization**

Relationship-based access control is especially relevant to TOEFL House because access may depend on organization, branch, resource relationship, delegation and current context. OpenFGA's current guidance models authorization from resources and relationships and supports attribute-based contextual conditions. citeturn775489search1turn775489search5turn775489search8

**V6. Architectural interpretation**

The external patterns support, but do not dictate, the following TOEFL House design decisions:

```
Use bounded contexts
Use domain ownership
Use workflow as coordination, not business authority
Use typed events
Use transactional outbox
Use idempotent consumers
Use resource/scope-aware authorization
Use versioned evidence/documents
Use projections for reporting/search/workspace
```

**W. Alternatives Considered**

**W1. Module-centric monolith**

```
UI
 ↓
Modules
 ↓
Shared tables
```

**Rejected.** It encourages multiple modules to mutate the same business fact and hides authority boundaries.

**W2. Microservice-per-module**

```
20+ services
20+ databases
many synchronous calls
```

**Rejected as the default physical implementation.** The domain model is not mature enough to justify operational complexity. Logical bounded contexts should exist even if implemented in one deployable application.

**W3. Event-sourced everything**

```
Every business fact = event stream
Current state = projection
```

**Rejected as the default.** Auditability and history do not automatically require global event sourcing. It adds operational, modeling and replay complexity that is not justified for every context.

**W4. Shared database as shared authority**

```
All modules can update all tables
```

**Rejected.** A shared physical database is acceptable only if logical ownership and write boundaries are enforced.

**W5. Workflow-owned business truth**

```
Workflow says enrollment approved
therefore student is enrolled
```

**Rejected.** Workflow may coordinate a decision but Enrollment must authoritatively change Enrollment state.

**W6. Report-driven truth**

```
Report calculates a new balance
system trusts report balance
```

**Rejected.** Reports are projections.

**W7. Search-as-authority**

```
Search index is faster → therefore system reads/writes search as truth
```

**Rejected.** Search is a projection.

**X. Final Decisions**

**X1. Final architectural shape**

**Decision:** TOEFL House will be a **domain-oriented modular enterprise system**, preferably a modular monolith at the current stage, with strict logical bounded contexts and authority boundaries.

**X2. Source of truth**

**Decision:** exactly one authoritative owner per major business fact.

**X3. Workspace**

**Decision:** workspace is a projection/orchestration surface, never a fact owner.

**X4. Finance**

**Decision:** Finance owns monetary truth; other domains request financial actions or consume financial decisions.

**X5. Enrollment**

**Decision:** Enrollment owns enrollment membership/decision; it consumes academic and financial eligibility rather than delegating enrollment authority to either.

**X6. Placement**

**Decision:** Placement owns measurement/evidence/result; it does not directly assign classes.

**X7. Workflow**

**Decision:** Workflow owns process state/work items/coordination, not domain facts.

**X8. Events**

**Decision:** transactional outbox + idempotent consumers are mandatory for durable asynchronous business-event propagation.

**X9. Security**

**Decision:** authorization is resource/scope/lifecycle/context aware and enforced server-side.

**X10. History**

**Decision:** historical facts are preserved; current attributes never overwrite prior provenance.

**X11. Documents**

**Decision:** documents are immutable/versioned evidence snapshots of authoritative facts.

**X12. Reporting**

**Decision:** reports consume governed projections and cannot redefine source truth.

**Y. Rejected Architectures**

The following are explicitly rejected unless a future ADR reverses them:

```
1. UI-owned business logic
2. Shared-table unrestricted writes
3. Multiple balance authorities
4. Applicant = Application
5. Student = Enrollment
6. Payment = Accounting Entry
7. Payroll = Finance
8. Workflow = Domain Authority
9. Event = Source of Truth
10. Document = Source of Truth
11. Search = Source of Truth
12. Current branch overwrites historical branch
13. Hidden authorization in UI only
14. GET requests that mutate business state
15. Report calculations treated as transaction truth
16. Microservice-per-module by default
17. Event sourcing everywhere by default
18. One generic “status” field for all lifecycle meanings
19. One generic “approval” concept for business eligibility and human authorization
20. One generic “task” concept for task, approval, exception and notification
```

**Z. Implementation Sequence**

Implementation follows architecture rather than the other way around.

**Z1. Phase 0 — Architecture contract**

```
Define contexts
Define ownership matrix
Define entity/aggregate ownership
Define command catalog
Define query catalog
Define event catalog
Define API contracts
Define authorization model
Define provenance contract
Define lifecycle/state machines
Define concurrency rules
```

**Z2. Phase 1 — Foundation**

```
Organization
Organizational Structure
People
Identity
Time / Periods
Actor Context
Authorization
Audit foundation
```

**Z3. Phase 2 — Workforce**

```
Employment
Position
Assignment
Workforce lifecycle
Leave / attendance as justified
Payroll boundary
```

**Z4. Phase 3 — Customer to Student**

```
CRM
Admissions
Application
Placement
Eligibility
Admission Decision
Student lifecycle
```

**Z5. Phase 4 — Academic Delivery**

```
Program
Program Version
Level
Offering
Scheduling
Enrollment
Class / Section
Session
Attendance
Assessment
Progression
Completion
Credentials
```

**Z6. Phase 5 — Finance**

```
Obligations
Billing
Payments
Allocation
Refunds
Credits / waivers
Subledger
General Ledger
Reconciliation
Period close
```

Payroll integrates here through controlled financial liability contracts.

**Z7. Phase 6 — Platform coordination**

```
Workflow
Task
WorkItem
Approval
Exception
Notification
Document service
Communication service
Outbox / event delivery
Search projections
Reporting projections
```

**Z8. Phase 7 — Management and control**

```
Executive dashboards
Branch dashboards
Academic dashboards
Finance dashboards
Workforce dashboards
Audit views
Exception management
SLA / escalation
```

**Z9. Phase 8 — Hardening**

```
Concurrency tests
Authorization tests
Branch-isolation tests
Lifecycle transition tests
Idempotency tests
Outbox/replay tests
Historical/provenance tests
Financial correction tests
Document reproducibility tests
Reporting reconciliation
```

**Final Integrated Enterprise Graph**

The complete architecture is intentionally readable as a continuous chain.

**Owner → Management path**

```
OWNER / MANAGEMENT
 → ORGANIZATION
 → ORGANIZATIONAL CONTEXT
 → BRANCH / UNIT
 → PERSON
 → EMPLOYEE PARTICIPATION
 → EMPLOYMENT
 → POSITION
 → ASSIGNMENT
 → ACTOR CONTEXT
 → AUTHORIZATION POLICY
 → AUTHORIZATION DECISION
 → EMPLOYEE WORKSPACE
 → WORK DISCOVERY
 → AUTHORIZED ACTION
 → CANONICAL COMMAND
 → DOMAIN AUTHORITY
 → TRANSACTION
 → AUTHORITATIVE FACT
 → DOMAIN EVENT
 → OUTBOX
 → EVENT DELIVERY
 → REPORTING PROJECTION
 → MANAGEMENT VIEW
 → MANAGEMENT DECISION
 → AUTHORIZED COMMAND / APPROVAL
 → DOMAIN AUTHORITY
```

**Lead → credential path**

```
LEAD
 → ADMISSION CASE
 → APPLICANT
 → APPLICATION
 → REQUIREMENT / DOCUMENT
 → PLACEMENT ATTEMPT
 → EVIDENCE
 → SCORING
 → PLACEMENT RESULT
 → ACADEMIC ELIGIBILITY SNAPSHOT
 → ADMISSION DECISION
 → STUDENT
 → PROGRAM / LEVEL
 → OFFERING
 → FINANCIAL ELIGIBILITY
 → ENROLLMENT REQUEST
 → ENROLLMENT DECISION
 → CLASS
 → SCHEDULE
 → SESSION
 → ATTENDANCE
 → ASSESSMENT
 → ACADEMIC RECORD
 → PROGRESSION
 → COMPLETION
 → GRADUATION
 → CREDENTIAL
 → DOCUMENT SNAPSHOT
```

**Employee → settlement path**

```
EMPLOYEE
 → EMPLOYMENT
 → POSITION
 → ASSIGNMENT
 → WORK / VALIDATED PAYROLL INPUT
 → PAYROLL CALCULATION
 → PAYROLL RESULT
 → PAYROLL LIABILITY
 → FINANCE
 → ACCOUNTING EVENT
 → SUBLEDGER
 → GENERAL LEDGER
 → RECONCILIATION / CLOSE
 → SETTLEMENT
 → AUDIT EVIDENCE
```

**Exception path**

```
DOMAIN FACT / EVENT
 → RULE / MONITOR
 → EXCEPTION
 → WORKFLOW
 → WORK ITEM
 → AUTHORIZED EMPLOYEE
 → VALIDATE CURRENT STATE
 → COMMAND
 → DOMAIN AUTHORITY
 → RESULT
 → EVENT / AUDIT
 → WORKFLOW COMPLETE OR ESCALATE
```

**Document path**

```
AUTHORITATIVE FACT
 → DOCUMENT REQUEST
 → TEMPLATE VERSION
 → SOURCE VERSION SNAPSHOT
 → IMMUTABLE RENDER
 → DOCUMENT
 → VERIFICATION / SIGNATURE
 → DELIVERY
```

**Search path**

```
AUTHORITATIVE FACT
 → CHANGE EVENT
 → SEARCH PROJECTION
 → AUTHORIZATION-FILTERED QUERY
 → WORKSPACE RESULT
 → CANONICAL RECORD
 → CANONICAL ACTION
```

**Governing Laws of TOEFL House**

These rules govern the architecture above.

```
LAW 01 — ONE FACT, ONE OWNER

LAW 02 — WORKSPACE IS NOT AUTHORITY

LAW 03 — REPORTING IS NOT AUTHORITY

LAW 04 — SEARCH IS NOT AUTHORITY

LAW 05 — DOCUMENTS ARE EVIDENCE, NOT SOURCE TRUTH

LAW 06 — EVENTS ANNOUNCE FACTS; THEY DO NOT SILENTLY OWN THEM

LAW 07 — WORKFLOW COORDINATES; DOMAIN AUTHORITIES DECIDE BUSINESS STATE

LAW 08 — AUTHORIZATION MUST BE ENFORCED AT THE SERVER-SIDE COMMAND BOUNDARY

LAW 09 — CURRENT CONTEXT MUST NEVER REWRITE HISTORICAL PROVENANCE

LAW 10 — MONETARY TRUTH HAS ONE OWNER: FINANCE

LAW 11 — ENROLLMENT DECIDES ENROLLMENT, AFTER CONSUMING GOVERNED ELIGIBILITY INPUTS

LAW 12 — PLACEMENT MEASURES AND RECOMMENDS; IT DOES NOT ASSIGN CLASSES

LAW 13 — PAYROLL CALCULATES; FINANCE RECORDS MONETARY TRUTH

LAW 14 — SYNCHRONOUS PATHS PROTECT DECISIONS; ASYNC PATHS PROPAGATE CONSEQUENCES

LAW 15 — EVERY DURABLE EVENT CONSUMER MUST BE IDEMPOTENT

LAW 16 — EVERY CRITICAL COMMAND MUST DEFINE DUPLICATE, CONCURRENT, STALE,
         UNAUTHORIZED AND RETRY BEHAVIOR

LAW 17 — READ DEPENDENCY MUST NOT IMPLY WRITE AUTHORITY

LAW 18 — BUSINESS PERIODS ARE GOVERNED OBJECTS, NOT LOCAL MODULE CONVENTIONS

LAW 19 — HISTORICAL FACTS ARE PRESERVED; CORRECTIONS CREATE NEW FACTS WHERE REQUIRED

LAW 20 — ARCHITECTURE IS DEFINED BY RESPONSIBILITY AND AUTHORITY, NOT BY SCREEN COUNT
```

**Architectural Acceptance Criteria**

The architecture is considered internally coherent only when all of the following can be answered without ambiguity:

```
1. Who owns this fact?
2. Which command changes it?
3. Which authorization permits that command?
4. Which actor and organizational context performed it?
5. Which aggregate/boundary protects its invariants?
6. What happens under concurrent modification?
7. What happens if the request is repeated?
8. What happens if the event is repeated?
9. What event announces the committed change?
10. Which projections consume it?
11. Which workflow, if any, is created?
12. Which document, if any, is generated?
13. Which notification, if any, is sent?
14. Which reports consume the result?
15. How is the historical state reconstructed?
16. How is branch provenance retained?
17. What does an unauthorized attempt leave in audit evidence?
18. Can any other module mutate the fact directly?
19. Can the source fact be corrected without rewriting history?
20. Can management trace a KPI back to authoritative source facts?
```

If any answer is unclear, that part of the architecture is not finished.

**Final Architectural Position**

TOEFL House is therefore not defined as:

```
CRM + Admissions + Academic + Finance + HR + Reports + etc.
```

It is defined as:

```
ORGANIZATIONAL REALITY
        ↓
PEOPLE + CONTEXT + TIME
        ↓
AUTHORITY
        ↓
BUSINESS LIFECYCLES
        ↓
AUTHORITATIVE FACTS
        ↓
TRANSACTIONAL EVENTS
        ↓
WORK / DOCUMENT / COMMUNICATION / SEARCH / REPORTING
        ↓
MANAGEMENT DECISION
        ↓
AUTHORIZED ACTION
        ↓
NEW AUTHORITATIVE FACT
```

The system is coherent when a user can trace any meaningful outcome from:

```
WHO
→ ACTOR
→ AUTHORITY
→ CONTEXT
→ COMMAND
→ DOMAIN OWNER
→ FACT
→ EVENT
→ OUTCOME
```

and when every business lifecycle can be followed without encountering:

```
multiple sources of truth
hidden authority
unbounded shared writes
branch leakage
historical rewriting
lifecycle contradictions
workflow/business-state confusion
financial duplication
non-idempotent event effects
or unexplained circular dependencies.
```

**This is the governing target architecture for the next repository audit and implementation phase.**

---

## HISTORICAL SOURCE: `architecture/review/2026-09-05-repository-conformance-audit.md`

# TOEFL House — Repository Conformance Audit

**Date:** 2026-09-05  
**Repository:** `alfrotan-glitch/TOEFL-House`  
**Branch:** `arena/01a07134-toefl-house`  
**Target:** `docs/architecture/review/2026-09-05-integrated-system-architecture-graph.md`  
**Audit type:** repository-wide static conformance audit  
**Verdict:** **NOT CONFORMING**

> Historical baseline: this audit predates the convergence slices recorded in `2026-09-05-fourth-architecture-convergence.md`. Read that required report for the current static actual/gap/decision/implementation state; this document remains evidence of the earlier conformance starting point.

This document compares the target architecture with the repository as it exists in the current worktree. It is a conformance finding, not a production-readiness statement. The audit did not run PHP, Composer, PostgreSQL, migrations, PHPUnit, PHPStan, Pint, Node, browser, queue, HTTP, or integration execution.

## 1. Executive result

The repository contains a substantial, disciplined Laravel modular-monolith implementation with canonical Finance facts, Payroll results, Academic delivery, branch-aware authorization, append-only history, audit evidence, and a transactional domain-event recorder. It is nevertheless **not conforming** to the mandatory Integrated Enterprise System Architecture Graph.

The decisive gaps and contradictions are:

1. **Enrollment is implemented inside Academic.** The target makes Enrollment its own authority for enrollment membership and decision. `Academic\\Models\\Enrollment` and `Academic\\Commands\\MaintainEnrollment` remain the sole write path, so the authority is singular but the bounded-context boundary is wrong.
2. **Scheduling is implemented inside Academic.** `MaintainClass::scheduleSession()` creates `Academic\\Models\\ClassSession` and owns room, section, teacher, and timetable constraints. There is no Scheduling context or application boundary.
3. **Workflow / Work Management is missing.** Resource `WorkOrder` is a facilities/resource fact, not the target's separate Task, Approval, WorkItem, Exception, and process-coordination capability. Domain-specific approvals exist, but there is no Workflow authority.
4. **Employee Workspace is missing as a product capability.** There is no Workspace module, aggregate, query, controller, API route, web route, Blade view, React tree, or typed workspace contract.
5. **The event/outbox chain stops at append-only recording.** `domain_events` and `TransactionalEventRecorder` exist, but no static consumer/projector/relay was found that reads domain events and creates integration deliveries or durable internal projections. `ProcessDeliveries` processes pre-existing endpoint delivery rows; it is not a domain-event relay.
6. **Search and Notification capabilities are missing.** Communication messages are not a Notification authority, and no Search projection/index boundary was found.
7. **The documented frontend target and actual frontend disagree.** Existing architecture reconciliation documents select a React/TypeScript feature frontend; the repository contains a Blade employee console and no package/frontend feature tree.
8. **Finance has the strongest fact ownership, but the full accounting flow is incomplete.** Obligations, payments, allocations, corrections, payroll liability recognition, journals, and reconciliations exist. The static code does not demonstrate that every approved monetary source automatically produces an accounting event/subledger/GL entry through a governed flow.
9. **The migration result is not statically executable evidence.** Historical `final_settlements`, `compensation_components`, and `work_bases` definitions remain in migration history while later migrations remove or supersede them. The intended active authority is clear, but clean-schema and upgrade outcomes remain runtime questions.

No second Enrollment, Payment, Payroll Result, Payroll Liability, or Finance Settlement fact owner was found. The principal issue is therefore not uncontrolled duplication of every named fact; it is that several target contexts and propagation paths are absent or merged.

## 2. Method and comparison chain

The audit followed the required chain:

```text
TARGET ARCHITECTURE
 → ACTUAL DOMAIN STRUCTURE
 → ACTUAL BACKEND
 → ACTUAL DATABASE / MIGRATIONS
 → ACTUAL API / WEB ROUTES
 → ACTUAL FRONTEND
 → ACTUAL EVENTS / OUTBOX
 → ACTUAL REPORTING
 → ACTUAL AUTHORIZATION
 → ACTUAL WORKSPACE
```

Static evidence was cross-checked across:

- the target graph and governing architecture/ADR documents;
- `app/Modules/*/Models`, `Commands`, `Queries`, `Domain`, and supporting ports;
- `database/migrations/`, including migration ordering, historical drops, triggers, indexes, and foreign keys;
- `routes/api.php`, `routes/web.php`, and `app/Http/Controllers`;
- `resources/views/` and repository frontend/package inventory;
- Outbox, Integrations, Audit, Reporting, and authorization infrastructure;
- references to each required business concept, including raw table access and direct cross-module writes.

The comparison treats an ordered migration as an intended schema transition, not as proof of the deployed schema. It also distinguishes a read-side cross-domain query from an unauthorized write. A target concept was not invented merely to make the matrix appear complete.

## 3. Finding classification

| Classification | Meaning in this audit |
|---|---|
| `CONFORMING` | The actual owner, write boundary, schema representation, and principal contract agree with the target for the inspected scope. |
| `PARTIALLY CONFORMING` | A usable implementation exists, but one or more target edges, invariants, propagation paths, or boundary details are incomplete. |
| `CONTRADICTORY` | The repository chooses a boundary, authority, or mutation path that conflicts with a mandatory target decision. |
| `MISSING` | The target capability or authority has no actual repository implementation. |
| `DUPLICATED` | More than one active implementation claims the same authoritative fact. Historical migrations or clearly separate evidence records are not counted as active duplication without runtime evidence. |
| `UNRESOLVED` | Static inspection cannot establish the result, most commonly because it depends on the resultant database, runtime wiring, data, queue, or deployment. |

## 4. Target-to-actual conformance matrix

### 4.1 Enterprise contexts and capabilities

| Target context / capability | Target responsibility | Actual repository structure | Status | Classification / finding |
|---|---|---|---|---|
| Organization | Organization, campus, branch, department, reporting scope | `Organization`, `Branch`, `Campus`, `Department`, `CampusAssignment`, structure commands, branch-scope links | Implemented with branch provenance and effective structure queries | `PARTIALLY CONFORMING` — the target's complete cost-center/reporting-context surface and emitted event/consumer chain are not demonstrated |
| People / Identity | Person identity, account identity, actor context | `Identity`, `Person`, `UserAccount`, authentication routes, `Actor` | Person and account authorities are separated | `CONFORMING` for the inspected identity scope |
| Workforce / Employment | Employment, position, assignment, lifecycle, work eligibility | `Hr` owns employment/contracts/leave/scales; `Access` owns `Position` and `PositionAssignment` | Employment exists, but position/assignment storage and commands are in Access | `CONTRADICTORY` — the target assigns position/assignment to Workforce, while Access treats them as authorization topology |
| Authorization | Effective resource/scope/lifecycle/context decision | `AccessResolution`, `AccessDecision`, `StructureDecision`, `ActorBranches`, capability-bearing commands | Server-side command decisions and read-side branch filtering exist | `PARTIALLY CONFORMING` — central decision infrastructure is present, but the full resource relationship model and all read contracts are not proven statically |
| CRM | Prospect, lead, source, interaction, follow-up, conversion trace | `Crm` models, commands, queries, routes, and views | CRM writes its own facts; downstream conversion lineage is recorded | `CONFORMING` for current CRM scope |
| Admissions / Application | Application/admission case and decision, distinct from Applicant participation | `Admissions\\Models\\Applicant`, `AdmissionDecision`; no `Application` model/table or application route | Applicant is carrying the intake/admission case role | `CONTRADICTORY` — the target explicitly separates Applicant participation from Application/case |
| Placement | Attempts, evidence, scoring, recommendation, result; no direct class assignment | Academic Placement sub-tree, placement tables, `DecidePlacement`, signed snapshot builder | Placement produces recommendation and snapshot; no direct enrollment write found | `PARTIALLY CONFORMING` — implementation is under Academic/Placement and snapshot creation is initiated by Placement while the ADR calls the snapshot an Academic fact |
| Student Lifecycle | Student participation and student status lifecycle | `Students\\Models\\Student`, append-only `StudentStatus`, status/hold/transfer commands | Student lifecycle state is owned by Students; admission conversion is orchestrated by Admissions | `PARTIALLY CONFORMING` — a static-phase correction now routes aggregate creation through `Students\\Domain\\StudentAdmissionRegistrar`; runtime boundary/authorization verification remains open |
| Academic Delivery | Program, version, level, offering, class, session, attendance, assessment, progression, completion | `Academic` models, commands, queries, migrations | Broad academic surface exists and writes are mostly centralized | `PARTIALLY CONFORMING` — Enrollment and Scheduling responsibilities are merged into Academic |
| Scheduling / Facilities | Timetable and room/teacher/availability constraint authority | No Scheduling module; `Academic\\Commands\\MaintainClass`, `ClassSession`, rooms, teacher assignments, `TimetableQuery` | One Academic command creates sessions and enforces conflicts | `CONTRADICTORY` — target requires Scheduling as a planning/constraint authority and Academic as delivery authority |
| Enrollment | Enrollment request/decision/membership consuming academic and financial eligibility | `Academic\\Models\\Enrollment`, `Academic\\Commands\\MaintainEnrollment`, `enrollments` | One actual authority, including financial gate evidence and eligibility snapshot reference | `CONTRADICTORY` — target explicitly makes Enrollment its own authority; the current Academic command decides and creates membership |
| Finance | Obligations, settlement, payment, allocation, corrections, subledger/GL and monetary truth | `Finance` models/commands/queries, obligations, payments, allocations, corrections, journals, reconciliation | Strong monetary fact boundary exists | `PARTIALLY CONFORMING` — accounting-event/subledger/GL propagation and complete AR/AP boundaries are not established |
| Payroll | Payroll periods, calculations, payroll result and adjustments | `Payroll\\Models\\PayrollPeriod`, `PayrollCalculation`, `PayrollResult`, `PayrollAdjustment`, commands | Payroll result is distinct from Finance liability | `CONFORMING` for calculation/result ownership |
| Workflow / Work Management | Process instances, tasks, approvals, work items, exceptions, coordination, escalation | No Workflow module, process/work-item tables, consumer, or workspace composition. `Resources\\Models\\WorkOrder` is a facilities work-order fact | Domain-specific staged approvals exist in several modules | `MISSING` — no target Workflow authority; resource WorkOrder must not be treated as the missing generic WorkItem |
| Documents | Versioned documentary evidence and immutable rendered artifacts | `Documents`, document/version/verification/retention models and commands | Dedicated Documents authority exists | `PARTIALLY CONFORMING` — source snapshot/render/delivery integration is not demonstrated for all document paths |
| Communication | Communication intent, delivery state, history | `Communication\\Models\\Message`, `SendMessage`, communication routes/view | Dedicated message/delivery record exists | `PARTIALLY CONFORMING` — no separate Notification event/artifact boundary and no durable event-to-message consumer found |
| Notifications | Delivery artifact informing an actor, separate from task/approval/exception | No Notification module/table/query/route/view found | Not represented as a separate capability | `MISSING` |
| Search | Search projection/index and authorization-filtered discovery | No Search module, index/projection, route, or query found | None | `MISSING` |
| Events / Outbox | Transactional domain event, relay, integration event, idempotent consumer/receipt | `Outbox\\DomainEvent`, `TransactionalEventRecorder`, `domain_events`; separate `Integrations\\IntegrationDelivery` processing | Append-only event recording and endpoint delivery machinery exist independently | `PARTIALLY CONFORMING` — the connecting relay, consumer receipts, event catalog, and required envelope fields are absent statically |
| Reporting / Analytics | Governed projections and management views, never source truth | `Reporting`, `MetricCatalog`, 11 calculators, projections, reconciliations, report runs, dashboards | Source ownership is documented and Finance balance calculator delegates to Finance | `PARTIALLY CONFORMING` — no event-fed projection path and management/workspace decision surface is missing |
| Audit / Forensics | Immutable evidence of actions and denied attempts | `AuditEvent`, `AuditRecorder`, attempted/rejected operation recorders, audit view | Audit is distinct from domain events | `PARTIALLY CONFORMING` — event envelope and durable consumer traceability are incomplete |
| Employee Workspace | First-class authorized work discovery/orchestration, never a fact owner | No Workspace module, controller, route, view, API contract, or query | Home page is a static module-link console | `MISSING` |
| Management Decision Support | Governed KPIs, staffing/capacity, risk, exceptions, pending decisions, command links | Reporting dashboards and report runs exist; no management workspace or exception/work queue | Analytical view exists without full work/decision composition | `PARTIALLY CONFORMING` |
| Calendar / Time | Shared authoritative period/date context | `Calendar\\CalendarAuthority` and academic/financial/payroll periods | Calendar authority is separate and explicit | `PARTIALLY CONFORMING` — period ownership is split by business period type as intended, but cross-context event/period contracts are not demonstrated |

### 4.2 Required business-fact authority matrix

| Business fact | Target authority | Actual model/table | Actual command/query path | Duplicate / cross-module observation | Status |
|---|---|---|---|---|---|
| Enrollment | Enrollment | `Academic\\Models\\Enrollment` / `enrollments` | `Academic\\Commands\\MaintainEnrollment`; roster/history queries | Admissions creates students but does not create enrollment; Reporting reads; no duplicate enrollment table found | `CONTRADICTORY` |
| Academic eligibility | Academic-owned signed snapshot after Placement recommendation | `Academic\\Placement\\Models\\AcademicEligibilitySnapshot` / `academic_eligibility_snapshots` | `Placement\\Commands\\DecidePlacement` materializes; `AcademicEligibilitySnapshotQuery` verifies/reads; Admissions and Enrollment consume references | One snapshot table; creation is placed under Placement while ADR says Academic authority | `PARTIALLY CONFORMING` |
| Class membership | Enrollment decision/membership | `enrollments.class_id` | `MaintainEnrollment` creates/transitions membership; class roster reads | No separate `class_memberships` table; this is acceptable only if Enrollment remains the ownership boundary | `PARTIALLY CONFORMING` |
| Session | Academic Session / delivery fact, with Scheduling creating the schedule decision | `Academic\\Models\\ClassSession` / `class_sessions` | `MaintainClass::scheduleSession`; `TimetableQuery`; attendance and Payroll read session | No duplicate Session writer found; scheduling and delivery creation are merged | `PARTIALLY CONFORMING` |
| Scheduling | Scheduling planning/constraint decision | No Scheduling model/module; `MaintainClass` and `ClassSession` are actual path | `MaintainClass::scheduleSession`, room/section/teacher checks and DB triggers | No duplicate, but target authority is absent and embedded in Academic | `CONTRADICTORY` |
| Payroll result | Payroll | `Payroll\\Models\\PayrollResult` / `payroll_results` | `CalculatePayroll`, `ApprovePayrollResult` | Finance reads approved result; Finance liability is a separate recognized fact, not a duplicate | `CONFORMING` |
| Payroll liability | Finance | `Finance\\Models\\PayrollLiabilityFact` / `payroll_liability_facts` | `Finance\\Commands\\RecognizePayrollLiability`; reporting reads `payroll_liability_facts` | Unique source recognition and DB source guard; no Payroll monetary-liability writer found | `CONFORMING` |
| Employment settlement | Finance recorded settlement; Payroll proposal is workflow evidence | `Finance\\Models\\EmploymentSettlement` / `employment_settlements`; `Payroll\\Models\\SettlementProposal` / `settlement_proposals` | Payroll proposes; Finance `MaintainEmploymentSettlement` records immutable fact | Historical `final_settlements` migration is removed by `000143`; resultant schema and upgrade data are unresolved | `PARTIALLY CONFORMING` |
| Financial obligation | Finance | `Finance\\Models\\Obligation`, `ObligationLine` / `obligations`, `obligation_lines` | `PostObligation`; `FinancialBalanceQuery` | No Admissions/Academic obligation writer found; gate queries Finance | `CONFORMING` |
| Payment | Finance | `Finance\\Models\\Payment` / `payments` | `RecordPayment`; Finance API/web routes | Payment is not treated as accounting entry; no competing receipt authority found | `CONFORMING` |
| Allocation | Finance | `PaymentAllocation` / `payment_allocations`; `FundAllocation` / `fund_allocations` | `AllocatePayment`, `AllocateFunds`; Finance balance query | Payment allocation and funding allocation are distinct subtypes, not duplicate payment allocation authorities | `CONFORMING` |
| Financial correction | Finance | `FinancialCorrection` / `financial_corrections` | `MaintainFinancialCorrection` propose/approve and source-linked correction paths | Corrections append or compensate; no report/controller correction writer found | `CONFORMING` |
| Work item / task / approval / exception | Workflow | No generic model/table/query; resource `WorkOrder` only | Domain-specific commands stage approvals in their own fact tables | Multiple domain approvals are not one generic authority; no Workflow coordination exists | `MISSING` |
| Employee Workspace | Workspace projection/orchestration | None | None | Home/reporting pages are not a workspace and cannot supply its missing work concepts | `MISSING` |

## 5. Authority and boundary findings

### 5.1 Authorities that are clear and should be preserved

The following actual authorities agree with the target's one-fact/one-owner law for the inspected scope:

- `Person` owns identity; `UserAccount` owns authentication account state.
- HR owns `Employment`, contracts, contract versions, leave, and compensation scale/rule records.
- Payroll owns payroll period process state, calculations, results, adjustments, and settlement proposals as evidence.
- Finance owns obligations, payments, payment/fund allocations, corrections, journals, reconciliations, the signed payroll liability fact, and recorded employment settlements.
- Student Lifecycle owns student status/history, holds, transfer history, and communication preference records. The static-phase registrar correction makes it the owner of student aggregate creation as well.
- Academic owns the academic catalog, class/session/assessment/progression records in the current implementation, subject to the Scheduling and Enrollment boundary defects below.
- Reporting owns projections and report/dashboards, not the underlying facts. The Finance outstanding-balance calculator delegates to `Finance\\Queries\\FinancialBalanceQuery`, which is the correct direction.
- Audit owns evidence of actions and attempted/denied operations; it is not used as the business fact owner.

### 5.2 Boundary contradictions

1. **Academic vs Enrollment:** `MaintainEnrollment` directly creates and transitions `Academic\\Models\\Enrollment`, performs the academic eligibility reference, calls Finance's financial gate query, freezes gate evidence, and records enrollment lifecycle. This is a coherent single writer, but it violates the target's explicit X5 decision that Enrollment owns the decision after consuming Academic and Finance inputs.
2. **Academic vs Scheduling:** `MaintainClass` handles class definition, class lifecycle, sessions, sections, teacher assignment, teacher skill assignment, room availability, timetable conflicts, and session scheduling. The database names the fact `class_sessions`, but no Scheduling boundary exists.
3. **Workforce vs Access:** `Position` and `PositionAssignment` are in Access and are used to derive authority. The target says Workforce owns position and assignment while Authorization consumes them. The repository therefore makes an authorization-topology module a partial Workforce authority.
4. **Admissions vs Student Lifecycle:** Before this audit correction, Admissions directly wrote Student and StudentStatus models. The write was moved to `Students\\Domain\\StudentAdmissionRegistrar`; Admissions still owns the authorized conversion orchestration and reads Student for duplicate checks. This removes the direct aggregate write without inventing a second compatibility path.
5. **Admissions/Application:** `Applicant` is the available admissions aggregate, but the target requires a distinct Application/case concept. No safe static rename or split was attempted because it would change persistence and transport semantics.
6. **Academic Eligibility:** The prior ADR explicitly declares the signed snapshot an Academic fact, while `Placement\\Commands\\DecidePlacement` creates it atomically with placement release. This is a defensible producer/consumer arrangement only if the implementation boundary is documented as an Academic eligibility port. It is not yet represented as a separate owner/application boundary.

### 5.3 No active duplicate was established for the named monetary and academic facts

Static inventory found no active second writer for Enrollment, PayrollResult, PayrollLiabilityFact, Obligation, Payment, PaymentAllocation, or FinancialCorrection. `SettlementProposal` is intentionally proposal evidence and `EmploymentSettlement` is the Finance fact. `AcademicEligibilitySnapshot` is immutable evidence and not a second live eligibility rule. `WorkOrder` is a resource/facilities work fact, not a generic Workflow WorkItem. These distinctions avoid incorrectly classifying separate evidence or subtypes as duplicate authorities.

## 6. Database and migration findings

### 6.1 Present schema authorities

Migration inventory includes the expected current fact tables, including:

- `students`, `admission_decisions`, `enrollments`, `classes`, `class_sections`, `class_sessions`, `attendance_facts`, assessments, progression, completion, certificates, and transcripts;
- `payroll_periods`, `payroll_calculations`, `payroll_results`, `payroll_adjustments`, `payroll_clearances`, and `settlement_proposals`;
- Finance `financial_periods`, `obligations`, `obligation_lines`, `payments`, `payment_allocations`, `refunds`, `financial_corrections`, journals, reconciliations, `employment_settlements`, and `payroll_liability_facts`;
- `academic_eligibility_snapshots` with signed payload, digest, key version, immutable trigger, and downstream references;
- branch provenance columns, `branch_scope_links`, idempotency, audit events, and `domain_events`.

The schema uses PostgreSQL-oriented checks, foreign keys, partial unique indexes, row/history triggers, append-only guards, and source-link guards in the later migrations. That is strong static evidence of the intended integrity model, not deployment evidence.

### 6.2 Missing target schema

No current migration creates tables or projections for:

- workflow process instances;
- generic tasks;
- generic approvals as actionable work items;
- generic exceptions;
- notification artifacts/receipts;
- search indexes/projections;
- employee workspace state/projection;
- application cases distinct from applicant participation;
- event-consumer receipts or domain-event relay cursor/state;
- a domain-event-to-integration-delivery projection.

`work_orders` is a Resource-owned facilities work-order table. `sessions` is not the Academic Session authority; the Academic fact is `class_sessions`. The same table name is used by the framework/application session infrastructure and should not be confused with delivery sessions.

### 6.3 Historical and obsolete migrations

The repository has intentionally retired competing architecture in migration history:

- `000096` drops the legacy `compensation_components` table and its trigger; active compensation is Contract Version plus Compensation Rule.
- `000097` drops `work_bases`; payroll volume is derived from academic teaching-delivery evidence rather than manual work bases.
- `000056` historically creates Payroll `final_settlements`; `000142` creates Finance-owned `employment_settlements`, and `000143` drops the competing runtime table and makes the consolidation one-way.
- The Payroll `FinalSettlement` model is deleted from the active tree; no active application reference to `final_settlements` was found.

The old create definitions and reversible `down()` bodies are migration history, not by themselves active duplicate tables. A migration history scan cannot establish whether a clean install, upgrade with data, rollback, or partially applied deployment produces the intended result. Those are runtime-only questions in §14.

No compatibility table or fallback writer was added in this audit. The `Payroll\\Domain\\SettlementProposalApproval` port is an ownership correction: it lets Payroll mutate its own proposal state while Finance records the Finance fact; it does not preserve the retired Payroll settlement authority.

## 7. API and web route findings

### 7.1 Actual transport shape

- `routes/web.php` exposes a broad server-rendered employee console through the `employee` middleware, including organization, identity, students/admissions, CRM, Academic, Placement, HR, Resources/Library, Finance, Communication, Payroll, Reporting, Documents, Access, Privacy, Audit, and printing.
- `routes/api.php` exposes JSON controllers for Students, Academic sessions/attendance, Identity, Finance, Payroll, Placement, and CRM, plus `/me`.
- Both transports delegate to shared command/query authorities for the covered operations; controller comments and inspected methods keep business decisions out of route code.
- Finance, Payroll liability recognition, Academic sessions, placement, CRM, and student operations have both web/API portions, but not complete route parity.

### 7.2 Mismatches and stale contracts

1. There is no Workspace route in either transport.
2. There is no Workflow, Task, WorkItem, Exception, Notification, or Search route.
3. The JSON API has no corresponding surface for many web modules, including HR, Resources, Communication, Reporting, Documents, Access, Privacy, Organization, Audit, printing, and much of Academic setup/lifecycle. The target/reconciliation requirement for API/web parity is therefore not met.
4. The API has no visible version prefix or generated typed contract boundary. Its route names and method aliases differ from the web surface, and error/record-resolution behavior still needs runtime comparison.
5. Controllers often call `findOrFail` before entering a command. Static code therefore leaves open whether an unauthorized caller can distinguish record existence from a command denial; this requires HTTP/security verification and, for sensitive resources, may require lookup authorization before failure behavior.
6. Existing architecture reconciliation documentation selects a React/TypeScript feature frontend; the actual API is session-authenticated Laravel JSON and the actual web product is Blade. The API is not a demonstrated React contract merely because it is JSON.

The absence of a Workspace route is a capability gap, not a reason to add a dashboard alias. A Workspace route must compose authorized work and link to canonical commands without becoming a new write authority.

## 8. Frontend findings

The actual frontend inventory is:

- Blade layout and module views under `resources/views/`;
- HTML forms and server-rendered pages for Academic, Finance, HR, Payroll, Students, CRM, Reporting, Placement, Documents, Communication, Access, Privacy, Resources, Organization, Identity, Audit, and print artifacts;
- no `package.json`, React feature tree, TypeScript source tree, Vite application, or browser-side workspace application found in the repository root/frontend inventory.

`resources/views/home.blade.php` is a module navigation page. `resources/views/reporting/index.blade.php` is a reporting/dashboard page. Neither is an Employee Workspace: neither composes effective employee positions, assignments, capability/scope, tasks, approvals, deadlines, notifications, authoritative context, and exceptions with direct canonical action links.

The Blade console is not inherently disallowed by the target graph, but it contradicts the selected React/TypeScript frontend decision in the existing reconciliation documents. Converting the entire surface or adding a thin React shell without typed contracts, workspace data ownership, accessibility, and runtime validation would not be a safe static correction.

## 9. Events and Outbox findings

### 9.1 What exists

`AuditRecorder` records a successful operation and calls `TransactionalEventRecorder` inside the caller's transaction. `DomainEvent` is append-only and linked to `AuditEvent` through `domain_events.audit_event_id`. The schema provides event type/version, aggregate identity, correlation ID, payload digest, occurrence time, and append-only protection. Denied attempts remain audit evidence rather than successful domain events.

`Integrations\\IntegrationDelivery`, `DispatchDelivery`, `DeliveryProcessor`, retry/backoff, dead-letter state, and `ProcessDeliveries` provide endpoint-specific delivery behavior. That is useful infrastructure and correctly distinct from the domain event log.

### 9.2 Missing link and envelope

The static repository does not demonstrate:

```text
DomainEvent
 → relay/cursor
 → IntegrationDelivery projection
 → endpoint delivery
```

`ProcessDeliveries` queries `integration_deliveries` directly. No consumer/projector/relay was found that reads `domain_events`, creates delivery rows, records consumer receipts, or makes internal reporting/workflow/communication/search consumers idempotent. The existence of both tables does not establish an outbox pipeline.

The target event envelope requires `message_id`, `aggregate_type`, `aggregate_id`, `aggregate_version`, `event_type`, `event_version`, `occurred_at`, `recorded_at`, `organization_id`, `operational_context_id`, `actor_id`, `correlation_id`, `causation_id`, and payload. The actual table lacks explicit aggregate version, organization/context identifiers, causation ID, and recorded-at semantics distinct from generic timestamps. These may be derivable in some commands, but the envelope contract is not established.

Reporting calculators currently read authoritative tables synchronously and write governed projections. That is not a second authority, but it is not proof of an event-fed rebuildable projection path. Outbox replay, consumer idempotency, ordering, and failure behavior remain unresolved.

## 10. Reporting and management findings

The Reporting module is materially better than a free-form dashboard implementation:

- `MetricCatalog` registers metric owner, period authority, scopes, and calculator;
- `MetricProjection`, `MetricVersion`, `MetricDefinition`, `MetricReconciliation`, and `ReportRun` provide governed derived-data records;
- Finance `OutstandingBalanceCalculator` delegates to `FinancialBalanceQuery`, preserving Finance as balance authority;
- `PayrollTotalCalculator` reads Finance-recognized `payroll_liability_facts`, not Payroll result totals;
- academic, placement, CRM, attendance, and fund metrics have explicit calculator paths.

The remaining issues are:

- the event/outbox projector path is not present, so freshness/replay/rebuild behavior is not connected to the target event graph;
- there is no Workspace or management decision workspace that combines projections with pending decisions, exceptions, deadlines, staffing/capacity, and canonical command links;
- AR/AP and complete accounting/subledger source lineage are not represented as full target contexts;
- historical reporting checkpoint documents describe older metric counts and settlement architecture. They have historical notices in the worktree, but the new audit is the current conformance comparison.

Status: `PARTIALLY CONFORMING`, not a reporting authority duplication.

## 11. Authorization and security findings

### 11.1 Conforming mechanisms

- `AccessDecision` is a server-side port with deny-by-default semantics.
- `AccessResolution` resolves effective permissions from positions, assignments, policies, grants, delegations, lifecycle, dates, and scope.
- Commands define capability constants and perform authorization at the mutation boundary.
- `ActorBranches` is a read-side visibility query derived from the canonical grant/delegation/organization topology and treats null provenance as unknown, not wildcard.
- Sensitive operations include separation-of-duties and beneficiary checks in application logic and PostgreSQL guards for settlement, contract, grant, correction, and other facts.
- Idempotency keys and row locks are widely used in critical commands.

### 11.2 Gaps

- Position/assignment authority is structurally in Access instead of Workforce.
- Many web/API read surfaces require parity and data-dependent checks; static route presence is not proof of branch isolation.
- A generic Workspace cannot safely be built until its query uses authorization-filtered work discovery and every action re-enters the canonical command boundary.
- The target's resource/relationship-centric authorization model is only partially represented; static capability strings and branch scopes are not equivalent to a fully typed resource relationship graph.
- Domain event and workspace envelopes do not yet carry the full organization/operational context required for authorization-filtered downstream consumption.

Status: `PARTIALLY CONFORMING`.

## 12. Lifecycle, provenance, branch, and concurrency findings

### 12.1 Lifecycle and concurrency strengths

The repository statically shows:

- explicit lifecycle classes for Enrollment, Class, Section, Room, Assessment, Progression, Admission, Student, Finance, Payroll, HR, Documents, Resources, and other domains;
- append-only or immutable records for eligibility snapshots, academic delivery evidence, payroll results, Finance settlements/liabilities, corrections, audit, and domain events;
- `lockForUpdate()` in high-risk state transitions and allocation/settlement paths;
- idempotency execution around critical command operations;
- PostgreSQL checks/triggers for period closure, session conflict, capacity, immutable timetable identity, settlement source matching, liability source matching, and correction limits.

These properties do not cure the context-boundary defects: an invariant enforced by a correct Academic command is still an Academic-owned Enrollment decision when the target requires Enrollment ownership.

### 12.2 Remaining lifecycle gaps

- No Workflow process state, work-item assignment, SLA/deadline, escalation, exception, or notification lifecycle exists.
- No aggregate version/optimistic concurrency field or HTTP version contract was statically found for the general command surface; locking is the dominant mechanism.
- No durable event-consumer duplicate receipt or replay state was found.
- Settlement proposal approval is a Payroll-owned transition coupled to Finance fact creation. The static correction now routes the proposal mutation through a Payroll owner port, but the full event/approval/workflow representation remains absent.
- Admissions Application and Enrollment Request are not distinct persisted cases as required by the target graph.

### 12.3 Branch and provenance

Present controls include:

- `originating_branch_id` and `current_home_branch_id` on key student/enrollment/Finance/contract records;
- immutable originating branch guards;
- class branch provenance and class-based timetable reads, including room-less sessions;
- `branch_scope_links` for explicit affected scope;
- branch-aware access and null-provenance fail-closed reads;
- Finance settlement and Payroll liability evidence checks.

The target provenance envelope is still incomplete. Domain events and audit rows do not explicitly carry organization and operational context fields. `class_sessions` derives branch from the class rather than carrying a frozen branch field. Payroll results and some source facts rely on related employment/organization context rather than a uniform envelope. Existing provenance columns are nullable to avoid fabricated history; whether all new writes populate them and whether old rows are safely handled is runtime/data dependent. Finance settlement uses the employee's home branch designation; whether that is the correct historical operational settlement context requires policy and data verification.

Status: `PARTIALLY CONFORMING` and `UNRESOLVED` for deployed-data coverage.

## 13. Finance conformance findings

### 13.1 Strong alignment

- Finance owns `Obligation` and `ObligationLine`, `Payment`, `PaymentAllocation`, `Refund`, credits, discounts, installment plans, gate exceptions, funding allocations, corrections, journals, and reconciliation.
- Payment is not treated as an accounting entry.
- Enrollment financial eligibility is derived through `FinancialGateQuery` from Finance facts; Academic stores the signed/frozen gate evidence rather than becoming Finance.
- Payroll calculates `PayrollResult`; Finance recognizes the approved source into unique `PayrollLiabilityFact` rows with exact source identity and amount checks.
- Employment settlement is a Finance immutable fact linked to a matching Payroll proposal, HR termination, clearances, independent actors, beneficiary separation, and branch evidence.
- Financial correction paths are source-linked and append/compensate rather than silently mutating historical money.
- Reporting's financial balance path delegates back to Finance.

### 13.2 Incomplete target flow

The target Finance graph is:

```text
source fact → obligation → AR/AP → settlement → allocation → accounting event → subledger → GL → reconciliation/close → reporting
```

The repository contains the endpoints of much of this graph, but static inspection does not establish:

- distinct AR/AP authority and lifecycle contracts;
- automatic accounting-event creation from every approved obligation/payment/allocation/payroll liability/correction;
- a complete subledger boundary separate from manually posted journals;
- controlled linkage between Finance liability recognition and the journal/GL source event;
- settlement/disbursement flow beyond the recorded employment-settlement fact.

`PostJournal` and journal tables are not enough to prove the target accounting flow. The correct classification is `PARTIALLY CONFORMING`, not `DUPLICATED` and not a reason to make Payroll write Finance.

## 14. Employee Workspace findings

The target Workspace is explicitly a first-class work environment, not a generic dashboard. It must compose, at request time or through rebuildable authorized projections:

- effective employee identity and employment eligibility;
- all positions and assignments;
- capabilities, organizational/branch scope, lifecycle state;
- separate tasks, approvals, work items, exceptions, deadlines, notifications, and recent work;
- authoritative context and direct canonical workflow links;
- management-specific decision work where authorized;
- freshness/failure state where projections are involved.

The repository has none of the required Workspace surface. It has:

- a static Home module-link page;
- separate module pages;
- Reporting dashboards;
- resource-specific WorkOrders;
- domain-local approval/proposal queues;
- no unified authorized work-discovery query.

It is not safe to add a thin `/workspace` page that queries unrelated tables and calls that conformance. Until Workflow, Notification, Search/projection, and route contracts are designed against the target, Workspace remains `MISSING`. This is a required implementation gap, not a justification for a new business authority or a generic role dashboard.

## 15. Deleted obsolete architecture and dispositions

The following obsolete architecture is deleted from the active application path or explicitly retired by ordered migrations:

| Retired architecture | Evidence | Disposition |
|---|---|---|
| Flat compensation components | No active model/command reference; `000096` drops table and trigger; contract version/rules are the active path | Retired; do not restore as fallback |
| Manual/academic `work_bases` volume | No active model/command reference; `000097` drops table and trigger; `TeachingDeliveryFact` is the evidence path | Retired; do not add compatibility writes |
| Payroll `FinalSettlement` fact | Model deleted; `000143` drops `final_settlements`; Finance `employment_settlements` is the recorded fact | Retired; `SettlementProposal` remains evidence only |
| Report-owned Finance balance | Reporting calculator delegates to `Finance\\Queries\\FinancialBalanceQuery` | Correctly removed as a competing balance authority |
| Academic session branch inference from room only | Class branch provenance and class-based timetable filtering are now the intended path | Static correction present; runtime isolation still required |
| Admissions direct Student aggregate write | `Students\\Domain\\StudentAdmissionRegistrar` now owns the Student and initial status inserts | Static boundary correction present; runtime behavior unverified |
| Finance direct Payroll proposal table update | `Payroll\\Domain\\SettlementProposalApproval` now owns proposal lifecycle mutation; Finance records its own fact | Static boundary correction present; runtime/DI/trigger behavior unverified |

Historical documents and migration `down()` definitions are not deleted merely to hide history. They must remain clearly marked as historical and must not be used as active authority or compatibility fallback.

## 16. Implemented safe static corrections in this phase

Two contradictions had a behavior-preserving, ownership-preserving static correction and were changed:

1. **Student aggregate ownership:** added `app/Modules/Students/Domain/StudentAdmissionRegistrar.php`. Admissions retains admission authorization, applicant locking, provenance validation, and conversion orchestration; the Students boundary now performs Student and initial StudentStatus inserts. This removes Admissions' direct write to Student Lifecycle tables without adding a duplicate path.
2. **Settlement proposal ownership:** added `app/Modules/Payroll/Domain/SettlementProposalApproval.php`. Finance still validates and records the authoritative Finance settlement, but proposal lifecycle closure is requested through a Payroll-owned port instead of Finance directly updating `settlement_proposals`. Finance remains the monetary authority; Payroll remains proposal/process evidence authority.

Since the original audit baseline, the static convergence slice has added the explicit contracts and guarded boundaries that were previously missing: versioned `/api/v1`, a React/TypeScript workspace root, branch-safe Search, Employee/Management Workspace queries, Work Management coordination (`workflow_instances`, `work_items`, append-only history), recipient/read Notifications, an allowlisted domain-event relay with consumer receipts, explicit envelope context, workflow/notification consumers, projection invalidations, and scheduled `outbox.relay` registration. These additions do not remove the original gaps that remain: concrete materialized projectors/rebuild commands, notification/read-state authority, automatic domain-to-workflow adapters, Scheduling/Enrollment boundary extraction, full React migration, and runtime verification. Historical findings above remain the pre-slice baseline; the current convergence status is maintained in `2026-09-05-fourth-architecture-convergence.md`.

## 17. Unresolved issues

The following require follow-up implementation or architectural closure; they are not resolved by this audit:

1. Establish the target Enrollment authority boundary without duplicating `enrollments` or retaining Academic as a hidden writer.
2. Establish Scheduling as a planning/constraint boundary while preserving Academic Session delivery facts and Payroll evidence lineage.
3. Decide whether to split `Position`/`PositionAssignment` from Access into Workforce or formally amend the target boundary with a governing ADR.
4. Add a distinct Application/case authority if the target's Applicant/Application distinction is required for current TOEFL House.
5. Design and implement separate Workflow process state, Task, Approval, WorkItem, Exception, deadline/SLA, escalation, and assignment contracts. Do not collapse them into one generic status/task table.
6. Implement Notification and Search as projections/communication artifacts, never source authorities.
7. Implement the transactional outbox relay, event catalog, full event envelope, consumer receipts, idempotent projector behavior, replay, ordering, retry, and dead-letter semantics.
8. Define the Finance accounting-event/subledger/GL integration and AR/AP scope; do not infer it from the existence of journals.
9. Resolve complete API/web parity, API versioning/typed contracts, authorization-before-resource-disclosure behavior, and frontend direction.
10. Implement the first-class Employee Workspace only after its authorized work-discovery input contracts exist.
11. Complete migration clean-install/upgrade/reconciliation strategy for the one-way settlement consolidation and all nullable provenance additions.
12. Verify every domain event has the required organization/operational context and causal lineage, or explicitly document a safe derivation contract.
13. Resolve management decision-support and exception visibility without allowing dashboards/reports/workspace to mutate source facts.

## 18. Runtime-only questions deliberately not answered

These questions cannot be answered from static inspection and were intentionally not tested:

- Does the complete ordered migration set build the intended live PostgreSQL schema from empty state?
- Does an upgrade with historical `final_settlements` data reconcile safely before `000143`?
- Do `down()` paths, trigger replacement order, foreign keys, and one-way migrations behave as intended?
- Does Laravel resolve the new registrar and settlement-port constructor dependencies in every controller, test, and seeder path?
- Do failed transactions roll back facts, audit rows, and domain events together?
- Do row locks, unique indexes, trigger order, and idempotency behavior hold under concurrent enrollment, allocation, correction, settlement, payroll recognition, and timetable requests?
- Are branch visibility, null provenance, transferred students, terminated employees, expired delegations, revocations, and organization-wide grants isolated correctly with real data?
- Do API and web responses have equivalent validation, denial, error codes, idempotency, and record-disclosure behavior?
- Do queue workers, relay retries, event replays, consumer receipts, endpoint delivery, dead letters, and out-of-order events behave safely?
- Are Reporting projections fresh, rebuildable, reconciled, and traceable to source facts after failure or replay?
- Does the actual browser interface meet accessibility, mobile/responsive, workflow-efficiency, freshness, and management decision requirements?
- Does any real deployment contain legacy rows or provenance values that invalidate the intended static authority model?

## 19. Scope classification for next work

| Category | Findings |
|---|---|
| Required for Current TOEFL House | Preserve the single Finance/Payroll boundary; preserve append-only corrections and provenance; complete current Enrollment and Scheduling authority decisions; provide the required Employee Workspace if the graph is the current product target; close direct cross-module writes; make API/web security behavior explicit; implement event delivery for any workflow/reporting/communication capability actually relied upon |
| Architecturally Required | Workflow coordination with separate work concepts; transactional outbox relay and idempotent consumers; Search and Notification projection boundaries; full event/provenance envelope; Finance accounting-event/subledger/GL contract; authorized Workspace orchestration; migration/reconciliation plan |
| Future / Policy-Gated | Distinct AR/AP or supplier scope beyond current student/employee flows; Alumni; advanced management workspaces; mobile/React migration details; additional integrations and notification channels; broader cost-center/organizational reporting policy |
| Not Required merely to obtain conformance | One microservice per logical context; event sourcing every fact; a second database; a generic role dashboard; a compatibility copy of retired settlement/compensation tables; a report-owned balance; UI-only authorization |
| Unjustified Complexity | Generic task/approval/status table that conflates separate target concepts; rebuilding authoritative facts in Workspace/Search/Reporting; adding a React façade without contract and accessibility work; restoring `final_settlements`, `work_bases`, or flat compensation as fallback; making Payroll write Finance or Finance write Academic/Enrollment directly |

## 20. Precise final verdict

**NOT CONFORMING.**

This verdict is based on mandatory target contradictions and missing capabilities, not on a claim that the repository is generally poor or unimplemented. The repository has several `CONFORMING` authorities, especially Finance monetary facts, Payroll results, Finance payroll liability recognition, payment/allocation/correction ownership, audit separation, and server-side command authorization. It also has meaningful `PARTIALLY CONFORMING` infrastructure for provenance, reporting, documents, communication, and events.

It cannot be classified as `CONFORMING` or merely `CONDITIONALLY CONFORMING` because the target's Enrollment and Scheduling boundaries are merged into Academic, Workflow/Work Management, Search, Notifications, and Employee Workspace are missing, the event/outbox propagation chain is incomplete, the actual Blade frontend contradicts the selected React/TypeScript product decision, and Finance's complete accounting flow is not established. Static implementation corrections were limited to the two clear direct cross-module writes described above. No production readiness, runtime correctness, deployed-schema correctness, browser usability, queue safety, or integration reliability is claimed.

---

## HISTORICAL SOURCE: `architecture/review/2026-09-06-academic-class-api-convergence.md`

# Academic Classes API and authority convergence — 2026-09-06

## Scope

This is a static implementation review for the canonical Academic authority. It does not claim runtime, PostgreSQL, migration, build, browser, or production readiness. The interactive Academic Classes workspace remains the React projection at `/academic`; the versioned transport is `/api/v1/academic`.

## Canonical command matrix

| Fact/workflow | Canonical command | Versioned API boundary | Read projection |
|---|---|---|---|
| Program, version, level, period | `MaintainAcademicStructure` | `/programs`, `/programs/{id}/versions`, `/levels`, `/periods` | `program_versions`, `levels`, `periods` |
| Branch availability and offering | `MaintainAcademicStructure`, `ManageAcademicOffering` | `/availabilities`, `/offerings`, offering/availability transitions and resize | `availabilities`, `offerings` |
| Class, section, teacher, room, session | `MaintainClass`, `MaintainRoom` | `/classes`, `/sections`, `/teacher-assignments`, `/rooms`, `/sessions` | `classes`, `sessions`, `teachers`, `rooms` |
| Enrollment, capacity, waitlist | `MaintainEnrollment`, `ManageClassWaitlist` | `/enrollments`, enrollment transitions, `/waitlist`, waitlist transitions | `enrollments`, `waitlist`, server-derived seat counts |
| Attendance | `RecordAttendance` | `/sessions/{id}/attendance`, `/attendance/{id}/correct` | append-only `attendance` facts |
| Assessment and correction | `ManageAssessmentResult` | `/attempts`, `/results`, `/corrections` | `attempts`, `results` |
| Progression | `DecideProgression` | `/progressions` and progression transitions | `progressions` |
| Graduation and certificate | `DecideGraduation` | `/graduations`, graduation transitions, certificate issuance | `graduations` |
| Official transcript | `IssueTranscript` | `/transcripts` | immutable `transcripts` metadata and hash |
| Academic appeal | `ManageAcademicAppeal` | `/appeals`, assignment, appeal transitions | `appeals` |
| Placement catalog/profile and report | `MaintainPlacementCatalog`, `ManagePlacementProfile`, `RegisterDocument` | `/api/v1/placement` catalog, attempt, profile, report, and appeal routes | placement tests, profiles, Documents versions |

Every mutation endpoint is a transport adapter. It validates shape, resolves the requested aggregate, supplies the actor and idempotency key, and delegates policy, lifecycle, scope, provenance, audit, and outbox behavior to the command. No API or React code calculates capacity, financial standing, eligibility, progression, grading, or appeal redress.

## Static authority corrections

- Legacy Academic operations for setup, rooms, dated teacher assignment history, enrollment exit/transfer, waitlist expiry, graduation, transcript, offering/availability, and appeals now have equivalent versioned API routes. They do not create a parallel write path.
- The workspace exposes server-provided branch capability maps and lifecycle state. Class rows may be visible to any relevant Academic read scope, but sessions, seats, students, outcomes, offerings, and records are independently filtered by their owning capability scope. The Records tab only renders graduation/certificate/transcript/appeal controls when the projection says the actor can perform that action; required appeal remediation remains on the contested subject authority.
- Enrollment activation and transfer now lock class capacity rows before enrollment rows. Waitlist offer/promotion also lock class rows before waitlist rows and the nested enrollment command. Assessment correction approval locks the original result before the correction row. These orders align class-capacity and aggregate lifecycle operations and reduce lock-cycle risk.
- The consolidated database authority migration now guards graduation signer/lifecycle identity, appeal lifecycle/decision evidence, nullable pre-Student placement appeals, one open appeal per subject, class/offering/period references, live-seat capacity, waitlist references, session identity/scope, attendance identity, assessment evidence and signer transitions, progression transitions, and teacher assignment temporal overlap.
- Transcript rows remain immutable and are rendered from their frozen payload/hash; Finance remains the only monetary authority for enrollment gates and certificate visibility.
- Placement catalog/profile/report operations now have equivalent JSON adapters; placement remains owned by its dedicated commands and its pre-Student appeal subject is projected by placement-profile provenance rather than a fabricated student scope.
- Progression appeal resolution is a staged two-actor flow: the independent reviewer is recorded in `appeal_reviewed_by` when a decision enters `appealed`, and only a distinct authenticated approver can supersede it. Client input cannot nominate or impersonate the prior reviewer.

## Remaining static limit

PostgreSQL trigger ordering and syntax, pre-existing historical rows, migration rollback behavior, dependency injection, route execution, capability fixtures, concurrent transactions, queue delivery, projection freshness, TypeScript compilation, accessibility behavior, and browser behavior remain unexecuted by instruction. No production-readiness conclusion follows from this static review.

---

## HISTORICAL SOURCE: `architecture/review/academic-redteam-2026-09-05.md`

# Academic System — Independent Red-Team Architecture Review

**Date:** 2026-09-05 · **Reviewer:** Arena.ai Agent Mode (independent pass; no implementation performed)
**Scope:** the entire Academic surface — domain model, migrations, commands, queries, authorization, audit/history, console UI, print surface, integrations, tests, real-world operability.
**Method:** evidence-first. Every finding below cites repository paths. Counter-checks were run against the code (not the docs), and several first-impression suspicions were **retracted** after the code proved them wrong — those retractions are recorded so nobody re-litigates them.

---

## 1. Verdict: CONDITIONAL GO — do not put this live until 3 items are closed

The architecture is **genuinely production-grade at its core**: lifecycle machines with DB check constraints, row-locked capacity counting, conflicting-payload idempotency, append-only audit with denial logging, and terminal-with-history semantics are all real, tested (761 tests / 6117 assertions), and survived adversarial probing. This is not a rewrite situation.

But there are **two ship-blocking authorization holes and one process-integrity decision** that must be closed first, because they expose cross-branch student PII and allow a legally sensitive workflow (appeals) to terminate with zero effect while reporting success:

| # | Ship-blocker | Sign-off requirement |
|---|--------------|----------------------|
| 1 | `PrintingController` renders **every printable artifact with zero capability checks** (§2.1) | Capability gate on each print action + negative tests (wrong-branch actor, capability-less actor) |
| 2 | Core Academic commands pass **`scope: null`**, and `null` scope **means "allow on any grant"** — so any holder of a capability can act on **any branch's** records (§2.2) | Non-null branch scope propagation (Placement-style) for all Academic verbs + cross-branch negative tests |
| 3 | Appeal `resolve()` sets `resolved` and stops: **no effect on, or linkage to, the contested subject** (§2.3) | Recorded product decision in an ADR — either wire defined effects per subject type or rename to `closeWithoutAction` with UI copy that says so |

Full suite + PHPStan + Pint must be re-run after the fixes. Everything else in this report is fix-in-sprint (§3) or accept-with-record (§4).

---

## 2. Must-fix (ranked by real-world importance)

### 2.1 PRINTING HAS NO AUTHORIZATION — any employee can print anything (HIGH — privacy)

`app/Http/Controllers/PrintingController.php:27-111` — all six actions (`receipt`, `invoice`, `certificate`, `transcript`, `payslip`, `enrollmentRecord`, `idCard`) resolve the record by id and render. There is **no `requireCapability`, no policy, no scope check** anywhere in the controller. `routes/web.php:78-83` exposes them to any authenticated employee session:

```php
Route::get('/print/enrollments/{enrollment}/record', [PrintingController::class, 'enrollmentRecord'])
```

`EnsureEmployeeSession` (`app/Http/Middleware/EnsureEmployeeSession.php:34-55`) guarantees *authentication* (employee session bound to a person + branch) — not authorization. So: a front-desk employee at Branch A can print report cards, transcripts, payroll slips, and student ID cards for **any student, any branch**, by id. Student records include minors' PII; payroll slips are employee-sensitive. Read-only does not make this low-severity — bulk exfiltration needs nothing but enumeration. Note the irony: the console views (§5.4) were carefully stripped of sensitive data while the print surface hands it out.

Fix: gate each action on the owning capability (`academic.results.read` / `academic.enrollments.read` / HR pay capability for slips) **and** branch scope of the underlying record.

### 2.2 BRANCH ISOLATION IS DECORATIVE outside Placement (HIGH — multi-branch integrity)

- `app/Modules/Access/AccessResolution.php:37-40`: `decide($actor, $capability, $scope = null)` — when `$scope` is `null`, **any grant of the capability authorizes, regardless of branch**.
- Core Academic commands call it with `scope: null` (`MaintainAcademicStructure::requireCapability`, `MaintainClass`, `ManageAcademicAppeal`, `ManageAcademicOffering`, …).
- Placement — and only Placement — does it correctly: `app/Modules/Academic/Placement/Domain/PlacementAccess.php:19-43` resolves the actor's branch and enforces it.

Consequences: the schema carries `branch_id` on classes, offerings, availabilities, and employee sessions, and the UI filters by branch — but the domain layer will happily let Branch A's registrar define levels, move classes, resolve appeals, and complete seats for Branch B. The branch column is a **UI filter, not a security boundary**. For a multi-branch school this is the single most structurally dangerous finding: every negative test in the suite tests capability presence, not branch confinement.

Fix: propagate record branch as `$scope` in every Academic `requireCapability` (or adopt `PlacementAccess`-style resolution centrally). Add cross-branch negative tests per verb.

### 2.3 APPEAL `resolve()` IS TERMINAL THEATER (MEDIUM-HIGH — process/legal integrity)

`app/Modules/Academic/Commands/ManageAcademicAppeal.php:130-154`: `resolve()` locks the appeal, requires `assigned`, flips it to `resolved` with outcome/narrative, audits — and touches **nothing else**. It never verifies the contested subject exists (see §3.1), never marks the result `appealed`, never amends a score, never re-opens a progression decision, never notifies. Meanwhile:

- `DecideProgression::assertCompletionEvidence` demands a `released` result; an upheld appeal has **no defined path** to produce one (the `appealed` result state from §3.2 is unreachable).
- Console copy (`resources/views/academic/index.blade.php:567`) presents "resolve the appeal" as the redress step. A parent told "the appeal was resolved in your favor" will discover the report card unchanged.

An appeal system whose terminal state has no defined downstream effect is worse than no appeal system: it manufactures a false record of redress. This needs a **product decision, not just code**: enumerate per-subject-type effects of each outcome (upheld → amend result / re-run progression; rejected → close), or explicitly scope appeals to advisory-only and say so in the UI. Either is defensible; the current middle is not.

### 2.4 APPEALS CAN BE FILED AGAINST NOTHING, FOR ANYBODY (MEDIUM — data integrity)

`ManageAcademicAppeal::file()` (`:57-90`): only `placement_profile` subjects are existence-checked (`academic.appeal_placement_unknown`). For `assessment_result` / `progression_decision` subjects, the code checks that *a student id was supplied* and that the student exists — **never that the subject row exists, is in an appealable state, or belongs to that student**. You can file an appeal "against result X on behalf of student Y" where X doesn't exist and Y never sat the assessment. The row persists, gets assigned, investigated, and "resolved" — a complete due-process paper trail over a void subject.

Fix: resolve the subject row inside `file()`, assert appealable state (released result / approved decision), and assert subject.student == appeal.student.

### 2.5 CLASS AND PERIOD TERMINAL TRANSITIONS HAVE NO SEAT GUARDS (MEDIUM — operational footgun)

The codebase already knows the correct pattern — `ManageAcademicOffering::transitionOffering` (`:136-144`) refuses `cancelled`/`completed` while `requested/active/frozen` seats reference the offering (`academic.offering_open_seats`). But:

- `MaintainClass::transitionClass` (`:102-130`) checks lifecycle legality + teacher presence only. A class with 30 active seats can be `cancelled`/`completed` in one click; seats dangle (`active` seats in a `cancelled` class refuse attendance via `academic.attendance_class_not_active` yet remain completable — `complete()` never checks class state).
- `MaintainAcademicStructure::transitionPeriod` (`:509` region) checks only lifecycle legality. A period can be closed with live classes, open seats, and in-flight assessments; the period is documented as "authority-owner of academic timing" but closure is consequence-free.

Fix: apply the offering guard pattern at both levels (class: active/frozen seats block cancel/complete; period: non-terminal classes or open seats block close), or require an explicit force-with-reason that gets audited.

---

## 3. Should-fix (fix in sprint; not ship-blocking)

1. **Dead `appealed` result state (LOW-MED).** `assessment_results` check constraint (`2026_08_31_000109…:16`) and `ResultLifecycle::released → [corrected, appealed]` advertise a transition **no code path performs** — `ManageAssessmentResult` has no mark-appealed verb, and appeal `file()` doesn't set it. Either wire it (file → mark appealed, mirroring the deliberate manual marking on progression decisions) or remove it from the machine. Dead states are traps for the next developer.
2. **Classes have no human-readable code (MED).** `2026_08_26_000034_create_classes_table.php:13-20` — identity is UUID-only; the console's own empty-state admits "Classes are opening soon." Registrars cannot operate, search, or reconcile UUID-only classes (timetables, room sheets, payroll all need codes like `BEG-AM-2511-A`). Add `class_code` (unique per period) before real operation.
3. **Sections exist but enrollments can't carry one (MED-LOW).** `class_sections` has capacity, yet `Enrollment::$fillable` has no section field and no consumer reads section capacity — section capacity is unenforceable decoration. Either attach enrollments to sections (and enforce) or drop the column before it misleads capacity planning.
4. **`assign()` accepts nonexistent reviewers (LOW).** `ManageAcademicAppeal::assign` checks independence from the original decision-maker (good) but never that the reviewer person exists or holds review capability. A typo parks the appeal in `assigned` forever — fail-stuck. Validate at assign time.
5. **Score upper bounds are half-enforced (LOW).** Negatives rejected (`assess()`/`correct()` floor at 0), but no per-component maximum: `coverage_pct` gets an implicit cap only via recombination (`min(100, …)`), and essays have none. Decide: max-score-per-item with validation, or record that 0–100 convention is enforced upstream.
6. **`updateSchedule`/`addRoom` bypass capacity sanity (LOW).** Both mutate class operating facts without re-validating `max_seats` vs room capacity. Minor while rooms carry no capacity field — becomes real the day they do.
7. **Enrollment snapshots can go stale (LOW — record the decision).** `request()` snapshots `seat_level_id`/program version at request time; nothing refreshes them at approve/activate. If staleness is intended (price-lock semantics), say so in an ADR; if not, re-snapshot at activation.
8. **Waitlist races fail ugly but safe (LOW — accept).** Position allocation is read-then-insert under no serial guard; the partial unique index (`2026_09_04_000131…:216`) converts a collision into a 500 rather than a clean rejection. Integrity holds (fail-closed); consider a retry-on-conflict for polish.
9. **Numeric corrections bypass reviewer independence (LOW — record the decision).** `correct()` on numeric results needs only the capability — no independent reviewer — while coverage recombination *does* (`academic.result_not_independent`). If numeric trust is intentional (teacher owns scores), document it; the asymmetry currently looks accidental.

---

## 4. Accept / redesign-later / out-of-scope

- **Regrade reuses `record()`; metric grammar is intentionally narrow** (`RecordAttendance`, `ScorePlacement::scoreSection` word/level/enum/count bounds verified sound). The 1.77-word/utterance figure is a metric-definition artifact (total words ÷ all 5,639 turns incl. assessor prompts), not a data-integrity signal — recomputation over rateable utterances gives ~2.9–4.1, matching the reported bands. No action.
- **Repeated `completed → completed` is denied by design** (terminal states, denial logged to audit). Accept; the denial *is* the audit trail.
- **Per-request random idempotency keys + `Idempotency-Key` header support + per-render form tokens** (`Controller::idempotencyKey`, `IdempotentExecution` records outcome only after success, conflicting payloads rejected): sound. The "spent key" and "double-submit" suspicions were checked and retracted.
- **Finance integration (partial verification):** Finance owns its own `EnrollmentInstallmentPlan` keyed by schedule ref; no direct read of academic enrollment state was found from the Academic side in this pass. Treat "Finance packages completed seats" as **unverified end-to-end** until a joint Finance/Academic test proves the join. (Also: `branch_id` on finance obligations should fall under the §2.2 scope fix.)
- **Deferred by ratified scope, unchanged:** D-F-059/070, push fan-out, 90-day attendance window, Friday-prayer blackout, Kankor-band model. None challenged by this review.

---

## 5. What survived attack (soundness register — do not regress)

- **Capacity control is race-safe:** `MaintainEnrollment::assertCapacity` / `assertOfferingCapacity` (`:632-650`) count under `lockForUpdate`; class + offering caps enforced at request, approve, activate, and unfreeze.
- **Graduation is terminal-with-history:** `DecideGraduation::assertNoOpenSeats` blocks graduation with open seats; transferred seats are terminal; program-version snapshots freeze history.
- **Idempotency conflicts are rejected, not merged:** `IdempotentExecution::execute` (`app/Support/Idempotency/IdempotentExecution.php:23-46`).
- **Independence rules exist where they matter most:** appeal assign excludes the original decision-maker; coverage recombination requires an independent reviewer; progression `markAppealed` is a conscious act.
- **Offerings are lifecycle-guarded** (open-seat guard, reopen context checks).
- **Regression net is real:** 761 tests / 6117 assertions, PHPStan clean, Pint clean at last full run.

---

## Appendix — retracted suspicions (checked, found sound)

Unfreeze *does* re-check class-active + capacity + gate (`MaintainEnrollment` unfreeze path, `:61-68`). `define()`/structure verbs *do* require the registrar capability. `date_of_birth` validation is correct (past-or-today rejected properly — the "allows today" reading was a misparse). `ScorePlacement` bounds are enforced. The `academic.index` 300-row cap is a documented governor, not a silent filter. These are listed so future reviewers don't burn time re-proving them.
