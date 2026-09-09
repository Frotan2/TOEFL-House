# TOEFL House — Placement, Teachers & HR Lifecycle Boundary Audit

## Scope

This audit is limited to:

- Placement qualification/recommendation/review/moderation/approval/release.
- Teacher qualification, branch authority, skill authority, availability, workload, assignment and delivery/assessment authority.
- HR employment/status/leave facts that determine whether a teacher is operational.
- Progression evidence where it crosses assessment/teacher authority boundaries.
- API/React parity, provenance, idempotency, concurrency and regression/E2E coverage for the above.

No normal-domain redesign was introduced.

## Canonical authority model

The repository uses command/domain seams as the write authority. The Employee API is versioned under `/api/v1`, session-authenticated and delegates mutations to the same command surface used by the web console. The API session stack has explicit regression coverage for cookie/session/CSRF behavior, and the Teacher test fixtures deliberately construct teachers through production commands rather than direct inserts.

Teacher eligibility is a conjunctive fact, not a role label:

1. verified person identity;
2. active canonical TeacherProfile;
3. current verified qualification;
4. effective branch authorization;
5. active HR employment status on the academic date;
6. no approved leave covering the date;
7. effective skill authority when a skill is required;
8. effective assignment to the class for teacher-specific delivery/assessment actions.

## Confirmed findings and changes

### P1 — Teacher workspace leaked foreign branch child facts

**Location:** `app/Http/Controllers/Api/TeacherApiController.php`

Teacher profiles were already selected using the viewer's server-derived branch scope, but child projections could still return branch-specific assignments, branch authorizations, skill authorities, availability, and workload limits belonging to other branches. The result was a read-side scope violation even when the profile itself was legitimate for the viewer.

**Fix:** filter branch-scoped child collections and assignment projections by the actor's authorized branch set. The teacher's own profile remains intentionally visible across its authorized branches, while qualifications remain global profile evidence.

**Regression:** `tests/Feature/Api/TeacherWorkspaceScopeTest.php` creates two branches, a teacher with legitimate cross-branch authority, separate branch-A/branch-B operational facts, authentic session login, and asserts that a branch-A manager receives only branch-A operational evidence.

### P1 — Teacher weekly workload accounting was inverted

**Location:** `app/Modules/Academic/Domain/TeacherAuthority.php`

The workload calculation skipped sessions that had an effective teacher assignment, then counted other sessions. That made assigned delivery hours disappear from the teacher's weekly workload and could allow a teacher to exceed their configured limit.

**Fix:** assigned, non-cancelled sessions with an effective teacher assignment for the same profile and branch are now counted toward weekly used hours before the proposed session is added.

**Regression:** `tests/Feature/Academic/TeacherWorkloadLimitFeatureTest.php` drives the canonical class → teacher assignment → schedulable skill/availability → workload limit → session path and verifies that a second two-hour session is rejected after a two-hour weekly limit is consumed.

### Existing lifecycle hardening retained in this scoped branch

The branch also contains earlier Placement/Teacher lifecycle hardening that remains within the requested boundary:

- independent final release signoff for Placement and Assessment results;
- database triggers protecting release independence against alternate writers;
- TeacherProfile protection against registration against a non-effective HR employment state;
- temporal employment/teacher boundary regression coverage.

These controls preserve SoD and provenance instead of introducing alternate business authority.

## Non-findings intentionally left unchanged

### Placement eligibility snapshot `version_no`

Reviewed as a possible lineage defect. The repository's current semantics use the snapshot predecessor (`supersedes_snapshot_id`) as the lineage chain, while `version_no` is per snapshot/profile context. Existing retake coverage is consistent with that model. No confirmed contradiction was found, so no change was made.

### Progression approval assessment linkage

Reviewed for a possible approval-time provenance gap after enrollment transfer. The current command/model boundary and existing evidence checks did not provide enough proof of a real bypass in the authoritative transition path. No speculative redesign was introduced.

### Scheduling concurrency

The canonical scheduling path locks the class and applies scheduling constraints against effective teacher assignments before creating the session. No confirmed race that bypasses the assignment/conflict boundary was established during this pass.

## API / React parity

The API surface remains a transport over canonical commands rather than a parallel business layer. Teacher mutations (`register`, qualification, qualification verification, transition, branch transfer/authorization, skill authority, availability, workload, assignment, end/extend/handover and assignment-skill attribution) delegate to the domain command layer. The React API client participates in the same session/idempotency contract; no second teacher authority implementation was added.

## Idempotency

Teacher and Placement mutations already pass idempotency keys into their canonical commands. No duplicate authority layer was introduced. The new regressions use unique keys and continue to exercise the production command idempotency surface.

## Verification status

The branch triggered GitHub Actions after the scoped fixes. The previous verification run established:

- Composer dependency installation/platform checks: pass.
- Frontend typecheck: pass.
- Frontend build: pass.
- Database migrations/invariants: pass.
- Concurrency checks: pass.
- Canonical backend suite: reached and executed successfully before the full backend suite completed.

That earlier run was blocked by:

- Pint finding formatting issues, including branch-specific formatting in the new Teacher files plus unrelated baseline files.
- Frontend parity test failure in a pre-existing file outside the branch's Teacher/Placement/HR net changes.
- Browser E2E failure; its next run is required for current-head evidence.

A fresh Verification run was triggered for the latest scoped commit and must be used for closure rather than relying on stale results.

## Closure rule

Do not declare this branch closed until the latest head has current evidence for:

1. static analysis / PHPStan;
2. backend canonical + full test suite;
3. migration/database invariant checks;
4. concurrency checks;
5. frontend typecheck/build/parity tests;
6. real Chromium Browser E2E;
7. no unrelated net diff relative to the effective `main` baseline.
