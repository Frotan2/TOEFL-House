# ADR — Database Baseline Readiness and Migration Consolidation

**Date:** 2026-09-07  
**Status:** CURRENT — updated 2026-09-10
**Decision:** Defer physical migration consolidation until a PostgreSQL schema freeze; runtime verification is a required baseline input and is now available.

## Context

TOEFL House currently has exactly 202 migration files (ordinals `000001`–`000207`; the `000175`–`000179` numbering gap is historical). Many of the newest migrations are not disposable history: they contain active domain convergence and database integrity hardening. The project is still pre-production, so a clean baseline is desirable once the schema is stable.

A migration count is not a correctness metric. The engineering objective is one canonical schema with controlled versioned evolution, not the smallest possible number of files.

## Decision

1. Do not delete the existing migration chain during this phase.
2. Treat the fully replayed current migration chain as the authoritative implementation candidate for the present schema.
3. Prepare a dedicated canonical baseline only from a verified PostgreSQL schema snapshot.
4. Exclude obsolete structures already retired by the migration chain, such as `compensation_components` and `work_bases`.
5. Keep reference/system data out of the physical schema baseline. Use explicit seeders/reference loaders for such data.
6. After the baseline is established, every schema change must use a new version-controlled Laravel migration/schema artifact.
7. Existing development/staging databases must be preserved and reconciled; migration history must never be faked to make a database appear current.
8. Emergency/manual DDL may be used only under controlled operational procedure and must be reconciled into version control afterward.

## Why consolidation is not executed yet

The locked runtime can now replay the current chain on PostgreSQL 18.4, so an
unavailable environment is no longer the blocker. Physical consolidation still
requires an explicit schema freeze, a reviewed schema-only capture, and a
baseline-versus-chain equivalence replay. None of those may be inferred merely
from a successful migration run.

Creating a guessed baseline now would risk silently dropping functions, triggers,
exclusion constraints, defaults, indexes, or late hardening. That would violate
the project's evidence-first and migration-truth rules.

## Consequences

The repository temporarily continues to carry the historical migration chain. This is intentional. The chain remains reproducible until a verified baseline is produced.

A future baseline operation will be a controlled repository change with:

- disposable-chain replay,
- canonical schema capture,
- schema/invariant inventory,
- baseline replay,
- old-chain/baseline diff,
- Laravel boot/migration-state verification,
- data-preservation reconciliation for any existing non-production database,
- documentation and release-gate update.

## Supersession

This decision may be superseded only by an explicit later database architecture decision after the above evidence gates are satisfied.
