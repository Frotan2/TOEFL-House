# TOEFL House — Autonomous AI Engineering Execution Directive

**Status:** PROJECT-CANONICAL AI EXECUTION LAYER  
**Authority:** Operational directive for AI agents working in this repository  
**Relationship to architecture:** Complements and executes the existing `docs/MASTER_ENGINEERING_CONTRACT.md`; it does not replace approved ADRs, domain contracts, requirements, or other more-specific canonical decisions.  
**Primary objective:** Drive the repository toward its highest defensible production-grade state, not merely toward the state implied by current defects.

---

## 1. Read Before Acting

Any AI agent entering this repository for engineering work MUST first read:

1. `AGENTS.md`
2. `docs/MASTER_ENGINEERING_CONTRACT.md`
3. `docs/02-TARGET-ARCHITECTURE.md`
4. `docs/04-DATA-AUTHORITY-PROVENANCE.md`
5. `docs/05-SECURITY-RBAC-GOVERNANCE.md`
6. the domain-specific canonical documents relevant to the work
7. `docs/15-REQUIREMENT-TRACEABILITY.md`
8. `docs/16-DECISION-REGISTER.md`
9. `docs/17-FRONTEND-SURFACE-REGISTER.md`
10. current CI/verification definitions and the repository's executable verification tooling

Then inspect the actual repository. Documents describe intended and governed truth; code, schema, tests, CI and runtime evidence describe implementation reality. Neither may be blindly substituted for the other.

Do not rewrite, reorganize, duplicate, or create competing versions of existing canonical project documents merely because a new task is starting. Preserve the existing documentation hierarchy unless a contradiction or governance defect itself is confirmed.

---

## 2. Mission

The mission is to engineer TOEFL House into the strongest objectively defensible version of the product that can be derived from its approved purpose, current architecture, domain knowledge, security requirements, operational needs, user workflows, repository evidence, and verified engineering practice.

The mission is explicitly **not** limited to finding currently visible bugs.

The agent MUST actively discover and resolve, where justified:

- defects;
- missing capabilities;
- incomplete lifecycles;
- architectural weaknesses;
- authority duplication;
- incorrect abstractions;
- invalid assumptions;
- stale or superseded implementations;
- dead code and dead paths;
- schema weaknesses;
- migration hazards;
- concurrency hazards;
- security weaknesses;
- authorization and scope gaps;
- API/React divergence;
- poor failure and recovery semantics;
- observability gaps;
- performance pathologies;
- maintainability problems;
- unnecessary complexity;
- inconsistent UX and product workflows;
- accessibility failures;
- dependency/runtime drift;
- operational fragility;
- documentation/governance drift;
- test blind spots;
- unreproducible behavior;
- opportunities for principled modernization.

It MUST also identify areas that are already correct and must be preserved. Improvement is not synonymous with change.

---

## 3. Definition of the Highest Standard

The target state is not “the cleanest code imaginable” and not “the largest feature set.”

The target is a system whose architecture, behavior, data, security, user experience and operations form one coherent whole.

At the highest defensible level, the system should be:

- semantically correct;
- domain-coherent;
- single-authority by business concept;
- secure by construction;
- fail-closed where authorization or integrity is involved;
- database-enforced where invariants require durable enforcement;
- transactionally correct;
- concurrency-safe;
- idempotent where retries are possible;
- historically faithful;
- auditable and provenance-preserving;
- observable;
- recoverable;
- reproducible from a clean environment;
- upgrade-safe;
- maintainable by humans other than the author;
- explicit rather than magical at important boundaries;
- efficient without sacrificing correctness;
- accessible;
- operationally usable;
- visually and interactionally coherent;
- testable at the correct boundary;
- verifiable from executable evidence;
- extensible without creating competing authorities.

The agent MUST continuously distinguish between:

`good enough`  
`correct`  
`robust`  
`production-grade`  
`best defensible architecture for this product`

and must not silently equate one with another.

---

## 4. Architecture Target

Use the existing approved architecture as the starting point, not as an excuse to stop thinking.

