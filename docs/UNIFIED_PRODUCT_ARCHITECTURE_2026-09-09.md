# TOEFL House — Unified Product Architecture

## Product decision

The canonical product is the existing Laravel/PostgreSQL application with the React experience layer mounted inside the Laravel employee console. The standalone frontend review branch is a pattern library and feature reference, not a second application to transplant.

The final system follows one authority chain:

`Laravel domain -> canonical commands/queries -> /api/v1 -> React product workspaces -> shared design system`

The browser never becomes the source of truth for authorization, scope, financial truth, academic progression, admission legality, payroll, workflow transitions, institutional policy, or audit history.

## Experience architecture

The interface is organized around the work operators perform rather than database entities:

1. **My Work** — personal priority queue, notifications, visible-scope search, and next actions.
2. **Reception Desk** — operator-first visitor, admissions, student lookup and academic context.
3. **Students & Admissions** — intake, decision chain, learner lifecycle, branch provenance, guardians, communication, attendance, assessment and finance projections.
4. **Academic Operations** — setup, classes, sections, capacity, timetable, attendance, assessment, progression, graduation, transcripts and appeals.
5. **Placement** — evidence, scoring, recommendation, review, approval, release and downstream eligibility.
6. **People & Faculty** — teacher identity, employment/provenance, capability, qualification evidence, availability, workload and dated assignments.
7. **HR / Payroll** — employee lifecycle, payroll periods, calculation review, approval and settlement.
8. **CRM & Follow-up** — visitor pipeline, interaction history, sources, campaigns and follow-ups.
9. **Finance & Funding** — obligations, payments, funds, allocations, discounts, credits, installments, exceptions, reconciliation and ledger-facing operations.
10. **Library & Resources** — operational learning/resource management.
11. **Communication** — governed messaging and communication preferences.
12. **Documents & Evidence** — documents, verification, versions, retention and templates.
13. **Reports & Dashboards** — evidence-aware reporting and drill-in.
14. **Governance** — organization, identity, access, privacy and audit/history.
15. **Command Center** — management decisions, readiness, open work and operational evidence.

## Experience rules

The product is progressive-disclosure based: expose the next useful decision first, keep supporting evidence adjacent, and only expose lower-level configuration when it is relevant.

Every workspace follows the same product grammar:

`context -> purpose -> primary action -> scope/status -> decision surface -> evidence -> secondary configuration -> feedback`

The first screen should answer:

- Where am I?
- What needs attention?
- What is the next safe action?

## Global design system

The product uses institutional navy, warm academic gold and restrained blue-grey neutrals. Green, amber, red and blue are reserved for semantic states. Color never carries meaning alone.

Shared tokens cover typography, spacing, control height, radii, borders, surfaces, elevation, focus rings and responsive breakpoints. The shared product theme is registered in Vite and loaded from the common Laravel console boundary.

The shell is responsive and persistent on desktop, collapsible at dense widths, and switches to a mobile drawer when the navigation rail would harm focus. Tables scroll inside their own containers instead of creating page-level horizontal overflow.

## Core workspace patterns

### Academic Setup
`Infrastructure -> Curriculum -> Course delivery`

Readiness is calculated from live server projections and never becomes a client rule engine.

### Placement
`Profile -> attempt -> section evidence -> score -> recommendation -> review -> approval -> release -> downstream academic consumption`

Recommendation is not approval; approval is not enrollment.

### Student Journey
Chronology uses dated/effective source facts. Financial and academic evidence remain separate from the learner narrative.

### Teacher Day
The default faculty operational view is a day schedule with assignments, live-now state, roster context, workload and operational warnings.

### Front Office
Read-oriented composition over CRM, admissions, students and academic context. Commands hand off to the canonical domain surfaces.

### Finance & Funding
The UI must expose source-owned facts without recreating financial truth. Current finance authority includes payments, obligations, periods, chart of accounts, journals, discounts, reconciliation, funds and allocations, credits, installment plans, gate exceptions and other governed financial commands. New read surfaces should be added only when an authoritative GET projection exists.

### Reports
Every displayed number retains metric identity, period semantics, scope, evidence completeness and reproducibility context.

### Administration / Settings
Settings are a control plane organized by domain ownership, not a monolithic form. The target arrangement is:

- Organization / campuses / branches
- Academic configuration / setup
- Identity / users
- Access roles / scopes
- Finance and payroll policies
- CRM and communication policies
- Documents / retention / templates
- Privacy / consent
- Business rules / workflow policies
- Audit / governance
- Print and document output policies

### Output / Printing
Printing is treated as an operational output layer. The product should converge on a Print Center covering ID cards, enrollment documents, receipts, certificates, transcripts and payroll outputs. Each output should preserve template version, organization/branch context, locale, paper format, approval state and audit metadata.

## Convergence rules from standalone review frontend

Adopt:
- Academic Setup as phased setup with gates.
- Class detail as contextual roster / assessment / settings work.
- Student Journey as evidence-backed chronology.
- Teacher Day as task-first faculty workflow.
- Reception as operator-first CRM intake.
- Business Operating System concepts for exception-oriented management.
- Responsive, accessible, RTL-safe product patterns.

Never transplant:
- standalone API transport;
- standalone token login/session model;
- generic client persistence of credentials/tokens;
- frontend God Store as domain authority;
- client-side authoritative academic/finance calculations;
- conflicting standalone build configuration.

## Quality gates

Every experience increment must pass:

- TypeScript typecheck and production build;
- frontend mount/runtime regression tests;
- PHP formatting and static analysis;
- migration and invariant verification;
- canonical backend suite;
- concurrency verification;
- branch/scope authorization regression coverage;
- responsive and keyboard accessibility inspection.

A workflow is complete only when its read projection, action authority, failure state, success feedback and audit semantics are coherent.
