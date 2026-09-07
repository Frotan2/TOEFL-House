# TOEFL House — Codebase Hygiene & Engineering Standards

**STATUS: CURRENT CANONICAL — NORMATIVE**

This document defines the repository-wide standard for removing legacy residue without weakening domain ownership, security, auditability, transaction semantics, database invariants, or runtime guarantees.

## 1. Governing principle

The repository follows:

**ONE FACT → ONE OWNER → ONE WRITE AUTHORITY → ONE LIFECYCLE**

A cleanup is correct only when it preserves that chain.

The preferred order is:

`delete > simplify > consolidate > refactor > abstract`

Abstractions are introduced only when they reduce real duplication or make an invariant explicit.

## 2. Evidence states

Every engineering claim uses one of these states:

- **VERIFIED** — executed successfully in the required runtime.
- **STATICALLY VERIFIED** — established by repository inspection, reference tracing, or deterministic static tooling, without runtime execution.
- **UNVERIFIED** — not executed or not proven by available evidence.
- **BLOCKED** — execution cannot currently be performed because a required dependency or environment is unavailable.

No document, comment, test description, or commit message may imply a stronger state than the evidence supports.

## 3. Dead-code policy

A file, symbol, route, configuration key, asset, test helper, or script is not deleted solely because a simple text search does not find a caller.

Before deletion, establish all of the following where applicable:

1. no runtime import/reference;
2. no route, event, listener, job, scheduler, framework-discovery, service-provider, or reflection reference;
3. no test, fixture, factory, seed, migration, deployment, or operational dependency;
4. no external/API contract requiring the artifact;
5. no current documentation that represents supported behavior.

Dynamic imports, Laravel discovery, framework conventions, route registration, configuration loading, and external consumers must be considered explicitly.

Deletion must be followed by a repository-wide re-audit and the strongest available verification.

## 4. Legacy disposition policy

Every legacy implementation receives exactly one disposition:

- **DELETE** — proven unused or obsolete.
- **REFACTOR** — still required, but structurally non-convergent.
- **REPLACE WITH CANONICAL AUTHORITY** — a duplicate implementation exists and must delegate to the established owner.
- **KEEP AS DOCUMENTED COMPATIBILITY ADAPTER** — a real current contract still requires it; the consumer/contract and removal condition must be recorded.

A compatibility adapter is never retained merely because deletion feels risky. Where external consumption cannot be observed from the repository, the uncertainty must be documented rather than presented as proof of active use.

## 5. Domain and write-authority standard

Business facts have one authoritative owner.

- **Finance** owns monetary truth, journals, payments, allocations, refunds, cash, expenses, scholarships, corrections, and settlement recognition.
- **Academic** owns academic state transitions, capacity, enrollment, attendance, assessment, progression, graduation, transcripts, and appeals.
- **Placement** owns placement evidence, scoring, recommendations, moderation, and decisions.
- **Access** owns authentication context, capability, scope, delegation, approval, and separation-of-duties decisions.
- **Organization** owns organization/campus/branch topology.
- **Identity** owns people and account identity state.
- **HR/Payroll** own employment facts and payroll calculation/proposal respectively; payroll does not become a second Finance authority.
- **Reporting** is read/projection authority only and cannot mutate operational truth through a reporting path.

Frontend code is presentation, interaction, and intent orchestration. It is never the authority for money, enrollment validity, capacity, authorization, academic transitions, placement decisions, payroll settlement, or organization topology.

## 6. Backend structure

Controllers are transport boundaries. Domain commands/actions are the business write authority. Repositories and query services provide persistence/read access without creating a second business lifecycle.

New business logic must not be introduced in route closures, HTTP requests, Blade views, React components, or API transport wrappers when a domain authority already exists.

Transactions must cover the complete state mutation and its required audit/provenance effects. Concurrency-sensitive operations must use the existing locking/idempotency strategy and real PostgreSQL verification before release certification.

## 7. Frontend transport standard

`resources/js/core/api.ts` is the canonical frontend transport client.

New fetch wrappers, Axios clients, duplicated API clients, or ad-hoc transport conventions are prohibited unless a documented boundary requires a separate protocol.

Frontend normalization may shape transport data for presentation, but may not calculate or decide business truth.

## 8. PHP/Laravel coding standard

Use PHP 8.2+ language features supported by the project runtime and Laravel 12 conventions.

Required defaults:

- `declare(strict_types=1);` for new PHP source where consistent with the existing module standard;
- explicit parameter and return types;
- explicit nullability;
- dependency injection at boundaries;
- small, purpose-named methods;
- domain exceptions for domain failures;
- no swallowed exceptions;
- no secret/sensitive-data logging;
- no unsafe mass assignment;
- no duplicate transaction wrappers around the same authority;
- no service-locator or global-state workaround without a documented framework reason;
- no premature interface or repository layer when no real abstraction boundary exists.

Controllers and requests should validate and transport, not own domain state transitions.

## 9. TypeScript/React standard

TypeScript strict mode remains mandatory.

Avoid `any`, unsafe casts, `@ts-ignore`, stale effects, dead state, dead props, impossible states, and duplicated normalization. Exceptions require a precise boundary reason and should be isolated.

Use domain-specific names. Prefer discriminated unions and explicit result shapes over ambiguous records.

React components should remain focused on rendering and interaction. Business state transitions belong to the backend authority and are invoked through the canonical API client.

## 10. CSS/UI standard

The interface uses one shared visual foundation. Do not introduce a second design system.

New or modified UI should reuse the established typography, spacing, radius, border, elevation, layout, form, button, table, dialog, feedback, and responsive tokens/classes.

Remove dead selectors and styling only after confirming their class names are not used by templates, React, dynamic composition, or framework-generated markup.

