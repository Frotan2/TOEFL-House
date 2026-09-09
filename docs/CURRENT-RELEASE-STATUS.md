# TOEFL House — Current Release Status

**STATUS: CURRENT CANONICAL — NORMATIVE RELEASE SUMMARY**  
**Authority branch:** `main`  
**Last reconciled:** 2026-09-09

## Current release truth

The repository must **not** inherit production-readiness from historical certification documents.

`AUDIT-2026-09-09-FINAL-CERTIFICATION.md` is historical evidence for the execution line that certified commit `96925d3`. It remains preserved for provenance and is not current authority for later commits.

The current release state is determined by the latest non-superseded Verification workflow attached to the actual `main` HEAD, plus any explicitly required manual evidence under the release-certification protocol.

## Current engineering state

- **Organization / Scope:** Covered / strong.
- **Identity:** Covered / strong.
- **Library & Resources:** Implementation complete; current release certification pending current evidence.
- **Access, Students, CRM, Academic, Placement, Teachers, HR, Finance, Payroll, Documents, Privacy, Audit, Communication, Reporting, Management/Work:** Partial and must be closed one domain at a time.
- **Integrations / queue / projection / audit-event internals / database machinery:** Backend-only or operator-oriented unless a specific approved user-facing capability requires otherwise.

## Active gate

**Library & Resources is the active closure domain.**

Do not start another material domain until Library & Resources has passed its applicable implementation, parity, security/scope, test, runtime/E2E, UX and documentation gates.

## Domain closure formula

`Implementation + Backend Authority + DB Integrity + Authorization + Scope + API + React + UX + Audit/Provenance + Idempotency + Concurrency + Tests + Browser/E2E + Security + Documentation = PASS`

## Evidence rules

- `VERIFIED` = actually executed and observed.
- `STATICALLY VERIFIED` = source/config/static evidence only.
- `UNVERIFIED` = insufficient evidence.
- `BLOCKED` = required prerequisite prevented execution.
- `FAILED` = executed and failed.

A documentation statement is never evidence by itself.

## Required reading for the next agent

1. `docs/ai/00-AI-ENTRYPOINT.md`
2. `docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`
3. `docs/MASTER_ENGINEERING_CONTRACT.md`
4. `docs/14-CURRENT-STATE-ROADMAP.md`
5. `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
6. `docs/DOMAIN-REASSESSMENT-2026-09-09.md`
7. `docs/RUNTIME_VERIFICATION_HANDOFF.md`
8. `docs/RUNTIME_ENVIRONMENT_LOCK.md`
9. relevant domain source and tests

Before any release claim, inspect the current branch HEAD and latest Verification run directly.
