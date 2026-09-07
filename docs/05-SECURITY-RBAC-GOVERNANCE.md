# TOEFL House — Security / RBAC / Governance

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Identity

Authentication establishes the account. Authorization evaluates the effective actor, active state, roles/positions, capability and scope.

## Authorization

Server-side authorization is mandatory. Frontend visibility never substitutes for permission enforcement.

Central authorization is resolved through the Access authority, including organization/campus/branch scope and actor/position checks.

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

