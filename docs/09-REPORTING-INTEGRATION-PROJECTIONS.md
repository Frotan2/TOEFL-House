# TOEFL House — Reporting / Integration / Projections

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Reporting

Reporting is read-side authority only. It may calculate metrics, build projections and run invariant audits, but it must never become a second ledger or lifecycle authority.

## Projection rules

Derived state should have:

- explicit source authority
- deterministic projection rules
- rebuild/replay strategy where required
- lineage to the source fact

## Outbox

Committed domain facts cross process/integration boundaries through a transactional outbox. Delivery state is not business truth.

## Consumers

Consumers must be idempotent and independently observable. Retry behavior must not create duplicate domain facts.

## Failure handling

The target operating model includes replay and dead-letter handling so failed delivery can be diagnosed and recovered without rewriting source truth.

## External integrations

External systems are adapters at the boundary. They may request or receive facts; they do not become owners of TOEFL House business state.


---

# Consolidated Governing Evidence

