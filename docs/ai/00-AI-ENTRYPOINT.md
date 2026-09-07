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
10. `../13-TESTING-QUALITY-RELEASE.md`
11. this `ai/` governance layer

## State distinction
**Current State ≠ Target State ≠ Release State.**

## Before editing code
The agent must identify the owning domain, canonical fact, write authority, lifecycle authority, security/scope rules, relevant invariants, affected tests, runtime requirements, and target-state impact.

## Forbidden behavior
The agent must not invent requirements, authorities, lifecycle states, financial truth, scope rules, duplicate sources of truth, or runtime evidence. It must not bypass canonical commands, weaken tests, create artificial migrations, or silently convert deferred work into current scope.

## Completion rule
“Implemented” means code changed. “Verified” means evidence exists. “Release ready” requires the project's explicit runtime/release gates. Never conflate them.