## 11. Naming standard

Names must describe the domain responsibility and lifecycle they represent.

Avoid generic names such as `data`, `info`, `temp`, `misc`, `helper`, `util`, `manager`, `thing`, or `item` when a domain-specific name is available.

Use consistent forms:

- PHP classes: `StudlyCase` nouns/verbs appropriate to the responsibility;
- methods: explicit verbs for mutations and nouns/queries for reads;
- booleans: `is*`, `has*`, `can*`, `should*` where semantically correct;
- identifiers: keep `*Id` suffixes explicit;
- timestamps: use precise names such as `createdAt`, `startsAt`, `effectiveFrom` rather than generic `date`;
- React components: `PascalCase`;
- hooks: `use*`;
- constants: project-standard uppercase naming only for true constants.

Public API names are not casually renamed. External contracts are mapped at a transport boundary when compatibility is required.

## 12. Comment standard

Comments are for information that code cannot express clearly.

Allowed comments explain:

- architectural constraints;
- security rationale;
- transaction/concurrency behavior;
- database limitations/invariants;
- external protocol requirements;
- intentional compatibility behavior.

Prohibited comments include conversational notes, emotional language, AI instructions, stale architecture descriptions, commented-out implementations, `TODO`/`FIXME`/`HACK` notes without an actionable tracked owner, and comments that merely restate obvious syntax.

A cleanup must remove obsolete commented-out code rather than preserving it as an unofficial backup.

## 13. Routes and API policy

Routes remain thin. Every route must have an identifiable consumer, domain owner, authentication/authorization boundary, and transport contract.

The versioned `/api/v1` application API is the canonical interactive SPA transport. Older web POST endpoints may remain only as documented compatibility boundaries until their current consumers are migrated and the deletion gate is satisfied.

No route closure may become a new business authority.

## 14. Database alignment

Application code must never reference retired database structures.

Known retired structures include:

- `compensation_components`
- `work_bases`

The migration chain remains authoritative until the separately documented PostgreSQL baseline freeze is performed. A guessed schema dump, fake migration state, or uncontrolled production DDL is prohibited.

Reference/system data is seeded separately from schema where practical. Historical data-writing migrations must not be deleted merely to reduce file count.

## 15. Dependency policy

Do not perform major dependency upgrades as cleanup work.

Remove a dependency only after its runtime, test, build, and configuration references have been checked. Lockfiles are authoritative and must not be regenerated or fabricated without a real package-manager run.

Required project tooling should be simple, deterministic, documented, and usable in CI.

## 16. Security and repository hygiene

Repositories must not contain real credentials, private keys, local `.env` files, database dumps, logs, editor metadata, dependency caches, or runtime artifacts unless explicitly required as versioned fixtures.

Diagnostic and recovery utilities may be versioned when they have a defined operational purpose, but they must be clearly named and documented.

Examples and tests use placeholders or generated values, never real credentials.

## 17. Test expectations

Tests should verify behavior and invariants rather than implementation trivia.

Maintain explicit coverage for:

- authorization and scope isolation;
- domain lifecycle transitions;
- financial integrity;
- database invariants;
- concurrency and idempotency;
- API contracts;
- security regressions;
- compatibility adapters while they remain supported.

Concurrency claims that depend on PostgreSQL must be exercised against real PostgreSQL before release certification.

## 18. Runtime-readiness policy

The repository must contain deterministic instructions for:

1. dependency installation;
2. environment bootstrap;
3. database setup and migration;
4. reference-data seeding;
5. backend tests/static checks;
6. frontend type/build checks;
7. production build/startup;
8. health/readiness verification;
9. backup/restore and recovery checks.

Documentation distinguishes preparation from execution. A command being documented is not evidence that it has passed.

## 19. Cleanup workflow

Every meaningful cleanup follows:

**Discover → Prove → Change → Verify → Re-audit → Document**

The re-audit must explicitly search for remnants of the removed name, route, import, table, comment, or architecture terminology.

A cleanup is not complete while an unexplained residue remains.

## 20. Prohibited patterns

The following are prohibited unless an explicit architecture decision says otherwise:

- duplicate business authorities;
- frontend business truth;
- direct frontend database access;
- route-closure business logic;
- second API transport clients;
- hidden cross-branch authorization bypasses;
- financial calculations duplicated outside Finance authority;
- payroll-to-cash shortcuts;
- reporting writes to operational truth;
- uncontrolled manual database changes;
- fake migration history or guessed schema baselines;
- commented-out legacy implementations;
- unexplained `TODO`/`FIXME`/`HACK` markers;
- real secrets in source;
- silent exception swallowing;
- claims of runtime readiness without runtime evidence.

## 21. Compatibility register expectation

For every retained compatibility boundary, documentation should record:

`contract → current consumer → canonical replacement → removal gate`

The removal gate must be objective: migration of the consuming client, confirmed absence of supported external consumers, or an approved deprecation decision.

## 22. Canonical terminology

Current domain vocabulary is governed by `docs/CANONICAL_TERMINOLOGY.md`.

New code, tests, API descriptions, UI labels and current documentation must use the canonical term for an existing concept. A new synonym requires an explicit semantic distinction, an external-compatibility reason, or an approved historical context.

Do not rename public API or database identifiers merely for vocabulary consistency. Preserve the external contract and map it to the canonical internal concept at the boundary when required.

Run the advisory terminology audit with:

```text
php scripts/terminology-audit.php
```

The audit must be reviewed semantically; it is not a license to bulk-replace legitimate historical or compatibility terminology.

This policy supplements the governing architecture, data-authority, security, operations, testing, release, database-consolidation, and canonical-terminology documents; it does not override them.
