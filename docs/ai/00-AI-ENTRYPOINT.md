# TOEFL House AI Engineering Entry Point

**STATUS: CURRENT CANONICAL — MANDATORY FOR AI AGENTS**

This is the first document an AI engineering agent must read before making repository changes. It is the entry point into the project's institutional memory and engineering control plane.

## Mandatory reading order

1. `../README.md`
2. `../MASTER_ENGINEERING_CONTRACT.md`
3. `../01-ULTIMATE-GOAL.md`
4. `../02-TARGET-ARCHITECTURE.md`
5. `../03-DOMAIN-CAPABILITY-MODEL.md`
6. `../04-DATA-AUTHORITY-PROVENANCE.md`
7. `../05-SECURITY-RBAC-GOVERNANCE.md`
8. the relevant domain manual(s)
9. `../14-CURRENT-STATE-ROADMAP.md`
10. `../15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
11. `../13-TESTING-QUALITY-RELEASE.md`
12. `09-NEXT-AGENT-MANDATORY-HANDOFF.md`
13. this `ai/` governance layer

## State distinction

**Current State ≠ Target State ≠ Release State.**

A historical certification or a previous green run never certifies a later commit. The latest non-superseded Verification evidence controls release state.

## Current operating constraint

**ONE DOMAIN AT A TIME.** The selected domain must reach complete implementation/parity/security/scope/tests/runtime-E2E/UX/documentation closure before the next domain may begin. Partial domains listed by the current-state roadmap are work queues, not completion claims.

## Before editing code

The agent must identify the owning domain, canonical fact, write authority, lifecycle authority, security/scope rules, relevant invariants, affected tests, runtime requirements, target-state impact, and required documentation updates.

## Mandatory re-evaluation

Do not trust the existing capability matrix blindly. Re-inspect backend, frontend, routes, commands, migrations, tests, browser flows, scope boundaries, and UX for the selected domain. Endpoint count is not a parity metric.

## Forbidden behavior

The agent must not invent requirements, authorities, lifecycle states, financial truth, scope rules, duplicate sources of truth, or runtime evidence. It must not bypass canonical commands, weaken tests, create artificial migrations, silently convert deferred work into current scope, or perform blind whole-file rewrites from stale content.

## Completion rule

“Implemented” means code changed. “Verified” means current evidence exists. “Release ready” requires the project's explicit runtime/release gates on the current authoritative commit. Never conflate them.

## Mandatory next-agent handoff

Before starting material implementation, read `09-NEXT-AGENT-MANDATORY-HANDOFF.md`. It is a blocking project operating contract and contains the current domain re-evaluation boundary, release-state warning, security checks, frontend rules, and documentation obligations.
