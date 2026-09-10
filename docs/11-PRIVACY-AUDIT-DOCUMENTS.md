# TOEFL House — Security / Privacy / Audit

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Security

Production security requires secure configuration, server-side authorization, protected secrets, safe file handling, secure headers, controlled error exposure and explicit TLS expectations for remote databases.

## Privacy

Private documents and sensitive information require controlled access, lifecycle/retention semantics and attributable access decisions.

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


---

# Consolidated Governing Evidence

