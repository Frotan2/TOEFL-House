# Release Certification Protocol

**STATUS: CURRENT CANONICAL — NORMATIVE**

A release claim requires the official project runtime, reproducible dependencies, clean PostgreSQL migration, schema verification, full configured tests, static quality gates, frontend production build, critical business journeys, concurrency checks, deployment rehearsal and readiness verification as defined by `13-TESTING-QUALITY-RELEASE.md` and `12-OPERATIONS-DEPLOYMENT-DR.md`.

Application rollback and database rollback are separate concepts. Schema compatibility must be demonstrated; a symlink rollback alone is not evidence of database rollback.
