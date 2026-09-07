# TOEFL House — Canonical Terminology

**STATUS: CURRENT CANONICAL — NORMATIVE**

This vocabulary defines the preferred repository-wide names for TOEFL House domain concepts. It does not collapse concepts that have different lifecycle or ownership semantics. Historical documents may retain historical wording when necessary to preserve provenance.

## 1. Vocabulary rule

**ONE CONCEPT → ONE CANONICAL TERM → ONE DEFINITION → ONE REPRESENTATION**

Application code, API contracts, tests, user-facing text and current documentation should use the canonical term below whenever they describe the same concept. A competing name is acceptable only when it represents a genuinely different concept or a documented external compatibility contract.

## 2. Canonical vocabulary

| Concept | Canonical term | Meaning | Domain owner | Forbidden / ambiguous alternatives |
|---|---|---|---|---|
| Legal/top-level institutional entity | Organization | Root institutional entity that owns the TOEFL House operational topology | Organization | Institution, company, tenant when they mean the same object |
| Physical/operational location | Campus | A physical/operational site belonging to an organization | Organization | Branch when the concept is specifically a campus |
| Operational organizational location unit | Branch | Operational unit under an organization/campus used for scope and responsibility | Organization | Campus when they mean a branch; office when they mean a branch |
| Functional organizational unit | Department | Internal functional unit responsible for work | Organization | Unit, division when they mean the same domain object |
| Authenticated application principal | User | Application login/account that authenticates to the system | Identity/Access | Account when referring to the authenticated user object |
| Human identity | Person / Identity | Stable human identity and its identity attributes | Identity | User when a human identity rather than login account is meant |
| Employment/authority placement | Position | A person's organizational position with effective dates and authority context | HR/Access | Role, job, title when they mean the same assignment |
| Permission bundle | Role | Named set of capabilities granted through access policy | Access | Position, permission, capability |
| Atomic permission | Capability | A specific action the actor is allowed to perform | Access | Privilege, permission when the canonical capability object is meant |
| Authorization boundary | Scope | Effective organizational/data boundary within which an actor may act | Access | Branch scope when a broader organization/campus scope is meant |
| Learner record | Student | Person in the student lifecycle | Students/Academic | Learner |
| Teaching profile | Teacher | Person authorized/qualified to teach and receive teaching assignments | Academic/HR | Instructor, faculty when they mean the teaching profile |
| Employment record | Employee | Employment relationship and employment facts | HR | Staff when the employee record is meant |
| Academic calendar unit | Term | Academic period used by the institution for academic state and scheduling | Academic | Semester when the architecture intentionally models a broader term |
| Program delivery unit | Offering | A published academic offering for a level/program in a defined period | Academic | Course, class when those concepts are meant |
| Scheduled teaching unit | Class | Operational teaching group created from an offering | Academic | Course, offering, section |
| Subdivision of a class | Section | Named subdivision of a class used for grouping or scheduling | Academic | Class, cohort when the model means a section |
| Scheduled teaching occurrence | Session | One scheduled occurrence of a class | Academic | Lesson, class when referring to the occurrence rather than the class |
| Student academic participation | Enrollment | Student's governed participation in an offering/class lifecycle | Academic/Students | Registration, admission, joining |
| Capacity overflow queue | Waitlist | Ordered set of students waiting for available capacity | Academic | Waiting list when the domain object is meant |
| Attendance fact | Attendance | Recorded presence/absence fact for a session and student | Academic | Presence, check-in when the canonical fact is meant |
| Evaluated academic attempt | Assessment | Governed assessment attempt/result lifecycle | Academic | Exam when the broader assessment lifecycle is meant |
| Entry-level academic evaluation | Placement | Placement assessment/process used to determine academic entry level or recommendation | Placement | Placement test, assessment when the full placement process is meant |
| Suggested academic outcome | Recommendation | A proposed outcome produced by placement/evidence rules before final decision | Placement | Decision, result |
| Ordered academic outcome | Progression | Governed movement of a student to a subsequent academic level/state | Academic | Promotion when it means the same lifecycle action |
| Completion decision | Graduation | Governed completion/graduation lifecycle and certificate outcome | Academic | Completion when the formal graduation process is meant |
| Academic history document | Transcript | Authoritative record of a student's academic history/results | Academic | Report, result sheet |
| Customer financial obligation | Invoice | Formal financial obligation issued/recorded by Finance | Finance | Bill, charge when referring to an invoice record |
| Incoming monetary event | Payment | Money received against a financial obligation | Finance | Transaction when the payment event is meant |
| Allocation of received money | Allocation | Distribution of a payment to one or more obligations/lines | Finance | Payment, settlement |
| Money returned | Refund | Authorized reversal/return of received money | Finance | Reversal when a refund event is meant |
| Calculated financial position | Balance | Authoritative amount remaining or outstanding under Finance rules | Finance | Due, outstanding when they are intended as the same computed value |
| Accounting posting structure | Journal | Authoritative accounting entry container/posting event | Finance | Transaction, payment |
| Account-level accounting record | Ledger | Authoritative account-level posting/history derived from journals | Finance | Balance, journal |
| Payroll-to-finance recognition event | Settlement | Governed recognition/settlement of an approved financial amount | Finance/Payroll boundary | Payment, payroll when those concepts are conflated |
| Payroll calculation/proposal | Payroll | Payroll calculation and governed proposal produced from HR facts and payroll rules | Payroll | Salary, payment, settlement |
| Employment lifecycle | Employment | Governed relationship between employee and organization | HR | Staff record, position |
| Outgoing operating cost | Expense | Financial record of an organizational expense | Finance | Payment when an outgoing payment is the only concept being described |
| Financial concession | Scholarship | Authorized student financial support/concession | Finance | Discount when the program is a scholarship rather than a pricing adjustment |
| Governance decision gate | Approval | Explicit authorization decision in a governed workflow | Access/Governance/owning domain | Confirmation when the action is a formal approval |
| Immutable compliance history | Audit | Recorded evidence of a governed action, decision or state change | Audit | Log when the record is an audit fact rather than operational logging |
| Derived/read-side representation | Projection | Read-optimized representation derived from authoritative facts | Reporting/Projection | Snapshot when it is actually a point-in-time evidence snapshot |
| Analytical read-side capability | Reporting | Queries/presentations over authoritative data or projections | Reporting | Dashboard when the broader reporting capability is meant |

