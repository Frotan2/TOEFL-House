# TOEFL House — Security / RBAC / Governance

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Identity

Authentication establishes the account. Authorization evaluates the effective actor, active state, roles/positions, capability and scope.

## Authorization

Server-side authorization is mandatory. Frontend visibility never substitutes for permission enforcement.

Central authorization is resolved through the Access authority, including organization/campus/branch scope and actor/position checks.

Employment eligibility is part of authorization rather than an HR concern consumed from
outside it: a person with no employment relationship may hold explicitly granted
authority (an appointed owner or auditor), and once HR owns any employment row for that
person, only the newest row being `active` keeps it. Each source of scope — role-derived
grants, direct grants, delegations, and each delegator inside a delegation — consults
the same eligibility fact and denies on failure; it never narrows a decision instead.

A decision may memoize actor-level facts for its own duration and no longer. The
container binding for the resolver is a singleton, so a request-lifetime cache would be
correct under PHP-FPM and wrong under any long-running worker; "cleared at the start of
each decision" is the only lifetime that is correct in both, which is why
`AccessResolution` does it that way and why `tests/Feature/Access/EmploymentEligibilityResolutionTest`
re-resolves on purpose between two decisions.

Resolving which branches an actor may see costs one decision per active branch — 13
queries each as measured on 2026-09-08, bounded by branch count and never by row count.
The per-branch loop is a fail-closed choice, not an oversight (see the comment on
`Controller::authorizedBranches`); collapsing it is a decision change, not a
micro-optimization.

## Scope doctrine

Absent/unknown branch context is never a wildcard. Scope must be explicit and must propagate to every protected read/write boundary.

## Separation of duties

Sensitive workflows distinguish proposal, approval, confirmation, execution and financial recognition where required.

## Governance

Access to sensitive operations must be attributable and auditable. Denied attempts are evidence of an attempted operation, not successful business events.

## Fail-closed doctrine

When the system cannot establish the required authority, actor or scope, the operation must fail closed rather than infer broader access.

## Security status

Static architecture establishes strong controls. Runtime certification of all paths remains a separate release gate.


---

# Consolidated Governing Evidence

