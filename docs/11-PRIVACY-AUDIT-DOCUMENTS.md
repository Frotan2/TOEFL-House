# TOEFL House — Security / Privacy / Audit

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Security

Production security requires secure configuration, server-side authorization, protected secrets, safe file handling, secure headers, controlled error exposure and explicit TLS expectations for remote databases.

## Privacy

Private documents and sensitive information require controlled access, lifecycle/retention semantics and attributable access decisions.

Personal data follows the same rule with one addition: consent is a recorded fact with a lifecycle, never a boolean flag, and erasure is expressed as revocation, expiry and archive over an append-only history — never as a row deletion. A subject may read their own dossier and submit or revoke their own consent without holding any staff capability; every other act over that subject's data requires a capability inside the subject's own branch.

## Audit

Audit evidence records material actions and attempts. It is append-oriented evidence, not a replacement for source-domain state.

## Historical integrity

Audit history must remain trustworthy across corrections, retries, failed operations and lifecycle transitions.

## Evidence boundary

Security and privacy policies are only “achieved” to the extent that enforcement exists in code/database/operations. Documentation alone is not proof.

## Documents & Evidence workspace transport

`/documents` is the canonical React operator workspace. It uses the same-origin,
session-authenticated `/api/v1/documents` transport; the browser only renders a
server projection and submits intent to existing Documents commands.

- `GET /api/v1/documents` independently resolves active branch authority for
  `documents.register`, `documents.verify`, and `documents.retention`, then
  scopes people and documents to their union. A branch hint is never authority.
- `documents.classify` is evaluated only at organization root. A classifier with
  no document branch scope can load the policy catalog, but receives no people
  or documents. A caller with neither catalog authority nor a concrete document
  branch is denied and that denial is audit-recorded.
- The workspace read model excludes `storage_ref`, content fingerprints, and
  verification rationales. `GET /api/v1/documents/{documentId}/history` is a
  separately authorized, immutable evidence projection; it still never exposes
  storage references.
- Per-document UI affordances are server-projected from that document's branch
  scope. They reduce misleading controls but are not permission grants: every
  mutation re-enters the owning command, which re-checks authority, lifecycle,
  separation of duties, idempotency, and audit evidence.
- Existing web POST endpoints remain thin compatibility adapters. The old Blade
  documents index is retired so it cannot become a second documents read model.

## Privacy & Consent workspace transport

`/privacy` is the canonical React operator workspace. It uses the same-origin,
session-authenticated `/api/v1/privacy` transport; the browser only renders a
server projection and submits intent to existing Privacy commands.

- `GET /api/v1/privacy/workspace` independently resolves active branch authority
  for `privacy.define_purpose`, `privacy.consent`, `privacy.disclose`,
  `privacy.export`, and `privacy.approve_bulk_export`, then scopes purposes,
  consents, disclosures and export requests to their union. A branch hint is
  never authority. An actor holding no privacy capability at all is denied
  (`api.organization_read_denied`) and that denial is audit-recorded under
  `privacy.api.workspace`.
- `GET /api/v1/privacy/subjects/{subjectId}` is the subject dossier: identity,
  the whole consent history with each revocation attached to the consent it
  ended, every disclosure, and every export request the organization holds. It
  is denied (`api.read_denied`) unless the actor holds a privacy capability
  inside the subject's own branch — or is the subject. Reading your own dossier
  is a subject right, not a staff privilege.
- Consent lifecycle legality lives in `ConsentLifecycle`, export staging in
  `ExportApprovalChain`, scope arithmetic in `PrivacyScopePolicy`, and the
  one-open-consent boundary in a partial unique index. The API re-enters the
  owning command on every write, which re-checks authority, subject provenance,
  lifecycle legality, separation of duties, idempotency and audit evidence. The
  browser re-derives none of it.
- Per-row consent affordances (submit, verify, activate, expire, revoke,
  archive) are server-projected from the granted scope AND that single lifecycle
  table. They reduce misleading controls but are not permission grants. `expire`
  additionally requires the recorded `effective_to` window to have lapsed,
  decided by the CalendarAuthority and never by a client clock.
- Separation of duties is structural, not advisory: an organization-wide export
  needs two distinct approver signatures, the requester can never be one of them,
  and both `privacy.export.approve` and `privacy.export.execute` are
  organization-scoped — so a branch-scoped officer may request an export but can
  never sign or execute one.
- Personal data is never deleted. Revocation, expiry and archive are lifecycle
  states over append-only rows: `Consent::delete()` and `Consent::forceDelete()`
  throw `privacy.consent_immutable`, and the export-request trigger refuses any
  rewrite of its approval history.
- Existing web POST endpoints remain thin compatibility adapters. The old Blade
  privacy index and the `/governance/privacy` alias are retired so they cannot
  become a second privacy read model.


---

# Consolidated Governing Evidence

