# TOEFL House — Requirement Traceability Registry

**STATUS: CURRENT CANONICAL — NORMATIVE**

Material requirements are tracked as:

`Requirement → Canonical source → Implementation evidence → Test evidence → Runtime status`

Runtime status column updated 2026-09-09 from the executed evidence behind
`AUDIT-2026-09-09-FINAL-CERTIFICATION.md` (locked runtime: PHP 8.4.14,
PostgreSQL 18.4). "Carried" marks evidence executed 2026-09-08 and carried
forward unchanged by that certification.

| ID | Requirement | Canonical source | Implementation evidence | Test evidence | Runtime status |
|---|---|---|---|---|---|
| R-01 | One authority per business fact | 03, 04 | `app/Modules/*`; architecture contracts | architecture/feature tests | **Verified** — architecture contracts + full suite green in official runtime |
| R-02 | Server-side scoped authorization | 05 | `app/Modules/Access/*` | access/scope tests | **Verified** — suite green; journey RBAC probes deny and persist nothing |
| R-03 | Finance owns monetary truth | 07 | `app/Modules/Finance/*` | finance/ledger/payment tests | **Verified** — payment journey 29/29; ledger balanced end-to-end |
| R-04 | Payroll proposes; Finance records settlement | 07 | `Payroll/*`, `Finance/*`; settlement ADR | payroll/finance tests | **Verified** — payroll journey 27/27 (liability recognition → exactly-once journal) |
| R-05 | Academic lifecycle has guarded transitions | 06 | `Academic/*`, `Enrollment/*` | academic lifecycle tests | **Verified** — student journey 77/77 from true first boot |
| R-06 | Critical invariants enforced in PostgreSQL | 02, 04, 06, 07 | `database/migrations/*`, incl. `000170`, `000190` | schema/invariant tests | **Verified** — `verify:invariants` 6/6 against the migrated schema |
| R-07 | Temporal assignments cannot overlap | 04, 06 | migration `000170` | concurrency/schema tests | **Verified** — `verify:concurrency` 4/4 (incl. overlapping room-slot exclusion) |
| R-08 | Transactional outbox and idempotent consumers | 09 | `Outbox/*`, `Integrations/*` | outbox/consumer tests | **Verified in suite**; live relay intentionally not enabled by the synchronous deployment (`operations/production-deployment.md` §9–10) |
| R-09 | Workspace composes, never owns, domain truth | 08 | `Workspace/*` | workspace tests | **Verified** — suite green; console mount 8/8; browser E2E carried (2026-09-08) |
| R-10 | React is the target operator frontend | 11 | `resources/js/*`; API v1 | frontend/E2E tests | **Verified** — typecheck/build/mount green; browser E2E 21/21 carried (2026-09-08) |
| R-11 | Production deployment is schema-aware | 12 | `deploy/deploy.sh`; production docs | deployment tests/procedures | **Verified** — deployment rehearsal (2026-09-08) + DR drill and schema-compatibility cycle re-executed 2026-09-09; launcher contract green in suite |
| R-12 | Release certification requires official runtime | 13 | release process + environment docs | certification procedure | **Satisfied** — certification executed on the official runtime 2026-09-09 (`AUDIT-2026-09-09-FINAL-CERTIFICATION.md`) |

## Evidence rules

A test file's existence does not prove passage. A static code path does not prove runtime behavior. Runtime status must be updated only from actual evidence.
