# TOEFL House — Unified Experience Release Gates

## Purpose

This document is the completion contract for the unified employee experience. A workspace is not considered production-ready merely because its React bundle renders.

## Gate A — Architecture

- Canonical interactive transport remains `/api/v1`.
- Laravel session/CSRF remains the employee authentication boundary.
- Authorization, organization/campus/branch scope, workflow transitions, finance truth, academic progression and audit semantics remain server-owned.
- React does not become a second domain authority.
- The standalone frontend remains a reference branch rather than a parallel application.

**Required result:** PASS.

## Gate B — Build and runtime integrity

- TypeScript `--noEmit` passes.
- Production Vite build passes.
- Frontend mount regression suite passes for every legacy console and the canonical `app.tsx` entrypoint.
- No route introduced by this productization increment throws during initial render.
- No missing import, invalid hook usage, or blank mount remains.
- Shared product theme is registered as a Vite build input and loaded through the common console boundary.
- Runtime environment verification passes against the exact supported PHP, Composer, Node, npm, Laravel and PostgreSQL contract.
- CI uses the same pinned Node/PostgreSQL toolchain and enforces package engine constraints.
- A genuine Chromium browser E2E run passes against a fresh isolated database and production-mode application configuration.

**Required result:** PASS.

## Gate C — Server/domain regression safety

- Migration replay passes.
- Canonical PHPUnit suite passes.
- Invariant verification passes.
- Concurrency verification passes.
- PHP formatting and PHPStan pass.
- Existing branch/scope authorization regression coverage remains green.
- Scheduled integration work is bounded, idempotent and protected by durable claims/leases.
- Background jobs require an explicit durable operator identity; no implicit system actor is accepted.

**Required result:** PASS.

## Gate D — Workflow integrity

For every task-first workspace:

`read projection -> next action -> authorized command -> server result -> refreshed projection -> visible feedback -> audit evidence`

The browser must never infer success from a local state mutation when the server command has failed or been rejected.

The same rule applies to background work:

`durable schedule -> authorized job run -> claimed/leased work -> bounded execution -> durable outcome -> audit evidence`

**Required result:** PASS across Home/My Work, Students & Admissions, Academic Operations, Placement, People & Faculty, Front Office/CRM, HR/Payroll, Finance/Funding, Reporting and Command Center.

## Gate E — Scope/persona acceptance

At minimum validate the following personas:

- Owner: organization-wide command center and authorized cross-branch visibility.
- Head of Department: cross-branch academic/operational visibility within granted scope.
- Branch/General Manager: own-branch operations without cross-branch leakage.
- Teacher: own faculty context, assigned classes and allowed academic actions only.
- Reception/Front Office: CRM/admissions/operator workflows without finance or academic authority escalation.
- Finance: finance-owned records and commands without unauthorized academic/branch visibility.
- Registrar: student/admission workflows within granted scope.

For each persona verify positive access, negative access, empty state, denied-command state, success feedback and post-command refresh.

**Required result:** PASS.

## Gate F — Responsive/accessibility/design-system acceptance

Inspect desktop, tablet and mobile widths with keyboard-only navigation for:

- persistent shell and collapsed navigation;
- mobile drawer and backdrop;
- command palette;
- navigation taxonomy and active states;
- dashboards, tabs, tables and long identifiers;
- forms and validation errors;
- status/empty/loading states;
- primary task visibility without horizontal overflow;
- visible focus indication and logical reading order;
- reduced-motion behavior;
- semantic state colors with non-color labels;
- print-oriented output surfaces.

**Required result:** PASS.

## Gate G — Deployment and recovery readiness

- Production health/readiness endpoints report the actual dependency state rather than a framework-only liveness response.
- First-run deployment creates the minimum valid organization/campus/branch/access genesis structure without dropping existing data.
- Bootstrap credentials are supplied at runtime and never embedded in source or committed artifacts.
- Backup output is integrity-checked before retention/rotation and restore verifies the dump before destructive replacement.
- The deployment launcher builds the frontend before its own readiness gate.
- Recovery paths are explicit for interrupted initialization and failed migrations.
- A recovery drill or equivalent CI evidence must be attached to a release record; documentation alone does not count as proof of recovery.

**Required result:** PASS for a production release; otherwise release status remains **NOT CERTIFIED**.

## Current implementation status

Implemented in the current productization branch:

- unified responsive enterprise shell;
- task-first Home / My Work;
- Command Center and Administration hub;
- Academic Setup;
- Student Journey;
- Teacher Day;
- Front Office / Reception Desk;
- expanded enterprise navigation taxonomy across work, people, operations, governance and control;
- institutional navy/gold product theme with semantic status tokens;
- shared theme registered in Vite and loaded at the Laravel console boundary;
- responsive, reduced-motion, focus, dense-data and print styling contract;
- canonical app-entrypoint mount regression coverage;
- formal unified architecture, design-system and release-gate documentation;
- exact runtime verification in code and CI;
- isolated real-Chromium E2E release gate with runtime-provided credentials;
- bounded scheduled integration retry execution with durable operator identity and regression coverage;
- CI concurrency cancellation, execution timeouts, least-privilege repository permissions and pinned Node/PostgreSQL versions.

The remaining release decision is evidence-based: GitHub Verification and manual persona/responsive acceptance must complete successfully before the PR is marked ready or merged. Domain-specific UI expansion must consume authoritative server read projections and must not invent client-side truth where a source projection is absent.

## Non-negotiable rule

**Do not convert this PR to Ready for Review, enable auto-merge, or merge it solely because the UI looks complete. Release status requires the gates above to be evidenced.**
