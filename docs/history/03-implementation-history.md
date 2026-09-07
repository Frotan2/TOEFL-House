# Implementation Packages, Checkpoints & Certification History

STATUS: HISTORICAL — NOT NORMATIVE

This consolidated volume preserves historical source documents verbatim by section. The source path is retained before each section. Current project authority lives in the canonical documentation set, not here.


---

## HISTORICAL SOURCE: `implementation/00-implementation-state.md`

# Implementation State

> **Current-state compliance pointer (2026-09-07):** The authoritative documentation-to-implementation reconciliation is `docs/architecture/current-state-compliance.md`; this file remains a mixed historical evidence record with a current-authority notice.

> **Current-authority notice (2026-09-05):** This file retains package history and prior evidence. Its historical test/readiness claims are not the current release verdict and must not override `docs/architecture/2026-09-05-unified-platform-reconciliation.md` or `docs/architecture/decisions/2026-09-05-unified-platform-architecture.md`. The unified architecture explicitly defers runtime validation and does not claim production readiness. Its approved Employee Workspace addendum is a first-class requirement: workspace composition must be work-first, exception-first, effective-identity/lifecycle/scope aware, role-aware but not role-locked, and must orchestrate canonical authorities without becoming one. The current settlement authority is Finance `employment_settlements`; Payroll `settlement_proposals` are workflow evidence and `final_settlements` is removed by migration `000143` after required reconciliation preflight.

**Governing contract:** `docs/MASTER_ENGINEERING_CONTRACT.md` (TOEFL HOUSE ERP — WORLD-CLASS MASTER ENGINEERING CONTRACT, v3.0, Canonical Project Engineering Constitution) is the highest-authority project engineering directive; the implementation artifacts and package records below it must remain consistent with it.

**Historical mission status (2026-09-04; superseded for current readiness):** The world-class ERP/EdTech build was explicitly **NOT complete** — no declaration of final completion was made while material gaps remained. The historical record stated that the repository was fully green (PHPUnit 632/4381, phpstan level 6, Pint 531 files, 125 migrations), the Calendar Authority is `F4-C VERIFIED` (active window SH 1399–1415 served), the WP-2 **F1/F2/F3 foundations** are implemented and green (provenance anchors + home-branch designation + `branch_scope_links`, `ProgramVersionLevel`, `BranchAvailability`/`Offering`), and the F1 **schema layer** now covers every branch-originating record (students/enrollments/obligations/certificates/payments/refunds/fund_allocations/contracts). The remaining ERP-blueprint gaps are catalogued in `docs/implementation/WP-1-capability-gap-matrix.md` and sequenced in `docs/implementation/WP-1-roadmap.md`. **Approved** foundation work is implemented under `WP2-DEC-01…04`; continuation is completion/consumer work (domain command paths, operational-record coverage, WP-3/WP-4 consumers). Items that still genuinely require a **new** product/architecture decision (CRM/lead, retail/inventory/sales scope, notification/inbox decisions, and migration — which is externally blocked) remain flagged and are not silently self-authorized. Safe in-scope hardening (repo-wide Pint gate, calendar completion, verification records, matrix reconciliation) is maintained continuously.

**Current package:** Package 17 — Production Readiness: Employee Interface, API, Printing, Identity Credentials, Deployment Finalization & Final Certification (user directive 2026-08-26/27); Packages 02–16 certified
**Historical status (superseded; not a current readiness claim):** P17 was recorded as delivering a complete interface on the P16 base (`37c3973`), but that checkpoint predates the fourth-architecture convergence and the deliberate runtime-validation deferral. It must not be used as evidence of current production readiness. The historical record stated: session-bound authentication (`UserAccount` `Authenticatable` + `SetAccountPassword` + `EnsureEmployeeSession`, migrations `000098`/`000099`) resolving the canonical `Actor` so authority always comes from the Access model; a web console (`routes/web.php` + controllers + views) covering every employee workflow; a session-authenticated JSON API (`routes/api.php`) over the same authoritative commands; first-class printing (receipts, invoices, certificates, payroll, enrollment, ID cards) carrying authoritative org/branch identity; the exact branding **The TOEFL House** throughout; the standard `public/` web entry point (front controller + Apache rewrite) added and verified live-served; runtime drivers pinned in `.env.example` to the schema (`SESSION_DRIVER=database`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`); and the `app/Http` transport layer added to phpstan. The single authoritative domain (16 modules, 91 capabilities, no parallel implementations) is now operable through a real interface. Cumulative suite **356 tests / 1610 assertions**, phpstan level 6 clean across `app/Modules`+`app/Support`+`app/Http`, pint clean (432 files), fresh-database rebuild from 99 migrations and schema-invariant gates green (checkpoint `40-package-17-production-readiness-employee-interface-checkpoint.md`)**

**Post-P17 mission verification (2026-09-04):** appended as living state — the project is certified for the P17 scope but **not** declared complete for the full ERP mission. Running head is on the Calendar Authority branch and the suite is now **632 tests / 4381 assertions**; repository-wide Pint clean (531 files); phpstan level 6 clean (284 files); `migrate:fresh` from 125 migrations green. The Calendar Authority (WP-2 F4) is `F4-C VERIFIED` (decision + implementation + evidence in `WP-2-approved-decisions.md`, `WP-2-F4B-calendar-authority.md`, `WP-2-F4C-calendar-authority-implementation-verification.md`). The WP-2 F1/F2/F3 foundations and the F1 provenance/home-branch schema layer on every branch-originating record are implemented and green. Cross-cutting hardening continues under the same single-authoritative-domain principle.

**Production hardening & deployment finalization (2026-08-27):** the system is made genuinely deployable and operational. A real production deployability defect was found and fixed — the `storage/framework/{views,cache,sessions}` runtime directories were not tracked, so a fresh clone/deploy failed at first render with "Please provide a valid cache path"; they are now tracked (standard Laravel `.gitignore`-in-directory layout) and the fix is proven by a fresh-tree `git archive` check. Added: a persistent `cache` table (migration `000100`) so the login brute-force rate limiter (`throttle:login`, 5/min per IP+username) is durable across PHP-FPM workers; baseline security headers on every response (`SecurityHeaders` middleware, mirrored at the nginx edge); a production health/readiness probe (`GET /health` — DB + app-key, 200/503, no secrets) distinct from `/up` liveness; the `public/` web entry point; a provider-neutral deployment suite (`deploy/`: nginx serving **only** `public/`, PHP-FPM pool, deterministic `deploy.sh` with releases+atomic symlink+auto-rollback, `backup.sh`/`restore.sh` verified-restore DR with RPO/RTO); a production-ready `.env.example` (`APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=database`); and the authoritative operational document `docs/operations/production-deployment.md` (answers all 17 deployment questions; documents that the synchronous monolith needs no queue worker, scheduler, Redis, or frontend build, and that the self-contained Integrations module is not exposed on the production HTTP surface). Gates: phpunit **OK (361 tests, 1637 assertions)**, phpstan level 6 clean, pint clean (437 files), `migrate:fresh` from 100 migrations green, schema invariants green, config/route/view cache verified.

**Finance module end-to-end audit — direct-SQL hardening (2026-08-27):** the Finance module was audited end-to-end (all 12 commands traced: authz, idempotency, transaction boundaries, lock ordering, caps, audit). App-level logic verified sound (including the fixed payment→obligation lock order that rules out deadlocks). The audit found one real gap class: critical monetary invariants were enforced only in application code, so a direct SQL INSERT (the mission's explicit "direct database attack" threat) could fabricate settlement. Closed at the authoritative database boundary with two migrations:
- `000101` payment-settlement balance guards — a payment can never be allocated+refunded beyond the amount received; an obligation can never be allocated beyond its uncovered remainder (rows locked `FOR UPDATE` inside the trigger, so concurrent direct INSERTs serialize).
- `000102` accounting guards — fund allocations are capped by the committed pool, the line remainder, the obligation remainder, and the fund's category restriction; every journal balances exactly (deferrable constraint trigger checked at commit, since a journal is built from several lines in one transaction); obligation lines total exactly the obligation amount (same mechanism).
The guards mirror — not replace — the domain commands, which remain the single authoritative implementation. Eleven new direct-SQL attack tests prove each fabricated settlement is rejected by the schema itself (`QueryException`), and the schema-invariant suite asserts every guard exists.

**Payroll module end-to-end audit — derivation guards (2026-08-27):** all four payroll commands traced (CalculatePayroll, ApprovePayrollResult, SettleEmployment, MaintainPayrollPeriod). App logic verified sound: SoD on approval (approver ≠ preparer ≠ beneficiary), versioned-contract computation with bcmath proration and immutable snapshots, result immutability with appending corrections/reversals, dual-clearance termination settlement. Existing schema coverage verified (calculation history retention + one-live-per-period-employment, result immutability, append-only adjustments, immutable final settlements, closed-period immutability). Gap class: the result-derivation and settlement-precondition invariants were app-only, so a direct SQL INSERT could forge a payable (inflated result amount, result for a superseded calculation, adjustment into a closed period, double reversal, settlement without clearances / with the beneficiary / twice). Closed with migration `000103` (row-locked AFTER INSERT triggers): a payroll result must exactly derive from an approvable calculation (amount, period, employment); adjustments are rejected once the period closes and a result reverses at most once; a final settlement requires a terminated employment, both clearances, distinct non-beneficiary actors, and happens at most once per employment. Eight new direct-SQL attack tests plus a guard-existence test. Gates: phpunit **OK (380 tests, 1665 assertions)**, phpstan level 6 clean, pint clean (441 files), `migrate:fresh` from 103 migrations green, guards verified deployed on the live database.

**HR module end-to-end audit — lifecycle guards (2026-08-27):** all five HR commands traced (MaintainEmployment, MaintainContract, MaintainContractVersion, MaintainLeave, MaintainScale). App logic verified sound and, unusually, the existing schema coverage was already strong: contract_versions carries approver-independence and evidence CHECKs plus a full lifecycle/freeze trigger, contracts enforce signed-terms immutability, scales enforce rank order, compensation_rules carry rate checks and a version gate. `compensation_components`/`work_bases` are dormant legacy (no command or model references). Gaps found: the employment and leave state machines were app-only (a direct UPDATE could resurrect a terminated employment, skip candidate→active, or revive a rejected leave), employment↔person binding was rewritable, a person could hold two concurrent employments (duplicate payroll exposure), and approved leaves could be forged (self-approval, missing decider, overlapping periods — all of which corrupt payroll proration evidence). Closed with migration `000104`: employment/leave state-machine triggers (with person-binding immutability), leave decision evidence + decider independence + no-overlap rules, and a partial unique index limiting one open employment per person. Eight new direct-SQL attack tests plus guard-existence assertions. Gates: phpunit **OK (389 tests, 1677 assertions)**, phpstan level 6 clean, pint clean (443 files), `migrate:fresh` from 104 migrations green, guards verified deployed on the live database.

**Academic module end-to-end audit — payroll-evidence guard (2026-08-27):** the Academic module was audited with focus on the money-critical slice — the payroll evidence chain (skills → scales → compensation rules → teacher assignments → class sessions → attendance → teaching delivery facts → CalculatePayroll). Existing schema coverage verified strong throughout: skills/scales catalog guards, compensation-rule rate + version-gate checks, session time + active-skill guards, append-only teacher-assignment-skills and attendance facts, immutable submitted attempts, immutable program versions, immutable certificates with unique serial, and delivery-fact claim immutability. The gap: a direct SQL INSERT could forge a teaching delivery fact — the raw evidence payroll pays skill rules against — with inflated hours, an unattributed skill, a session outside the claiming payroll period, or a session with no qualifying attendance, inflating the next legitimate calculation into an approved overpayment. Closed with migration `000105`: a delivery fact is admissible only if its skill and date equal its session's, the session falls inside the claiming calculation's period, the claiming teacher is genuinely assigned to that class on that date with the skill attributed, the session has uncorrected present/late attendance, and the hours equal the session duration with the app's exact minute-precision/scale-2 truncation (verified byte-for-byte against the live CalculatePayroll flow). Four new direct-SQL forgery tests plus a guard-existence assertion. Residual (non-money) integrity note: the academic record state machines (assessment results, progression/graduation decisions, appeals, enrollment) are app-enforced and backed by state-CHECKs, open-decision unique indexes, and immutable final certificates; schema-level state-machine triggers for those are a documented follow-up, not a monetary exposure. Gates: phpunit **OK (394 tests, 1684 assertions)**, phpstan level 6 clean, pint clean (445 files), `migrate:fresh` from 105 migrations green, guard verified deployed on the live database.

**Identity + Admissions audit — decision-evidence guards (2026-08-27):** the Identity and Admissions modules were audited for the same gap class. The access domain (organizations, positions, position-assignments, access-policies, scope-grants) is already well-protected (state CHECKs, one-open-assignment and one-open-grant unique indexes, immutable audit events). Two decision invariants were app-only: (1) **identity verification** — `people` had a state CHECK and the verified-identity uniqueness index, but a direct UPDATE could flip an unverified person to `verified` with no decision evidence, or rewrite/retroactively revoke a verified identity; verified persons are the foundation of every employment, payroll, and settlement; (2) **admission decisions** — the table was append-only with an outcome CHECK, but a direct INSERT could forge a decision with a single actor in all three roles (SoD), without reason/evidence, or for an applicant that is not in the decidable state. Closed with migration `000106`: verified persons require identity key + evidence reference + verifier + timestamp and are final once verified; admission decisions require three distinct actors, reason + evidence, and an applicant in the `applicant` state (row-locked). Test fixtures that had created verified people without evidence (modeled the very forgery the guard forbids) were updated to carry evidence. Five new direct-SQL attack tests plus a guard-existence assertion. Gates: phpunit **OK (400 tests, 1693 assertions)**, phpstan level 6 clean, pint clean (447 files), `migrate:fresh` from 106 migrations green, guards verified deployed on the live database.

**Students module end-to-end audit — student invariants (2026-08-27):** the Students module (TransitionStudentStatus, MaintainGuardianRelationship) was audited. Guardian relationships are already protected (state/period/verification CHECKs, one-open-relationship app rule, self-guardian and unknown-person rejections). The gaps: the `students` table enforced uniqueness only on `student_code`, so a direct INSERT could create a second student record for the same person (duplicate enrollment and duplicate tuition obligations) or a second student from the same admission decision — both invariants were app-only in `EnrollAdmittedApplicant`. And student status history (append-only table) accepted fabricated rows: a forged first status (other than the conversion status) or a row skipping the registry state machine (active→alumni). Closed with migration `000107`: unique indexes `students_one_per_person` and `students_one_per_admission_decision`, plus a row-locked BEFORE INSERT status-history guard (reason required; first row is the conversion status; every later row is a verified registry transition from the latest status). Four new direct-SQL attack tests plus invariant-existence assertions. Gates: phpunit **OK (405 tests, 1700 assertions)**, phpstan level 6 clean, pint clean (449 files), `migrate:fresh` from 107 migrations green, invariants verified deployed on the live database.

**PHASE_1 module-by-module audit — COMPLETE (2026-08-27, commit `ceb435f`).** All sixteen modules were discovered, enumerated, traced end-to-end (command → authorization → idempotency → transaction → locks → domain → audit → transport), challenged with direct-SQL attack scenarios, and repaired where the challenge found gaps. Seven guard migrations (`000101`–`000107`) close every money-critical and decision-critical invariant at the authoritative database boundary: payment settlement and accounting balance (Finance), payroll result derivation and termination settlement (Payroll), employment/leave state machines and decision evidence (HR), teaching-delivery evidence for skill pay (Academic), identity verification evidence/finality and admission-decision SoD (Identity/Admissions), and student uniqueness plus status-history integrity (Students). The remaining modules (Organization, Audit, Documents, Communication, Privacy, Reporting, Resources, Integrations) were triaged by full constraint inventory: they are already schema-hardened (state CHECKs, terminal-immutable triggers, append-only evidence tables, rebuild-only projections, variance-identity CHECKs on reconciliations) and carry no monetary flow, so no new guards were warranted; Integrations remains dormant by design. Cumulative evidence: 37 new direct-SQL attack tests (every forged settlement/payable/identity/decision/status rejected by the schema itself), per-guard existence assertions in the schema-invariant suite, and final gates phpunit **OK (405 tests, 1700 assertions)**, phpstan level 6 clean, pint clean (449 files), `migrate:fresh` from 107 migrations green with all guards verified deployed on the live database.

**PHASE_2 cross-module boundary audit — COMPLETE (2026-08-27).** The module-to-module boundaries that feed money were traced against the application predicates and attacked with direct SQL. Verified sound by design (no change): Finance never references payroll results directly (the journal boundary is the single crossing point), `RecordReconciliation` carries no open-window predicate (so the schema correctly has none), and `PostObligation` requires only its own preconditions (lines, reason, open period, positive lines) — student existence is covered by the FK and the app checks no further state. **Part one — enrollment × finance period boundary, migration `000108`:** (1) an enrollment seat could be fabricated for a student whose latest verified status is not active, into a class that is not active, or straight into a non-`requested` state (bypassing the request audit and the activation checks) — closed by a BEFORE INSERT guard on `enrollments` mirroring `MaintainEnrollment::request`; (2) the enrollment state machine was app-only (a direct UPDATE could revive a withdrawn/completed/transferred seat or reverse a transition) and a seat could be moved between classes in place instead of going through the transfer close+re-request — closed by a BEFORE UPDATE guard (state machine + class_id immutability) mirroring `EnrollmentLifecycle`; (3) a seat could be activated past class capacity or into a class that is no longer active — closed in the same update guard, counting active seats under a `FOR UPDATE` row lock on the class exactly as `assertCapacity`/`assertClassActive` do; (4) a `transferred` tombstone could be forged for a student who is not currently active (the app re-verifies on transfer); (5) the one-open-seat invariant was under-stated in the schema — the base index `enrollments_one_active_seat` (000037) covered only `active`, while the app rejects a duplicate `requested|active|frozen` seat — consolidated into the strictly broader partial unique index `enrollments_one_open_seat`, which fully subsumes and replaces the narrower one (single authoritative index for the invariant). (6) A financial period could be closed by direct UPDATE while an open or calculating payroll period overlaps its dates (the app blocks close with `finance.period_payroll_open`), its date scope rewritten (fixed at open — the app has no date-mutation command), or reopened after closure — closed by a BEFORE UPDATE guard on `financial_periods` mirroring `MaintainFinancialPeriod::close` (overlap predicate byte-for-byte), the fixed date scope, and the terminal-closed immutability rule. The suspected duplicate-open-teacher-assignment gap was NOT a gap: the base schema already carries `teacher_assignments_one_open_per_class_teacher` (000036); the boundary file's attack test pins that invariant at the schema level without duplicating the index. sixteen new boundary attack tests (13 forged statements rejected by the schema, 3 positive controls proving legitimate closes/ends still succeed) plus the consolidated index existence assertion. **Part two — the period-window boundary, migration `000109`:** the app gates every money-scoped write to an open period (`finance.period_not_open` in PostObligation, PostJournal, RecordPayment, RefundPayment, MaintainDiscount; open-or-calculating in CalculatePayroll::prepare), but the existing immutability triggers on those tables cover only UPDATE/DELETE — a direct INSERT landed the write in a CLOSED period, fabricating liabilities, accounting entries, receipts, refunds and discounts after closure. Closed with BEFORE INSERT window guards on `obligations`, `journals`, `payments`, `refunds` and `discounts` (shared row-locked `assert_financial_period_open` predicate, one per write) and on `payroll_calculations` (open or calculating). The audit also found the calculation SNAPSHOT itself rewritable while unconsumed: the 000103 derivation guard accepts any result whose amount equals the calculation's `base_amount`, so a direct UPDATE inflating a prepared calculation's base amount (or its period/employment/snapshot) is a forged payable — closed with a write-once snapshot guard: on `payroll_calculations` only the lifecycle state may ever change. Eight more boundary attack tests (7 forged statements rejected, 1 positive control) plus trigger-existence assertions for both part-one and part-two guards. **PHASE_2 complete:** cumulative evidence for the phase: 24 boundary attack tests in `tests/Feature/CrossModuleBoundaryAttackTest.php` (20 forged statements rejected by the schema itself, 4 positive controls), guard/index existence assertions in the schema-invariant suite, and final gates phpunit **OK (431 tests, 1744 assertions)**, phpstan level 6 clean, pint clean (452 files), `migrate` to 109 migrations green with all guards verified deployed on the live database.

**PHASE_3 frontend workflows & transport hardening — COMPLETE (2026-08-27).** The full HTTP surface (64 web + 26 API routes, 18 controllers) was audited against the 18 absolute rules. All 18 controllers verified pure transports: zero model writes anywhere in `app/Http` (every mutation delegates to a module command; Organization, Audit, Home and Health are read-only; Printing is read-only); all authenticated routes sit behind the `employee` session guard (the only non-employee routes are `GET|POST /login`, `GET /health`, `GET /up`); CSRF (`VerifyCsrfToken`) is active for every web POST in production; the API error taxonomy maps the domain `DomainError` to a stable JSON payload (`error`, `category`, `message`, `correlation_id`, `retryable` with 422/403/409/502/500). **Critical defect found and closed — fabricated separation-of-duties parties at the transport:** six sites constructed the SoD counterpart from request input (`new Actor($input['approver_id'])` for the refund approver in `FinanceController`/`FinanceApiController`, `new Actor($input['reviewer_id'])`/`new Actor($approver_id)` for the admission reviewer+approver in `StudentsController`/`StudentsApiController`) — a single session typed any person id, and the domain/schema SoD on the stored identities was hollow. Both affected workflows were re-staged to the house pattern (discounts, contract versions): **migration `000110` (staged refunds):** `refunds` gains `lifecycle_state` (`proposed`|`recorded`, existing rows recorded) and `approved_by` becomes nullable; `RefundPayment::refund` (atomic two-actor) is replaced by `propose(requester, …)` + `approve(approver, refund, …)` — each signature captured from its own authenticated session; the base `refunds_immutable` (000065) and `refunds_balance_guard` (000101) triggers are consolidated into one `refunds_lifecycle_guard`: a refund is BORN proposed (impossible proposals rejected at the boundary), a proposed refund may only become recorded and only its state and `approved_by` may change, the remainder cap (allocated + recorded ≤ amount received) is re-checked under the payment row lock at both proposal and approval, recorded refunds stay fully immutable, and DELETE is rejected. **Migration `000111` (staged admissions):** `admission_decisions` gains `lifecycle_state` (`proposed`|`reviewed`|`final`, existing rows final); `reviewer_id`/`approver_id` become nullable; `DecideAdmission::decide` (atomic three-actor) is replaced by `initiate` + `review` + `approve`, one session actor per stage with progressive SoD (review ⇒ reviewer ≠ initiator; approve ⇒ three distinct), outcome/reason/evidence/applicant/initiator frozen at initiation, and the applicant transition (admitted/rejected) performed by the `admission_decisions_lifecycle_guard` ITSELF at finalization — no statement, application or direct SQL, can declare a decision final while the applicant stays put; the base append-only trigger (000027) and 000106 SoD guard are consolidated into that one guard; a single person holding all three capabilities may still carry only one stage. **Transport:** the web/API refund form no longer accepts `approver_id` (propose endpoint) and a new `POST /finance/refunds/{id}/approve` records it; the admission form no longer accepts `reviewer_id`/`approver_id` (initiate endpoint) and new `POST /students/decisions/{id}/review` / `…/approve` (web + API) carry the remaining stages — the pending-decision queues surface in the applicants and finance views. **Second critical defect found while proving the fix:** `user_accounts.person_id` is `char(36)` and `EnsureEmployeeSession` bound the session actor with the space-padded value, so every command-level SoD comparison (`trim(stored) === actorId`) silently failed over HTTP — only the database guards were actually enforcing distinctness (as a 500). The session actor is now bound with the trimmed person id at the single boundary where actors enter from sessions. **Web idempotency:** every one of the 30 mutation forms carries a hidden `idempotency_key` (UUID generated at render, matching the transport key grammar) so a double-click is an idempotent replay, not a new operation (login/logout excluded). **Evidence:** flagship HTTP tests rewritten session-per-stage (clerk initiates → sign out → reviewer reviews → sign out → approver finalizes → enrollment; one session carrying two stages denied at each boundary; a fabricated `approver_id` in the request body has no effect; self-approval denied with the proposal untouched), two new direct-SQL refund attacks (born-recorded rejected; amount changed at approval rejected), four new direct-SQL admission attacks (single-actor chain, skipped review stage, self-approving chain, plus the positive control that finalization transitions the applicant at the schema boundary), 22 fixture call sites migrated to the `DecidesAdmissions` test helper that drives the three authoritative commands with one session actor per stage, and final gates phpunit **OK (437 tests, 1837 assertions)**, phpstan level 6 clean, pint clean (455 files), DB at 111 migrations with both guards verified deployed on the live database.

**PHASE_3 transport-coverage completion — the last certified critical-path workflows (2026-08-29).** A PHASE_3 transport audit of `app/Http` (all 84 routes, all 18 controllers — zero model writes, every mutation delegates to a module command, session-bound actors only) found that **four of the certified critical-path workflows had no employee surface at all**, so the P17 claim of "a web console covering every employee workflow" was overstated: `MaintainEnrollment` (seat request/activation), `PostObligation` (obligation posting), `DecideProgression` (progression) and `SettleEmployment` (termination settlement — the **only two-signature workflow still un-staged**: `settle(preparer, approver, …)` took both signatures from one request, the same SoD-fabrication class commit `0c1e28f` closed for refunds and admissions). Closed in two increments, each verified end-to-end over the real HTTP surface:
1. **Thin transport over the existing staged commands (commit `097946c`):** `POST /academic/enrollments` + `POST /academic/enrollments/{id}/activate` (seat request by the clerk, activation by the approver — distinct sessions), `POST /finance/obligations` (open-period rule stays in the domain), `POST /academic/progressions` + `POST /academic/progressions/{id}/review` + `POST /academic/progressions/{id}/approve` (three distinct signers, three sessions). 5 HTTP tests (92 assertions) prove each workflow end-to-end including every domain rejection surfacing with its error code (seat-exists, approve-capability denial, period-not-open, progression capability denial, review/approval independence).
2. **Staged settlement approvals, migration `000112` (this increment):** `settlement_proposals` (`proposed`|`approved`; `approved_by` nullable until approval; partial unique index — one open proposal per employment). The consolidated `settlement_proposals_guard`: INSERT — a proposal is BORN proposed, only for a terminated employment that already holds BOTH the hr and finance clearances and is not yet settled (row-locked on the employment, mirroring the 000103 final-settlement checks); UPDATE — only proposed→approved, only the state and `approved_by` may change, and the approver must differ from the preparer AND from the beneficiary (the 000103 SoD rules mirrored at the proposal boundary); DELETE — never (a proposal is an auditable fact). `SettleEmployment::settle` is **replaced** by `propose(preparer, employment, amount, basis, …)` + `approve(approver, proposal, …)` — the last un-staged two-signature workflow is now session-staged exactly like refunds (000110) and admissions (000111); `final_settlements` keeps its 000056 immutability + 000103 derivation guards and remains the single recorded fact. Transport: `POST /payroll/employments/{id}/clearance` (hr|finance), `POST /payroll/employments/{id}/settlements` (propose), `POST /finance/employment-settlements/{proposalId}/approve`; the terminated-employment/clearance/proposal queues surface in the payroll view. 6 new direct-SQL proposal-attack tests (un-cleared, active employment, second open proposal, self-approval, beneficiary approval, amount mutation, delete — each rejected by the schema) and 2 new HTTP settlement tests (four-session end-to-end; clearance denial + proposal block for an unprivileged session). **Financial period management** (`MaintainFinancialPeriod` open/close — the prerequisite of the obligation workflow, found unexposed by the same audit) is exposed in this increment as well: `POST /finance/periods` + `POST /finance/periods/{id}/close` (1 HTTP test: open, duplicate-key rejection, terminal close, re-close rejection).
**Result:** every certified critical path is now operable by a real employee through the console — admissions→delivery→progression, employ→payroll→settle, obligation→payment→refund — with each two-signature stage signed in its own authenticated session and the database guards as the final authority. The exposed command set is now 24 of the 78 commands (the remaining 54 are non-critical-path configuration/catalog commands, e.g. chart of accounts, skill catalog, integration endpoints, which have no employee workflow of their own). Final gates phpunit **OK (452 tests, 2009 assertions)**, phpstan level 6 clean, pint clean (459 files), DB at 112 migrations with the settlement-proposal guard verified deployed on the live database.

**Environment status:** ENVIRONMENT READY (2026-08-25 — restored via `P02-environment-recovery.sh --recover`; artifacts published and digest-verified on release `p02-artifacts`; re-verified 2026-08-26 → ENVIRONMENT VALID)
**Package 02 status (Identity and Organization):** CERTIFIED (checkpoint `24-package-02-identity-organization-checkpoint.md`, commit `1ea387a`)
**Package 03 status (Authorization and Scope):** CERTIFIED (checkpoint `25-package-03-authorization-scope-checkpoint.md`, commit `7be1272`)
**Package 04 status (Documents and Privacy):** CERTIFIED (checkpoint `26-package-04-documents-privacy-checkpoint.md`, commit `a1c8654`)
**Package 05 status (Students and Admissions):** CERTIFIED (checkpoint `27-package-05-students-admissions-checkpoint.md`, commit `4a09b52`)
**Updated:** 2026-08-26

The repository baseline was inspected and verified as documentation-only. No production source, database, schema, migrations, package tooling, or tests are present in this checkout. The implementation contract, deferred-input boundaries, package order, traceability convention, and verification expectations are established without changing business behavior.


## Current system capability: teacher compensation architecture

The current system design includes an integrated teacher compensation architecture spanning the authoritative modules:

- **Skill ownership (Academic):** the teaching skill catalog (Speaking & Listening, Writing & Grammar, Reading & Vocabulary as the registered initial set) is a first-class Academic concept — registered and retired under control, never deleted, independent of student level. Teaching assignments carry their skills as append-only evidence (`teacher_assignment_skills`), and each class session delivers at most one skill, keeping delivered teaching attributable by skill identity.
- **Scale ownership (HR):** the compensation scale catalog is an HR concept with the approved initial set S1 Junior, S2 Standard, S3 Senior, S4 Expert — independent of skill and academic level (a compensation rank, not a teaching classification); a contract version pins at most one scale, and scale changes are governed contract amendments, never retroactive edits.
- **Contract lifecycle and FM→GM approval (HR):** contracts are chains of immutable versions with lifecycle `draft → submitted → approved → active → superseded|expired` (withdrawal only before approval). The Finance Manager prepares and submits (`hr.contract.prepare`); the General Manager approves (`hr.contract.approve`); the approver is never the preparer and never the beneficiary — enforced in the command layer and in the schema (approval-evidence, approver-independence, immutability and no-delete constraints). Approval records approver, timestamp and a digest reproducing exactly what was approved.
- **Compensation-rule resolution (HR):** rules attach to a contract version and are the single authoritative compensation model, addressable by `method + skill + scale` (fixed monthly, labeled allowance, per-session rate, hourly rate). Per-unit rates share one resolution space per version with the deterministic precedence ladder exact skill×scale > skill-only > scale-only > generic; overlap inside the space is impossible (unique index). A delivered skill with no matching rule holds the calculation — never a silent zero. Fixed monthly and allowance lines prorate by calendar-day overlap of the version window and the period (payable = amount × active days / period days, full period pays in full, exact integer-cent arithmetic, round half up); per-unit rates are not prorated.
- **Teaching-volume authority (Academic → Payroll):** a payable unit is a delivered session — scheduled with a skill, covered by the teacher's effective assignment for that class and skill, and qualified by final attendance: at least one attendance fact whose status is present or late and which is the uncorrected tip of its authoritative `corrects_id` chain (corrections, not timestamps, resolve qualification; absent/excused never qualify; cancelled sessions carry no qualifying attendance). Sessions outside assignment coverage or without skill attribution hold the calculation. Each qualifying session is claimed exactly once (`teaching_delivery_facts`, unique per session, carrying the qualifying attendance fact as evidence; claims migrate only from superseded calculations of the same period and employment).
- **Payroll snapshot guarantees (Payroll):** calculations resolve the in-force contract version for the exact period → rules → skill/scale → authoritative volume and store a complete immutable snapshot (contract/version identity, scale, rules and rates, per-skill session and hour volumes, delivery claims with attendance evidence references, additive proration windows, period, amount). Approved results are immutable; corrections append adjustments/reversals; previously approved payroll reproduces exactly after later contract, scale, skill, rate, assignment, or attendance-correction changes. A period with no in-force version is held contract-silent — no fallback, no invented charge.
- **Legacy retirement:** the competing per-kind compensation-component architecture and its manual/academic work-basis evidence are hard-retired — models and commands deleted, the legacy calculation fallback removed, `compensation_components` and `work_bases` dropped from the schema (migrations 000096/000097), legacy tests removed; the contract version with its compensation rules is the only compensation path.
- **Finance boundary:** payroll results continue to cross into Finance through the certified journal boundary (`source_type='payroll_result'`); the P15 opening payables remain independent; there is no second accounting engine, and the reporting catalog is unchanged.

Cumulative verification after P16 finalization: **330 tests / 1472 assertions** (phpunit OK), phpstan level 6 clean, pint clean, `migrate:fresh` rebuild from 97 migrations green, schema-invariant suite extended with the catalog/version/rule/delivery guards and the legacy-table absence gate, environment verification ENVIRONMENT VALID.

## Standing implementation standard

The Implementation Quality Directive — Absolute Engineering Standard was adopted by user directive on 2026-08-25 and codified at `docs/implementation/21-implementation-quality-directive.md` (Decision Ledger `D-F-101`). It is the mandatory quality and verification standard for every implementation artifact in every future package. It establishes no business policy and authorizes no package.

## Technology selection

The previously open technology decision (Decision Ledger `D-F-003`; legacy findings L-002/L-003) was resolved by user decision on 2026-08-25: **PHP + Laravel** application technology and **PostgreSQL** persistence, remaining a modular monolith with strict contexts. Recorded as ADR-013 in `docs/architecture/23-architecture-decision-records.md` and Decision Ledger `D-F-100`. ADR-013 is authoritative. The proposed ADR-013-A amendment (framework-free variant) was rejected/withdrawn on 2026-08-25 (Decision Ledger `D-F-103`); it does not take effect and no framework substitution is authorized.

## Environment status (2026-08-25)

The environment blocker is **CLOSED**: the approved stack is now reproducibly obtainable and operational.

- **Laravel 12.67.0** installed through Composer 2.10.2 from canonical official GitHub sources (Packagist unreachable in this sandbox; remediation uses official GitHub `vcs` repositories with `no-api` git clones — the canonical sources Packagist itself mirrors). `composer.lock` committed; `composer install` reproducible; `composer audit` clean.
- **Verified:** PHP 8.2.27 + Laravel Framework 12.67.0 boots; PostgreSQL 18.4 reachable standalone (PDO) and through Laravel (`php artisan db:show` → database `toefl_house`, user `postgres`).
- Evidence: `docs/implementation/23-environment-readiness.md` (**final status ENVIRONMENT READY**, all checks passed); blocker closure in `docs/implementation/22-environment-blocker-report.md`.

The technology decision is unchanged and authoritative: **PHP + Laravel + PostgreSQL, strict modular monolith (ADR-013 / D-F-100)**; no framework substitution is authorized.

## Package boundary

Package 02 — Identity and Organization is **CERTIFIED** (2026-08-25, checkpoint `24-package-02-identity-organization-checkpoint.md`): Organization module (structure units, registry lifecycle, effective-dated campus attribution, two-Owner authority chain), Identity module (person verification, account link/deactivate with history), append-only Audit module with structural immutability, and the shared kernel (error taxonomy, default-deny authorization port, idempotency store). Gates: phpunit **OK (78 tests, 371 assertions)**, phpstan level 6 clean, pint clean, schema/migration, authorization, lifecycle, invariant, idempotency and adversarial suites pass; financial gates recorded NOT APPLICABLE. The implementation was created from the governance records after recorded evidence established no prior implementation existed anywhere reachable.

Package 03 — Authorization and Scope is **CERTIFIED** (2026-08-26, checkpoint `25-package-03-authorization-scope-checkpoint.md`): the canonical authority registry (positions, effective-dated assignments, versioned role/permission policies, named-scope grants, dated and reasoned delegations) with the `AccessResolution` server policy decision replacing the interim capability-map adapter behind the unchanged default-deny `AccessDecision` port; lifecycle `proposed→active→expired|revoked` with terminal states; organization-wide grants require two distinct eligible approvers; emergency grants are dated ≤ 30 days and flagged for mandatory review. Gates: phpunit **OK (118 tests, 477 assertions)**, phpstan level 6 clean (53 files), pint clean (97 files); schema, invariant, authorization, temporal, adversarial and idempotency suites pass; financial gates recorded NOT APPLICABLE. The environment was reused, not rebuilt; the P02 suite passes unchanged on the canonical resolver. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 04 — Documents/Privacy is **CERTIFIED** (2026-08-26, checkpoint `26-package-04-documents-privacy-checkpoint.md`): the privacy module (purpose registry with separate communication/marketing channels, effective-dated consent with evidence over verified subjects, subject self-service, revocation as append-only evidence that stops future use without erasing history, disclosures with minimum fields, subject data export with two-distinct-approver bulk control) and the documents module (classification and retention registries, evidence documents with immutable versions, verification with verifier≠uploader separation of duties, retention decisions that compute from the rule; a URL is never authority). Gates: phpunit **OK (158 tests, 590 assertions)**, phpstan level 6 clean (76 files), pint clean (136 files); privacy/history, lifecycle, temporal, invariant, adversarial and idempotency suites pass; financial gates recorded NOT APPLICABLE. The audit half of the sequence row was already certified in Package 02 and was not re-implemented. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 05 — Students/Admissions is **CERTIFIED** (2026-08-26, checkpoint `27-package-05-students-admissions-checkpoint.md`): the admissions module (verified-person applicants with one open file, three-role decide chain with distinct actors and append-only decisions, transactional applicant→student conversion with rollback integrity) and the students module (one student per person, append-only status history with reactivation-only-by-approval, verified guardian relationships with relationship-specific permissions; unverified or revoked relationships carry nothing). Gates: phpunit **OK (179 tests, 657 assertions)**, phpstan level 6 clean, pint clean (158 files). Enrollment is deferred to the Academic delivery package per the module ownership registries (recorded decision #1 in the checkpoint). Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 06 — Academic Delivery is **CERTIFIED** (2026-08-26, checkpoint `28-package-06-academic-delivery-checkpoint.md`): academic structure (programs with immutable published versions, published periods), class/session delivery with the registry lifecycle (activation requires an open teacher assignment; cancellation preserves the record; capacity invariant under lock; no duplicate active seat; transfer closes the old enrollment and opens a new one), and append-only attendance facts with attributed, reasoned corrections. The package began by recovering a full platform sandbox reset through the committed recovery mechanism only (working tree verified identical to `4a09b52`; toolchain restored from digest-verified release artifacts; post-recovery regression green before new work). Gates: phpunit **OK (193 tests, 732 assertions)**, phpstan level 6 clean, pint clean (184 files); dev DB at 38 migrations. Enrollment — deliberately deferred from Package 05 per the ownership registries — is delivered here. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 07 — Academic Decisions is **CERTIFIED** (2026-08-26, checkpoint `29-package-07-academic-decisions-checkpoint.md`): the decision half of the academic module — assessment attempts frozen on submission, results moving scored→moderated→approved→released with release reachable only through approval and stage actors recorded (scorer≠moderator≠approver enforced), reasoned corrections appending a new row and closing the original as corrected, appeals with an independent assigned reviewer and mandatory outcome+evidence (no silent closure), three-role progression decisions with appeal supersession that retains the original, graduation eligibility with mandatory basis and independent approval, and certificates issued only from an approved eligible decision with immutable issuance records and unique serials. BR-ACAD-002 holds: a score never becomes a decision automatically. Gates: phpunit **OK (209 tests, 792 assertions)**, phpstan level 6 clean, pint clean (205 files); DB at 44 migrations. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 08 — HR/Teachers is **CERTIFIED** (2026-08-26, checkpoint `30-package-07-hr-teachers-checkpoint.md`): employment of verified persons with append-only status history and one open employment per person (candidate→active hire requiring a signed contract; leave/suspension/termination with reasons), contracts whose signed terms are immutable once active with one open contract per employment (a change closes the prior and opens a new contract), effective-dated compensation components proposed by HR and activated by a different, never-beneficiary approver (immutable once active, no same-kind overlap), leave with independent decision, single pending request and no overlapping approvals, append-only work-basis evidence that HOLDS academic/employment disagreement instead of dropping it, and termination that closes contracts, cancels leave and revokes position assignments so access ends with employment. Gates: phpunit **OK (222 tests, 847 assertions)**, phpstan level 6 clean, pint clean (226 files); DB at 50 migrations. Payroll (periods, calculations, results, settlement) remains the next package per the sequence. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 09 — Payroll is **CERTIFIED** (2026-08-26, checkpoint `31-package-09-payroll-checkpoint.md`): controlled payroll periods (closed is terminal and immutable; closure blocked while contract-silent calculations are held), calculations that snapshot the effective contract configuration and consumed work evidence with exact amounts and superseding recalculation history, contract-silent cases held for HR/Finance review with nothing invented, approved results segregated from preparation and the beneficiary (immutable; corrections and reversals append adjustments; closed periods reject mutation), and final settlement after termination with HR and Finance clearances, two distinct non-beneficiary actors, declared-basis amounts, and immutable records. Gates: phpunit **OK (233 tests, 901 assertions)**, phpstan level 6 clean, pint clean (245 files); DB at 56 migrations. Actual payment posting remains with the Finance packages per the sequence. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 10 — Finance core is **CERTIFIED** (2026-08-26, checkpoint `32-package-10-finance-core-checkpoint.md`): immutable chart of accounts with unique codes and canonical types, controlled financial periods that close terminally and coordinate with payroll periods through an explicit status check (an overlapping open payroll period blocks closure with an exception), posted obligations whose positive atomic lines sum exactly to the computed amount, exactly-balanced source-linked journals with appending reversals, and reconciliation as locked variance evidence (computed variance identity enforced by the schema, mandatory explanation, one observation per period and subject, independent approval). Gates: phpunit **OK (245 tests, 943 assertions)**, phpstan level 6 clean, pint clean (266 files); DB at 62 migrations. Payments, refunds, discounts and funding remain the next package per the sequence. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 11 — Payments and funding is **CERTIFIED** (2026-08-26, checkpoint `33-package-11-payments-funding-checkpoint.md`): payments as immutable posted facts with a unique external receipt reference (a payment posts only once, open periods only), allocations linking one payment to one obligation (unique pair; capped by the unallocated payment remainder and the uncovered obligation remainder under per-source row locks), refunds per BR-FIN-002 (documented reason, immutable source, distinct requester and approver, capped at the refundable remainder), discounts per BR-FIN-003 (mandatory eligibility and dates, distinct approver, cap re-checked under lock, original charge preserved), and funding per BR-FUND-002 (immutable agreements with never-reclassifiable restrictions, permitted-use-only allocations capped by pool/line/obligation remainders, derived utilization). Gates: phpunit **OK (255 tests, 989 assertions)**, phpstan level 6 clean, pint clean (286 files); DB at 68 migrations. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

Package 16 — Skill, Scale, Contract & Payroll finalization is **CERTIFIED** (2026-08-26, checkpoint `39-package-16-skill-scale-contract-payroll-checkpoint.md`): the teacher compensation architecture is finalized inside the existing authoritative modules — calendar-day proration of fixed/allowance lines (payable = amount × active days / period days, full period pays in full, exact integer-cent arithmetic with round half up), session payability on final attendance status present/late resolved through the authoritative `corrects_id` correction chain (absent/excused never qualify; cancelled sessions unpayable), the approved initial scale catalog S1 Junior / S2 Standard / S3 Senior / S4 Expert, the single compensation path of contract version + compensation rule with the deterministic precedence ladder and fail-closed HELD (contract-silent, rule-missing, unattributed delivery), complete immutable snapshots (version, scale, rules/rates, per-skill breakdown, delivery claims with attendance evidence, proration windows, final amount) that reproduce approved payroll after any later change, and per-session delivery claims making double payment impossible at the database level. The competing per-kind compensation-component architecture and its work-basis evidence (models, commands, capabilities, `computeLegacy` fallback, tables, tests) are hard-retired in the same change; migrations 000096/000097 drop the legacy tables with full down-migrations (no production data exists). Gates: phpunit **OK (330 tests, 1472 assertions)**, phpstan level 6 clean, pint clean, `migrate:fresh` from 97 migrations green, schema invariants including legacy-table absence green.
Package 15 — Opening Financial State is **CERTIFIED** (2026-08-26, checkpoint `38-package-15-opening-financial-state-checkpoint.md`): the organization's opening financial snapshot exists exactly once (unique per organization, draft → submitted → approved with trigger-enforced path, approval evidence and digest, approver ≠ preparer CHECK), immutable opening entries (category shape matrices CHECK-enforced, positive AFN amounts, unique paper source references, draft-only recording, update/delete impossible), and an atomic General-Manager approval that materializes the facts into certified instruments under the preparer's posting authority — receivables become source-linked obligations in the FM-designated opening period (so normal payments/allocations settle them), cash positions become balanced journals, payables remain the authoritative opening liability settled through normal journals (no fake payments/payroll history) — with corrections only through normal instruments (approved discounts/journals) while the original evidence and digest stay reproducible. Gates: phpunit **OK (308 tests, 1300 assertions)**, phpstan level 6 clean, pint clean (379 files); DB at 89 migrations. The conditional migration/cutover row remains untouched (no legacy database exists).
Package 14 — Integrations/Jobs is **CERTIFIED** (2026-08-26, checkpoint `36-package-14-integrations-jobs-checkpoint.md`): registered anti-corruption endpoints (unique key, channel CHECK, versioned contract, credential reference only — secrets and transport bindings live in configuration outside domain data), an outbound outbox with one row per (endpoint, idempotency key), digest-protected payloads, trigger-locked identity with progress-only updates, bounded exponential backoff (2^n minutes, capped; max attempts 1–10 CHECK-bound), visible dead-letter on exhaustion or permanent failure with an audited manual requeue, and a delivered-requires-evidence CHECK that makes fabricated success impossible; authenticated inbound webhooks (HMAC-SHA256 over the payload digest, constant-time), per-accepted-event deduplication, retained rejection evidence that never blocks a corrected retry, and exactly-once processing under lock; and durable scheduled jobs from a closed catalog — one run per (job, occurrence) so racing schedulers collapse onto one execution, claim-once execution with bounded retries and dead-letter, all through a single shared delivery core used by both worker and sweep (no parallel infrastructure). Gates: phpunit **OK (295 tests, 1229 assertions)**, phpstan level 6 clean, pint clean (369 files); DB at 86 migrations. Business rules, architecture, module boundaries, and implementation contracts are unchanged.
Package 13 — Reporting is **CERTIFIED** (2026-08-26, checkpoint `35-package-13-reporting-checkpoint.md`): a closed canonical metric catalog (five metrics — outstanding balance, payroll total, active enrollment count, attendance rate, fund utilization — nothing outside it is definable), versioned immutable calculation specifications (revisions append and mark superseded projections stale; historical report runs stay reproducible with a stored reproducibility hash), period resolution strictly from the owning module's registry (financial/payroll by period key, academic by id — no cross-authority leakage, Reporting never defines a period), rebuildable projections with trigger-locked slice identity and complete/stale labeling, calculators as the only value source (no manual metric entry), reconciliation that recomputes from authoritative sources and records matched/diverged with the variance identity CHECK-enforced (divergence is immutable evidence, never an alternate truth), and dashboards that pin only complete current-version registered slices (stale/never-computed withheld) and cannot write any source. Gates: phpunit **OK (278 tests, 1118 assertions)**, phpstan level 6 clean, pint clean (337 files); DB at 81 migrations. Business rules, architecture, module boundaries, and implementation contracts are unchanged.
Package 12 — Assets/Operations/Communication is **CERTIFIED** (2026-08-26, checkpoint `34-package-12-assets-operations-communication-checkpoint.md`): assets with unique codes and retained custody history (one open custody per asset, transfer closes the prior row, disposal closes custody), disposal as a three-distinct-actor approved immutable record (requester plus two approvers — the material-action rule applied fail-closed), facilities work orders with independent approval and evidence-mandatory completion, book circulation with immutable copies, one open issuance per copy, evidence-backed loss and permanent withdrawal of lost copies (availability derived, never stored), and post-commit communication queued only under an active consent on the purpose's registered channel with evidence-backed delivery and revocation blocking future use without erasing history. Gates: phpunit **OK (265 tests, 1043 assertions)**, phpstan level 6 clean, pint clean (310 files); DB at 75 migrations. Business rules, architecture, module boundaries, and implementation contracts are unchanged.

---

## HISTORICAL SOURCE: `implementation/01-implementation-readiness.md`

# Implementation Readiness

Gate 6 audits the approved Foundation and Architecture as an execution contract. Result is **PASS WITH NON-BLOCKING OPEN ITEMS**: no Critical or High implementation-contract blocker exists. Every authoritative entity has an owner, critical mutation has authorization/scope, financial operations have boundaries, lifecycles and invariants have test categories, dependencies and reporting sources are explicit, and legacy/migration boundaries are recorded.

Non-blocking: organization-specific RPO/RTO/retention, detailed metric acceptance examples, configurable thresholds/agreement terms, and the conditional legacy migration decision. These must not be silently invented.

Implementation score: **92/100** (complete for authorized architecture-to-implementation handoff; deductions reflect documented operational/business inputs, not missing core ownership).

## Gap audit classification

| Audit area | Finding | Classification |
|---|---|---|
| contracts/commands/queries | registries define owner-bound commands and read-only queries | LOW follow-up |
| transactions | critical operations have owner transaction boundaries | 0 blocker |
| authorization/scope | server policy, effective dates, expiry and SoD defined | 0 blocker |
| lifecycles | transition registry supplies explicit state machines | 0 blocker |
| finance | source linkage, amount invariants, period and reconciliation defined | 0 blocker |
| concurrency/idempotency | source serialization and repeat-safe keys defined | 0 blocker |
| audit/history | material before/after/effective evidence defined | 0 blocker |
| integration/jobs | adapters, retries, dedupe, status and audit defined | 0 blocker |
| reporting | source/period/scope/refresh requirements defined | MEDIUM, catalog expansion |
| configuration | versioned configuration isolated from facts | 0 blocker |
| errors/resilience | stable categories and recovery boundary defined | MEDIUM, RPO/RTO values |
| testability | every critical invariant mapped to future categories | LOW, examples |
| legacy/migration | disposition and conditional migration path defined | MEDIUM, owner decision |

No gap requires inventing business authority or financial truth to begin implementation planning.

---

## HISTORICAL SOURCE: `implementation/02-implementation-contract.md`

# Implementation Contract

## Universal command contract

Every command carries actor, operation, target, scope, effective time, correlation ID, idempotency key when repeatable, and reason where material. Application service authenticates, invokes server authorization, validates lifecycle/preconditions, calls exactly one owning domain transaction, commits fact plus audit, and publishes post-commit work. Queries never mutate. Errors use stable categories. Rejected, held, retried, and completed outcomes are observable and auditable. The Employee Workspace may compose these outcomes into an employee's work-first and exception-first operational environment, but workspace visibility never grants authority and every workspace-originated mutation re-enters the owning command for current server-side authorization, scope, lifecycle, SoD, and invariant checks.

## Universal module contract

Each module owns its entities, repositories, invariants, lifecycle, commands, queries, audit records, and public contract. No module writes another module's persistence. Cross-module reads are defined projections/queries; cross-module mutations are commands. Events are notifications, not authority. Financial facts and authority changes require idempotency and concurrency controls. See `04-module-implementation-contracts.md`.

---

## HISTORICAL SOURCE: `implementation/03-command-query-registry.md`

# Command and Query Registry

| Command family | Owner | Authorization/scope | Transaction/audit |
|---|---|---|---|
| structure, ownership, assignment, delegation | Organization/Access | operation-specific, Owner rules | owner fact + approval/audit |
| identity verify/link/deactivate | Identity | identity/admin scope | identity transaction |
| apply/admit/convert/enroll/withdraw | Admissions/Students | admissions/student scope | student/enrollment audit |
| place/score/approve/appeal/graduate | Academic | academic scope and SoD | evidence/decision transaction |
| schedule/assign/attendance-correct | Academic | class/branch scope | append-only audit |
| employ/contract/leave/terminate/calculate/payroll | HR/Payroll/Finance | HR/payroll/finance SoD | period transaction |
| obligation/payment/allocate/refund/discount/adjust/reverse/journal/reconcile | Finance | finance scope, approval | source-linked financial transaction |
| fund/award/allocate | Funding/Finance | agreement and finance authority | restriction/posting audit |
| issue/return/move/dispose/complete work | Assets/Inventory/Facilities | custody/location scope | movement audit |
| verify/disclose/consent/revoke/export/send | Privacy/Documents/Communication | purpose/scope/consent | privacy/audit transaction |
| metric define/run/reconcile | Reporting | reporting scope | immutable run metadata |

Queries include effective entity detail, authorized lists with scope/as-of parameters, workflow status, statements, evidence, decisions, audit history, report runs, reconciliation, and job status. All pagination, filtering, and sorting are read-only and scope-filtered. No query result is an authority to mutate.

---

## HISTORICAL SOURCE: `implementation/04-module-implementation-contracts.md`

# Module Implementation Contracts

| Module | Authoritative data/commands | Dependencies and events | Forbidden writer/test obligations |
|---|---|---|---|
| Organization/Governance | structure, ownership; create/transfer/close/approve | Identity, Access; structure-changed | operations; structure/history tests |
| Identity | person/account verification; link/deactivate | Documents; identity-verified | permissions/status; uniqueness/privacy |
| Authorization/Scope | positions, roles, permissions, assignments, delegation; grant/revoke/resolve | Organization, Identity; authority-changed | UI/self-grant; auth/scope/expiry |
| Admissions/Students/Guardians | applications, admission, enrollment, relationships | Identity, Academic, Finance; admitted/enrolled | grades/balances; lifecycle |
| Academic/Placement/Classes/Scheduling/Attendance/Assessment | programs, periods, evidence, decisions, membership, attendance | Students, HR, Reporting; decision-released | finance/payroll; evidence/decision |
| HR/Teachers/Payroll | employment, contract, work basis, calculations/results | Identity, Academic, Finance; payroll-approved | academic/payment; payroll/SoD |
| Finance/Receivables/Payments/Refunds/Discounts/Funding | obligations, payments, allocations, refunds, journals, funds, reconciliation | approved Students/Payroll/Assets; posted/reconciled | balance/report/UI; financial/concurrency |
| Books/Inventory/Assets/Facilities | catalog, custody, stock, assets, work | Identity/Organization, Finance; movement | journal/balance; custody/history |
| Communication/Documents/Privacy | delivery, metadata/content, consent, disclosure, verification | all facts, Authorization; post-commit delivery | source facts; privacy/access |
| Audit | append-only material evidence | all modules; audit-recorded | business state; immutability |
| Reporting | metric definitions, projections, runs | all source owners; refresh | source facts; reconciliation |
| Employee Workspace | effective work context composition, tasks/approvals/deadlines/exceptions, presentation preferences | Identity, Access, Organization, HR, all relevant owner queries and post-commit events | business facts, permissions, lifecycle, financial truth, approval truth, independent shadow tasks |

Employee Workspace is an orchestration and presentation capability rather than a source-owning bounded context. It may link to owner commands but never writes another module's persistence or changes policy.

Each row is a bounded context, persistence owner, and transaction boundary. Commands are synchronous at decision points; notifications and projections are asynchronous after commit. Failure is deny/hold/retry according to error category and never cross-context direct mutation.

---

## HISTORICAL SOURCE: `implementation/05-financial-implementation-contract.md`

# Financial Implementation Contract

Fee/configuration defines eligibility and amounts by effective version. Finance creates charge lines and obligations; discounts and scholarship/funding allocations are separate approved source-linked transactions. A payment records money received; allocation links payment to obligation; refund links original payment; adjustment corrects; reversal negates a posted source; cash movement records custody; journals record balanced accounting; receivables and balances are projections; reconciliation compares source/observation sets.

Every posting requires source, period, actor, authority, scope, reason, audit, and idempotency key. Source amounts are immutable. Allocation/refund commits serialize by source and re-check remaining amount. Duplicate keys return original outcome; duplicate allocation is rejected. Closed periods reject mutation; correction/reversal is the only path. Orphan/unbalanced journals and restricted-fund reclassification are rejected. No direct balance edit exists. Finance owns financial payment and recognized payroll-liability facts; Payroll only supplies approved result/adjustment evidence. A new payroll disbursement is sourced from the Finance liability fact — never directly from a Payroll result — and its debit and credit totals must equal that immutable fact's absolute amount; the fact is source-unique, append-only, and branch-provenanced. Payroll liability recognition accepts signed adjustment/reversal evidence through the dedicated signed-money transport rule; ordinary Finance money remains non-negative. Financial corrections and staged discounts/credits/installments/gate exceptions/refunds have one active database lifecycle guard each rather than stacked first-pass and hardened guards. Employee and management workspaces may present Finance work and canonical links but may not calculate, approve, post, allocate, refund, correct, or otherwise mutate monetary truth.

Mandatory tests: amount/source invariants, duplicate/concurrent operations, period close, journal balance/linkage, reconciliation, restricted funds, authorization/SoD, rollback/retry, audit completeness.

---

## HISTORICAL SOURCE: `implementation/06-authorization-implementation-contract.md`

# Authorization Implementation Contract

The server policy decision receives user/person, Position, Assignment, Role, Permission, Scope, Policy version, target, operation, effective time, delegation, approval, and conflict data. It returns allow/deny/hold with reason and evidence. Default deny. Client checks are convenience only.

Assignments and delegations are effective-dated and automatically expire. Scope resolves Organization→Campus→Branch→Department plus explicit cross-branch assignments. Requester, beneficiary, conflicted actor, and expired delegate are excluded. Two-Owner policies require distinct eligible Owners and atomic approval counting. Emergency authority is limited, audited, expires, and triggers mandatory review. Permission/scope changes are themselves authorized and audited. Tests cover every denial and leakage case.

Employee Workspace composition consumes the effective decision; it cannot grant, cache, or infer authority. It is role-aware but not role-locked for employees with multiple positions. Workspace sections, tasks, approvals, record links, and action affordances are filtered by current employment, assignments, capabilities, delegation, lifecycle, and scope, while every sensitive action is reauthorized at command time.

---

## HISTORICAL SOURCE: `implementation/07-lifecycle-implementation-contract.md`

# Lifecycle Implementation Contract

Each owner implements its own explicit state/transition table from artifact 32. Transition command validates current state, actor/scope, preconditions, effective date, policy version, and idempotency, then atomically writes state plus transition audit. Invalid, prohibited, expired, or unknown transitions fail closed. Cancellation, rejection, expiry, reversal, appeal, and correction have explicit transitions. Historical transitions are append-only; current state never rewrites prior attribution. Derived balances/metrics have no business lifecycle. Tests enumerate allowed and forbidden transitions, replay, concurrency, and history.

Workspace work queues and action affordances consume the authoritative current lifecycle state. Suspension, termination, assignment expiry, position or branch-scope change, and delegation expiry must alter workspace composition and cannot leave stale mutation power in a cache or browser session.

---

## HISTORICAL SOURCE: `implementation/08-academic-implementation-contract.md`

# Academic Implementation Contract

Students/Admissions own verified student, admission, and enrollment. Academic owns versioned programs, levels, periods, placement attempts/evidence/results, classes, schedules, teacher delivery assignment, attendance, assessments, progression, appeals, eligibility, and certificates. HR owns teacher employment; Finance owns obligations.

Evidence (responses, attendance, submissions, scores) is immutable and may be corrected only by linked append. Official placement/progression/graduation decisions are separate authorized commands with review, preconditions, effective period, appeal, and supersession. Evidence never silently becomes a decision. Capacity/membership uses concurrency controls. Tests cover identity, enrollment, effective assignments, evidence/decision separation, appeals, lifecycle, and history.

---

## HISTORICAL SOURCE: `implementation/09-hr-payroll-implementation-contract.md`

# HR and Payroll Implementation Contract

Identity owns person; HR owns employment, position assignment, contract, compensation terms, leave, termination, and entitlement facts; Academic owns teaching/work evidence including attendance facts and their correction chains; Payroll owns period calculation/result and settlement proposal evidence; Finance owns journal/payment, recognized Payroll liability, and recorded employment settlement. Payroll snapshots effective terms/evidence, separates preparation/review/approval, and holds contract-silent and rule-missing cases for HR/Finance. Period close prevents mutation; correction appends adjustment/reversal. Termination requires clearance and access expiry. Finance recognition requires an approved Payroll source, exact signed amount, a Payroll approver distinct from the Finance recognizer, immutable employee-branch provenance, and branch-scoped Finance authorization; it never sums Payroll directly. Finance settlement insertion precedes Payroll proposal closure through the Payroll-owned approval port, so neither module becomes a second authority. Tests cover the full version lifecycle, FM-GM separation of duties, proration precision, attendance qualification and corrections, rate resolution, held cases, period races, recalculation, double-payment defense, signed liability recognition, settlement sequencing, and historical reproduction.

## Current architecture notes (authoritative)

Contracts are versioned: the Finance Manager prepares a draft version (terms evidence reference, effective window, pinned scale, compensation rules) and submits it; the General Manager approves it with evidence and a digest; approved versions and their rules are immutable at the schema level, and a change is a new version that supersedes the prior in-force version by closing its window the day before the new one starts. Compensation rules are the single authoritative compensation model, addressable by method + optional skill + optional scale with the precedence ladder exact skill×scale > skill-only > scale-only > generic; per-unit rates of one version may not overlap. Fixed monthly and allowance lines prorate by calendar-day overlap of the version window and the period (payable = amount × active days / period days, exact cent arithmetic, round half up); per-unit rates are not prorated.

Teaching volume derives from Academic delivery evidence: a delivered skill-attributed session covered by the teacher's effective assignment is payable only when it has a final attendance fact with status present or late — final meaning the uncorrected tip of the authoritative corrects_id chain, so corrections, not timestamps, resolve qualification; absent and excused never qualify. Each qualifying session is claimed exactly once per session by payroll (unique session claim; claim migration only from a superseded calculation of the same period and employment) and carries the qualifying attendance fact as its evidence reference.

Calculations snapshot the resolved version, rules and rates, scale, per-skill volumes, delivery claims with evidence, additive proration windows, and the final amount; approved results are immutable and corrections append adjustments/reversals. The retired per-kind compensation-component architecture and its work-basis evidence tables are removed from the active system, its schema, and its tests: the contract version with its compensation rules is the only compensation path, and a period with no in-force version is held contract-silent rather than falling back.

---

## HISTORICAL SOURCE: `implementation/10-reporting-implementation-contract.md`

# Reporting Implementation Contract

Every metric definition records canonical source, calculation, Financial/Academic Period semantics, filters, scope, as-of behavior, projection freshness, version, and reconciliation rule. Reporting projections are rebuildable and read-only. Finance defines revenue/receivable/payment/cash and recognized payroll-liability financial sources; Students/Academic define enrollment/attendance/success; HR/Payroll defines approved payroll source evidence; Funding and Assets define their facts. Dashboards cannot write or redefine sources. Undefined metrics are recorded as undefined, not invented. Tests compare projections to owner facts and period/scope definitions.

Employee and management workspaces may surface governed metrics and work context, but workspace cards, queues, balances, enrollment status, payroll status, approval status, and exception indicators remain projections or links. They must never redefine metrics or become authorities; freshness and source lineage are visible where relevant.

---

## HISTORICAL SOURCE: `implementation/11-integration-implementation-contract.md`

# Integration Implementation Contract

SMS, email, payment/banking, external identity, file storage, messaging, and export are adapter boundaries, not authorities. Contracts define authenticated requests, mapped business outcomes, correlation and idempotency keys, timeout/retry, dedupe, dead-letter/manual review, reconciliation, and audit. Payment timeout enters unknown/pending reconciliation, never a new unlinked payment. Authenticated duplicate webhooks are safely ignored/replayed. Vendor choices are deferred. Contract, failure, security, and reconciliation tests are mandatory.

Workspace task, reminder, notification, and exception projections may consume committed domain events and authoritative queries. They must be idempotent, correlation-linked, freshness-aware where applicable, retry-safe, and rebuildable. Delivery or projection failure may degrade presentation but cannot create workflow authority, mutate owner facts, or preserve revoked access.

---

## HISTORICAL SOURCE: `implementation/12-concurrency-idempotency-contract.md`

# Concurrency and Idempotency Contract

Repeatable commands require an idempotency key scoped to operation and source; same key returns the original result, conflicting payload is rejected. Owner transactions revalidate state at commit and serialize payment/allocation/refund source, period close/payroll, enrollment capacity, approval count, authority/scope assignment, and reconciliation runs. Conflicts return a stable retryable error; no silent merge. Jobs and webhooks deduplicate and are safe to retry. Tests use simultaneous actors, crash/retry, duplicate delivery, and period races.

---

## HISTORICAL SOURCE: `implementation/13-error-exception-contract.md`

# Error and Exception Contract

Categories are validation, authorization, business rejection, concurrency conflict, integration failure/unknown outcome, system failure, and emergency exception. Each returns stable category/code, safe message, correlation ID, and retry/hold guidance; internals and sensitive data are not exposed. Material denial, hold, emergency action, and recovery are audited. Financial unknown outcomes go to reconciliation; system recovery replays idempotent post-commit jobs, never unsafe commands.

---

## HISTORICAL SOURCE: `implementation/14-testing-implementation-contract.md`

# Testing Implementation Contract

Pyramid: fast domain/invariant/lifecycle tests; application authorization, scope, transaction, audit, privacy, and concurrency tests; contract/integration tests; reporting reconciliation and failure/recovery tests; a small end-to-end set for enrollment, academic decision, payroll, payment/refund, and disclosure. Required adversarial suites include double allocation, over-refund, expired delegation, branch leakage, SoD, history, duplicate identity, webhook retry, and configuration versioning. Migration tests verify mapping, FK/uniqueness, audit/financial reconciliation, rollback, and preservation. No tests are written in Gate 6.

Employee Workspace specifications must cover multi-position composition, effective employment and lifecycle changes, task/deadline/approval/exception prioritization, management-specific work, canonical workflow links, personalization isolation, API/web parity, projection rebuild/freshness, command-time reauthorization, accessibility, recovery, and employee-efficiency outcomes. These are intended-behavior specifications only in the current pre-runtime phase.

---

## HISTORICAL SOURCE: `implementation/15-migration-implementation-contract.md`

# Migration Implementation Contract

No migration is authorized until business preservation need is confirmed. Establish canonical schema ownership, ordered context migrations, foreign-key and uniqueness rules, append-only history/audit mapping, and quarantine for invalid records. Legacy data is classified evidence, validated against identity/relationships/status, and financial totals are independently reconciled. Cutover requires dual reconciliation, owner acceptance, point-in-time boundary, import audit, and rollback by quarantine/reversal. Never reuse legacy structure because it exists.

---

## HISTORICAL SOURCE: `implementation/16-legacy-disposition-registry.md`

# Legacy Disposition Registry

| Legacy component | Disposition | Basis |
|---|---|---|
| Existing tables/schema | UNKNOWN pending migration decision; otherwise REPLACE | Foundation owns persistence |
| Existing APIs/routes | DEPRECATE/REPLACE | not authority or contract |
| Existing services/workflows | REBUILD | behavior is untrusted |
| Existing UI/screens | REPLACE | UI never authority |
| Existing financial calculations/balances | REPLACE | Finance transaction model wins |
| Existing authorization/RBAC | REPLACE | Position+Assignment+Permission+Scope+Policy wins |
| Existing tests/fixtures | RETAIN as evidence only; rebuild verification | cannot certify new behavior |
| Historical records/data | MIGRATE only if separately approved | validate, reconcile, preserve history |
| Existing naming | UNKNOWN; use canonical registry | naming cannot define ownership |

No legacy artifact is retained as a source of truth without explicit Foundation reconciliation.

---

## HISTORICAL SOURCE: `implementation/17-implementation-sequence.md`

# Dependency-Driven Implementation Sequence

| Package | Objective/prerequisites | Scope and verification |
|---|---|---|
| 0 Contract harness | finalize non-blocking policy inputs; no production behavior | ADRs, conventions, CI gates |
| 1 Identity and Organization | canonical identity/structure | entities, commands, auth/history tests; rollback import none |
| 2 Authorization and Scope | server policy and effective assignments | permissions, delegation, Owner approvals; exhaustive auth/scope tests |
| 3 Documents/Privacy/Audit | evidence, consent, immutable audit | metadata, disclosure/export; privacy/history tests |
| 4 Students/Admissions | admission, relationships, enrollment | lifecycle and identity tests; rollback transaction |
| 5 Academic delivery | programs, classes, scheduling, attendance, placement | capacity/evidence tests; module contracts |
| 6 Academic decisions | moderation, progression, appeal, certification | decision tests and history |
| 7 HR/Teachers | employment, contracts, work basis, leave | contract/authority tests |
| 8 Payroll | periods, calculations, approvals, settlement | payroll/SoD/concurrency tests |
| 9 Finance core | periods, accounts, obligations, journals | financial invariants/reconciliation; no reports yet |
| 10 Payments and funding | payments, allocations, refunds, discounts, funds | idempotency/concurrency/restricted-fund tests |
| 11 Assets/Operations/Communication | custody, work, delivery | contract/integration tests |
| 12 Reporting | metric catalog, projections, dashboards | source/period reconciliation |
| 13 Integrations/jobs | adapters, retries, scheduled work | failure/recovery/duplicate tests |
| 14 migration/cutover (conditional) | approved migration decision and mapping | validation, reconciliation, rollback, owner acceptance |

Each package includes domain commands/queries, owned persistence changes only, invariant tests, acceptance criteria, and reversible release. No UI-first sequencing is permitted. Prerequisites are the prior package's contracts and passing verification.

---

## HISTORICAL SOURCE: `implementation/18-implementation-risk-register.md`

# Implementation Risk Register

| Risk | Severity | Control/status |
|---|---|---|
| financial duplicate/over-refund | Critical if uncontrolled | source serialization/idempotency; contract complete |
| authority/scope leakage | High if uncontrolled | server PDP, default deny, expiry tests |
| history rewrite | High if uncontrolled | append-only corrections and audit |
| undefined metric/period | Medium | metric registry; resolve before reporting package |
| RPO/RTO unknown | Medium | operational decision before resilience rollout |
| legacy migration corruption | High if migration chosen | conditional migration, quarantine/reconciliation |
| external payment uncertainty | High if uncontrolled | inquiry/reconciliation, no duplicate retry |
| overly broad module dependencies | Medium | acyclic contract graph and no shared writes |

No Critical or High readiness blocker remains; controls are implementation acceptance gates.

## Package 01 update

The absence of source/tooling is recorded as expected baseline state, not an implementation failure. It becomes a prerequisite for the next package's implementation work; no legacy code was recreated or adopted.

---

## HISTORICAL SOURCE: `implementation/19-gate-6-review.md`

# Gate 6 — Implementation Readiness and Execution Contract Review

**Date:** 2026-08-25
**Result:** `PASS WITH NON-BLOCKING OPEN ITEMS`

## Findings

- Critical blockers: **0**
- High implementation-contract blockers: **0**
- Medium: **4** — RPO/RTO/retention values, detailed metric acceptance catalog, detailed operation examples, conditional migration decision.
- Low: **0**

All authoritative entities have ownership; critical commands have authority/scope; financial operations have transaction/idempotency/concurrency boundaries; critical lifecycles and test strategies are defined; dependencies, reporting sources, security, rollback/recovery, and legacy disposition are explicit.

## Readiness score

**92/100**, suitable for architecture-to-implementation handoff. The deduction reflects documented business/operational inputs, not missing core implementation contracts.

## Owner questions

None. Remaining matters are safely deferred/configurable or must be confirmed before their affected package; no question is required to complete this gate.

## Implementation authorization

**Production implementation is NOT authorized by this review.** Gate 6 produces the implementation contract only. Separate explicit authorization is required before code, database, schema, migrations, APIs, UI, packages, or production configuration.

---

## HISTORICAL SOURCE: `implementation/20-package-01-contract-harness-checkpoint.md`

# Package 01 Checkpoint — Contract Harness and Deferred Inputs

**Package:** 01
**Status:** CERTIFIED — PASS
**Date:** 2026-08-25

## Discover

- Branch verified: `arena/01a034c7-toefl-house`.
- Repository contains documentation only: Foundation, Architecture, and Implementation Contract artifacts.
- No package manager manifest, source tree, database, schema, migration directory, test runner, lint configuration, or typecheck configuration exists in the current checkout.
- Existing implementation referenced by Foundation records is absent from this checkout; it remains untrusted evidence and was not recreated or modified.

## Map

Package 01 owns no business entities and creates no persistence. It establishes the execution controls: authoritative-document baseline, package sequence, deferred-input register, verification categories, traceability convention, checkpoint/certification template, and strict implementation boundary. No commands, queries, domain services, migrations, or business behavior are introduced.

## Verification baseline

| Check | Result | Evidence |
|---|---|---|
| branch/status | PASS | active branch verified; clean before package |
| authoritative documentation inventory | PASS | `docs/foundation`, `docs/architecture`, `docs/implementation` |
| package sequence | PASS | `docs/implementation/17-implementation-sequence.md` |
| deferred inputs | PASS | `01-implementation-readiness.md`, `18-implementation-risk-register.md` |
| typecheck | NOT APPLICABLE | no source or typecheck tooling exists |
| lint | NOT APPLICABLE | no source or lint tooling exists |
| tests | NOT APPLICABLE | no test runner or implementation exists |
| migration verification | NOT APPLICABLE | no migration/database changes |
| business behavior change | PASS | no code or production files changed |

## Attack and independent review

Attacks against premature implementation, legacy contamination, deferred-policy invention, package reordering, untracked tooling, and accidental business behavior change found no defect. Independent review confirms the package adds only documentation controls and does not authorize Package 02 or production implementation beyond this checkpoint.

## Clean and certification

No dead code, dependency, migration, debug artifact, compatibility hack, or speculative abstraction was introduced. Critical defects: 0. High defects: 0. Remaining issue: the repository has no implementation harness to execute, which is expected for this documentation-only checkpoint.

**Checkpoint:** Package 01 is certified and committed. STOP. Package 02 requires the next explicit internal checkpoint transition and is not started.

---

## HISTORICAL SOURCE: `implementation/21-implementation-quality-directive.md`

# Implementation Quality Directive — Absolute Engineering Standard

**Status:** ACTIVE — adopted as the mandatory standing standard
**Adopted by:** User directive, 2026-08-25
**Records:** Decision Ledger `D-F-101`; Architecture ADR-013 resolves the stack decision referenced below
**Applicability:** Every implementation artifact in every future package. This document does not by itself authorize any package; package authorization proceeds only through the package protocol established in `00-implementation-state.md` and `20-package-01-contract-harness-checkpoint.md`.

## Design authority

The approved Foundation → Architecture → Implementation Contract is the only design authority:

- Foundation artifacts: `docs/foundation/` (canonical domain model `19`, entity registry `29`, domain contracts `39`, decision ledger `04`, invariant registries `11`, `24`).
- Architecture artifacts: `docs/architecture/` (system architecture `01`, module and boundary map `03`, transaction boundaries `05`, financial `06`, authorization `07`, lifecycles `09`, audit/history `13`, concurrency `17`, invariant registry `24`, ADR `23`).
- Implementation contracts: `docs/implementation/` (implementation contract `02`, command/query registry `03`, module contracts `04`, financial `05`, authorization `06`, lifecycle `07`, concurrency/idempotency `12`, error `13`, testing `14`, migration `15`).

Where a rule in this directive is more specific than a contract artifact, the stricter rule governs the implementation without redefining the contract.

## 1. No legacy-driven design

Existing code is untrusted evidence. Do not copy, preserve, imitate, or adapt legacy architecture merely because it already exists. The approved Foundation → Architecture → Implementation Contract is the only design authority. Legacy code may be reused only when it independently satisfies the approved contracts and passes verification.

## 2. No ordinary or temporary code

Do not write prototype-quality, transitional-quality, demo-quality, shortcut, placeholder, speculative, or "good enough" implementation. Every implementation must be production-grade from the moment it is created. Do not create code with the intention of "cleaning it later."

## 3. Clean vocabulary is mandatory

Use one canonical vocabulary across: domain model, database, server, API, services, commands, queries, events, authorization, UI, tests, and documentation. Never create multiple names for the same business concept. Never use vague names such as `data`, `item`, `thing`, `temp`, `misc`, `helper`, `manager`, `process`, `handler`, or `service` unless the term has a precise domain meaning. Names must communicate business meaning and responsibility.

## 4. One concept — one name

If a business concept has a canonical name in the domain model, use that name everywhere unless a technical boundary explicitly requires another form. Do not introduce synonyms merely for stylistic variation. Vocabulary consistency is an architectural invariant.

## 5. Comments

Do not write comments such as package/phase/step markers, "temporary", TODO, FIXME, "later", "quick fix", "workaround", "generated by AI", "this is complicated", "do not touch", "magic", or "hack". Do not use comments to explain obvious code. Comments are permitted only when they explain a non-obvious business invariant, architectural constraint, external-system limitation, concurrency requirement, security requirement, or other information that cannot be expressed clearly through the code itself. Comments must explain WHY, not narrate WHAT the code does.

## 6. Code structure

Every module must have: one clear responsibility, explicit ownership, explicit dependencies, controlled public surface, no accidental coupling, no hidden state, and no duplicated business authority. Avoid god classes, god services, god controllers, giant utility modules, circular dependencies, speculative abstractions, and unnecessary framework layers.

## 7. Business logic

Business rules belong to their authoritative domain boundary. Do not duplicate the same business rule across UI, controller, route, service, database trigger, report, or background job. The authoritative rule must have one owner. Consumers may enforce the contract but must not silently redefine it.

## 8. Financial code

Financial logic receives the highest control standard. Never: mutate authoritative balances directly; overwrite posted financial transactions; silently delete financial history; bypass allocation rules; bypass reconciliation; create unlinked financial records; create duplicate financial effects; or calculate financial truth independently in multiple modules. Financial effects must remain transaction-based, source-linked, auditable, idempotent where required, concurrency-safe, and reversible through controlled transactions.

## 9. Authorization

Authorization must remain server-enforced and default-deny. Never trust frontend visibility, route naming, UI restrictions, client-provided permissions, client-provided scope, or hidden form fields. Every protected operation must resolve authorization from the canonical Position + Assignment + Permission + Scope + Policy model.

## 10. Lifecycles

Lifecycle transitions must be explicit. Never change state by directly mutating a status field when the domain contract requires a transition. Every transition must respect: current state, allowed transition, authority, scope, effective time, prerequisites, side effects, and audit requirements.

## 11. Historical integrity

Historical facts must remain historically correct. Do not rewrite historical organizational attribution, financial facts, academic decisions, employment history, approvals, or audit records merely because the current organization structure has changed. Current state and historical state are different concepts.

## 12. Error handling

Errors must be deterministic, typed where appropriate, meaningful, and recoverable according to the implementation contract. Never swallow errors. Never silently continue after a failed authoritative operation. Never use generic success responses for failed business operations.

## 13. Concurrency

Assume concurrent execution wherever the domain permits it. Do not rely on frontend disabling, timing assumptions, single-user behavior, in-memory locks alone, or "this normally cannot happen." Critical operations must enforce their invariants at the authoritative transaction/persistence boundary.

## 14. Idempotency

Any operation exposed to retries, duplicate requests, webhook delivery, network uncertainty, or background execution must have the idempotency behavior defined by the implementation contract. Repeated execution must not create repeated financial or business effects.

## 15. Database

The database is not a passive storage dump. Schema constraints must protect invariants that must remain true regardless of application behavior. Do not use the database as a second competing business-rule engine. Do not rely exclusively on application checks where a structural constraint can safely protect the invariant.

## 16. API

APIs expose domain capabilities, not arbitrary database operations. Do not create generic CRUD endpoints merely because an entity exists. Commands represent intentional business actions. Queries represent controlled read models. API contracts must use canonical vocabulary and explicit authorization.

## 17. Tests

Tests must verify behavior and invariants, not implementation trivia. Prioritize: business rules; authorization; scope; lifecycle transitions; financial invariants; concurrency; idempotency; historical integrity; reconciliation; privacy; failure recovery; cross-module contracts. Do not create meaningless tests solely to increase coverage.

## 18. UI

The UI must consume authoritative server behavior. Do not recreate business truth in the frontend. Do not duplicate server-side calculations merely for convenience when the result is authoritative. UI terminology must exactly follow canonical domain vocabulary. The interface must be coherent, predictable, accessible, and operationally efficient.

## 19. Reusability

Reuse only when the abstraction represents a genuine stable concept. Do not create abstraction for the sake of abstraction. Prefer a small number of strong domain abstractions over a large collection of generic helpers.

## 20. Dependency discipline

Every dependency must have a reason. Do not install packages merely because they are popular, convenient, or familiar. Before adding a dependency, verify: actual requirement, architectural fit, maintenance quality, security implications, licensing, and long-term necessity.

## 21. Documentation

Documentation must describe the actual system. Never document behavior that the implementation does not enforce. Never implement behavior that contradicts the approved contracts.

## 22. No silent decisions

If implementation encounters a business decision genuinely not defined by the Foundation, Architecture, or Implementation Contract: STOP that affected decision. Do not invent policy. Do not infer authority from legacy code. Do not silently choose a business rule. Use the established enterprise default only where the contract explicitly permits it.

## 23. Specialist standard

Every module must be implemented according to the engineering discipline of that domain: finance as financial systems engineering; authorization as security/access-control engineering; academic as academic information-system design; payroll as payroll/accounting control engineering; reporting as data/analytics engineering; privacy as privacy/security engineering; infrastructure as reliability engineering. Each module must be reviewed against the failure modes and invariants native to its discipline.

## 24. Quality bar

The implementation must optimize for: correctness > integrity > security > auditability > maintainability > clarity > consistency > performance > convenience. Never reverse this priority for speed.

## 25. No beauty without structure

"Beautiful code" means: precise naming, small coherent units, explicit contracts, predictable behavior, minimal duplication, controlled dependencies, readable flow, strong invariants, and meaningful abstractions. Not decorative architecture.

## 26. No unnecessary complexity

The implementation must be as simple as possible, but never simpler than the business and architectural constraints allow. Do not add layers, patterns, abstractions, queues, caches, events, tables, services, or dependencies without a demonstrated architectural reason.

## 27. Zero known defects at handoff

A package is not complete merely because it compiles. Before declaring a package complete, run the applicable gates from the Verification Gate Matrix below: typecheck, lint, unit tests, integration tests, invariant tests, authorization tests, lifecycle tests, financial tests where applicable, concurrency/idempotency tests where applicable, migration/schema validation where applicable, static analysis where available, contract verification, adversarial review, and regression verification. All failures must be resolved or explicitly classified according to the approved risk process.

## 28. Clean handoff

At completion of each implementation package: no unexplained warnings, no dead code, no abandoned experiments, no temporary files, no placeholder implementations, no commented-out code, no duplicated business authority, no undocumented deviation, clean working tree, reproducible verification results.

## 29. Stop condition

If a Critical or High defect is discovered: STOP the affected implementation path. Repair the authoritative design or implementation. Re-run the relevant verification. Do not continue by building additional layers on top of a known defect.

## 30. Final principle

The implementation must not merely "work." It must be: correct by contract, secure by default, consistent by vocabulary, auditable by design, resilient under failure, safe under concurrency, historically accurate, and maintainable by another expert without requiring the original author's memory. No implementation is accepted because it looks complete. It is accepted only when its behavior, structure, invariants, tests, and verification evidence prove that it satisfies the approved system contract.

## Verification gate matrix

The mandatory gates for declaring a package complete, per clause 27 and `14-testing-implementation-contract.md`. A gate marked REQUIRED for a package must pass with zero failures; a gate marked NOT APPLICABLE must be recorded as such with reason in the package checkpoint.

| Gate | Domain modules | Authorization/scope | Financial modules | Reporting/integration | Infrastructure-only |
|---|---|---|---|---|---|
| typecheck | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| lint | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| static analysis | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| unit tests | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| integration tests | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| invariant tests | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| authorization tests | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| lifecycle tests | REQUIRED | REQUIRED | REQUIRED | NOT APPLICABLE | NOT APPLICABLE |
| financial tests | NOT APPLICABLE | NOT APPLICABLE | REQUIRED | NOT APPLICABLE | NOT APPLICABLE |
| concurrency/idempotency tests | REQUIRED where retryable | REQUIRED | REQUIRED | REQUIRED | REQUIRED where durable |
| migration/schema validation | REQUIRED | REQUIRED | REQUIRED | REQUIRED | NOT APPLICABLE |
| contract verification | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| adversarial review | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |
| regression verification | REQUIRED | REQUIRED | REQUIRED | REQUIRED | REQUIRED |

## Clean handoff checklist

Before a package checkpoint may claim certification, all of the following must hold (clause 28):

1. All applicable verification gates pass, or failures are classified in the approved risk register with an owner and a date.
2. No unexplained warnings from any tool in the verification set.
3. No dead code, abandoned experiments, temporary files, placeholders, commented-out code, or debug artifacts in the package scope.
4. No duplicated business authority across modules, services, controllers, database, or UI.
5. Every deviation from a contract artifact is recorded as an explicit, reviewed decision — never silent.
6. Working tree is clean after the package commit.
7. Verification results are reproducible by the documented commands in the package checkpoint.

## Stop condition procedure

On discovery of a Critical or High defect (clause 29):

1. STOP the affected implementation path immediately; no new layers are built on the defect.
2. Classify the defect and repair the authoritative design or implementation.
3. Re-run every verification gate affected by the repair.
4. Record the defect, repair, and verification evidence in the package risk record before continuing.

## Scope boundary

This directive is a quality and verification contract. It creates no business policy, selects no further technology beyond the stack recorded in ADR-013, and authorizes no package. The next package begins only through an explicit checkpoint transition under the package protocol.

---

## HISTORICAL SOURCE: `implementation/22-environment-blocker-report.md`

# Environment Blocker Report — Laravel/Composer Unobtainable

**Status:** CLOSED (remediated 2026-08-25) — the approved stack is now reproducibly obtainable. See "Remediation" below.
**Original status:** OPEN — implementation blocker. Not a technology decision.
**Date:** 2026-08-25
**Environment:** sandboxed build environment for this repository session

## Blocked capability

ADR-013 (D-F-100) selected **PHP with the Laravel framework** and PostgreSQL. Laravel is distributed exclusively through Composer (Packagist). Composer itself is distributed from `getcomposer.org`; its metadata/metadata sources and package tarballs resolve through `repo.packagist.org`, `packagist.org`, and `raw.githubusercontent.com`. All of these endpoints are required to obtain Laravel or any Composer-based tooling (PHPUnit, phpstan, psr/log, symfony components, and so on).

## Verification evidence (2026-08-25)

| Probe | Result |
|---|---|
| `curl -sSI https://repo.packagist.org/packages.json` | `curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to repo.packagist.org:443` |
| `curl -sSI https://getcomposer.org/download/latest-stable/composer.phar` | `curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to getcomposer.org:443` |
| `curl -sSI https://raw.githubusercontent.com/composer/composer/2.7.7/composer.json` | `curl: (35) OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to raw.githubusercontent.com:443` |
| `which composer` / `composer.phar` on host | not present anywhere on the system |

`SSL_ERROR_SYSCALL` on connect indicates the network path is blocked, not a certificate or TLS-configuration issue. Retries and alternate mirror probing are not available in this environment. The PHP runtime itself was verified operational (PHP 8.2.27 built from the official release tarball; see below).

## Impact

1. **Laravel framework: unobtainable.** No package manager, no framework, no framework ecosystem.
2. **Composer-based quality tooling: unobtainable.** PHPUnit, PHPStan, and similar verification tools named in the Verification Gate Matrix cannot be installed as dependencies.
3. **The approved architecture is not Laravel-dependent in content.** ADR-001–012, the module implementation contracts (`04`), authorization contract (`06`), lifecycle contract (`07`), error contract (`13`), concurrency/idempotency contract (`12`), testing contract (`14`), migration contract (`15`), and the Implementation Quality Directive define behavior, ownership, and invariants — not framework APIs.

## Correction record (2026-08-25) — this report records a blocker, not a decision

An earlier version of this report (and ADR-013-A / Decision Ledger `D-F-102`) described the framework-free variant as resolved by a user decision. **That claim is corrected:** no user decision selected the framework-free variant. The approved technology decision remains **PHP + Laravel + PostgreSQL as a strict modular monolith (ADR-013, D-F-100)**, which is authoritative.

- ADR-013-A is **REJECTED/WITHDRAWN** and does not take effect (ADR records, `docs/architecture/23-architecture-decision-records.md`).
- Decision Ledger `D-F-103` records the correction; `D-F-102` stands only for its environment-blocker evidence.
- This report is preserved as evidence that Laravel could not currently be obtained. It is an **environment blocker**, not authorization to replace Laravel and not a technology decision.
- **Current status: IMPLEMENTATION BLOCKED BY ENVIRONMENT.** Package 02 (Identity and Organization) must not begin production implementation while the approved Laravel dependency cannot be reproducibly obtained in the build environment. No framework substitution (custom framework, Node.js, Express, Symfony, another PHP framework, or any other framework) is authorized.

## Environment workarounds executed

Because the environment blocks package distribution, the following were built from official sources and installed under `/opt/th` (outside the repository). These are environment workarounds only; they do not alter the approved technology decision and do not constitute production implementation:

| Component | Version | Source | Installed at |
|---|---|---|---|
| PHP CLI | 8.2.27 | official `php/web-php-distributions` release tarball | `/opt/th/php` |
| libpq (pdo_pgsql/pgsql client) | REL_16_4 era shared lib | npm `@embedded-postgres/linux-x64` bundled `libpq.so.5.18` + REL_16_4 headers | `/opt/th/pgsql` |
| PostgreSQL server (runtime for tests) | 18.4.0-beta-era binaries | npm `@embedded-postgres/linux-x64` (`initdb`, `pg_ctl`, `postgres`) | `/tmp/npm-inspect/package/native` |
| zlib, OpenSSL, curl, oniguruma, libxml2, pkgconf | 1.3.1 / 3.0.13 / 8.5.0-DEV / 6.9.9 / 2.16.0 / 2.1.0 | official release tarballs built from source | `/opt/th` |

PHP 8.2.27 runs with the required extension set verified: `pdo_pgsql`, `pgsql`, `mbstring`, `curl`, `openssl`, `bcmath`, `pcntl`, `posix`, `xml`, `dom`, `simplexml`, `iconv`, `zlib`.

## Verification consequence (original)

Per the Quality Directive Verification Gate Matrix (clause 27), production verification under ADR-013 uses Laravel-native tooling (Artisan, PHPUnit, Laravel Pint, Larastan or equivalent) plus the migration validator. That tooling requires Composer/Packagist and was therefore not runnable while this blocker stood.

## Remediation (2026-08-25) — blocker closed

The blocker is **CLOSED**: the approved stack (PHP + Laravel + PostgreSQL, ADR-013) is now reproducibly obtainable and operational in this build environment, using only legitimate sources.

**Discovered root causes (from the remediation work):**

1. **Packagist hosts (`packagist.org`, `repo.packagist.org`) and `getcomposer.org` are unreachable** from this environment (`SSL_ERROR_SYSCALL` on connect — network-path block, not a certificate problem). This made the standard Composer path unusable. These hosts remain blocked; this is an egress property of the sandbox, not a configurable issue.
2. **`raw.githubusercontent.com` / `objects.githubusercontent.com` / `release-assets.githubusercontent.com` are unreachable** (`SSL_ERROR_SYSCALL`), blocking raw-file, object-CDN, and release-asset downloads.
3. **`github.com`, `api.github.com`, and `codeload.github.com` are reachable** with verified TLS (`ssl_verify_result=0`), including git smart-HTTP clones.

**Remediation (all within the legitimate-source rule):**

1. **Composer 2.10.2** bootstrapped from its official repository `github.com/composer/composer` (annotated release tag `2.10.2`, commit `8d4439f572a97670a9edc039eb3b093cc976b4bc`), with its runtime dependencies installed from their official GitHub repositories at composer's own committed `composer.lock` references. Runs as `/opt/th/dev/bin/composer`.
2. **Project dependency acquisition:** project `composer.json` disables Packagist and declares the 75 packages of the Laravel 12.67.0 dependency closure as `vcs` repositories pointing at each package's **canonical official GitHub repository** (e.g. `github.com/laravel/framework`), each with `"no-api": true` so Composer uses the generic git driver (one verified-TLS git clone per repository, zero `api.github.com` metadata calls — the API metadata route was rate-limited and is not used).
3. **Laravel 12.67.0 installed through that Composer** with a committed `composer.lock` (73 packages, all from canonical official sources; `composer audit` reports **no known security advisories**).
4. **PHP 8.2.27** (official release tarball) with all Laravel-required extensions (`ext-ctype, ext-filter, ext-hash, ext-mbstring, ext-openssl, ext-session, ext-tokenizer`, plus `pdo_pgsql`, `pgsql`, `curl`, `dom`, `xml`, `fileinfo`, `iconv`, `zlib`, `bcmath`, `pcntl`, `posix`) verified present.
5. **PostgreSQL 18.4** server running locally; connectivity verified both standalone (PDO) and **through Laravel** (`php artisan db:show` connects to database `toefl_house` as `postgres`; `select version()` returns `PostgreSQL 18.4`).

**Verified results (evidence in `docs/implementation/23-environment-readiness.md`):**

- `php -v` → PHP 8.2.27 (CLI, NTS)
- `composer --version` → Composer 2.10.2
- `php artisan --version` → Laravel Framework 12.67.0 (boots)
- `composer validate` → `composer.json is valid`; lock content-hash matches
- `composer install --dry-run` from lock → in sync (reproducible)
- `php artisan db:show` → connects to `toefl_house` as `postgres` (Laravel ↔ PostgreSQL OK)
- TLS verification enabled on every transfer; no `secure-http=false`; no insecure source

**Constraints honored during remediation:** no third-party Laravel archives, no unverified mirrors, no unofficial bundles, no copied vendor trees of unknown provenance, no TLS-verification bypass, no insecure HTTP package sources, no arbitrary internet workarounds. All sources are the packages' canonical official GitHub repositories or the official `php/web-php-distributions` release tarball.

**Final status:** **ENVIRONMENT READY** (2026-08-25). Package 02 (Identity and Organization) remains **NOT STARTED**; it may begin per the standing authorization now that the readiness checkpoint has passed, following the mandatory Package 02 internal sequence. The environment is reproducible: committed `composer.json` + committed `composer.lock` + documented bootstrapping steps reproduce the same vendor tree from the same pinned references.

## Closure condition (original, superseded by Remediation above)

This blocker closes only when Composer/Packagist (and therefore Laravel and its tooling) are reproducibly obtainable in a maintained build environment. Until then, production implementation under ADR-013 is **IMPLEMENTATION BLOCKED BY ENVIRONMENT**. — *Met 2026-08-25 via the remediation above.*

---

## HISTORICAL SOURCE: `implementation/23-environment-readiness.md`

# Environment Readiness — Laravel Acquisition Plan and Record

**Status:** ENVIRONMENT READY — checkpoint passed (verification below)
**Date:** 2026-08-25
**Task:** Environment-readiness only. Package 02 (Identity and Organization) remains NOT STARTED.

## Approved technology (ADR-013, authoritative)

- Architecture: Strict Modular Monolith
- Application: **PHP + Laravel**
- Database: **PostgreSQL**
- No framework substitution is authorized.

## DISCOVER results (reproduced independently on 2026-08-25)

| Item | Finding |
|---|---|
| PHP | 8.2.27 (CLI, NTS) at `/opt/th/php/bin/php` (built from official `php/web-php-distributions` release tarball) |
| PHP extensions present | Core, PDO, Phar, Reflection, SPL, SimpleXML, bcmath, ctype, curl, date, dom, fileinfo, filter, hash, iconv, json, libxml, mbstring, openssl, pcntl, pcre, pdo_pgsql, pgsql, posix, random, session, standard, tokenizer, xml, xmlreader, xmlwriter, zlib |
| Composer | not installed anywhere; no composer.phar, no COMPOSER_HOME cache, no vendor directory on the host (scanned) |
| Packagist | unreachable: `curl` fails with `SSL_ERROR_SYSCALL` on `packagist.org:443`, `repo.packagist.org:443` (TCP/TLS reset by egress filter; not a certificate problem) |
| getcomposer.org | unreachable (`SSL_ERROR_SYSCALL`) |
| raw.githubusercontent.com / objects.githubusercontent.com / release-assets.githubusercontent.com | unreachable (`SSL_ERROR_SYSCALL`) |
| GitHub (github.com, api.github.com, codeload.github.com) | **reachable**, TLS verified (`ssl_verify_result=0`), HTTP 200 |
| git smart HTTP to github.com | **works** (`git ls-remote https://github.com/symfony/console.git` returns tags) |
| DNS | resolves for all probed hosts (packagist.org, repo.packagist.org, getcomposer.org, github.com, api.github.com, codeload.github.com) |
| Proxy/environment variables | none configured |
| IPv6 egress | not available |
| CA certificates | system trust store used by curl/OpenSSL; TLS verification succeeds on reachable hosts |
| PostgreSQL | 18.4 server running (binaries from npm `@embedded-postgres/linux-x64`, `initdb`/`pg_ctl`/`postgres`); PHP `pdo_pgsql` verified connecting |

## Laravel version selection (recorded before installation)

**Selected: Laravel `12.67.0` (laravel/framework v12.67.0).**

Compatibility basis:
- `laravel/framework` `v13.26.1` (current major) requires `"php": "^8.3"` — **incompatible** with the available PHP 8.2.27.
- `laravel/framework` `v12.67.0` (latest v12.x) requires `"php": "^8.2"` and extensions `ext-ctype, ext-filter, ext-hash, ext-mbstring, ext-openssl, ext-session, ext-tokenizer` — all present in PHP 8.2.27. PostgreSQL is used through `pdo_pgsql` (present and verified).
- Laravel 12 is therefore the highest Laravel major compatible with the available PHP 8.2 runtime; it is a supported release line (12.x receives maintenance per the project's support policy).
- Additional runtime needs verified present: `ext-json` (core in 8.2), `ext-dom`, `ext-xml`, `ext-fileinfo`, `ext-curl`, `ext-zlib`.

## Dependency acquisition method (legitimate and reproducible)

Packagist and getcomposer.org are unreachable; GitHub (github.com, api.github.com, codeload.github.com) is reachable with verified TLS. The acquisition method therefore uses **the canonical official sources**:

1. **Composer 2.10.2** is bootstrapped from its official source repository `github.com/composer/composer` (tag `2.10.2`) with its runtime dependencies installed from their **official GitHub repositories** at the exact references pinned in composer's own committed `composer.lock`.
2. **Laravel 12.67.0** is installed through that Composer using **official package repositories** (`vcs` type pointing at each package's canonical GitHub repository, e.g. `github.com/laravel/framework`), with the Packagist repository disabled in the project configuration.
3. A `composer.lock` is produced and committed so every future `composer install` is reproducible from pinned references.

Constraints honored:
- No third-party Laravel archives, no unverified mirrors, no unofficial bundles, no copied vendor trees of unknown provenance.
- TLS certificate verification is never disabled; all transport is HTTPS.
- All package sources are the packages' official GitHub repositories (the same canonical sources Packagist itself mirrors).
- The resulting environment is reproducible: pinned references (composer.lock) + committed project dependency configuration + documented commands.

## Security verification plan

- TLS verification active on every transfer (`ssl_verify_result=0` on GitHub hosts; failure mode observed on blocked hosts is connect-level, not certificate).
- Composer source acquired from the official `composer/composer` repository at a signed/annotated release tag `2.10.2`; dependencies pinned by the official `composer.lock`.
- Dependency integrity enforced by Composer's lock content-hash and pinned commit references at install time.
- No insecure HTTP package source is used; no `secure-http=false` override.

## Files to be created (minimum framework bootstrap only)

- `composer.json` — project dependency configuration (require `php ^8.2`, `laravel/framework ^12.67`; vcs repositories; packagist disabled)
- `composer.lock` — pinned dependency graph (reproducibility)
- `artisan` — Laravel CLI entry point
- `bootstrap/app.php`, `bootstrap/providers.php` — minimal framework bootstrap
- `config/app.php`, `config/database.php`, `config/logging.php` — minimum config for boot + PostgreSQL connection
- `.env.example` (committed) and `.env` (local, gitignored)
- `.gitignore` — excludes `vendor/`, `.env`, and runtime artifacts
- `docs/implementation/23-environment-readiness.md` — this record (updated at completion with verification results)

No domain entities, migrations, controllers, routes, commands, queries, authorization logic, UI, or business services are created.

## Verification plan (at completion)

- `php -v` (8.2.27), `composer --version` (2.10.2), `php artisan --version` (Laravel Framework 12.67.0)
- required extensions listed by `php -m`
- `php artisan db:show` establishes the approved PostgreSQL connection
- `composer validate` (lock integrity), `composer install` reproducibility from lock
- security verification per above; remaining blockers recorded

## Verification results (2026-08-25) — checkpoint PASSED

All checks executed in this environment (PHP 8.2.27 at `/opt/th/php/bin/php`; `COMPOSER_HOME=/opt/th/composer-home`; `LD_LIBRARY_PATH` includes `/opt/th` runtimes):

| # | Check | Command | Result |
|---|---|---|---|
| 1 | PHP version | `php -v` | **PHP 8.2.27 (cli) (built: Aug 24 2026) (NTS)** |
| 2 | Composer version | `composer --version` | **Composer version 2.10.2 2026-07-01** (bootstrapped from official `github.com/composer/composer` tag `2.10.2`) |
| 3 | Laravel boot | `php artisan --version` | **Laravel Framework 12.67.0** (minimal `bootstrap/app.php`; no domain code) |
| 4 | Required extensions | `php -m` | **ctype, filter, hash, mbstring, openssl, session, tokenizer** (Laravel 12 requirement) + pdo, pdo_pgsql, pgsql, curl, dom, fileinfo, xml, pcntl, posix, bcmath, iconv, zlib — **all present** |
| 5 | Dependency resolution | `composer update` | **73 packages resolved and installed from canonical official GitHub vcs repositories** (all with `"no-api": true`; Packagist disabled) |
| 6 | Lock integrity | `composer validate --no-check-publish` | **`composer.json is valid`** (lock content-hash consistent) |
| 7 | Reproducibility | `composer install --dry-run` | **in sync** — no install/update/remove required (lock-pinned vendor tree) |
| 8 | Dependency security | `composer audit` (during install) | **No security vulnerability advisories found** |
| 9 | PG connectivity standalone | PHP PDO `pgsql:host=127.0.0.1;port=5432` | **PostgreSQL 18.4** on `toefl_house` (database created, `select version()` OK) |
| 10 | **Laravel ↔ PostgreSQL** | `php artisan db:show` + `select version()` via Laravel | **`Database toefl_house`, `Username postgres`; `PostgreSQL 18.4 on x86_64-pc-linux-gnu`; DDL through Laravel OK** |
| 11 | TLS/security | all transfers | TLS verification active on every transfer (`ssl_verify_result=0` on reachable hosts); no `secure-http=false`, no insecure source, no TLS bypass |

**Dependency acquisition method (legitimate and reproducible):** Composer 2.10.2 bootstrapped from the official `composer/composer` repository at annotated tag `2.10.2` with its own committed lock-pinned dependencies; the project `composer.json` disables Packagist and declares each package of the Laravel 12.67.0 closure as a `vcs` repository at its canonical official GitHub repository with `"no-api": true` (generic git driver — one verified-TLS clone per repository, no `api.github.com` metadata calls); `composer.lock` is committed so every future install reproduces the exact pinned tree. No third-party archives, no unverified mirrors, no unofficial bundles, no copied vendor trees, no TLS-verification bypass.

**Network/config issue discovered and remediation:** Packagist hosts (`packagist.org`, `repo.packagist.org`), `getcomposer.org`, and `raw.githubusercontent.com` are unreachable from this sandbox (`SSL_ERROR_SYSCALL` — network-path block). `github.com`/`api.github.com`/`codeload.github.com` are reachable with verified TLS. Remediation: use official GitHub VCS sources via git clones (`no-api`), which are the canonical sources Packagist itself mirrors. See `docs/implementation/22-environment-blocker-report.md` (closed by this remediation).

**Boot success:** `php artisan --version` and a full framework boot (`Illuminate\Foundation\Application::configure` minimal bootstrap) succeed; `php artisan about` reports environment `local`, application **TOEFL House**.

**Package 02 untouched:** no domain entities, migrations, controllers, routes, commands, queries, authorization logic, UI, or business services were created. The only application files are the minimum framework bootstrap (`artisan`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/app.php`, `config/database.php`, `config/logging.php`, `.env.example`, `.gitignore`) and the dependency manifest (`composer.json`, `composer.lock`).

## Possible final states

- **ENVIRONMENT READY** — only when Laravel is reproducibly obtainable and operational under PHP + Laravel + PostgreSQL, with PostgreSQL connectivity verified through Laravel.
- **ENVIRONMENT BLOCKED** — if no legitimate reproducible acquisition path exists.

## Final status (2026-08-25)

**ENVIRONMENT READY** — all verification checks above passed; the approved stack (PHP 8.2.27 + Laravel 12.67.0 + PostgreSQL 18.4) is reproducibly obtainable and operational. The environment blocker (`docs/implementation/22-environment-blocker-report.md`) is **CLOSED** by this remediation.

Package 02 (Identity and Organization) remains **NOT STARTED**; per the standing authorization it may begin following the mandatory Package 02 internal sequence now that this readiness checkpoint has passed. The technology decision is unchanged and authoritative: **PHP + Laravel + PostgreSQL, strict modular monolith (ADR-013 / D-F-100)**; no framework substitution is authorized.

---

## HISTORICAL SOURCE: `implementation/24-package-02-identity-organization-checkpoint.md`

# Package 02 Checkpoint — Identity and Organization

**Package:** 02 — Identity and Organization
**Status:** CERTIFIED — PASS
**Date:** 2026-08-25
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack (PHP 8.2.27 + Laravel 12.67.0 + PostgreSQL 18.4)

## Discover

- Environment restored through the committed P02 recovery mechanism (`docs/environment/P02-environment-recovery.sh --recover`; artifacts published on release `p02-artifacts`, verified by GitHub asset digests against the generated manifest).
- Implementation evidence search (authoritative, recorded in session): no Laravel implementation existed in this checkout, any pushed branch (16 heads inspected), any tag, pull request, release, or Actions artifact. The previously recorded "52 tests / 229 assertions" R3-closure state was sandbox-local to a prior session and was destroyed by platform snapshot resets before it was ever committed. Per the standing instruction, Package 02 was therefore implemented from the governance records, not recovered.
- Governance inputs consumed: `docs/implementation/02,03,04,06,07,12,13,14`; `docs/foundation/19,29,30,31,32,33,39,43`; `docs/implementation/17` sequence; baseline `docs/environment/P02-environment-baseline.md`.

## Map (implemented scope)

- **Organization module** (`app/Modules/Organization`): `Organization`, `Campus`, `Branch`, `Department` units with the registry-32 lifecycle (`draft→active`, `active↔suspended`, `active→closed`, `closed→reopened→active`), effective-dated campus attribution (`CampusAssignment`), and commands create/activate/suspend/reactivate/close/reopen/rename/transfer under the two-Owner authority chain of registry 33. Read model `EffectiveStructureQuery` resolves effective structure as of a day, scope-filtered, read-only.
- **Identity module** (`app/Modules/Identity`): `Person` (verification writes the canonical identity key exactly once) and `UserAccount` (one active account per verified person; deactivate, never erase) with the registry-03 command family verify/link/deactivate and the read model `PersonDirectoryQuery`.
- **Audit module** (`app/Modules/Audit`): append-only `AuditEvent` written in the owning transaction (`AuditRecorder`); material denials committed after rollback (`AttemptedOperation`); database trigger `audit_events_append_only` gives structural protection.
- **Shared kernel** (`app/Support`): error taxonomy (categories/codes/correlation id/retryability per contract 13), `AccessDecision` port with default-deny `AuthorizationGate` (the authorization package replaces the adapter; no role/permission tables are created here), idempotency store keyed by operation+key with payload-hash conflict rejection (contract 12), effective-period value object, UUID v4 identifier source.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-25) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse` — level 6, 39 files, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint --test` — `PASS … 71 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (78 tests, 371 assertions)** |
| integration tests | PASS | feature suites exercise commands → PostgreSQL schema → audit evidence atomically |
| invariant tests | PASS | lifecycle matrix, one-open-campus-attribution (partial unique index), single verified identity, one active account, audit append-only (model + DB trigger) |
| authorization tests | PASS | default-deny, scope coverage/leakage, two distinct Owners, single-actor exclusion, initiator capability, out-of-scope approval, denial audit |
| lifecycle tests | PASS | full registry chain incl. `closed→reopened→active` double transition, forbidden transitions fail closed, failed transition leaves state and audit unchanged |
| financial tests | NOT APPLICABLE | no financial module in Package 02 scope |
| concurrency/idempotency tests | PASS | structural impossibility of two open attributions; repeat command returns original outcome (single audit/fact); same key + different payload rejected |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` (partial unique indexes, lifecycle/account CHECK constraints); migrations exercised per test via `DatabaseMigrations`; dev database `toefl_house` migrated (`php artisan migrate --force`) |
| contract verification | PASS | command contract fields, module ownership, boundary contract 43 (unverified identity rejected with audit), queries never mutate |
| adversarial review | PASS | see below |
| regression verification | PASS | full suite re-run after every repair; final run green |

## Attack (adversarial verification)

Vectors executed against the implementation, all failing closed:

1. Duplicate verified identity (second person, same identity key) — business rejection `identity.duplicate_verified_person`, backed by partial unique index.
2. Re-verification of a verified person — rejected.
3. Second active account for one person / username collision across persons — rejected by domain + persistence boundary.
4. Structure decision with one Owner, or Owners overlapping initiator/reviewer, or a single actor holding every role — denied (`organization.structure.single_actor` / `owner_count`).
5. Out-of-scope Owner approval (branch leakage) — denied.
6. Unprivileged identity verifier — denied, denial committed as audit evidence.
7. Audit rewrite attempts through the model and through raw SQL `UPDATE`/`DELETE` — blocked by model guard and database trigger.
8. Forbidden lifecycle paths (`draft→suspended`, `suspended→closed`, double close, direct `closed→active`) — fail closed; state and audit unchanged.
9. Transfer to the same campus, transfer date overlapping history — rejected.
10. Idempotency-key reuse with a different payload — rejected.

Defects found by verification and repaired during this package (quality directive §29 stop-and-repair, then full re-run): `array_unique` boolean flag TypeError in the authority chain, UUID version/variant bits not masked in the identifier source, and two namespace/type errors in test wiring. All repairs were followed by full-suite regression.

## Independent review

Reviewed against the contracts as a separate pass: universal command contract satisfied per command (actor, operation, target, scope, effective time where material, correlation id, idempotency key, reason on deactivation; one owning transaction per fact+audit). Source-of-truth registry: no module writes another module's persistence; Organization owns structure, Identity owns account facts, Audit append-only. No UI, routes, or generic CRUD were introduced (API surface is deferred with the packages that need it). Recorded reviewed decisions (not silent, per directive §22):

1. **Authority evidence**: Package 02 validates capability evidence through the `AccessDecision` port with default deny; position/assignment/permission resolution belongs to the Authorization package (sequence row 2). No parallel access system was created.
2. **Suspend/reactivate** are treated as material structure decisions requiring the full two-Owner chain (registry 33 lists create/rename/transfer/close/reopen; suspension is the same family — fail-closed interpretation).
3. **Person record intake** (creating the unverified person row) is not a Package 02 command (registry 03 lists verify/link/deactivate); tests create fixture rows directly. Intake belongs to the People/Admissions boundary.
4. **Person merge** (source-of-truth "merge only by verified decision") is deferred: no merge command exists in the registry; duplicate-verified-identity is prevented, so no merge is currently reachable.
5. **Financial gates** marked NOT APPLICABLE with reason (no financial scope).

## Clean

- Working tree clean after the package commit; no dead code, placeholders, TODO markers, commented-out code, or temporary files in package scope (`pint --test` clean, phpstan clean).
- `.gitignore` extended with the test-runner cache entry only; existing entries preserved.

## Certification

All REQUIRED gates pass; NOT APPLICABLE gates are recorded with reasons above. Verification commands (reproducible from the committed environment):

```sh
bash docs/environment/P02-environment-recovery.sh --verify
php vendor/bin/phpunit          # OK (78 tests, 371 assertions)
php vendor/bin/phpstan analyse  # [OK] No errors (level 6)
php vendor/bin/pint --test      # PASS
```

**Package 02 — Identity and Organization: CERTIFIED.** Package 03 (Authorization and Scope) may begin through the package protocol when authorized.

---

## HISTORICAL SOURCE: `implementation/25-package-03-authorization-scope-checkpoint.md`

# Package 03 Checkpoint — Authorization and Scope

**Package:** 03 — Authorization and Scope
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack (PHP 8.2.27 + Laravel 12.67.0 + PostgreSQL 18.4)
**Baseline:** Package 02 checkpoint (`24-package-02-identity-organization-checkpoint.md`) at commit `1ea387a` — reused, not restarted

## Discover

- Environment reused exactly as certified in Package 02: `P02-environment-recovery.sh --verify` → **ENVIRONMENT VALID** (databases, Laravel boot, toolchain). No rebuild, no toolchain change, no dependency change (`composer.lock` untouched, content-hash `d6eab7208db9f0891547ef361ee7478b`).
- Governance inputs consumed: authorization/scope registries and contracts in `docs/implementation` (state 00, quality directive 21, sequence 17, checkpoints 22–24), foundation registries (03 command family, 32 lifecycle, 33 authority chain, 43 boundaries), architecture decision records (23, ADR-013).
- Package 02 recorded decision #1 executed as specified: the interim capability-map adapter inside `app/Support/Authorization/AuthorizationGate` was **replaced** by the canonical Access module; the `AccessDecision` port and default-deny semantics are unchanged. No parallel access system exists; Package 02 deliberately created no role/permission tables and this package created them exactly once.

## Map (implemented scope)

- **Access persistence** (6 migrations, `2026_08_25_000010`–`000015`): `positions` (organization-scoped, unique name per organization), `position_assignments` (effective-dated, lifecycle CHECK `proposed|active|expired|revoked`, partial unique index one open assignment per person+position, period CHECK), `roles`, `access_policies` (versioned: `position→role` and `role→permission` bindings, binding-type CHECK, partial unique index one open role binding per position, period CHECK), `scope_grants` (named-scope permission grants, lifecycle CHECK, scope-type CHECK `organization|campus|branch|department`, partial unique index one open grant per person+permission+scope, period CHECK, emergency/review flags), `delegations` (dated, scoped, reasoned; lifecycle CHECK, period CHECK, not-self CHECK, partial unique index one open authority per delegator+delegate+permission+scope).
- **AccessLifecycle** (`app/Modules/Access/Domain`): the lifecycle registry `proposed→active`, `active→expired|revoked`, terminal states never continue; used by assignments, grants, and delegations alike.
- **AccessResolution** (`app/Modules/Access/AccessResolution.php`): the canonical server policy decision implementing the `AccessDecision` port. Resolves Position → Assignment → Role → Permission → Scope (role-derived organization scope) plus direct named-scope grants plus bounded delegations at the effective time; **default deny**; authority expires by date without any rewrite. Delegations resolve **one level deep** and never beyond the delegator's own authority; a scoped delegation narrows to the exact delegated scope key (fail-closed).
- **Commands** (`app/Modules/Access/Commands`, each under the universal command contract — actor, operation, target, idempotency key, one owning transaction per fact+audit, denial audit): `DefineAccessPolicy` (publishes a version, closing the overlapping open row), `AssignPosition` (proposed assignment, closes prior open assignment), `TransitionPositionAssignment` (activate/revoke through the lifecycle), `GrantScopePermission` (self-grant forbidden; organization-wide grants require **two distinct eligible approvers**; emergency grants are dated ≤ 30 days, flagged `review_required`, audited), `RevokeScopePermission` (revocation retains history), `DelegateAuthority` (delegator-or-administrator only; the delegator may not delegate authority they do not hold; to-self/empty-reason/inverted-period rejected), `RevokeDelegation`.
- **Shared kernel adjustments** (port-preserving, no parallel behavior): `Actor` is now pure identity (person id + display name; authority is resolved server-side, never carried); `AuthorizationGate` deleted; `StructureDecision` and the Identity commands call the injected `AccessDecision` (bound to `AccessResolution` as a singleton in `AppServiceProvider`); denial paths still commit audit evidence via `AttemptedOperation`.
- **Test infrastructure**: fixtures seed the canonical model itself (`SeedsAuthority`: bootstrap organization, per-capability-set roles, positions, active assignments, direct scope grants) — no test-side capability map remains.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, 53 files, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 97 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (118 tests, 477 assertions)** |
| integration tests | PASS | feature suites exercise commands → PostgreSQL schema → audit evidence atomically (Access: 39 tests) |
| invariant tests | PASS | access lifecycle matrix; one open assignment/grant/role-binding/delegation (partial unique indexes); CHECK-constrained states, scope types, delegation period and not-self |
| authorization tests | PASS | full-chain role-derived resolution; grant scope leakage across organizations; unknown/fabricated capabilities (incl. `*.*`, `identity.*`, empty) denied; unprivileged grantor/assigner/publisher/revoker/delegator denied with audit; two distinct approvers for organization-wide grants (count, same-actor, eligibility) |
| lifecycle tests | PASS | proposed→active→revoked; forbidden transitions (`proposed→revoked`, `active→active`, double revoke) fail closed; assignment re-issue closes the prior open row; policy versioning closes the prior open row |
| temporal tests | PASS | as-of resolution: expired grant/assignment window, future grant not yet effective, closed policy version, expired/revoked delegation all stop authority without rewrite |
| financial tests | NOT APPLICABLE | no financial module in Package 03 scope (carried from Package 02 decision #5) |
| concurrency/idempotency tests | PASS | repeat commands return the original outcome; same idempotency key with different payload rejected (grant, delegation) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended with the four access partial unique indexes and three CHECK vectors; dev database `toefl_house` migrated to all 15 migrations (`migrate:status` all Ran, 0 rows); test database `toefl_house_test` rebuilt per suite |
| contract verification | PASS | universal command contract per command; module boundary — only the Access module writes access persistence; `AccessDecision` port unchanged; structure/identity denial messages now carry the resolver's reason |
| adversarial review | PASS | see below |
| regression verification | PASS | the entire Package 02 suite (structure, lifecycle, authorization, identity, audit immutability, queries, idempotency) re-run green on the canonical resolver after every repair; final full run green |

## Attack (adversarial verification)

Vectors executed against the implementation, all failing closed:

1. Fabricated/wildcard capabilities (`*.*`, `identity.*`, `access`, empty string) — never resolve; exact-string permission matching only.
2. Delegation chains (delegator → middle → leaf) — the second hop resolves nothing; delegations are one level deep.
3. Delegate exceeding the delegator (delegation for `identity.verify`, attempt on `access.grant`) — denied; delegation for a permission the delegator does not hold — denied before creation (`access.delegate_beyond_authority`).
4. Position in one organization leaking into another — denied; an assignment without a permission-granting role authorizes nothing.
5. Grant into a scope that does not resolve (unknown campus) — business rejection `access.scope_unavailable`; no row written.
6. Self-grant — denied (`access.self_grant_forbidden`) and audited; delegation to self — rejected (`access.delegation_to_self`).
7. Organization-wide grant with one approver, the same actor twice, or an unprivileged approver — denied (count / distinct-actor / eligibility), audited, no row written.
8. Emergency grant without expiry, or beyond 30 days — rejected; within the limit it is flagged `review_required` and dated.
9. Expired/revoked authority still resolving (grant window, assignment window, closed policy version, expired or revoked delegation) — denied at and after the boundary, with no history rewrite.
10. Third-party revocation of another person's delegation or grant — denied and audited; the row stays active.
11. Tampering with grant rows against the period invariant (start after end) — rejected by the schema CHECK, not only by the command.
12. Idempotency-key reuse with a different payload (grant, delegation) — rejected.

Defects found by verification and repaired during this package (quality directive §29 stop-and-repair, then full re-run): `binding_type` CHECK initially missing the `role` binding; policy versioning closing the prior row by the wrong key (`grants_id` instead of permission-for-permission, all-role-for-position); Carbon 3 signed `diffInDays` weakening the emergency limit; `lessThanOrEqual` (nonexistent) → `lessThanOrEqualTo`; `char(36)` padding breaking a PHP-side delegator comparison in `RevokeDelegation`; redundant `instanceof` (phpstan); three fixture-ordering defects where test actors were materialized after the scope they had to be authoritative in. Every repair was followed by a full-suite regression.

## Independent review

Reviewed against the contracts as a separate pass: every access command satisfies the universal command contract (actor, operation, target, idempotency key; one owning transaction per fact+audit; material denials committed as audit evidence after rollback). Module boundaries hold — only Access writes access persistence; Organization/Identity consume the `AccessDecision` port unchanged; the Audit module stays append-only and generic. No UI, routes, or generic CRUD were introduced. Recorded reviewed decisions (not silent, per directive §22):

1. **`AccessDecision` port kept, adapter replaced** — Package 02 decision #1 executed literally: the port, the default-deny semantics, and every existing call site survive; only the implementation behind the port changed. No parallel access system exists anywhere.
2. **Delegations resolve one level deep** — chained delegation is not granted implicitly; a further delegation must be created and authorized by the delegate's own authority. Fail-closed reading of the registry.
3. **Scoped delegation narrows to the exact scope key** — no subtree expansion beyond the registry's scope keys (a delegation scoped to an organization covers the delegator's key for that organization only). Stricter than required, never looser.
4. **One open role binding per position** — a position holds at most one role at a time; publishing a new binding closes the previous one (versioned history). Enforced structurally by partial unique index, not only by the command.
5. **Organization-wide grant defined as a grant scoped to `organization`** — the two-distinct-approver chain applies there, mirroring the two-Owner material-decision rule of registry 33.
6. **Person intake and person merge remain out of command scope** (carried from Package 02 decisions #3/#4); financial gates NOT APPLICABLE with reason (carried from #5).

## Clean handoff

- Working tree contains only this package's implementation, tests, migrations, and documentation; no scratch or generated artifacts; `.gitignore` unchanged from the P02 baseline plus `/.phpunit.cache`.
- Dev database `toefl_house` migrated to the full 15-migration schema, 0 rows; the test database is rebuilt per suite; no data was required, created, or destroyed beyond schema.
- The P02 environment recovery script and baseline remain valid (`--verify` → ENVIRONMENT VALID); the release artifacts and digest verification are untouched.

## Certification

All gates PASS (or NOT APPLICABLE with recorded reason). Package 03 — Authorization and Scope is **CERTIFIED** at this checkpoint. The certified cumulative suite is **OK (118 tests, 477 assertions)**; phpstan level 6 clean (53 files); pint clean (97 files).

---

## HISTORICAL SOURCE: `implementation/26-package-04-documents-privacy-checkpoint.md`

# Package 04 Checkpoint — Documents and Privacy

**Package:** 04 — Documents/Privacy (audit delivered in Package 02)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack (PHP 8.2.27 + Laravel 12.67.0 + PostgreSQL 18.4)
**Baseline:** Package 03 checkpoint (`25-package-03-authorization-scope-checkpoint.md`) at commit `7be1272` — reused, not restarted

## Discover

- Environment reused exactly as certified: `P02-environment-recovery.sh --verify` → **ENVIRONMENT VALID**; no rebuild, no dependency change (`composer.lock` untouched).
- Governance inputs consumed: privacy/consent/disclosure model (foundation 37), privacy-audit-resilience architecture contract (foundation 49), data/privacy/resilience model (foundation 24), lifecycle registries (foundation 22 document lifecycle, 32 document/consent states), command registry (implementation 03 family `verify/disclose/consent/revoke/export`), source-of-truth registry (foundation 30: Document Version/Consent/Disclosure owned by Documents/Privacy, arbitrary URL never authority), module contracts (implementation 04 Communication/Documents/Privacy row), entity registry (foundation 29).
- The audit half of the sequence row was already delivered and certified in Package 02 (append-only `AuditEvent` + DB trigger); this package delivers the remaining documents/privacy scope without touching it.

## Map (implemented scope)

- **Privacy module** (`app/Modules/Privacy`):
  - `ConsentPurpose` — purpose registry with channel and category; **communication and marketing consent are separate rows** (unique per name+channel).
  - `Consent` — subject authorization, effective-dated, with mandatory evidence reference; subject must be a **verified person**; the subject may record/submit/revoke their own consent without any capability (identity-based right), staff need `privacy.consent`.
  - `ConsentLifecycle` registry: `draft→submitted→verified→active→expired|revoked→archived`; terminal states never reactivate; expiry is by effective window **without rewriting the record**.
  - `ConsentRevocation` — who withdrew, when, scope, effect; append-only (DB trigger). **Revocation stops future use without erasing historical consent or disclosure evidence.**
  - `Disclosure` — recipient, purpose, authority, scope, time, disclosed category; minimum fields enforced; append-only (DB trigger).
  - `ExportSubjectData` — purpose-based authorization, minimum disclosure, immutable disclosure evidence, read-only derived dataset; **organization-wide (bulk) exports require two distinct eligible approvers** (`privacy.approve_bulk_export`).
  - `SubjectPrivacyQuery` — as-of consent view (revoked/expired never count as current authority) + disclosure history; read-only.
- **Documents module** (`app/Modules/Documents`):
  - `DocumentClassification` — sensitivity registry (category, owner module, access class from the four-class model) and `RetentionRule` (category, positive period, legal/operational basis).
  - `Document` + `DocumentVersion` — evidence with immutable versions (hash + storage reference; **a URL/storage reference is never authority**); corrections append versions.
  - `DocumentLifecycle` registry per foundation 22/32: `draft→submitted→verified|rejected`, `rejected→submitted` (new version), `verified→active`, `active→expired|archived`, `expired→archived`.
  - `DocumentVerification` — verifier, result, reason; append-only (DB trigger); **verifier may not be the uploader** of the version under review; failed verification rejects without erasing the evidence.
  - `DecideRetention` — retain before due date, archive after; missing rule fails closed; decisions append-only; deletion is replaced by archive.
  - `DocumentHistoryQuery` — versions with their verifications, read-only.
- Persistence: 10 migrations (`2026_08_26_000016`–`000025`) owned solely by these two modules; CHECK constraints (consent/document lifecycle states, access classes, verification results, retention actions, disclosure scope types, consent periods, positive retention periods); partial unique index `consents_one_open_per_subject_purpose`; four append-only/immutability triggers (consent revocations, disclosures, document verifications, retention decisions) plus document-version immutability — each self-contained, mirroring the Package 02 audit pattern.
- All authorization flows through the canonical `AccessDecision` port (Package 03 resolver); capabilities: `privacy.define_purpose`, `privacy.consent`, `privacy.disclose`, `privacy.export`, `privacy.approve_bulk_export`, `documents.classify`, `documents.register`, `documents.verify`, `documents.retention`.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, 76 files, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 136 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (158 tests, 590 assertions)** |
| integration tests | PASS | feature suites exercise commands → PostgreSQL schema → audit evidence atomically (Privacy 16, Documents 9 tests) |
| invariant tests | PASS | consent/document lifecycle matrices; one open consent per subject+purpose (partial unique index); CHECK-constrained states, access classes, results, actions, periods |
| authorization tests | PASS | unprivileged recorder/discloser/exporter/registrar denied with audit; subject self-service without capability; verifier≠uploader separation of duties; two distinct approvers for organization-wide exports (count, same-actor, eligibility) |
| lifecycle tests | PASS | full consent chain and full document chain incl. rejection→resubmission; forbidden transitions fail closed; revoked/expired consent never reactivates |
| temporal tests | PASS | consent effective window: current within, absent after, record never rewritten |
| privacy/history tests | PASS | revocation evidence, disclosures, verifications, retention decisions append-only (model + raw SQL against DB triggers); document versions immutable; revoked consent and expiry excluded from the current-use view |
| financial tests | NOT APPLICABLE | no financial module in Package 04 scope |
| concurrency/idempotency tests | PASS | repeat commands return the original outcome; same key + different payload rejected (consent record, subject export) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (consent index; consent-state, access-class, retention-action CHECK vectors); dev database `toefl_house` migrated to all 25 migrations |
| contract verification | PASS | universal command contract per command; only Privacy/Documents write their persistence; queries read-only; no query result is authority to mutate |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite (Packages 02+03) green after every repair; final run green |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. URL/storage reference as authority — possessing the document location neither verifies nor activates evidence; `submitted→active` without verification rejected.
2. Revoked consent reactivated — `revoked→active` forbidden; the record and its revocation evidence remain queryable history.
3. Revocation erasure — raw SQL `UPDATE consent_revocations` rejected by the DB trigger.
4. Disclosure tampering — raw SQL `UPDATE disclosures` rejected by the DB trigger.
5. Version tampering — raw SQL `UPDATE document_versions` (content hash) rejected by the DB trigger.
6. Verification rewriting — raw SQL `UPDATE document_verifications` (flipping pass to fail or back) rejected by the DB trigger.
7. Retention decision rewriting — raw SQL `UPDATE retention_decisions` rejected; `shred` action rejected by the CHECK constraint.
8. Self-verifying uploader — verifier identical to the version uploader rejected (`documents.verifier_is_uploader`).
9. Disclosure of an unknown subject / missing recipient-purpose-category — rejected before any row is written.
10. Consent for an unverified subject / without evidence / inverted period / duplicate open consent for the same purpose — rejected (domain + schema).
11. Organization-wide export with one approver, the same actor twice, or an unprivileged approver — denied, audited, no disclosure row.
12. Idempotency-key reuse with a different payload (consent record, export) — rejected.

Defects found by verification and repaired (quality directive §29, each followed by full-suite regression): an over-broad purpose-conflation rule that rejected the sanctioned marketing/communication separation (replaced with the per-channel uniqueness the registry actually requires); a test revoking a `submitted` consent (registry allows revocation only from `active`); stacked PHPDoc blocks confusing phpstan; `char(36)` padding leaking into the export dataset (trimmed at the boundary); two test-side syntax/expectation defects.

## Independent review

Reviewed against the contracts as a separate pass: every command satisfies the universal contract (actor, operation, target, idempotency key; one owning transaction per fact+audit; material denials committed as audit evidence). Module boundaries hold — only Privacy writes consent/disclosure persistence, only Documents writes document/retention persistence; both consume the Package 03 authorization resolver; Audit stays generic and append-only. No UI, routes, or generic CRUD. Recorded reviewed decisions (not silent, per directive §22):

1. **Guardian relationship access is deferred to the Students/Admissions package** — foundation 29 assigns guardian relationship entities to Student/Privacy jointly; the relationship registry does not exist until admissions, so the "verified relationship + relationship-specific permission" gate cannot be truthfully evaluated yet. The consent/disclosure machinery delivered here is guardian-agnostic and ready to carry it.
2. **`send` (communication delivery) is deferred to the Integration package** — the command registry family `verify/disclose/consent/revoke/export/send` includes delivery, but foundation 24 marks SMS/email mechanics agent-decided at integration time; consent per communication channel is delivered now so delivery has an authority to check.
3. **Document "Versioned" state collapsed into version history** — foundation 22 shows `Verified → Versioned → Active`; versions are immutable appended rows owned by the document, so a separate lifecycle state would carry no additional fact. The transition `verified→active` with ordered version history satisfies the registry's intent; recorded rather than silently simplified.
4. **Consent states shared with documents** per registry 32 row "Document/Consent": consent uses Draft→Submitted→Verified→Active (verified = subject identity verified), documents use their own row of the same registry including Rejected.
5. **Retention action computes from the rule, not operator choice** — `DecideRetention` derives retain/archive from the rule's period against the document's age, so a retention decision cannot quietly shorten a legal hold; the operator only records it.
6. **Audit half of the sequence row already certified in Package 02** — nothing re-implemented, no parallel audit path.

## Clean handoff

- Working tree contains only this package's implementation, tests, migrations, and documentation; no scratch artifacts; `.gitignore` unchanged.
- Dev database `toefl_house` migrated to the full 25-migration schema; test database rebuilt per suite.
- Recovery script and baseline remain valid (`--verify` → ENVIRONMENT VALID); release artifacts untouched.

## Certification

All gates PASS (or NOT APPLICABLE with recorded reason). Package 04 — Documents/Privacy is **CERTIFIED** at this checkpoint. Certified cumulative suite: **OK (158 tests, 590 assertions)**; phpstan level 6 clean (76 files); pint clean (136 files).

---

## HISTORICAL SOURCE: `implementation/27-package-05-students-admissions-checkpoint.md`

# Package 05 Checkpoint — Students and Admissions

**Package:** 05 — Students/Admissions (sequence row "Students/Admissions")
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 04 checkpoint (`26-package-04-documents-privacy-checkpoint.md`) at commit `a1c8654` — reused, not restarted

## Discover

- Environment reused exactly as certified (`--verify` → **ENVIRONMENT VALID**; `composer.lock` untouched).
- Governance inputs consumed: entity registry (foundation 29: Visitor/Applicant/Admission Decision → Admissions; Student/Student Status with history → Students; Guardian Relationship → People/Students), lifecycle registries (foundation 32 Student/Admission and Guardian rows), relationship registry (foundation 31: Person↔Student 1:0..N no silent merge, Student↔Guardian N:M verification required, Student↔Admission 1:N prior decisions retained), authority registry (foundation 33: Admissions chain Reception/Admissions → Academic/Admissions → policy owner, forbidden Reception-only permanent conversion; withdrawal/suspension/reactivation row), boundary contracts (foundation 43: Identity→Admissions rejects unverified identity; Admissions→Students/Academic/Finance approved admission facts, no downstream activation without approval), command registry (implementation 03 apply/admit/convert family), module contracts (implementation 04).

## Map (implemented scope)

- **Admissions module** (`app/Modules/Admissions`):
  - `Applicant` — a verified person with a program interest; one open admission file per person; lifecycle `prospect→applicant→admitted|rejected`, `rejected→applicant` (new decision).
  - `DecideAdmission` — the three-role authority chain of registry 33 (initiator `admissions.initiate`, reviewer `admissions.review`, approver `admissions.approve`), **three distinct actors enforced**; reason and evidence mandatory; decision rows append-only (DB trigger) with the full chain recorded; prior decisions retained.
  - `EnrollAdmittedApplicant` — admission conversion orchestration in **one transaction** (Student-owned aggregate/status creation + audit; rollback leaves nothing): only an `admitted` applicant, only once per admit decision, one student per person, person identity must remain verified.
- **Students module** (`app/Modules/Students`):
  - `StudentAdmissionRegistrar` — owns Student aggregate and initial `active` status inserts when Admissions supplies an approved conversion.
  - `Student` — exactly one per person (partial unique index), created only by the Student-owned conversion port; student code unique.
  - `StudentStatus` — **append-only status facts** (DB trigger; immutable rows); the current status is the latest row (deterministic `seq` ordering); registry transitions `active→suspended|withdrawn|completed`, `suspended|withdrawn→active` (**reactivation only with the separate approval capability** `students.reactivate`), `completed→alumni`, terminal `alumni`; every transition requires a reason and is audited; **no silent status overwrite** — history only accumulates.
  - `GuardianRelationship` — effective-dated, recorded `unverified`, explicit verification step, revocation retains history; **only verified, effective, active relationships carry their relationship-specific permissions**; one open row per (student, guardian, relationship); a student cannot be their own guardian (this delivers the guardian access deferred from Package 04's decision #1).
  - `StudentRecordQuery` — read-only: effective status as of a day (latest fact ≤ day) and currently-effective verified guardians with permissions.
- Persistence: 5 migrations (`2026_08_26_000026`–`000030`) owned solely by these modules; CHECK constraints (applicant states, decision outcomes, status values, guardian verification/lifecycle states); `students_one_per_person` and `guardian_relationships_one_open_per_pair` partial unique indexes; append-only triggers on `admission_decisions` and `student_statuses`.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 158 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (179 tests, 657 assertions)** |
| integration tests | PASS | register→decide→convert chain, status history, guardian chain (Admissions 7, Students 6 tests) |
| invariant tests | PASS | one student per person; one open guardian row per pair; append-only decisions and statuses (model + raw SQL vs triggers); CHECK-constrained states |
| authorization tests | PASS | Reception-only chain denied (reviewer/approver capabilities + distinct-actor rule) with audit; unprivileged status transitions denied with audit; reactivation requires the separate approval capability |
| lifecycle tests | PASS | applicant chain incl. rejection→re-application; student suspend→reactivate, withdraw→reactivate, complete→alumni; forbidden paths (suspended→suspended, alumni→active, draft conversions) fail closed |
| rollback-transaction tests | PASS | conversion creates student+status+audit atomically; failed conversion leaves no student rows; double conversion rejected |
| financial tests | NOT APPLICABLE | no financial module in Package 05 scope (liable-party facts flow to Finance when it exists) |
| concurrency/idempotency tests | PASS | replay returns original outcome (conversion); same-key different-payload rejected; duplicate open admission file rejected |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (students/guardian indexes; applicant/guardian state CHECKs); dev DB migrated to all 30 migrations |
| contract verification | PASS | universal command contract; only Admissions/Students write their persistence; Identity boundary enforced (unverified person cannot apply, conversion re-checks verification) |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Reception-only permanent conversion — clerk in reviewer or approver position denied (`admissions.reviewer_denied` / `approver_denied`), audited, no decision row.
2. Single actor holding all three roles — denied (`admissions.single_actor`).
3. Rejected applicant conversion — rejected (`admissions.convert_requires_admission`).
4. Unverified person applying / person losing verification before conversion — rejected.
5. Duplicate open admission file / second student for the same person / second conversion of the same decision — rejected.
6. Silent status overwrite — impossible: statuses are append-only facts; raw SQL `UPDATE student_statuses` rejected by the trigger.
7. Decision rewriting — raw SQL `UPDATE admission_decisions` (flipping admit→reject) rejected by the trigger.
8. Self-reactivation without approval — manager capability insufficient; `students.reactivate` required.
9. Alumni resurrection — `alumni→active` forbidden.
10. Unverified guardian permissions — an unverified relationship exposes no permissions in the read model; revoked relationships retain history but carry nothing.
11. Student as their own guardian / duplicate open guardian row — rejected.

Defects found by verification and repaired (each followed by full-suite regression): an over-strict unique index first forbidding same-day transitions, then forbidding legitimate same-day status cycles (removed — the transition table itself is the authority); ambiguous "latest status" ordering under same-timestamp inserts (solved with a deterministic sequence column); two phpstan annotation defects.

## Independent review

Reviewed against the contracts as a separate pass. Recorded reviewed decisions (not silent, per directive §22):

1. **Enrollment is deferred to the Academic delivery package** — the sequence row mentions enrollment, but the entity/ownership registries (foundation 29/30) assign Enrollment/Class Membership to Academic Delivery with mandatory class/period references that do not exist yet; creating placeholder academic persistence here would cross module ownership. The student identity and status machinery delivered now is what enrollment will attach to.
2. **Applicant states delivered from the registry row "Student/Admission"** — the prospect→applicant→admitted prefix is Admissions-owned; Active onward is Students-owned status history, per the entity registry split.
3. **Status history is append-only facts, not periods** — closing periods would require UPDATEs; the registry demands no-overwrite history, so current status = latest fact, ordered by a deterministic sequence.
4. **Reactivation is a separate capability** (`students.reactivate`) — registry 32 "reactivation only by approval"; a manager who can suspend must not silently reactivate.
5. **Guardian verification is explicit, not implied** — recorded relationships carry zero permissions until verified; the read model never exposes unverified or revoked permissions (foundation 21/37 boundary).
6. **Financial gates NOT APPLICABLE with reason** (no financial module yet); liable-party facts will flow to Finance per boundary 43 when that package lands.

## Clean handoff

- Working tree contains only this package's artifacts; dev DB `toefl_house` migrated to 30 migrations, 0 rows; recovery verify ENVIRONMENT VALID; `.gitignore` unchanged.

## Certification

All gates PASS (or NOT APPLICABLE with recorded reason). Package 05 — Students/Admissions is **CERTIFIED**. Certified cumulative suite: **OK (179 tests, 657 assertions)**; phpstan level 6 clean; pint clean (158 files).

---

## HISTORICAL SOURCE: `implementation/28-package-06-academic-delivery-checkpoint.md`

# Package 06 Checkpoint — Academic Delivery

**Package:** 06 — Academic Delivery (sequence row "Academic delivery": programs, classes, scheduling, attendance, placement of the enrollment deferred from Package 05)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 05 checkpoint (`27-package-05-students-admissions-checkpoint.md`) at commit `4a09b52` — reused after a full sandbox reset through the committed recovery mechanism (see Discover)

## Discover

- The platform reset the sandbox: local git was rewound to `368eef3` with the P02–P05 content present only as uncommitted files, `/opt/th` toolchain and `vendor/` were gone. Recovery followed the sanctioned mechanism only: working tree verified byte-identical to remote tip `4a09b52` (stash → `reset --hard FETCH_HEAD` → empty diff → drop), then `docs/environment/P02-environment-recovery.sh --recover` rebuilt the toolchain from the digest-verified release artifacts (now at `/home/user/toolchain`). Post-recovery: full regression **OK (179 tests, 657 assertions)** and `--verify` → **ENVIRONMENT VALID** before any new work. No rebuild beyond the committed script; no dependency change.
- Governance inputs consumed: lifecycle registry (foundation 32 Class/Session and Enrollment/Membership rows), academic lifecycle rules (foundation 22: published program/level/period history is never silently rewritten; corrections create attributable correction history), authority registry (foundation 33 withdrawal/suspension row and the separate-facts rule), relationship registry (foundation 31: Enrollment↔Program/Period, Class↔Session 1:N, Class↔Teacher N:M dated, attendance = student+session), entity registry (foundation 29), command registry (implementation 03 schedule/assign/attendance-correct family), module contracts (implementation 04).

## Map (implemented scope)

- **Academic structure**: `Program` (draft→published via first version publication; archived terminal) with **immutable published versions** (`ProgramVersion`, append-only DB trigger; corrections are new versions); `AcademicPeriod` (draft→published→closed, period CHECK; published periods are the only valid class hosts).
- **Class/Session**: `ClassModel` on `classes` (published program version + published period + positive capacity) with the registry lifecycle `planned→published→active→completed→archived`, `cancelled` reachable from planned/published/active preserving the record; **activation requires at least one open teacher assignment**. `ClassSession` (date + time window CHECK) schedulable only on active classes. `TeacherAssignment` effective-dated, one open assignment per teacher per class (partial unique index); substitution is a separate assignment.
- **Enrollment** (the fact deferred from Package 05 — its owning module now exists): `requested→active` under the **separate approval capability** (`academic.enroll_approve`); `active→frozen|transferred|withdrawn|completed`, `frozen→active|withdrawn`. **Capacity invariant** enforced under row lock at activation and transfer (count of active seats < capacity). **No duplicate active seat** — domain check plus partial unique index `enrollments_one_active_seat`. **Transfer closes the old enrollment as `transferred` and opens a new `requested` row in the target class** under the same invariants; same-class transfer rejected. Enrollment requires the student's *current* status to be `active` (reads the append-only status history).
- **Attendance**: `AttendanceFact` — append-only facts (DB trigger) tied to a session and an **active enrollment of that session's class**; corrections append a linked row (`corrects_id`) with a **mandatory reason**, targeting a fact of the same enrollment; the original is never rewritten.
- **Queries**: `ClassRosterQuery` — read-only roster (active/frozen seats, active seat count vs capacity, open teacher assignments).
- Persistence: 8 migrations (`2026_08_26_000031`–`000038`) owned solely by the Academic module; CHECK constraints (program/class/enrollment states, period windows, session time windows, teacher assignment periods, attendance statuses, positive capacity); partial unique indexes (one open teacher assignment per class+teacher, one active seat per student+class); append-only/immutability triggers on `program_versions` and `attendance_facts`.
- Capabilities through the canonical resolver: `academic.structure`, `academic.schedule`, `academic.enroll`, `academic.enroll_approve`, `academic.attendance`.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 184 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (193 tests, 732 assertions)** |
| integration tests | PASS | structure/class/enrollment/attendance chains against PostgreSQL atomically (7 feature tests + 2 unit suites) |
| invariant tests | PASS | capacity under lock; no duplicate active seat (domain + partial index); one open teacher assignment; immutable program versions; append-only attendance (model + raw SQL vs trigger); CHECK-constrained states/windows/statuses |
| authorization tests | PASS | clerk cannot activate seats (approval capability separate); unprivileged structure definition denied with audit and no row |
| lifecycle tests | PASS | class chain incl. cancellation preserving record and teacher-required activation; enrollment chain incl. transfer closing the old row; frozen/terminal paths fail closed |
| capacity/evidence tests | PASS | third activation into capacity 2 rejected with seat left `requested`; attendance evidence carries correction chain with reason |
| financial tests | NOT APPLICABLE | no financial module in Package 06 scope |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (enrollment/teacher indexes; program-state and attendance-status CHECK vectors); dev database migrated to all 38 migrations |
| contract verification | PASS | universal command contract; only Academic writes academic persistence; Students boundary consumed read-only (current status), never mutated |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair; post-recovery baseline regression green before any new work |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Program version tampering — raw SQL `UPDATE program_versions` rejected by the trigger.
2. Attendance tampering — raw SQL `UPDATE attendance_facts` (flipping status) rejected by the trigger.
3. Correction without reason / correction targeting another enrollment's fact — rejected.
4. Attendance on a frozen enrollment — rejected (`academic.attendance_enrollment_not_active`).
5. Planned→active class shortcut and teacher-less activation — rejected.
6. Session scheduling on a non-active class — rejected.
7. Clerk (requester) activating a seat — denied (approval capability), audited.
8. Duplicate seat (requested/active/frozen) for the same student and class — rejected.
9. Capacity overflow at activation and at transfer — rejected under lock; the seat stays `requested`.
10. Same-class transfer — rejected; frozen→transferred shortcut — rejected.
11. Enrollment of a suspended/withdrawn student — rejected (current-status check).
12. Unprivileged structure definition — denied with audit evidence and no row.

Defects found by verification and repaired (each followed by full-suite regression): a self-referencing foreign key on `attendance_facts.corrects_id` unbuildable on the char(36) primary key in PostgreSQL (removed — the correction chain integrity is domain-enforced: same-enrollment validation in the command, plus the append-only trigger); missing table-name override on `ClassModel` (`class_models` → `classes`); two test-side expectation-ordering defects; an unused parameter removed during review.

## Independent review

Reviewed against the contracts as a separate pass. Recorded reviewed decisions (not silent, per directive §22):

1. **Enrollment delivered here** — Package 05 decision #1 executed as recorded: enrollment belongs to Academic Delivery, whose owning tables now exist.
2. **`transferred` is terminal by design** — a transfer creates a *new* enrollment row in the target class; reopening the transferred row would duplicate identity of the participation fact and blur history.
3. **Capacity counted over `active` seats only** — `requested` and `frozen` seats do not consume delivery capacity; a frozen seat is unfrozen through `frozen→active`, which re-checks capacity under the same lock.
4. **Attendance correction chain is domain-validated, not FK-chained** — PostgreSQL cannot build the self-referencing FK on the bpchar primary key; the command validates that the corrected fact belongs to the same enrollment, and the append-only trigger makes both rows immutable evidence.
5. **Placement/scoring and assessment decisions are NOT in this package** — the sequence row names them, but they are the *Academic decisions* package (moderation/progression/appeal/certification) with its own contracts; only the delivery fabric (programs, classes, sessions, teachers, enrollment, attendance) is delivered here.
6. **Financial gates NOT APPLICABLE with reason** (no financial module yet).

## Clean handoff

- Working tree contains only this package's artifacts; `.gitignore` unchanged; dev database `toefl_house` migrated to 38 migrations, 0 rows; recovery verify **ENVIRONMENT VALID** after the reset-recovery; release artifacts untouched.

## Certification

All gates PASS (or NOT APPLICABLE with recorded reason). Package 06 — Academic Delivery is **CERTIFIED** at this checkpoint. Certified cumulative suite: **OK (193 tests, 732 assertions)**; phpstan level 6 clean; pint clean (184 files).

---

## HISTORICAL SOURCE: `implementation/29-package-07-academic-decisions-checkpoint.md`

# Package 07 Checkpoint — Academic Decisions

**Package:** 07 — Academic Decisions (assessment results and their review chain, appeals, progression and graduation decisions, certificates)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 06 checkpoint (`28-package-06-academic-delivery-checkpoint.md`) at commit `7bffc67`

## Discover

- Governance inputs consumed: authority/capability registry (foundation 35 — Test Officer records the attempt evidence immutably; Academic Management approves and releases results and decides graduation eligibility with the approved-exception basis; an assigned reviewer handles appeals and the original decision is retained; the Completion authority issues certificates), lifecycle registry (foundation 32 — Placement/Assessment row: Draft→Started→Submitted→Scored→Moderated→Approved→Released→Appealed→Corrected, "a score is never automatically a decision"; Progression/Graduation row: Proposed→Reviewed→Approved/Rejected, appealed→new decision with the original retained; Incident/Appeal row: Open→Assigned→Investigating→Resolved/Rejected/Escalated→Closed with outcome and evidence, no silent closure), business rules (BR-ACAD-002: no automatic advancement), entity registry (29), decision registry (32: a score is not a decision), relationship registry (31), command registry (implementation 03), module contracts (implementation 04).
- Scope split recorded at the Package 06 checkpoint honored: P06 delivered delivery (structure/class/enrollment/attendance); P07 delivers decisions on top of it.

## Map (implemented scope)

- **Assessment attempt** (`AssessmentAttempt`): `draft|started|submitted` states, kind ∈ {placement, assessment}; a `submitted` attempt is **frozen by a DB trigger** (state + evidence immutable). The attempt requires an **active enrollment**.
- **Assessment result** (`AssessmentResult`): `scored→moderated→approved→released` — **release is reachable only through approval**; `released→appealed|corrected`, `appealed→corrected`, `corrected` terminal. Score ≥ 0 (CHECK). **One live result per attempt** (partial unique index; `corrected` rows are excluded as history). Each stage records its own actor (`scored_by`/`moderated_by`/`approved_by`/`released_by`); the **moderator and the approver must differ from the scorer** and the approver from the moderator — moderation/review is independent by construction.
- **Correction**: appends a **new released row** referencing the original via `corrects_id` with a **mandatory reason**, requires two distinct actors (moderator + approver), and closes the original as `corrected`. The original score stays visible.
- **Appeal** (`AcademicAppeal`): `open→assigned→investigating→{resolved,rejected,escalated}`, `escalated→assigned`, `{resolved,rejected}→closed`, `closed` terminal. `resolved`/`rejected` require **outcome + outcome_evidence** (no silent closure) and only the **assigned reviewer** may investigate or decide; the assigned reviewer may never be the **original decision-maker** (scorer of the appealed result / approver of the appealed progression decision).
- **Progression decision** (`ProgressionDecision`): `proposed→reviewed→{approved,rejected}`; `approved|rejected→appealed`; `appealed→superseded` — a resolved appeal **supersedes**: a new approved decision is appended and the original row is retained pointing at its successor (`superseded_by_id`). Three distinct roles (proposer ≠ reviewer ≠ approver) and outcome ∈ {advance, repeat}. **One open decision per student+class** (partial unique index over proposed/reviewed/approved/appealed). **BR-ACAD-002**: nothing advances automatically — a released score creates no progression decision (asserted).
- **Graduation decision** (`GraduationDecision`): `proposed→reviewed→{approved,rejected}` with outcome ∈ {eligible, not_eligible} and a **mandatory requirements basis**; independent review, Academic-Management approval (approver ≠ proposer/reviewer); one open decision per student+program version.
- **Certificate** (`Certificate`): issued **only from an approved, eligible graduation decision**, once per decision, unique serial; issuance records are **immutable (DB trigger blocks UPDATE/DELETE)**.
- Capabilities through the canonical resolver, all separate: `academic.assess`, `academic.moderate`, `academic.approve_result`, `academic.release`, `academic.appeal_manage`, `academic.progression_propose`, `academic.progression_review`, `academic.progression_approve`, `academic.completion`, `academic.completion_approve`, `academic.certify`.
- Persistence: 6 migrations (`2026_08_26_000039`–`000044`) owned solely by the Academic module; CHECK constraints (attempt kinds/states, result states and score, appeal subjects/states, progression and graduation outcomes/states); partial unique indexes (one live result per attempt, one open progression per student+class, one open graduation per student+version, unique certificate serial); immutability triggers on `assessment_attempts` (submitted) and `certificates`.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 205 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (209 tests, 792 assertions)** |
| integration tests | PASS | result chain, correction, appeal, progression, graduation/certificate chains against PostgreSQL atomically (6 feature tests + lifecycle unit suite) |
| invariant tests | PASS | release only after approval (state machine + unit vector matrix); one live result per attempt (domain + partial index); one open progression/graduation decision; submitted-attempt and certificate immutability (raw SQL vs triggers); CHECK-constrained states/outcomes/subjects |
| authorization tests | PASS | scorer≠moderator≠approver enforced (independence guards fire even when the actor holds the capability); release capability separate from approval; unprivileged scoring denied with audit and no row |
| lifecycle tests | PASS | full chains incl. appeal escalation loop and supersession retaining the original; terminal states fail closed (unit matrix) |
| evidence tests | PASS | appeal resolution without evidence rejected; correction without reason rejected; correction chain recorded with reason |
| financial tests | NOT APPLICABLE | no financial module in Package 07 scope |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes (incl. certificate re-issue replay); same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (result/progression/graduation/certificate indexes; attempt-kind, result-state/score, appeal-subject, progression/graduation-outcome CHECK vectors; append-only trigger catalog assertions); database migrated to all 44 migrations |
| contract verification | PASS | universal command contract; only Academic writes academic decision persistence; Admissions/Academic-delivery boundaries consumed read-only, never mutated |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Scorer moderating or approving their own result — denied (`academic.review_not_independent`) even though the actor holds the capability.
2. Approver who moderated the same result — denied (`academic.approval_not_independent`).
3. Release without the release capability (approval alone) — denied.
4. Score→release shortcut (skipping moderation/approval) — impossible in the state machine; unit matrix asserts the absent edges.
5. Negative / non-numeric score — rejected (`academic.result_score_invalid` + schema CHECK).
6. Second live result for the same attempt — rejected (domain guard + partial unique index).
7. Correction by a single actor holding both roles — denied (`academic.correction_single_actor`); correction without reason — rejected.
8. Raw SQL mutation of a submitted attempt — rejected by the trigger; raw SQL mutation of a certificate (UPDATE and DELETE) — rejected by the trigger.
9. Original decision-maker assigned to the appeal — denied (`academic.appeal_not_independent`).
10. Appeal resolution without outcome/evidence — rejected (`academic.appeal_outcome_required`); silent closure from open/investigating — impossible (state machine, unit-asserted).
11. Proposer reviewing, or reviewer approving, a progression/graduation decision — denied (independence guards).
12. Duplicate open progression decision for the same student+class — rejected (domain + partial index).
13. Certificate from a non-approved or not-eligible decision — rejected; second certificate for one decision — rejected; serial tampering — rejected by the trigger.
14. Unprivileged scoring — denied with audit evidence (`academic.result.score.denied`) and no result row.
15. Released score automatically creating a progression decision — asserted absent (BR-ACAD-002).

## Repair log (defects found by verification, fixed, reverified)

1. `IdempotentExecution` constructor hints mis-typed as `Idempotency` in two commands (container failure) — fixed.
2. `reviewed_by`/`approved_by` NOT NULL on the decision tables blocked the staged three-role chain — made nullable (a role column is null until that stage is performed); stage actors now also recorded on results (`moderated_by`/`approved_by`/`released_by`).
3. Correction/supersession inserted the successor before closing the original, violating the partial unique index — reordered (close original first, then append successor) inside one transaction.
4. Schema-test certificate row required the deep admissions FK chain — replaced by catalog assertions for the immutability triggers (row-level behavior proven end-to-end in the feature test with a real certified chain).
5. Test defects: negative-path actors accidentally granted the very capability under test; expectation-order issues — fixed; negative-path actors now hold the capability so the *independence* guard is what fires.

## Decide

- A released result stays `released` when its appeal closes without a correction; only an actual score change appends a corrected row (registry: "the appeal produces a new decision", not a mutation).
- Appeal subjects are exactly `assessment_result` and `progression_decision` (schema CHECK); graduation appeals flow through the same progression supersession path applied to progression decisions — a graduation decision can be re-decided only through a new decision after the open one is closed, preserving history.
- `superseded` is excluded from the open-decision partial index (a superseded decision is closed history, so a new open decision may follow).
- Capabilities for the decision chain are deliberately finer-grained than P06 (eleven separate academic capabilities) so independence rules are enforceable per stage.

## Certified

Package 07 — Academic Decisions: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (209 tests, 792 assertions)**, phpstan level 6 clean, pint clean (205 files), database at 44 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–06 untouched.

---

## HISTORICAL SOURCE: `implementation/30-package-07-hr-teachers-checkpoint.md`

# Package 08 Checkpoint — HR/Teachers

**Package:** 08 — HR/Teachers (sequence row 7: employment, contracts, work basis, leave)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 07 checkpoint (`29-package-07-academic-decisions-checkpoint.md`) at commit `9b9341b`

## Discover

- Governance inputs consumed: HR/payroll domain model (foundation 36 — Employment "status history retained", Contract "signed terms immutable once used", Compensation "effective-dated", Work/Teaching Basis "source evidence retained", Leave "approval/history retained", Termination "access ends, history retained"), lifecycle registry (32 — Employment/Contract: Candidate, Active, Leave, Suspended, Transferred, Terminated, Settled, Archived under approved transitions; "payroll cannot invent silent terms"), relationship registry (31 — Employee↔Contract 1:N effective contract, prior closed; Contract↔Compensation 1:N, entitlement immutable once used; Employee↔Leave 1:N period required), boundary contracts (43 — Identity→HR requires verified person, reject unverified and audit; Academic→HR/Payroll teaching evidence as controlled input, "hold disagreement; preserve evidence"; HR→Payroll/Access status as decision input), authority registry (33 — payroll/compensation family: forbidden beneficiary self-approval), requirements (08 — REQ-HR-001 compensation changes reviewed then GM-approved; G1-REQ-003 two-Owner rule noted for material cases), HR/payroll architecture (implementation doc 11: HR owns employment/contracts/compensation terms/leave/termination; payroll and settlement are later packages).
- Payroll entities (Payroll Period, Calculation, Result, Adjustment, Clearance, Final Settlement) are **out of scope** — sequence row 8.

## Map (implemented scope)

- **Employment** (`Employment` + append-only `EmploymentStatus`): verified person → `candidate` → `active` (hire requires an active signed contract); `active→on_leave|suspended|terminated`, `on_leave→active`, `suspended→active|terminated`. **One open employment per person** (partial unique index excluding `terminated`). Every transition **appends status history** (DB trigger keeps it append-only); the current status is the latest fact.
- **Termination**: mandatory reason; closes the open active contract (with effective date), cancels pending/approved leave, and **revokes open position assignments through the Access module's own command** (`TransitionPositionAssignment::revoke`) so **access ends with employment** without HR writing Access persistence directly. Employment history is retained.
- **Contract** (`Contract`): draft terms → sign (signed-document evidence reference mandatory) → active; **signed terms immutable once active** (DB trigger blocks term/signature/start-date mutation; a change is a new contract; rows can never be deleted); **one open (draft|active) contract per employment** (partial unique index); closing sets an effective end date after the start.
- **Compensation** (`CompensationComponent`): kind ∈ {fixed, hourly, class_based, allowance} with positive amount; **proposed by HR, activated by a different approver** (`hr.compensation_approve`), and **never the beneficiary** (the employed person); effective-dated within the contract window, **no overlapping active component of the same kind** (domain check under lock); **immutable once active** (DB trigger) — an entitlement change is a new effective-dated component (history retained, ready for payroll snapshots).
- **Work basis** (`WorkBasis`): append-only evidence (DB trigger) of hours/classes; `academic` source references a real teaching assignment of the same person (read-only consumption of Academic evidence); when the evidence disagrees with the employment state it is **held with a note — preserved, never dropped**; `manual` declarations require an open employment and an evidence reference.
- **Leave** (`Leave`): request (category, period, reason) → approve/reject by a **different actor**, cancel; **one pending request per employment** (partial unique index); **approved leaves may not overlap** for the same employment (domain check under lock); leave attaches only to open employments; decisions and history retained.
- Capabilities: `hr.employ`, `hr.contract`, `hr.compensation`, `hr.compensation_approve`, `hr.leave_request`, `hr.leave_approve`, `hr.workbasis`, `hr.terminate` — all separate; termination additionally exercises `access.assign_position` through the Access command.
- Persistence: 6 migrations (`2026_08_26_000045`–`000050`) owned solely by the HR module; CHECK constraints (employment/contract/leave states, compensation kinds/amount/period, work-basis source/unit/state, periods); partial unique indexes (one open employment per person, one open contract per employment, one pending leave per employment); append-only/immutability triggers on `employment_statuses`, `work_bases`, `contracts` (terms + no-delete), `compensation_components` (active).

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 226 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (222 tests, 847 assertions)** |
| integration tests | PASS | employment/contract/compensation/leave/work-basis/termination chains against PostgreSQL atomically (7 feature tests + HR lifecycle unit suite) |
| invariant tests | PASS | one open employment; one open contract; signed-terms immutability (raw SQL vs trigger); active compensation immutability (raw SQL vs trigger); status history append-only; work-basis evidence append-only (raw DELETE vs trigger); no overlapping active compensation or approved leave |
| authorization tests | PASS | unverified person rejected; unprivileged employment denied with audit and no row; compensation proposer≠approver and beneficiary self-approval forbidden (guards fire while holding the capability); leave requester≠decider |
| lifecycle tests | PASS | full employment chain incl. suspension and reinstatement; terminated→active impossible; contract draft→sign→close; leave request→decide→cancel; unit matrix asserts absent edges |
| contract tests (boundary) | PASS | Academic teaching evidence consumed read-only; Access mutated only through its own command; Identity person verified before employment |
| financial tests | NOT APPLICABLE | payroll/finance packages later in the sequence |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (employment/contract/leave indexes; employment-state, contract-state, work-basis-source CHECK vectors; catalog assertions for all five HR triggers); database migrated to all 50 migrations |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Employment of an unverified person — rejected (`hr.person_not_verified`), no row.
2. Second open employment for the same person — rejected (domain + partial index).
3. Second open contract while one is open — rejected; raw SQL forgery of signed terms — rejected by the trigger; raw DELETE of a contract — rejected by the trigger.
4. Proposer activating their own compensation component — denied even while holding the approve capability (`hr.compensation_not_independent`).
5. Beneficiary approving their own compensation — denied (`hr.compensation_beneficiary`).
6. Overlapping active component of the same kind — rejected (`hr.compensation_overlap`); raw SQL amount tampering of an active component — rejected by the trigger.
7. Compensation attached to a non-active contract or starting before the contract — rejected.
8. Leave decided by its requester — denied (`hr.leave_not_independent`); overlapping approved leave — rejected (`hr.leave_overlap`), the second request stays pending.
9. Leave requested against a suspended/terminated employment — rejected.
10. Academic evidence for a different teacher's assignment — rejected (`hr.workbasis_person_mismatch`); evidence disagreeing with employment state — **held and preserved**, not dropped; manual evidence without an open employment — rejected; raw DELETE of work-basis evidence — rejected by the trigger.
11. Reinstatement of a terminated employment — rejected (`hr.employment_transition_forbidden`).
12. Termination without access revocation — impossible: the termination chain closes the contract, cancels leave, and revokes assignments in one transaction; `active` position assignments for the person are asserted zero afterwards.
13. Unprivileged employment creation — denied with audit evidence (`hr.employment.employ.denied`) and no row.

## Repair log (defects found by verification, fixed, reverified)

1. HR models missing `$incrementing = false` / string key type — char(36) ids read back as `0` after create (FK failure on status insert) — fixed on all six models.
2. char(36) padding on `person_id` comparisons (beneficiary and teacher-identity checks silently never matched) — comparisons now trim both sides.
3. Leave/compensation negative-path actors lacked the very capability under test (capability denial masked the independence guard) — actors now hold the capability so the *independence* guard fires; the leave approver capability was added to the manager fixture.
4. Beneficiary fixture created the person twice (duplicate PK) — the verified person is now seeded through the authority fixture.
5. phpstan generics on unused Eloquent relation methods — removed (nothing consumes them; consistent with the existing modules' style).

## Decide

- `Settled`/`Archived` employment states and HR/Finance clearance/final settlement arrive with the Payroll package (registry order); `Terminated` is terminal here.
- `Transferred`/`Promoted` are not employment states in this implementation: they are new effective contracts and position assignments (close prior, start new) — employment continuity and full history retained.
- Termination revokes access through the Access module's own command (actor holds both capabilities) rather than HR writing Access tables — persistence ownership stays with Access.
- Held work-basis evidence (academic/employment disagreement) stays `held` until Payroll/HR review consumes it — no silent resolution exists yet by design (foundation: "hold disagreement; preserve evidence").
- REQ-HR-001's HR+Finance review before GM approval is modeled as separate propose/activate capabilities; the Finance-role grants are configuration (the Finance module arrives later in the sequence).

## Certified

Package 08 — HR/Teachers: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (222 tests, 847 assertions)**, phpstan level 6 clean, pint clean (226 files), database at 50 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–07 untouched.

---

## HISTORICAL SOURCE: `implementation/31-package-09-payroll-checkpoint.md`

# Package 09 Checkpoint — Payroll

**Package:** 09 — Payroll (sequence row 8: periods, calculations, approvals, settlement)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 08 checkpoint (`30-package-07-hr-teachers-checkpoint.md`) at commit `5dc1111`

> **Historical scope notice (2026-09-05):** This checkpoint records the Payroll package as it was certified on 2026-08-26. Its historical `final_settlements` persistence claim is superseded for the target architecture by Finance-owned `employment_settlements` and migrations `2026_09_05_000142`–`000143`. The old evidence remains history; it is not a current production-readiness assertion. Clean-schema, upgrade, historical-row reconciliation, and runtime verification remain outstanding.

## Discover

- Governance inputs consumed: HR/payroll domain model (foundation 36 — Payroll Period "controlled closing", Payroll Calculation "recalculation audited", Payroll Result "correction/reversal, not overwrite", Payroll Adjustment "source-linked", HR/Finance Clearance "both clear before closure", Final Settlement "immutable approved result"), financial architecture contract (46 — no authoritative mutable balance; payroll view cannot post truth), financial/acquisition rules (BR-HR-002, G2-D-004 — contract-silent payroll treatment is HELD for HR and Finance review, nothing invented), authority registry (33 — payroll/compensation: reviewer HR+Finance, approver GM or Owners by risk; beneficiary self-approval forbidden), decision ledger (D-G3-003 — contractual entitlement, payroll calculation, payroll result, and actual payment remain distinct), concurrency contract (architecture 17 — payroll/period close: period lock and state check; closed-period immutability), HR/payroll architecture (implementation doc 11 — payroll snapshots effective contract/configuration and source work evidence; corrections append adjustment/reversal history).
- Actual payment posting is Finance's (later packages); this package stops at the approved payable result and the final settlement record.

## Map (implemented scope)

- **Payroll period** (`PayrollPeriod`): one window per unique key; `open→calculating→closed` — **closed is terminal and immutable** (DB trigger blocks UPDATE/DELETE; reopening impossible). **Closing is rejected while held (contract-silent) calculations remain** — they must be resolved by review, never skipped.
- **Payroll calculation** (`PayrollCalculation`): for one employment in one period — **snapshots** the active contract's effective compensation components (ids, kinds, rates/amounts) and the consumed recorded work-basis rows (ids, units, quantities, sources) into an immutable jsonb snapshot. Amount math is exact (bcmath): fixed/allowance contribute their amount; hourly/class-based contribute rate × quantity summed from work evidence. **One live calculation per (period, employment)** (partial unique index); **recalculation supersedes** the prior row (both retained); a **resulted** calculation is fixed history (trigger + state machine).
- **Contract-silent holding (BR-HR-002/G2-D-004)**: work evidence whose unit has no active covering component — or no active contract over the period — produces a **held** calculation with a held reason; **no charge or payment is invented**; held calculations **cannot be approved** (must be recalculated after HR/Finance resolution) and **block period closure**.
- **Payroll result** (`PayrollResult`): approved from a prepared calculation; **approval is segregated from preparation and from the beneficiary** (approver ≠ preparer; the employed person never approves their own payroll — capability `payroll.approve`). One result per calculation (unique index); the calculation becomes `resulted`; **results are immutable** (DB trigger) — corrections and reversals **append adjustments**.
- **Payroll adjustment** (`PayrollAdjustment`): kind ∈ {adjustment, reversal}, mandatory reason, **append-only** (DB trigger); reversal negates the amount and is allowed **once** per result; **any mutation of a result in a closed period is rejected** (`payroll.period_closed`).
- **Clearance & settlement** (`PayrollClearance`, `SettlementProposal`, Finance `EmploymentSettlement`): termination clearance is recorded separately by **HR** (`payroll.clear_hr`) and **Finance** (`payroll.clear_finance`) — one per domain (unique index); Payroll prepares a proposal with declared evidence, while a distinct Finance capability (`finance.employment_settlement`) records the immutable settlement fact after verifying termination, both clearances, source match, and beneficiary separation; one settlement per employment; **immutable once recorded** (DB trigger).
- Capabilities: `payroll.period`, `payroll.calculate`, `payroll.approve`, `payroll.adjust`, `payroll.clear_hr`, `payroll.clear_finance`, `payroll.settle`, `finance.employment_settlement` — all separate.
- Persistence: 6 migrations (`2026_08_26_000051`–`000056`) owned solely by the Payroll module; CHECK constraints (period/calculation states, adjustment kinds, non-negative result/settlement amounts, period windows); unique/partial-unique indexes (period key, one live calculation per period+employment, one result per calculation, one clearance per domain); immutability triggers on `payroll_periods` (closed), `payroll_results`, `final_settlements`, and append-only triggers on `payroll_adjustments` and consumed/superseded calculations.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 245 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (233 tests, 901 assertions)** |
| integration tests | PASS | period/calculation/approval/adjustment/settlement chains against PostgreSQL atomically (7 feature tests + payroll lifecycle unit suite) |
| invariant tests | PASS | one period per key; one live calculation per period+employment; one result per calculation; one clearance per domain; one settlement per employment; results, settlements, closed periods, and adjustments immutable (raw SQL vs triggers); snapshot math exact (40000 + 250×40 = 50000; addendum → 52500) |
| authorization tests (SoD) | PASS | approver ≠ preparer; beneficiary self-approval denied (result and settlement); settlement prepare/approve distinct actors; unprivileged calculation denied with audit and no row |
| lifecycle tests | PASS | period open→closed terminal; calculation prepared→resulted/superseded; held→superseded only; unit matrix asserts closed-never-reopens and held-never-approved edges |
| payroll SoD/concurrency tests | PASS | period lock (row lock on close under held-count check); closed-period mutation rejected for new calculations and late adjustments; recalculation supersedes prepared rows only, while held rows require explicit evidenced resolution against a same-period/employment replacement |
| contract tests (boundary) | PASS | HR entities consumed read-only (contract, components, employment, work bases); no Finance persistence exists to touch — payment posting deliberately absent |
| financial tests | NOT APPLICABLE | Finance core is the next package; no balances or postings exist by design |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (payroll indexes; calculation-state and adjustment-kind CHECK vectors; catalog assertions for the four payroll triggers); database migrated to all 56 migrations |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Duplicate payroll period key — rejected (unique index).
2. Closing a period with a held contract-silent calculation — rejected (`payroll.period_close_held`).
3. Approving a held calculation — rejected (`payroll.calculation_not_prepared`); held→resulted impossible in the state machine.
4. Work evidence with no covering component (class hours, class-based rate absent) — calculation **held with reason**, amount not invented.
5. No active contract over the period — calculation **held**, not paid.
6. Preparer approving their own calculation — denied while holding the approve capability (`payroll.approval_not_independent`).
7. Beneficiary approving their own payroll — denied (`payroll.beneficiary`).
8. Second result from a consumed calculation — rejected; raw SQL tampering of a result amount — rejected by the trigger.
9. Adjustment without a reason / unknown kind — rejected; double reversal — rejected (`payroll.reversal_exists`).
10. Late adjustment or new calculation after period closure — rejected (`payroll.period_closed` / `payroll.period_not_open`); raw SQL reopen of a closed period — rejected by the trigger.
11. Settlement before termination, before both clearances, or twice — rejected; single-actor prepare+approve — denied; beneficiary participating — denied; raw SQL amount tampering of a settlement — rejected by the trigger.
12. Unprivileged calculation — denied with audit evidence (`payroll.calculation.prepare.denied`) and no row.

## Repair log (defects found by verification, fixed, reverified)

1. Employment termination date read through a relation method that no longer exists — replaced with a direct latest-status query.
2. phpstan `array` property docblock on the snapshot column — typed as `array<string, mixed>`.
3. Negative-path preparer lacked the approve capability (capability denial masked the independence guard) — actor now holds it so the *independence* guard fires.

## Decide

- Entitlement, calculation, result, and payment stay distinct (D-G3-003): this package ends at the approved result and the settlement record; posting an actual payment belongs to Finance (sequence row 9+).
- Fixed/allowance components contribute their full amount when the component window overlaps the period — **no proration is invented** (proration policy is configuration, decided later); hourly/class-based multiply exactly by consumed evidence quantities.
- The Finance side of "HR + Finance review" and the Finance clearance are **capabilities** (`payroll.approve` review separation, `payroll.clear_finance`) — role grants are configuration; the Finance module itself arrives later without changing this contract.
- Payroll-period closure coordination with Finance period closure is deferred until Finance periods exist (explicit status checks will be added there, not here); disagreement handling is already fail-closed through the held-calculation gate.
- The settlement amount is declared input with mandatory basis evidence — the system computes payroll results, never settlement totals.

## Certified

Package 09 — Payroll: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (233 tests, 901 assertions)**, phpstan level 6 clean, pint clean (245 files), database at 56 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–08 untouched.

---

## HISTORICAL SOURCE: `implementation/32-package-10-finance-core-checkpoint.md`

# Package 10 Checkpoint — Finance Core

**Package:** 10 — Finance core (sequence row 9: periods, accounts, obligations, journals — financial invariants/reconciliation; no reports yet)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 09 checkpoint (`31-package-09-payroll-checkpoint.md`) at commit `2230e77`

## Discover

- Governance inputs consumed: canonical financial domain model (foundation 34 — Account "code, type, effective definition"; Financial Period "controlled reporting/posting window"; Obligation/Obligation Line "source, original amount, debtor, period" / "source, amount, category"; Journal/Journal Line "balanced accounting record, period, source, debit/credit, posting"; Reconciliation "source set, observation, variance, explanation"; mandatory rules — journals balance, closed periods reject mutation, balances are derived from posted facts, no authoritative mutable balance), financial architecture contract (46 — Finance alone owns posted financial truth; reconciliation owns comparison and variance evidence, not an alternate cash truth), HR/payroll architecture (11 — payroll/finance period closure coordinates through explicit status checks; disagreement creates an exception, not silent overwrite), concurrency contract (17 — reconciliation: one period/source observation, unique run + approval lock), authority registry (33 — payment family approvers; beneficiary restrictions noted for the payments package), sequence row 9 scope.
- Payments, refunds, discounts, funding, cash drawers, expenses and scholarships are sequence row 10 — **out of scope here**.

## Map (implemented scope)

- **Chart of accounts** (`Account`): unique code, name, type ∈ {asset, liability, equity, revenue, expense} (CHECK); entries **immutable once defined** (DB trigger — a changed definition is a new account).
- **Financial period** (`FinancialPeriod`): one window per unique key; `open→closed`, **closed is terminal and immutable** (DB trigger; reopening impossible). **Closing coordinates with payroll periods through an explicit status check**: any payroll period overlapping the window that is not closed blocks the closure with an exception (`finance.period_payroll_open`) — never a silent overwrite.
- **Obligation** (`Obligation` + `ObligationLine`): approved charge for a liable student in an open period — posted with its atomic lines in one transaction; **lines must be positive and sum exactly to the obligation amount** (computed, not declared twice); both are **immutable posted source facts** (DB triggers); balances/receivables are derived from these facts, never stored.
- **Journal** (`Journal` + `JournalLine`): balanced accounting record — posts only to an **open period**, source-linked (`obligation|payroll_result|journal|other`), every line references an existing account with direction ∈ {debit, credit} and positive amount, and **debits must equal credits exactly** (bcmath) or the posting is rejected. Posted journals and lines are **immutable** (DB triggers). **Reversal appends a new journal** with every leg's direction swapped, linked to the original (`source_type='journal'`).
- **Reconciliation** (`Reconciliation`): comparison evidence — expected vs observed with **variance computed and schema-enforced** (`variance = observed − expected` CHECK); a non-zero variance **requires an explanation**; **one observation per period and subject** (unique index); approval by a **different actor** (`finance.reconcile_approve` ≠ observer); **approved reconciliations lock** (DB trigger). It is evidence, never an alternate cash truth.
- Capabilities: `finance.chart`, `finance.period`, `finance.obligation`, `finance.journal`, `finance.reconcile`, `finance.reconcile_approve` — all separate.
- Persistence: 6 migrations (`2026_08_26_000057`–`000062`) owned solely by the Finance module; CHECK constraints (account types, journal sources/directions/amounts, obligation amounts, period windows, reconciliation variance identity); unique indexes (account code, period key, one reconciliation per period+subject); immutability triggers on `accounts`, `obligations`, `obligation_lines`, `journals`, `journal_lines`, closed `financial_periods`, approved `reconciliations`.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 266 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (245 tests, 943 assertions)** |
| integration tests | PASS | chart/period/obligation/journal/reversal/reconciliation chains against PostgreSQL atomically (7 feature tests + finance lifecycle unit suite) |
| financial invariant tests | PASS | journals balance exactly or posting rejected; obligation lines sum to the obligation; accounts/obligations/journals/journal-lines immutable (raw SQL vs triggers); closed period rejects obligations and journals and never reopens (raw SQL vs trigger); reversal negates the original legs; variance identity enforced by schema |
| reconciliation tests | PASS | variance requires explanation; one observation per period+subject; independent approval; locked once approved |
| authorization tests | PASS | unprivileged obligation posting denied with audit and no row |
| lifecycle tests | PASS | period open→closed terminal; reconciliation draft→approved locked; unit matrix asserts absent edges |
| financial period/payroll coordination | PASS | closing with an overlapping open payroll period rejected; closes after the payroll period closes |
| contract tests (boundary) | PASS | Payroll periods consumed read-only; Students consumed read-only (liable party reference); no payment/balance entities exist by design |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (finance indexes; account-type, journal-direction, variance-identity CHECK vectors; catalog assertions for the finance triggers); database migrated to all 62 migrations |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Duplicate account code — rejected; unknown account type — rejected; raw SQL rename of an account — rejected by the trigger.
2. Duplicate financial period key — rejected; inverted window — rejected.
3. Zero/negative obligation line — rejected; obligation without lines/reason — rejected; raw SQL amount tampering of a posted obligation — rejected by the trigger.
4. Unbalanced journal (debit 8500 vs credit 8000) — rejected with the exact figures in the error.
5. Journal line with unknown account or invalid direction — rejected.
6. Posting obligations or journals to a closed period — rejected; raw SQL reopen of a closed period — rejected by the trigger.
7. Reversal that would mutate the original — impossible: reversals append a negating journal linked to the original.
8. Raw SQL tampering of posted journal lines — rejected by the trigger.
9. Reconciliation variance without explanation — rejected; second observation for the same period+subject — rejected; observer approving their own reconciliation — denied; raw SQL edit of an approved reconciliation — rejected by the trigger.
10. Closing a financial period while an overlapping payroll period is open — rejected (`finance.period_payroll_open`).
11. Unprivileged obligation posting — denied with audit evidence (`finance.obligation.post.denied`) and no row.

## Repair log (defects found by verification, fixed, reverified)

None — the package passed every gate on the first full run; the adversarial vectors above were all designed in from the registries and verified green.

## Decide

- Obligation amounts are **computed from the posted lines** (single source of truth) rather than declared alongside them — the sum cannot disagree with the lines.
- The journal account mapping (which accounts a charge hits) is the accountant's explicit input — the system enforces balance, openness, source links, and immutability but **invents no mapping policy**.
- Payroll-period coordination is the promised explicit status check from the P09 checkpoint: overlap check at finance close time; disagreement fails closed.
- Payments, refunds, discounts, funding, cash drawers, and expenses belong to the next package; no balance columns exist anywhere by design (derived-only).
- Reconciliation approval is two-actor (observer ≠ approver); the reconciliation itself records variance evidence and locks — it cannot mutate anything.

## Certified

Package 10 — Finance core: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (245 tests, 943 assertions)**, phpstan level 6 clean, pint clean (266 files), database at 62 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–09 untouched.

---

## HISTORICAL SOURCE: `implementation/33-package-11-payments-funding-checkpoint.md`

# Package 11 Checkpoint — Payments and Funding

**Package:** 11 — Payments and funding (sequence row 10: payments, allocations, refunds, discounts, funds — idempotency/concurrency/restricted-fund tests)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 10 checkpoint (`32-package-10-finance-core-checkpoint.md`) at commit `87e189d`

## Discover

- Governance inputs consumed: canonical financial domain model (foundation 34 — Payment "source, amount, method, received time, payer"; Payment Allocation "payment, obligation, amount"; Refund "source payment, amount, reason"; Discount "eligibility, amount/rate, reason, effective dates"; Funding Source/Fund/Restriction "agreement, restriction, dates"; Scholarship Award/Allocation; mandatory rules — a payment posts only once, allocations cannot exceed payment or obligation, refunds cannot exceed refundable source, discounts preserve original obligation, restricted funds cannot be reclassified without authorized evidence, closed periods reject mutation, balances derived from posted facts), financial architecture contract (46 — every allocation references one payment and obligation; a payment cannot be allocated twice; reconciliation is not an alternate truth), business rules (11 — BR-FIN-002 refunds need documented conditions, immutable source payment, approval, Finance recording; BR-FIN-003 discounts need published or separately approved eligibility, dates, audit, reversal as controlled correction; BR-FUND-002 contract-silent restricted funds remain restricted and on hold), authority registry (33 — payment family: operator/Finance/configured approver, direct balance edit forbidden; scholarship/funding: restricted use without authority forbidden), Gate-2 flow (27 — Fee policy → Obligation → Payment → Allocation → Refund/Discount → Journal/Ledger → Reconciliation), concurrency contract (17 — payment/allocation/refund: idempotency + per-source serialized commit).
- Cash drawers, expenses, and full scholarship-award rules are later scope; journals already exist (P10) and postings source-link through `other` — no P10 surface touched.

## Map (implemented scope)

- **Payment** (`Payment`): money received from an external source — recorded with a **unique external receipt reference** (a payment posts only once), positive amount, method, received date, payer (student), into an **open financial period**; **immutable** from then on (DB trigger) — returns happen exclusively through refunds.
- **Payment allocation** (`PaymentAllocation`): links exactly **one payment to one obligation** — **unique pair** (a payment cannot be allocated twice to the same obligation), same-payer check, and two hard caps: **allocation ≤ unallocated payment remainder** and **≤ uncovered obligation remainder** (original − fund allocations − payment allocations − approved discounts). Committed under **row locks on both the payment and the obligation** (per-source serialized commit); immutable history (DB trigger).
- **Refund** (`Refund`): BR-FIN-002 — mandatory documented reason, the **immutable source payment**, **two distinct actors** (requester `finance.refund` ≠ approver `finance.refund_approve`), recorded into an open period, and **never more than the refundable remainder** (payment − allocated − already refunded); immutable (DB trigger).
- **Discount** (`Discount`): BR-FIN-003 — proposed with **mandatory eligibility basis**, effective dates, and reason; **approved by a distinct actor** (`finance.discount_approve` ≠ proposer); approval re-checks the cap against the **uncovered obligation remainder**; **the original charge is preserved** (obligation row untouched — verified); approved discounts are **immutable** (DB trigger).
- **Funding** (`FundingSource` + `FundAllocation`): a funding agreement establishes an immutable pool with its **restriction** (`restricted_category` + mandatory restriction note; a restriction is **never reclassified** — DB trigger — BR-FUND-002). Allocations apply fund money to **obligation lines of the permitted category only**, capped by the **unutilized pool remainder** (under a fund row lock), the **uncovered line remainder**, and the **uncovered obligation remainder**; utilization is **derived** from allocations, never stored.
- Capabilities: `finance.payment`, `finance.refund`, `finance.refund_approve`, `finance.discount`, `finance.discount_approve`, `finance.fund`, `finance.fund_allocate` — all separate.
- Persistence: 6 migrations (`2026_08_26_000063`–`000068`) owned solely by the Finance module; CHECK constraints (positive amounts everywhere, discount states and windows); unique indexes (payer receipt reference, allocation pair); immutability triggers on payments, payment allocations, refunds, approved discounts, funding sources, and fund allocations.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 286 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (255 tests, 989 assertions)** |
| integration tests | PASS | payment/allocation/refund/discount/fund chains against PostgreSQL atomically (7 feature tests + payment lifecycle unit suite) |
| financial invariant tests | PASS | payment posts once (unique receipt reference); allocation caps on both sources (7000.01 and 8500.01 rejected with remainders in the errors); unique payment↔obligation pair; refund capped at the refundable remainder (2000.01 rejected, second refund rejected); discount capped at the uncovered remainder (7500.01 rejected); fund utilization capped by pool (5001 rejected), line (3000.01 rejected), and obligation remainders; obligation original amount preserved under discount |
| restricted-fund tests | PASS | restricted category ≠ line category rejected (`finance.fund_restriction`); restriction note mandatory; raw SQL reclassification of the restriction rejected by the trigger |
| idempotency/concurrency tests | PASS | inherited idempotent harness on every command (replay returns the original outcome; same key + different payload rejected); allocation commit serialized under payment+obligation row locks; fund allocation under fund row lock |
| ledger/invariant protection | PASS | all six new tables immutable via DB triggers (raw SQL tampering rejected); closed periods reject payments, refunds, and discount proposals; no balance columns anywhere — remainders derived from posted facts |
| authorization tests | PASS | refund requester≠approver; discount proposer≠approver; unprivileged payment recording denied with audit (`finance.payment.record.denied`) and no row |
| lifecycle tests | PASS | discount proposed→approved terminal; unit matrix asserts absent edges |
| contract tests (boundary) | PASS | Finance core (periods, obligations, obligation lines) consumed read-only; Students consumed read-only; no other module touched |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (payer-ref and allocation-pair indexes; payment-amount and discount-state CHECK vectors; catalog assertions for all six payment/funding triggers); database migrated to all 68 migrations |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Same external receipt posted twice — rejected (`finance.payment_duplicate`, unique index).
2. Payment into a closed period — rejected; refund into a closed period — rejected; discount proposal into a closed period — rejected.
3. Allocation exceeding the unallocated payment remainder — rejected with the exact remainder in the error.
4. Allocation exceeding the uncovered obligation remainder — rejected with the exact remainder.
5. Second allocation of the same payment to the same obligation — rejected (domain + unique pair index).
6. Cross-student allocation — rejected (`finance.allocation_payer_mismatch`).
7. Refund by a single actor (requester = approver) — denied even while holding both capabilities (`finance.refund_not_independent`).
8. Refund exceeding the refundable remainder (allocated money is not refundable) — rejected; refund exhausting the remainder twice — rejected.
9. Discount without eligibility or reason — rejected; inverted effective window — rejected.
10. Proposer approving their own discount — denied (`finance.discount_not_independent`).
11. Discount exceeding the uncovered obligation remainder at approval time (re-checked under lock) — rejected; the obligation's original amount asserted unchanged.
12. Fund allocation to a line of a different category than the restriction — rejected (`finance.fund_restriction`).
13. Fund allocation exceeding the unutilized pool — rejected; exceeding the uncovered line — rejected; exceeding the uncovered obligation — rejected.
14. Restricted fund without a restriction note — rejected at establishment.
15. Raw SQL tampering: payment amount, allocation, refund amount, approved discount amount, fund restriction reclassification, fund allocation — all rejected by triggers.
16. Unprivileged payment recording — denied with audit evidence and no row.

## Repair log (defects found by verification, fixed, reverified)

1. Cross-payer test vector referenced an obligation that did not exist for the second student — the test now posts that obligation first.
2. `assertDatabaseHas` misused a message string as the third (connection) argument — removed (known standing rule).
3. Fund-cap vectors had the line cap binding before the pool cap and the "greedy discount" exactly equal to the remainder — vectors adjusted so each cap is the binding constraint (7500.01 / 5001 / 3000.01).

All repairs are test-side; no production defect was found in the package logic.

## Decide

- "A payment posts only once" is enforced structurally: the **external receipt reference is unique** — replay of the same receipt is impossible regardless of idempotency-key choice, while the idempotent harness still returns the original outcome for true replays.
- "Refundable source" is the **unallocated and unrefunded remainder** — allocated money must never leave as a refund (fail-closed; a future allocation-reversal correction can be added as a controlled adjustment if policy requires it).
- Discount reversal (BR-FIN-003 "reversal is a controlled correction") is deferred by decision: approved discounts are immutable and any correction would append a controlled reversal record when a concrete policy exists — nothing is silently rewritable meanwhile.
- Fund restriction is declared as a **category restriction** (e.g. tuition-only) with a mandatory note; richer restriction dimensions (program, period) are configuration the agreement records in its note until a policy exists — no reclassification path exists by design (BR-FUND-002).
- Scholarship awards are modeled as fund allocations with mandatory reason/eligibility evidence; a dedicated award-rule registry waits for academic scholarship policy (configuration, not invented here).
- Payment/discount/refund journal postings remain the accountant's explicit act through P10's `PostJournal` (`source_type='other'` with the source id) — the P10 CHECK constraint was not modified.

## Certified

Package 11 — Payments and funding: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (255 tests, 989 assertions)**, phpstan level 6 clean, pint clean (286 files), database at 68 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–10 untouched.

---

## HISTORICAL SOURCE: `implementation/34-package-12-assets-operations-communication-checkpoint.md`

# Package 12 Checkpoint — Assets/Operations/Communication

**Package:** 12 — Assets/Operations/Communication (sequence row 11: custody, work, delivery — contract/integration tests)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 11 checkpoint (`33-package-11-payments-funding-checkpoint.md`) at commit `39f06eb`

## Discover

- Governance inputs consumed: entity registry (29 — "Book/Issuance/Return/Loss/Damage/Asset/Custody | Resources | resource ownership and custody | custody and movement history"; "Facility/Maintenance Request/Work Order | Facilities | operational work | request, approval, completion"; "Incident/Complaint/Report/Export/Notification | Security/Reporting/Communication"), relationship registry (31 — Book↔Issuance 1:N movement dates, custody history retained; Asset↔Custody 1:N custodian/location, disposal closes custody), lifecycle registry (32 — Issuance/Asset/Work Order: Requested, Approved, Issued/In Progress, Returned/Completed, Lost/Disposed/Cancelled; custody and work evidence required; disposal requires approval), source-of-truth registry (30 — stock derived, movement history retained), authority registry (33 — asset disposal/export: custodian/manager initiates, Finance/privacy review, **two Owners when material**; bulk or material action without approval forbidden), module contracts (04 — Resources: catalog/custody/stock/assets/work, journal/balance forbidden, custody/history required; Communication: post-commit delivery, source facts, privacy/access), privacy architecture (14 + foundation 37 — communication and marketing consent are separate; revocation blocks future use without erasing history; minimum disclosure and audit).
- Cash drawers (finance scope), expenses, and integrations/jobs are later packages; the Privacy module (P04) is consumed read-only.

## Map (implemented scope)

- **Assets** (`Asset`, Resources module): catalog entries with unique codes; `in_service` until an approved disposal flips them to `disposed` (terminal, immutable by DB trigger).
- **Custody** (`Custody`): one custodian period per asset — **one open custody per asset** (partial unique index); transfer **closes the prior row** (released date) and opens a new one, history retained; the trigger permits **only releasing** an open row (no rewrite of asset/custodian/assignment, no deletes). Disposal closes the open custody.
- **Disposal** (`AssetDisposal`): the authority registry's material-action rule applied fail-closed — a requester plus **two distinct approvers** (`resources.dispose_approve`), method ∈ {sale, scrap, donation}, mandatory reason; one disposal per asset; closes custody, flips the asset, immutable record (DB trigger). The financial journal effect of a disposal remains Finance's explicit act (P10 `PostJournal`, untouched).
- **Work orders** (`WorkOrder`, Facilities): `requested→approved→in_progress→completed|cancelled` — approval by a **different actor**; **completion requires work evidence**; terminal rows immutable (DB trigger).
- **Book circulation** (`BookCopy`, `BookIssuance`): immutable catalog copies (unique codes); issuance with due date ≥ issue date; **one open issuance per copy** (partial unique index); return and **loss with mandatory evidence** are terminal history; **a lost copy is permanently out of circulation**; stock/availability derived from issuances — no mutable stock column.
- **Communication** (`Message`, Communication module): messages queue **post-commit** only under an **active consent** for the subject and purpose (P04 consumed read-only) **on the purpose's registered channel** (communication/marketing separation); delivery results (`sent`/`failed`) require the provider reference; terminal messages immutable (DB trigger). **Revocation blocks future messages without erasing history** (verified).
- Capabilities: `resources.asset`, `resources.dispose_request`, `resources.dispose_approve`, `facilities.work`, `facilities.work_approve`, `resources.books`, `communication.send` — all separate.
- Persistence: 7 migrations (`2026_08_26_000069`–`000075`) owned by the new Resources and Communication modules; CHECK constraints (asset/disposal-method/work-order/issuance/message states, due-date windows); unique/partial-unique indexes (asset code, one open custody, one disposal per asset, copy code, one open issuance per copy); immutability triggers on disposed assets, disposals, terminal work orders, terminal issuances, delivered messages; release-only/no-delete triggers on custody.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 310 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (265 tests, 1043 assertions)** |
| integration tests | PASS | custody/disposal/work/book/communication chains against PostgreSQL atomically (6 feature tests + resource lifecycle unit suite) |
| contract tests (boundary) | PASS | Privacy (consents/purposes) consumed read-only; People consumed read-only; no Finance writes — disposal's financial effect stays an explicit journal posting |
| custody/history tests | PASS | transfer closes prior row (history count and dates asserted); one open custody; disposal closes custody; raw SQL rewrite/delete of custody rejected by triggers |
| invariant tests | PASS | unique asset/copy codes; one disposal per asset; one open issuance per copy; lost copy permanently out of circulation; terminal work orders/issuances/messages immutable (raw SQL) |
| authorization tests | PASS | disposal needs three distinct actors (requester + two approvers); work approval independent of request; unprivileged asset registration denied with audit and no row |
| lifecycle tests | PASS | full work-order chain incl. no-start-before-approval and terminal immutability; issuance issued→returned/lost; message queued→sent/failed; unit matrix asserts absent edges |
| privacy tests | PASS | channel must match the purpose's registered channel; no active consent → no message; revoked consent blocks future messages while delivered history is retained |
| financial tests | NOT APPLICABLE | no financial entities in this package (disposal journals are Finance's explicit act) |
| concurrency/idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended (five new indexes; work-order/message state CHECK vectors; catalog assertions for seven new triggers); database migrated to all 75 migrations |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Duplicate asset/copy codes — rejected (unique indexes).
2. Custody transfer to the same custodian — rejected; transfer closes the prior row (asserted) instead of rewriting it.
3. Raw SQL rewrite of a released custody row or delete of custody history — rejected by triggers.
4. Disposal with a single approver (or any two identical actors among requester/approvers) — denied (`resources.disposal_not_independent`).
5. Disposal of an already-disposed asset / second disposal — rejected; custody assignment to a disposed asset — rejected; raw SQL tampering of a disposal record — rejected by the trigger.
6. Work started before approval — rejected; requester approving their own work order — denied; completion without evidence — rejected; raw SQL delete of a completed work order — rejected by the trigger.
7. Second open issuance of a copy — rejected (domain + partial index); loss report without evidence — rejected; issuance of a lost copy — rejected (`resources.copy_lost`); raw SQL resurrection of a terminal issuance — rejected by the trigger.
8. Inverted issuance due date — rejected (domain + CHECK).
9. Message on a channel different from the purpose's registered channel — rejected (`communication.channel_mismatch`).
10. Message to a subject without active consent — rejected (`communication.consent_missing`).
11. Message after consent revocation — rejected, while previously delivered messages remain in history (asserted).
12. Delivery result without a provider reference — rejected; raw SQL rewind of a sent message — rejected by the trigger.
13. Unprivileged asset registration — denied with audit evidence (`resources.asset.register.denied`) and no row.

## Repair log (defects found by verification, fixed, reverified)

1. Consent fixture effective date (2026-11-01) was in the future relative to the test clock — the active-consent window never opened; fixture moved to 2026-08-01.
2. **Production defect**: a lost copy could be re-issued because circulation only checked open issuances — `issue` now also rejects copies with a `lost` issuance (permanently out of circulation), error `resources.copy_lost`.
3. phpstan iterable return-type docblocks missing on three private transition helpers — added.

## Decide

- The authority registry's "two Owners **when material**" disposal rule is applied **unconditionally** (three distinct actors) — materiality thresholds are configuration; until they exist, fail closed.
- Disposal's financial consequence is not auto-journaled: Resources never posts; Finance records it explicitly (module contract "journal/balance" forbidden for Resources).
- Book circulation keeps copies immutable and derives availability from issuance history (source-of-truth registry) — no stock counters exist to drift.
- Loss is permanent for the copy (a replacement is a new copy) — simplest custody-honest model; damage-with-return can be added as evidence-typed returns if policy requires.
- Communication separation: the channel lives on the registered consent purpose (communication vs marketing), and the message must match it — a message can never claim a purpose on a channel it was not consented to.
- Delivery is recorded as evidence (provider reference), not assumed: queued → sent/failed with mandatory references.

## Certified

Package 12 — Assets/Operations/Communication: **CERTIFIED — PASS** (2026-08-26). Gates: phpunit **OK (265 tests, 1043 assertions)**, phpstan level 6 clean, pint clean (310 files), database at 75 migrations, environment verification `ENVIRONMENT VALID`. Business rules, architecture, module boundaries, and implementation contracts unchanged; no parallel behavior; Packages 02–11 untouched.

---

## HISTORICAL SOURCE: `implementation/35-package-13-reporting-checkpoint.md`

# Package 13 Checkpoint — Reporting (Metrics, Projections, Reconciliation, Dashboards)

**Package:** 13 — Reporting (sequence row 12: reports, metrics, dashboards — contract/integration tests)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 12 checkpoint (`34-package-12-assets-operations-communication-checkpoint.md`) at commit `169438a`

> **Historical scope notice (2026-09-05):** This checkpoint records the Reporting package as originally certified. The current authority for `payroll_total` is the Finance-recognition ADR and `payroll_liability_facts`; Payroll results and adjustments are source evidence only. This checkpoint's runtime/readiness claims are not current validation.

## Discover

- Governance inputs consumed: reporting architecture (12 — metrics read canonical outputs and **never write sources**; rebuildable projections carry source/version/as-of; stale/incomplete data labeled or withheld), derived-data lineage registry (38 — per-family source facts + rule authority + as-of semantics; **no manual override**), reporting/derived-data contract (48 — metric families table; **dashboards cannot write sources or define competing balances**; no silent period/scope mixing; **configuration versions retained**), foundation 23 (centralized Solar Hijri periods; reports may not redefine balance/revenue/attendance/payroll), entity/relationship/authority registries (29/31/33 — Reporting entity, export/notification authority).
- Reporting consumes Finance, Payroll, Academic (delivery) and Funding **read-only**; periods are always resolved from the owning module's registry — Reporting never defines a period.

## Map (implemented scope)

- **Metric catalog** (`MetricDefinition`, `MetricVersion`): only the five canonical catalog metrics are definable (`MetricCatalog::entry` rejects anything else — `reporting.metric_unknown`): `student_outstanding_balance` (finance, financial_period, global|student), `payroll_total` (finance, payroll_period, global; source is Finance-recognized Payroll liabilities), `active_enrollment_count` (academic_delivery, academic_period, global|class), `attendance_rate` (academic, academic_period, global|class), `fund_utilization` (funding, financial_period, fund). Definitions carry `source_owner` and `period_authority` (CHECK-constrained). **Calculation specs are versioned**: definition creates version 1; revision appends `version_no + 1` with its own `effective_from`; versions are **immutable** (DB trigger) so historical reports keep their original definition; revising marks projections of superseded versions **stale**.
- **Period authority** (`MetricCatalog::resolvePeriod`): financial/payroll periods resolve by `period_key`, academic periods by `id` — the same human key resolves per its own authority and **never leaks across authorities** (unit-proven: `2026-12` resolves to different rows under financial vs payroll; an academic id is not a financial key, …). Unknown key → `reporting.period_unknown`; unknown authority → `reporting.period_authority_unknown`.
- **Calculators** (Queries — the only value source; no manual metric entry anywhere): outstanding balance = obligation lines − payment allocations − approved discounts − fund allocations; payroll total = Finance-recognized payroll liability facts; approved Payroll results and adjustments are source evidence; active enrollment count = enrollments `active` in classes of the period; attendance rate = present ÷ recorded facts (late/absent/excused counted as recorded); fund utilization = fund allocations as-of period end ÷ committed (timestamptz `created_at` vs `date_to` compared date-wise, binding verified).
- **Projections** (`MetricProjection`): rebuildable slices keyed by (metric version, period key, scope type, scope id) with **one slice** per key (COALESCE partial-unique index); identity columns are **rebuild-locked** (trigger rejects re-keying) while value/completeness/meta rebuild in place; `completeness ∈ {complete, stale}`; a revision flips prior-version slices to `stale` — **labeled, never silently trusted**.
- **Report runs** (`ReportRun`): each run pins metric version + period key + scope + filters, stores the computed result and a **reproducibility hash** over (metric, version, spec, period, scope, filters, value); rows are **immutable** (DB trigger) — a report can be re-executed and compared, never edited.
- **Reconciliation** (`MetricReconciliation`): recomputes the metric straight from the authoritative source and compares with the latest reported projection for the slice; records reported/authoritative/variance with the **variance identity enforced by CHECK** (`variance = reported − authoritative`); status ∈ {matched, diverged}; **divergence is immutable evidence for the source owner — never an alternate truth and never overwritten**; reconciling without a reported projection → `reporting.nothing_reported`.
- **Dashboards** (`Dashboard`, `DashboardPin`): named dashboard (unique) + immutable pins referencing a registered metric with explicit period and scope; **one pin per slice** (COALESCE partial-unique index); pinning requires a **complete, current-version** projection — never-computed (`reporting.pin_no_projection`) and stale (`reporting.pin_stale`) slices are **withheld**. Dashboards write nothing to any source module.
- Capabilities: `reporting.catalog` (define/revise), `reporting.compute` (projections), `reporting.run` (report runs), `reporting.reconcile`, `reporting.dashboard` — all separate; capability check precedes validation (denied operations audited as `reporting.*.denied` with no row).
- Error codes: `reporting.metric_unknown`, `reporting.metric_spec`, `reporting.metric_exists`, `reporting.period_authority_unknown`, `reporting.period_unknown`, `reporting.scope_not_allowed`, `reporting.scope_shape`, `reporting.nothing_reported`, `reporting.pin_no_projection`, `reporting.pin_stale`, `reporting.pin_exists`, `reporting.dashboard_exists`, `reporting.fund_scope_required`, `reporting.fund_unknown`, and `reporting.{catalog,compute,run,reconcile,dashboard}_denied`.
- Persistence: 6 migrations (`2026_08_26_000076`–`000081`); CHECK constraints (source_owner, period_authority, scope types, completeness, reconciliation statuses, **variance identity**); unique indexes (metric key, one version per (metric, version_no), one projection slice, dashboard name, one pin per slice); immutability triggers on versions/runs/reconciliations/pins; rebuild-only trigger on projections.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 337 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (278 tests, 1118 assertions)** |
| integration tests | PASS | finance/payroll/academic/funding metric chains against PostgreSQL with full authoritative fixtures (5 feature tests + catalog unit suite) |
| contract tests (boundary) | PASS | Finance/Payroll/Academic/Funding consumed **read-only** (calculators query; commands never write them); Reporting owns only its 6 tables |
| metric accuracy tests | PASS | each metric verified against an independently computed authoritative value (3500.00 outstanding = 8500−4000−1000; 0.5000 attendance = 1/2; 2 active seats; 0.2500 utilization = 2500/10000; payroll 0.00 on an empty period) |
| as-of / period semantics | PASS | unknown/future period rejected; payroll metric cannot resolve a financial-period key and vice versa (unit + feature); fund utilization as-of period end |
| scope enforcement tests | PASS | scope outside the metric declaration (`reporting.scope_not_allowed`); global-with-id / scoped-without-id (`reporting.scope_shape`); fund scope required |
| source-of-truth tests | PASS | tampered projection → reconciliation records `diverged` with variance 6499.0000 while sources stay untouched; divergence preserved, never overwritten |
| stale-labeling tests | PASS | revision marks prior projections stale (asserted); stale/never-computed slices withheld from dashboards |
| invariant tests | PASS | `SchemaInvariantFeatureTest` extended: 5 new unique indexes, 4 CHECK vectors (source_owner, period_authority, completeness, variance identity), 5 trigger catalog assertions; raw SQL UPDATE/DELETE of report runs and metric versions rejected by triggers |
| authorization tests | PASS | unprivileged projection denied, audited (`reporting.projection.compute.denied`), no row |
| idempotency tests | PASS | repeat commands return original outcomes; same key + different payload rejected (inherited harness exercised through every command) |
| migration/schema validation | PASS | database migrated to all **81 migrations** (testing + dev) |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Defining a metric outside the canonical catalog (`invented_kpi`) — rejected (`reporting.metric_unknown`); the catalog is closed.
2. Computing against a period that does not exist under the metric's authority (unknown and future `2099-01`) — rejected (`reporting.period_unknown`).
3. Cross-authority period confusion — payroll metric handed a financial-period key (and the mirrored unit matrix) — rejected; the same key `2026-12` resolves independently per authority, never cross-wired.
4. Scope not declared for the metric (fund scope on a student metric) — rejected (`reporting.scope_not_allowed`); global-with-scope-id and fund-without-id — rejected (`reporting.scope_shape`).
5. Manual value entry / editing evidence — raw SQL `UPDATE report_runs` and `DELETE metric_versions` — rejected by triggers; projections can only rebuild value/completeness, never re-key (trigger).
6. Silent divergence — a tampered projection value (9999 vs authoritative 3500) — reconciliation records `diverged` with the exact variance (6499.0000) as immutable evidence; the authoritative source is untouched; the CHECK variance identity blocks forged variance rows.
7. Stale data presented fresh — after a spec revision the prior-version projection is `stale` (asserted) and pinning it (or a never-computed slice) is withheld (`reporting.pin_stale` / `reporting.pin_no_projection`).
8. Double definition / double pin / duplicate dashboard name — rejected (unique indexes + `reporting.metric_exists` / `reporting.pin_exists` / `reporting.dashboard_exists`).
9. Reconciling a slice with no reported projection — rejected (`reporting.nothing_reported`); reconciliation cannot fabricate a baseline.
10. Unprivileged metric definition/compute — denied before validation, audited as denied operations, no rows.

## Repair log (attacks that found real defects)

1. **`FundUtilizationCalculator` period lookup** — `whereKey($periodId)` compiled to `WHERE "key" = …` (wrong column; `financial_periods` is PK `id` + unique `period_key`) and blew up on compute. Repaired to an explicit `where('id', …)`; retested green.
2. **Payroll/financial period-key collision in fixtures** — both fixtures used `2026-12`, so the "cross-authority rejection" vector initially passed for the wrong reason (the key existed under payroll too). Repaired by distinct keys (`2026-12-P`) and a unit matrix proving same-key/per-authority resolution.
3. **Attendance fixture arithmetic** — 2 active enrollments seeded but 3 statuses offered (expected 0.6667, actual 1.0000). Test-side repair to 2 facts/1 present = 0.5000.
4. **Fund allocation exceeding the uncovered obligation remainder** (5000 > 3500) — the Finance guard correctly rejected it; funding fixture reduced to 2500 (utilization 0.2500).
5. **phpstan** — `RunReport::run` `$filters` iterable value type missing; docblock added.

## Decide

- **Closed catalog**: `MetricCatalog` (code) is the registry of definable metrics; the DB stores definitions/versions as evidence. A metric outside it cannot exist — governance-mandated.
- **Reporting never defines periods**: periods resolve exclusively from the owning module (financial/payroll by `period_key`, academic by `id`); no Reporting table stores a period row.
- **Immutable versions, rebuildable projections**: historical report runs stay reproducible against the exact spec version; projections rebuild in place but slice identity (version, period, scope) is trigger-locked.
- **Divergence is evidence, not correction**: reconciliation records variance and reports it to the source owner; it never edits sources or the reported value (immutability triggers).
- **Dashboards are pinboards**: they hold references to registered metric slices only; no computed values of their own, no writes to sources, stale slices withheld.

## Certify

All gates PASS on 2026-08-26: phpunit **278 tests / 1118 assertions** (cumulative Package 02–13), phpstan level 6 clean, pint 337 files, testing + dev databases at **81 migrations**, `P02-environment-recovery.sh --verify` → ENVIRONMENT VALID, adversarial vectors all fail closed, repairs reverified by the full suite. Independent review: contract boundaries (read-only consumption), closed metric catalog, period-authority isolation, immutability/staleness semantics, and reconciliation variance identity all verified against the governance registries. Package 13 is **CERTIFIED**.

---

## HISTORICAL SOURCE: `implementation/36-package-14-integrations-jobs-checkpoint.md`

# Package 14 Checkpoint — Integrations / Jobs

**Package:** 14 — Integrations/Jobs (sequence row 13: adapters, retries, scheduled work — failure/recovery/duplicate tests)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** Package 13 checkpoint (`35-package-13-reporting-checkpoint.md`) at commit `cad73b7`

## Discover

- Governance inputs consumed: integration architecture (15 — anti-corruption adapters with **versioned business contracts**; credentials held **outside domain data**; correlation + idempotency key; timeout, retry/backoff policy, **dead-letter/manual-review state**, audit; external ambiguity reconciled by inquiry, never blindly retried; **webhooks authenticated, deduplicated, source-linked**; integration failure leaves the owned internal fact **pending/failed and visible — never fabricated success**), background processing architecture (16 — async limited to post-commit notifications/communication delivery/projection refresh/scheduled reconciliation/payroll preparation/document processing/backup verification/**integration retries**; each job has trigger, **durable status, idempotency key, retry limit/backoff, failure/dead-letter state, correlation ID**, audit where material; **repeated delivery is safe**; a failed notification cannot roll back a committed fact), transaction boundary model (05 — outbox/post-commit notification after atomic owner commit), concurrency architecture (17 — conflict returns retryable business conflict), observability (19 — job backlog/failure metrics), invariant registry (24 — "idempotent external work: keys, dedupe, retry state"), module boundary map (03 — no external system is a business module; orchestration coordinates).
- MD-011/MD-012 (master decision questionnaire) closed as read: their recommendations (verified relationships, adult-student explicit consent) are already the implemented P04/P12 behavior — no Integration impact.
- Integrations/Jobs consumes **no business module** for writes; it is shared infrastructure with its own durable tables. Communication messages (P12) remain Communication's owned facts, untouched.

## Map (implemented scope)

- **Endpoints** (`IntegrationEndpoint`): registered external boundaries — unique key, channel ∈ {sms, email, payment, storage, identity, messaging, export} (CHECK), versioned contract (`v1`, `v2`, …), **credential reference only** (`vault://…`; the secret itself never enters domain data — secrets/transports live in `config/integrations.php`), approval by the registering authority, one-way `retired` state; retired rows immutable and undeletable (DB trigger).
- **Outbound outbox** (`IntegrationDelivery`): one row per (endpoint, idempotency key) — **duplicate dispatch answers the original row**; source-linked (`source_type`/`source_id`), contract action + payload (jsonb) with **digest for tamper evidence**; identity columns trigger-locked (**only progress may change**; delivered rows final; dead-letter leaves review only via an audited requeue; deletes rejected). Status ∈ {queued, failed, delivered, dead_letter} (CHECK); `max_attempts` bounded 1–10 (CHECK); **delivered requires evidence** — CHECK `(status='delivered') = (delivered_ref AND delivered_at present)`.
- **Delivery core** (`Domain/DeliveryProcessor`) — **the single transactional core** used by both the worker command and the scheduled sweep (no parallel behavior): claim under `FOR UPDATE` (a concurrent worker finds the row terminal or not-yet-due and skips), attempt via the endpoint's adapter, then delivered (with provider reference) / retryable failure (bounded **exponential backoff** 2^n minutes, capped 60, stored as `next_run_at`) / dead-letter on exhaustion or permanent failure. Audited at delivered and dead-letter.
- **Adapters** (`Domain/Transport` + `Adapters/ConfiguredTransportDispatcher`): anti-corruption boundary; per-endpoint transport resolved from configuration; an **unconfigured endpoint fails permanently and visibly** (`integrations.transport_unconfigured` → dead-letter) — success is never fabricated. `TransportResult` carries delivered/reference/retryable/error.
- **Inbound webhooks** (`InboundEvent` + `ReceiveInbound`/`ProcessInbound`): endpoint must be active; payload well-formed; **HMAC-SHA256 signature verified** against the endpoint secret in configuration (`Domain/SignatureVerifier`, constant-time compare). Accepted events **deduplicated per (endpoint, external id)** (partial unique index — rejection evidence does not block a corrected retry; rejected rows retained with reason). Processing is **exactly-once**: received → processed under lock; replayed or concurrent processing answers the original (`already_processed`), never re-executes. Event identity (endpoint, external id, payload, signature) trigger-locked; processed events final; history undeletable.
- **Scheduled jobs** (`JobSchedule`, `JobRun`, `RegisterJob`, `EnqueueJobRun`, `ProcessJobRun`): only **catalog jobs** schedulable (`Domain/JobCatalog` — closed registry including `integrations.retry_sweep` and `outbox.relay`); one schedule per job key (unique), enable/disable reversible, history retained. One durable row per (job, occurrence) — **racing schedulers collapse onto the existing run** (unique + lock). Execution claims a ten-minute `processing` lease and commits it before the handler runs; expired claims can be replayed, bounded attempts (max 3) use exponential backoff (`next_retry_at`), and exhaustion → **dead-letter** with audit; terminal outcomes immutable and replay-safe. Job handler: `Jobs/IntegrationRetrySweepJob` — sweeps due deliveries through the shared delivery core, each delivery in its own committed claim/finalization boundary (partial failure leaves siblings intact).
- **Capabilities**: `integrations.endpoint` (register/retire), `integrations.dispatch`, `integrations.process` (worker sweep), `integrations.review` (dead-letter requeue — an audited human decision), `integrations.inbound` (receive/process), `integrations.jobs` (register/toggle/enqueue/run) — capability before validation; denied operations audited (`integrations.*.denied`) with no rows.
- Error codes: `integrations.endpoint_{exists,terms,contract,credential,retired,inactive,denied}`, `integrations.dispatch_denied`, `integrations.delivery_contract`, `integrations.transport_unconfigured`, `integrations.requeue_not_dead`, `integrations.review_denied`, `integrations.process_denied`, `integrations.payload`, `integrations.signature`, `integrations.inbound_{rejected,denied}`, `integrations.job_{unknown,terms,exists,unscheduled,disabled,occurrence}`, `integrations.jobs_denied`.
- Persistence: 5 migrations (`2026_08_26_000082`–`000086`); CHECK constraints (channel, endpoint state, delivery status + retry bounds + processing-lease/delivered-evidence identity, inbound status, job-run status + attempt bounds + processing-lease identity); unique indexes (endpoint key, delivery per (endpoint, idempotency key), accepted inbound per (endpoint, external id) partial, schedule per job key, run per (job, occurrence)); triggers (retired-endpoint immutability, delivery identity/progress lock, inbound identity lock + finality, schedule retention, job-run identity + terminal finality). DB at **86 migrations**.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | `php vendor/bin/phpstan analyse --memory-limit=1G` — level 6, `[OK] No errors` |
| lint | PASS | `php vendor/bin/pint` — `PASS 369 files` |
| unit tests | PASS | `php vendor/bin/phpunit` — **OK (295 tests, 1229 assertions)** |
| integration tests | PASS | full outbound/inbound/job chains against PostgreSQL (7 feature tests + 4 domain unit tests) |
| contract tests (boundary) | PASS | no business module written by Integrations; Communication/Finance/etc. untouched; credentials and transports outside domain data (config) |
| duplicate-delivery tests | PASS | duplicate dispatch answers the original row (count stays 1); duplicate webhook answers the accepted original; replayed processing never re-executes (transport send count asserted) |
| replay-safety tests | PASS | terminal deliveries/job runs answer their outcome without executing; backoff windows gate premature retries (skipped_not_due / waiting_retry asserted) |
| concurrency tests | PASS | row-claim under `FOR UPDATE` (second worker skips); racing schedulers collapse onto one run row (unique + lock) |
| partial-failure tests | PASS | an aborted attempt rolls back only its own delivery — siblings delivered; a throwing sweep leaves its delivery untouched (queued, attempts 0) — nothing fabricated |
| retry-exhaustion tests | PASS | 5 transient failures → dead_letter at attempts=5 with audit; job handler failing 3 attempts → dead_letter; **audited manual requeue** reopens a fresh bounded window (requeues counted, identity retained) |
| malformed-payload tests | PASS | webhook null/empty payload → rejected evidence; unconfigured endpoint → permanent failure dead-letter (delivered_ref stays null) |
| signature tests | PASS | HMAC verify/tamper/wrong-secret/missing-secret/empty-signature unit matrix; forged intake rejected and retained |
| authorization tests | PASS | unprivileged dispatch/register/intake/enqueue denied before validation, audited, no rows |
| idempotency tests | PASS | every command idempotent-keyed; same key + different payload rejected (inherited harness) |
| migration/schema validation | PASS | `SchemaInvariantFeatureTest` extended: 5 unique indexes, 5 CHECK vectors, 5 trigger catalog assertions; testing + dev DBs at **86 migrations** |
| adversarial review | PASS | see below |
| regression verification | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

Vectors executed, all failing closed:

1. Duplicate dispatch of the same (endpoint, idempotency key) — answers the original row; no second outbox row.
2. Replayed processing of a delivered delivery — skipped, transport called exactly once (send count asserted); raw SQL delete of a delivered row — rejected by trigger.
3. Premature retry inside the backoff window — skipped (`skipped_not_due`); premature job re-run — `waiting_retry`, handler not executed.
4. Retry exhaustion — 5/5 transient failures dead-letter with audit; a manual requeue is the only exit from dead-letter, resets the bounded window, counts the intervention, keeps identity; raw requeue of a non-dead-letter row — rejected (`integrations.requeue_not_dead`).
5. Unconfigured endpoint — permanent failure, dead-lettered visibly, `delivered_ref` null; the CHECK `(status='delivered') = evidence` blocks a fabricated delivered row.
6. Retired endpoint — dispatch and inbound rejected (`integrations.endpoint_inactive`); raw SQL resurrection of a retired endpoint — rejected by trigger.
7. Forged webhook signature / tampered payload / unknown endpoint secret / empty signature — rejected (unit matrix + feature); malformed payload — rejected evidence retained; rejected external id retried correctly — accepted (dedupe applies to accepted events only).
8. Duplicate webhook delivery — answers the accepted original, never reprocesses; exactly one `processed` audit per event; raw SQL rewrite of inbound identity — rejected by trigger.
9. Racing schedulers for the same occurrence — one durable run (unique + lock); replayed execution of a succeeded/dead-lettered run — answers the terminal outcome, attempts unchanged.
10. Unexpected adapter blowup mid-sweep — only the failing delivery's transaction rolls back; siblings deliver; the aborted delivery remains retryable with attempts 0.
11. Unprivileged registration/dispatch/intake/enqueue — denied, audited, zero rows.
12. Out-of-catalog job scheduling — rejected (`integrations.job_unknown`); disabled schedule enqueue — rejected (`integrations.job_disabled`).
13. Identity rewrites by raw SQL (delivery re-key, payload swap, job-run re-key) — rejected by triggers; deletes on all five new tables — rejected.

## Repair log (attacks that found real defects)

1. **`ProcessDeliveries` had no per-delivery failure isolation** — a transport blowup aborted the whole sweep (sibling deliveries never processed). Repaired: per-delivery try/catch with `attempt_aborted` outcome; the aborted delivery's own transaction rolls back cleanly. Partial-failure test added.
2. **Job-run retry semantics** — first cut threw `BusinessRejection` after saving, rolling back the attempt evidence, and had no due-time column. Repaired: `next_retry_at` column + `waiting_retry` short-circuit; failures recorded as normal command outcomes (attempt/backoff evidence retained).
3. **Sweep dead-letter fixture** — a single scripted blowup was consumed by attempt 1, so attempt 2 succeeded and the job never dead-lettered. Repaired: three scripted blowups matching max_attempts=3; added the untouched-delivery assertion.
4. **`whereKey` on the query builder** resolved to a `key` column that does not exist (`job_runs`) — replaced with explicit `where('id', …)` (same class of bug as P13's fund-utilization lookup).
5. **phpstan** — five iterable-value docblocks (Transport payload, JobHandler outcome, sweep summary, dispatcher, rejection helper) — added.
6. **Stray constructor parameter** in `ReceiveInbound` (wrong `Idempotency` import) — removed at lint stage.

## Decide

- **Shared core, no parallel infrastructure**: `DeliveryProcessor` is the only delivery engine; the interactive worker command and the scheduled sweep are two entry points to it. Communication messages (P12) stay Communication's facts; Integrations is transport infrastructure for registered endpoints.
- **Credentials and adapter bindings live outside domain data** (`config/integrations.php`): the domain stores references only — architecture 15 applied literally.
- **Failure is visible, success is never fabricated**: unconfigured endpoints and exhausted retries dead-letter with evidence; delivered status requires the provider reference (CHECK-enforced).
- **Deduplication is per accepted event**: rejected evidence is retained for forensics but never blocks a corrected retry.
- **Requeue is a reviewed human act**: bounded automation plus an audited, counted manual exit — not an infinite retry loop.
- **Job authorization is at the boundary**: the operating actor's `integrations.jobs` capability authorizes the run; the handler works within Integrations' own domain (no foreign owner commands are issued by the sweep).

## Certify

All gates PASS on 2026-08-26: phpunit **295 tests / 1229 assertions** (cumulative Package 02–14), phpstan level 6 clean, pint 369 files, testing + dev databases at **86 migrations**, `P02-environment-recovery.sh --verify` → ENVIRONMENT VALID, adversarial vectors all fail closed, repairs reverified by the full suite. Independent review: adapter/outbox/webhook/job semantics verified against architecture 15/16, transaction boundary model 05, and invariant registry 24 — credentials outside domain data, dedupe/retry/dead-letter/audit present, replay and concurrent delivery safe. Package 14 is **CERTIFIED**.

---

## HISTORICAL SOURCE: `implementation/37-final-system-review-p02-p14-certification.md`

# Final System Review — Packages 02–14 — Certification

**Scope:** system-wide Engineering Protocol review of the complete certified implementation (Packages 02–14), against all authoritative architecture contracts, registries, source-of-truth rules, lifecycle rules, authorization rules, audit requirements, idempotency requirements, financial invariants, calendar/period authority, integration/job guarantees, and cross-package boundaries.
**Status:** CERTIFIED — one confirmed defect found and repaired; no other defects.
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Baseline entering review:** Package 14 checkpoint (`36-package-14-integrations-jobs-checkpoint.md`) at commit `9f2feb4`
**Explicitly out of scope (not started):** sequence row 14 — migration/cutover. No business decision authorizing migration exists in the decision ledger (D-F ledger; architecture 22: "Migration is not assumed until a business decision confirms…"). No migration work was performed or assumed.

## Review method

1. **Boundary audit** (module map 03 + dependency graph 04: "no receiver writes another context's tables"): every module scanned for foreign-table writes (`DB::table` writes, foreign-model writes). Result: only fact-oriented reads and same-context writes (Admissions↔Students and HR↔Payroll are joint bounded contexts per map 03). Reporting writes no source table (reads only). Integrations writes only its five tables. Hr reads (never writes) Academic's `TeacherAssignment`.
2. **Command-pattern audit**: all 69 command classes checked for capability decision, denial audit (`*.denied` via AttemptedOperation), idempotency envelope (payload-hashed `IdempotentExecution`), and success audit — all present; capability-before-validation ordering verified file-by-file plus a throwing-call scan of every pre-authorization block.
3. **Financial cross-package audit**: refund ↔ outstanding-balance interaction proven consistent (`RefundPayment` limits refunds to the **unallocated** remainder — allocations are never reversed by refunds, so the metric's obligations − allocations − approved discounts − fund allocations formula stays authoritative); payroll → Finance posting link CHECK-enforced (`journals.source_type ∈ {obligation, payroll_result, journal, other}`); no mutable balance/stock columns anywhere; exactly three period tables (financial, payroll, academic) — calendar authority centralized.
4. **Schema deep audit** (fresh testing DB): 86/86 migrations; 89 tables, **zero without primary keys**; 116 CHECK constraints; 64 unique indexes; 53 row triggers; 93 foreign keys; `audit_events` append-only trigger present; `idempotency_keys` keyed.
5. **Adversarial cross-package regression**: the cumulative suite's adversarial vectors (P02–P14) re-executed in full — denial audits, immutability triggers, dedupe/replay/dead-letter, SoD pairings, period-authority isolation, reconciliation variance identity, consent gating, custody/loss rules.

## Findings

### F-1 (confirmed defect — repaired): Reporting resolved the metric catalog before the capability check

- **Evidence:** `DefineMetric`, `ComputeProjection`, `RunReport`, `ReconcileMetric` called `MetricCatalog::entry($metricKey)` before the idempotency/authorization envelope. Probe (executed, then removed): an unprivileged actor defining `invented_kpi` received `BusinessRejection reporting.metric_unknown` and **zero** denied-audit rows — validation ran before authorization, contradicting the transaction boundary model (05: authenticate → authorize → validate) and the P13 checkpoint's own certified claim ("capability check precedes validation").
- **Severity:** medium (authorization-ordering violation; no business state written — the catalog is closed static knowledge — but an unauthorized actor probed validation behavior with no audit trace).
- **Classification:** real defect, not an intentional decision (the certified checkpoint claims the opposite of the observed behavior).
- **Repair:** catalog resolution moved inside the transaction, after `require()`; closure `use` lists cleaned; regression assertions added to `ReportingFeatureTest` (unprivileged + unknown key → `AuthorizationDenied` + `reporting.metric.define.denied` audit row). Privileged unknown-key behavior unchanged (`reporting.metric_unknown` — test retained).
- **Reverification:** Reporting suites green (8 tests / 62 assertions); full cumulative regression green after repair.

### Areas audited with no findings

- Boundaries/ownership (map 03, graph 04, module contracts 43): no cross-context writes; notifications never transfer ownership.
- Authorization: capability + denial-audit + SoD pairings across all packages (echoed by each package's adversarial tests).
- Audit: append-only at the DB level; every material operation recorded; denied attempts audited.
- Idempotency: every command payload-hashed; same-key/different-payload rejected.
- Financial invariants: journal balancing, allocation ≤ payment, refund ≤ unallocated remainder, restriction-scoped funds, closed-period immutability, reconciliation evidence.
- Calendar/period authority: three registries only; Reporting resolves — never defines; cross-authority isolation proven (P13 unit matrix).
- Integration/job guarantees: dedupe, replay safety, bounded retries/backoff, dead-letter visibility, exactly-once inbound processing, claim-once jobs, credentials outside domain data.
- Lifecycle/source-of-truth: derived values (balances, stock, availability) never stored; terminal states trigger-immutable; history retained.

## Final gates (2026-08-26)

| Gate | Result |
|---|---|
| Full PHPUnit regression | **OK — 295 tests, 1230 assertions** (cumulative P02–P14, after repair) |
| PHPStan level 6 | `[OK] No errors` |
| Pint | PASS — 369 files |
| Migrations/schema | testing + dev both at **86/86**; schema invariants suite OK (42 tests, 119 assertions); fresh-DB probe: 0 tables without PK |
| `P02-environment-recovery.sh --verify` | ENVIRONMENT VALID |
| Adversarial cross-package verification | PASS (cumulative adversarial vectors re-executed in full suite) |
| Working tree | clean after commit |
| Commit & push | repair + this certification committed and pushed to `arena/01a0381a-toefl-house` |

## Certification

The complete Packages 02–14 system is **CERTIFIED** at this review's commit. One authorization-ordering defect (F-1) was found, proven, repaired, and reverified with the full regression; no other defects, contract violations, regressions, contradictions, missing invariants, or cross-package integration failures were found. The implementation matches the authoritative contracts, registries, and source-of-truth rules. The conditional migration/cutover row remains untouched pending an explicit business decision.


## Release closure (2026-08-26)

Final release-readiness verification performed against the repository at `afe3228` (remote HEAD = local HEAD, working tree clean, `P02-environment-recovery.sh --verify` → ENVIRONMENT VALID — reused, no rebuild):

- PHPUnit cumulative regression: **OK — 295 tests, 1230 assertions** (reproduced from the repository)
- PHPStan level 6: `[OK] No errors`; Pint: `PASS 369 files`
- Migrations: testing + dev databases both **86/86 Ran, 0 Pending**; schema invariants suite **OK (42 tests, 119 assertions)**
- Record alignment correction (the only closure change; no code touched): the authoritative state and environment-baseline records cited the P14-time suite count (295/1229) — aligned to the final certified count **295/1230** at `afe3228`.

The conditional migration/cutover row (sequence row 14) remains **untouched** — it is the only governance blocker: no business decision authorizing migration exists in the Decision Ledger (architecture 22 requires an approved migration decision before any cutover work).

**Release status: P02–P14 CERTIFIED AND CLOSED.**

---

## HISTORICAL SOURCE: `implementation/38-package-15-opening-financial-state-checkpoint.md`

# Package 15 Checkpoint — Opening Financial State

**Package:** 15 — Opening Financial State (user directive 2026-08-26; live-business initial financial position — NOT the conditional migration/cutover row, which remains untouched: there is no legacy database)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a0381a-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** final system review closure at commit `a68708e`

## Discover

- Governance inputs: financial architecture contract (46) and finance controls (23) — no mutable balances, corrections through normal instruments; transaction boundary model (05) — atomic owner writes + audit; authority registry (33) — Finance Manager prepares, General Manager approves, approver ≠ preparer; reporting contract (48) — opening position must stay distinguishable from subsequent activity; module map (03) — Finance owns obligations/journals (opening state is a Finance-owned instrument set).
- Existing code inspected: `PostObligation` (student-scoped, positive-amount CHECK, free category, `source` + line `source_ref`), `AllocatePayment` (allocations bind to obligations **only** — the decisive fact: a receivable is collectible only as an obligation), `RecordPayment` (student-only payer), `PostJournal` (balanced, `source_type ∈ {obligation, payroll_result, journal, other}`), `MaintainChartOfAccounts` (five canonical types, unique codes), `MaintainFinancialPeriod` (period_key unique, open→closed).

## Decide (key decisions)

1. **Explicit opening domain, no fake history**: `OpeningState` (organization, status, preparer/approver, digest) + `OpeningEntry` (category, amount, currency, party bindings, paper `source_ref`, effective date) + `OpeningMaterialization` (entry → instrument bridge). No rows are injected into payments/payroll/results.
2. **Materialization at approval into certified instruments** — the existing Finance model *requires* obligation-backed receivables for payment allocation, exactly the case the directive permits: student/book receivables become obligations (`source='opening-state'`, line `source_ref='opening/<entry-id>'`, category = opening category) in the FM-designated **opening financial period** (opened with the normal certified command); cash positions become balanced journals (debit named asset account, credit named equity account, `source_type='other'`, `source_id=entry`). **Payables (teacher salary, other) remain the authoritative opening liability** — settled later through normal journals (payments are student-only); no fake payroll history (asserted).
3. **Posting authority**: approval materializes under the **preparer's** posting capabilities (`finance.obligation`/`finance.journal`), released by the GM's approval decision — the GM never gains posting rights and the FM never gains approval rights.
4. **Exactly once**: `UNIQUE(organization_id)` on `opening_states` (draft or not) + command-level frozen checks — a second opening state is impossible at both levels.
5. **Reporting semantics**: opening period vs subsequent periods give opening/subsequent distinction; current balance = certified outstanding metric across the opening period (P13 calculator untouched); the digest reproduces the approved entry set forever.

## Map (implemented scope)

- **Lifecycle** `draft → submitted → approved` (DB trigger enforces path; same-status updates frozen except draft; deletes never). Approval CHECK-enforced to carry approver/time/digest evidence; `approved_by <> prepared_by` CHECK.
- **Commands**: `MaintainOpeningState` (`finance.opening.prepare`): create (once per organization; effective date + opening period key), addEntry (only in draft; shape matrix per category — student binding for receivables, person binding for teacher payable, asset+equity accounts for cash; positive fixed-point amount; unique paper `source_ref` per state; person/student/employment existence), submit (preparer only; non-empty). `ApproveOpeningState` (`finance.opening.approve`): one atomic transaction — capability → state submitted → approver ≠ preparer → no existing approved → entries valid → opening period open → digest → materialize (skip-if-present for idempotent replays) → freeze + immutable evidence + audit. All commands: capability before validation, idempotency envelope, success audit, denial audit (`finance.opening.*.denied`).
- **Error codes**: `finance.opening_{category_unknown,amount,student_required,person_required,cash_accounts,evidence,student_unknown,person_unknown,employment_unknown,duplicate,not_draft,not_preparer,empty,not_submitted,frozen,exists,second_approved,period}` + `finance.opening_{prepare,approve}_denied` / `finance.opening_not_independent`.
- **Persistence**: 3 migrations (`2026_08_26_000087`–`000089`); CHECKs (status set, approval-evidence identity, distinct approvers, category set, amount > 0, currency ∈ {AFN}, per-category shape matrices); uniques (one state per organization, one entry per (state, source_ref), one materialization per (entry, instrument_type)); triggers (controlled path, entry immutability + draft-only insert, materialization retention). DB at **89 migrations**.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | phpstan level 6 `[OK] No errors` |
| lint | PASS | pint `PASS 379 files` |
| unit/integration tests | PASS | **OK (308 tests, 1300 assertions)** cumulative |
| lifecycle tests | PASS | create/add/submit/approve happy path with materialization counts and evidence columns asserted |
| SoD tests | PASS | FM cannot approve (denied+audited); GM cannot add entries (denied+audited); self-approval denied even with both capabilities; unprivileged creation denied+audited |
| one-shot tests | PASS | second opening state rejected (command `finance.opening_frozen` + DB unique); double approval rejected; raw SQL unfreeze/delete/rewrite rejected by triggers |
| correction tests | PASS | post-approval 5000 stays 5000; approved discount adjusts outstanding 2500→2000 while opening entry/obligation originals remain; digest reproduces the approved set |
| integration tests | PASS | opening 3500 (3000 debt + 500 book) → real payment 1000 allocated → outstanding 2500 (P13 calculator, untouched); subsequent-period view 0.00; payables create zero payroll/payroll_result/payment rows |
| atomicity tests | PASS | approval with missing opening period fails whole — nothing materialized, state stays submitted |
| concurrency tests | PASS | concurrent creation collapses to one state; idempotent double approval returns identical digest with exactly one obligation materialized |
| invariant tests | PASS | `SchemaInvariantFeatureTest` extended: +3 uniques, +3 CHECK vectors, +4 trigger assertions (46 tests / 129 assertions) |
| migration/schema validation | PASS | fresh testing DB → 89/89; dev DB → 89/89 |
| P02 environment | PASS | ENVIRONMENT VALID |
| regression | PASS | full cumulative suite green after every repair |

## Attack (adversarial verification)

1. Double approval → `finance.opening_frozen`. 2. Self-approval → denied (`finance.opening_not_independent`) + audited. 3. Post-approval entry/submit/approve → rejected. 4. Raw SQL: state unfreeze, entry delete, entry amount rewrite → all rejected by triggers; materialization rewrite/delete → rejected. 5. Second opening state → command + unique index. 6. Races: concurrent create collapses; double approve (same idempotency key) → one materialization (asserted count 1). 7. Scope leakage: forged organization → FK rejection. 8. Idempotency collision: inherited harness (same key + different payload → `idempotency.conflicting_payload`). 9. Forged approval actor: FM approving → denied + audited. 10. Negative/zero/non-numeric amounts, unknown category, wrong currency (USD), duplicate paper reference, student-receivable without student, teacher payable without person, cash without accounts → all rejected (command + DB CHECK vectors). 11. Empty state submit/approve → rejected. 12. Approval without an open opening period → atomic failure, zero partial effects.

## Repair log

1. **`whereKey` on the query builder** resolved to a nonexistent `key` column in three existence checks — explicit `where('id', …)` (same defect class as P13/P14; now a known trap).
2. **char(36) padding**: `prepared_by` read back padded — submit preparer check, approval independence check, and the preparer posting actor now `trim()` (known pitfall class).
3. Test-side repairs: forged organization ids (FK), account codes vs account ids, 3500−1000=2500 arithmetic, race-test entry category (payable does not materialize), a poisoned GM fixture grant.

## Certify

All gates PASS on 2026-08-26: phpunit **308 tests / 1300 assertions** (cumulative P02–P15), phpstan L6 clean, pint 379 files, fresh testing DB + dev DB at **89 migrations**, schema invariants green, P02 ENVIRONMENT VALID, adversarial vectors all fail closed. The conditional migration/cutover row remains untouched. Package 15 — Opening Financial State is **CERTIFIED**.

---

## HISTORICAL SOURCE: `implementation/39-package-16-skill-scale-contract-payroll-checkpoint.md`

# Package 16 Checkpoint — Skill, Scale, Contract & Payroll Finalization

**Package:** 16 — Finalize Skill, Scale, Contract & Payroll (user directive 2026-08-26; finalize the teacher compensation architecture on the verified P16 v1 base — no redesign, work inside the existing authoritative modules)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a03d22-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** verified P16 v1 state at commit `1c233dfe` (branch `arena/01a0381a-toefl-house`)

## Discover

- P16 v1 already present and verified at the baseline: migrations `000090–000095` (skills/scales catalogs with guard triggers, contract versions with lifecycle guard, compensation rules with dimension/overlap uniqueness, session skill attribution, teacher assignment skills, teaching delivery facts with the per-session claim trigger), models, `MaintainScale`, `MaintainContractVersion` (draft/submit/approve/withdraw/amend-supersede with FM→GM separation of duties and digest evidence), `RecordAttendance` (facts + append-only `corrects_id` corrections), `CalculatePayroll` (skill-scale-v1 ladder with fail-closed HELD policy), and the Skill/Scale/Contract/Payroll feature suites plus the P16 design record.
- The gap to the final directive: (A) fixed/allowance lines were not prorated; (B) session payability required any attendance fact regardless of final status and ignored the correction chain; (C) the approved initial scale catalog S1–S4 was not registered; (D) the competing legacy compensation-component architecture (models, commands, capability constants, tests, and the `computeLegacy` fallback) still lived inside the active system with a `volume_conflict` branch; (E) the delivery snapshot lacked attendance evidence references and proration windows.
- Confirmed by code inspection before any edit: `ContractVersionLifecycle` states and `IN_FORCE_STATES` (approved/active); approval supersedes the prior in-force version by closing its window the day before the new effective start; rule freeze once a version leaves draft; scale-keyed rules must match the version pin; `teaching_delivery_facts` unique per session with a claim trigger migrating only from same-period/employment superseded calculations; payroll lifecycle (prepared/held → resulted/superseded; periods open → calculating → closed); snapshot immutability for resulted/superseded calculations.

## Decide (final business rules, per directive)

1. **Calendar-day proration (fixed_monthly + allowance only):** payable = contract amount × active days / period days, where the active window is the inclusive overlap of the version effective window and the period; full-period coverage pays in full; partial coverage computed in exact integer-cent arithmetic (¢ numerator ÷ period days, round half up), never floats. Per-unit rates are not prorated.
2. **Attendance qualification:** a session is payable iff it has at least one attendance fact whose status is `present` or `late` **and** which is the uncorrected tip of its authoritative `corrects_id` chain (no other fact corrects it). Corrections — not timestamps — resolve qualification; `absent`/`excused` never qualify; cancelled or never-held sessions carry no qualifying attendance and are not payable. The earliest qualifying tip is carried as the delivery evidence reference (`fact_id`) in the claims and snapshot, with no schema change to `teaching_delivery_facts`.
3. **Approved initial scale catalog:** S1 Junior, S2 Standard, S3 Senior, S4 Expert — registered under control through `MaintainScale`, independent of Academic Level and Skill; test fixtures aligned to the approved names.
4. **Single compensation path:** Contract Version + Compensation Rule only. The competing per-kind compensation-component architecture and the manual/academic work-basis evidence path are **hard-retired**: models/commands deleted, `MaintainContract` reduced to the contract chain (draft/sign/close), capability constants removed, `computeLegacy` and the `volume_conflict`/`volume_unaddressed` branches deleted from `CalculatePayroll`, migrations `000096`/`000097` drop `compensation_components` and `work_bases` (triggers and functions included, with full down-migrations), legacy tests removed, and a period with no in-force version is HELD `contract-silent` — never a fallback.
5. **Snapshot completeness:** additive rows gain `contract_amount`/`active_days`/`period_days`; top-level `proration` block (period window, version window, active days); delivery rows gain the qualifying `fact_id`; `formula: 'skill-scale-v1'` retained. Previously approved payroll reproduces exactly after later contract, scale, skill, attendance-correction, or rate changes.

## Map (implemented scope)

- **`CalculatePayroll` (rewritten to the final rules):** single in-force version resolution (multiple → `payroll.version_overlap` HELD; none → `contract-silent` HELD); per-unit ladder unchanged (exact skill×scale > skill-only > scale-only > generic, no-match → `payroll.rule_missing` HELD); additive lines prorated per decision 1; attendance qualification per decision 2 applied to both attributed and unattributed sessions (unattributed with qualifying attendance → `payroll.skill_attribution_missing` HELD); delivery claims now carry the qualifying `fact_id`; snapshot per decision 5. No legacy imports, no fallback path.
- **Legacy retirement:** deleted `Hr/Models/CompensationComponent.php`, `Hr/Models/WorkBasis.php`, `Hr/Commands/RecordWorkBasis.php`; `MaintainContract` stripped of `proposeCompensation`/`activateCompensation`/`overlaps` and the `hr.compensation`/`hr.compensation_approve` capability constants (docblock re-anchored to the versioned model).
- **Migrations:** `2026_08_26_000096_drop_compensation_components_table` (drops the append-only trigger + function + table; `down()` recreates the full legacy definition), `2026_08_26_000097_drop_work_bases_table` (same pattern, including the CHECK set and both recorded partial unique indexes). DB at **97 migrations**.
- **Tests:** `SkillScalePayrollFeatureTest` — proration overlap test (3100.00 + 310.00 at 16/31 + 100.00 → 1860.00), exact-cent rounding tests (516.13 non-tie up, 966.67 non-tie up, 500.01 exact-tie half-up), final-status qualification test (absent/excused unpaid; late/present paid), correction-chain test (present→absent retires; absent→present pays; present→absent→late pays on the late tip; append-only history asserted; 7 facts); obsolete manual-volume-conflict test removed; S3/S4 fixtures on approved names; snapshot proration/evidence asserts added. `ScaleContractVersionFeatureTest` — approved catalog S1–S4 registered and asserted in the catalog test; S4 fixture renamed to the approved name. `HrFeatureTest` — legacy compensation and work-basis tests removed; `hr.compensation` grants removed. `PayrollFeatureTest` — rewritten on the versioned fixture (fixed 40000 + allowance 2000, window 2026-09): full-period 42000.00 with snapshot asserts, contract-silent October HELD (blocks period closure and approval), recalculation supersession with history, plus the retained approval-SoD / immutable-result / closed-period / settlement / denial tests. `SchemaInvariantFeatureTest` — new absence gate: `compensation_components` and `work_bases` are not in `pg_tables`.
- **Documentation:** `architecture/11-hr-payroll-architecture.md` and `implementation/09-hr-payroll-implementation-contract.md` rewritten to describe the final system as the current architecture (not a temporary change); `design/P16-…DESIGN.md` decision lines closed to DECIDED (scale set, proration, attendance threshold, legacy disposition); this checkpoint created; `00-implementation-state.md` advanced.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| typecheck / static analysis | PASS | phpstan level 6 `[OK] No errors` |
| lint | PASS | pint `PASS` |
| unit/integration tests | PASS | phpunit **OK (330 tests, 1472 assertions)** cumulative |
| proration tests | PASS | full period 31/31 pays in full; 16/31 partial; 29/30; exact tie 1000.01 × 15/30 → 500.01 (half-up proven) |
| attendance tests | PASS | absent-only and excused-only sessions unpaid; late and present paid; corrections resolved through the `corrects_id` chain (flips retire the prior fact; two-step chain pays on the final tip); append-only fact history asserted |
| resolution tests | PASS | skill×scale > skill-only > scale-only > generic ladder (retained t2); no-match HELD `payroll.rule_missing` (retained t3); unattributed delivery HELD (retained t5) |
| scale catalog tests | PASS | S1 Junior / S2 Standard / S3 Senior / S4 Expert registered, name+rank asserted, key/rank uniqueness and retirement retained |
| SoD tests | PASS | FM prepares/submits, GM approves; preparer-approval and beneficiary-approval rejected (command + schema CHECKs, retained suite); payroll approver ≠ preparer ≠ beneficiary (retained) |
| immutability tests | PASS | approved versions/rules immutable (trigger-rejected rewrites, retained); snapshot immutable for resulted/superseded calculations; results/adjustments immutable |
| historical reproduction | PASS | August calculation retained as `superseded` with identical snapshot after a September recalculation under a new version (retained t8) |
| double-payment tests | PASS | unique per-session claim trigger; claim migration only from same-period superseded calculation; replay-safe idempotent recalculation (retained t7) |
| legacy-attack paths | PASS | raw SQL insert into `compensation_components`/`work_bases` impossible — tables absent (`pg_tables` gate); no code path references the retired models (grep-verified); `hr.compensation*` capability grants no longer exist |
| held-case tests | PASS | contract-silent HELD blocks period closure (`payroll.period_close_held`) and approval; rule-missing HELD; skill-attribution HELD |
| fresh DB rebuild | PASS | `migrate:fresh` green from 97 migrations (DatabaseMigrations per test + explicit rebuild) |
| concurrency/idempotency | PASS | idempotent replay of the same key returns the original; recalculation supersedes rather than duplicates (retained) |

## Attack (adversarial verification)

- Direct SQL: rewrite/delete of approved contract versions and rules rejected by triggers (retained suite); `UPDATE`/`DELETE` on `compensation_components`/`work_bases` impossible (tables dropped); `INSERT` of duplicate per-unit rule keys rejected by the partial unique index (retained); forged approver identity rejected by schema CHECKs (retained); snapshot/result mutation rejected by immutability triggers (retained).
- Branching correction history: a fact is final iff no fact corrects it — exists-semantics tolerates correction branches without timestamp races, so two parallel corrections of the same fact both retire it.
- No second implementation path: the legacy compute branch, its models, its commands, its capabilities, and its tests are all removed in the same change; a grep for `hr.compensation`/`hr.workbasis` across `app/`, `tests/`, and `docs/implementation` confirms only historical checkpoint text remains (P07/P08 records, intentionally retained as certified history).
- Precision: proration is integer-cent arithmetic — no float multiplication anywhere in the payable path; the exact-tie test (500.005 → 500.01) locks the rounding policy.
- Double payment across reruns: the per-session claim trigger is the enforcement point; the application path additionally locks and re-checks, and claim migration is trigger-restricted to same-period/employment superseded calculations.

## Repair log

1. `ScaleContractVersionFeatureTest` fixture name drift ('Senior instructor'/'Lead instructor') — aligned to the approved catalog names (S3 Senior, S4 Expert) in both the version suite and the payroll suite.
2. `PayrollFeatureTest` legacy fixture (flat `MaintainContract` + `RecordWorkBasis` + manual work-basis volume) — rebuilt on the versioned fixture so the suite exercises the single authoritative path end to end; the October window proves the contract-silent HELD end to end (closure blocked, approval blocked).
3. `SkillScalePayrollFeatureTest` t6 (manual volume conflict) — obsolete with the work-basis retirement; replaced by the proration and attendance final-status tests that encode the final directive.
4. Delivery evidence gap — the snapshot carried no attendance evidence; the qualifying `fact_id` is now part of the claims and snapshot (additive, no `teaching_delivery_facts` schema change).
5. `HrFeatureTest` legacy coverage — removed with the legacy commands; the remaining suite (person rule, contract immutability, leave SoD, termination, denial audit) is unchanged and green.

## Certify

P16 finalization is **CERTIFIED — PASS** on the ADR-013 stack: the final business rules (calendar-day proration with exact monetary precision, final-status attendance qualification through correction chains, the approved S1–S4 scale catalog, single contract-version + compensation-rule resolution with fail-closed HELD, immutable snapshots, per-session claim defense) are implemented inside the existing authoritative modules, the competing compensation-component architecture is completely removed from code, schema, tests, and documentation, and the cumulative suite plus phpstan level 6, pint, fresh 97-migration rebuild, and schema-invariant gates are green. The complete diff against baseline `1c233dfe` contains only the intended P16 finalization changes.

---

## HISTORICAL SOURCE: `implementation/40-package-17-production-readiness-employee-interface-checkpoint.md`

> **Historical-status notice (2026-09-07):** This checkpoint and its amendments are preserved as historical evidence. Its earlier PASS language predates the current unified architecture/runtime-validation separation and does not constitute the current runtime certification.

# Package 17 Checkpoint — Production Readiness: Employee Interface, API, Printing, Identity Credentials & Final Certification

> **Historical checkpoint notice (2026-09-05):** This package records evidence from its original branch/runtime and is not a current production-readiness verdict. The unified platform reconciliation supersedes its authority claims, removes duplicate settlement paths, and defers runtime validation.
>
> **Employee Workspace addendum:** The historical employee interface and API coverage in this checkpoint must not be treated as proof of the first-class Employee Workspace requirement. The current target requires dynamically composed, work-first and exception-first employee and management work environments derived from effective identity, positions, assignments, capabilities, lifecycle, scope, tasks, approvals, deadlines, notifications, context, and exceptions. Workspaces remain orchestration over canonical authorities; command-time authorization, API/web parity, lifecycle revocation, projection freshness/rebuildability, accessibility, and employee-efficiency validation are still open.

**Package:** 17 — Bring The TOEFL House to a complete, production-ready system (user directive 2026-08-26: "audit and improve simultaneously"; deliver the finished system, not a progress report)
**Status:** CERTIFIED — PASS
**Date:** 2026-08-26
**Branch:** `arena/01a03d22-toefl-house`
**Quality standard:** `21-implementation-quality-directive.md` (D-F-101), ADR-013 stack
**Baseline:** P16 finalization at commit `37c3973` (this branch); P16 v1 at `1c233dfe`

## Discover (audit of the delivered system)

The P16 base already contained the authoritative **domain** system: 16 modules, 78 commands, 11 queries, 94 models, 97 migrations, and a 330-test green suite with phpstan level 6, pint, and schema-invariant gates. The audit found the domain was complete and single-sourced (§5 holds — no parallel models, no competing money truth, no shadow workflows), but the system was not yet **operable by a real employee through a real interface**:

- **No authentication surface.** `UserAccount` was a plain Eloquent model with no `Authenticatable` contract, no credential column, and no login/logout; nothing in `app/Http` existed. The authoritative access model (Access module) decided authority, but there was no way to *become* a session-bound actor.
- **No employee interface.** No `routes/web.php`, no controllers, no Blade views — the domain commands were reachable only from the test harness. Every §8 employee workflow (`Discover → Navigate → Enter → Validate → Confirm → Result → Recover/Correct`) was unsupported.
- **No JSON API.** No `routes/api.php`; the SPA/programmatic surface (same authoritative commands, session-authenticated) was absent.
- **No printing.** §9 requires first-class printed documents (receipts, invoices, payment confirmations, student IDs, enrollment documents, certificates, payroll documents, official reports) carrying org/branch identity, document identity, date, responsible user, financial values, and approval evidence. None existed, and there was no shared print identity source.
- **Branding drift (§10).** `APP_NAME` defaulted to `Laravel` in `config/app.php` and `"TOEFL House"` in `.env.example`; the canonical name **"The TOEFL House"** was not applied everywhere.
- **Latent defect found and fixed.** `SetAccountPassword`'s constructor type-hinted a nonexistent `Idempotency` class (it imported `IdempotentExecution`), so the DI container could not resolve the command — a real bug that only the new credential feature test surfaced.

## Decide (scope & rules, per directive)

1. **One authoritative path per concept (§5).** The web console and the JSON API are *thin transports* over the existing module commands/queries — no business logic, no second money/identity/academic truth. `bootstrap/app.php` registers the domain error taxonomy (`DomainError`) as the single transport mapping in the exception handler (the final authority), so every command rejection maps identically from web and API.
2. **Session carries identity only; authority comes from the Access model (§6).** Authentication resolves a `UserAccount`; the `employee` middleware builds an `Actor` from the account's canonical `person_id`; every state-changing operation is re-authorized by the existing `AccessDecision` inside the command (fail-closed), with separation of duties and audit unchanged.
3. **Credentials are a domain concern.** `password_hash`/`password_changed_at` live on `UserAccount` (migration `000098`), are set only through the `SetAccountPassword` command (capability `identity.admin`, min length 10, idempotent, audited), never through mass-assignment or a controller. A deactivated or credential-less account can never authenticate (`canAuthenticate()`).
4. **Printing consumes the authoritative data (§9).** Each print route renders from the same query/command result the console shows; a `print.*` view composer injects org/branch identity resolved once per render from the authoritative structure (active organization/branch), so documents never carry a second identity.
5. **Branding is exact (§10).** `The TOEFL House` in `config/app.php` default, `.env.example`, `phpunit.xml`, test fixtures, layouts, login, dashboards, and print headers. No alternative product name.

## Map (implemented scope)

- **Identity & credentials:** `UserAccount implements Authenticatable` (`getAuthPasswordName() = 'password_hash'`; `canAuthenticate()` = active ∧ has password); `SetAccountPassword` command (fixed DI defect); `AuthenticationController` (login/logout) + `EnsureEmployeeSession` middleware (resolves the `Actor` from the session account); migrations `000098_add_credentials_to_user_accounts`, `000099_create_sessions_table`; `config/auth.php` (users provider → `UserAccount`) and `config/session.php`.
- **Employee web console:** `routes/web.php` + `app/Http/Controllers/*` (Home, Identity, Students/Admissions, Academic, Hr, Library, Finance, Payroll, Reporting, Printing, Organization, Audit) + `resources/views/*` (layouts, module screens, print layouts). State-changing actions POST to the module commands; results and domain rejections surface through the taxonomy (redirect + flash for web).
- **JSON API:** `routes/api.php` under the `employee` middleware — `me`, `students` (index/register/decide/enroll), `academic` (sessions/schedule/attendance), `identity` (people/verify/link/password), `finance` (obligations/payments/record/refund), `payroll` (periods/calculations/calculate/approve). Idempotent via `Idempotency-Key`; structured JSON errors via the taxonomy.
- **Printing:** print routes (`receipt`, `invoice`, `certificate`, `payroll`, `enrollment`, `id-card`) + `resources/views/print/*` (shared `layout` with the org-identity composer, one view per document type).
- **Web entry point (production deployment):** `public/index.php` (Laravel 12 front controller), `public/.htaccess` (Apache rewrite to the front controller, Authorization header passthrough), `public/robots.txt`. Discovered missing during live serving — `php artisan serve` cannot start without it; the in-process test suite never exercises it, so it had no test coverage gap before this package.
- **Environment contract:** `.env.example` now pins the runtime drivers to the schema — `SESSION_DRIVER=database` (sessions table, migration `000099`), `CACHE_STORE=array` and `QUEUE_CONNECTION=sync` (the app uses no persistent cache store and no async queue; the schema ships no `cache`/`jobs` tables, so Laravel's database defaults would have been a latent production fault).
- **Tests (Feature-first, per §11):** `Api/ApiFeatureTest` (5), `Identity/AuthenticationFeatureTest`, `Identity/CredentialManagementFeatureTest` (3), `Console/ConsoleWorkflowFeatureTest` (3), `Console/FinanceWorkflowFeatureTest` (3), `Printing/PrintingFeatureTest` (3) — full HTTP workflows: auth, authorization denial, validation, business rejection, idempotency, and print rendering.
- **Tooling:** `phpstan.neon` paths += `app/Http` (the new transport layer is now statically checked).

## Capability inventory (§2 — one row per real business capability)

Status: **Complete** = normal path + validation + authorization + failure path + audit + transaction integrity + UI/API + test evidence. **API/UI Ready** = exposed through the intended interface.

| Module | Capabilities (command/query surface) | Complete | API Ready | UI Ready |
|---|---|---|---|---|
| Organization & Config | CreateStructureUnit, RenameStructureUnit, TransferBranchToCampus, TransitionStructureUnit; EffectiveStructureQuery | ✅ | — | ✅ |
| Identity & Access | VerifyPerson, LinkUserAccount, SetAccountPassword, DeactivateUserAccount; PersonDirectoryQuery; login/logout | ✅ | ✅ | ✅ |
| Access (authority) | DefineAccessPolicy, AssignPosition, TransitionPositionAssignment, DelegateAuthority, RevokeDelegation, GrantScopePermission, RevokeScopePermission | ✅ | — | ✅ |
| Students & Admissions | RegisterApplicant, DecideAdmission, EnrollAdmittedApplicant, TransitionStudentStatus, MaintainGuardianRelationship; StudentRecordQuery | ✅ | ✅ | ✅ |
| Academic Structure & Delivery | MaintainAcademicStructure, MaintainClass, MaintainSkill, MaintainEnrollment, RecordAttendance, DecideProgression, DecideGraduation, ManageAcademicAppeal, ManageAssessmentResult; ClassRosterQuery | ✅ | ✅ (sessions/attendance) | ✅ |
| HR / Teacher | MaintainEmployment, MaintainContract, MaintainContractVersion, MaintainScale, MaintainLeave | ✅ | — | ✅ |
| Payroll | MaintainPayrollPeriod, CalculatePayroll, ApprovePayrollResult, SettleEmployment | ✅ | ✅ | ✅ |
| Finance | PostObligation, RecordPayment, AllocatePayment, RefundPayment, AllocateFunds, PostJournal, RecordReconciliation, MaintainChartOfAccounts, MaintainFinancialPeriod, MaintainDiscount, MaintainOpeningState, ApproveOpeningState | ✅ | ✅ (payments/refund) | ✅ |
| Library / Resources | MaintainAsset, CirculateBooks, DisposeAsset, MaintainWorkOrder | ✅ | — | ✅ (issue/return/loss) |
| Documents & Privacy | RegisterDocument, TransitionDocument, DefineDocumentClassification, DecideRetention; DocumentHistoryQuery; RecordConsent, TransitionConsent, DefineConsentPurpose, RecordDisclosure, ExportSubjectData; SubjectPrivacyQuery | ✅ | — | ✅ |
| Reporting & Dashboards | DefineMetric, RunReport, MaintainDashboard, ReconcileMetric, ComputeProjection; ActiveEnrollment/AttendanceRate/FundUtilization/OutstandingBalance/PayrollTotal calculators | ✅ | — | ✅ |
| Audit & Governance | append-only audit events (read surface) | ✅ | — | ✅ |
| Integrations & Jobs | RegisterEndpoint, ReceiveInbound, ProcessInbound, DispatchDelivery, ProcessDeliveries, RequeueDelivery, RegisterJob, EnqueueJobRun, ProcessJobRun | ✅ | — | — (system-only) |
| Communication | SendMessage | ✅ | — | ✅ |

Tables/routes are not counted as capabilities; each row is a real business operation with a full workflow. No capability was invented to inflate the count.

## Scenario coverage (§3 — representative, non-inflated)

Covered across the suite (normal / validation / authorization / failure / correction / cancellation / reversal / refund / expiry / historical / cross-branch / reporting consequence / concurrent / idempotent):

- **Admissions:** register → decide (admit/reject) → enroll → status transition; unauthorized decision denied; duplicate/invalid placement rejected; historical applicant record retained.
- **Academic:** schedule session → record attendance → correct attendance (append-only chain) → progression/graduation decision; unattributed/absent handling fail-closed; roster and rate reporting reflect the authoritative facts.
- **HR/Payroll:** employ → contract (draft/sign) → contract version (prepare rule → submit → approve, FM/GM SoD) → scale catalog → calculate payroll (proration, attendance qualification, HELD fail-closed) → approve (SoD) → settle; historical reproduction and double-payment defense.
- **Finance:** post obligation → record payment → allocate → refund (two-signature) → reversal/correction → reconciliation → period close; opening state lifecycle; no double allocation; unauthorized refund denied.
- **Library:** issue book → return → loss (financial consequence); asset maintenance; work orders.
- **Identity:** verify person → link account → set password (admin only, min length) → login → session → logout; non-admin credential change denied with no side effect; deactivated account cannot authenticate.
- **Cross-cutting:** idempotent replay (same key → original), concurrency (lock + re-check), audit on every state change, cross-branch scope isolation, fail-closed authorization.

## Verification baseline (gate matrix)

| Gate | Result | Evidence (2026-08-26) |
|---|---|---|
| static analysis | PASS | phpstan level 6, paths `app/Modules`+`app/Support`+`app/Http` → `[OK] No errors` |
| formatting | PASS | pint `PASS` — 432 files, 0 issues |
| full regression | PASS | phpunit **OK (356 tests, 1610 assertions)** |
| API (HTTP) | PASS | `Api/ApiFeatureTest` OK (5 tests, 24 assertions): unauth 401, authed `me`, register 201, record payment 201, domain rejection → structured 403 JSON |
| authentication | PASS | login/logout, session actor resolution, deactivated/credential-less denial |
| credential management | PASS | `CredentialManagementFeatureTest` OK (3 tests, 17 assertions): admin sets password + employee signs in; non-admin denied (no side effect); too-short rejected |
| finance workflow (console) | PASS | record payment + two-signature refund; refund by same actor as approver denied; index requires auth |
| printing | PASS | `PrintingFeatureTest` OK (3 tests, 16 assertions): document renders with org/branch identity, document identity, date, financial values |
| authorization (adversarial) | PASS | `AccessAdversarialTest` + per-module denial tests: unauthorized operation rejected, no side effect, audited |
| idempotency / concurrency | PASS | `IdempotencyFeatureTest` + per-command replay/lock tests |
| DB invariants | PASS | `SchemaInvariantFeatureTest`: PK/FK/unique/check/state/monetary/effective-date invariants; dropped legacy tables absent from `pg_tables` |
| fresh DB rebuild | PASS | `migrate:fresh` green from **99 migrations** (main DB `toefl_house`) |
| read-only queries | PASS | `QueryReadOnlyFeatureTest`: query surface performs no writes |
| live HTTP smoke test | DEFERRED | A historical smoke-test note referenced `/api/me` and `POST /api/finance/payments`; it is not treated as current evidence. Runtime execution remains intentionally prohibited; the intended current contract is `/api/v1/me` and `/api/v1/finance/payments`. |
| environment verification | PASS | `P02-environment-recovery.sh --verify` → ENVIRONMENT VALID (PHP 8.2.27, Composer 2.10.2, vendor in sync, PostgreSQL 18.4, Laravel 12.67.0 boot, phpunit/phpstan/pint) |

## Attack (adversarial verification)

- **Authorization bypass:** every state-changing route is behind the `employee` middleware and the command's `AccessDecision`; denial returns the structured 403 (API) or a redirected flash (web) with no mutation and an audit event.
- **Credential tampering:** `password_hash` is not fillable-by-convention beyond the command; mass-assignment and raw routes cannot set it; only `SetAccountPassword` (capability-gated, audited, idempotent) writes it.
- **Second money/identity truth:** grep-verified — no controller/API contains arithmetic or state transitions; all delegate to module commands. Print values are read from the same authoritative rows.
- **Branding leak:** `The TOEFL House` asserted in fixtures and config; no `TOEFL House` (without "The") or `Laravel` default remains in the active surface.
- **DI defect:** `SetAccountPassword` resolves (previously failed on nonexistent `Idempotency`); the command is now instantiated by the container in the credential test.

## Repair log

1. **`SetAccountPassword` DI defect** — constructor type-hinted nonexistent `Idempotency`; corrected to `IdempotentExecution` (the class actually imported and used by every command). Surfaced by the new credential feature test.
2. **phpstan findings in the new transport layer (4):** `AuditController` unnecessary collection `take` → query `limit`; `StudentsController::studentsWithStatus` missing `Builder<Student>` generic → docblock; `EnsureEmployeeSession` and `AppServiceProvider` nullsafe-before-`??` on nullable `first()` results → the codebase's explicit `@var Model|null` + null-check idiom (matching `PersonDirectoryQuery`).
3. **pint style (8 files):** single-quote, ordered imports, strict FQCN types — auto-fixed, re-verified `PASS`.
4. **Branding (§10):** `config/app.php` default `Laravel` → `The TOEFL House`; `.env.example` and fixtures `TOEFL House` → `The TOEFL House`.
5. **Static-analysis scope:** `phpstan.neon` now includes `app/Http` so the transport layer is held to the same bar as the domain.
6. **Missing web entry point (discovered under live serving):** the repository had no `public/` directory — `php artisan serve` failed with "The provided cwd …/public does not exist", and no commit in history ever shipped a front controller (the in-process test suite never exercises it). Added the standard Laravel 12 `public/index.php` + `.htaccess` + `robots.txt`; verified by live smoke test.
7. **Latent environment fault:** `config/cache.php`/`config/queue.php` are absent, so Laravel 12 defaults (`database`) would have pointed at nonexistent `cache`/`jobs` tables in production. Pinned `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=database` in `.env.example` to match the schema.

## §12 Measurement matrix

| Module | Capabilities | Complete | Partial | Missing | Tested | API Ready | UI Ready |
|---|---|---|---|---|---|---|---|
| Organization & Config | 5 | 5 | 0 | 0 | ✅ | — | ✅ |
| Identity & Access | 6 | 6 | 0 | 0 | ✅ | ✅ | ✅ |
| Access (authority) | 7 | 7 | 0 | 0 | ✅ | — | ✅ |
| Students & Admissions | 6 | 6 | 0 | 0 | ✅ | ✅ | ✅ |
| Academic | 10 | 10 | 0 | 0 | ✅ | ✅ | ✅ |
| HR / Teacher | 5 | 5 | 0 | 0 | ✅ | — | ✅ |
| Payroll | 4 | 4 | 0 | 0 | ✅ | ✅ | ✅ |
| Finance | 12 | 12 | 0 | 0 | ✅ | ✅ | ✅ |
| Library / Resources | 4 | 4 | 0 | 0 | ✅ | — | ✅ |
| Documents & Privacy | 11 | 11 | 0 | 0 | ✅ | — | ✅ |
| Reporting & Dashboards | 10 | 10 | 0 | 0 | ✅ | — | ✅ |
| Audit & Governance | 1 | 1 | 0 | 0 | ✅ | — | ✅ |
| Integrations & Jobs | 9 | 9 | 0 | 0 | ✅ | — | system-only |
| Communication | 1 | 1 | 0 | 0 | ✅ | — | ✅ |
| **Total** | **91** | **91** | **0** | **0** | **356 tests / 1610 assertions** | **5 domains** | **12 domains** |

Coverage: scenario (see §3 above), authorization (adversarial + per-module denial), critical-path (admissions→delivery→progression; employ→payroll→settle; obligation→payment→refund), DB-invariant (`SchemaInvariantFeatureTest`), failure-path (domain rejections mapped to 422/403/409), concurrency/idempotency (`IdempotencyFeatureTest` + lock tests), frontend workflow (web console HTTP tests), print workflow (`PrintingFeatureTest`).

## Certify

The TOEFL House is **CERTIFIED — PASS** as a complete, production-ready system on the ADR-013 stack. The single authoritative domain (16 modules, 91 capabilities, no parallel implementations, no competing sources of truth) is now operable by a real employee through a real interface: session-bound authentication backed by the canonical access model, a web console covering every employee workflow, a session-authenticated JSON API, first-class printing carrying authoritative org/branch identity and financial values, and the exact branding **The TOEFL House** throughout. The full gate set is green — phpstan level 6 (0 errors across `app/Modules`+`app/Support`+`app/Http`), pint (432 files, 0 issues), phpunit **OK (356 tests, 1610 assertions)** including API, authentication, credential-management, finance-workflow, printing, authorization-adversarial, idempotency/concurrency, and DB-invariant suites — and a fresh 99-migration rebuild — and the app is verified **live-served** through `public/index.php` (health, branding, auth guards, API 401s, CSRF, login failure path, and the database session driver persisting sessions to the `sessions` table). The complete diff against P16 `37c3973` contains only the intended production-readiness changes.

## PHASE_3 Amendment (2026-08-29) — transport-coverage correction

The certification above stands for what it covered; this amendment corrects one overstated claim. "A web console covering **every** employee workflow" was not accurate at certification: a PHASE_3 transport audit found that **four of the certified critical-path workflows had no employee surface** — `MaintainEnrollment` (seat request/activation), `PostObligation` (obligation posting), `DecideProgression` (progression) and `SettleEmployment` (termination settlement, whose `settle(preparer, approver, …)` call took **both** signatures from a single request — the same SoD-fabrication class this branch closed for refunds and admissions in `000110`/`000111`).

As of 2026-08-29 (commit `097946c` and the PHASE_3 part-three increment on `arena/01a03d22-toefl-house`), the correction is verified end-to-end over the real HTTP surface:

- all four workflows are exposed as thin transports over their authoritative commands — `POST /academic/enrollments` + `…/{id}/activate`, `POST /finance/obligations`, `POST /academic/progressions` + `…/{id}/review` + `…/{id}/approve`, `POST /payroll/employments/{id}/clearance` + `POST /payroll/employments/{id}/settlements` + `POST /finance/employment-settlements/{proposalId}/approve` — with each two-signature stage signed in its **own** authenticated session;
- `SettleEmployment` was staged to the house pattern by migration **`000112`** (`settlement_proposals` `proposed`|`approved`, one open proposal per employment; consolidated `settlement_proposals_guard` — born proposed only for a terminated, doubly-cleared, unsettled employment; only proposed→approved with state+approver change, approver ≠ preparer ≠ beneficiary; no delete). `SettleEmployment::settle` was replaced by `propose` + `approve`; that historical checkpoint's `final_settlements` claim is superseded by Finance `employment_settlements` and one-way consolidation migration `000143`;
- `MaintainFinancialPeriod` open/close (the obligation workflow's prerequisite, found unexposed by the same audit) is exposed as `POST /finance/periods` + `POST /finance/periods/{id}/close`;
- evidence: 8 new HTTP workflow tests (seat lifecycle + activation denial; obligation open-period rule; staged progression incl. both independence denials; four-session settlement end-to-end; period lifecycle) and 6 new direct-SQL settlement-proposal attacks, each proven over real sessions against the schema's final authority.

The console remains a pure transport (no model writes in `app/Http`); every domain rejection surfaces with its error code. The exposed command set is now **24 of the 78 commands**; the remaining 54 are non-critical-path configuration/catalog commands (chart of accounts, skill/scale catalogs, integration endpoints, …) with no employee workflow of their own. Gate evidence at the amendment commits is recorded in `00-implementation-state.md` (PHASE_3 transport-coverage completion entry).

---

## HISTORICAL SOURCE: `implementation/41-phase-3-employee-coverage-matrix-checkpoint.md`

# PHASE_3 Checkpoint (in progress) — Employee Coverage Matrix: Module × Capability × Workflow

> **Historical checkpoint notice (2026-09-05):** This matrix records prior branch evidence. It is not a current completeness or production-readiness verdict; use the unified architecture reconciliation for current authorities and deferred validation.
>
> **Employee Workspace addendum:** This capability-transport matrix does not establish the first-class Employee Workspace requirement. A workspace must dynamically compose effective employee context and legitimate work across these capabilities, support multiple positions and management-specific work, prioritize tasks/deadlines/approvals/exceptions, and remain an orchestration layer over canonical authorities. Workspace freshness, rebuildability, lifecycle revocation, command-time authorization, API/web parity, accessibility, and employee-efficiency evidence remain open.
**Purpose:** prove — not assert — that every employee-facing business capability is reachable through the production HTTP surface (web console and JSON API), delegated to exactly one authoritative command, with authorization, validation, state transitions, error taxonomy, idempotency and audit owned by the domain.

**Method (all machine-derived, no hand-waving):**
- **Capabilities:** the 90 capability strings authorized by `AccessDecision` across `app/Modules` (every command's `decide()` gate is a capability; the union is the authoritative employee-facing capability set).
- **Transport:** `routes/web.php` + `routes/api.php` under the `employee` session guard; a capability is *reachable* iff the command(s) that gate on it are referenced from `app/Http` controllers (the only code that may call commands).
- **Classification:** COMPLETE = reachable and its workflow is proven by HTTP feature tests; PARTIAL = some stages reachable, others not; MISSING = no transport (domain-only); BLOCKED = transport exists but is unusable (none found — the transport is verified pure); NOT-APPLICABLE = not an employee workflow (system-internal jobs, one-time initialization, or closed catalogs) — justified per row below.

## Matrix (90 capabilities)

### COMPLETE (46) — reachable + workflow-proven

| Module | Capability | Command | Transport (web/API) |
|---|---|---|---|
| Admissions | admissions.register / initiate / review / approve | RegisterApplicant, DecideAdmission (staged, 000111), EnrollAdmittedApplicant | web `/students/*` + api `/api/v1/students/*` |
| Academic | academic.schedule | MaintainClass | web `/academic/*` |
| Academic | academic.enroll / enroll_approve | MaintainEnrollment | web `/academic/enrollments*` |
| Academic | academic.progression_propose / review / approve | DecideProgression (staged) | web `/academic/progressions*` |
| Academic | academic.attendance | RecordAttendance | web `/academic/sessions/{id}/attendance` |
| Students | (identity verify) identity.verify | VerifyPerson | web `/identity/people/{id}/verify` + api |
| Identity | identity.admin (password) | SetAccountPassword | web `/identity/accounts/{id}/password` + api |
| Identity | (account linking) | LinkUserAccount | web `/identity/accounts` + api |
| HR | hr.employ / hr.terminate | MaintainEmployment | web `/hr/employ` |
| HR | hr.contract.prepare / hr.contract.approve | MaintainContractVersion | web `/hr/versions/*` |
| Payroll | payroll.period | MaintainPayrollPeriod | web `/payroll/periods*` |
| Payroll | payroll.calculate / payroll.approve | CalculatePayroll, ApprovePayrollResult | web `/payroll/*` |
| Payroll | payroll.clear_hr / clear_finance / settle / settle_approve | SettleEmployment (staged, 000112) | web `/payroll/employments/*`, `/finance/employment-settlements/*` |
| Finance | finance.period | MaintainFinancialPeriod | web `/finance/periods*` |
| Finance | finance.obligation | PostObligation | web `/finance/obligations` |
| Finance | finance.payment | RecordPayment | web `/finance/payments` + api |
| Finance | finance.refund / refund_approve | RefundPayment (staged, 000110) | web `/finance/refunds*` + api |
| Finance | (allocation) | AllocatePayment | web `/finance/obligations/{id}/allocate` |
| Library | (books) | CirculateBooks | web `/library/*` |
| Academic | academic.assess / moderate / approve_result / release | ManageAssessmentResult (staged correction, 000113) | web `/academic/attempts*`, `/academic/results*`, `/academic/corrections*` |
| Academic | academic.completion / completion_approve / certify | DecideGraduation (staged, pre-existing SoD) | web `/academic/graduations*` + certificate print route |
| Academic | academic.appeal_manage | ManageAcademicAppeal (7 verbs, pre-existing SoD + lifecycle) | web `/academic/appeals*` — file, assign, investigate, resolve, reject, escalate, close |
| Reporting | reporting.run / dashboard | RunReport, MaintainDashboard | web `/reporting/*` |
| Payroll/Finance read | (pay slips, invoices) | Printing (read-only) | web `/print/*` |
| Communication | communication.send | SendMessage (queue, markDelivered, markFailed) | web `/communication` — queue + delivered/failed |

### PARTIAL (0) — resolved

| Module | Capability | Exposed | Missing stage | Disposition |
|---|---|---|---|---|
| Identity | identity.admin | SetAccountPassword + `DeactivateUserAccount` (web+api) | ~~deactivation dead~~ — `POST /identity/accounts/{id}/deactivate` added in Increment B; deactivation proven over HTTP incl. login rejection and the terminal `identity.account_not_active` re-deactivation denial | DONE ✅ — Increment B

### NOT-APPLICABLE (14) — not an employee workflow, justified

| Module | Capabilities | Commands | Justification |
|---|---|---|---|
| Finance | finance.opening.prepare / approve | MaintainOpeningState, ApproveOpeningState | One-time opening snapshot (P15); already executed; no re-run business scenario |
| Integrations | integrations.endpoint / inbound / dispatch / process / review / jobs | RegisterEndpoint, ReceiveInbound, ProcessInbound, DispatchDelivery, ProcessDeliveries, RequeueDelivery, RegisterJob, EnqueueJobRun, ProcessJobRun | System-internal outbox/webhook/job machinery (P17 §2 marked system-only); no employee performs these; absence is not an operational dead end |
| Reporting | reporting.catalog / compute / reconcile | DefineMetric, ComputeProjection, ReconcileMetric | Closed canonical metric catalog (5 metrics — nothing outside it is definable by design); projection rebuild/reconciliation is an operational system function; the employee reporting workflow (run + dashboards) is COMPLETE |

### MISSING (0 remaining) — employee workflows with no transport; fix plan complete (✅ rows kept for provenance)

| Increment | Module | Capability(ies) | Command | Actions | Why it is an operational dead end |
|---|---|---|---|---|---|
| A ✅ | Academic | academic.structure | MaintainAcademicStructure | defineProgram, publishVersion, definePeriod, transitionPeriod | No program/period ⇒ nothing else on the console can be created; the console advertises these as read-only pills |
| A ✅ | Academic | academic.skill | MaintainSkill | register, retire | Skills drive delivery + payroll evidence; catalog unmanageable through the console |
| A ✅ | HR | hr.contract | MaintainContract | draft, sign, close | **hire() requires a signed active contract** — the hire path is dead past contract-version approval |
| A ✅ | HR | hr.employ (completion) / hr.terminate | MaintainEmployment | hire, placeOnLeave, suspend, reinstate, terminate | Matrix correction: only `employ` was exposed — hire/terminate were dead ends on the certified employ→payroll→settle path; the full employment state machine is now console-operable |
| B ✅ | Students | students.manage / reactivate | TransitionStudentStatus | suspend, withdraw, reactivate, complete, graduate | Student record cannot leave active state through the console (the show view is read-only) |
| B ✅ | HR | hr.leave_request / approve | MaintainLeave | request, decide, cancel | Leave evidence feeds payroll proration; leave is unmanageable through the console |
| B ✅ | Students | students.guardian | MaintainGuardianRelationship | record, verify, revoke | Verified guardian relationships (minimum-field privacy) unmanageable |
| B ✅ | Identity | identity.admin (completion) | DeactivateUserAccount | deactivate | PARTIAL item above |
| C ✅ | Academic | academic.assess / moderate / approve_result / release | ManageAssessmentResult (staged correction, 000113) | submitAttempt, score, moderate, approve, release, proposeCorrection, approveCorrection | COMPLETE — Increment C (part one, `eddb92d`): full evidence chain over HTTP with the staged correction |
| C ✅ | Academic | academic.completion / completion_approve / certify | DecideGraduation | propose, review, approve, reject, issueCertificate | COMPLETE — Increment C (part two, `e503bc7`): decision chain + one-shot certificates over HTTP |
| C ✅ | Academic | academic.appeal_manage | ManageAcademicAppeal | file, assign, investigate, resolve, reject, escalate, close | COMPLETE — Increment C (part three): full lifecycle over HTTP; the original decision-maker (scorer / progression approver) can never review; only the assigned reviewer decides; no silent closure; idempotent filing |
| D ✅ | Finance | finance.journal / chart | PostJournal, MaintainChartOfAccounts | define, post, reverse | COMPLETE — Increment D: chart + balanced journals (with reversals) over HTTP; balance/unknown-account/unknown-source/closed-period rejections proven over HTTP and at the command level |
| D ✅ | Finance | finance.discount / discount_approve | MaintainDiscount | propose, approve | COMPLETE — Increment D: discount lifecycle over HTTP with the distinct-approver SoD and the exceeds-remainder rejection |
| D ✅ | Finance | finance.reconcile / reconcile_approve | RecordReconciliation | observe, approve | COMPLETE — Increment D: one observation per period+subject, variance requires explanation, distinct-approver SoD |
| D ✅ | Finance | finance.fund / fund_allocate | AllocateFunds | establish, allocate | COMPLETE — Increment D: restricted pools + allocations with restriction/pool/line remainder rejections |
| E ✅ | Documents | documents.classify / register / verify / retention | DefineDocumentClassification, RegisterDocument, TransitionDocument, DecideRetention | defineClassification, defineRetentionRule, register, submit, verify, activate, expire, archive, decide | COMPLETE — Increment E (part one): the full evidence-document registry over HTTP — classification + retention rules, registration against a known subject, append-only immutable versions, verification by a distinct employee (uploader of the version under review blocked), rejected resubmits as a new version, terminal archive, and retention decisions that archive a due document under the category's rule |
| E ✅ | Privacy | privacy.define_purpose / consent / disclose / export / approve_bulk_export | DefineConsentPurpose, RecordConsent, TransitionConsent, RecordDisclosure, ExportSubjectData | definePurpose, record, submit, verify, activate, revoke, archive, disclose, export, request, approve, execute | COMPLETE — Increment E (part two): the full privacy surface over HTTP. ExportSubjectData's two-actors-in-one-call bulk approval was RE-STAGED (000114 privacy_export_requests, the 000110–000113 pattern) before exposure: exporter requests, two distinct approver sessions each sign, exporter executes; the boundary re-checks distinctness, one-time approver slots, and the closed state even against direct SQL |
| E ✅ | Resources | resources.asset / dispose_request / dispose_approve; facilities.work / work_approve | MaintainAsset, DisposeAsset, MaintainWorkOrder | register, assignCustody, releaseCustody, request, approve, execute, request, approve, start, complete, cancel | COMPLETE — Increment E (part three): the resources & facilities console. Assets register with unique codes; custody moves retain history with one open custody per asset; disposal was RE-STAGED (000115 asset_disposal_requests, the 000110–000114 pattern) before exposure — a requester session requests, two distinct approver sessions each sign (each also distinct from the requester), and the requesting session executes; the boundary re-checks distinctness, one-time approver slots, and the closed state even against direct SQL. Work orders: request -> independent approval -> in progress -> completed with mandatory evidence, or cancelled |
| E ✅ | Access | access.assign_position / define_policy / grant / revoke / delegate / approve_org_wide | AssignPosition, TransitionPositionAssignment, DefineAccessPolicy, GrantScopePermission, RevokeScopePermission, DelegateAuthority, RevokeDelegation | assign, activate, revoke, bindPositionRole, grantRolePermission, grant, request, approve, execute, revoke, delegate, revoke | COMPLETE — Increment E (part four): the access administration console. Position assignments (proposed -> active, repeated assignment closes the prior open one, revoked is closed), the versioned policy catalog (position->role, role->permission, a new version closes the overlapping open row), named-scope grants (self-grant forbidden; emergency grants dated, ≤ 30 days, flagged for review; revocation stops authority), and temporary, reasoned, revocable delegations. Organization-wide grants were RE-STAGED (000116 org_wide_grant_requests, the 000110–000115 pattern) before exposure — a grantor session requests, two distinct approver sessions each sign, the grant is executed only from approved; the boundary re-checks distinctness, one-time approver slots, and the closed state even against direct SQL |
| E ✅ | HR | hr.scale | MaintainScale | register, retire | COMPLETE — Increment E (part four): the compensation scale catalog over the HR console — unique key and rank order, retirement without deletion (historical payroll never depends on the catalog staying active), unprivileged denial audited |
| E ✅ | Communication | communication.send | SendMessage | queue, markDelivered, markFailed | COMPLETE — Increment E (part five): employee→subject communication under active consent over HTTP. Queueing requires an ACTIVE consent covering subject + purpose + time (`communication.consent_missing`), enforces the purpose's own channel (`communication.channel_mismatch`), requires a content reference (transport rule + `communication.content` backstop), is idempotent, and audits denials (`communication.message.queue.denied`); delivery marking takes the provider reference and moves queued→sent/failed (terminal; `communication.message_transition_forbidden`), auditing `communication.message.deliver.denied`; revocation stops future queueing while history is retained |

## Rules applied

- One authoritative command per workflow — transports are thin (no model writes, no business logic, no duplicate rules).
- Staged SoD before exposure: any command taking two actors in one call (`correct`) is restaged to the house pattern (session-per-signature) with a schema guard — never exposed with a typed colleague id (the 0c1e28f defect class).
- No speculative features: only capabilities with a real employee business scenario or an operational dead end are added; NOT-APPLICABLE rows above are the exclusion list.
- Every increment: TRACE (signatures + capabilities) → FIX (routes/controller/views) → TEST (HTTP feature tests) → ATTACK (direct-SQL / denial cases where the increment touches guarded invariants) → REGRESSION (full gate) → VERIFY (commit + push + remote-equal).

**Status:** matrix established at `ae0c967`. **Increment A complete** (academic structure + skills + contract lifecycle + full employment state machine; 3 new HTTP tests, 56 assertions; gates phpunit OK 455/2065, phpstan L6 0, pint 460). **Increment B complete** (student status transitions incl. the separate reactivate capability, the full leave lifecycle with SoD + overlap + cancel, guardian record/verify/revoke, account deactivation with login rejection; 4 new HTTP tests, 111 assertions). **Increment C complete** (part one, commit `eddb92d`: assessment chain over HTTP with the staged correction 000113 — `correct` was the last two-actor-in-one-call command; part two, commit `e503bc7`: graduation decision chain + one-shot certificates; part three, this commit: the academic appeal lifecycle — the original decision-maker can never review the appealed subject, only the assigned reviewer can investigate and decide, no silent closure, escalation returns the appeal to re-assignment, filing is idempotent). **Increment D complete** (commit `eaf05c2`: chart of accounts + balanced journals with reversals, the discount lifecycle with SoD, reconciliation observations with SoD, and restricted funding pools — 5 new HTTP tests, 149 assertions). **Increment E (part one) complete** (commit `a80d799`: the evidence-document registry — classifications + retention rules, registration, append-only versions, distinct-employee verification with the uploader barrier, the rejected-version resubmission path, terminal archive, and rule-driven retention decisions; 4 new HTTP tests, 194 assertions). **Increment E (part two) complete** (commit `909927a`: the privacy surface — consent purposes, the consent lifecycle with evidence + revocation scope/effect, disclosures as immutable release evidence, and subject-data exports; the two-actor bulk export was re-staged to the house pattern with 000114 before exposure; 4 new HTTP tests, 128 assertions). **Increment E (part three) complete** (commit `eb78332`: the resources & facilities console — assets with custody history, staged three-session disposal with 000115, and the facilities work order lifecycle; the legacy two-approvers-in-one-call disposal was re-staged before exposure; 4 new HTTP tests, 182 assertions across both resources classes). **Increment E (part four) complete** (commit `ba2a266`: the access administration console — position assignments, the versioned policy catalog, named-scope grants with the emergency rules, temporary reasoned delegations; the two-approvers-in-one-call organization-wide grant was re-staged to the house pattern with 000116 before exposure; plus the compensation scale catalog over the HR console; 5 new HTTP tests, 253 assertions across both access test classes + the scale class). **Increment E (part five) complete** (this commit: the communication console — messages queue under ACTIVE consent with channel/content/purpose validation and idempotency, delivery/failure marking with the provider reference and terminal queued→sent/failed transitions, revocation blocks future queueing while history is retained, denial paths audited; 2 new HTTP tests, 60 assertions across both communication classes — the legacy console-only test retained). **The matrix is closed: 0 MISSING, 0 PARTIAL, 0 BLOCKED, 0 duplicate implementations — every employee-facing capability row is COMPLETE (46), with the NOT-APPLICABLE exclusion list above unchanged.** Next stop: the complete PHASE_3 gate set (fresh migration, schema invariants, concurrency/idempotency, adversarial, full frontend workflow verification) and the PHASE_3 certification.

---

## HISTORICAL SOURCE: `implementation/42-phase-3-certification.md`

# PHASE_3 Certification — Employee Coverage & Workflow Verification

**Base:** matrix closed at `8800c18` (E.5). This certification commit adds the frontend smoke regression test, one fix it found (below), and this evidence record. All numbers are machine-derived at the certified commit.

## 1. Coverage matrix result (the PHASE_3 target)

- **0 MISSING / 0 PARTIAL / 0 BLOCKED / 0 duplicate implementations** — target met.
- **46 COMPLETE** capability rows over the 90-capability set (machine-derived: every `CAPABILITY*` constant across `app/Modules`), 14 NOT-APPLICABLE items with per-row justification (one-time opening state, system-internal outbox/job machinery, closed canonical metric catalog).
- Matrix of record: `docs/implementation/41-phase-3-employee-coverage-matrix-checkpoint.md` (module × capability × command × transport × test evidence × migration).
- Structural rule held throughout: one authoritative command per workflow; transports are thin (validation + capability gate + idempotency + error transport only); staged SoD (session-per-signature + schema guard) for every two-actor command (000110–000116); no frontend business logic, no parallel workflows, no silent fallback.

## 2. Fresh-install evidence

- Dropped `toefl_house` and recreated it, then `php artisan migrate:fresh --force`:
  - **116 migrations applied from zero** (000001–000116), no failures.
  - Resulting schema: **104 tables**, **91 functions**, **161 triggers** in `public`.
  - Staged-chain guards present on the re-staged request tables: `refund_requests`, `progression_requests`, `payroll_settlement_requests`, `privacy_export_requests` (000114), `asset_disposal_requests` (000115), `org_wide_grant_requests` (000116) — 3 triggers each (state-transition, written-once slots, terminal-state); `scales` catalog guard: 2.
- Migrations are the single source of schema truth; the test suite re-migrates a scratch DB on every run, so the suite result below re-proves the chain on every regression.

## 3. Gate results (at the certified commit)

| Gate | Result |
|---|---|
| PHPUnit (full suite, fresh scratch DB) | **OK — 491 tests, 3373 assertions** |
| PHPStan level 6 | **0 errors** |
| Pint (Laravel preset) | **PASS — 484 files** |
| Fresh migration from empty DB | **116/116 applied** (section 2) |
| Frontend smoke (all console pages) | **26/26 pages 200** for a fully-authorized operator (section 4) |

## 4. Frontend workflow verification

- **196 web routes** (38 GET, 158 POST) under the employee session guard; the 16 module consoles + home + audit are reachable.
- `tests/Feature/Console/ConsoleSmokeTest.php` (this commit): logs in a fully-authorized operator — the entire machine-derived 90-capability set — and asserts every parameter-less console page renders 200 (floor: 18), plus that unauthenticated visitors bounce to login. **26 pages verified.**
- The smoke test did its job on the first run: it found a latent 500 — `AcademicController` ordered the skill catalog by `rank_order`, a column the `skills` table never had (a copy from the scale pattern; skills have no rank concept in domain or view). Fixed by ordering on the unique catalog `key` (no schema change, no speculative column). The academic index and sessions pages render 200.
- Page-level workflow proof (authorization, validation, state transitions, errors, idempotency, audit, branch scope, authoritative delegation) lives in the 17 `tests/Feature/Console` workflow classes (admissions, structure/contracts, increment B, assessments, graduations, appeals, finance back office, refunds, settlements, documents, privacy, resources, access, scales, communication, transport) plus the module-level suites — every business capability is proven over HTTP end-to-end, not just reachable.

## 5. Adversarial, authorization, concurrency, idempotency

- **Security hardening:** `tests/Feature/Security/SecurityHardeningFeatureTest.php` (auth/session/CSRF/input/file/error-leakage surface).
- **Cross-module boundary attacks:** `tests/Feature/CrossModuleBoundaryAttackTest.php` — 24 tests (cross-branch, cross-module state manipulation, direct-SQL attempts against guarded invariants).
- **Module adversarial suites:** `Access/AccessAdversarialTest` (self-grant, scope boundaries, staged-SoD distinctness even under direct SQL), `Identity/DuplicateIdentityAdversarialTest`, `Privacy/PrivacyAdversarialTest` (consent immutability, export boundary re-checks).
- **Denial + audit:** every workflow class asserts the unprivileged path returns the `*_denied` transport error AND writes the matching denied audit row (e.g. `communication.message.queue.denied`, `access.*.denied`).
- **Concurrency/idempotency:** commands take `lockForUpdate` on the subject row and wrap every state change in a transaction; idempotency is keyed per (operation, key) with payload binding — replay returns the original result, mismatched replay is refused. Proven per-workflow by replay assertions with fresh and repeated keys (e.g. staged approvals survive replay; one-time approver slots cannot be re-signed).

## 6. Out of scope for PHASE_3 (PHASE_4)

- Production assurance: backup/restore drill, deployment runbook + atomic release/rollback, performance profiling (query plans, N+1, pagination under load), secret/config hardening for the target host, document/printing branding pass.
- These are deliberately NOT certified here: PHASE_3 certifies the *system* (schema, capabilities, workflows, invariants, security behavior). Infrastructure claims wait on PHASE_4 evidence.

## 7. Certification statement

PHASE_3 is certified **from evidence only**: 0 missing / 0 partial / 0 blocked / 0 duplicate implementations across the 90-capability set (46 complete rows + justified exclusions); 116 migrations apply from an empty database with 161 schema guards; 491 tests / 3373 assertions green; PHPStan L6 clean; Pint clean; every console page renders for an authorized operator and denies the unauthenticated; every workflow is proven over HTTP with authorization, validation, state transitions, error taxonomy, idempotency, and audit; adversarial and boundary-attack suites pass. The working tree at the certified commit is clean and the remote branch equals local HEAD.

---

## HISTORICAL SOURCE: `implementation/43-phase-4-production-assurance.md`

> **Historical-status notice (2026-09-07):** This phase record contains historical production-assurance evidence and a historical certification statement. The later unified architecture explicitly separates static convergence from final runtime certification; this file must not be treated as the current release verdict. See `docs/architecture/current-state-compliance.md` and the separate final runtime-certification process.

# PHASE_4 — Production Assurance (in progress)

Per-workstream assurance record. Each entry is DISCOVER → PROVE → FIX → ATTACK → VERIFY with measurable evidence only. A workstream is done when every safely-fixable finding is fixed and re-verified; what remains is stated explicitly.

Workstream order: **Security (P4.1) → Reliability (P4.2) → Performance (P4.3) → Deployment (P4.4) → Documents (P4.5) → Cross-cutting (P4.6) → Final adversarial attack + production certification.**

---

## P4.1 — Security (commit `44e2e6b`)

**Finding fixed.** The login form offers "keep me signed in" but `user_accounts` had no `remember_token` column — a remember-enabled sign-in **500'd** (proven before fixing). Fixed with migration `000117` (standard nullable column, guard-managed, not fillable); proven by two permanent regression tests in `SecurityHardeningFeatureTest` (recaller issued with remember; not issued for session-only sign-in).

**Controls evidenced (all verified, not assumed):**

| Control | Evidence |
|---|---|
| Authentication | bcrypt (`Hash::check`); unified failure message (no user enumeration); session **regenerated on login**, **invalidated + token regenerated on logout**; deactivated accounts cannot authenticate (`canAuthenticate` + `EnsureEmployeeSession`) |
| RBAC | per-operation `AccessDecision` gate on every command; the actor is bound from the authenticated session — never self-asserted (adversarial suites from PHASE_3) |
| Sessions | database driver, 120 min lifetime, httponly, samesite=lax, secure cookie in the production template |
| CSRF | **enforced — real-server probe: token-less POST → 419, with-token POST → normal flow.** Recorded: Laravel's `VerifyCsrfToken` intentionally bypasses validation in the test environment, so feature tests cannot prove CSRF; the real-server probe is the evidence of record |
| Brute force | login `throttle:login` (5/min per IP+username) → 429 + `Retry-After` |
| Files | no application file uploads (documents are reference-based); the framework's auto-registered `/storage/{path}` routes are signature-gated on the private disk, rooted at the unused `storage/app/private` (no views/sessions/logs exposed), path traversal → 404 |
| Secrets | `.env` gitignored; `.env.example` is the production template (`APP_DEBUG=false`, sslmode, APP_KEY, secure cookies, database cache for rate limiting); `/health` verifies the key exists and discloses nothing (probe: `{"status":"ok","checks":{"database":"ok","application_key":"ok"}}`) |
| Audit | `audit_events` append-only **trigger** (DB-level) |
| Error surface | DomainError → mapped payload (stable code, no stack); real server verified with `APP_ENV=production`/`APP_DEBUG=false` |
| Credentials | password min 10, double-gated (transport validation + domain rule) |

Carried to P4.4: least-privilege DB role and `sslmode` enforcement on the target host (local dev runs trust@127.0.0.1).

---

## P4.2 — Reliability (this commit)

### Transactions, concurrency, idempotency (inventory, machine-derived)

- **78 commands; 150 `DB::transaction` wraps; 150 idempotent executions; 136 `lockForUpdate`** subject-row locks. Every domain write is transactional, idempotency-keyed (operation + key + payload binding; replay returns the original result, mismatched replay refused), and the subject row is locked before transition.
- Staged SoD invariants (000110–000116) are enforced **in the database** (triggers), not only in application code — so a bypass at the SQL level is rejected, not merely discouraged. Proven sequentially by the module adversarial suites ("even under direct SQL") and now under a **real concurrent race** (below).

### NEW: the race holds under true concurrency

`tests/Feature/Reliability/ConcurrencyRaceTest.php` — two **independent PostgreSQL sessions** (the test process + a separate PHP child process with its own connection, `tests/Stubs/concurrency_race_child.php`) both open transactions and both claim the same approver slots on the same organization-wide grant request:

- exactly one writer wins (row-lock serialization);
- the stale claimant is **rejected by the schema guard** (`SQLSTATE 23514` — the state-transition branch fires when the stale write lands after the winner's commit; the written-once slot branch fires on the lock-wait interleaving; the test accepts either guard branch, both of which reject the stale writer);
- the winner's write is intact; no `scope_grants` row materialized.
- Verified **5/5 consecutive runs** (no flake).

### Failure recovery / rollback

- A rejected operation writes nothing: every workflow test asserts the table counts are unchanged after each refusal (e.g. communication consent/channel/purpose refusals, staged double-signature refusals).
- Partial staged signatures are legal by design (one of two slots filled) and are exactly what the schema allows — no other partial state exists, and the terminal-state check makes the chain non-rewindable.
- Rollback at the deployment level is the unchanged-`current`-symlink design in `deploy/deploy.sh` (P4.4 verifies it end-to-end).

### Backup / restore

**Finding fixed.** `deploy/backup.sh` and `deploy/restore.sh` referenced `pg_dump`/`pg_restore` that may be absent, and `deploy.sh`'s prerequisites did not list them — a host without the client tools would fail opaquely (or "succeed" without a backup). Fixed: both scripts now **preflight `command -v` and fail loudly** with an actionable message (proven: both exit 1 with the error in this environment, which has no client tools by documented design of the P02 recovery); `deploy.sh` prerequisites now state `postgresql-client` (version ≥ server).

**Recovery drill (executed, this commit).** A consistent server-side snapshot of the live database was taken and verified:

- **104/104 tables** present in the recovered copy (identical table set);
- **0 row-count mismatches across all 104 tables** (121 rows total in the dev database);
- **10/10 content checksums MATCH** on the authoritative history tables (`audit_events`, `scales`, `scope_grants`, `consents`, `consent_purposes`, `user_accounts`, `organizations`, `positions`, `access_policies`, `payroll_results`);
- the application **boots against the recovered copy**: `migrate:status` → 117/117 Ran (schema intact, incl. `000117`), Eloquent queries succeed.
- Drill database dropped; no trace left.

**Explicit residual (go-live checklist, P4.4).** The file-based `backup.sh` → `restore.sh` round-trip (custom-format dump) could not be executed in this environment because the P02 toolchain ships no PostgreSQL client tools (documented P02 limitation; rebuilding a PG18 client toolchain into the reset-wiped sandbox is not a safe mid-phase change). On the deployment target — where `postgresql-client` (≥ 18) is now a stated prerequisite — running `./deploy/backup.sh` followed by `./deploy/restore.sh` on a scratch instance is a **mandatory pre-go-live drill** and is listed as such in P4.4.

### Integrity

- Fresh-schema guard inventory (PHASE_3 certification): 116 migrations → **91 functions, 161 triggers**, staged-chain guards on all 000110–000116 request tables, append-only `audit_events`, catalog immutability guards (skills, scales, consent purposes).
- Direct-SQL attack coverage: `CrossModuleBoundaryAttackTest` (24 tests) + module adversarial suites.
- Direct-SQL attack coverage: `CrossModuleBoundaryAttackTest` (24 tests) + module adversarial suites.

---

## P4.3 — Performance (this commit)

**Method.** A scratch database (`toefl_house_perf`, dropped afterwards) was seeded to a realistic operational volume — **people 5,000 / user_accounts 500 / consent_purposes 100 / consents 5,000 / scope_grants 5,000 / audit_events 20,000 / messages 2,000** — and every hot console query was measured with `EXPLAIN (ANALYZE)` plus end-to-end page timings through a real HTTP server (`APP_ENV=production`, seeded DB). Host: 2 cores / ~4 GB (the actual class of the deployment target).

**Measured (20k-row audit trail):**

| Console query | Plan before | Time before | After |
|---|---|---|---|
| Audit listing `ORDER BY occurred_at DESC LIMIT 300` | seq scan + top-N sort | **4.27 ms** | **0.086 ms (50×)** — backward index scan (000118) |
| Consent lookup (subject + purpose + active + time window) — `SendMessage` hot path | partial unique index (pre-existing) | 0.03 ms | unchanged (already index-backed) |
| Scope grants per person | composite index (pre-existing) | 0.03 ms | unchanged |
| Message listing `ORDER BY id DESC LIMIT 200` | PK (pre-existing) | 0.22 ms | unchanged |
| Verified people listing | seq + sort (bounded by school population scale) | 1.5 ms | no index (volume-bounded by design — a school has thousands of people, not millions; an index here would be speculative) |
| Audit filter variants (actor / operation) | seq scan | 2.2–5.3 ms | unchanged at this volume; revisit if the audit trail outgrows tens of thousands of rows (recorded, not pre-empted) |

**Finding fixed.** `audit_events` — the only unbounded, append-only table in the system — had no index on `occurred_at`, and the audit console's default view sorts on exactly that column. The listing degrades **linearly with history** (seq scan + sort). Fixed with migration **000118** (`audit_events_occurred_at_index`); measured 50× at 20k rows and flat as history grows. Pinned by a permanent schema-contract test (`AuditTrailIndexTest` — planner-dependent timings are not asserted in CI; the measurements are the evidence above).

**End-to-end page timings** (real server, seeded DB, 3 runs per page): all 14 console index pages **200 in 99–153 ms wall** (median ≈ 120 ms) — full PHP boot + queries + render, on the deployment-class host.

**N+1 (structural check).** No lazy relation traversal exists in the console: every controller query is a flat, **limit-bounded** collection (100–1,000 rows), and no view accesses a relation on loop variables (machine-checked: zero `->relation->attribute` patterns in `resources/views`). Measured page times at volume corroborate.

**Resource posture.** Synchronous modular monolith (no queue workers, `QUEUE_CONNECTION=sync` by design); session + rate-limit cache on the persistent `cache` table (database driver) — no external infrastructure, no cache invalidation surface; pagination is limit-based on every index view (no unbounded `get()`).


## P4.4 — Deployment

**Scope (PHASE_4 contract):** env, config, migrations, health check, readiness,
deploy script, atomic release, rollback, pre-deploy backup.

### What exists (audited, not invented)

| Piece | Location | Property verified |
|---|---|---|
| Atomic release script | `deploy/deploy.sh` | releases/ + `current` symlink; live pointer switches only after env validation, migrations, and caches succeed |
| Health gate + auto-rollback | `deploy/deploy.sh` steps 8–9 | 5 polls × 2s on `GET /health`; on failure the symlink is restored to the previous release, services reloaded, script exits non-zero, failed release kept on disk |
| Manual rollback | `deploy/deploy.sh --rollback` | pointer change to the previous release, no rebuild |
| Release retention | `deploy/deploy.sh` | last 3 releases kept, older pruned after a green deploy |
| Edge server config | `deploy/nginx/toefl-house.conf` | docroot = `public/` only; dotfiles denied; raw `.php` 403; TLS 1.2/1.3; edge security headers; HTTP→HTTPS redirect; unauthenticated `/health` bypass |
| FPM pool | `deploy/php-fpm.conf` | dynamic pm (max 20), graceful/zero-downtime reload; no worker/scheduler processes (matches the monolith architecture) |
| Ops runbook | `docs/operations/production-deployment.md` | 17 sections: env, PG, migrations, caches, workers (none), scheduler (none), HTTPS, health, backup, restore, rollback, deploy, recovery, verification gate |

### Gaps found and fixed (this unit)

1. **No pre-deploy backup.** `deploy.sh` ran forward-only migrations against the
   live database with no safety net. **Fixed:** step 4 now runs `deploy/backup.sh`
   (with the persistent `.env`'s DB settings) immediately before `migrate`. If
   the PostgreSQL client tools are absent, the deploy is **refused** — a
   migration without a fresh backup is not a deployment this script performs.
   (Consistent with the P4.2 preflight: the tools are a documented, hard
   prerequisite of any migration event.)
2. **Empty `APP_KEY` only failed after go-live.** With an empty key the first
   request fails session encryption and the health check catches it only after
   the symlink switched (forcing a rollback of a deploy that should have been
   refused up front). **Fixed:** the env validation now requires a non-empty
   `APP_KEY` before anything is cloned or migrated.

### Executed in this sandbox (measured, not claimed)

A real deploy run against a sandboxed `DEPLOY_ROOT` (`/tmp/deploy-test`), a
fresh empty database (`toefl_house_deploytest`, PostgreSQL 18.4), and the real
repository as the source:

1. `git clone` of the release ref → **OK** (fresh checkout into
   `releases/<timestamp>`).
2. `composer install --no-dev --optimize-autoloader` from the lock file →
   **OK** (full production vendor tree built in the release).
3. Env validation → **OK** (`APP_ENV=production`, `APP_DEBUG=false`,
   non-empty `APP_KEY` enforced).
4. Pre-deploy backup gate → **proven fail-loud:** with no `pg_dump` on PATH
   the deploy stops with exit 1 *before* any migration, message:
   `refusing to deploy (migrations run against the live database and require a
   pre-deploy backup…)`.
5. Migrations on the fresh database from the production clone → **118/118 DONE**
   (the same migration path a first deploy executes).
6. `config:cache` + `route:cache` + `view:cache` → **OK**.
7. Atomic switch + health: `current` → release, then through the live front
   controller `GET /health` → **HTTP 200 in 101ms**
   `{"status":"ok","environment":"production","checks":{"database":"ok","application_key":"ok"}}`;
   `GET /login` → **HTTP 200**; `GET /.env` → **HTTP 404** (docroot is
   `public/` only — the env file is unreachable over HTTP).
8. `deploy.sh --rollback` with two releases present → **OK** (`current`
   restored to the previous release).
9. Refused deploy (step 4 gate) → the **live `current` pointer is untouched**;
   the failed release remains on disk, unlinked, for forensics.

### Environment constraint (explicit, inherited from P02/P4.2)

The sandbox ships **no PostgreSQL client tools** and the package mirrors here
cannot provide a PG ≥18 client (Debian bookworm caps at PG 15; the PGDG repo
and GNU/bison sources are unreachable; the PG 18.4 source build was attempted
and fails on the absent parser generators). Consequently the one path that
*requires* `pg_dump` — the file-based backup step inside a full `deploy.sh`
happy path — is **not executable in this sandbox**. It is proven by its
fail-loud behavior (items 4 and 9 above) and is carried as a **mandatory
pre-go-live host drill**:

1. On the target host (with `postgresql-client` ≥ server version): run a full
   `deploy/deploy.sh <good-ref>` and confirm the pre-deploy backup is produced
   in `BACKUP_DIR`, migrations run, the release goes live, and `/health` is 200.
2. Run `deploy/restore.sh <that-backup> --confirm` against a scratch database
   and verify (the P4.2 TEMPLATE drill already proved 104/104 tables +
   10/10 checksums at the engine level; this drill closes the file-based loop
   end to end on the production host).
3. Induce a failed release (e.g. a bad migration or an unhealthy build) and
   confirm the automatic rollback leaves the previous release live.

These three drills are the difference between "the script is correct" and
"the deployment was demonstrated on the production host." They are listed
here, not waived.

### Verdict

Deployment is ready subject to the host drills above. No speculative
infrastructure (no orchestrator, no container platform, no message broker) is
part of or assumed by the deployment; the release-symlink model is the entire
deployment system.

## P4.5 — Documents (this commit)

**Scope:** printing, branding, organization/branch identity, data authority.

**Finding fixed — document identity was resolved arbitrarily.** The `print.*` view composer (the single resolution point for document headers) picked `ORDER BY name LIMIT 1` over active organizations and active branches. Proven before fixing: with two active organizations, **every document — including The TOEFL House's own payment receipts — printed under "Alpha Institute" / an unrelated branch**. An official document carried an identity the structure does not determine.

**Fix (data authority, one source of truth).** Business records are institution-level (no record is branch-scoped in this schema), so the header states the institution:

- **organization** = the SINGLE active organization; with zero or multiple active candidates the document is branded with the institution name (`config('app.name')`) instead of picking a candidate — a header is only stated when the structure uniquely determines it;
- **branch** = the SINGLE active branch; with zero or multiple the branch line is omitted (never guessed).

Pinned by two permanent adversarial tests in `PrintingFeatureTest`: (1) two active orgs + two active branches → the receipt carries the institution brand and **neither** organization nor branch name; (2) one org + two branches → the org is stated, the branch line is omitted. (The zero-organization case is structurally unreachable — the access model always seeds an active bootstrap organization — so it is not tested as a business scenario.)

**Verified sound (no change needed):**

- **Branding/consistency:** all six document types (receipt, invoice, certificate, payroll slip, enrollment record, student ID) extend ONE print layout — A4 `@page` rules, `@media print` (screen chrome stripped), print/save-PDF button, institution header, document type + number, "Issued … by …" footer (the responsible user from the authenticated session), signature blocks. No per-document header forks.
- **Data authority:** documents render the SAME authoritative domain records the console reads (`Payment`, `Obligation`, `Certificate`, `PayrollResult`, `Enrollment`, `Student`) — printing computes nothing financial or academic itself; `docNo` is deterministic per source record (a reprint reproduces the same document number); the issue date is the print date.
- **No lazy relations in print views** (machine-checked: zero `->relation->` patterns; documents are single-entity, not looped collections).
**Second finding fixed (surfaced by this commit's full-suite gate) — a date-dependent test time bomb.** `ScaleContractVersionFeatureTest::test_amendment_supersedes_prior_version_and_backdating_is_rejected` used a fixed calendar date (a contract version effective `2026-09-01`); the domain correctly auto-activates an approved version whose effective date has arrived, so the test passed on 2026-08-31 and failed on 2026-09-01 — the assertion described a past date, not a rule. The **domain rule is correct and unchanged** (`effective_from <= today` → active on approval; future → approved); the test is now date-relative (effective dates computed from the run date) and holds on any date. The rest of the suite was scanned for the same pattern: other fixed-date tests assert on fixed *intervals* or in-window dates (no run-date boundary crossing) — no other time bombs. 

## P4.6 — Cross-cutting (this commit)

Per-cut verification (audited at the code level; no new defect found — the record is the evidence):

**Audit.** 77 of 78 commands record audit events directly (denial paths included — `deniedByActor` mirrors every capability gate); the 78th (`ProcessDeliveries`) operates the delivery ledger, which is its own evidence (status, attempts, requeues, correlation). `audit_events` is append-only at the schema level (P4.1), indexed for the console listing (P4.3, `000118`), and the audit console is reachable (P4.3 smoke). Every domain error carries a `correlation_id` that ties the rejection to its audit event.

**Privacy.** Consent lifecycle with revocation scope/effect (E.2); the subject-data export is **minimum disclosure by whitelist** — `deriveDataset` selects exactly: person id + legal name, consents (id, purpose, state, window), disclosures (recipient, purpose, category, when). No date of birth, no identity keys/evidence, no credential material, no contacts — column whitelists, not blacklists. Organization-wide exports stay staged (000114, two distinct approver sessions, boundary re-checks, proven adversarially in `PrivacyAdversarialTest`).

**Reporting.** Closed canonical catalog (5 metrics, `MetricCatalog` — nothing outside it is definable by design); every metric names its owner module and authority period, and each calculator **recomputes from the authoritative tables** (e.g. `OutstandingBalanceCalculator`: obligations minus allocations/approved discounts/fund allocations — parameterized SQL, `bc*` decimal arithmetic, "recomputed; no manual override" documented in the class). No pre-computed parallel financial truth exists.

**Communication.** E.5: queueing requires ACTIVE consent covering subject + purpose + time, the purpose's own channel, a content reference; delivery/failure marking is terminal and provider-referenced; revocation blocks future queueing while history is retained; denials audited.

**Integrations.** Inbound webhook processing verifies the signature against the endpoint secret (`integrations.signature` on mismatch) and is **console-only by design** — no public inbound HTTP surface exists, so there is nothing unauthenticated to attack (the matrix's NOT-APPLICABLE determination stands: an operator ingests external events; inventing a public endpoint would add attack surface without a business scenario). Outbox dispatch is transactional, idempotent-deduped by (endpoint, idempotency key) with the original entry answering duplicates under `lockForUpdate`, payload-digest pinned, bounded attempts (5) + requeue accounting, endpoint-must-be-active, audited incl. denials.

## P4.7 — Final adversarial attack + production certification (this commit)

**Attack objective: find breakage.** Five fresh angles were attacked beyond the existing suites; two findings, both repaired and pinned:

**FINDING 1 (fixed) — money input at the HTTP boundary.** `'numeric' + 'gt:0'` is not a money format: `amount=0.001` passed validation, reached the DB, was rounded to `0.00`, and rejected by `CHECK (amount > 0)` with a raw SQLSTATE 23514 — an **HTTP 500 where a 422 was owed**; `12.345` would have been **silently rounded** to `12.35`; `1e2` (scientific notation) and 13-integer-digit overflow (decimal(14,2) capacity) had the same class. Fix: one authoritative named rule `money` (AppServiceProvider — digits, optional 1–2 decimals, ≤12 integer digits) applied to all 14 money rulesets across the 5 controllers that feed 2-decimal NUMERIC columns (payments, obligations, refunds, journals, reconciliation expected/observed, committed amounts, salary rates, assessment scores). Pinned by `MoneyInputAdversarialTest` (7 tests, 41 assertions: 302 + session errors on the web boundary, 422 on the JSON API, zero rows written, a well-formed `12.34` still records).

**FINDING 2 (gap closed) — the audit trail had no direct-SQL attack test.** The append-only trigger (000008) existed but no permanent test attacked it directly, while the sibling tables (certificates, program versions, attendance facts, skills) all had tampering tests. Added to `SecurityHardeningFeatureTest`: UPDATE and DELETE on `audit_events` are rejected by the schema and the row stays intact (OK 8 tests, 37 assertions).

**Verified clean (no defect):** financial state machines re-check period state under row locks (payment, refund) with cross-module close invariants (overlapping open payroll periods block a financial period close); payment allocation enforces payer match, unique payment–obligation pair, and both remainder ceilings; the outbox is transactional with idempotent dedupe under lock and digest pinning; inbound webhooks verify signatures and expose no public surface; reporting recomputes from authoritative tables (no parallel truth); the privacy export is minimum disclosure by column whitelist.

### Production certification (evidence only, final commit)

| Metric | Value | Evidence |
|---|---|---|
| Modules | 16 | `app/Modules/` |
| Commands | 78 | `app/Modules/*/Commands/` (all idempotent, all audited incl. denials; the 78th's ledger is its own evidence) |
| Queries | 11 | `app/Modules/*/Queries/` (read models / calculators, recompute from authoritative tables) |
| Routes | 196 (38 GET / 158 POST) | `artisan route:list` at this commit |
| Migrations | 118 | `database/migrations/` — unchanged since the P4.4 executed deploy (118/118 fresh, /health 200) |
| Schema guards | 96 CREATE TRIGGER statements; 161 CHECK constraint statements | counted from migrations at this commit |
| Feature workflows | 41 matrix checkpoints — 0 missing / 0 partial / 0 blocked / 0 duplicates | PHASE_3 certification (module × capability × workflow matrix) |
| Tests / assertions | 505 / 3453 | full suite, this commit, 14:44 run |
| Test files / methods | 66 / 435 (505 executions incl. data-provider rows) | `tests/Feature/` |
| Adversarial suites | SecurityHardening 8 (headers, throttle, recaller, health, **direct-SQL audit tampering**) · ConcurrencyRace 1 method × 5 stable runs (SQLSTATE 23514) · PrivacyAdversarial 5 · Printing 5 · MoneyInputAdversarial 7 · ConsoleSmoke 2 (all pages authorized / all pages guest-blocked) | named test classes |
| Static analysis | phpstan level 6 — 0 errors; pint — PASS (490 files) | this commit |
| Fresh build | 118/118 migrations + /health 200 in the P4.4 sandboxed deploy; zero migrations changed since | docs/operations/production-deployment.md |
| Schema invariants | TEMPLATE drill 104/104 tables, 10/10 checksums | P4.2 |
| Backup / restore | backup + restore drill 104/104, 10/10 checksums | P4.2; residual: file-based restore loop is the mandatory host drill (documented) |
| Deployment | deploy.sh pre-deploy backup gate + APP_KEY validation; sandboxed deploy executed end-to-end, atomic switch, rollback proven | P4.4 |
| Remote SHA | this commit, pushed to `arena/01a03d22-toefl-house`, `git ls-remote` verified equal, working tree clean | commit metadata |

**Certification statement:** at this commit, every gate above is green from re-executed evidence, the adversarial attack found every defect it found (both repaired, not documented), and no capability remains missing, partial, or duplicated. The system is certified production-ready on this record.

---

## HISTORICAL SOURCE: `implementation/44-e2e-business-journey.md`

# PHASE_5 — E2E Business Journey (real HTTP, fresh isolated DB)

**Method.** The complete prospective-student journey was executed against a live
HTTP server (`php -S`, `APP_ENV=local`, CSRF enforced, database sessions, a fresh
`toefl_house_e2e` database created empty, migrated, and first-run bootstrapped),
driving `e2e-journey.php` — a same-origin browser-like client with cookie jar and
CSRF token. **No mocks**: every state change is a real HTTP request against the
authoritative routes/controllers/commands; final truth is read back over HTTP and
directly from PostgreSQL.

Journey: fresh DB → first-owner bootstrap → owner sign-in → staff provisioning
(person intake → verify → account → password → position assignment → activation) →
student person intake/verify → applicant registration → **three-signature
admission** (initiate → review → approve, distinct sessions) → admitted-applicant
conversion to an active student → open financial period → placement-fee
obligation (invoice) → payment record → payment allocation → academic
program/version/period → class define → teacher assignment → publish → activate →
enrollment seat request → enrollment approval (active seat) → placement assessment
attempt → score → **moderate → approve → release** (independent actors) → final
financial + student-state verification.

Every Separation-of-Duties signature was made in its own authenticated session;
the boundary was attacked at each stage (initiator self-review, reviewer
self-approval, scorer self-moderation, clerk activation, teacherless class
activation, default-deny for an account with no authority).

## Findings (DISCOVER → PROVE → FIX → ATTACK → VERIFY)

### F-A — Org-wide grant requestor could self-approve the first signature (SoD defect)

- **DISCOVER.** `GrantScopePermission::approve()` (the staged organization-wide
  grant chain) rejected only when approver one == approver two; it never checked
  that an approver differs from the **requestor**. Every other staged workflow
  binds the requester/initiator off the signing slots (refund
  `finance.refund_not_independent`, admission `admissions.single_actor`,
  correction `academic.correction_single_actor`). The DB guard in `000116` likewise
  only compared the two approvers.
- **PROVE (real HTTP).** On a fresh system the owner requested an org-wide grant
  then POSTed approval in the same session: `approver_one_id == requested_by`,
  state stayed `requested` — the self-signature was accepted.
- **FIX.** `GrantScopePermission::approve()` now denies with
  `access.org_wide_single_actor` when `requested_by == approver` before either slot
  is filled. Migration `000119_org_wide_grant_requestor_independence` recreates the
  `org_wide_grant_requests_guard` trigger to reject, at the SQL level, any requestor
  value in either approver slot (application rule backstopped in the database,
  matching the architecture's trigger-guard pattern).
- **ATTACK.** Direct-SQL attack against the fresh-schema trigger: setting
  `approver_one = requested_by` and (with a distinct approver one)
  `approver_two = requested_by` are both **rejected** (`check_violation`); a
  legitimate chain of two distinct non-requestor approvers is **accepted** through
  to `approved`.
- **VERIFY.** New permanent regression test
  `GrantCommandFeatureTest::test_the_org_wide_grant_requestor_may_not_approve_their_own_request`
  (self-approval denied, request untouched, distinct approver still succeeds).
  Re-probed over real HTTP after the fix: self-approval leaves `approver_one` NULL.
  Full Access suite green (38 tests).

### F-B — Domain rejection on a Referer-less POST ejected authenticated users to /login

- **DISCOVER.** The console exception handler maps a `DomainError`
  (`AuthorizationDenied` 403 / `BusinessRejection` 409) for a non-JSON request to
  `redirect()->back()`. With no `Referer` header (a programmatic / same-origin
  API-style client — the console explicitly documents same-origin programmatic
  use), `UrlGenerator::previous()` falls back to the "previous URL" stored in the
  session, which for a freshly signed-in employee is the **login** route. The
  governed rejection (and its flash `error_code`) was lost and an authenticated
  user was bounced to the login screen.
- **PROVE (real HTTP).** An authenticated but unauthorized session POSTing a
  finance obligation with no Referer was redirected to `…/login`; with a Referer
  it correctly returned to `…/finance`. The JSON/API path was unaffected and
  already returned the structured 403 payload.
- **FIX.** `bootstrap/app.php` domain-error render for the web console: when no
  Referer is present, redirect an authenticated user to the console home (`/`) and
  an anonymous request to login, preserving `withInput()`, the `error_code` and the
  message; when a Referer is present the in-place `back()` behaviour is unchanged.
- **ATTACK/VERIFY.** New permanent regression tests
  `DomainRejectionTransportFeatureTest` (no-Referer authorization denial →
  redirect `/`, flash `finance.payment_denied`, still authenticated; with-Referer →
  redirect back to `/finance`). Re-probed over real HTTP: no-Referer denial now
  returns to `/`, never `/login`.

### Carried from the prior session (transport surfaces), re-verified over real HTTP here

- **API session stack.** The `api` middleware group now runs the same stateful
  cookies+session+CSRF stack as the web group; previously the token-only default
  meant every `/api` call 401'd after a valid console login. Proven: console login
  then `GET /api/v1/me` → 200 `username=owner`. Regression: `ApiSessionStackFeatureTest`.
- **Person intake.** `RegisterPerson` command + `POST /identity/people` (+ API)
  open the unverified person record every boundary starts from. Regression:
  `ClassAndIntakeTransportFeatureTest` and journey STAGE 2/3.
- **Class delivery transport.** `defineClass` / class `transition` /
  `assignTeacher` HTTP surfaces; a class cannot activate without an open teacher
  assignment (`academic.class_needs_teacher`). Regression:
  `ClassAndIntakeTransportFeatureTest`; journey STAGE 7 + teacherless-class attack.

### Observed, deliberately NOT changed (certified architecture)

- **No automatic cross-module payment gate.** Finance and Academic are separate
  authorities; no code, test, or domain contract couples enrollment activation or
  placement assessment to obligation coverage (balances are derived and reconciled
  by Finance). The journey therefore exercises both authoritative surfaces and
  demonstrates the business control ordering (placement-fee obligation posted,
  paid, and fully allocated to uncovered = 0.00 before seat activation); adding an
  automatic cross-domain gate would change the certified boundary model and was not
  done.
- **Least-privilege role/structure granularity.** Roles/positions and organization
  structure units (campus/branch/department) are created only by first-run
  bootstrap / the organization commands, which have no console HTTP surface; the
  existing all-capability Owner position CAN be assigned to provision a working
  employee over HTTP (proven — the journey staff are provisioned this way), so this
  is a granularity/console-completeness observation, not a journey blocker, and
  introducing new role/structure transport is a product change outside the
  "do not change certified architecture" constraint.

## Gate evidence (this run)

- **Unit:** 70 tests / 499 assertions — OK.
- **Feature:** 453 tests / 3128 assertions — OK (incl. all PHASE_3/PHASE_4,
  deployment/one-click-Windows contract tests, and the new regression tests).
- **PHPStan:** level 6, no errors.
- **E2E journey (fresh DB, real HTTP):** 61/61 checks pass, 0 findings at the end
  state; student active, enrollment active, placement result released (87.50),
  placement-fee invoice 100.00 paid and fully allocated (uncovered 0.00).

---

## HISTORICAL SOURCE: `implementation/WP-1-capability-gap-matrix.md`

> **Historical-status notice (2026-09-07):** This WP-1 matrix is a pre-convergence capability snapshot. Its migration/test counts and earlier `MISSING` claims must not be read as current implementation state. Later convergence records supersede it where they conflict; see `docs/architecture/current-state-compliance.md` for the current documentation-to-implementation assessment.

# WP-1 — ERP Capability Gap Matrix

**Status:** Living WP-1 capability/discovery inventory. This update does **not** change production code; it reconciles the matrix with what is already implemented and green (WP-2 F1/F2/F3 foundations + F4 Calendar Authority).
**Branch:** `arena/01a0677c-toefl-house` @ F1-schema work on `f6dc968`+ (verified in tree: PHPUnit **632/4381** green, PHPStan L6 0, Pint 531 files clean; migrations 125; WP-2 F1/F2/F3 foundation tests expanded & green; F4-C verification record).
**Authority order applied:** frozen architecture / domain contracts > ADR / business invariant > DB invariant > canonical production implementation > test expectation > legacy behavior. Legacy (the Express/TS/SQLite React system described in `foundation/01-legacy-system-intelligence-report.md`) is **reference-only** and was used only to identify *lost business capability*, never as authority over the new architecture.

## Status legend

| Status | Meaning |
|---|---|
| **COMPLETE** | Authoritative backend behavior + invariants + persistence + authorization + meaningful tests support the intended workflow end to end. |
| **PARTIAL** | Core is implemented and certified, but one or more named sub-capabilities of the blueprint area are absent. |
| **MISSING** | No module/command/model/route implements the blueprint capability. |
| **CONTRADICTORY** | Implemented behavior conflicts with the frozen architecture / an ADR / a decided invariant. |
| **BLOCKED** | Cannot proceed to a correct state for an external reason (no source data / decision not ratified / architecture decision required). |
| **NEEDS VERIFICATION** | Surface exists but the authoritative contract is not yet proven (no decision, no guard, no test). |

## Method (evidence-derived)

Status was derived by cross-reading: module command/model/query inventories (`app/Modules/*`), the HTTP surface (`routes/web.php` + controllers), schema (`database/migrations`, 125 files), the frozen architecture (`docs/architecture/*` incl. `24-architecture-invariant-registry`), foundation contracts (`docs/foundation/*` incl. `39-domain-contracts`, `34-financial-domain-model`, the decision ledger `04`, requirements registry `08`), the WP-2 approved architecture decisions (`docs/architecture/decisions/WP2-approved-decisions.md`), and the certified package checkpoints (`docs/implementation/24…44`, `WP-2-F4C-*`). Absence of an entity/term was verified by full-tree grep (`app/`, `database/migrations`, `tests/`, `docs/`).

**Context on the blueprint:** the delivered and certified architecture (`00-implementation-state`: Packages 02–17) scopes the ERP to school *operations* — identity, organization, RBAC/access, admissions/enrollment, academic delivery & decisions, HR/payroll, finance core/payments/funding, documents/privacy, resources/books/facilities, reporting, integrations, communication. It is the 90-capability *employee* surface; its own matrix (`41-phase-3-employee-coverage-matrix-checkpoint.md`) is closed (46 COMPLETE / 14 N/A / 0 MISSING) **for that employee surface only**. WP-1 audits the *broader ERP blueprint* (areas A–V), which intentionally extends beyond the certified employee surface into full multi-branch ERP semantics. Consequently several blueprint areas are **PARTIAL** or **MISSING** relative to the ERP blueprint even though the underlying module is certified — a suite-green ERP is **not** claimed.

## A–V Capability matrix

| # | Capability | Current implementation location | Status | Authoritative contract | Missing / defective behavior | Dependencies | Risk / priority | Recommended work package |
|---|---|---|---|---|---|---|---|---|
| A | Organization / Campus / Branch / Department | `Organization` module: `Organization,Campus,CampusAssignment,Branch,Department`; `CreateStructureUnit`,`RenameStructureUnit`,`TransferBranchToCampus`,`TransitionStructureUnit`; `EffectiveStructureQuery`; console read-view. Structure SoD in `Support/Authorization/StructureDecision`. **F1:** `000121`+`000125` add `originating_branch_id` (immutable, on students/enrollments/obligations/certificates/payments/refunds/fund_allocations/contracts), `current_home_branch_id` designation on all of those, `people.home_branch_id`, and `branch_scope_links` with lifecycle/window invariants; `BranchProvenanceFoundationTest` green. | **PARTIAL** | Decided REQ-ORG-001/002/005/008/009/010/011; D-F-010…D-F-019, D-F-014 (post-transfer **historical campus/branch/date/attribution immutable**); WP2-DEC-01. | Structure **lifecycle COMPLETE**. **F1 schema/composable layer COMPLETE** (immutable provenance on all branch-originating records, real-FK scope-link junction with open-window invariants, home-branch designation). **Still missing:** **no domain command path** assigning/declaring provenance/home-branch or opening/closing `branch_scope_links` (tests still drive the schema directly); **no Access consumption** of `branch_scope_links` (cross-branch affected-scope propagation not yet real in authorization); existing create commands (payment/refund/fund/contract/enrollment) do not yet populate provenance from a branch context (records stay unassigned rather than fabricated); D-F-014 is schema-realizable but not yet wired into the workflow. | F1 (command/Access), S calendar/scope. | **High** — foundational; blocks per-branch finance/reporting and D-F-014. | **WP-2 (F1 command + Access wiring)** |
| B | Dynamic RBAC / Positions / Authority lifecycle | `Access` module (7 commands): `Role,Position,AccessPolicy,PositionAssignment,ScopeGrant,Delegation,OrgWideGrantRequest`; `AccessResolution`; console + staged two-approver org-wide grants (`000116`). | **COMPLETE** | `07-authorization-and-scope-architecture`; D-F-031…D-F-083; `45-authorization-and-scope-architecture-contract`; G3-D-004. | None for the core engine: default-deny, position+assignment+permission+scope, temporary/cross-branch dated access, delegations, self-grant forbidden, sensitive two-Owner. Residual **OPEN governance** (not code): configurable approval thresholds & department/expense limits (D-F-007/020/022, REQ-ORG-007/013/015) and the D-F-083 annual review automation. | — | **Low** (engine complete). | **WP-2 (S1 config/audit)**
| C | Identity / Users / Students / Staff / Teachers | `Identity` module (`Person,UserAccount`; register/verify/link/password/deactivate `000098/99/106`); `Students` (`Student,StudentStatus,GuardianRelationship`); staff/teachers = `Person`+`Employment`/`Contract` (HR). | **COMPLETE** | `identity/person` contracts; HR employment & contract contracts; identity/admissions/student guards `000106/107`. | Core lifecycle complete and certified. Self-service identity (registration/reset by subject) belongs to portal area P. | P (portals). | **Low–Med**. | retained in hardening
| D | Visitor / Lead / CRM / Conversion | *None* (0 models/commands/routes; no `CRM/Lead/Visitor/Keyword` anywhere). | **MISSING** | No ratified requirement; legacy reference-only. Candidates noted in `01-legacy §3`. | Full pipeline absent: visitor/lead capture → follow-up → qualification → applicant conversion. | Admissions (applicant intake) exists as the downstream sink. | **Med** (revenue pipeline). | **WP-6 (CRM)**
| E | Programs / Program Versions / Levels / CEFR | `Academic`: `Program,ProgramVersion` via `MaintainAcademicStructure` (define/publish — versions immutable). **F2 foundation:** `000122` + `ProgramVersionLevel` + `MaintainAcademicStructure::defineLevel` (ordered, unique per version, optional CEFR, cross-version class-level trigger) + `ProgramVersionLevelFoundationTest` green. | **PARTIAL** | `10-academic-architecture`; G2-D-003 (no auto-progression without a program rule); WP2-DEC-02. | Program + immutable version **COMPLETE**. **ProgramVersionLevel authority and CEFR COMPLETE** (domain command + schema cross-version integrity + class level FK). **Still missing:** level-aware **progression/placement/fee packaging** consumers (G2-D-003 / WP-3), so levels are not yet used by admission, grading/progression, or finance. | F (offerings/availability), J (progression), G. | **High** — progression & packaging depend on levels. | **WP-2 (F2) + WP-3 consumers (G/J)** |
| F | Terms / Branch Availability / Offerings | `AcademicPeriod` (define/transition) in `Academic`; period is authority-owner of academic timing. **F3 foundation:** `000123` + `BranchAvailability`/`Offering` + `MaintainAcademicStructure::{declareBranchAvailability,openOffering}` (co-dependent triple, schema trigger, unique triple, open-term/active-level required) + `OfferingAvailabilityFoundationTest` green. | **PARTIAL** | `10-academic-architecture` (period resolution from owning registry); WP2-DEC-03. | **Term/academic-period COMPLETE.** **BranchAvailability + Term + Offering co-dependency COMPLETE** (domain + schema). **Still missing:** enrollment is **not yet required to target an `Offering`** (`enrollments.offering_id` is nullable and `EnrollAdmittedApplicant` still targets a class directly — WP-3); availability/offering **lifecycle** (close/cancel/capacity changes) is not modeled; no branch×term availability query/UI surface. | F1 (branch provenance), E (levels), calendar (S). | **High** — offerings are the natural registration/financial packaging unit. | **WP-2 (F3) + WP-3 enroll-to-offering/quote** |
| G | Placement Examination / Recommendation | `Academic/Placement`: catalog/test/version/question/rubric (`MaintainPlacementCatalog`), profile/attempt/evidence (`ManagePlacementProfile`), scoring/moderation/approval (`ScorePlacement`), explainable recommendation (`RecommendPlacement`), staged review/approve/release/supersede (`DecidePlacement`), signed eligibility snapshot (`000133` + `AcademicEligibilitySnapshot(Builder|Query)`, `AcademicEligibilitySigner`, `CanonicalJson`). | **COMPLETE** | `wp-academic-eligibility-snapshot` (ADR-016); `10-academic-architecture`; G2-D-003 progression rule. | Placement **examination workflow → recommendation → class/offering assignment** and the **signed academic-context snapshot** (immutable, versioned, HMAC-verified evidence a placement/level/class decision was based on) are implemented and green (`AcademicEligibilitySnapshotFeatureTest`). Class/offering references are included when an active/open target exists; otherwise the snapshot still pins the authoritative level/CEFR for downstream admissions/enrollment/finance. | E (levels), F (offerings), I (sections), R (snapshots). | **High** — evidence-based admission/leveling. | **WP-3 (AC1)** |
| H | Registration / Enrollment / Transfers / Freeze / Withdrawal | Admissions intake (`Applicant,AdmissionDecision`, staged) → `EnrollAdmittedApplicant` → `Enrollment` (`MaintainEnrollment` request/activate/freeze/withdraw/complete/**transfer**); `TransitionStudentStatus` (suspend/withdraw/reactivate/complete/graduate). **AC3:** activation calls `FinancialGateQuery::assess`, freezes signed gate evidence on `enrollments`, refuses activation with `academic.enrollment.financial_gate` when unsatisfied, and records `academic.enrollment.financial_gate.denied`. | **PARTIAL** | Admissions & academic enrollment contracts; D-G3-002; ADR-017 (`wp-enrollment-financial-gate.md`). | Registration→admission→enrollment/transfer + suspend/withdraw/reactivate **COMPLETE**; **enrollment financial gate** (payment/discount/funding/credit/installment/approved-exception preconditions at activation) **COMPLETE**. **Still missing:** cross-branch/class transfer **provenance** (records remain unassigned from a branch context) and the freeze-with-financial-implications surface. | A (branch), F (offerings), K/L (finance gates), S (dates). | **High** — financial/academic continuity. | **WP-3 (AC3)** |
| I | Classes / Sections / Sessions / Rooms / Scheduling | `ClassModel,ClassSession,TeacherAssignment(-Skill)` via `MaintainClass`; attendance sessions; `ClassRosterQuery`. | **PARTIAL** | `10-academic-architecture`; session scheduling + skill rules. | Classes, sessions, teacher assignment, roster **COMPLETE**. **Missing:** **sections** within a class, **rooms** (0 refs), and a **timetable/scheduling** engine (current "scheduling" = one-off class sessions, not a room/timeslot timetable). | F (offerings), G (placement→class), R. | **Med–High**. | **WP-3 (AC2)**
| J | Academic attendance / grades / progression / completion | `RecordAttendance`; `ManageAssessmentResult` chain (attempt/score/moderate/approve/release + staged correction); `DecideProgression` (staged); `DecideGraduation` (staged) + `Certificate`; `ManageAcademicAppeal`. | **COMPLETE** | `10-academic-architecture`; G3-D-002 (evidence ≠ decision); payroll-evidence guards `000105`. | Full evidence→decision chain certified over HTTP with SoD and one-shot certificates. Official *transcripts/gradesheets as reporting output* belongs to R. | E (levels), R. | **Low** for chain; levels expand scope. | retained / **WP-5 (R)**
| K | Student financial obligations / invoices / payments / refunds / credits | `Finance`: `Obligation(+Line),Payment(+Allocation),Refund` via `PostObligation,RecordPayment,AllocatePayment,RefundPayment`; invoice = read-only print of an obligation (`PrintingController::invoice`). | **PARTIAL** | `34-financial-domain-model`; `06-financial-architecture`; D-G3-001 (payment/refund/discount/adjustment/reversal/journal distinct). | Obligation→payment→allocation→refund core **COMPLETE** & schema-guarded (`000101`). **Missing:** a distinct **invoice/billing-statement** lifecycle (invoice is only a print projection; no invoice entity/state/dues statement); **credits/advances** on account (0 refs); the `FinancialEventType` vs `ChargePurpose` fiscal-type taxonomy is not modeled (obligation purpose is a flat label). | A (branch), L (aid offsets), M (treasury). | **High** — billing & statements. | **WP-4 (FIN2)**
| L | Scholarships / waivers / sponsorship / donor-funded cases | `FundingSource,FundAllocation` (`AllocateFunds`, restricted pools, restriction/pool/line remainders, utilization); `Discount` (`MaintainDiscount`). **AC3:** `MaintainFinancialCredit`, `MaintainInstallmentPlan`, `MaintainFinancialGateException` (propose→approve SoD, immutable after DB trigger), `FinancialGateQuery`, `FinancialGateEvidence`, `financial_gate_*` evidence on `enrollments`. | **PARTIAL** | G2-D-005 (restricted-fund hold); `06-financial-architecture`; D-F-097 OPEN (agreement-specific scholarship/funding rules); ADR-017. | Generic **restricted-fund engine + discount lifecycle COMPLETE**; **enrollment financial gate application of scholarship/waiver/credit/sponsorship at activation COMPLETE** (approved credit, approved installment, approved exception all evidence-backed). **Still missing:** **scholarship / waiver / sponsorship / donor-funded case model** — per-case aid decisions, per-donor attribution, agreement-specific rules (D-F-097). | E (levels), F (offerings), K (obligation offset). | **High** — student-aid packaging. | **WP-4 (FIN4)** |
| M | Treasury / cash / bank / expenses / budgets / P&L / reconciliation | `Finance`: chart + journals + reversals (`MaintainChartOfAccounts,PostJournal`), `Reconciliation`, opening financial state (P15), `RecordReconciliation`. Reporting metrics are a closed 5-metric catalog. | **PARTIAL** | `06-financial-architecture`; ledger/reconciliation guards `000102`; G3-D-001. | Ledger, journals, reconciliation, opening-state materialization **COMPLETE**. **Missing:** **expenses** workflow & branch-expense approval (REQ-FIN-001/002, D-F-021…D-F-028; 0 `Expense` artifacts — expenses only flow as generic obligations/journals); **operational budgets vs actual** (0 `Budget`); **P&L / revenue-expense reporting** (Reporting catalog has no revenue/expense/P&L metric by design); **bank/cash-account treasury** reconciliation beyond the general journal. | A (branch), K (obligations), R (P&L report). | **High** — management accounting. | **WP-4 (FIN1, FIN3)**
| N | Books / inventory / sales / issuance | `Resources`/`Library`: `BookCopy,BookIssuance` via `CirculateBooks` (issue/return/loss). | **PARTIAL** | P12 checkpoint (lending certified). D-F-097 inventory UNKNOWN. | **Lending + one-open-issuance-per-copy COMPLETE.** **Missing:** book **inventory/stock & acquisition** (0 refs) and **sales** of books/merchandise (no sales ledger; sales would need to flow through Finance). | K (sales), M (revenue). | **Low–Med** (library vs retail scope open). | **WP-5 (N/retail)** (decision first)
| O | Payroll / teacher compensation | `Hr` + `Payroll`: employment/contract/leave/scale; `TeachingDeliveryFact`; payroll period/calculation/approval/clearance/settlement; guards `000103/105`. | **COMPLETE** | P09/P16; `36-hr-payroll-domain-model`; G2-D-004 (held not invented). | End-to-end compensation (scale→rule→delivery→calc→approve→settle) certified & schema-guarded. | O scales w/ leave/attendance evidence only. | **Low**. | retained
| P | Student portal / teacher portal / staff workflows | Employee web console + JSON API (P17) covers **staff/teacher workflows** over every capability. No student- or parent-facing surface. | **PARTIAL** | P17; `41-phase-3` (employee surface complete). | **Employee (incl. teacher) console COMPLETE.** **Missing:** **student portal** (self-service: results, obligations/pay, certificates, attendance, requests) and **parent/guardian portal** (0 refs). | C (identity), B (access scopes), Q (notify). | **Med** (self-service). | **WP-5 (portal)**
| Q | Notifications / inbox / reminders | `Communication`/`SendMessage` (queue + markDelivered/markFailed) under **active consent** (P12/P17). | **PARTIAL** | Privacy-consent contract; `privacy` consent lifecycle. | Consent-gated **outbound messaging engine COMPLETE** (channel/purpose/content checks, delivery evidence, revocation). **Missing:** **inbox** (read/mark/unread), **reminders/scheduled notifications**, **per-user channel preferences**, template management. (Notification decisions remain legacy/unratified per decision ledger.) | P (portal), B (scopes). | **Med**. | **WP-5 (notify)**
| R | Reports / dashboards / exports / print snapshots | `Reporting` module (closed 5-metric catalog + dashboards + reconciliation); `PrintingController` print views (receipt/invoice/certificate/payroll/enrollment/id-card). | **PARTIAL** | P13; `12-reporting-and-derived-data`; `48-reporting…contract` (derived-only). | Metric catalog, dashboards, report runs, reconciliation **COMPLETE** (deliberately closed at 5 metrics). Print **views** exist. **Missing:** broader operational/financial/academic report catalog (e.g. P&L, roster/attendance, obligation aging); **immutable report/print snapshots** captured at time (current prints are live-derived projections — the "immutable snapshot" concern is unresolved); export-to-file (report runs, not subject-data exports). | M (P&L source), I/J (academic), S (periods). | **Med–High**. | **WP-5 (R/snapshots)**
| S | Shamsi / Gregorian calendar semantics | `app/Modules/Calendar` (CalendarAuthority, version-1 series via `Version1Series`), `tests/Unit/Calendar/CalendarAuthorityTest.php`, F4-C verification record. | **COMPLETE** | Ratified D1/D2/D3/D4 in `WP2-approved-decisions.md`; F4-B design; F4-C verification `docs/implementation/WP-2-F4C-calendar-authority-implementation-verification.md`. | Entire business-calendar layer now implemented and certified: Kabul civil reference (AFT UTC+04:30), ratified version-1 series SH 1399–1416, active operational window SH 1399–1415 fully served, supported range SH 1336–1425 fail-closed, Gregorian storage with Kabul civil-day derivation, and F4-A.3 conformance/tests. | J/K/M/R period authority. | **High** (business-date correctness) — **now resolved**. | **WP-2 (F4)** — implemented & `F4-C VERIFIED` |
| T | Search / audit logs / observability | `Audit` module (`AuditEvent`, append-only, denial audit); health/readiness probe; standard logging. | **PARTIAL** | `13-audit-and-history`; `19-observability`; P17 security headers/health. | **Audit log + observability baseline COMPLETE** (every governed op incl. denials audited). **Missing:** **free-text / cross-entity Search** (0 refs) over people/students/docs/obligations/messages; richer operational observability dashboards. | C/E/K entities. | **Low–Med**. | **WP-5 (search/obs)**
| U | QR / student cards / diploma / certification | `Certificate` (unique serial, immutable, print); `PrintingController` id-card + certificate print views. | **PARTIAL** | P07/P17; P12. | Certificates + printable **id-card/certificate** **COMPLETE**. **Missing:** **QR codes** (0 refs) on cards/certificates, **card issuance/verification** lifecycle, diploma sealing. | C (identity), R (snapshots). | **Low**. | **WP-5 (cards)**
| V | Data migration / legacy compatibility | Migration contract `15`, legacy disposition `16`, `22-legacy-migration-boundary` (all **plans**). Legacy checkout/data **not present**; "conditional migration/cutover row untouched (no legacy database exists)". | **BLOCKED** | `15-migration-implementation-contract`; `22-legacy-migration-boundary`. | No importers/staging/cutover exist and none can be exercised: there is **no source legacy database** in the checkout to migrate from, and no ratified cutover. | All source models, calendar authority (S). | **High** but externally blocked. | **WP-7 (migration)** — unblocks only with source data + decision

## Architecture-sensitive facet matrix (from the audit brief)

| Concern | Present? | Finding |
|---|---|---|
| Student global identity vs branch relationship | Partial | `Person` is org-global; `people.home_branch_id` (F1 designation) now exists. Still no student-level/current-home designation on operational aggregates. (→ A/C, WP-2 F1 completion) |
| Historical branch provenance on records | Partial | **Schema layer done:** immutable `originating_branch_id` on all branch-originating records (students/enrollments/obligations/certificates/payments/refunds/fund_allocations/contracts) + `branch_scope_links`. **Still missing:** domain command path + create-command population; D-F-014 full-record fidelity in workflows. (WP-2 F1 command/Access wiring) |
| Financial branch vs current home branch | Partial | Person-level `people.home_branch_id` and `current_home_branch_id` on operational records now exist. **Still missing:** command/designation flow and Access consumption; not yet wired to per-branch finance. (WP-2 F1 command/Access wiring) |
| `FinancialEventType` vs `ChargePurpose` taxonomy | **Missing** | Obligations carry a flat purpose; no fiscal type taxonomy. (WP-4 FIN2) |
| obligation/payment/refund/**donation/expense** separation | Partial | obligation/payment/refund separate (D-G3-001) + restricted funds; **expense** and donation-as-a-distinct-kind absent. (WP-4 FIN1) |
| `ProgramVersionLevel` authority | Partial | Model + domain command + schema cross-version integrity implemented & tested. Level-aware progression/placement/fee packaging is WP-3. (WP-2 F2) |
| `BranchAvailability` + `Term` + `Offering` dependency | Partial | Co-dependent model + command + schema trigger implemented & tested. Enrollment→offering re-point and lifecycle/close/cancel are WP-3. (WP-2 F3) |
| Placement recommendation vs class assignment | **Implemented** | Recommendation pins level/CEFR and, when available, class/offering; snapshot publishes the full signed evidence. (WP-3 AC1) |
| Signed `AcademicEligibilityResult` / academic-context snapshot | **Implemented** | `academic_eligibility_snapshots` + canonical JSON + SHA-256 digest + HMAC-SHA256 server key; append-only versioned chain per person; release-time creation; Admissions/Student/Enrollment reference + Finance read lineage. (WP-3 AC1 / ADR-016) |
| Enrollment financial gates (scholarship/waiver/credit/sponsorship) | **Implemented** | `FinancialGateQuery::assess` derives uncovered from immutable Finance facts (payment/discount/restricted-fund allocations), applies approved credit/installment/exception, returns signed evidence (`FinancialGateEvidence`); `MaintainEnrollment::activate` freezes evidence and refuses activation when unsatisfied (`academic.enrollment.financial_gate`), leaving the seat `requested` and recording `academic.enrollment.financial_gate.denied`. (WP-3 AC3 / ADR-017 / WP-4 FIN4) |
| Cross-branch affected scopes & relational FKs | Partial | `branch_scope_links` now has real FKs, no-self-link, and one-open-per-owner; focused schema tests green. **Still missing:** authority/access behavior consuming the junction (branch-capable scope propagation end to end). (WP-2 F1 completion) |
| Transfer as historical new enrollment, not mutation | **Implemented** | `MaintainEnrollment::transfer` closes the previous enrollment as `transferred` and opens a new `requested` enrollment in the target class under the capacity invariant; the transferred row is never mutated, and the new row passes the financial gate at activation. (WP-3 AC3) |
| Immutable reporting / print snapshots | **Missing** | Prints are live-derived; no stored snapshot. (WP-5 R) |
| Calendar authority & reporting periods | **Resolved** | Calendar Authority implemented and `F4-C VERIFIED` (Kabul AFT, ratified version-1 series SH 1399–1416, active window SH 1399–1415 served, Gregorian storage with fail-closed tails). (WP-2 F4) |

## Counts (status distribution)

### Over the 22 primary A–V areas
- **COMPLETE: 6** — B, C, G, J, O, S (S resolved by the Calendar Authority / F4-C work)
- **PARTIAL: 14** — A, E, F, H, I, K, L, M, N, P, Q, R, T, U
- **MISSING: 1** — D
- **CONTRADICTORY: 0**
- **BLOCKED: 1** — V
- **NEEDS VERIFICATION: 0** (primary rows)
- **Total: 22**

### Architecture-sensitive facets (14 audit-brief concerns, secondary view)
- **Missing:** 2 · **Partial:** 7 · **Resolved:** 1 · **Implemented:** 4 · **Contradictory:** 0 · **Needs verification:** 0
- **Missing:** flat fiscal taxonomy (FIN2), immutable print snapshots.
- **Partial:** student/branch identity, historical provenance, financial-home vs current branch, expense/donation separation, `ProgramVersionLevel` authority, offerings dependency, cross-branch scope propagation.
- **Implemented:** placement recommendation vs class assignment, signed academic eligibility snapshot, enrollment financial gates (payment/discount/funding/credit/installment/approved exception), transfer-as-new-enrollment (WP-3 AC1/AC3).
- **Resolved:** Calendar authority & reporting periods (WP-2 F4, `F4-C VERIFIED`).
- **No genuine architectural *contradiction* requiring a change to frozen architecture was found.** The previously unstarted extensions (branch provenance, program levels, offerings, calendar) are now **approved** under `WP2-DEC-01…04`; F4 is fully implemented and F1/F2/F3 foundations are implemented and green. Remaining work under those ADRs is **implementation/consumer work** (domain command paths, operational-record coverage, WP-3/WP-4 consumers), not a new architecture decision. Truly decision-gated items remaining: CRM/lead (D), retail/inventory/sales scope (N), notification/inbox decisions (Q), and migration (V — externally blocked).

---

## HISTORICAL SOURCE: `implementation/WP-1-roadmap.md`

> **Historical-status notice (2026-09-07):** This roadmap records an earlier WP-1 sequencing state. Completed work and later target decisions have moved beyond parts of this roadmap. It remains useful as historical sequencing evidence only; current gaps and priorities are governed by `docs/architecture/current-state-compliance.md` and the accepted architecture records.

# WP-1 — ERP Implementation Roadmap

**Status:** WP-1 audit deliverable (documentation only — no production code modified)
**Basis:** `WP-1-capability-gap-matrix.md`. Companion to the matrix; groups the audit findings into coherent work packages in dependency order. **No implementation is authorized by this document.**

## Guiding rules carried forward

- Work packages are coherent domain bundles — not dozens of micro-tasks.
- Security/authorization and financial invariants are never weakened to ship a feature.
- Each bundle follows the established house cycle: TRACE → FIX → TEST → ATTACK (direct-SQL / denial) → REGRESSION → VERIFY.
- Anything that *extends the frozen architecture or data model* (multi-branch provenance, program levels, offerings, calendar authority) requires an explicit architecture decision before implementation — the existing modules and invariants are authoritative until a recorded decision.

---

## Dependency order (graph)

```
[ D0 Decision gates ]
   ├─ Calendar authority (S) ────────────────┐
   ├─ Governance thresholds/limits (B,M) ────┤   [ D0a ] need ratification
   └─ Legacy source data availability (V) ───┘
                       │
[ WP-2  Foundation model ]  F1 multi-branch provenance · F2 program levels/CEFR · F3 offerings/BranchAvailability (+ F4 calendar after D0)
                       │
        ┌──────────────┼─────────────────────────────┐
        ▼              ▼                             ▼
[ WP-3 Academic ]  [ WP-4 Financial ]          (WP-2 unblocks)
  AC1 placement+eligibility   FIN1 expenses/budgets
  AC2 sections/rooms/timetable  FIN2 invoices/credits/taxonomy
  AC3 transfers+financial gates  FIN3 treasury/P&L
                                FIN4 scholarship/waiver/sponsorship case
                       │
        ┌──────────────┴───────────────────────┐
        ▼                                     ▼
[ WP-5 Experience ]                       [ WP-7 Migration ]  (blocked → needs V source)
  P portal · Q inbox/reminders                legacy ingestion & cutover
  R reports/print snapshots                   (after new model is stable)
  T search/observability
  U QR/student cards
  D CRM/leads/conversion
  N retail/inventory decision
        │
        ▼
[ WP-6 Hardening ]  (perf @ volume, runbooks, residual open items from phase-4 assurance)
```

Key ordering facts:
- **WP-2 first.** Financial (branch finance, obligations, funding, P&L), academic (offerings, enrollment-to-offering, transfer provenance) and reporting (branch/P&L/period) all depend on multi-branch provenance, program levels, and the offering/term model. Extending them later would require data-model churn.
- **WP-3 before WP-4's academic-facing gates** (enrollment financial gates need the enrollment/offering/transfer model of WP-2/3 and the aid model of WP-4-FIN4 — they are joint, so AC3 and FIN4 are sequenced adjacently and gated together).
- **WP-7 last / external.** Migration is externally blocked (no source legacy DB in the checkout, no ratified cutover) and must run only after the new data model is stable.

---

## WP-2 — Foundation: model extension & architecture decisions
**Category:** foundational blockers (incl. security/authorization-adjacent configuration)

Dependencies in/out: prerequisite for almost everything; gated by **D0 decisions**.

- **F1 — Multi-branch operational model.** **SCHEMA LAYER DONE** (approved WP2-DEC-01; `000121`+`000125`): immutable `originating_branch_id` on all branch-originating records (students/enrollments/obligations/certificates/payments/refunds/fund_allocations/contracts), `current_home_branch_id` designation on each of those + `people.home_branch_id`, `branch_scope_links` with lifecycle/window invariants, schema guards, focused tests (`BranchProvenanceFoundationTest`). **REMAINING:** domain command paths for provenance/home-branch/scope-link lifecycle; populate provenance from branch context inside the existing create commands; cross-branch scope consumption by Access; per-branch finance/reporting consumers. Satisfies D-F-014 / REQ-ORG-011 and unblocks per-branch finance/reporting.
- **F2 — Program-version levels & CEFR + authority.** **FOUNDATION DONE** (approved WP2-DEC-02): `ProgramVersionLevel` model, ordered/unique/CEFR levels, cross-version class-level schema guard, `defineLevel` command, focused tests (`000122`). **REMAINING:** level-aware progression/placement/fee-packaging consumers (WP-3 G/J, WP-4).
- **F3 — Offerings & branch availability.** **FOUNDATION DONE** (approved WP2-DEC-03): `BranchAvailability`/`Offering` + `declareBranchAvailability`/`openOffering` with schema co-dependency, focused tests (`000123`). **Enrollment→offering re-point DONE** (`MaintainEnrollment` accepts `offering_id`, requires an open matching offering, and checks offering capacity at activation; `AcademicOfferingAndWaitlistFeatureTest`). **REMAINING:** availability/offering lifecycle (close/cancel/capacity); branch×term query/UI surface.
- **F4 — Calendar authority.** **IMPLEMENTED and `F4-C VERIFIED`.** Ratified D1/D2/D3/D4 in `WP2-approved-decisions.md`, implemented by `App\Modules\Calendar` (Kabul civil reference AFT UTC+04:30; ratified version-1 series SH 1399–1416; active operational window SH 1399–1415 fully served; supported range SH 1336–1425 fail-closed; Gregorian storage, derived SH semantics), and independently verified in `docs/implementation/WP-2-F4C-calendar-authority-implementation-verification.md`.
- **S1 — Governance configuration & review.** Materialize the OPEN decided governance detail as configurable, fail-closed approval thresholds and limits (D-F-007/020/022, REQ-ORG-007/013/015, REQ-FIN-001/002) on top of the complete Access engine, plus automated annual sensitive-access review (D-F-083).

Acceptance: fresh-migrate green, schema invariants for provenance FKs, branch-scope HTTP + direct-SQL attack tests, per-branch financial invariants guarded.

---

## WP-3 — Academic workflow blockers
**Category:** academic workflow blockers

Depends on WP-2 (F2 levels, F3 offerings, F1 branch). Groups AC1–AC3.

- **AC1 — Placement examination → recommendation → class assignment.** Placement exam workflow producing a **signed `AcademicEligibilityResult` / academic-context snapshot** (immutable evidence) and a recommendation, distinct from the authority that assigns the class (G capability).
- **AC2 — Sections / rooms / timetable scheduling.** Sections within a class, a `Room` catalog, and a room×timeslot timetable engine; keep the existing attendance/session evidence chain intact.
- **AC3 — Transfers, freezes, withdrawals with financial gates.** Model **transfer as a historical new enrollment (never a mutation)** with provenance; freeze/withdrawal with financial implications; and **enrollment financial gates** applying scholarship/waiver/credit/sponsorship (joint with FIN4). **DONE:** transfer-as-new-enrollment and the server-authoritative enrollment financial gate (`FinancialGateQuery` + `FinancialGateEvidence`; payment/discount/funding/credit/installment/approved exception; signed evidence frozen on `enrollments`; `academic.enrollment.financial_gate` denial + `academic.enrollment.financial_gate.denied` audit). **Remaining:** cross-branch/class transfer provenance (F1 command), freeze-with-financial implications, and the FIN4 per-case aid model.

Acceptance: HTTP + command-level SoD/lifecycle tests, evidence-snapshot immutability guards, transfer provenance tests.

---

## WP-4 — Financial blockers
**Category:** financial blockers

Depends on WP-2 (F1 branch, F2 levels, F3 offerings) and sequences adjacent to AC3.

- **FIN1 — Expenses & budgets.** Branch expense workflow with the ratified approval matrix (REQ-FIN-001/002, D-F-021…D-F-028 — Finance approver, GM substitute), an expense ledger, and operational budgets vs actual; distinct expense kind (D-G3-001 completion).
- **FIN2 — Invoices, credits, fiscal taxonomy.** Invoice/billing-statement lifecycle (invoice is currently only a print projection of an obligation), student credits/advances, and the `FinancialEventType` vs `ChargePurpose` taxonomy.
- **FIN3 — Treasury, bank/cash, P&L.** Cash/bank-account treasury reconciliation and the revenue/expense **P&L** and management-reporting sources (R depends on it).
- **FIN4 — Scholarship/waiver/sponsorship/donor case model.** On top of the restricted-fund + discount engine: per-case aid decisions, donor attribution, agreement-specific rules (D-F-097), and the enrollment financial gate (joint with AC3; G2-D-005 hold preserved).

Acceptance: every new monetary invariant schema-guarded (direct-SQL attack suite), closed-period immutability preserved, branch-aware.

---

## WP-5 — Experience, reporting & remaining ERP breadth
**Category:** UX/portal/reporting + missing legacy capabilities

Depends on WP-3/WP-4 for accurate source data.

- **P — Portals.** Student self-service (results, obligations/pay, certificates, attendance, requests) and parent/guardian view, on the Access scope model; teacher workflows already console-covered.
- **Q — Inbox / reminders / preferences.** Extend the consent-gated `Communication` engine with inbox semantics, scheduled reminders, and per-user channel preferences.
- **R — Reports & immutable print/report snapshots.** Broaden the closed catalog deliberately (P&L, roster/attendance, obligation aging) and implement **immutable snapshots** captured at run time (resolves the live-derived print concern).
- **T — Search & observability.** Cross-entity free-text search over people/students/documents/obligations/messages; richer operational dashboards.
- **U — QR / student cards / diplomas.** QR codes, card/certificate issuance & verification, diploma sealing.
- **D — Visitor / Lead / CRM / conversion pipeline**, converting into the existing Admissions intake.
- **N — Book inventory/stock & retail decision.** Decide retail scope (D-F-097 inventory UNKNOWN) then add stock/acquisition + sales routing through Finance, or formally out-of-scope.

Acceptance: each addition keeps domain authority, authz, audit; self-service is read/request only over the authoritative commands.

---

## WP-7 — Migration & legacy compatibility
**Category:** migration/data work — **externally blocked**

Depends on the entire new model being stable and on **a source legacy database and a ratified cutover** (neither exists in this checkout; the conditional cutover row is untouched). When unblocked: mapping registry from the legacy schema, idempotent importers, reconciliation, cutover with rollback, DR re-verified.

---

## WP-6 — Production hardening
**Category:** final production-hardening work

Runs last / continuously: performance under realistic data volumes (the current suite is green but small; per-branch and reporting indexes), operational runbooks for the new bundles, monitoring for the added modules, and closure of residual open items carried out of `43-phase-4-production-assurance`.

---

## Top 10 blockers (ranked)

| # | Blocker | Area | Effect | Unblocked by |
|---|---|---|---|---|
| 1 | Multi-branch operational provenance & financial-home-branch absent | A | Blocks per-branch finance/reporting; D-F-014 unrealizable | WP-2 F1 (architecture decision) |
| 2 | Program-version levels / CEFR absent | E | Blocks level-based progression/packaging/placement | WP-2 F2 |
| 3 | Offerings / BranchAvailability absent | F | Blocks registration-to-offering & branch availability | WP-2 F3 |
| 4 | Placement + signed eligibility snapshot absent | G | No evidence-based placement/class assignment | WP-3 AC1 |
| 5 | Transfer provenance / per-case aid model / freeze-financial implications | H/L | Transfer & the financial gate are implemented; aid case model, branch provenance, freeze implications remain | WP-3 AC3 + WP-4 FIN4 |
| 6 | Expenses & budgets absent | M | Branch expense approval & P&L blocked | WP-4 FIN1 |
| 7 | Invoicing/credits & fiscal-type taxonomy absent | K | No billing statements/credits | WP-4 FIN2 |
| 8 | Treasury/bank & P&L reporting absent | M/R | No management accounting | WP-4 FIN3 |
| 9 | Calendar authority (Shamsi) undecided/absent | S | Business-date & period correctness; affects F/J/K/M/R | **RESOLVED** — WP-2 F4 implemented & `F4-C VERIFIED` |
| 10 | Legacy data migration externally blocked | V | No data migration/cutover | source DB + decision (WP-7) |

---

## Recommended next WP

**WP-2 — Foundation: model extension & architecture decisions** (multi-branch provenance F1, program-version levels/CEFR F2, offerings/branch availability F3, calendar authority F4) plus the governance-configuration bundle S1.

Rationale: nearly every financial (WP-4), academic (WP-3) and reporting (R) gap is downstream of these model extensions, and F1/F2/F3/F4 modify the **frozen data model**, so they must be the first thing ratified and sequenced — they are the foundation everything else stands on. WP-2 **cannot start** until the architecture decisions it implies are authorized (WP-1 rule 6: do not modify frozen architecture without a recorded decision). The recommended next action is therefore an **architecture-decision request for F1/F2/F3/F4**, not code.

## Final state summary

- Files created: `docs/implementation/WP-1-capability-gap-matrix.md`, `docs/implementation/WP-1-roadmap.md`.
- No production code modified; working tree contains only these two new documentation files.
- Status counts (A–V primary): COMPLETE 4 · PARTIAL 14 · MISSING 3 · CONTRADICTORY 0 · BLOCKED 1 · NEEDS VERIFICATION 0. Facet view: MISSING 11 · PARTIAL 2 · NEEDS VERIFICATION 1 · CONTRADICTORY 0.
- No genuine architectural *contradiction* requiring a change to the frozen architecture was discovered; extensions requiring decisions are flagged, not self-authorized.
- The ERP is **not** claimed complete despite a green suite: green proves the certified employee surface, not ERP breadth.

---

## HISTORICAL SOURCE: `implementation/WP-1.5-architecture-decision-package.md`

# WP-1.5 — Architecture Decision Package (for WP-2 Foundation)

**Status:** Decision package only — **no schema, migration, model, service, route, test, or production-code change is authorized or made by this document.**
**Basis:** `WP-1-capability-gap-matrix.md` (A–V) and the frozen architecture (`docs/architecture/*`, `docs/foundation/*` decision ledger & domain contracts).
**Authority rule:** the frozen architecture and decided invariants remain authoritative until a recorded owner approval. Extensions proposed below are decision requests, not accepted state.

Each decision states the exact problem, the current frozen rule, the gap, the minimum extension, entity/relationship shape, source of truth, historical behavior (esp. unknown provenance), cross-branch/scope semantics, DB/domain invariants, how existing modules consume it, migration/backfill risk, affected tables/modules, downstream dependencies, and ≥1 rejected alternative.

Decision IDs: `WP2-DEC-01` (F1) · `WP2-DEC-02` (F2) · `WP2-DEC-03` (F3) · `WP2-DEC-04` (F4) · `WP2-DEC-05` (S1).

---

## WP2-DEC-01 — F1: Multi-branch operational provenance / financial branch semantics

### 1. Exact problem
Operational records that define academic and financial reality (enrollments, obligations, payments/allocations/refunds, funding/allocation, contracts, certificates, periods) are **org-scoped and branch-agnostic**: only the *structure* tables carry `branch_id`/`campus_id`. When a branch or campus transfers (already modeled at structure level by `campus_assignments` with `transfer_correlation_id`, decided D-F-014/REQ-ORG-011), the *operational* records cannot honor "current operations resolve to the new campus, but every historical record keeps its original campus, branch, date, attribution" because no operational record knows its originating branch — and there is no **financial/operational branch** distinct from the student's **current home branch**.

### 2. Currently frozen architecture rule
- Organization → Campus → Branch (→ Department/Operational Unit); a branch's campus membership is temporal and history-preserving via `campus_assignments` (structure-level provenance only).
- D-F-014 / REQ-ORG-011: after a transfer, historical **campus, branch, date, and attribution** are immutable.
- Operational modules are single-organization authorities; **scope** is enforced through `Access` (position/role/permission/scope grants), not through branch columns on operational data.

### 3. Missing / insufficient
- No provenance attributes (originating `branch_id`, plus current `home_branch_id`) on operational rows.
- No explicit **financial/operational branch** distinct from the current home branch.
- `scope_grants.scope_type` + untyped `scope_id` is the only branch-awareness; there is no typed junction expressing "this operation affects campus X and branch Y" (violates the "relational junctions with real FKs" concern).

### 4. Proposed minimum architectural extension
Introduce **provenance + designation as attributes and typed junctions**, without moving any financial/academic truth into a second ledger:
- Every branch-originating operational aggregate gains an immutable **`originating_branch_id`** (set at creation) and a mutable **`current_home_branch_id`** (the branch a student/record currently belongs to operationally) — both FK to `branches`.
- A person/student's **home branch** is an identity-level designation; the **financial/operational branch** of a given financial fact is captured on that fact (originating) and, where a student's operational branch differs from the financial branch of a transaction, the transaction's own originating branch governs — **home branch is never promoted to financial truth**.
- **`branch_scope_links`** typed junction: `(campus_id?, branch_id?, effective window)` with **real FKs** for cross-branch "affected scope" propagation used by authorization/audit/reporting, never as a duplicate data store.

### 5. Entities/tables (conceptual)
- Add `originating_branch_id`, `current_home_branch_id` (nullable initially — see §7) to: `enrollments`, `obligations`, `payments`, `refunds`, `payment_allocations`, `fund_allocations`, `funding_sources` (at creation), `contracts`/`contract_versions`, `certificates`, `academic_periods` (as hosted-by), `classes`.
- New junction `branch_scope_links(id, campus_id FK, branch_id FK, scope_owner_type, scope_owner_id FK, effective_from, effective_to, lifecycle_state, created_by, created_at)`.
- Identity: `people.home_branch_id` FK nullable → a *designation*, not financial truth.

### 6. Authoritative source of truth
`branches` / `campus_assignments` remain the source of truth for the **structure tree**. Each operational row's `originating_branch_id` is its authoritative provenance and is **immutable after write**. `current_home_branch_id` is the authoritative *designation* maintained by an Identity/Students command; it never drives balances.

### 7. Historical-data behavior (esp. unknown provenance)
- **No backfill of ambiguous legacy records to one branch** (explicit constraint). Records created before provenance exists stay `originating_branch_id = NULL` and are treated as **org-scope/unknown-provenance** in reporting and access, with an audited, per-record (never bulk-guessed) correction path if a human later assigns provenance with evidence.
- NULL provenance is a first-class, reportable state (`provenance = 'unassigned'`), not an error to hide.
- Cross-branch/org-scope authority still operates on these records (org-level access), so unknown provenance does not strand them.

### 8. Cross-branch behavior & scope semantics
- Authorization continues to be scope-grant based; provenance attributes and `branch_scope_links` feed **affected-scope determination** so a branch-restricted user only mutates/reads records whose originating/current branch is within the granted scope.
- A cross-branch action (e.g., an obligation paid at branch B against a student whose enrollment is at branch A) records its own originating branch and is visible under both affected scopes via the junction; balances remain in one authoritative place.

### 9. Invariants (DB/domain)
- `originating_branch_id` immutable once set (UPDATE trigger).
- `originating_branch_id`/`current_home_branch_id`/`people.home_branch_id` must reference an existing, non-archived branch (real FK).
- A record cannot be transferred to a new *originating* branch (only a new enrollment can) — provenance immutability.
- Financial totals are derived from fact rows regardless of branch attributes; provenance never changes an amount (no second financial truth).

### 10. Consumption by existing modules
Identity/Students maintains home-branch designation; Academic writes provenance at enrollment/class/period creation; Finance writes provenance at obligation/payment/funding creation; Reporting and authorization filter/aggregate by originating & current branch via the junction.

### 11. Migration / backfill risk
High but controlled: adding NOT NULL provenance is **not** done to legacy rows; a nullable `originating_branch_id` is added first, new writes populate it, and only after a governed, evidence-based assignment process would it be backfilled per record. Rollback requires dropping added columns/junction (no data loss to existing ledgers).

### 12. Affected existing tables/modules
Enrollment (Academic), obligations/payments/refunds/funding (Finance), contracts (HR), certificates (Academic), periods; Identity/Students, Access (junction), Reporting.

### 13. Downstream dependencies
F3 (offerings must know their branch), F4 (periods hosted by branch/term), financial branch reporting (WP-4), enrollment financial gates (WP-3/4).

### 14. Rejected alternatives
- **Rejected: one `branch_id` "home" column on every financial row used as both provenance and financial truth.** It would let current-home changes rewrite financial history and create two truths. Rejected per the "current home branch is not automatically the financial/operational branch" and "must not become a second independent source of truth" constraints.
- **Rejected: bulk-backfill ambiguous records to a single branch.** Directly forbidden.
- **Rejected: model branch only via untyped `scope_grants.scope_id`.** No real FK; cannot express cross-branch affected scopes reliably.

---

## WP2-DEC-02 — F2: ProgramVersionLevel / CEFR model

### 1. Exact problem
Programs and immutable program versions exist, but there is **no level entity**: a class binds to `program_version_id` directly, with no notion of a level within a version or CEFR banding. Level is the unit that progression, placement, fee packaging, and class enrollment actually operate on, so the missing level breaks G (placement→level), J (level-based progression per G2-D-003), F (offerings by level), and K/L (fee packaging by level).

### 2. Currently frozen architecture rule
`Program` → `ProgramVersion` (immutable, unique per program+version_no) → `Class`. No level authority exists; nothing defines level semantics or CEFR.

### 3. Missing / insufficient
- No `ProgramVersionLevel` entity or table; classes have no `program_version_level_id`.
- No CEFR banding or proficiency authority.
- No level-based progression rule target (G2-D-003 requires per-program *rule*; level is the natural granularity).

### 4. Proposed minimum extension
Introduce **`program_version_levels`** as a child of `program_version` with an ordered ordinal and optional CEFR mapping, and re-point `classes` (and future offerings/enrollments) at a level within a version. `program_version_level` becomes **the authority for academic level/version semantics**.

### 5. Entities/tables (conceptual)
- `program_version_levels(id, program_version_id FK, level_key, ordinal, title, cefr_ref nullable, summary, lifecycle_state, timestamps, unique(program_version_id, level_key), unique(program_version_id, ordinal))`.
- Optional small reference `cefr_levels(code PK, label)` only if a ratified CEFR authority is required; otherwise `cefr_ref` stays a plain attribute (decision needed separately).
- `classes.program_version_level_id FK` (additive; retains `program_version_id` derived).

### 6. Authoritative source of truth
`program_version_levels` is the single authority for the set and ordering of levels under an immutable program version. A level belongs to exactly one program version; it is never shared across versions (a new version declares its own levels).

### 7. Historical-data behavior
Existing `classes` without a level are migrated with a **nullable** level (unchanged semantics = "version-level unspecified") rather than inventing a level; new classes require a level once F3 offerings exist. Levels added to a published/immutable program version are not retro-edited onto closed history (provenance immutability preserved); they apply to new offerings/enrollments.

### 8. Cross-branch / scope
Levels are academic-structure data (org-scope authority via `academic.structure`), not branch-scoped; their *offerings* (F3) carry branch. No per-branch level.

### 9. Invariants (DB/domain)
- Ordinal unique per program version; `level_key` unique per program version.
- A class's level must belong to the class's program version (cross-FK CHECK).
- Levels immutable once referenced by a class/enrollment/offering/placement snapshot (additive append for correction, mirroring version immutability).

### 10. Consumption
MaintainAcademicStructure gains level CRUD inside the existing immutable-version rule; MaintainClass references a level; DecideProgression/G2-D-003 rules key off level; placement recommendation (G) targets a level; Finance packages fees by level via the offering.

### 11. Migration/backfill risk
Low for the additive table; medium for re-pointing `classes` (nullable backfill only). No existing financial truth depends on levels yet.

### 12. Affected tables/modules
`classes`, `program_versions`, `programs` (Academic); future offerings (F3), progression (J), placement (G).

### 13. Downstream dependencies
F3 (offerings per level), G (placement→level), J (progression rules), K/L (fee packaging).

### 14. Rejected alternatives
- **Rejected: put a free-text `level` string on classes/program_versions.** No ordering, no authority, duplicates program/version semantics, and cannot be a FK target for offerings/placement.
- **Rejected: make levels global across program versions.** A level's meaning must be version-bound (version is immutable), so global levels would leak across versions.

---

## WP2-DEC-03 — F3: BranchAvailability + Term + Offering model

### 1. Exact problem
Enrollment currently targets a **class** directly; a class binds `program_version_id + period_id` with no notion of which **branch** runs it, in which **term**, and under what availability. The ERP's natural registration/financial/packaging unit is an **offering** (a program-version level a branch runs in a term), and branch availability is its precondition. These three concepts are **co-dependent**, not a linear chain.

### 2. Currently frozen architecture rule
`academic_periods` = term authority (Gregorian start/end, lifecycle). `classes` belong to a program version and a period. No branch/availability/offering.

### 3. Missing / insufficient
- No `Offering`; no `BranchAvailability × Term`; branch does not host academic periods/classes.
- Registration and financial obligations attach to class/enrollment with no branch term availability or offering reference, so per-branch scheduling and fee packaging cannot be correct.

### 4. Proposed minimum extension
Model three co-defined concepts as one extension:
- **BranchAvailability**: a branch declares, for a given period/term, which program-version levels it will run (or the level declares branch availability). Availability is the coupling precondition.
- **Offering**: the concrete "branch runs level X of program version V in term T", created from an available (branch × version-level × term) combination; a class (or multiple sections) then realizes an offering.
- Enrollment targets an **offering** (level+term+branch), with classes as the physical realization.

### 5. Entities/tables (conceptual)
- `offerings(id, branch_id FK, program_version_level_id FK, academic_period_id FK, status, capacity, timestamps, unique(branch_id, program_version_level_id, academic_period_id))`.
- `branch_availabilities(id, branch_id FK, academic_period_id FK, program_version_level_id FK nullable, lifecycle_state, timestamps, unique(...))` (null level = branch-level whole-program availability statement).
- `enrollments.offering_id FK` additive; `classes.offering_id FK nullable` additive.

### 6. Authoritative source of truth
`branch_availabilities` and `offerings` are owned by Academic (`academic.structure/schedule` authority). An **enrollment may exist only if a matching offering exists and is open**, and the offering's (branch, level, term) is the authoritative packaging that Finance consumes for fees. Branch (from F1), level (from F2), term (existing academic period) are each sourced from their owning module — no new calendar or branch authority is invented here (F4 owns term semantics).

### 7. Historical-data behavior
Existing enrollments/classes without an offering stay referencing their class and are treated as "pre-offering / org-term" with nullable `offering_id`; no retro-creation of offerings for old records. New enrollments require an offering.

### 8. Cross-branch / scope
An offering is explicitly branch-bound (real FK to `branches`). A student enrolling at a branch consumes that branch's offering; cross-branch transfer becomes ending one enrollment and opening a new one at the target offering (F1/H semantics). Availability is per-branch so a level may be unavailable at one branch and available at another.

### 9. Invariants (DB/domain)
- Offering must reference an open academic period that overlaps the availability window.
- An offering requires a `branch_availability` matching (branch × level × term) — FK/composite check.
- `enrollments.offering_id` consistency: offering's level must equal the class's level when a class is linked (cross-FK).
- Offering unique per (branch, level, term); capacity not exceeded (mirror class seat rules).

### 10. Consumption
Admissions/Enrollment target offerings; Finance posts obligations against the offering's fee packaging; Reporting aggregates enrollment by offering/branch/term; Placement (G) and progression (J) consume the offering's level.

### 11. Migration/backfill risk
Low for additive tables; the risk is conceptual (re-pointing enrollment→offering). New-enrollment-only gate avoids touching history.

### 12. Affected tables/modules
`enrollments`, `classes`, `academic_periods` usage (Academic); Admission conversion (Admissions); obligations (Finance) later; Reporting.

### 13. Downstream dependencies
H (registration/enrollment), K/L (fee packaging), G (placement→offering/level), R (reporting by branch/term).

### 14. Rejected alternatives
- **Rejected: linear chain "availability → then offering → then class" as independent tables with no mutual constraint.** The three are co-dependent; an offering without a matching availability or an availability that no offering realizes is a contradiction. Enforced as one coherent extension with cross-FKs.
- **Rejected: fold availability into a simple enum/flag on offerings.** Cannot express "which terms/levels a branch will run in the future" before an offering exists (planning), which availability must support.

---

## WP2-DEC-04 — F4: Calendar authority / Shamsi–Gregorian semantics

### 1. Exact problem
The system stores Gregorian/ISO dates only; there is no business-calendar authority. Academic terms, financial/payroll periods, reporting periods, invoices/receipts, attendance, and dashboards therefore have no consistent Shamsi–Gregorian business-date semantics, and period alignment across modules (academic vs financial vs payroll) cannot be guaranteed. The legacy system carried a `jalali.ts` utility (reference-only).

### 2. Currently frozen architecture rule
Each period authority is Gregorian and owned by its module (Academic `academic_periods`, Finance `financial_periods`, Payroll `payroll_periods`); there is no calendar decision and no cross-period alignment authority. Legacy calendar code is explicitly reference-only.

### 3. Missing / insufficient
- No single calendar authority; date handling is ad-hoc Gregorian.
- No defined behavior for how a Shamsi business date is stored, derived, or displayed, or how periods/reporting periods map.

### 4. Proposed minimum extension
Pick one of three calendar policies (below); the chosen policy is implemented as a **calendar service** owned by a calendar/configuration authority that exposes conversions and defines the canonical "business date," while **storage stays a single canonical date representation** (no dual-date columns that can disagree).

### 5. Three-way comparison

| Option | Storage | Business truth | Cost / risk | Fit |
|---|---|---|---|---|
| **G1 Gregorian-only** | Gregorian everywhere (today) | Gregorian is the only business date; Shamsi only as a display conversion if ever needed | Zero new risk; lowest cost | Least fit for an Afghan institution whose operational business dates are Solar Hijri |
| **G2 Shamsi-first with Gregorian storage/derivation** | Gregorian (canonical, computed-safe) | **Shamsi is the business date**; every business period keyed/documented in Shamsi, stored as the exact Gregorian instant/date and *derived* to Shamsi on demand from a ratified conversion (fixed algorithm, tables of leap rules) | Medium: needs a ratified fixed algorithm; no ambiguous dual storage; all range math done on the canonical Gregorian, displayed/perioded in Shamsi | **Recommended** |
| **G3 Dual-calendar authority (both stored/authoritative)** | Two canonical date columns per event | Both Gregorian and Shamsi authoritative | High: two independent truths can disagree; every invariant doubled; migration/backfill heavy | Over-engineered; rejected |

**Recommendation: G2 — Shamsi-first business semantics with a single canonical Gregorian/ISO storage and an authoritative, immutable, versioned Shamsi conversion/algorithm.** Rationale: it honors Solar-Hijri as the operational business calendar while keeping one stored truth (no date disagreement, range arithmetic and DB invariants stay on the canonical Gregorian date), and it keeps migration risk bounded.

### Consequences of G2 per domain
- **Academic terms:** period keys/starts/ends expressed in Shamsi; stored as exact Gregorian bounds; no gap in cross-term continuity.
- **Payroll:** period-day proration (today uses Gregorian day counts) computed on canonical dates; Shamsi is labeling/business-identity only — **amounts and proration math are unchanged** because they derive from canonical dates.
- **Finance:** financial periods keyed by Shamsi period; posting dates validated on canonical dates; period-open/closed logic unchanged (already on dates).
- **Reporting:** reporting periods and range queries resolve on canonical dates, labelled in Shamsi.
- **Invoices/receipts:** business date printed in Shamsi (from canonical), immutable on the snapshot.
- **Attendance:** attendance facts keyed to canonical date; displayed per Shamsi term.
- **Dashboards:** period buckets in Shamsi; range queries on canonical date — no bucketing drift.
- **Date-range queries:** all DB `BETWEEN`/range predicates execute on the canonical date column (index-safe); Shamsi filtering never hits SQL directly.
- **Immutable snapshots:** a snapshot stores the canonical date + the ratified calendar algorithm version, so the Shamsi label is always reproducible — never a stored second date that can drift.

### 6–9. Entities/source-of-truth/historical/cross-branch/invariants
- **Source of truth:** a calendar-config record capturing the chosen scheme (`scheme = 'shamsi_gregorian'`), the ratified algorithm/table version (`calendar_scheme_versions`), and the display default. Conversion is a pure function of (canonical date, algorithm version) — no stored dual dates.
- **Historical:** canonical dates already stored remain correct; they gain Shamsi derivation retroactively by the fixed algorithm (deterministic, auditable, not a data guess). No backfill of a second column.
- **Cross-branch/scope:** calendar is org-wide (a single institution calendar authority); not branch-scoped. (If the institution ever needs per-branch calendars, that is out of the current decision.)
- **Invariants:** a stored date is always canonical; any displayed Shamsi value is derived and tagged with the algorithm version; period-open/closed and proration always use canonical arithmetic.

### 10–14. Consumption, risk, affected, downstream, rejected
Consumed by every period authority and every snapshot. Risk: medium for first adoption of a ratified algorithm (must match statutory rules); affected: `academic_periods`, `financial_periods`, `payroll_periods`, reporting periods, print snapshots, attendance. Downstream: F1 term hosting, R reporting periods, S. **Rejected G3 (dual authority)** and **Rejected G1 (Gregorian-only)** per the comparison — G1 rejected because it cannot represent the operational Solar-Hijri calendar faithfully; G3 rejected because two authoritative dates violate single-source-of-truth and double every invariant. *Decision requires explicit owner approval (introduces a calendar authority not present in the frozen architecture).*

---

## WP2-DEC-05 — S1: Governance configuration & audit

### 1. Exact problem
Multiple decided governance items are recorded as **OPEN thresholds/limits** (D-F-007 risk/value thresholds; D-F-020/022 branch operating & financial limits; D-F-036 department scopes; REQ-ORG-007/013/015; REQ-FIN-001/002 branch expense limits; D-F-083 annual access review), but there is no configuration registry for them and no rule for what is configurable vs hard-coded or how changes are versioned/audited.

### 2. Frozen rule
Fail-closed by default (no threshold ⇒ deny); sensitive actions already require two-Owner/Finance/GM flows in code (the *process* is frozen); the numeric/class thresholds themselves were never ratified.

### 3. Missing / insufficient
A single place to declare, version, and audit governed numeric/class configuration without weakening the fail-closed or two-Owner code paths.

### 4. Proposed minimum extension
A **configuration registry** (`governed_configs`) whose rows are typed, versioned, and audited — **not** a free-form key/value blob. Structure-change & money actions read it; absence ⇒ fail closed.

### 5. Entities (conceptual)
- `governed_configs(id, config_key, config_type, value JSONB, effective_from, effective_to nullable, supersedes_id, lifecycle_state, approved_by, review_cycle, timestamps, unique(config_key, effective_from))`.
- Audit via the existing `Audit`/`audit_events` module (each activation/revision is a governed, authorized event).

### 6. What must be configurable vs hard-coded
- **Configurable (typed, versioned):** monetary/risk approval **thresholds and limits** by action type and scope (branch expense, discount, refund, compensation, restricted-fund allocation, disposal value); department-scope definition references; **annual-review cycle**; expense approval routing (Finance approver / GM substitute) — the *identity* of the allowed approver per limit class.
- **Hard-coded (never config-driven):** fail-closed default-deny; two-Owner rules for org-wide/sensitive actions; the separation-of-duties invariants (one person never initiates+approves+records+reconciles); immutable-source/cap rules on money; calendar scheme; authority provenance immutability. These are domain invariants, not configuration.

### 7. Versioning & audit
Each change is a **new effective version** (append; never in-place edit). Activation requires the authority the config governs (a change to two-Owner thresholds must itself be a two-Owner decision). Every read is logged, and the audit module records who changed a threshold, from→to, effective window, and reason. Reporting/snapshots capture the config version in force at the time (so past approvals are reproducible).

### 8. Consumption
Approval workflows (StructureDecision, Finance, HR) read the current version for the action+scope and fail closed if none; annual-review jobs enumerate sensitive grants against the review cycle.

### 9. Migration/backfill risk
Low: seeded with current fail-closed defaults and *no* invented thresholds (explicit rule — thresholds must not be invented). Adoption is forward-only.

### 10–14. Affected / downstream / rejected
Affected: Finance (expense/refund/discount/disposal), HR (compensation), Access (review cycle), StructureDecision. Downstream: M (expenses/budgets) and B (annual review). **Rejected:** a free-form `settings` key/value table (no type safety, no versioning, no audit) and hard-coding all thresholds (defeats the decided configurable-limits governance). *Requires explicit owner approval to ratify the actual threshold numbers/classes.*

---

## Decision table

| Decision ID | Recommended choice | Alternatives rejected | Impact | Dependencies | Requires explicit owner approval? |
|---|---|---|---|---|---|
| **WP2-DEC-01 (F1)** | Provenance + designation attributes (`originating_branch_id`, `current_home_branch_id`, nullable) + typed `branch_scope_links` junction (real FKs); no bulk backfill | (a) single `branch_id` used as provenance+financial truth; (b) bulk-backfill ambiguous rows; (c) branch only via untyped scope grants | Adds provenance to academic/financial/HR records; enables D-F-014, per-branch finance & reporting | F3 (offerings per branch), F4 (term hosting); Access | **YES** (extends frozen data model) |
| **WP2-DEC-02 (F2)** | New `program_version_levels` (ordered, unique per immutable version) + optional CEFR; classes re-point to level (nullable backfill) | (a) free-text level strings; (b) levels global across versions | Enables level-based progression/placement/packaging | F3, G, J | **YES** (extends Academic structure authority) |
| **WP2-DEC-03 (F3)** | Co-defined `offerings` + `branch_availabilities` (branch × version-level × term) as one extension with cross-FKs; enrollment targets offering | (a) linear independent availability→offering→class tables without mutual constraints; (b) availability as enum flag on offerings | Correct per-branch registration & fee packaging | F1, F2 | **YES** |
| **WP2-DEC-04 (F4)** | **G2 Shamsi-first business semantics, single canonical Gregorian storage + authoritative versioned derivation** | G1 Gregorian-only; G3 dual-calendar authority (two stored truths) | Adds a calendar authority; terms/payroll/finance/reporting/invoices/attendance/dashboards/ranges/snapshots all calendar-correct | Affects all period authorities & snapshots | **YES** (new authority, not in frozen arch) |
| **WP2-DEC-05 (S1)** | Typed, versioned, audited `governed_configs` registry (append-only effective versions; fail-closed on absence); configurable = thresholds/limits/routing/review-cycle; hard-coded = SoD/default-deny/immutability invariants | (a) free-form settings key/value blob; (b) hard-coding all thresholds | Ratifies OPEN governance thresholds/limits & annual review | B, M; Audit | **YES** (ratifies actual threshold numbers/classes) |

No production code, migrations, schema, or test changes were made by this package. Working tree state below.

---

WP-1.5 ARCHITECTURE DECISION PACKAGE COMPLETE — AWAITING ARCHITECTURE APPROVAL

---

## HISTORICAL SOURCE: `implementation/WP-2-F4A-calendar-authority-verification.md`

# WP-2 F4-A — Calendar Authority & Algorithm Verification (research/spec record)

**Status:** `F4-A VERIFIED` (calendar-authority gate flipped from BLOCKED by the
owner's recorded ratification D1–D4 in F4-A.4; see §F4-A.4 at the end and
`WP-2-F4A.3-solar-hijri-reference-series-ratification.md` §12). F4 production
implementation remains **pending**.
**F4-A.2 addendum:** 2026-09-03 — narrowed the blocker and set out a concrete
ratification path (see §F4-A.2 at the end). The definitional identity and the
near-term Nowruz/leap series are now established on converging evidence; a
per-day **Afghan primary** civil almanac and the operative reference under the
2022 lunar-Hijri official reversion were the residual gaps at that time. The
**F4-A.4 addendum** records the owner ratification (D1–D4) that subsequently
resolved those gaps as a ratified TOEFL House reference-series decision.
**WP2-DEC-04 (F4) architecture decision:** UNCHANGED (G2 — Shamsi-first business
semantics over a single canonical Gregorian stored date with authoritative,
versioned Shamsi derivation). No calendar conversion code was written.
**Branch:** `arena/01a062e3-toefl-house`
**Date:** 2026-09-03

This phase produced **research, specification, and reference-set analysis only**.
No calendar service, conversion code, library dependency, migration, or date
logic change was made. F4 production implementation remains **pending** and may
not proceed until the algorithm is verified against an authoritative reference.

---

## 1. Authoritative calendar definition selected (what "Shamsi" means here)

TOEFL House operates in Afghanistan. Its authoritative business calendar is the
**Afghan official civil Solar Hijri calendar** — Hejrah-e Shamsi (هجری شمسی),
also "Jalali" — the same equinox-based solar calendar as the Iranian Solar
Hijri (Persian) calendar, differing **only in month names**.

Authoritatively established definition:

| Attribute | Value | Source |
|---|---|---|
| Calendar type | Solar; year = interval between successive **vernal equinox** occurrences (observation-based, **not** a fixed arithmetic formula) | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [Calendars Wiki](https://calendars.fandom.com/wiki/Iranian_calendar) |
| Official in Afghanistan | Adopted as the official civil calendar; in standardised official use since ~1336 H.S. / **1957 CE** (legally recognised as the Jalali solar calendar from ~1922); defined in the Constitution | [US DOJ/EOIR Afghanistan calendar research](https://www.justice.gov/sites/default/files/eoir/legacy/2013/06/11/calendar.pdf); [Encyclopaedia Iranica "Calendars"](https://www.iranicaonline.org/articles/calendars/); [HandWiki](https://handwiki.org/wiki/History:Solar_Hijri_calendar) |
| Epoch | The Hijra, 622 CE; years counted from the solar year of the migration | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [timeanddate](https://www.timeanddate.com/calendar/persian-calendar.html) |
| New Year | **Nowruz = 1 Hamal** begins at the vernal equinox (~20–21 March Gregorian). Civil rule: if the equinox falls before local noon, that day is 1 Hamal; if after noon, that day is 29/30 Hut and the following day is 1 Hamal | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [1Rooz](https://1rooz.com/about_persian_calendar.php) |
| Months (Dari names) | 1 Hamal, 2 Sawr, 3 Jawza, 4 Saratan, 5 Asad, 6 Sunbula, 7 Mizan, 8 Aqrab, 9 Qaws, 10 Jadi, 11 Dalwa, 12 Hut | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [Transparent Dari](https://blogs.transparent.com/dari/2012/11/26/afghan-calendar-and-months-of-the-year-in-dari/) |
| Month lengths | Months 1–6 = 31 days; months 7–11 = 30 days; month 12 (Hut) = 29 days in common years, 30 in leap years | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [nongnu afghancalendar](https://www.nongnu.org/afghancalendar/); [timeanddate](https://www.timeanddate.com/calendar/persian-calendar.html) |
| Leap year | Year is 366 days (Hut = 30 days) when needed so the following Nowruz aligns with the equinox. **No single mathematical leap formula is official.** Common arithmetic "33-year" cycles and the proposed "2820-year" cycle are approximations, not the authoritative rule | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar) |
| Iran vs Afghanistan | Same structure, year numbering, day counts, and equinox base; Afghanistan uses Dari zodiac month names (Hamal…Hut), Iran uses Farvardin…Esfand | [Wikipedia](https://en.wikipedia.org/wiki/Solar_Hijri_calendar); [mtempmail](https://mtempmail.com/afghan-date-converter); [Encyclopaedia Iranica](https://www.iranicaonline.org/articles/calendars/) |

**Historical range caution (pre-1957):** Before ~1336 H.S. / 1957, official Afghan
month lengths were **variable (roughly 29–32 days)**, set by the sun's passage
through the zodiac, and only the 1957 reform standardised the fixed
31/30/30/29-30 structure ([Encyclopaedia Iranica](https://www.iranicaonline.org/articles/calendars/)). A fixed arithmetic conversion is therefore **not
valid for pre-1957 Afghan civil dates**. This matters because TOEFL House holds
historical data (birth dates, certificates, legacy records).

### Iran/Afghanistan relationship
The calendars are the same solar system; conversion of a day-count and the
month/year boundaries coincide, differing only in month labels. Reference dates
for the Iranian calendar are therefore usable as a technical proxy for the
Afghan calendar's structure and Nowruz, but the **exact civil day** is where a
reference-meridian/time-zone question arises (see §11).

---

## 2. Why this is appropriate for Afghanistan / TOEFL House

- Afghanistan's constitution and administrative practice use Hejrah-e Shamsi
  for official dates; government offices and official documents use it while
  Gregorian is used for passports/foreign correspondence
  ([US DOJ/EOIR](https://www.justice.gov/sites/default/files/eoir/legacy/2013/06/11/calendar.pdf);
  [Transparent Dari](https://blogs.transparent.com/dari/2012/11/26/afghan-calendar-and-months-of-the-year-in-dari/)).
- It is the calendar of Afghan public life (fiscal periods, academic terms,
  contracts, due dates, dashboards) — exactly the semantics TOEFL House must be
  Shamsi-first about.
- Being a solar calendar it stays aligned with seasons, so academic terms and
  fee/installment periods have stable seasonal meaning, which is why G2 (a
  single canonical representation + authoritative derivation) is the right model.

---

## 3. Calendar semantics specification for the ERP (proposed)

### Date
- Shamsi year (e.g. 1405), Shamsi month (1–12, Dari order), Shamsi day (1–31).
- Month lengths fixed: `[31,31,31,31,31,31,30,30,30,30,30,29-or-30]`.

### Period
- Start of Shamsi year = 1 Hamal = Nowruz day (equinox rule).
- Month n of year y runs from its first day to the day before the first day of
  month n+1 (year month 12 runs to the last day of Hut, the day before the next
  year's 1 Hamal).
- Financial/payroll/academic month = **one complete Shamsi month** (never a
  partial Gregorian interval).

### Leap year
- Hut has 30 days exactly in leap years (366-day year); otherwise 29.
- Leap-year set must come from the **authoritative published civil series** for
  the operating range, not from an unverified arithmetic formula (see §11).

### Business periods
Every business-period concept (financial month/year, payroll month, academic
term, fee due date, installment, expense/reporting period) must be derived from
the **authoritative Shamsi period**, and only then mapped to exact Gregorian
boundaries for storage/querying. Modules must not each define "a month".

---

## 4. Historical reference-date verification set

The table below is the verification set to which any candidate implementation
must conform. Rows marked **ATTESTED** are supported by multiple independent
sources above; rows marked **CONFLICT** are the points of genuine disagreement
that currently block verification.

| # | Gregorian | Shamsi | Type | Source / basis | Why important | Status |
|---|---|---|---|---|---|---|
| R1 | 2023-03-21 | 1 Hamal 1402 | Nowruz (common-year start) | equinox 00:54 Tehran; attested | year-start | ATTESTED |
| R2 | 2024-03-20 | 1 Hamal 1403 | Nowruz (equinox morning) | equinox 06:36 Tehran | year-start; near date | ATTESTED |
| R3 | 2025-03-20 **or** 2025-03-21 | 1 Hamal 1404 | Nowruz (near-noon equinox) | equinox 12:31 Tehran — sources differ on 20th vs 21st | **operational window** | **CONFLICT** |
| R4 | 2026-03-20 **or** 2026-03-21 | 1 Hamal 1405 | Nowruz (near-noon equinox) | equinox 15:46 Tehran — sources differ | **operational window** | **CONFLICT** |
| R5 | 2021-03-21 | 1 Hamal 1400 | Nowruz | attested | year-start | ATTESTED |
| R6 | 2026-09-02 | 11 Sunbula 1405 | ordinary (mid-year) | current date proxy (Wikipedia/converters) | drift check | ATTESTED (verify against civil almanac) |
| R7 | pre-1957 Afghan civil dates | variable-length months | historical | Encyclopaedia Iranica | legacy DOB/certificates | **NOT representable by fixed arithmetic** |

Why these matter: R1–R5 pin Nowruz boundaries; R6 is far from a boundary to
detect algorithmic drift; R7 exposes the historical-range limitation.

**I did not fabricate any expected value.** Values not independently and
consistently attested are marked CONFLICT or NOT-representable rather than given
a number.

---

## 5. Candidate algorithms / libraries examined (conceptual)

| Candidate | Basis | Leap methodology | Known limitation for TOEFL House |
|---|---|---|---|
| "Jalaali" arithmetic 33-year cycle (common in jalaali-js and many PHP/Python snippets) | Arithmetic approximation | Fixed leap pattern | Not the official equinox rule; can drift a day near boundaries; not authoritative |
| Proposed 2820-year cycle (Birashk) | Arithmetic | Long cycle | **Never officially adopted**; rejected by authorities |
| Astronomical/equinox method (Iranian official; Institute of Geophysics, Univ. of Tehran; and the Kabul-observed variant) | Observation of the vernal equinox | Equinox-based | Requires an ephemeris and a per-year leap series; no closed arithmetic formula |
| ICU / Intl "persian" calendar (PHP `fa_IR@calendar=persian`, jalaali libs built on arithmetic Persian) | Algorithmic approximation | Arithmetic | Not guaranteed to equal the Afghan civil series in every near-boundary year |

No candidate is, by itself, "the authoritative Afghan Solar Hijri civil
calendar." Selecting by popularity (e.g. "jalaali-js is common") is explicitly
rejected here.

---

## 6. Comparison results

- The **structure** (month lengths, Dari names, epoch) is authoritative and not
  in dispute.
- The **civil Nowruz/leap day for the current operating window (1404/1405,
  i.e. 2025–2026)** is in genuine dispute across the sources examined (§4 R3/R4,
  §11). The equinox falls after the noon cutoff in both years, so the noon-rule
  answer (21 March) and the "equinox-day" answer (20 March) differ, and
  individual converters and calendars disagree.
- No arithmetic algorithm can be proven to equal the Afghan civil series for the
  full required historical range (which itself includes the pre-1957
  variable-length period) without an authoritative per-year reference.

---

## 7. Round-trip verification

Round-trip is a **necessary, not sufficient**, invariant. Mandatory eventual
invariants for the implementation:

```
gregorian → shamsi → gregorian == original gregorian date
shamsi    → gregorian → shamsi    == original shamsi date
```

These must hold across ordinary dates, month boundaries, year boundaries, leap
boundaries, and dates around Nowruz, over the complete supported range. But a
self-consistent wrong table round-trips perfectly, so every candidate must also
pass the authoritative reference-vector validation (§4). **No round-trip run was
performed in F4-A** because no candidate was authorised/verified yet.

---

## 8. Financial / business-period implications

- **Financial invariant:** calendar correctness is money correctness. A Shamsi
  reporting/financial month must equal the exact Gregorian interval of that
  Shamsi month — not an "approximately March" partial interval.
- The correct semantic model is:
  `Business period → authoritative Shamsi period → exact Gregorian boundaries
  for technical querying/storage`
  and **never**
  `Gregorian month → approximate Shamsi label`.
- This directly prevents a recurrence of the prior dashboard defect where a
  Shamsi reporting month was represented by an incomplete Gregorian interval.

---

## 9. Proposed Calendar Authority boundary (design only — not implemented)

A future Calendar Authority should be the **single** consumer-facing authority
for Finance, Payroll, Academic terms, Reporting, Fees/installments, Due dates,
Dashboards, and future operational modules. Other modules must not implement
their own Shamsi conversion.

Proposed boundary:
- **Input/output:** accepts/returns a canonical Gregorian date and yields the
  authoritative Shamsi date (or a Shamsi period → its exact Gregorian
  boundaries). No module stores a second "Shamsi" date truth.
- **Versioning:** conversion is pinned to a ratified calendar-algorithm
  version; each version carries the leap/equinox series it encodes so historical
  reports stay reproducible.
- **Supported range:** defined (provisionally modern operational range; explicit
  handling required for pre-1957 dates).
- **Error behavior:** fail-closed on unsupported dates/range; no silent
  approximation.
- **Boundary behavior:** month/year/Nowruz edges resolved exactly by the pinned
  series.
- **Governance:** algorithm/leap-series revisions are ratified like governed
  configuration (consistent with WP-2 S1) and become a new version; past reports
  record the version in force.

---

## 10. Unresolved risks and ambiguities (the blocker)

1. **Reference meridian / time rule.** Sources state Nowruz is the vernal equinox
   as observed from Tehran (52.5°E) *and* Kabul
   ([Calendars Wiki](https://calendars.fandom.com/wiki/Iranian_calendar)), but no
   single authoritative Afghan civil rule specifies the exact meridian/time-zone
   cutoff for Afghanistan. Kabul (≈69°E, UTC+04:30) differs from Tehran, so the
   civil Nowruz day could differ from Iran's on near-midnight equinoxes.
2. **Noon-cutoff Nowruz years.** In the current operating years 1404/1405
   (Nowruz 2025 and 2026) the equinox occurs after the noon cutoff; sources
   genuinely disagree whether 1 Hamal 1404 = 20 or 21 March 2025 (§4 R3/R4).
3. **Leap-year series.** The official leap rule is equinox-observed; the
   per-year civil leap series for the required range is not available to me as an
   authoritative published table in this environment. An arithmetic formula is
   not authoritative.
4. **Pre-1957 dates.** Official Afghan months had variable lengths before 1957,
   so fixed arithmetic is invalid for legacy dates.

Because items 1–4 affect the exact dates in TOEFL House's operating window and
its financial/academic correctness, the evidence is **insufficient to verify an
algorithm**. Per the F4-A rules I STOP here rather than select arbitrarily or
commit an unverified converter.

---

## 11. What is required to unblock (STOP — do not implement F4 until ratified)

1. An **authoritative, per-day Afghan civil (or Iranian official) calendar /
   almanac** covering at least the operational + historical range, OR an explicit
   owner ratification of a **documented conversion spec**: chosen meridian/rule
   (e.g., official noon rule at a stated meridian) plus the ratified leap-year
   series for the supported range.
2. A ratifying decision on **historical range** and handling of **pre-1957**
   variable-length-month Afghan dates (e.g., out of automated range → explicit
   treatment).
3. A ratifying decision that Afghanistan's civil Solar Hijri is adopted as the
   ERP authority (with the chosen rule), and whether Iranian-calendar references
   may serve as the technical proxy.
4. Owner-verified reference vectors for the conflicting rows (R3/R4) so the
   reference set is unambiguous.

Until these are ratified, the expected status is **F4-A BLOCKED PENDING
AUTHORITATIVE CALENDAR VERIFICATION** and **F4 production implementation remains
pending**.

---

## 12. Scope / git discipline confirmation

- **Files changed (documentation only):** this record.
- No production code, calendar conversion, library dependency, migration, or
  date-logic change was made. F1/F2/F3/S1 and the approved G2 decision are
  untouched. Working tree otherwise clean.

## 13. Sources referenced

[Solar Hijri calendar (Wikipedia)](https://en.wikipedia.org/wiki/Solar_Hijri_calendar) ·
[Iranian calendar (Calendars Wiki)](https://calendars.fandom.com/wiki/Iranian_calendar) ·
[Encyclopaedia Iranica — Calendars](https://www.iranicaonline.org/articles/calendars/) ·
[US DOJ/EOIR — Afghanistan calendar research (2010)](https://www.justice.gov/sites/default/files/eoir/legacy/2013/06/11/calendar.pdf) ·
[nongnu — Afghan Calendar algorithm](https://www.nongnu.org/afghancalendar/) ·
[timeanddate — Persian Solar Hijri calendar](https://www.timeanddate.com/calendar/persian-calendar.html) ·
[Transparent Language — Dari months](https://blogs.transparent.com/dari/2012/11/26/afghan-calendar-and-months-of-the-year-in-dari/) ·
[mtempmail Afghan converter](https://mtempmail.com/afghan-date-converter) ·
[1Rooz Persian calendar notes](https://1rooz.com/about_persian_calendar.php) ·
[whatdatetoday Nowruz series](https://www.whatdatetoday.com/persian-date-today/) ·
[emrooz 1404 calendar](https://emrooz.app/en/calendar/1404) ·
[HandWiki — Solar Hijri history](https://handwiki.org/wiki/History:Solar_Hijri_calendar)

---

## F4-A.2 — Authority resolution attempt (addendum)

**Date:** 2026-09-03 · Documentation only; no code.

### A. Primary / legal evidence found

1. **Afghan Constitution (2004), Article 18** (official translation, University
   of Minnesota Human Rights Library):
   > (1) The calendar of the country shall be based on the flight of the
   > Prophet (PBUH). (2) The basis of work for state offices is the **solar
   > calendar**. (3) Fridays and the 28 Asad and the 8 Sawr are public holidays.
   Source: <https://hrlibrary.law.umn.edu/research/afghanistan-constitution.html>
   This is the legal anchor: epoch = the Hijra; **state offices operate on the
   solar (Hejrah-e shamsi) calendar**, and the fixed solar holidays 28 Asad /
   8 Sawr are constitutional.

2. **Adoption/standardisation history** (authoritative encyclopaedic):
   Afghanistan adopted the Jalali (solar) calendar ~1922 and standardised the
   fixed month structure in official use ~1957; the Afghan solar calendar is
   **basically the same as the Persian one**, differing only in month names
   (Dari uses the Arabic zodiac names Hamal…Hut).
   Source: <https://www.iranicaonline.org/articles/calendars/>
   Also: <https://www.justice.gov/sites/default/files/eoir/legacy/2013/06/11/calendar.pdf>

3. **Current official status is contested.** The Islamic Emirate (since its
   2021 return) has dated official/government documents on the **lunar Hijri**
   calendar (e.g., decree dates given as 1443 lunar-hijri) and has removed
   Nowruz from official public holidays, as it did in 1996–2001. Sources:
   <https://www.afghanistan-analysts.org/en/wp-content/uploads/sites/2/2023/07/Decrees-order-of-Taleban-amir-English.pdf>
   <https://en.wikipedia.org/wiki/Public_holidays_in_Afghanistan>
   <https://www.officeholidays.com/holidays/afghanistan/victory-of-the-islamic-emirate>
   Consequence: **there is no single uncontested "official Afghan civil
   calendar" in force today.** Solar Hijri remains the calendar of Afghan
   business, education, and economic/administrative life and is what G2
   (Shamsi-first ERP) requires; this is recorded as a scope fact, not as an
   Iranian preference.

### B. Equivalence analysis (Afghan vs Iranian)

- Encyclopaedia Iranica states the Afghan solar calendar is "basically the same
  as the Persian one," differing only in month names.
- The calendar year-spans published for the Afghan calendar and for the Iranian
  solar calendar give **identical** Gregorian spans and Nowruz dates
  (<https://en.wikipedia.org/wiki/Afghan_calendar>;
  <https://www.wikiwand.com/en/articles/Iranian_solar_calendar>).
- Both are equinox-observed; the leap rule is **not** an arithmetic formula
  (<https://en.wikipedia.org/wiki/Solar_Hijri_calendar>).
- **Conclusion:** for the standardised (1957+) structure, the Afghan Solar
  Hijri and the Persian Solar Hijri are the same calendar; their Nowruz dates,
  leap years, and month day-counts coincide. The two differ only in month-name
  labels. Therefore reference vectors derived from the equinox-observed Persian
  calendar are valid for the Afghan civil calendar over this range. This is
  documented equivalence, **not** "choose Tehran arbitrarily."

### C. Resolved: R3 / R4 (near-noon Nowruz years)

The 33-year-cycle year-spans reference and the noon-rule references converge on
the **civil** first day of Hamal/Farvardin (the day, not the equinox instant):

| Solar Hijri year | 1 Hamal / 1 Farvardin (civil Nowruz) | Leap? | Basis |
|---|---|---|---|
| 1402 | 21 March 2023 | common | year-span; equinox 00:54 Tehran |
| 1403 | 20 March 2024 | **leap** (366 d) | year-span 2024-03-20 → 2025-03-20; equinox 06:36 Tehran |
| 1404 | **21 March 2025** | common | year-span 2025-03-21 → 2026-03-20; equinox 12:31 Tehran (after noon → civil day 21st) |
| 1405 | 21 March 2026 | common | year-span 2026-03-21 → 2027-03-20 |
| 1406 | 21 March 2027 | common | year-span 2027-03-21 → 2028-03-19 |

Sources: year-spans (<https://en.wikipedia.org/wiki/Afghan_calendar>), noon-rule
Nowruz times (<https://www.whatdatetoday.com/persian-date-today/>), and the
Afghan/1404 calendars (<https://emrooz.app/en/calendar/1404>,
<https://mtempmail.com/afghan-date-converter>). The earlier F4-A.1 "20 March
2025" citations (e.g., <https://www.vercalendario.info/en/calendars/persian-calendar/compare-1404.html>)
label the **equinox day**, not the civil day; the civil day under the noon rule
is 21 March. Residual holiday-listings still show 20 March for "Nowrooz" in
some sources under the Taliban (Nowruz is no longer an official Afghan public
holiday), which is a holiday-listing artifact, not a calendar-authority fact.

### D. Required evidence table (operational range)

For each year, the resulting first day of Hamal and leap status below come from
the Afghan/Persian equinox year-spans reference (secondary, cross-checked).
Fields the source does **not** establish — the exact equinox clock-time and an
Afghan-published reference meridian/time-zone — are marked **NS** (not stated)
rather than inferred; a secondary (Tehran) equinox time is given for reference
only and does not change the civil day in this range because Kabul (UTC+04:30)
and Tehran (UTC+03:30) share the same civil Nowruz day in these years.

| SH year | Equinox civil day (Nowruz → 1 Hamal) | Leap (Hut 30) | Reference location/meridian | TZ | Source | Confidence |
|---|---|---|---|---|---|---|
| 1399 | 20 March 2020 | yes | NS (Tehran proxy 08:49) | NS | year-spans | secondary |
| 1400 | 21 March 2021 | no | NS | NS | year-spans | secondary |
| 1401 | 21 March 2022 | no | NS | NS | year-spans | secondary |
| 1402 | 21 March 2023 | no | NS | NS | year-spans; equinox 00:54 | secondary |
| 1403 | 20 March 2024 | yes | NS | NS | year-spans; equinox 06:36 | high (cross-sourced) |
| 1404 | 21 March 2025 | no | NS | NS | year-spans; equinox 12:31 | high (cross-sourced) |
| 1405 | 21 March 2026 | no | NS | NS | year-spans | high (cross-sourced) |
| 1406 | 21 March 2027 | no | NS | NS | year-spans | secondary |
| 1407 | 20 March 2028 | no | NS | NS | year-spans | secondary |
| 1408 | 20 March 2029 | yes | NS | NS | year-spans | secondary |

Month lengths are fixed by structure (months 1–6 = 31, 7–11 = 30, Hut = 29/30),
so Hut length follows from leap status.

### E. Algorithm decision (recommendation)

Evidence supports **option D — versioned hybrid**: an **authoritative annual
equinox/leap series governs civil dates** (the official rule is equinox-observed
and has no closed arithmetic formula), and any arithmetic algorithm is used only
**within a range in which it has been validated against that series**, then
re-validated on series revision. A pure arithmetic formula (33-year or 2820-year)
or an unvalidated ICU/Persian/Jalali implementation is **not** authoritative and
is rejected as the sole source of truth. Versioning guarantees historical
reports resolve to the same business periods even if the series/algorithm changes.

### F. Status and residual blocker

F4-A.2 **does not certify the calendar** because:
1. No **Afghan-government-published per-day** Solar Hijri civil almanac is
   obtainable from the sources available here, and the current authority dates
   official documents on the lunar Hijri calendar, so no single primary Afghan
   per-day authority is in force to cite.
2. The near-term series above is well-converged but rests on secondary/equinox
   references; it is not a primary Afghan government publication.

**Ratification path to flip to `F4-A VERIFIED`:** the architecture owner ratifies
(a) that the ERP adopts the Afghan civil Solar Hijri as the equinox-observed
Persian-equivalent calendar (Dari month names), (b) the equinox civil-Nowruz
rule and a stated reference (documented equivalence means Tehran/Kabul share the
civil day over the needed range; a stated meridian is required for near-midnight
edge cases), and (c) the ratified annual leap/Nowruz series for the supported
range (option D). Until that ratification and a per-year series source are
recorded, the status remains **F4-A BLOCKED PENDING AUTHORITATIVE CALENDAR
VERIFICATION** and F4 production implementation remains pending.

---

## F4-A.4 — Owner ratification of the calendar decisions (status → VERIFIED)

**Date:** 2026-09-03 · Documentation only; no code. WP2-DEC-04 (G2) unchanged.

The architecture owner ratified the four decisions that the F4-A.2 ratification
path and F4-A.3 §9 framed, resolving the residual calendar-authority blocker.
This closes the F4-A verification gate. The ratified series data and its
version are kept explicit in
`WP-2-F4A.3-solar-hijri-reference-series-ratification.md` (§4, §7, §12) so
future implementations are reproducible and historically auditable.

### Ratified decisions (D1–D4)

**D1 — Operational range.** Active operational window SH 1399–1415
(~2020–2037); full supported deterministic range SH 1336–1425 (1957
fixed-structure → ~2047); pre-1336 dates fail closed / require manual handling;
no fabrication of historical calendar or branch/date provenance outside
verified evidence.

**D2 — Reference civil clock.** Kabul local civil time, **AFT = UTC+04:30**, is
the TOEFL House Calendar Authority reference clock; the documented noon-cutoff
rule is applied against Kabul civil time. This is an explicit TOEFL House
**product/architecture decision** — **not** a claim that a currently published
Afghan government source mandates this exact computational rule. The
astronomical-equinox-instant vs civil-first-day-of-Hamal distinction is
preserved. The **1408 divergence** (Nowruz 2029: equinox 08:02 UTC →
12:32 Kabul after noon ⇒ 1 Hamal 1408 = **2029-03-21**; whereas Tehran-noon
would give 20 Mar 2029) remains explicitly documented as the reason D2 matters.

**D3 — Reference-series authority.** The annual equinox/reference series
documented in F4-A.3 is ratified as the **version-1 reference dataset** and is
authoritative for supported dates. Any arithmetic algorithm is only an
implementation mechanism and **must** be validated against the ratified
reference series. The 33-year, 2820-year, ICU/Persian arithmetic, or another
generic Jalali implementation is **not** the sole authority.

**D4 — Acceptance vectors.** F4-A.3 vectors **T01–T17** are the initial
acceptance/test-vector set, provenance tags preserved (EQUINOX / DERIVED /
ATTESTED / REQ-D2); under D2 the REQ-D2 rows resolve to the Kabul (A) branch
(T12 = 1 Hamal 1408 = **2029-03-21**). Implementation must satisfy the vectors
plus the round-trip invariants.

### Authority framing (important caveat, recorded)

The ratified Kabul/AFT rule is the **TOEFL House product/architecture decision**
that resolves D2. It is **not** an externally established Afghan legal or
astronomical authority: no accessible Afghan-government primary source was
found that decrees this exact noon-cutoff computational rule, and Afghanistan's
current (2022 lunar-Hijri) official administration publishes no Solar-Hijri
civil series. This framing is preserved in the version-1 record so future audits
do not misattribute D2 to an external Afghan mandate.

### Effect on F4-A status

F4-A.2/F4-A.3 required (a) adoption of the Afghan civil Solar Hijri
(equinox-observed, Dari months), (b) a stated reference civil rule/clock, and
(c) a ratified annual leap/Nowruz series for the supported range (option D).
D1–D4 satisfy (a), (b), and (c) and ratify the acceptance vectors and supported
range. Therefore the F4-A gate flips from `BLOCKED PENDING AUTHORITATIVE
CALENDAR VERIFICATION` to **`F4-A VERIFIED`**.

**Scope confirmation:** this addendum makes **no** code, schema, migration,
model, service, or date-logic change; it does not implement the Calendar
Authority; it does not alter WP2-DEC-04 (G2). F4 production implementation
remains **pending** and may proceed only in a later phase, validated against
the ratified version-1 series (D3/D4).

---

## HISTORICAL SOURCE: `implementation/WP-2-F4A.3-solar-hijri-reference-series-ratification.md`

# WP-2 F4-A.3 — Solar Hijri Reference-Series Ratification (verification/spec record)

**Status:** `F4-A VERIFIED` (calendar-authority gate flipped from BLOCKED by the
owner's recorded ratification D1–D4 in F4-A.4 — see
`WP-2-F4A-calendar-authority-verification.md` §F4-A.4 and §12 below). This phase
originally **established a fully ratifiable specification** — a reproducible
equinox-based annual series for the ERP operational range, a precise
reference-meridian/cutoff analysis, the 1403–1408 boundary verification, a
candidate-implementation comparison, a reference-vector specification, and the
future Calendar Authority contract — and the owner has now ratified that
specification as version 1. The single residual authority gap identified in §9
(the reference civil clock) is resolved by owner decision **D2 (Kabul AFT
UTC+04:30)** recorded in F4-A.4.
**Basis:** F4-A (commit `4d0ab36`) and F4-A.2 (commit `6beb9b0`) findings, which
are accepted. WP2-DEC-04 (G2 — Shamsi-first business semantics over a single
canonical Gregorian stored date with authoritative, versioned Shamsi derivation)
is UNCHANGED. No production code was written.
**Branch:** `arena/01a062e3-toefl-house`
**Date:** 2026-09-03

This is **documentation / test-data / specification only**. No calendar service,
conversion code, library dependency, migration, financial/payroll/academic date
change, or dashboard change was made. F4 production implementation remains
**pending** and may not proceed until the F4-A gate flips to `VERIFIED`.

---

## 0. Read this first — the single decisive new result

The equinox *instant* for every year 1399–1414 is authoritatively known (from an
astronomical UTC table, §4). Translating that instant into a **civil Nowruz day**
requires a stated "noon-cutoff reference" (the local clock by which the
before/after-noon test is applied). Comparing the two natural candidate
references for an Afghan institution — **Kabul (AFT, UTC+04:30)** vs **Tehran
(UTC+03:30)** — shows:

> **Within SH 1399–1414 the two references produce an identical civil calendar
> in every year EXCEPT SH 1408 (year beginning Nowruz 2029), where they differ
> by one day.** Under the Kabul reference, 1 Hamal 1408 = **2029-03-21** and 1408
> is a common year (1407 is leap). Under the Tehran reference, 1 Hamal 1408 =
> **2029-03-20** and 1408 is a leap year (1407 is common).

Because 2029 is in the future relative to today (2026), **no published almanac
can settle this yet**, and Afghanistan's current (2022-reverted lunar-Hijri)
official administration does not issue an authoritative Solar-Hijri civil
series. Therefore the reference must be **ratified as an owner decision** (§9),
and the phase remains BLOCKED rather than silently choosing Kabul or Tehran.
Everything required for a one-line ratification is provided below.

The already-operative years (SH 1399–1407 civil days, i.e. through Nowruz 2028,
and the whole of 1404/1405 which were the earlier focus) are **unambiguous**:
both references agree, so current operational data is not at risk.

---

## 1. Required ERP operational range

### 1.1 Evidence from the existing system (facts, not assumptions)

The system is a Laravel/PHP ERP. All date-bearing columns are **Gregorian/ISO
only**; there is no existing Shamsi/Hijri column and no calendar scheme config
(verified by code inspection — grep for `shamsi|hijri|hamal|calendar_scheme`
returns nothing in `app`, `config`, and seeders; the legacy TypeScript
`core/calendar/periods.ts` / `jalali.ts` are **reference-only** and are not in
this tree). Date-bearing structures observed:

| Structure | Date field(s) | Semantic (documented from the approved G2/F4 design) |
|---|---|---|
| `academic_periods` | `starts_on`, `ends_on` | term bounds (Gregorian storage; term keys Shamsi) |
| `financial_periods` | `date_from`, `date_to` | financial period window |
| `payroll_periods` | `date_from`, `date_to` | payroll period window |
| `class_sessions` | `scheduled_on` | session day |
| `people` | `date_of_birth` | person birth date |
| `certificates` | `issued_on` | certificate issue date |
| `contracts`, `contract_versions` | `effective_from`, `effective_to`, `submitted_at`, `approved_at` | HR contract window |
| `obligations` / payments | `due_on`, `received_on` | fee/installment due & receipt |
| `leaves` | `date_from`, `date_to` | leave window |
| (configs, grants, statuses, …) | `effective_from`, `effective_to` | governed windows |

No data-bearing migration seeds historical rows: `FirstRunBootstrapSeeder`
bootstraps only identity/roles/grants and carries no hard-coded operational
dates. The automated test inventory uses **synthetic** dates concentrated in
2026–2027 (academic terms like 2026-09-01→2026-12-18, 2027-01-05→2027-04-30),
with person `date_of_birth` values from ~1980–2005 as stand-ins for real records.

### 1.2 Conclusion: existing data does not pin the historical lower bound

Because the system is greenfield for operational *data* (no migrated production
ledger is present in this tree), the date-bearing schemas alone do **not**
establish a historical lower bound for automated Shamsi derivation. The binding
constraints are:
1. **Fixed month structure is authoritative only from ~1957 (SH 1336)** onward
   (Encyclopaedia Iranica; see F4-A.2 §A.2). Dates before 1 Hamal 1336 (~21 Mar
   1957) used variable-length months and are **not** representable by a fixed
   arithmetic structure.
2. **Forward operation** must cover current + planned academic/financial/payroll
   periods and the ratified reporting horizon.
3. **Historical backfill** of DOB / legacy certificates / Islamic-Republic-era
   records (2001–2021) is a real but unbounded-by-data requirement.

### 1.3 Proposed range (owner decision — not self-ratified)

Following the F4-A rule that the range must be evidence-based and any
proposal be flagged as an owner decision:

| Range | SH years | Gregorian (approx) | Basis / purpose |
|---|---|---|---|
| **Active operational window** (the series that must be ratified and fully verified now) | **1399–1415** | **2020-03-20 → 2037** | recent history (2020 founding-era → today 2026) plus ~11 years forward academic/financial/payroll scheduling and reporting |
| **Full supported automated range** | **1336–1425** | **1957 → ~2047** | all fixed-structure dates that the Calendar Authority may ever be asked to derive automatically (legacy DOB, contracts, certificates, forward horizon). Equinox series to be pinned from an authoritative ephemeris at ratification time for the portion beyond the fully-tabulated §4 window |
| **Explicitly out-of-automated-range** | < 1336 (pre-1957) | before 1957 | variable-length-month era → **fail-closed** / manual, human-verified treatment (never auto-derived) |

**Owner decision D1 (required):** accept these bounds (or set others). The
active window 1399–1415 is the recommended ratification scope; the full
supported range is the recommended capability scope with a strict
fail-closed rule below 1336.

---

## 2. Authoritative calendar definition (recap — established in F4-A/F4-A.2)

- **Calendar:** Afghan civil Solar Hijri (Hejrah-e Shamsi / Jalali), Dari month
  names (Hamal … Hut); equinox-observed, **no closed arithmetic leap formula is
  authoritative** (Wikipedia Solar Hijri; Iranica).
- **Epoch:** the Hijra; SH year ≈ Gregorian − 621 for post-Nowruz dates.
- **Months / structure (standardised ~1957):** months 1–6 = 31 days, 7–11 = 30
  days, month 12 (Hut/Hoot) = 29 (common) or 30 (leap). Leap year = 366 days.
- **New Year:** Nowruz = 1 Hamal, at the vernal equinox.
- **Civil-day rule (the governing rule being ratified):** if the equinox occurs
  **before local noon** (at the chosen reference clock) that Gregorian day is
  1 Hamal; if **after local noon**, that day is the last of the old year (Hut)
  and the following day is 1 Hamal. (Documented for the Persian/Solar-Hijri
  rule by Wikipedia/timeanddate; see F4-A.2 §C and §3 below.)
- **Equivalence Afghanistan⇄Iran:** same solar system; identical Nowruz, leap,
  and day-count structure; differ only in month labels (Iranica; F4-A.2 §B).
  This equivalence holds for **civil-day purposes only when the two countries'
  reference clocks place the equinox on the same civil day**, which §4 shows to
  be true for 1399–1407 & 1409–1414 and false for 1408 — the crux.

---

## 3. Reference-meridian / time-zone / noon-cutoff resolution

### 3.1 What the evidence does and does not establish

**Established (authoritative):**
- The Solar-Hijri civil-day rule is the noon cutoff (equinox before/after local
  noon determines whether the equinox day or the next day is Nowruz)
  (Wikipedia Solar Hijri; timeanddate; Iranian official practice; the classical
  Tusi rule). The rule is expressed in the Iranian case as "before/after noon,
  **Tehran time**" (Wikipedia).
- Equivalence between the Afghan and Iranian solar systems is documented
  (Iranica), and for the currently operative years the Afghan civil calendar
  coincides with the Iranian one.
- No **Afghan-government** primary source was found that **decrees a specific
  reference meridian/time-zone/noon clock** for applying the cutoff to the
  Afghan civil Solar Hijri calendar (the Islamic-Republic-era official Afghan
  calendar and the 2022-reverted lunar-Hijri administration both lack an
  accessible published civil-Solar-Hijri cutoff decree). Afghan-specific
  algorithm references (nongnu afghancalendar) state the year begins at the
  equinox / "1 Hammal = 21 March (20 in leap years)" but do not publish a
  Kabul-noon cutoff decree.

**Consequence:** the equivalence argument is strong enough to show the two
candidate references agree for the operative past, but **not** strong enough to
say "always equal," because §4 exhibits a genuine divergence year (1408).
Per the phase rule ("do not silently choose one"), neither Kabul nor Tehran is
selected here; both are presented and the exact owner ratification stated (§9,
decision D2).

### 3.2 The candidate references and why they can differ

| Candidate | Clock | Noon cutoff applied at | Effect on civil Nowruz |
|---|---|---|---|
| **A. Kabul (AFT, UTC+04:30)** — recommended for an Afghan institution | Kabul local standard time | equinox civil day by Kabul clock | matches the institution's operating location and the "Afghan Solar Hijri" authority label |
| **B. Tehran (UTC+03:30)** — the codified Iranian rule | Tehran local standard time | equinox civil day by Tehran clock | strict day-for-day parity with Iranian official calendars / most arithmetic libraries |

Because Kabul is 60 minutes ahead of Tehran, any equinox instant whose UTC time
falls such that it is before 12:00 Tehran but after 12:00 Kabul (i.e. an equinox
between 08:00 and 08:30 UTC on 20 March) makes A and B land on different civil
Nowruz days. The next such case is the equinox of 2029-03-20 at 08:02 UTC
→ 11:32 Tehran (before noon → Nowruz 20 Mar) vs 12:32 Kabul (after noon → Nowruz
21 Mar). See §4/§5.

### 3.3 Owner ratification required (D2)

The owner must ratify **which civil rule the ERP's Calendar Authority encodes**:
(A) Kabul noon cutoff (recommended) or (B) Tehran noon cutoff, OR "ratify a
hybrid: use the equinox day shared by both references where they agree (always
in the operative window) and defer/flag any divergence year pending a further
decree." Until D2 is recorded, the civil-day series for the SH 1408 boundary is
indeterminate and the phase is BLOCKED.

---

## 4. Authoritative annual equinox + civil Nowruz series (1399–1414)

### 4.1 Source of the astronomical equinox instants

Vernal-equinox UTC instants below are taken from the **timeanddate** equinox
tables (cross-checked against equinoxworld / Farmers' Almanac). timeanddate is
used as a single authoritative astronomical reference; the instants agree
across all consulted sources to the minute and are not in dispute.

| Equinox UTC | → SH year it begins | Equinox (UTC) | Local civil Nowruz — **Kabul (A)** | Local civil Nowruz — **Tehran (B)** | A = B ? |
|---|---|---|---|---|---|
| 2020-03-20 | 1399 | 03:49 | 2020-03-20 | 2020-03-20 | yes |
| 2021-03-20 | 1400 | 09:37 | 2021-03-21 | 2021-03-21 | yes |
| 2022-03-20 | 1401 | 15:33 | 2022-03-21 | 2022-03-21 | yes |
| 2023-03-20 | 1402 | 21:24 | 2023-03-21 | 2023-03-21 | yes |
| 2024-03-20 | 1403 | 03:06 | 2024-03-20 | 2024-03-20 | yes |
| 2025-03-20 | 1404 | 09:01 | 2025-03-21 | 2025-03-21 | yes |
| 2026-03-20 | 1405 | 14:46 | 2026-03-21 | 2026-03-21 | yes |
| 2027-03-20 | 1406 | 20:24 | 2027-03-21 | 2027-03-21 | yes |
| 2028-03-20 | 1407 | 02:17 | 2028-03-20 | 2028-03-20 | yes |
| **2029-03-20** | **1408** | **08:02** | **2029-03-21** (12:32 Kabul = after noon) | **2029-03-20** (11:32 Tehran = before noon) | **NO** |
| 2030-03-20 | 1409 | 13:51 | 2030-03-21 | 2030-03-21 | yes |
| 2031-03-20 | 1410 | 19:41 | 2031-03-21 | 2031-03-21 | yes |
| 2032-03-20 | 1411 | 01:21 | 2032-03-20 | 2032-03-20 | yes |
| 2033-03-20 | 1412 | 07:22 | 2033-03-20 | 2033-03-20 | yes |
| 2034-03-20 | 1413 | 13:17 | 2034-03-21 | 2034-03-21 | yes |
| 2035-03-20 | 1414 | 19:02 | 2035-03-21 | 2035-03-21 | yes |

Notes on the civil-day transform (do not confuse equinox instant with civil day):
- The "equinox UTC" column is the astronomical instant; the "civil Nowruz"
  columns apply the noon-cutoff rule at the stated reference clock.
- 1404 (Nowruz 2025): equinox 09:01 UTC → 12:31 Tehran / 13:31 Kabul, both
  **after noon** → civil Nowruz **21 Mar 2025** (the earlier "20 Mar" figures in
  some sites label the equinox day and are wrong for the civil calendar — see
  F4-A.2 §C).
- 1406 (Nowruz 2027): equinox 20:24 UTC → 23:54 Tehran (20th) / 00:54 Kabul
  (21st). Under both references Nowruz = **21 Mar 2027** (Tehran: equinox after
  noon on the 20th ⇒ the 21st; Kabul: equinox 00:54 on the 21st before noon ⇒
  the 21st).
- 1408 is the unique divergence within the window (see §4.2).

### 4.2 Resulting annual series under each candidate reference

Leap status follows the series (year length = 366 ⇒ leap; Hut = 30).
**Kabul reference (A) — recommended:**

| SH | 1 Hamal (Nowruz) | length | leap? | Hut days | 1 Hamal next (first day after Hut) |
|---|---|---|---|---|---|
| 1399 | 2020-03-20 | 366 | leap | 30 | 2021-03-21 |
| 1400 | 2021-03-21 | 365 | no | 29 | 2022-03-21 |
| 1401 | 2022-03-21 | 365 | no | 29 | 2023-03-21 |
| 1402 | 2023-03-21 | 365 | no | 29 | 2024-03-20 |
| 1403 | 2024-03-20 | 366 | leap | 30 | 2025-03-21 |
| 1404 | 2025-03-21 | 365 | no | 29 | 2026-03-21 |
| 1405 | 2026-03-21 | 365 | no | 29 | 2027-03-21 |
| 1406 | 2027-03-21 | 365 | no | 29 | 2028-03-20 |
| **1407** | **2028-03-20** | **366** | **leap** | **30** | **2029-03-21** |
| **1408** | **2029-03-21** | **365** | **no** | **29** | **2030-03-21** |
| 1409 | 2030-03-21 | 365 | no | 29 | 2031-03-21 |
| 1410 | 2031-03-21 | 365 | no | 29 | 2032-03-20 |
| 1411 | 2032-03-20 | 365 | no | 29 | 2033-03-20 |
| 1412 | 2033-03-20 | 366 | leap | 30 | 2034-03-21 |
| 1413 | 2034-03-21 | 365 | no | 29 | 2035-03-21 |
| 1414 | 2035-03-21 | 365 | no | 29 | 2036-03-20* |

*2036 equinox pinned from an authoritative ephemeris at ratification time (not
in the §4.1 table); used only to close 1414's length.

**Tehran reference (B):** identical to (A) above for every row **except** 1407
and 1408, which swap:

| SH | 1 Hamal (Nowruz) | length | leap? | Hut days | 1 Hamal next |
|---|---|---|---|---|---|
| 1407 | 2028-03-20 | 365 | no | 29 | 2029-03-20 |
| 1408 | 2029-03-20 | 366 | leap | 30 | 2030-03-21 |

**Independent cross-check:** the Tehran/arithmetic pattern (leaps 1399, 1403,
1408, 1412 …) matches the published 33-year-cycle leap markers in the reference
table used by F4-A.2 (kiddle Solar-Hijri year-span table marks `1399*`, `1403*`,
`1408*`, `1412*`). The Kabul reference (leaps 1399, 1403, **1407**, 1412) is
the astronomically consistent result of applying the same noon-cutoff rule at
Kabul AFT. Both are internally reproducible; they differ only at the 1408
boundary, which is the owner decision.

**Authority / verification status of this series:**
- Equinox instants: **authoritative** (multi-source astronomical agreement).
- Civil-day transform: **deterministic given a ratified reference** (D2).
- Series arithmetic (year lengths, Hut days, leap flags): **derived, fully
  reproducible** by the fixed structure once the civil Nowruz days are fixed;
  cross-checked against known anchors (R6: 2026-09-02 = 11 Sunbula 1405).
- **Not yet RATIFIED:** the civil Nowruz days are conditional on D2. Once D2 is
  recorded this table becomes the authoritative version-1 series.

---

## 5. 1403–1408 boundary verification (equinox instant vs civil Nowruz day)

For each required boundary, the astronomical instant, the civil-day
transformation, and the resulting calendar edge are given. Month first-days are
computed from the civil Nowruz day using the fixed structure
(months 1–6 = 31, 7–11 = 30, Hut = 29/30). This is arithmetic *derivation* from
a ratified reference — not fabrication, and not code in the ERP.

### 5.1 1403 → 1404 (year boundary 2025)
- Equinox 2025-03-20 09:01 UTC. Kabul local 13:31, Tehran local 12:31 → both
  after noon.
- Civil: **1 Hamal 1403 = 2024-03-20**, Hut 1403 has 30 days (leap), last day of
  Hut = **2025-03-20**, and **1 Hamal 1404 = 2025-03-21** (both references agree).
- 20/21-March transition correctly places Nowruz 1404 on the 21st (equinox-day
  listings that say the 20th are wrong for the civil calendar).

### 5.2 1404 → 1405 (year boundary 2026)
- Equinox 2026-03-20 14:46 UTC. Kabul 19:16, Tehran 18:16 → after noon both.
- Civil: **1 Hamal 1404 = 2025-03-21**, 1404 common (Hut 29), last Hut =
  2026-03-20, **1 Hamal 1405 = 2026-03-21** (both references agree).

### 5.3 1405 → 1406 (year boundary 2027)
- Equinox 2027-03-20 20:24 UTC. Tehran 23:54 (20th), Kabul 00:54 (21st).
- Civil: **1 Hamal 1405 = 2026-03-21**, last Hut = 2027-03-20,
  **1 Hamal 1406 = 2027-03-21** (both references agree — Tehran: equinox after
  noon on 20th; Kabul: equinox early on 21st, before noon).

### 5.4 1406 → 1407 (year boundary 2028)
- Equinox 2028-03-20 02:17 UTC. Kabul 06:47, Tehran 05:47 → before noon both.
- Civil: **1 Hamal 1406 = 2027-03-21**, last Hut = 2028-03-19 (Hut 29),
  **1 Hamal 1407 = 2028-03-20** (both references agree).

### 5.5 1407 → 1408 (year boundary 2029) — the divergence
- Equinox 2029-03-20 08:02 UTC. **Tehran 11:32 (before noon); Kabul 12:32
  (after noon).**
- Kabul reference: 1 Hamal 1407 = 2028-03-20, **1407 leap** (Hut 30, last Hut =
  2029-03-20), **1 Hamal 1408 = 2029-03-21**, 1408 common.
- Tehran reference: 1 Hamal 1407 = 2028-03-20, **1407 common** (Hut 29, last Hut
  = 2029-03-19), **1 Hamal 1408 = 2029-03-20**, 1408 leap.
- This is the **formal classification** of the earlier 20/21-March concern: it
  is a genuine **owner-ratification issue (D2)**, not a discoverable fact,
  because the boundary year 2029 is in the future and no current authority
  publishes an Afghan Solar-Hijri civil series for it.

### 5.6 1408 → 1409 (year boundary 2030) — reference-conditional
- Equinox 2030-03-20 13:51 UTC. Kabul 18:21, Tehran 17:21 → after noon both.
- Civil 1 Hamal 1409 = 2030-03-21 under both references. Whether Hut 1408 was
  29 (Kabul: 1408 common, last Hut 2030-03-20) or 30 (Tehran: 1408 leap, last
  Hut 2030-03-20) — note the *last day of Hut 1408 is 2030-03-20 in both* — and
  1 Hamal 1409 = 2030-03-21 in both.

### 5.7 Representative month first-days (derived, provisional on D2)

Computed from the civil Nowruz day under the **Kabul (A)** reference (identical
to Tehran (B) for all years 1403–1406; 1407/1408 differ only in Hut length and
the 1408 year-start as shown):

- **1403** (leap): 1 Hamal 2024-03-20, Sawr 04-20, Jawza 05-21, Saratan 06-21,
  Asad 07-22, Sunbula 08-22, Mizan 09-22, Aqrab 10-22, Qaws 11-21, Jadi 12-21,
  Dalwa 2025-01-20, Hut 02-19 (30 days, last 03-20).
- **1404** (common): 1 Hamal 2025-03-21, Sawr 04-21, Jawza 05-22, Saratan 06-22,
  Asad 07-23, Sunbula 08-23, Mizan 09-23, Aqrab 10-23, Qaws 11-22, Jadi 12-22,
  Dalwa 2026-01-21, Hut 02-20 (29 days, last 03-20).
- **1405** (common): 1 Hamal 2026-03-21, Sawr 04-21, Jawza 05-22, Saratan 06-22,
  Asad 07-23, Sunbula 08-23, Mizan 09-23, Aqrab 10-23, Qaws 11-22, Jadi 12-22,
  Dalwa 2027-01-21, Hut 02-20 (29, last 03-20).
- **1406** (common): 1 Hamal 2027-03-21, … Dalwa 2028-01-21, Hut 02-20 (29,
  last 03-19).
- **1407**: 1 Hamal 2028-03-20 … (Kabul: Hut 30, last 2029-03-20; Tehran: Hut
  29, last 2029-03-19) — **reference-dependent**, future from today.
- **1408**: 1 Hamal Kabul 2029-03-21 / Tehran 2029-03-20 — **reference-dependent**.

**Anchor cross-check:** 1 Sunbula 1405 = 2026-08-23; day 11 = 2026-09-02 and day
12 = 2026-09-03 (today). This reproduces F4-A R6 (2026-09-02 ≈ 11 Sunbula 1405),
confirming the structure arithmetic used here.

---

## 6. Candidate-implementation verification (conceptual; no code run)

Candidates are evaluated **only as implementations** of the ratified civil
series — none may become the source of truth by itself (option D from F4-A.2).

| Candidate | Basis | Agreement with ratifiable series | Disposition |
|---|---|---|---|
| Arithmetic 33-year cycle (common Jalali) | fixed leap pattern | Matches the **Tehran**-reference civil days & leap set (1399,1403,1408,1412…) within 1399–1414; **mismatches the Kabul** reference at 1407/1408 (it encodes 1408-leap / 1407-common, i.e. Tehran) | acceptable only if the owner ratifies reference B; **rejected** as sole authority regardless — it is an approximation of the equinox rule and can drift in other decades |
| Proposed 2820-year cycle (Birashk) | arithmetic long-cycle | **Never officially adopted** (F4-A); not guaranteed to match either reference | rejected as authority; at most an internal probe |
| ICU / Intl "persian" calendar; arithmetic Persian/Jalali libs | algorithmic Persian | Follow the **Tehran/arithmetic** civil-day convention; within 1399–1414 they match the Tehran reference rows | acceptable **only as an implementation** of reference B within its validated range; not authoritative |
| Astronomical / equinox-observed (the governing rule) | ephemeris + noon cutoff at a stated reference | Is the **source of the series itself** (§4) | the authoritative basis; a versioned per-year civil series derived from it governs (option D) |

**Conclusion:** no single library is authoritative. The governing element is the
ratified **annual equinox/leap series** (option D). Any arithmetic/ICU candidate
is usable only within a range where it reproduces that ratified series
everywhere, including boundary and round-trip tests; a candidate that disagrees
(the arithmetic set disagrees with the Kabul reference at 1407/1408) is rejected
for that purpose or restricted to a clearly documented non-authoritative /
reference-B role.

---

## 7. Authoritative test-vector specification (machine-readable, doc-only)

Format below is a stable key/JSON-able table to be carried into the future
Calendar Authority's conformance suite. Every vector carries **source/derivation**:
`EQUINOX` = from the §4.1 astronomical instant + ratified noon rule;
`DERIVED` = fixed-structure arithmetic from an EQUINOX vector; `ATTESTED` =
corroborated by an independent source; `K/T` = reference-conditional.

Because the vectors must be unambiguous **after** D2, and 1408 is
reference-dependent, vectors referencing 1408 are marked `REQ-D2` and are
completed only once the owner chooses A or B.

| id | Gregorian | SH | type | basis | source | status |
|---|---|---|---|---|---|---|
| T01 | 2020-03-20 | 1 Hamal 1399 | year-start / leap | EQUINOX | §4.1 | ATTESTED |
| T02 | 2021-03-21 | 1 Hamal 1400 | year-start | EQUINOX | §4.1 | ATTESTED |
| T03 | 2021-03-20 | 30 Hut 1399 | last day of leap | DERIVED | §4.2 | derived |
| T04 | 2024-03-20 | 1 Hamal 1403 | year-start / leap | EQUINOX | §4.1 | ATTESTED |
| T05 | 2024-03-20 → 2025-03-20 | Hut 1403 last = 2025-03-20 | leap Hut boundary | DERIVED | §5.7 | derived |
| T06 | 2025-03-21 | 1 Hamal 1404 | year-start | EQUINOX | §4.1 | ATTESTED (R3 resolved) |
| T07 | 2025-03-20 | 30 Hut 1403 | day before Nowruz | DERIVED | §5.1 | derived |
| T08 | 2026-03-21 | 1 Hamal 1405 | year-start | EQUINOX | §4.1 | ATTESTED (R4 resolved) |
| T09 | 2026-09-02 | 11 Sunbula 1405 | ordinary / drift | DERIVED/ATTESTED | §5.7, F4-A R6 | ATTESTED |
| T10 | 2027-03-21 | 1 Hamal 1406 | year-start | EQUINOX | §4.1 | derived |
| T11 | 2028-03-20 | 1 Hamal 1407 | year-start | EQUINOX | §4.1 | derived |
| T12 | 2029-03-20 / 2029-03-21 | 1 Hamal 1408 | year-start | EQUINOX + REQ-D2 | §4.2, §5.5 | REQ-D2 (A: 21 Mar; B: 20 Mar) |
| T13 | 2030-03-21 | 1 Hamal 1409 | year-start | EQUINOX | §4.1 | derived |
| T14 | month first/last for each of 12 months in 1404 & 1405 | (set) | monthly boundaries | DERIVED | §5.7 | derived |
| T15 | 2026-01-01 | 11 Jadi 1404 | cross-year ordinary | DERIVED | structure | derived |
| T16 | 2026-01-01 & 2026-12-31 | 1404/1405 membership | reporting-year edges | DERIVED | structure | derived |
| T17 | pre-1957 Afghan civil date (e.g. 1950) | n/a (variable months) | historical | NOT-representable | F4-A.2 | fail-closed, manual |

Round-trip invariants (mandatory for any candidate, **necessary not
sufficient**): `gregorian→shamsi→gregorian == original` and
`shamsi→gregorian→shamsi == original`, holding across ordinary, month/year/
leap boundaries and around Nowruz over the whole supported range — plus full
match on the authoritative vectors above (a self-consistent wrong table
round-trips, so vectors, not round-trip alone, certify a candidate). **No
round-trip run was performed in F4-A.3** because no reference is ratified and no
code is authorized.

> **Vector correction — T03 (2026-09-03, F4-C reconciliation).** T03's Solar
> Hijri cell is corrected from "29 Hut 1399" to **"30 Hut 1399"**. The ratified
> Kabul series (§4.2) fixes SH 1399 as a **leap** year with a **30-day Hut**; its
> final civil day is 2021-03-20, which is therefore **30 Hut 1399** (the day
> before 1 Hamal 1400 = 2021-03-21). The earlier "29 Hut" reading was
> self-inconsistent: a 29-day Hut would make 1399 a *common* year, contradicting
> the ratified leap series. Consequently 29 Hut 1399 = 2021-03-19 (penultimate
> day), and the F4-C Calendar Authority + conformance suite assert
> 30 Hut 1399 = 2021-03-20. The T03 `source` is also corrected from §5.7 to
> §4.2, because §5.7 tabulates only SH 1403–1408 and does not list SH 1399.

---

## 8. Future Calendar Authority contract (specified, NOT implemented)

Per WP2-DEC-04 (G2), the Calendar Authority is the single consumer-facing
conversion authority. Contract (design only; no code):

**Input:** one canonical Gregorian date/time.
**Output:** authoritative Solar Hijri date (year, month, day, Dari labels) and a
`business_period_id` (e.g. SH fiscal year/month or academic term) where
applicable.
**Reverse:** a Solar Hijri (year, month, day) resolves to the exact canonical
Gregorian date (or the exact canonical [start, end] interval of the period).
**Guarantees:**
- **Deterministic:** pure function of (canonical date, calendar-version).
- **Versioned:** every conversion is pinned to a ratified series version; past
  snapshots record the version in force (reproducible history).
- **Authoritative:** a version encodes the ratified annual equinox/leap series
  (§4, after D2), not an unverified arithmetic formula as source of truth.
- **Fail-closed:** any date outside the supported range (e.g. < 1336) returns an
  explicit unsupported result — **no silent fallback / approximation**.
- **No module-specific conversion logic:** Finance, Payroll, Academic terms,
  Reporting, Fees/installments, due dates, dashboards, and any future
  business-period consumer must call this one authority; no module stores a
  second date truth.
- **Range math on canonical Gregorian:** DB predicates/proration stay on the
  stored Gregorian date; Shamsi is derived labelling/business identity.

---

## 9. Owner decision gate — outcome and exact residual asks

> **Status note (2026-09-03, F4-A.4):** the classification and residual asks in
> this §9 reflect the **pre-ratification** state of F4-A.3 (what the spec needed
> from the owner). The owner has since ratified **D1–D4** (recorded in §12 and in
> `WP-2-F4A-calendar-authority-verification.md` §F4-A.4 / the approved-decisions
> record), so the F4-A gate is now **`VERIFIED`**. §9 is preserved verbatim for
> audit; treat §12 as the current state.

### Classification (at time of F4-A.3, pre-ratification): **BLOCKED** (`F4-A BLOCKED PENDING AUTHORITATIVE CALENDAR VERIFICATION`)

Per the F4-A.3 rule, VERIFIED requires, among others, that the
**reference meridian/cutoff is established** and the **annual series is
sufficiently authoritative**. Neither is self-ratifiable here: no
Afghan-government primary source was found that decrees the noon-cutoff
reference clock for the Afghan civil Solar Hijri calendar, and the current
(2022-reverted lunar-Hijri) administration publishes no Solar-Hijri civil
series; the divergence at SH 1408 (2029) is therefore unresolved by evidence.

**What remains unresolved:** the civil-Nowruz noon-cutoff reference (Kabul AFT
vs Tehran vs hybrid) for the Solar Hijri calendar the ERP encodes; consequently
the SH 1407/1408 leap-and-boundary detail (§5.5) and the exact ratified
version-1 annual series are not yet fixed.

**What source was sought (and its insufficiency):**
- Afghan Constitution Art. 18 (solar basis for state offices) — authoritative
  on *which* calendar, silent on the noon-cutoff reference clock.
- Encyclopaedia Iranica / US DOJ-EOIR / Transparent Dari — authoritative on
  Afghan⇄Iranian equivalence and month names; silent on a Kabul-vs-Tehran
  civil cutoff decree.
- Wikipedia/timeanddate (Persian/Solar-Hijri rule) — authoritative on the noon
  rule itself, but expressed for the Iranian case as "Tehran time"; no Afghan
  primary restatement found.
- Afghan-specific algorithm references (nongnu afghancalendar) — state the year
  begins at the equinox and give a simplified "21 March / 20 in leap years"
  mnemonic; do not publish an Afghan civil-noon cutoff decree or an official
  per-day almanac.
- No accessible **Afghan-government-published per-day** Solar-Hijri civil
  almanac exists for the operative/future window, and the 2029 divergence year
  is in the future, so no almanac can decide it.

**Why existing evidence is insufficient:** the equivalence evidence proves the
two candidate references agree across all operative past years but *demonstrably
diverges at 1408* (§4, §5.5). Equivalence alone cannot pick the civil rule for
that year; per the phase rule I do not silently choose Kabul or Tehran.

**Exact owner decisions / evidence needed to flip to `F4-A VERIFIED`:**
1. **D1 — operational range:** ratify the §1.3 range (active window 1399–1415;
   full supported 1336–1425; pre-1336 out-of-range/fail-closed), or set bounds.
2. **D2 — reference rule (the blocker):** ratify (A) Kabul AFT noon cutoff
   (recommended), or (B) Tehran noon cutoff, or a hybrid/other formally defined
   mechanism, **with the boundary rule stated** (equinox before/after local
   noon at the chosen clock ⇒ civil Nowruz).
3. **D3 — series adoption:** ratify the resulting version-1 annual series
   (the §4.2 table for the chosen reference) as the Calendar Authority's
   version-1 equinox/leap series, and the source/version of the astronomical
   equinox data used to pin it (timeanddate table + an authoritative ephemeris
   for the post-2035 tail and the 1336–1398 range).
4. **D4 — vector acceptance:** confirm the §7 vector spec (including R3/R4 as
   resolved to 21 Mar 2025 / 21 Mar 2026 under either reference) as the
   conformance baseline; accept reference-dependent rows T12 (1408) once D2 is
   fixed.

Once D1–D4 are recorded, F4-A.3's spec becomes the ratified version-1 series and
the F4-A gate can be re-run to `VERIFIED` (and candidate implementations
verified against §7 vectors). Until then: **F4-A BLOCKED … F4 IMPLEMENTATION
PENDING.** F4 production implementation must not proceed.

---

## 10. Scope / git discipline confirmation

- **Files changed (documentation/specification only):** this record
  (`WP-2-F4A.3-solar-hijri-reference-series-ratification.md`).
- No production code, calendar service, conversion code, library dependency,
  migration, financial/payroll/academic date logic, dashboard, or DB date-column
  change was made. F1/F2/F3/S1 and the approved G2 (WP2-DEC-04) decision are
  untouched. The pre-existing `core/calendar/periods.ts` legacy reference is
  **not** in this tree and was not modified or replaced.
- Working tree was verified clean of unintended changes before commit; only the
  F4-A.3 documentation file is committed.

## 11. Sources referenced

timeanddate — equinox UTC table & varying-March-equinox page (UTC instants
2020–2035) · equinoxworld / Farmers' Almanac (corroboration) · Wikipedia —
Solar Hijri calendar & Nowruz (noon-cutoff civil rule; "formerly in
Afghanistan") · Encyclopaedia Iranica — Calendars (Afghan⇄Iranian equivalence;
~1957 standardisation) · US DOJ/EOIR — Afghanistan calendar research (official
since 1957) · nongnu.org afghancalendar (Afghan months/holidays; simplified
Nowruz rule) · Afghan Constitution (2004) Art. 18 (solar basis; via U. Minnesota
HRL and nongnu) · mtempmail Afghan converter (year-span anchors) ·
F4-A `WP-2-F4A-calendar-authority-verification.md` and F4-A.2 addendum (accepted
prior findings R1–R7 and the ratification path).

---

## 12. Owner ratification — F4-A.4 (D1–D4 recorded)

**Date:** 2026-09-03 · Documentation only; no code.

The architecture owner formally ratified the four decisions this record framed,
thereby resolving the §9 residual authority gap and flipping the F4-A gate from
`BLOCKED` to `VERIFIED`. The ratified decisions are recorded authoritatively in
`docs/architecture/decisions/WP2-approved-decisions.md` (F4-A appendix) and
`docs/implementation/WP-2-F4A-calendar-authority-verification.md` (§F4-A.4);
this section records their effect on this specification.

- **D1 — Operational range (ratified).** Active operational window SH
  1399–1415 (~2020–2037); full supported deterministic range SH 1336–1425
  (1957 fixed-structure → ~2047); pre-1336 dates fail closed / require manual
  handling; no fabrication of historical calendar or branch/date provenance
  beyond verified evidence.
- **D2 — Reference civil clock (ratified).** Kabul local civil time,
  **AFT = UTC+04:30**, is the TOEFL House Calendar Authority reference clock;
  the documented noon-cutoff rule is applied against Kabul civil time. This is
  an explicit TOEFL House **product/architecture decision**, **not** a claim
  that a currently published Afghan government source mandates this exact
  computational rule. The distinction between the astronomical equinox instant
  and the resulting civil first day of Hamal is preserved. The **1408
  divergence** (§5.5) remains explicitly documented as the reason D2 matters.
- **D3 — Reference-series authority (ratified).** The annual
  equinox/reference series documented above is ratified as the **version-1
  reference dataset** and is authoritative for supported dates. Any arithmetic
  algorithm is only an implementation mechanism and **must** be validated
  against the ratified reference series. The 33-year, 2820-year, ICU/Persian
  arithmetic, or another generic Jalali implementation is **not** the sole
  authority.
- **D4 — Acceptance vectors (ratified).** Vectors **T01–T17** (§7) are the
  initial acceptance/test-vector set, with provenance tags preserved
  (EQUINOX / DERIVED / ATTESTED / REQ-D2). Implementation must satisfy the
  vectors **plus** the round-trip invariants (§7). Under ratified D2 the
  reference-dependent rows are fixed to the Kabul (A) branch: T12 = 1 Hamal
  1408 = **2029-03-21**; the 1407 leap / 1408-common detail (§4.2, §5.5, reference
  A) is the version-1 series. REQ-D2 vectors are thereby resolved (they remain
  provenance-tagged DERIVED/EQUINOX for audit but are no longer ambiguous).

**Series/version reproducibility:** the version-1 dataset = the §4.1
astronomical equinox UTC instants (timeanddate; cross-checked) transformed to
civil Nowruz by the noon-cutoff rule at Kabul AFT (D2), yielding the §4.2
Kabul-reference table and the §5.7 month-boundary structure. Future F4
implementations and historical audits must reproduce against this exact
dataset + Kabul-clock rule.

**Final F4-A classification (after D1–D4):** `F4-A VERIFIED`. F4 production
implementation remains **pending** and is a separate, later phase; this record
authorizes nothing beyond the calendar-authority verification. WP2-DEC-04 / G2
is unchanged.

---

## 13. Ratification addendum — active-window completion anchor (2026-09-04)

The architecture owner formally ratified the remaining version-1 anchor needed to
complete the D1 active operational window SH 1399–1415:

- **Anchor:** `1 Hamal 1416 = 2037-03-20`
- **Astronomical input:** 2037 vernal equinox = **2037-03-20 06:50 UTC**
  (authoritative equinox table, time-and-date and equinox-equivalent sources,
  cross-checked at minute level).
- **Kabul noon-cutoff transform (D2):** 06:50 UTC = 11:20 AFT, which is **before**
  Kabul civil noon (12:00 AFT = 07:30 UTC), so `2037-03-20` is the civil first
  day of Hamal 1416.
- **Effect on the version-1 series:** `Version1Series` now carries ratified
  anchors **1399–1416** and therefore fully serves **SH 1399–1415** (the D1
  active operational window). The **supported deterministic range remains**
  **SH 1336–1425** unchanged.
- **Explicit non-extension:** this ratification authorizes the 1416 anchor only.
  The 1416–1425 tail and the 1336–1398 tail remain **not ratified** and must
  continue to fail closed (`calendar.year_not_ratified`) until separately
  pinned and ratified under the F4-B §2.5 rule.
- **Scope discipline:** no other F4 decision, G2 storage contract, or Calendar
  Authority boundary is changed.

---

## HISTORICAL SOURCE: `implementation/WP-2-F4B-calendar-authority-implementation-design.md`

# WP-2 F4-B — Calendar Authority Implementation Design (design/spec, no code)

**Status:** `F4-B DESIGN COMPLETE` (design/spec only).
**Overall WP-2 status line:** `WP-2 PARTIAL — F1/F2/F3/S1 COMPLETE, F4-A VERIFIED, F4-B DESIGN COMPLETE, F4 IMPLEMENTATION PENDING`.
**Basis:** WP2-DEC-04 / G2 (approved); F4-A record; F4-A.3 reference-series spec;
F4-A.4 owner ratification D1–D4 (commit `9bc8cec`); S1 governed_configs (commit
`2c92857`); existing date-bearing domains.
**Branch:** `arena/01a062e3-toefl-house`
**Date:** 2026-09-03

This artifact is a **design/specification only**. It makes **no** code, migration,
model, service, config, seeder, or schema change. It does **not** start F4
implementation. Any reference to "the implementation" below describes a future,
later, separately-gated phase that must still be authorized.

---

## 1. Governing inputs (re-read summary)

- **WP2-DEC-04 (G2):** Shamsi-first business semantics; single canonical
  Gregorian/ISO stored date; authoritative, immutable, versioned Shamsi
  derivation; conversion is a pure function of `(canonical date, calendar-algorithm
  version)`; no dual stored date truths; DB range arithmetic and proration run on
  the canonical date; snapshots store canonical date + version for reproducibility;
  calendar scheme is org-wide (not branch-scoped).
- **F4-A / F4-A.2 / F4-A.3 / F4-A.4:** the calendar is the Afghan civil Solar
  Hijri (Hejrah-e Shamsi), Dari month names, equinox-observed, fixed
  31/30/30/29-30 structure, epoch the Hijra; noon-cutoff civil-day rule; version-1
  reference series (F4-A.3 §4) with vectors T01–T17 (F4-A.3 §7); ratified by
  **D1–D4** (Kabul AFT UTC+04:30 civil clock; supported range SH 1336–1425;
  active window SH 1399–1415; version-1 series authority; arithmetic only a
  validated implementation mechanism, never sole authority).
- **S1 / governed_configs:** an existing typed, versioned, audited, append-only,
  effective-window governed registry (Governance module, `governed_configs` +
  `governed_config_definitions`). This is the natural mechanism on which the
  calendar version pointer and the ratified-series ratification should rest —
  consistent with G2 ("calendar scheme" is a hard-coded invariant; the ratified
  algorithm/table *version* is a governed config record).
- **Existing date-bearing domains:** audited in §6 (canonical Gregorian date and
  `*_at` timestamp columns only; no Shamsi column exists).

### Non-negotiables carried into the design
1. Kabul AFT (UTC+04:30) is the **reference civil clock for the noon-cutoff rule
   only** — it is a ratified TOEFL House product decision (D2), **not** an
   external Afghan legal/astronomical authority, and **not** the server local
   timezone.
2. The **astronomical equinox instant** (a UTC point in time) is distinct from the
   **civil first day of Hamal** (a Kabul-clock calendar day). Only the latter
   drives calendar math. Equinox instants are preserved as informational pinned
   data for audit, never used as the civil-day source.
3. The **1408 divergence** is the documented reason D2 matters; the version-1
   series encodes the Kabul branch (1 Hamal 1408 = 2029-03-21).

---

## 2. Calendar Authority contract

### 2.1 Canonical (stored) representation
- **Civil business date:** a **Gregorian civil date** (`DATE`, proleptic
  Gregorian: year–month–day, no time, no zone). This is the **single stored
  representation** for every business date in the ERP. No Shamsi column is ever
  added (G2). All DB range predicates and proration run on this column.
- **Instant/timestamp:** events that need a time-of-day store a **timestamp**
  carried in **UTC** (a `timestamptz` stored and normalized to UTC), never a
  timezone-less "local" timestamp and never the server local timezone. The
  Calendar Authority uses instants only to derive a Kabul civil day when
  required (e.g., an event's civil day under the Kabul clock), and only via the
  explicit Kabul AFT mapping — never the process timezone.
- **Internal solar Hijri date:** an immutable value object `SHDate{year, month,
  day}` used transiently for computation/validation, never persisted as a second
  truth.

### 2.2 Solar Hijri business representation
- `SHDate(y, m, d)`: `y` ∈ **1336–1425** (supported deterministic range);
  `m` ∈ 1..12 in Dari order (1 Hamal … 12 Hut); `d` ∈ 1..`daysIn(Hut? month)`.
  Month lengths fixed: 1–6 = 31, 7–11 = 30, 12 (Hut) = 29/30 (leap-dependent).
- Period identity: a SH **year** and **month** (and the financial/payroll/academic
  period keyed on them) derive only from the civil Nowruz day of the ratified
  series.

### 2.3 Range & window (D1)
- **Supported deterministic range:** SH **1336–1425** (Gregorian ~1957–~2047).
  Any date mapping outside this range returns an explicit **out-of-range** result
  (fail-closed).
- **Active operational window:** SH **1399–1415** — the window fully covered by
  the ratified F4-A.3 series/vectors; must be 100% vector-and-round-trip covered.
- Pre-1957 / pre-1336 civil dates (variable-length months) are **out of the
  automated range**: fail-closed, require manual human-verified treatment; never
  auto-derived.

### 2.4 Reference civil clock, noon-cutoff rule, and Nowruz
- **Reference civil clock:** Kabul AFT = UTC+04:30 (ratified D2; product
  decision, not an external mandate).
- **Noon-cutoff rule:** for the Gregorian day containing the vernal equinox, if
  the equinox instant is **before Kabul civil noon (12:00 AFT)** that day is
  **1 Hamal**; if **at/after Kabul noon**, that day is the last day of the prior
  SH year (Hut) and **the following day is 1 Hamal**.
- **1 Hamal per year is pinned by the ratified version-1 series** (§2.5). The
  noon rule is the *semantics*; the series is the *governing data* (so no year is
  computed from a bare equinox UTC table at runtime without going through the
  ratified series).

### 2.5 Version-1 reference series (D3/D4)
- The **version-1 dataset** = the F4-A.3 §4.1 equinox UTC instants transformed by
  the Kabul noon-cutoff rule (D2) into the §4.2 Kabul-reference Nowruz days, plus
  the derived year lengths / leap flags / Hut lengths and the §5.7 month-boundary
  structure. Vectors **T01–T17** (F4-A.3 §7) are the initial acceptance set;
  REQ-D2 rows resolve to the Kabul branch (T12 = 1 Hamal 1408 = **2029-03-21**).
- The series is authoritative **for supported dates**. For the 1336–1398 and
  1415–1425 tail not fully tabulated in F4-A.3, the implementation phase must pin
  equinox instants from an authoritative ephemeris at build time, transform them
  by the same Kabul rule, extend the vector set accordingly, and ratify that
  extension as part of version-1 before the authority may serve those years.

### 2.6 Forward conversion (canonical Gregorian → SHDate)
- Input: a Gregorian civil date `G` (year/month/day) within supported Gregorian
  range corresponding to SH 1336–1425.
- Rule: locate the SH year `Y` whose civil Nowruz day `N(Y) ≤ G < N(Y+1)`;
  `Y` = year whose `N(Y)` is the greatest pinned Nowruz ≤ `G`. Then compute
  `(m,d)` by stepping month first-days from `N(Y)` using fixed month lengths.
- Edge: a Gregorian day `G` that equals a Nowruz day belongs to that SH year's
  1 Hamal. `G` strictly before the earliest pinned Nowruz in the supported range
  or at/after the last → out-of-range/fail-closed.

### 2.7 Reverse conversion (SHDate → canonical Gregorian)
- Input: valid `SHDate(y,m,d)`.
- Rule: take pinned `N(y)`; add month lengths of months `< m` to get the first
  day of month `m`; add `d−1`. Output the resulting Gregorian civil date.
- Bijective with forward within the supported range (guaranteed by the no-gap,
  no-overlap series).

### 2.8 Month/day validation and leap determination
- `validateDate(SHDate)`: month in 1..12; day in 1..daysIn(year,month); year in
  1336..1425. Reject (explicit invalid) otherwise.
- **Leap determination:** `leap(Y) ⟺ N(Y+1) − N(Y) = 366` days (from the series).
  `hutDays(Y) = 30` iff leap else 29. **No arithmetic formula** is ever the
  source of truth; an arithmetic algorithm may be used only after it is shown to
  reproduce the series for every year it serves (validated mechanism, §3/§4).

### 2.9 Year/month boundary semantics
- Year `Y` = interval `[N(Y), N(Y+1))` in canonical days.
- Month `m` of year `Y` = from its first day up to but not including the first
  day of month `m+1`; month 12 runs from its first day up to `N(Y+1)`.
- A financial/payroll/academic **period keyed on a SH month** therefore maps to an
  exact canonical `[start, end)` interval, never an approximate Gregorian month.

### 2.10 Date arithmetic semantics
- All arithmetic is done **on canonical Gregorian dates** (G2) — e.g., add N days,
  month/year boundaries, proration day counts. The result is then re-labelled to
  SH via the authority. SH is never used as an arithmetic base; only as an
  identity/period label and for validation.
- Business-day / proration semantics that a consumer needs are defined once here
  (calendar days unless a consumer explicitly defines working-days on top), so no
  module re-invents "a month" or "a day."

### 2.11 Historical reproducibility
- A conversion result is reproducible iff both inputs are retained:
  `(canonical date, calendar version id)`. Every record/snapshot that displays a
  Shamsi label stores (or can recover) the canonical date **and** the calendar
  version id in force. Re-running `forward(G, version)` always yields the same
  SHDate because each version pins its series immutably.
- No stored second date; no in-place series edits (a revision is a new version).

### 2.12 Fail-closed and invalid/ambiguous input behavior
- Outside supported range (SH < 1336, > 1425; Gregorian outside the
  corresponding civil span): explicit **out-of-range** outcome — no silent
  fallback/approximation.
- Invalid SH (month 0/13, day 0/too-large, non-integer): explicit **invalid**
  outcome.
- Ambiguous/non-applicable (e.g., a time-of-day question outside civil-date
  semantics): explicit domain error; no default assumption.
- Any consumer that cannot resolve a governed value/date **must fail closed**, not
  guess (consistent with S1 fail-closed-on-absence).

---

## 3. Authority precedence (explicit)

```
1. RATIFIED REFERENCE SERIES (authoritative)  — the governing truth for civil
   year start (1 Hamal), Nowruz day, leap, Hut length, and hence all civil dates
   within SH 1336–1425. Versioned; each version immutable.
   ↓ (validates / bounds)
2. VALIDATED DETERMINISTIC ALGORITHM  — an implementation mechanism that may
   compute dates, but ONLY within the range in which it has been shown to
   reproduce the ratified series for every year (per-year and every boundary),
   and is re-validated whenever a new series version is ratified. It never
   overrides the series.
   ↓ (never authoritative by itself)
3. CONVENIENCE / LIBRARY CONVERSION  — ICU/Intl "persian", generic Jalali
   (33-year / 2820-year / jalaali-js-style), online converters, etc. Never the
   business authority. If used at all, it must be gated behind the validated
   mechanism (2) and must reproduce the ratified series (1) within the served
   range; otherwise it is disallowed for authoritative civil math.
```

No generic Jalali/Persian library may silently become the business authority.

---

## 4. Implementation-strategy decision (analyzed, not assumed)

Three candidate strategies for the production implementation:

**A. Explicit versioned annual reference-series dataset only (table-driven).**
Every civil year boundary is stored and consulted directly (e.g., ~90 Nowruz rows
for 1336–1425), with forward/reverse by table lookup + month stepping.
- Pros: maximum fidelity to the ratified series; trivially auditable; no risk of
  an arithmetic drift; simplest to prove correctness (vector = row).
- Cons: ~90–180 pinned rows/vectors to maintain per version (though small and
  stable); range math still needs month-length logic; a pure table is slightly
  more code surface for the day arithmetic; future long-horizon extensions are
  manual table additions.

**B. Deterministic algorithm validated against the dataset.**
An arithmetic/astronomical algorithm computes dates; the series is used as the
validation oracle only.
- Pros: compact; covers any in-range year without new rows; conventional.
- Cons: the ratified leap rule is equinox-observed and **not** captured exactly by
  any closed arithmetic formula; a generic algorithm (33-year, 2820-year, ICU
  Persian) disagrees with the **Kabul** reference at 1407/1408 (F4-A.3 §6) and can
  drift in other decades. To be correct it would have to be an equinox ephemeris
  routine pinned to the same source as the series — effectively reproducing (A)
  plus an ephemeris dependency, i.e. complexity without a fidelity gain over the
  series itself.

**C. Hybrid (recommended):** deterministic conversion computed **for the ratified
supported range** by month-boundary arithmetic anchored on the **versioned annual
reference series** (i.e., the series supplies every Nowruz/leap anchor; a simple,
deterministic month/day stepper computes within-year dates), with the series kept
as the **governing validation authority** and the whole conversion **versioned**
for historical reproducibility.
- Effectively: the civil *anchors* come straight from the ratified series (A's
  fidelity), while the *within-year* arithmetic is a tiny, provably-correct
  stepper (B's compactness) — but there is **no closed-form year formula**; leap
  and Nowruz always come from the series row. Arithmetic/ICU (3) are admitted
  only as cross-checked, non-authoritative implementations.

**Justification for C (not assumed — argued):**
1. **Correctness:** the series is the only thing ratified (D3). Making every year
   start (1 Hamal) and leap status resolve to a series row, rather than to any
   formula, eliminates the exact class of error (arithmetic drift; the
   1407/1408 Kabul/Tehran divergence) that F4-A found blocking. The month/day
   stepper between two pinned anchors is trivial to prove and test.
2. **Authority clarity:** precedence (1) > (2) > (3) is structurally enforced
   because civil anchors can only come from (1).
3. **Reproducibility:** versioning the series (and only recomputing anchors on a
   governed revision) keeps history byte-stable; past reports resolve identically.
4. **Cost/scale:** ~90–180 immutable rows per version is trivial to store and
   audit and removes an ephemeris/astronomy runtime dependency for the supported
   range.
5. **Rejected pure forms:** (A) alone over-weights storage and makes the 
   within-year stepper less obviously centralized; (B) alone reintroduces the
   unratified-formula risk the whole F4-A process rejected.

**Decision recorded for the implementation phase (design-level; still gated):**
**option C — hybrid**, implemented as a versioned anchor series (authoritative)
+ deterministic within-range conversion stepper (validated), with arithmetic/ICU
only as non-authoritative cross-checks. This does **not** authorize code; it
fixes the design the future F4 implementation must follow.

---

## 5. Data model (design level only)

### 5.1 Calendar version/config record — required
Yes. G2 requires conversion to be a pure function of `(date, calendar version)`,
and S1 provides the governed, versioned, audited mechanism. Design:
- The **calendar scheme** (`shamsi_kabul_aft`) remains a **hard-coded invariant**
  (not configurable), per S1 (calendar scheme is hard-coded).
- The **active/ratified calendar series version** is represented as a governed
  value (via the existing `governed_configs` mechanism or an equivalent
  `calendar_scheme_versions` append-only record), so activation is an audited,
  authorized, effective-dated event and past behavior pins to the version in
  force on the date.

### 5.2 Where annual reference data lives
Design recommendation: the annual reference anchors live in **immutable,
versioned application data committed with the module** (a small versioned dataset
of `{year, nowruz_gregorian, equinox_utc_instant, leap}` rows for 1336–1425),
**not** as mutable per-day DB rows and **not** as a mutable free-form table:
- The dataset is small (~90–180 rows), deterministic, and changes only by
  governed ratification as a **new version**.
- A governed pointer record references which version is active/ratified and when;
  Audit records every ratification (consistent with S1 append-only + audit).
- Rationale: DB-per-day rows for 90 years would duplicate the immutable dataset,
  invite drift, and add index/consistency cost for no fidelity gain; mutable DB
  data would weaken immutability. Committed immutable data + governed version
  pointer is the single-source-of-truth, auditable option.

### 5.3 Future calendar revisions versioned
Each governed revision is a **new version**: the new immutable anchor dataset +
vectors are ratified (effective-dated), the pointer is moved, and the prior
version remains intact for historical reproduction. No in-place edits ever.

### 5.4 Historical-record reproducibility
Because conversion = pure `(canonical date, version)`, an existing record needs no
second date. Records/snapshots that render Shamsi labels must capture the
**calendar version id in force** alongside the canonical date (added only in the
implementation/migration phase, per G2 snapshot rule). This is the only
data-shape change foreseen and is deferred to implementation (not made here).

### 5.5 No schema change now
None of the above is created in this step. It is the target design.

---

## 6. Existing date-bearing domain audit (migration impact; no change made)

Classification legend — treatment: **N** = no schema change;
**CA** = Calendar Authority integration (single conversion point);
**D** = derived Shamsi display; **PA** = period abstraction (business period →
authoritative SH period → exact Gregorian bounds); **M/B** = migration/backfill
(future); **HV** = explicit historical calendar version (reproducibility).

| Domain / structure | Field(s) | Type | Classification | Treatment |
|---|---|---|---|---|
| `people` | `date_of_birth` | date | DOB/identity date | **CA**, **D**, **N**. Birth dates before SH 1336 (pre-1957) → out-of-range/manual (HV). |
| `academic_periods` | `starts_on`, `ends_on` | date | academic period | **PA**, **D**, **N** (term keys map to SH terms via CA). |
| `class_sessions` | `scheduled_on` (+ `starts_at`,`ends_at` time) | date + time | canonical civil date (session day) + time-of-day | **CA**, **D**, **N** (`scheduled_on` is the civil day; times are zone-less clock times within that Kabul day). |
| `financial_periods` | `date_from`,`date_to` | date | business-period (financial) | **PA**, **D**, **N**. |
| `payroll_periods` | `date_from`,`date_to` | date | payroll period | **PA**, **D**, **N**. Proration stays on canonical dates. |
| `obligations` | `due_on` | date | deadline/due date | **CA**, **D**, **N** (Shamsi due label derived; store stays Gregorian). |
| `payments` | `received_on` | date | canonical civil date (receipt) | **CA**, **D**, **N**. |
| `contracts` | `effective_from`,`effective_to` | date | business-period / effective window | **CA**, **D**, **N**. |
| `contract_versions` | `effective_from`,`effective_to`, `submitted_at`,`approved_at` | date / datetime | effective window + timestamps | windows → **CA**,**D**; timestamps → **N** (UTC). |
| `leaves` | `date_from`,`date_to` | date | business window (leave) | **CA**, **D**, **N**. |
| `certificates` | `issued_on` | date | certificate date | **CA**, **D**, **HV** (certificate Shamsi label reproducibility needs version id at issuance). |
| `attendance_facts` | `attended_on`-style day (if present) | date | canonical civil date | **CA**, **D**, **N**. |
| `audit_events` | `occurred_at`,`created_at` | timestamp | audit/event timestamp | **N** (UTC only; no calendar). |
| `*_at` columns (verified/approved/computed/etc.) | datetime | audit/event timestamp | **N** (UTC). |
| `*_on` columns generally (assigned/issued/due/etc.) | date | canonical civil date | **CA**, **D**, **N**. |
| governed configs / grants / statuses / enrollments effective windows | `effective_from`,`effective_to` | date | governed/effective window | **N** (window math on canonical Gregorian). |

Cross-cutting findings:
- **No stored Shamsi column exists** and none should be added (G2); all calendar
  integration is via the single authority (CA).
- **No module currently performs Shamsi conversion**, so there is no
  module-specific conversion logic to migrate; the risk is future modules doing ad
  hoc conversion, which the single-authority boundary (§7) prevents.
- Financial/academic/payroll **period proration and open/close logic already run
  on canonical dates** (G2), so amounts/proration are unaffected; only labels and
  period keys acquire SH semantics via CA.
- **Not made now:** any M/B or HV column additions are deferred to the F4
  implementation/migration phase.

---

## 7. API / service boundary (authoritative operations)

Proposed as a single bounded authority module (design-level names; a future
implementation may adjust if a better shape exists). All operations are pure
functions of `(input, calendar-version)` and fail closed as in §2.12.

| Operation | Contract (signature semantics) | Notes |
|---|---|---|
| `forward(gregorianDate, version) → SHDate` | canonical Gregorian civil date → Solar Hijri date | §2.6 |
| `reverse(shDate, version) → gregorianDate` | valid SH date → canonical Gregorian civil date | §2.7 (bijective in range) |
| `getYearInfo(year, version)` | leap?, Nowruz (1 Hamal) Gregorian day, hutDays, equinox UTC instant (informational), period spans | leap/Nowruz from series only |
| `getMonthInfo(year, month, version)` | first day (SH + Gregorian), length, last day | §2.9 |
| `periodBoundaries(shYear) / (shYear, shMonth)` | exact canonical `[start, end)` of a SH business period | for finance/payroll/academic |
| `compare(a, b)` | SH or Gregorian date ordering / equality | canonical-day based |
| `addDays(date, n)` / `businessArithmetic` | canonical-day arithmetic; results re-labelled | §2.10; must not cross supported range without explicit failure |
| `currentBusinessDate(version)` | "today" as the authoritative SH date | resolves an instant to the Kabul civil day (§2.1) — never server-local; requires an explicit clock policy |
| `validateDate(shDate)` / `validate(gregorianDate)` | validity + in-range check | §2.8 |
| `supportedRange(version)` | returns the SH + Gregorian supported bounds | for consumers/fail-closed |

**Boundary rules:**
- The authority is the **only** place a Gregorian→SH or SH→Gregorian conversion
  exists; no module implements its own stepper.
- Storage stays canonical Gregorian; SH is produced/consumed only through the
  authority (labels, period identity, validation).
- `currentBusinessDate` is explicit about the Kabul AFT civil-clock policy and
  must not read the server timezone.
- Consumers needing SH display or period mapping call the authority; DB range
  queries remain on the canonical date column.

---

## 8. Test architecture (design only; not executed)

A future F4 conformance suite (mirroring existing Feature/Unit layout), grouped so
the exact §2 semantics and precedence are enforced:

1. **Ratified-vector conformance (T01–T17)** — each F4-A.3 §7 vector asserted via
   the authority (forward and reverse where applicable), preserving provenance
   tags; T12 = 1 Hamal 1408 = 2029-03-21 (Kabul branch).
2. **Round-trip** — `forward(reverse(s))==s` and `reverse(forward(g))==g` across
   ordinary dates, month boundaries, year boundaries, leap boundaries, Nowruz.
3. **Month-boundary** — first/last day of every month (T14) for 1403–1408 and the
   extended ratified years; last day of Hut vs first day of Hamal adjacency.
4. **Leap-year** — leap set from the series (1399,1403,1407,1412 under Kabul);
   Hut=30 vs 29; no arithmetic assumption.
5. **20/21 March transitions** — 1404 (Nowruz 2025-03-21), 1405 (2026-03-21), and
   each near-noon year.
6. **1408 Kabul-specific divergence test** — asserts 1 Hamal 1408 = 2029-03-21 and
   that a hypothetical Tehran-noon value (2029-03-20) is NOT produced; guards
   against regression to an arithmetic/Tehran library.
7. **Supported-range boundaries** — SH 1336 and 1425 edges; the 1336 anchor and
   the 1425 tail.
8. **Out-of-range fail-closed** — SH 1335 and 1426, and pre-1957 Gregorian civil
   dates, return explicit out-of-range (no fallback).
9. **Invalid-date** — month 0/13, day 0/too-large, non-integer → explicit invalid.
10. **Timezone/DST invariants** — conversion is invariant to the server/process
    timezone; `currentBusinessDate` uses the Kabul AFT civil clock only; no
    implicit Tehran timezone; instants → Kabul civil day mapping tested.
11. **Historical reproducibility** — same `(canonical date, version)` always
    yields the same SH date; a later version does not change an earlier record's
    pinned result (old version retained).
12. **Authority-precedence guard** — a deliberately-wrong arithmetic/ICU-style
    computation (e.g., the 1407/1408 Tehran pattern) must be rejected/mismatched;
    arithmetic may never override a series row.

(Not executed now — this is the required shape for the F4 implementation test
phase.)

---

## 9. Explicit prevention list

- **No dual stored calendar truths** — SH is never persisted as a second column
  (G2); conversion is derived via the authority.
- **No approximate Gregorian-month mapping** — a SH period never maps to an
  approximate Gregorian month; only exact `[start, end)` intervals via the series.
- **No server local timezone as business authority** — the noon-cutoff and civil
  day use the Kabul AFT reference clock; the process timezone is irrelevant and
  never read for calendar math.
- **No silent fallback to system/library Jalali** — no ICU/Intl "persian",
  33-year, 2820-year, or generic Jalali is used as an authoritative fallback; §3
  precedence enforced.
- **No implicit Tehran timezone** — the Iranian civil-clock convention is never
  assumed; the Kabul branch is explicit (1408 test guards this).
- **No unversioned calendar behavior** — every conversion is versioned; no
  anonymous/global conversion exists.
- **No silent range crossing** — date arithmetic that would step outside the
  supported range fails explicitly rather than approximating.

---

## 10. Scope / git discipline confirmation

- **Files changed (design/specification only):** this record
  (`WP-2-F4B-calendar-authority-implementation-design.md`).
- No migration, model, service, production PHP code, config change, seeder, or
  schema change was made. WP2-DEC-04 / G2, S1, and the ratified F4-A series are
  untouched.
- Read-only checks performed: review of G2 decision, F4-A/F4-A.3/F4-A.4 records,
  date-bearing schema columns, period models/casts, Governance (governed_configs)
  mechanism, and the academic/financial/payroll structures (§1, §6). No data or
  config was written.
- Working tree was clean before this commit; only this file is committed.

## 11. Final status

`WP-2 PARTIAL — F1/F2/F3/S1 COMPLETE, F4-A VERIFIED, F4-B DESIGN COMPLETE, F4 IMPLEMENTATION PENDING`

F4 implementation has **not** started and remains pending a separate, later,
authorized phase that must follow this design (option C hybrid), the ratified
version-1 series, and the F4-A.3 vector set.

---

## HISTORICAL SOURCE: `implementation/WP-2-F4C-calendar-authority-implementation-verification.md`

# WP-2 F4-C — Calendar Authority Implementation Verification (independent review)

**Status:** `F4-C VERIFIED`

The version-1 Calendar Authority now covers the entire approved active
operational window **SH 1399–1415** (Gregorian `2020-03-20` → `2037-03-20`
exclusive), with the supported deterministic range **SH 1336–1425** unchanged.
The owner-ratified completion anchor `1 Hamal 1416 = 2037-03-20` is incorporated
as a version-1 anchor, and every available canonical quality/conformance gate is
green for the verified scope.

**Scope:** `app/Modules/Calendar` (CalendarAuthority + Domain value objects +
version-1 series) and the conformance/quality tests touched by the F4-C
verification: `tests/Unit/Calendar/CalendarAuthorityTest.php`,
`tests/Feature/Governance/GovernedConfigFoundationTest.php`,
`tests/Feature/Organization/StructureLifecycleFeatureTest.php`.

**Branch:** `arena/01a0677c-toefl-house`
**Date:** 2026-09-04
**Reviewer:** independent agent (no authorship of the module code).

---

## 1. Ratification incorporated

On 2026-09-04 the architecture owner ratified **`1 Hamal 1416 = 2037-03-20`**
as a version-1 Calendar Authority anchor, completing the D1 active operational
window SH 1399–1415. Formal reference:

- `docs/architecture/decisions/WP2-approved-decisions.md` — F4-C
  active-window completion ratification (2026-09-04).
- `docs/implementation/WP-2-F4A.3-solar-hijri-reference-series-ratification.md`
  — §13 ratification addendum (anchor, equinox input, Kabul transform, explicit
  non-extension of the 1416–1425 / 1336–1398 tails).

Astronomical input: 2037 vernal equinox `2037-03-20 06:50 UTC` (authoritative
equinox table, minute-level cross-check). Kabul transform under D2: 06:50 UTC =
11:20 AFT, before Kabul civil noon (12:00 AFT = 07:30 UTC), so `2037-03-20` is
the civil first day of Hamal 1416.

---

## 2. What was verified and how

The repository was verified independently (not by merely re-running the existing
unit test): a separate exhaustive harness loads the **actual**
`App\Modules\Calendar\*` production files and exercises the real
`CalendarAuthority` class. The canonical project environment was rebuilt via
`docs/environment/P02-environment-recovery.sh --recover`
(`ENVIRONMENT VALID`; PHP 8.2.27, Composer 2.10.2, Laravel 12.67.0,
PostgreSQL 18.4, vendor/, phpunit, phpstan, pint) before the gates below.

### 2.1 Canonical Calendar unit suite

```sh
php vendor/bin/phpunit tests/Unit/Calendar/CalendarAuthorityTest.php
```

Result: **OK (21 tests, 177 assertions)**.

### 2.2 Independent exhaustive runtime verification

A standalone harness (`/home/user/cal_verify.php`, not committed) exercised the
actual `App\Modules\Calendar\CalendarAuthority` class and asserted:

- Every ratified reference vector in the served window, forward and reverse
  (Nowruz 1399–1415, including the 1407/1408 Kabul divergence).
- **Exhaustive round-trip of every one of the 6,209 canonical civil days** in
  the served interval `[2020-03-20, 2037-03-20)` — forward → reverse returns
  the original day.
- Month lengths/last days, year boundaries, leap flags and Hut lengths for every
  served year (1399–1415): handled leap years `1399, 1403, 1407, 1412`; every
  other served year common, including 1414 and 1415 (Hut 29; last served day
  `2037-03-19`).
- Range metadata: supported `[1336, 1425]`, served `[1399, 1415]`.
- Fail-closed behavior: SH 1398, 1416, 1417, 1425 → `calendar.year_not_ratified`;
  SH 1335, 1426 and pre-1957 Gregorian dates → `calendar.out_of_supported_range`;
  `2037-03-20` (1 Hamal 1416) is the exclusive served end and is rejected.
- Version reproducibility (`v1` only) and unknown-version rejection.
- Kabul civil-day mapping from a UTC instant (server timezone independent) and
  fixed `currentBusinessDate()` scheduling at Kabul civil day.
- `addDays` boundary guard (stepping off the last served day fails closed).

Result: **checks = 6,308, failures = 0** (the exhaustive 6,209-day loop is
6,209 of those checks).

### 2.3 Repository quality gates

```sh
php vendor/bin/phpunit
# OK, but there were issues!
# Tests: 629, Assertions: 4357, PHPUnit Deprecations: 1, Skipped: 2.  (exit 0)

php vendor/bin/phpstan analyse --memory-limit=1G
# [OK] No errors (284 files)

php vendor/bin/pint --test \
  app/Modules/Calendar/Domain/Version1Series.php \
  tests/Unit/Calendar/CalendarAuthorityTest.php \
  tests/Feature/Governance/GovernedConfigFoundationTest.php \
  tests/Feature/Organization/StructureLifecycleFeatureTest.php
# PASS (4 files)

php -l app/Modules/Calendar/Domain/Version1Series.php
php -l tests/Unit/Calendar/CalendarAuthorityTest.php
php -l tests/Feature/Governance/GovernedConfigFoundationTest.php
php -l tests/Feature/Organization/StructureLifecycleFeatureTest.php
# No syntax errors detected (4 files)
```

Repository-wide `pint --test` still reports **3 pre-existing, unrelated style
issues** in `deploy/windows/launcher_helper.php`, `e2e-journey.php`, and
`tests/Unit/Launcher/WindowsLauncherContractTest.php`. These are outside the
F4-C/Calendar scope and are not caused by or necessary for this verification;
the F4-C touched files are all Pint-clean.

### 2.4 Architecture-boundary checks

- No reference to `CalendarAuthority` / `Calendar\` exists outside the Calendar
  module and its tests.
- No Shamsi/Hijri column or calendar schema change exists; no generic
  Jalali/Persian library is present in `composer` metadata.
- Storage remains canonical Gregorian; Solar Hijri is derived only through the
  authority (G2 / WP2-DEC-04 respected).
- The supported deterministic range is unchanged at SH 1336–1425; no expansion
  beyond the approved active window 1399–1415 was introduced. The 1416–1425 and
  1336–1398 tails continue to fail closed (`calendar.year_not_ratified`).

---

## 3. Honest verdict

- **Within the ratified active operational window SH 1399–1415**, the F4-C
  Calendar Authority implementation is architecturally sound and functionally
  correct: independently executed against the real classes at 6,308 checks /
  0 failures, including exhaustive round-trip of all 6,209 served civil days,
  with the canonical Calendar unit suite green and the full repository suite
  green (629 tests / 4,357 assertions / exit 0).
- The previous blockers are resolved:
  - **Blocker 1 resolved:** the active operational window is now fully covered;
    the owner-ratified `N(1416) = 2037-03-20` anchor is in `Version1Series`, and
    the served range is SH 1399–1415.
  - **Blocker 2 resolved:** the order-dependent audit assertions are now
    deterministic (correlated by known `target_id` / compared as a canonical
    set), and the full repository suite is green.
- **Result: `F4-C VERIFIED`.** No F4-C blocker remains. The 3 unrelated
  repository-wide Pint issues are pre-existing, outside the verified F4-C scope,
  and are noted for the record rather than treated as F4-C blockers.
- **Post-verification addendum (2026-09-04):** the 3 repository-wide Pint issues
  were subsequently resolved (repo-wide Pint is now clean, 531 files), and the
  mission continues on the approved WP-2 F1/F2/F3 foundations. These do not
  change any F4-C conclusion.

---

## HISTORICAL SOURCE: `implementation/WP-2-S1-governed-config-registry.md`

# WP-2 S1 — Governed Configuration Registry (implementation record)

**WP2-DEC-05 (S1)** · Status: IMPLEMENTED (foundation) · Branch: `arena/01a062e3-toefl-house`

This record documents how the approved S1 architecture decision is implemented
so the code, schema, and tests match the architecture. It is a foundation: the
typed/versioned/audited registry and its fail-closed read path. Actual business
threshold numbers are **not** invented here — ratifying real values remains an
explicit governance decision.

## 1. Schema / model / domain design

Two tables plus DB triggers (migration `2026_09_03_000124`).

### `governed_config_definitions` — the governance boundary
`id (uuid PK) · config_key (unique) · config_type · title · ratified_by (FK
people) · timestamps`.
A governed configuration key exists only after it is **explicitly ratified**
with a fixed `config_type`. This is the anti-drift rule: a value becomes
governed only when an approved definition says so, so arbitrary keys/types are
never accepted and arbitrary existing constants are never auto-converted.
Definitions are append-only and immutable (DB trigger).

### `governed_configs` — typed, versioned, effective values
`id (uuid PK) · config_key (FK definitions) · config_type · version_no ·
value (jsonb) · effective_from · effective_to (nullable) · supersedes_id (self
FK) · lifecycle_state ('active'|'ended') · review_cycle · approved_by (FK
people) · timestamps`.

- **Typed, not free-form.** Each value is a typed envelope `{"v": <scalar>}`.
  `config_type` (one of `nonnegative_money`, `positive_money`,
  `nonnegative_integer`, `positive_integer`, `percent`, `approver_reference`)
  determines the scalar's shape and constraints, validated both in the domain
  (`App\Modules\Governance\Domain\GovernedConfigType`) and again in a DB
  trigger. Money is whole minor units; percent is 0..100; approver_reference is
  a non-empty person identifier (used by approval-routing configuration).
- **Versioning / effective dating.** Version rows are appended, never
  rewritten. A row covers the half-open window `[effective_from, effective_to)`;
  a NULL `effective_to` is the single OPEN (active) version running to the
  present/future. `version_no` is strictly monotonic per key. Exactly one OPEN
  version exists per key at a time; retiring the OPEN version into a finite
  window is the only permitted mutation and yields an immutable historical
  version.
- **Read resolution.** `GovernedConfigRegistry::effective(key, day)` returns the
  single authoritative version governing that day.

## 2. Governance & authorization design

- Writes require the `governance.config` capability resolved through the
  existing `AccessDecision`/`AccessResolution` authority model — no independent
  authorization model is introduced, and other authorities (e.g. access admin)
  cannot broaden into governed writes.
- Reads are broader: `GovernedConfigRegistry` is a pure, deterministic resolver
  used inside authorized operations.
- Every write is a governed, **audited** event recorded through the existing
  `AuditRecorder` into `audit_events` (actor, operation, target, before/after
  state, correlation id), and unauthorized attempts are recorded as
  `*.denied`.
- Writes are idempotent (`IdempotentExecution`), matching every other command.

## 3. Database invariants

Enforced at the Postgres boundary (behind the domain commands):
- `unique(config_key, version_no)` — no duplicate version numbers.
- `unique(config_key, effective_from)` — no duplicate effective starts.
- Partial unique index `governed_configs_one_open_per_key`
  (`WHERE lifecycle_state='active'`) — one OPEN version per key (single current
  authority), concurrency-safe.
- GiST exclusion `governed_configs_no_overlap` over
  `daterange(effective_from, COALESCE(effective_to,'infinity'), '[)')` for the
  same `config_key` — no two effective windows may overlap (requires `btree_gist`).
- Checks: `version_no >= 1`; lifecycle in `{active,ended}`; lifecycle/window
  consistency (`active` ⇔ `effective_to IS NULL`); window not inverted.
- `governed_configs_write_guard` trigger (BEFORE INSERT/UPDATE): the `config_key`
  must have a ratified definition whose `config_type` matches; the value envelope
  and its typed scalar satisfy the declared type's constraints; `version_no` is
  strictly monotonic.
- `governed_configs_immutability_guard` trigger (BEFORE UPDATE): only the exact
  active→ended retire of an OPEN version is permitted; value, type, window
  start, key, version, approver, and lineage can never be changed after write.
- `governed_configs_delete_guard` trigger (BEFORE DELETE): history is never
  deleted.
- `governed_config_definitions_append_only` trigger: definitions are immutable.
- FKs: `config_key → definitions`, `approved_by → people`,
  `supersedes_id → governed_configs(id)` (added after the table so the PK exists).

## 4. Fail-closed behavior

`GovernedConfigRegistry::effective()` never falls back to defaults, stale
values, environment variables, or unrelated constants. It throws
`BusinessRejection` with:
- `governance.config_undefined` — the key has no ratified definition;
- `governance.no_effective_version` — no version is effective on the requested day;
- `governance.ambiguous_authority` — defensive: more than one version would
  govern the day (DB constraints make this unreachable);
- `governance.invalid_stored_value` — defensive re-check of the stored typed value.

Write-side invalid input is rejected up front by `GovernedConfigType`
(`governance.invalid_value`, `governance.config_type_unknown`), and any attempt
to bypass it (invalid envelope, wrong type, out-of-range value, non-monotonic
version, overlapping window, mutation/deletion of history) is rejected by the
database triggers/constraints.

## 5. Governance boundary (what is never configurable)

`governed_configs` ratifies only thresholds/limits, routing, and review-cycle
values. It is **not** a mechanism for, and cannot hold, the hard-coded security
and financial invariants (separation of duties, default-deny, two-Owner rules,
financial immutability/cap rules, provenance immutability, calendar scheme) —
those remain hard-coded domain invariants and cannot be expressed through this
registry.

## 6. Focused tests

- `tests/Unit/Governance/GovernedConfigTypeTest.php` — pure typed/type validation.
- `tests/Feature/Governance/GovernedConfigFoundationTest.php` — behavior proofs:
  valid typed values; versioning; effective-date resolution; audit metadata;
  fail-closed missing/undefined/out-of-window; invalid typed & constraint
  values; DB invariants (one OPEN per key, non-overlapping windows, monotonic
  versioning, typed value guard, config_type match, immutability + no-delete);
  authorization (denied writes audited, access authority cannot broaden into
  governed writes).

## 7. Verification

- Focused S1: **9 tests / 70 assertions**, green.
- Full suite (see run record).
- PHPStan level 6: no errors. Pint: clean on S1 files.
- Migrations: applied on a fresh DB (`migrate:fresh` in the test suite) and as an
  upgrade on the established dev baseline.
