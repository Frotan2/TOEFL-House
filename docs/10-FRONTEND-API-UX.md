# TOEFL House — Frontend / API / UX

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Frontend boundary

TOEFL House uses a React/TypeScript feature frontend over `/api/v1` canonical backend authorities. Blade remains only where the accepted target explicitly retains a server-rendered, print/document, authentication/error, or transitional compatibility surface.

The frontend is a presentation and interaction layer. It may render server-derived state and collect command input, but it never owns business truth.

## UX constitution

The operator experience is governed by five rules:

1. **Context first** — the interface should make current scope, record, lifecycle state, and available actions obvious before the user acts.
2. **Progressive disclosure** — primary actions and decision-critical facts are prominent; secondary evidence is available without overwhelming the main task.
3. **Source-linked truth** — financial, academic, authorization, lifecycle, provenance, reporting, and notification facts are displayed from server-owned contracts.
4. **Consistency over novelty** — shared design tokens and components are preferred over page-specific visual patterns.
5. **Fast, accessible interaction** — keyboard navigation, visible focus, intentional loading/error/empty states, responsive layouts, and reduced-motion behavior are mandatory product concerns.

## Design system

The current React design system is implemented in `resources/js/app.css` and reusable UI primitives in `resources/js/ui.tsx`.

### Semantic tokens

The system defines semantic tokens for:

- ink/text hierarchy;
- surfaces/backgrounds;
- border hierarchy;
- brand/action states;
- success/warning/danger/info states;
- focus state;
- spacing, radii, and shadows.

Status colors are semantic and reused across Academic, Finance, CRM, Students, People, Workspace, and Management. Color is never the only carrier of state.

### Typography

The interface uses a system-first sans-serif stack with restrained weights and a strong hierarchy for page titles, section headings, metadata, table labels, and numeric values. The stack is selected to remain usable across English and Persian/Dari mixed-script content.

### Layout

The shared React shell provides:

- sticky global navigation;
- compact product identity;
- work-oriented primary navigation;
- active location indication;
- explicit sign-out control;
- skip-to-content accessibility behavior;
- responsive horizontal navigation on constrained widths.

Page templates consistently use:

- contextual page headers;
- section headings;
- summary/stat blocks;
- filter/search rows;
- detail panels;
- source notes;
- contextual action groups;
- intentional loading, empty, success, and error states.

## Navigation model

Primary navigation is organized around operator work:

`Workspace → Students → Academic → People → CRM → Finance → Reports → Management`

Secondary or specialist surfaces remain reachable through their canonical routes and source workflows without becoming top-level navigation clutter.

## Workspace

The Employee Workspace is the primary operator landing surface. It prioritizes:

- open work;
- notifications;
- effective positions and authorized scope;
- common work areas;
- governed management health.

Workspace data remains projection/read-side data. Mutations continue through owning domain commands.

## Core component language

Shared CSS/component contracts cover:

- buttons and text actions;
- inputs/selects/textareas;
- forms and validation;
- panels/cards;
- tabs;
- status badges;
- tables and lists;
- work/notification rows;
- timelines;
- detail facts;
- alerts/notices;
- loading states;
- responsive layouts;
- focus and reduced-motion behavior.

New page-specific visual variants should not be introduced when an existing primitive can express the same interaction.

## Forms

Forms must expose labels, required state, validation, server errors, disabled/submitting state, and consequences for sensitive actions. Duplicate submission protection and idempotency remain backend responsibilities; the UI must not defeat them.

## Data tables

Dense operational data uses a consistent table language with strong headers, aligned metadata, hover/focus treatment, horizontal overflow on constrained widths, and progressive detail rather than indiscriminate column growth.

## Record detail

Important entities use a consistent detail pattern:

`Summary → Lifecycle / Status → Primary Actions → Key Facts → Related Records → Evidence / History`

The interface must not render raw database structure as a substitute for an operator-oriented detail view.

## Financial UX

Financial facts are always presented as server-derived facts. The UI may display previews and context but may not create, recalculate, or reinterpret recorded balances, payments, allocations, refunds, cash, journal, ledger, payroll liability, or settlement truth.

Sensitive financial actions should surface current state, consequence, and authorization context before confirmation.

## Academic UX

Academic views prioritize lifecycle comprehension across terms, offerings, classes, teachers, sessions, capacity, enrollment, assessment, progression, graduation, and transcript evidence. The client does not invent academic transitions.

## CRM UX

CRM prioritizes identity, source/provenance, current stage, next action, recent interaction, owner, and conversion context rather than exposing a raw database record as the primary experience.

## Accessibility

The React UI includes:

- semantic HTML where practical;
- visible `:focus-visible` treatment;
- skip-to-content navigation;
- keyboard-operable buttons/links/forms;
- accessible loading and status messages;
- reduced-motion handling;
- sufficient contrast in the semantic palette;
- state communication that does not rely on color alone.

## Responsive behavior

The shell and core templates are designed for desktop, laptop, tablet, and narrow windows. Dense grids collapse intentionally, detail sidebars become normal-flow content, navigation becomes horizontally scrollable, and forms collapse to one column on constrained widths.

## Performance

The UI should minimize unnecessary client-side state, redundant requests, large dependencies, and expensive transformations. Server-authoritative data must not be cached in ways that make financial or lifecycle truth misleading.

## API and authority rules

The frontend communicates with `/api/v1` and preserves backend contracts. It must never:

- become authorization authority;
- become financial truth;
- own lifecycle state;
- bypass canonical commands;
- silently rename or reinterpret server states;
- manufacture provenance.

## Legacy Blade policy

Blade is not a second product architecture. Remaining interactive Blade screens are transitional unless explicitly retained as infrastructure/print/document surfaces. Their retirement requires an equivalent React/API replacement with preserved authorization, lifecycle, and source-of-truth semantics.

## Verification expectation

Frontend completion requires, as available:

- TypeScript/static verification;
- configured lint/format gates;
- production build from deterministic dependencies;
- browser workflow verification;
- responsive checks;
- accessibility checks.

`TEST EXISTS` is not the same claim as `TEST PASSES`, and neither is the same as `TEST PASSES IN THE OFFICIAL RUNTIME`.
