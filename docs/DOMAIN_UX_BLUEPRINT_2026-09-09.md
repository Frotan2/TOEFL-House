# TOEFL House — Domain UX Blueprint

## Universal page grammar
Every operational domain follows the same hierarchy:

1. Context: domain, authority boundary, authorized scope, current period.
2. Situation: concise metrics and the exceptions that matter now.
3. Work areas: stable tabs for overview, register/queue, and governed commands.
4. Detail: selected record with provenance, lifecycle state, evidence, and available commands.
5. Outcome: server result, refreshed projection, visible success/error state, and audit trace.

## Domain-specific ultimate layouts

### Students & Admissions
- Directory / applicants as the entry surface.
- Applicant decision queue before learner detail.
- Student detail as lifecycle hub.
- Journey, academic evidence, finance context, guardians, communication and branch provenance as contextual sections.
- Admission actions grouped by lifecycle stage, never mixed into unrelated records.

### Academic Operations
- Setup → delivery → outcomes is the primary mental model.
- Class list + contextual class workspace on desktop; stacked on tablet/mobile.
- Class detail includes roster, schedule, attendance, assessment, progression and capacity evidence.
- Outcome-changing commands are visually distinct and require explicit confirmation where irreversible.

### Academic Setup
- Three phases: Infrastructure → Curriculum → Course Delivery.
- Each phase has readiness checks, blocking conditions and a next-action handoff.
- Configuration forms remain owned by their domain authority.

### Placement
- Candidate profile → assessment attempt → evidence → scoring → recommendation → review → approval → release.
- Evidence and decision authority are visually separated.
- Eligibility is shown as a signed downstream fact, not inferred in the browser.

### People & Faculty / HR
- People directory → employment → contracts → leave.
- Employment detail is the lifecycle hub.
- Contract versions and compensation rules are separated from Payroll execution.
- Teacher capability, qualification, availability and assignment context stay linked but do not duplicate HR employment truth.

### Finance & Funding
- Overview → obligations → payments → exceptions/funding.
- Monetary records are source-linked and provenance-first.
- Balance, settlement, eligibility and coverage are server-derived; the browser never synthesizes ledger truth.
- Funding concepts include source, fund, allocation, beneficiary/program context and evidence when exposed by authoritative projections.

### Payroll
- Periods → calculations → holds → approved results → settlement/clearance.
- Payroll result is explicitly separate from Finance liability recognition.
- Held calculations are surfaced ahead of routine rows.

### Front Office / CRM
- Reception desk optimizes for today's operator queue.
- Visitor/applicant follow-up is immediately actionable.
- Existing student lookup is nearby to prevent duplicate records.
- Commands hand off to CRM, Admissions or Student authorities.

### Reporting
- Metric definitions → report runs → evidence status → reproducibility → dashboards.
- Incomplete evidence is visible and never disguised as a trustworthy numeric answer.

### Documents & Evidence
- Document register → verification queue → retention/expiry → archive.
- Every document surface should identify classification, lifecycle, evidence and scope.

### Library & Resources
- Asset/resource register → custody/work orders → issuance/return/disposal.
- Operational work is queue-first; long-lived asset facts remain visible in detail.

### Communication
- Queue → delivery state → failure/retry context.
- Message state is explicit; delivery claims are never inferred from local state.

### Governance
- Organization → Identity → Access → Privacy → Audit.
- Configuration is progressive-disclosure: simple structure first, policy/evidence only when relevant.

### Command Center
- Situation → exceptions → readiness → open work → decision evidence.
- Management dashboards link directly to the operational record that explains the number.

## Universal states
Every domain must design explicit states for loading, empty, partial-data, unauthorized, denied-command, validation error, conflict, server error, success, stale data and refreshed data.

## Responsive behavior
- Desktop: persistent navigation + split/list-detail where information density warrants it.
- Tablet: collapsed navigation + stacked detail when two-column reading becomes constrained.
- Mobile: drawer navigation, full-width primary actions, horizontal tab rail, horizontally scrollable dense tables, no clipped identifiers.

## Accessibility contract
- Keyboard-first interaction for navigation, tabs, dialogs and command surfaces.
- Visible focus states.
- Correct tab/tabpanel semantics.
- Status announcements for asynchronous success/error where appropriate.
- Reduced motion respected.
- Color is never the only indicator of status.

## Print/output contract
Operational outputs must use the same visual tokens but a print-safe composition. Interactive controls disappear; evidence, provenance, identity and official status remain.