The current canonical architecture is a Laravel modular monolith with PostgreSQL transactional persistence and a React/TypeScript feature frontend. The repository already defines server-authoritative business rules, explicit domain boundaries, command-based mutations, query/read boundaries, PostgreSQL invariants, and frontend-as-presentation principles.

The agent MUST determine whether the implementation actually achieves those principles.

Modernization may be performed when evidence shows a material architectural benefit, but technology changes MUST be justified by:

- measurable or demonstrable benefit;
- migration safety;
- operational feasibility;
- compatibility impact;
- maintenance impact;
- testability;
- security impact;
- failure/recovery implications;
- long-term ownership cost.

Do not replace a technology merely because another technology is fashionable.

Do not preserve a design merely because it is old.

Do not introduce microservices, event sourcing, CQRS, GraphQL, new frameworks, new databases, additional state-management systems, or other architectural fashions merely because they sound advanced. Use them only when the repository's actual problem warrants them and the resulting system is strictly more coherent.

---

## 5. Absolute Engineering Model

Every material business capability MUST be reasoned about through the complete chain:

`Purpose → Requirement → Business Rule → Canonical Authority → Aggregate/State → Database Invariants → Application Command → Authorization/Scope → Transaction → API Contract → UI Workflow → Audit/Provenance → Reporting/Projection → Recovery → Tests → Operational Verification`

Not every capability requires every layer, but every materially relevant layer MUST be accounted for.

A feature is not complete because a controller exists.

A screen is not complete because it renders.

An endpoint is not complete because it returns 200.

A migration is not complete because it applies to an empty database.

A test is not sufficient merely because it passes.

A module is not correct merely because it is internally consistent.

---

## 6. Authority and Truth

For every important concept, determine exactly one canonical authority.

The agent MUST build and maintain an internal authority map covering, as applicable:

- identity;
- capability and authorization;
- organization/campus/branch scope;
- people/students;
- academic lifecycle;
- placement;
- teachers and HR;
- finance and monetary truth;
- payroll proposals and settlements;
- CRM/front office;
- communication/work management;
- documents;
- resources/library;
- privacy;
- audit/evidence;
- reporting;
- integrations;
- calendar/time semantics;
- configuration/reference data.

Derived values, caches, projections, reporting models and UI state may exist, but they MUST remain explicitly non-authoritative.

If two implementations can independently make the same business decision, treat that as a potential architectural defect and investigate it.

If a frontend implementation can override a server-authoritative business rule, treat that as a material defect.

If an integration can silently become a write authority, treat that as a material defect.

---

## 7. Lifecycle Completeness

For every material lifecycle, identify:

- states;
- legal transitions;
- transition authority;
- preconditions;
- postconditions;
- actor/capability requirements;
- scope rules;
- side effects;
- audit requirements;
- idempotency semantics;
- concurrency behavior;
- correction/reversal behavior;
- historical behavior;
- failure behavior;
- recovery behavior;
- UI representation;
- API representation;
- reporting representation.

The agent MUST search for illegal implicit transitions, bypasses, direct state writes, duplicated transition logic and status fields that have become competing sources of truth.

---

## 8. Database as a Correctness Boundary

Treat PostgreSQL as an engineering authority for durable invariants, not merely storage.

Review, where applicable:

- primary and foreign keys;
- unique constraints;
- partial unique indexes;
- CHECK constraints;
- exclusion constraints;
- nullable semantics;
- generated/derived data;
- trigger behavior;
- transaction isolation;
- locking;
- deadlock ordering;
- cascade semantics;
- historical immutability;
- correction models;
- migration order;
- duplicate migration numbers;
- upgrade compatibility;
- rollback safety;
- fresh-install reproducibility;
- seed ordering;
- large-table migration safety;
- index/selectivity health;
- destructive-operation controls.

Do not rely on application validation for an invariant that must survive every write path when PostgreSQL can enforce it safely.

Do not put mutable business truth exclusively in triggers when a clearer domain boundary is required; use triggers for durable invariants and historical protection where they are the appropriate enforcement mechanism.

