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

**Required result:** PASS.

## Gate C — Server/domain regression safety

- Migration replay passes.
- Canonical PHPUnit suite passes.
- Invariant verification passes.
- Concurrency verification passes.
- PHP formatting and PHPStan pass.
- Existing branch/scope authorization regression coverage remains green.

**Required result:** PASS.

## Gate D — Workflow integrity

For every task-first workspace:

`read projection -> next action -> authorized command -> server result -> refreshed projection -> visible feedback -> audit evidence`

The browser must never infer success from a local state mutation when the server command has failed or been rejected.

**Required result:** PASS for Home/My Work, Students & Admissions, Academic Operations, People & Faculty, Front Office/CRM, Finance, Reports and Command Center.

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

## Gate F — Responsive/accessibility acceptance

Inspect desktop, tablet and mobile widths with keyboard-only navigation for:

- persistent shell and collapsed navigation;
- mobile drawer and backdrop;
- command palette;
- tables and long identifiers;
- forms and validation errors;
- status/empty/loading states;
- primary task visibility without horizontal overflow;
- visible focus indication and logical reading order;
- reduced-motion behavior where animation is used.

**Required result:** PASS.

## Current implementation status

Implemented in the current productization branch:

- unified responsive enterprise shell;
- task-first Home / My Work;
- Command Center and Administration hub;
- Academic Setup;
- Student Journey;
- Teacher Day;
- Front Office / Reception Desk;
- shared design primitives;
- canonical app-entrypoint mount regression coverage;
- architecture and release-gate documentation.

The remaining release decision is evidence-based: GitHub Verification and manual persona/responsive acceptance must complete successfully before the PR is marked ready or merged.

## Non-negotiable rule

**Do not convert this PR to Ready for Review, enable auto-merge, or merge it solely because the UI looks complete. Release status requires the gates above to be evidenced.**
