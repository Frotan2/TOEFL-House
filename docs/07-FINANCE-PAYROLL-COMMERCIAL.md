# TOEFL House — Finance / Payroll / Commercial

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Financial authority

Finance is the sole canonical authority for monetary facts:

- obligations
- invoices
- balances
- payments
- allocations
- refunds
- cash
- journal entries / ledger
- corrections
- funds/budgets where present
- recorded settlements

Payroll calculates and proposes. Finance records the monetary settlement fact.

## Invariants

Financial workflows must be protected by:

- idempotency
- immutable source facts where appropriate
- transactional state changes
- unique references
- amount/check constraints
- ledger/source linkage
- correction/reversal semantics
- actor and approval controls

## Payment model

A payment is not financial truth merely because a UI says “paid.” It becomes authoritative through Finance transaction paths and corresponding ledger/source records.

## Refunds

Refunds are governed financial corrections/reversals and must not silently delete the original payment fact.

## Cash

Cash-drawer movements must preserve transaction history and prevent phantom or duplicated cash under concurrent mutation.

## Payroll

Payroll output is workflow evidence/proposal until Finance recognizes a settlement fact. No parallel final-settlement monetary authority is permitted.

## Target-state commercial breadth

Bank reconciliation, procurement/AP, donor/aid depth and broader inventory/asset workflows remain approved target capabilities where not yet complete.


---

# Consolidated Governing Evidence