Every migration must be considered in at least three realities:

1. clean installation;
2. upgrade from the current supported state;
3. recovery/rollback implications.

---

## 9. Security and Access

Assume every boundary is adversarial until verified.

Audit:

- authentication;
- session security;
- capability checks;
- role/position semantics;
- delegation;
- organization/campus/branch/class/own scope;
- object-level authorization;
- IDOR/BOLA;
- privilege escalation;
- horizontal and vertical access violations;
- fail-closed behavior;
- separation of duties;
- sensitive-data exposure;
- destructive action protection;
- file/document access;
- rate limits;
- CSRF/session boundaries;
- audit trail integrity;
- error leakage;
- secret/configuration handling.

A UI restriction is never authorization.

A route restriction is never sufficient when the underlying command can be reached through another path.

The real authorization boundary is the canonical mutation authority.

---

## 10. Financial Integrity

Finance remains the sole monetary authority unless an explicitly approved architecture decision states otherwise.

The agent MUST preserve clear distinctions among:

`fact → obligation → allocation → payment → refund → adjustment → journal → reconciliation → settlement`

Never create a second monetary authority merely to simplify a workflow.

All monetary mutation paths MUST be reviewed for:

- correctness;
- transaction boundaries;
- locking;
- idempotency;
- over-allocation;
- over-refund;
- correction semantics;
- approval/SoD;
- auditability;
- historical preservation;
- concurrency;
- API/UI parity.

---

## 11. Frontend Standard

The frontend must be treated as a complete product, not as a collection of screens.

Review:

- information architecture;
- navigation model;
- workspace composition;
- role-aware visibility;
- API contract fidelity;
- form semantics;
- state transitions;
- stale data handling;
- optimistic/concurrent behavior;
- loading/empty/error/retry states;
- accessibility;
- keyboard operation;
- responsive behavior;
- visual consistency;
- design-system consistency;
- data density;
- search/filter/sort behavior;
- destructive-action confirmation;
- feedback and recovery;
- deep-linking and refresh behavior;
- asset resolution and deployment behavior;
- browser E2E behavior.

The frontend MUST NOT duplicate business truth.

Client-side validation may improve usability but MUST NOT weaken or replace server/domain validation.

Remove dead screens, dead routes, obsolete state pathways, duplicate components and stale client-side contracts when their obsolescence is proven.

---

## 12. Backend/API Standard

Controllers and routes are transport adapters.

Commands/services own governed mutations.

Queries/projections are read-side concerns.

The agent MUST inspect:

- validation boundaries;
- domain/application services;
- transaction boundaries;
- exception semantics;
- API resource contracts;
- pagination;
- filtering;
- authorization;
- scope propagation;
- idempotency;
- retry semantics;
- event dispatch;
- outbox behavior;
- job behavior;
- serialization;
- versioning;
- compatibility.

Thin transport is preferred where the domain already owns the rule.

Do not move business authority into controllers simply because the controller is convenient.

---

## 13. Integrations, Events and Reporting

Committed business facts are authoritative; integrations observe or transport them.

Review:

- transactional outbox semantics;
- exactly-once versus at-least-once assumptions;
- idempotent consumers;
- duplicate delivery;
- retry policy;
- replay;
- dead-letter handling;
- poison messages;
- ordering assumptions;
- consumer checkpoints;
- lineage;
- rebuildability;
- observability;
- manual recovery;
- projection correctness.

Reporting MUST NOT become a second business-authority layer.

A projection must be rebuildable from authoritative source facts or from an explicitly governed event history.

---

## 14. Code Quality and Refactoring

Refactoring is a means of reducing semantic and operational risk, not an aesthetic exercise.

The agent SHOULD improve:

- cohesion;
- coupling;
- naming;
- dependency direction;
- duplication;
- dead code;
- oversized classes;
- hidden side effects;
- invalid abstractions;
- inconsistent error handling;
- repetitive authorization logic;
- accidental complexity;
- testability;
- readability.

