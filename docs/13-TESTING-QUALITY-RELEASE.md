# TOEFL House — Testing & Quality Contract

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** `docs/RUNTIME-RELEASE.md` for runtime/release conclusions  
**Scope:** test architecture and quality expectations

## Evidence hierarchy

1. test exists;
2. test currently passes;
3. test passes in the official runtime;
4. release-critical behavior is exercised in a production-like environment.

These are distinct claims and must never be conflated.

## Required layers

Applicable verification may include:

- PHP syntax/lint;
- unit/feature/integration tests;
- database schema/invariant tests;
- authorization/scope tests;
- real-PostgreSQL concurrency tests;
- frontend type/build/mount checks;
- browser/E2E;
- security/adversarial tests;
- deployment/recovery rehearsals.

## Test isolation and fixtures

Use the repository's enforced test-isolation strategy and existing shared fixture/provenance helpers. Never weaken a correct database invariant, authorization rule or assertion merely to obtain green output.

Concurrency, database-invariant and browser gates that require committed state belong outside PHPUnit and are executed through their dedicated verification commands.

## Quality standard

A material change must include the strongest practical regression coverage for its authority, lifecycle, scope and compatibility boundary. Domain closure also requires current runtime/browser evidence where applicable.

## Release boundary

This document defines **what must be tested**. Current runtime versions, official verification outcomes, evidence semantics and release certification are defined only in `docs/RUNTIME-RELEASE.md` and `.github/workflows/verification.yml`.

`IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`. A historical green run never certifies a later commit.
