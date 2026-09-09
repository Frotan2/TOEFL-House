# TOEFL House — Unified Product Architecture

## Product decision

The canonical product is the existing Laravel/PostgreSQL application with the React experience layer mounted inside the Laravel employee console. The standalone frontend review branch is a pattern library and feature reference, not a second application to transplant.

The final system follows one authority chain:

`Laravel domain -> canonical commands/queries -> /api/v1 -> React product workspaces -> shared design system`

The browser never becomes the source of truth for authorization, scope, financial truth, academic progression, admission legality, payroll, workflow transitions, institutional policy, or audit history.

## Experience architecture

The interface is organized around the work operators perform rather than database entities:

1. **My Work** — personal priority queue, notifications, visible-scope search, and next actions.
2. **Students & Admissions** — intake, decision chain, learner lifecycle, branch provenance, guardians, communication, attendance, assessment and finance projections.
3. **Academic Operations** — academic setup, classes, sections, capacity, timetable, attendance facts, assessment evidence, progression, graduation and transcripts.
4. **People & Faculty** — teacher identity, employment/provenance, capability, qualification evidence, availability, workload and dated assignments.
5. **Front Office / CRM** — visitor and follow-up workflows, preserving server-owned lifecycle and scope.
6. **Finance Operations** — source-owned obligations, payments, exceptions and reporting boundaries.
7. **Reports** — evidence-aware reporting and drill-in.
8. **Command Center** — management decisions, readiness, open work and operational evidence.

## UX contract

The product uses progressive disclosure: show the next useful decision first, keep evidence nearby, and expose lower-level configuration only when it is relevant. The design system is token-based and componentized, with predictable spacing, hierarchy, focus states, responsive behavior and reduced-motion support.

The shell is responsive and persistent on desktop, collapsible for dense workflows, and switches to a mobile drawer when horizontal navigation would harm focus. Work queues remain stable anchors so operators can move between a summary and the actual work without losing context.

## Convergence rules

From the reviewed standalone frontend, the following patterns are intentionally adopted:

- Academic Setup as a phased setup experience with completion gates.
- Class detail as contextual roster / gradebook / settings work.
- Student Journey as a chronological operational view built from authoritative learner lifecycle facts.
- Teacher Day as a task-first faculty workflow.
- Reception / CRM as an operator-oriented intake workspace.
- Business Operating System concepts for management metrics and exception-oriented decisions.
- Modular navigation, design tokens, RTL-safe logical properties, accessibility, responsive layout and clear handoffs.

The following are explicitly **not** transplanted:

- the standalone `/api` transport layer;
- `/auth/me` or `/auth/login` assumptions;
- generic token persistence in local storage;
- a frontend God Store replacing server projections;
- client-side calculations that are authoritative in the academic or finance domains;
- standalone Vite/Tailwind configuration that conflicts with Laravel Vite integration.

## Quality gates

Every convergence increment must pass:

- TypeScript typecheck and production build;
- frontend mount/runtime guard tests;
- PHP formatting and static analysis;
- database migration and invariant checks;
- canonical backend test suite;
- concurrency verification;
- branch/scope authorization regression coverage;
- manual responsive inspection for desktop, tablet and mobile widths.

No UX feature is considered complete merely because it renders. A workflow is complete only when its read projection, action authority, failure state, success feedback and audit semantics are coherent.