Refactor only when the resulting behavior is demonstrably equivalent or intentionally changed with an explicit rationale and adequate verification.

Do not perform massive cosmetic rewrites that create review noise without increasing system quality.

---

## 15. Performance and Scale

Evaluate both current behavior and plausible growth.

Inspect:

- N+1 queries;
- unbounded result sets;
- missing pagination;
- expensive joins;
- repeated authorization queries;
- cache correctness;
- serialization cost;
- queue throughput;
- lock contention;
- hot rows;
- large migrations;
- expensive frontend rendering;
- oversized bundles;
- unnecessary network calls.

Optimize measured or structurally obvious bottlenecks without sacrificing correctness, auditability or security.

---

## 16. Observability and Operations

A production system must be diagnosable when something goes wrong.

Review:

- structured logs;
- useful error context;
- health checks;
- readiness/liveness semantics;
- queue/job visibility;
- failure alerts;
- correlation/trace identifiers where justified;
- audit evidence;
- deployment verification;
- migration observability;
- backup/restore procedures;
- recovery procedures;
- operational runbooks;
- rollback strategy.

A failure that cannot be diagnosed or recovered safely is a product defect even if the happy path works.

---

## 17. Testing Philosophy

Tests must verify behavior at the boundary where the rule is supposed to live.

Use the strongest applicable combination of:

- unit tests;
- feature tests;
- domain tests;
- database invariant tests;
- authorization tests;
- adversarial tests;
- concurrency tests;
- integration tests;
- API contract tests;
- frontend contract/parity tests;
- browser E2E tests;
- migration verification;
- static analysis;
- mutation/property testing where valuable.

Do not weaken production code to satisfy a broken test.

Do not weaken a test merely because production code currently fails it.

First determine which side is wrong.

A passing test suite is evidence, not proof of completeness.

---

## 18. Evidence Discipline

Every material conclusion MUST be evidence-backed.

Use these classifications:

- `CONFIRMED DEFECT`
- `CONFIRMED GAP`
- `CONFIRMED DUPLICATION`
- `CONFIRMED OBSOLETE`
- `FALSE POSITIVE`
- `DESIGN CHOICE`
- `RUNTIME UNVERIFIED`
- `BLOCKED`
- `FIXED`
- `VERIFIED`

Never silently convert `RUNTIME UNVERIFIED` into `VERIFIED`.

When runtime infrastructure is unavailable, distinguish static confidence from runtime confidence.

A tool limitation is not evidence that the system is correct.

A CI failure must remain open until its cause is understood or independently falsified.

---

## 19. Autonomous Decision Rights

The agent is the senior engineering authority for execution and MUST NOT require the user to dictate:

- task ordering;
- branch strategy;
- merge mechanics;
- refactoring sequence;
- test selection;
- architecture decomposition;
- implementation technique;
- cleanup strategy;
- verification depth.

The agent MUST make those decisions from evidence and project governance.

The agent MAY:

- create or discard branches;
- integrate or reject branch work;
- extract only the valuable part of a branch;
- supersede stale work;
- rewrite an implementation when necessary;
- refactor across files;
- remove dead code;
- add missing tests;
- add migrations/constraints;
- improve CI/verification tooling;
- improve UX and architecture;
- document material decisions.

However, autonomy is constrained by project authority and verification requirements. The agent is empowered to decide **how**, not to redefine the approved product's fundamental governance without evidence and appropriate decision recording.

Optimize for the final coherent system, not for maximizing merged commits or preserving authorship of earlier changes.

---

## 20. Parallel Engineering

Parallel work is encouraged when it reduces elapsed time without reducing independence or correctness.

Parallel specialists MAY independently audit:

- architecture;
- security/access;
- academic/people domains;
- finance/payroll;
- CRM/communication/work;
- documents/resources;
- reporting/integrations;
- privacy/audit;
- frontend/UX/accessibility;
- database/concurrency;
- DevSecOps/operations.

Their outputs MUST converge into one canonical finding set and one coherent architecture.

Never allow parallel agents to establish competing truths.

Independent challenge is valuable; independent authority is not.

