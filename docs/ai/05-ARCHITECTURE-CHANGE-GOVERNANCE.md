# Architecture Change Governance

**STATUS: CURRENT CANONICAL — NORMATIVE**

Before changing a module boundary, authority, aggregate, event, projection, migration contract, API contract, frontend boundary, financial meaning, lifecycle, RBAC model or scope semantics, the agent must document:

- current authority;
- reason the current rule is inadequate;
- affected domains and invariants;
- alternatives considered;
- migration/compatibility impact;
- tests and evidence required;
- documentation that must change.

No new source of truth may be introduced merely to avoid integrating with an existing authority.
