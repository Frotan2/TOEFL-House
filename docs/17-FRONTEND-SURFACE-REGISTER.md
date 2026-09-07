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

## Transitional or API-blocked surfaces

| Surface | Classification | Reason |
|---|---|---|
| Access | API BLOCKED / transitional | Current governance console is web-command backed; a stable v1 read/write projection for positions, policies, grants, delegations and approval states is not yet present. Do not create a shadow React authority. |
| Organization | API BLOCKED / transitional | Current organization console is server-rendered and no complete stable v1 organization administration boundary is exposed to the SPA. |
| Documents | API BLOCKED / specialized | Document lifecycle commands exist in the web boundary, but there is no complete v1 document/evidence projection suitable for a safe SPA migration. |
| Library | API BLOCKED / specialized | Library is not currently exposed through a complete versioned interactive API boundary. |
| Privacy | API BLOCKED / governance-specialized | Consent, disclosure, export, approval and execution flows need a versioned read/write governance contract before React migration. |
| Audit | KEEP SPECIALIZED SERVER SURFACE | Audit is immutable evidence, not ordinary mutable CRUD. Current read-only server surface remains intentionally specialized until a suitable evidence-query API is defined. |
| Communication | API BLOCKED / transitional | Message queue and delivery-state commands exist, but a complete versioned notification/message projection is not exposed for safe SPA ownership. |

## Legitimate server-rendered exceptions

| Surface | Classification | Reason |
|---|---|---|
| Print / operational documents | KEEP LEGACY | Receipt, invoice, certificate, transcript, payroll slip, enrollment and ID-card rendering are document/print surfaces rather than interactive SPA workspaces. |
| Authentication | KEEP LEGACY | Sign-in and authentication infrastructure remain server-rendered infrastructure boundaries. |

## Migration rule

A transitional surface is not considered architecturally complete merely because it can be styled to look like React. It becomes a React surface only when the authoritative read model, mutation commands, authorization/scope behavior, error taxonomy, provenance, and lifecycle semantics can cross the versioned API boundary without duplication of domain truth.

When that contract does not yet exist, the correct action is to classify the surface explicitly and avoid inventing a client-side authority.