---

## 21. Safe Integration of Parallel Work

For every branch/PR/change set, determine its semantic delta against the current integration baseline.

Classify each contribution as:

- integrate;
- partially integrate;
- superseded;
- duplicate;
- incompatible;
- unsafe;
- obsolete;
- needs redesign.

Prefer semantic convergence over mechanical merge count.

When two branches change the same concept, resolve the concept first and the files second.

Preserve provenance through appropriate commit/PR history and decision records.

Never merge a failing, unexplained or authority-conflicting change merely because it is available.

---

## 22. Cleanup Standard

After correctness and authority are stabilized, perform a repository-wide cleanup pass.

Look for:

- dead PHP/TS/JS;
- dead routes;
- dead exports;
- obsolete controllers/services;
- abandoned migrations;
- duplicate models;
- duplicate APIs;
- obsolete CSS;
- unused dependencies;
- stale test premises;
- legacy shims that no longer have a reason to exist;
- contradictory documentation.

Deletion requires evidence of obsolescence or duplication.

Preserve compatibility shims only when they have an explicit supported purpose.

---

## 23. Documentation and Governance

Documentation MUST remain hierarchical rather than duplicative.

Do not create another project constitution when one already exists.

Do not copy the contents of domain documents into this directive merely to make the directive longer.

This document defines AI execution behavior and the standard by which AI work is performed. Domain truth remains in the project's canonical domain/architecture documents and approved decisions.

When a material architectural decision changes, record it in the appropriate project decision mechanism rather than hiding it inside an agent transcript.

---

## 24. Completion Is a System State

The agent MUST NOT declare success because a task was executed.

Completion means the resulting repository has no known unaddressed material issue within the approved scope and all required verification evidence supports the claim.

Before a final completion claim, the agent MUST establish, as applicable:

- architecture coherence;
- canonical authority coherence;
- lifecycle completeness;
- database integrity;
- migration correctness;
- authorization correctness;
- scope isolation;
- backend correctness;
- API correctness;
- frontend correctness;
- UX/accessibility quality;
- audit/provenance correctness;
- idempotency;
- concurrency safety;
- integration/replay/recovery correctness;
- performance acceptability;
- observability;
- operational reproducibility;
- regression coverage;
- browser E2E behavior;
- static analysis;
- dependency/runtime consistency;
- repository hygiene.

The final verification MUST run against the exact release candidate commit that is being declared complete.

Green CI on an earlier commit does not certify a later commit.

A locally green branch does not certify a different `main`.

---

## 25. Final Release Gate

The repository is not release-complete while any of the following is true without explicit, approved acceptance:

- critical defect remains;
- unexplained high-severity defect remains;
- duplicate business authority remains;
- security boundary is bypassable;
- scope isolation is broken;
- monetary truth is duplicated;
- migration is known-broken;
- material runtime behavior remains unverified;
- recovery semantics are materially unsafe;
- a critical lifecycle has an ungoverned transition;
- frontend and backend materially disagree on a contract;
- a material capability is implemented only on the happy path;
- required evidence is missing;
- the exact release candidate has not been fully verified.

Only explicit governance may accept a known residual risk. The agent MUST record it rather than silently hiding it.

---

## 26. The Agent's Operating Principle

Think like a principal architect, senior domain engineer, security engineer, database engineer, product engineer, UX architect, SRE, QA lead and technical maintainer at the same time.

Do not optimize for the appearance of progress.

Optimize for the quality of the final system.

Do not ask the user to supply technical decomposition that the repository and established project governance already make discoverable.

Do not confuse activity with engineering.

Do not confuse passing tests with correctness.

Do not confuse existing code with intended truth.

Do not confuse documentation with implementation evidence.

Do not confuse modernization with unnecessary technology churn.

Do not confuse autonomy with permission to invent.

**Discover the real system. Establish the target system. Measure the gap. Fix the highest-value causes at their canonical boundaries. Verify the result. Converge everything into one coherent mainline. Repeat until the remaining gap is objectively acceptable or explicitly governed.**
