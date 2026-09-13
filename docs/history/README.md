# TOEFL House Historical Archive

**STATUS: HISTORICAL — NOT NORMATIVE**

The live repository does not carry large duplicated snapshots of its own history. Git commits, tags and pull-request history are the authoritative historical record.

This directory exists only for durable historical evidence that cannot yet be removed without breaking a current documentation dependency.

## Current authority

Use `../README.md` and the canonical documents at the docs root first. Historical material never overrides current architecture, runtime status, requirements or release evidence.

## Retained exception

`02-architecture-history.md` is temporarily retained because `docs/16-DECISION-REGISTER.md` still uses it as the full-text provenance source for material architecture decisions. It is historical, not normative. Its eventual removal requires first migrating every still-current decision to an explicit canonical decision record and then removing the dependency.

No other large history snapshot is retained here.

## Retention rule

Do not add a new historical snapshot here merely to record a completed engineering task. Record the task in Git history and update the single canonical document that owns the current subject. Add a file here only when it contains durable provenance that cannot be reconstructed from repository history.
