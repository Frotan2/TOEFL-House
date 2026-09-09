# Verification & Evidence Standard

**STATUS: CURRENT CANONICAL — NORMATIVE**

Evidence levels:

`STATIC < SYNTAX < AUTOMATED TEST < DATABASE RUNTIME < CONCURRENCY RUNTIME < BROWSER/E2E < DEPLOYMENT REHEARSAL < RELEASE CERTIFICATION`

A claim may only be made at the highest level actually executed for the exact commit under discussion.

## Absolute distinctions

`TEST EXISTS ≠ TEST PASSES ≠ TEST PASSES IN OFFICIAL RUNTIME`

`GREEN OLD COMMIT ≠ GREEN CURRENT COMMIT`

`IMPLEMENTED ≠ VERIFIED ≠ RELEASE CERTIFIED`

`DOCUMENTED ≠ EXECUTED`

## Current commit rule

Every release claim must name or otherwise unambiguously identify the exact commit and the corresponding non-superseded evidence. A later commit creates a new evidence boundary.

## Domain rule

For the active material domain, evidence must cover all materially applicable layers: implementation, authority, lifecycle, database integrity, authorization/scope, API, React parity, UX states, audit/provenance, idempotency, concurrency, automated tests, browser/runtime behavior, security, operations and documentation.

## Failure rule

Environment blockers must be reported, not hidden by substituting unsupported runtime versions. A failed or blocked gate must remain visibly failed/blocked until it is actually resolved and re-run.

## Historical rule

Historical audits remain valuable evidence of their own execution line. They must never be silently relabelled as current evidence for a later commit.
