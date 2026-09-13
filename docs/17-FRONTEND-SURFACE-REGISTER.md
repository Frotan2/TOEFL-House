# Frontend Surface Register

Status: implementation architecture register for `frontend-transformation-2026-09`.

The interactive frontend follows the canonical boundary: React renders and orchestrates user intent; domain commands, queries, authorization, scope, lifecycle, calculations, audit, and provenance remain server-owned.

## Operational React surfaces

| Surface | Classification | Reason |
|---|---|---|
| Workspace | React | Work-first employee projection with source-owned work items and notifications. |
| Students / Admissions | React | Student lifecycle and admissions already have versioned API/query/command boundaries. |
| Academic | React | Academic workspace uses the canonical Academic API and preserves lifecycle authority. |
| People / HR | React | HR workspace uses versioned HR API and delegates employment/contract/leave commands. |
| CRM | React | Visitor/follow-up/timeline behavior uses the canonical CRM API. |
| Finance | React | Finance UI reads and writes only through Finance authority; no browser balance truth. |
| Reporting | React | Reporting uses a dedicated versioned API and explicit evidence states. |
| Payroll | React | Payroll lifecycle/calculation/settlement is separated from Finance monetary authority. |
| Placement | React | Placement has a versioned API with attempt, scoring, moderation, recommendation, decision and eligibility queries/commands. |
| Identity | React | Identity has a versioned API for person, verification, account link, credential, and account deactivation operations. |
| Access | React | Access has a dedicated v1 boundary for assignments, policy publication, named-scope grants, staged organization-wide approvals, and dated delegations. |
| Organization | React | Organization has a scoped v1 projection for topology and position catalog. It is intentionally read-only; structural mutations remain organization-domain commands. |
| Library & Resources | React | A scoped Resources API projects books, assets, custody, disposals and facilities work; lifecycle commands remain canonical server authority. |
| Documents & Evidence | React | `/api/v1/documents` computes the union of authorized document branches, projects per-record action affordances, keeps storage references out of read models, and delegates every command to the existing lifecycle authority. |
| Privacy | React | `/api/v1/privacy` computes the union of authorized privacy branches, projects the workspace and the subject dossier, derives every per-consent affordance from the single `ConsentLifecycle` table, offers no deletion anywhere (revocation, expiry and archive only), and delegates all thirteen mutations — purpose definition, consent capture, the six lifecycle transitions, disclosure, direct export, staged export request, approval and execution — to the existing privacy commands. |
| Audit | React | The server projects immutable audit evidence and the UI is read-only; audit evidence is never modelled as mutable CRUD. |

## Transitional or API-blocked surfaces

| Surface | Classification | Reason |
|---|---|---|
| Communication | API BLOCKED / transitional | Message queue and delivery-state commands exist, but a complete versioned notification/message projection is not exposed for safe SPA ownership. |

## Legitimate server-rendered exceptions

| Surface | Classification | Reason |
|---|---|---|
| Print / operational documents | KEEP LEGACY | Receipt, invoice, certificate, transcript, payroll slip, enrollment and ID-card rendering are document/print surfaces rather than interactive SPA workspaces. |
| Authentication | KEEP LEGACY | Sign-in and authentication infrastructure remain server-rendered infrastructure boundaries. |

## Migration rule

A transitional surface is not considered architecturally complete merely because it can be styled to look like React. It becomes a React surface only when the authoritative read model, mutation commands, authorization/scope behavior, error taxonomy, provenance, and lifecycle semantics can cross the versioned API boundary without duplication of domain truth.

When that contract does not yet exist, the correct action is to classify the surface explicitly and avoid inventing a client-side authority.
