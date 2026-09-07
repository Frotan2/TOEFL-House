# TOEFL House — Requirement Traceability Registry

**STATUS: CURRENT CANONICAL — NORMATIVE**

Material requirements are tracked as:

`Requirement → Canonical source → Implementation evidence → Test evidence → Runtime status`

| ID | Requirement | Canonical source | Implementation evidence | Test evidence | Runtime status |
|---|---|---|---|---|---|
| R-01 | One authority per business fact | 03, 04 | `app/Modules/*`; architecture contracts | architecture/feature tests | Runtime unverified |
| R-02 | Server-side scoped authorization | 05 | `app/Modules/Access/*` | access/scope tests | Runtime unverified |
| R-03 | Finance owns monetary truth | 07 | `app/Modules/Finance/*` | finance/ledger/payment tests | Runtime unverified |
| R-04 | Payroll proposes; Finance records settlement | 07 | `Payroll/*`, `Finance/*`; settlement ADR | payroll/finance tests | Runtime unverified |
| R-05 | Academic lifecycle has guarded transitions | 06 | `Academic/*`, `Enrollment/*` | academic lifecycle tests | Runtime unverified |
| R-06 | Critical invariants enforced in PostgreSQL | 02, 04, 06, 07 | `database/migrations/*`, incl. `000170`, `000190` | schema/invariant tests | Runtime unverified |
| R-07 | Temporal assignments cannot overlap | 04, 06 | migration `000170` | concurrency/schema tests | Runtime unverified |
| R-08 | Transactional outbox and idempotent consumers | 09 | `Outbox/*`, `Integrations/*` | outbox/consumer tests | Runtime unverified |
| R-09 | Workspace composes, never owns, domain truth | 08 | `Workspace/*` | workspace tests | Runtime unverified |
| R-10 | React is the target operator frontend | 11 | `resources/js/*`; API v1 | frontend/E2E tests | Runtime unverified |
| R-11 | Production deployment is schema-aware | 12 | `deploy/deploy.sh`; production docs | deployment tests/procedures | Runtime unverified |
| R-12 | Release certification requires official runtime | 13 | release process + environment docs | certification procedure | **Blocked by environment** |

## Evidence rules

A test file's existence does not prove passage. A static code path does not prove runtime behavior. Runtime status must be updated only from actual evidence.
