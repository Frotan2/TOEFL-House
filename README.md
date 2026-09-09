# The TOEFL House

An enterprise education-management system built as a Laravel 12 modular monolith on PostgreSQL 18 with a React/TypeScript employee console.

**Engineering principle:** one fact → one owner → one write authority → one lifecycle.

## Current engineering control

The repository uses a single-source documentation architecture. Start with [`docs/OPERATING-CONTROL.md`](docs/OPERATING-CONTROL.md): it defines the active domain, current execution state, agent protocol and completion gates.

Domain ownership, maturity, backend↔frontend parity and requirement status live only in [`docs/DOMAIN-REGISTRY.md`](docs/DOMAIN-REGISTRY.md).

Runtime, verification and release evidence rules live only in [`docs/RUNTIME-RELEASE.md`](docs/RUNTIME-RELEASE.md).

Permanent engineering/business rules are governed by [`docs/MASTER_ENGINEERING_CONTRACT.md`](docs/MASTER_ENGINEERING_CONTRACT.md), with accepted target architecture in [`docs/02-TARGET-ARCHITECTURE.md`](docs/02-TARGET-ARCHITECTURE.md).

## Mandatory execution rule

**ONE MATERIAL DOMAIN AT A TIME.** Do not begin another material domain until the active domain passes its applicable implementation, authority, database, authorization, scope, API, React, UX, audit/provenance, idempotency, concurrency, testing, browser/E2E and operational evidence gates.

Historical audits are provenance only. A previous green run or certification never certifies a later commit.

## Quickstart

Use `START-TOEFL-HOUSE.bat` on supported Windows environments. For clean environments use the project runtime provisioner and [`SETUP.md`](SETUP.md).

## Verification

Use the official Verification workflow and the commands defined in [`docs/RUNTIME-RELEASE.md`](docs/RUNTIME-RELEASE.md). Never claim runtime readiness from documentation alone.

## Repository map

| Path | Responsibility |
|---|---|
| `app/Modules/<Domain>/` | Domain commands, queries and models — business write authorities |
| `app/Http/Controllers/` | Transport boundaries |
| `resources/js/` | React operational presentation and interaction |
| `database/migrations/` | PostgreSQL schema and durable invariants |
| `deploy/` | Deployment/recovery tooling |
| `docs/` | Canonical engineering documentation; start at [`docs/README.md`](docs/README.md) |
| `docs/decisions/` | Architecture Decision Records indexed by `docs/16-DECISION-REGISTER.md` |