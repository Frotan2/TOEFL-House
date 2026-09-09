# TOEFL House — Unified Experience Design System

## 1. Product design direction

TOEFL House uses a restrained institutional product language: deep academic navy as the primary brand, warm academic gold as the recognition accent, neutral blue-grey surfaces for hierarchy, and semantic green/amber/red for operational state. The interface should feel trustworthy, calm, precise and operational rather than decorative.

The design target is a professional multi-branch education operating system. Visual hierarchy must support scanning, decision-making, evidence review and safe execution of commands.

## 2. Navigation taxonomy

The primary shell uses five information groups:

### Work
- Home
- Reception Desk
- Students & Admissions
- Academic Operations
- Placement
- People & Faculty

### People
- HR
- CRM & Follow-up

### Operations
- Finance & Funding
- Payroll
- Library & Resources
- Communication
- Reports & Dashboards
- Documents & Evidence

### Governance
- Organization
- Identity
- Access Governance
- Privacy & Consent
- Audit & History

### Control
- Command Center
- Administration

The navigation must never expose a permission the server has not granted. Hiding is an experience optimization; authorization remains server-side.

## 3. Global shell

Desktop:
- sticky top header;
- persistent left navigation;
- 248–264px expanded sidebar;
- 76–80px collapsed rail;
- stable content stage with responsive gutters;
- command palette from Ctrl/⌘+K;
- My Work and Administration shortcuts.

Tablet:
- collapsed navigation rail by default;
- full labels available through the navigation affordance;
- content uses a single operational column where two-column density harms readability.

Mobile:
- top header remains visible;
- navigation becomes a drawer;
- no horizontal navigation rail;
- primary action and status remain above secondary evidence;
- dense tables scroll within their own container instead of overflowing the page.

## 4. Page grammar

Every major workspace follows this order unless the domain has a stronger safety reason:

1. Context line / eyebrow
2. Page title
3. One-sentence purpose
4. Primary action + secondary utilities
5. Scope / date / status context
6. Summary or decision metrics
7. Primary work surface
8. Supporting evidence
9. Secondary configuration
10. Empty / error / audit states

The first screen must answer three questions:
- Where am I?
- What needs attention?
- What is the next safe action?

## 5. Standard workspace tabs

Tabs are for peer-level work areas, not arbitrary sections. The preferred order is:

- Overview / Today
- Active work
- History / Evidence
- Configuration

A tab should never be used merely to hide an error or move a primary action below the fold.

## 6. Data density rules

Use cards for:
- small KPI sets;
- task/action shortcuts;
- readiness checks;
- exception summaries.

Use tables for:
- comparable records;
- audit/history;
- finance evidence;
- operational queues;
- class/session rosters.

Use drawers or detail panels for:
- contextual inspection;
- record-specific evidence;
- reversible secondary actions.

Use full pages for:
- long-lived workflows;
- multi-step configuration;
- high-risk approval decisions;
- complex evidence review.

## 7. State system

Every async or governed workflow must represent:

- loading;
- ready;
- empty;
- partially available;
- rejected/denied;
- validation error;
- command failure;
- command success;
- stale/refresh-needed;
- evidence incomplete.

Success must be derived from the server command result and a refreshed projection. Never acknowledge a command as successful because local React state changed.

## 8. Status semantics

Green:
- active;
- ready;
- approved;
- completed.

Amber:
- pending;
- proposed;
- reviewed;
- attention required.

Red:
- rejected;
- failed;
- blocked;
- revoked.

Blue:
- information;
- neutral source evidence;
- informational workflow state.

Color is never the only signal; status labels, icons or text must carry the meaning too.

## 9. Form design

Every command form must:
- group fields by decision relevance;
- use server-compatible names and values;
- show requiredness explicitly;
- keep destructive or irreversible actions behind confirmation;
- show a disabled/busy state during submission;
- preserve entered data after a validation error;
- present the exact server rejection in a human-readable way;
- refresh the source projection after success.

Use progressive disclosure for advanced references, evidence IDs and governance metadata.

## 10. Workspace-specific composition

### Home / My Work
Tabs or segments:
- Today
- Priority
- Overdue
- Notifications

Primary surface: actionable queue.
Secondary surfaces: operating summary, scope, shortcuts.

### Command Center
Tabs:
- Command center
- Administration & setup

Command center order:
1. Scope
2. Operational metrics
3. Action center
4. Readiness
5. Notifications
6. Reporting evidence