## 3. Important semantic distinctions

### Organization / Campus / Branch / Department

These are not synonyms. Organization is the top-level owner. Campus is a physical/operational site. Branch is an operational unit used for responsibility and scope. Department is a functional unit. A UI may use a simplified label for users, but internal domain names must preserve the distinction.

### Term / Offering / Class / Section / Session

A **Term** is an academic period. An **Offering** is a planned/published delivery opportunity. A **Class** is the operational teaching group created from the offering. A **Section** is a subdivision of a class. A **Session** is one scheduled occurrence. These must not be merged merely because everyday language uses “class” for all five.

### Student / User / Person / Employee / Teacher

A person can hold multiple domain roles. A **Person** is identity. A **User** is an application account. An **Employee** is an employment relationship. A **Teacher** is an authorized teaching profile. A **Student** is a student-lifecycle record. These are deliberately different concepts and are not interchangeable.

### Assessment / Placement / Recommendation / Decision

**Assessment** is the broad evaluated academic attempt/result lifecycle. **Placement** is the specific placement process. **Recommendation** is a proposed outcome. A **Decision** is the governed final outcome. They must not be collapsed into one status or object.

### Payment / Allocation / Refund / Balance / Settlement / Journal / Ledger

These are separate financial facts. Payment records money received; Allocation applies payment to obligations; Refund returns authorized money; Balance is a computed/authoritative financial position; Settlement records governed recognition across the payroll/finance boundary; Journal and Ledger are accounting structures. No UI label may turn these into a single generic “transaction” concept.

### Role / Capability / Scope / Position

Position describes organizational placement. Role groups capabilities. Capability is an atomic action permission. Scope limits where that authority applies. A person may have multiple positions and effective scopes.

## 4. Status vocabulary guidance

Do not replace domain state machines with a generic `active`, `enabled`, `open`, `done` or `pending` vocabulary merely for visual consistency.

Use a lifecycle-specific state where the domain has one. The same word may be valid in different domains when its state machine semantics are different. Before changing a state value, verify the owning aggregate, database constraint, API contract and transition graph.

Preferred generic lifecycle words are:

- `active` only for an entity whose lifecycle explicitly defines active state;
- `pending` for a governed state awaiting a defined next action;
- `completed` for a successfully completed lifecycle where no stronger domain term exists;
- `cancelled` for an intentional termination without normal completion;
- `retired` for a definition/configuration intentionally withdrawn from future use.

`open`, `current`, `finished`, `done`, `waiting`, `queued`, `enabled` and similar words require domain justification rather than automatic substitution.

## 5. Date/time vocabulary

Timestamp names must preserve lifecycle semantics:

- `createdAt` — record creation time;
- `updatedAt` — record modification time;
- `startsAt` / `endsAt` — scheduled interval boundaries;
- `effectiveFrom` / `effectiveUntil` — authority/lifecycle validity window;
- `submittedAt` — submission event time;
- `approvedAt` — approval event time;
- `confirmedAt` — confirmation event time;
- `occurredAt` — time an external/domain event actually occurred.

Do not rename a semantically distinct timestamp to a generic `date` merely for naming consistency.

## 6. External and historical terminology

Public API contracts, database identifiers and historical records are not renamed solely for cosmetics. When an external contract requires an old name, map it at the boundary and document:

`legacy name → canonical internal concept → consumer → removal gate`

Historical documentation may quote an obsolete term when describing a past decision. It must label that usage as historical rather than presenting it as current vocabulary.

## 7. Enforcement

New code must use this vocabulary. New synonyms for an existing concept require an explicit semantic distinction in code/documentation or an approved external-compatibility exception.

The lightweight audit command is:

```text
php scripts/terminology-audit.php
```

The audit is intentionally advisory by default so that historical/compatibility usages can be reviewed rather than mechanically deleted. Use an allowlist or an explicit source comment for legitimate exceptions.
