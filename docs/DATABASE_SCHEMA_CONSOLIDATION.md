# TOEFL House — Database Schema Consolidation & Baseline Readiness

**Status:** BASELINE DEFERRED — SCHEMA FREEZE REQUIRED

> **Progress note (2026-09-10):** the locked runtime (PostgreSQL 18.4 via
> `scripts/runtime/provision.sh`) can replay the entire **202-migration** chain
> from zero. The deferral decision stands: physical consolidation still requires
> a schema freeze, a reviewed schema-only capture, and the full equivalence
> procedure of §5 before any chain removal.

**Assessment date:** 2026-09-10

## 1. Engineering decision

The migration history must **not** be blindly deleted or squashed at this point.

The repository contains exactly **202 ordered migration files**, with ordinals ending at `000207` (the `000175`–`000179` numbering gap remains historical). The late part of the chain is not historical noise: migrations `000160`–`000207` contain active domain convergence, authority, provenance, financial, reporting, temporal, accounting, privacy, recovery, and capacity hardening. Several materially change the canonical schema.

The project remains pre-production, so eventual consolidation is reasonable **after a schema freeze**. The current environment can execute PostgreSQL and Laravel migrations, but no approved physical baseline artifact or baseline-versus-chain schema diff exists yet. A successful chain replay is necessary evidence, not a substitute for that equivalence proof.

**Decision:** establish the migration chain as the current implementation baseline, prepare the repository for a canonical baseline, but defer physical consolidation until the reviewed schema snapshot and equivalence procedure produce deterministic evidence.

## 2. Current migration inventory

The current Git tree contains migrations `000001` through `000207`, with no files after `000207`. Numeric gaps `000175`–`000179` exist. Those gaps are not themselves defects; they are historical numbering gaps and are permitted by this policy. Duplicate migration numbers are not permitted.

The migration history contains these important phases:

- `000001`–`000093`: initial domain schema for Organization, Identity, Access, Privacy, Documents, Admissions/Students, Academic, HR/Payroll, Finance, Resources/Library, Communication, Reporting and Integrations.
- `000094`–`000116`: authority attribution, delivery facts, retired structures, credentials, database guards, lifecycle hardening and staged governance.
- `000117`–`000125`: access independence, payroll disbursement protection and branch/scope provenance.
- `000126`–`000138`: CRM, Placement, Student lifecycle, Academic offerings/waitlist, rooms/sections/timetable, eligibility, financial gate, progression, completion, certificate and transcript structures.
- `000139`–`000159`: waitlist/authority/financial correction, outbox, liability, work management, reporting provenance and student/admission hardening.
- `000160`–`000174`: current major convergence of Academic, Teacher, Placement, Finance, Reporting, Campus Assignment and CRM authority.
- `000180`–`000190`: financial coverage, expenses, cash drawer, scholarships, journal/ledger authority, employment settlement authority and offering/class capacity protection.
- `000191`–`000207`: resource custody/provenance/history hardening, communication and work-management convergence, release-signoff/attendance/admission guards, retired payroll authority, notification-recipient projection, teacher-employment activation, assessment lineage, privacy evidence, and reporting/integrations recovery hardening.

## 3. Canonical-schema rule

The canonical database is the **resulting schema after all currently accepted migrations**, not the historical intermediate schemas represented by every migration file.

Known historical retirement already exists: migrations `000096` and `000097` deliberately remove `compensation_components` and `work_bases`. A future baseline must therefore omit those obsolete structures rather than resurrect them.

No baseline may be constructed by guessing table definitions from domain documentation alone. It must be generated from a verified PostgreSQL schema produced by the accepted migration chain or an equivalent approved schema artifact.

## 4. Reference data boundary

Schema definition and mutable/reference data are separate concerns.

Migration `000186_seed_standard_finance_chart.php` currently seeds a canonical chart of accounts using `INSERT ... ON CONFLICT DO NOTHING`. It is retained as historical migration behavior so existing migration chains are not silently rewritten. A dedicated `StandardFinanceChartSeeder` has been added for the future baseline/initialization path.

A future canonical baseline must not encode ordinary mutable business transactions. Reference/system data belongs in explicit seeders or reference-data loaders unless a specific immutable, versioned data requirement is approved.

## 5. Baseline creation gate

A physical canonical baseline is allowed only after all of the following are available:

1. Disposable PostgreSQL database.
2. Successful replay of the current migration chain.
3. Authoritative schema dump (`schema-only`, no owner/privilege noise).
4. Inventory of tables, columns, nullability, defaults, PK/FK/unique/check/exclusion constraints, indexes, sequences, types, extensions, functions, triggers, views and materialized views.
5. Explicit review of all intentional differences from historical intermediate schema states.
6. Fresh-database replay from the baseline producing an equivalent schema.
7. Laravel boot and migration-state verification against the baseline.
8. Relevant invariant/concurrency test execution.

Without these gates the baseline status is **UNVERIFIED**, not complete.

## 6. Existing databases

No pre-existing development, staging, or production database is part of this
repository workspace. Therefore:

**DATABASE RUNTIME RECONCILIATION — UNVERIFIED**

No existing database will be dropped, rebuilt, or assigned fake migration
history as part of this phase. When an existing database is brought into a
baseline operation, snapshot/back it up first and compare it against the
canonical schema. Data preservation takes priority over migration-table
cosmetic cleanup.

## 7. Future migration discipline

After a canonical baseline is formally established:

- every schema modification MUST be represented by a version-controlled Laravel migration or another explicitly approved Laravel schema artifact;
- developers MUST NOT manually mutate the canonical database as the normal workflow;
- emergency/operational DDL is allowed only under controlled procedure and MUST subsequently be reconciled into version-controlled migration/schema artifacts;
- migration numbers must be unique and monotonically ordered by timestamp/naming convention;
- a migration must not silently become a seed mechanism for mutable business data;
- schema drift must be detected before release.

The fact that the project has many migrations is **not** by itself a defect.

## 8. Verification classification

Rows updated 2026-09-10 against the locked runtime
(`scripts/runtime/provision.sh`, PostgreSQL 18.4):

| Area | Status |
|---|---|
| Migration inventory | STATICALLY REVIEWED |
| Historical retirement detection | STATICALLY REVIEWED |
| Reference-data separation design | IMPLEMENTED |
| Canonical physical baseline | UNVERIFIED — baseline not yet generated (deferred behind schema freeze) |
| Fresh PostgreSQL database | **VERIFIED (2026-09-10)** — current 202-file chain replayed by the transaction-isolated test process before the Academic suite |
| Existing DB reconciliation | UNVERIFIED — no pre-existing production database is available in this workspace to reconcile |
| Old-chain vs baseline schema diff | UNVERIFIED — baseline not yet generated |
| PostgreSQL constraint execution | **VERIFIED (2026-09-10)** — 6/6 exact named invariant boundaries and 4/4 real production-table concurrency races passed on the migrated schema |
| Laravel migration/runtime boot | **VERIFIED (2026-09-10)** — the Academic suite booted the application on the locked runtime |

## 9. Required next runtime operation

Run the migration-chain replay in a disposable PostgreSQL database, export the schema-only definition, and use that exact artifact as the input to physical baseline construction. The repository must not claim `DATABASE CONVERGED` until the fresh-baseline replay and schema equivalence checks are runtime-verified.

The first half of this operation (fresh replay) is now routine on the current
202-file chain. What remains gated is the *baseline construction and equivalence
proof* itself, pending a schema freeze.