Administration order:
1. Organization
2. Identity & access
3. Academic configuration
4. People & faculty
5. Finance & payroll
6. Documents / communication / privacy / audit

### Students & Admissions
Primary tabs:
- Directory
- Admissions
- Journey / Detail

Student detail order:
1. Identity / status hero
2. Lifecycle controls
3. Admissions lineage
4. Placement evidence
5. Enrollment / attendance / assessment
6. Financial context
7. Guardians / communication
8. Documents / messages
9. Audit

### Academic Operations
Primary tabs:
- Overview
- Structure
- Classes
- Timetable
- Attendance
- Assessment
- Progression
- Graduation / Transcript
- Appeals

Class detail should prioritize:
- state + capacity;
- roster / sections;
- teacher assignment;
- timetable;
- attendance;
- gradebook / assessment evidence;
- governed transitions.

### Academic Setup
Fixed sequence:
1. Infrastructure
2. Curriculum
3. Course delivery

Every setup check must expose:
- current value;
- readiness state;
- why it matters;
- owning authority;
- direct handoff.

Readiness is a view, not a second rule engine.

### Placement
Primary tabs:
- Profiles / Queue
- Evidence
- Scoring
- Recommendations
- Decision
- Eligibility

Workflow:
`profile -> attempt -> section evidence -> score -> recommendation -> review -> approval -> release -> downstream academic consumption`

Recommendation never equals approval. Approval never equals enrollment.

### People & Faculty
Primary tabs:
- Faculty directory
- Teacher Day
- Qualifications
- Availability
- Assignments
- Workload

Teacher Day is the default operational view; profile and governance evidence are secondary.

### Front Office / Reception
Primary surfaces:
- next actions;
- admissions queue;
- visitor pipeline;
- current student lookup;
- academic context.

Reception must not become a shortcut around finance or academic authority.

### CRM
Primary tabs:
- Visitors
- Pipeline
- Follow-ups
- Timeline
- Sources / campaigns

The timeline is append-oriented and provenance-aware.

### Finance & Funding
Primary tabs:
- Overview
- Obligations
- Payments
- Exceptions
- Funds / Funding
- Reconciliation
- Accounts / Journals
- Periods

Funding should be presented as an extension of finance governance, not as an unrelated dashboard.

Proposed top-level funding model:
`Donor -> Funding source/agreement -> Fund -> Allocation -> beneficiary/program/branch -> disbursement/expense -> report -> evidence`

### Payroll
Primary tabs:
- Periods
- Calculations
- Approvals
- Clearance
- Settlements

Payroll calculation is server-owned; the UI is a review and command surface.

### Documents & Evidence
Primary tabs:
- Inbox / Register
- Verification
- Active
- Expiring
- Archived
- Classifications
- Retention
- Templates

Every document view should show lifecycle state, version, owner/source and evidence state.

### Reports & Dashboards
Primary tabs:
- Overview
- Runs
- Dashboards

Every number must retain metric identity, period semantics, scope, evidence completeness and reproducibility information.

### Administration / Settings
The control plane is organized by domain ownership:
- Organization
- Branch / campus
- Academic configuration
- Identity
- Access / roles / scope
- Finance policies
- Payroll policies
- CRM configuration
- Communication policies
- Documents / retention
- Privacy / consent
- Business rules
- Audit / governance

No monolithic undifferentiated settings form.

## 11. Printing architecture

Printing is an operational output layer, not a navigation-heavy application. Supported document families include:
- ID cards;
- enrollment documents;
- receipts / invoices;
- certificates;
- transcripts;
- payroll slips.

Future Print Center should add template versioning, organization/branch context, locale, paper size, approval and audit metadata.

## 12. Accessibility and responsive contract

Required for every workspace:
- keyboard navigation;
- visible focus;
- semantic headings;
- accessible labels;
- no color-only status meaning;
- readable tables without page-wide overflow;
- touch targets sized for practical mobile use;
- reduced-motion behavior;
- logical reading order.

## 13. Design prohibitions

Do not introduce:
- a second API transport;
- token persistence that bypasses the Laravel session boundary;
- a frontend global store that becomes a domain authority;
- client-side authoritative finance or academic calculations;
- decorative charts without an operational question;
- five competing primary actions on one screen;
- modals for workflows that need durable review context;
- hidden permissions expressed only through UI visibility;
- domain-specific colors that conflict with global semantic state colors.

## 14. Definition of done for UX

A feature is complete only when:

`information architecture + layout + states + responsive behavior + accessibility + authorized command + server result + refreshed projection + audit/evidence semantics`

are all coherent and tested.
