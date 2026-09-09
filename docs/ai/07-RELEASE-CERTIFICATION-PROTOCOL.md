# Release Certification Protocol

**STATUS: CURRENT CANONICAL — NORMATIVE**

A release claim requires the official project runtime, reproducible dependencies, clean PostgreSQL migration, schema verification, full configured tests, static quality gates, frontend production build, critical business journeys, concurrency checks, browser/runtime evidence, deployment/readiness evidence and the other gates defined by `13-TESTING-QUALITY-RELEASE.md` and `12-OPERATIONS-DEPLOYMENT-DR.md`.

## Current-state rule

Release certification is always attached to a specific commit. A later commit requires fresh applicable verification. A historical certification is never inherited automatically.

## Domain closure rule

A material domain must be fully closed before the next material domain starts. Domain closure includes applicable implementation, authority, DB integrity, authorization, scope, API, React parity, UX, audit/provenance, idempotency, concurrency, tests, browser/E2E and documentation evidence.

## Evidence rule

A claim may only be made at the highest evidence level actually executed. In-progress, cancelled, superseded or historical runs do not certify the current `main` HEAD.

## Database rule

Application rollback and database rollback are separate concepts. Schema compatibility must be demonstrated; a symlink rollback alone is not evidence of database rollback.

## Historical certification rule

Dated audit reports remain immutable historical records. They may document what was true for their execution line, but current release status must be represented in `docs/CURRENT-RELEASE-STATUS.md` and proven by the latest non-superseded verification evidence.
