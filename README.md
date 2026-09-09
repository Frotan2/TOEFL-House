# The TOEFL House

An enterprise education-management system: a **Laravel 12 modular monolith** (Academic, Admissions/Students, Finance, Payroll, HR, CRM, Placement, Reporting, Organization/Access/Identity) on **PostgreSQL 18**, with a React/TypeScript employee console served by the same application.

One fact → one owner → one write authority → one lifecycle. Business truth lives in domain commands and database-enforced invariants; the frontend is a presentation/intent layer; every module has exactly one write path.

---

## Release status

**Current release status is determined only by the latest non-superseded Verification workflow on the actual `main` HEAD.** Historical certification documents do not automatically certify later commits.

See [`docs/CURRENT-RELEASE-STATUS.md`](docs/CURRENT-RELEASE-STATUS.md) for the current release-state contract and [`docs/README.md`](docs/README.md) for the canonical documentation chain.

The historical [`docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md`](docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md) records a prior execution line at commit `96925d3`; preserve it for provenance, but do not use it as current release authority for later `main` commits.

CI verifies every push through `.github/workflows/verification.yml`: frontend (typecheck, build, mount), static analysis (Pint, PHPStan, migration and terminology audits), backend (migration replay, suite, database invariants, concurrency), and browser E2E where the runner provides Chromium.

---

## Mandatory AI/agent operating rule

**ONE DOMAIN AT A TIME.** Before material implementation, every AI agent must read:

1. [`docs/ai/00-AI-ENTRYPOINT.md`](docs/ai/00-AI-ENTRYPOINT.md)
2. [`docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`](docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md)
3. [`docs/MASTER_ENGINEERING_CONTRACT.md`](docs/MASTER_ENGINEERING_CONTRACT.md)
4. [`docs/14-CURRENT-STATE-ROADMAP.md`](docs/14-CURRENT-STATE-ROADMAP.md)
5. [`docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`](docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md)
6. [`docs/DOMAIN-REASSESSMENT-2026-09-09.md`](docs/DOMAIN-REASSESSMENT-2026-09-09.md)
7. [`docs/RUNTIME_VERIFICATION_HANDOFF.md`](docs/RUNTIME_VERIFICATION_HANDOFF.md)
8. [`docs/RUNTIME_ENVIRONMENT_LOCK.md`](docs/RUNTIME_ENVIRONMENT_LOCK.md)

No agent may move to the next material domain until the active domain passes its applicable implementation, parity, security/scope, tests, runtime/E2E, UX, audit/provenance and documentation gates.

---

## Quickstart

### Windows one-click (supported convenience path)

Run `START-TOEFL-HOUSE.bat`. It provisions its pinned runtimes under `.runtime\` (PHP, Composer, Node, PostgreSQL), installs dependencies from the committed lockfiles, builds the employee console, creates and migrates a local PostgreSQL 18 cluster, performs the guarded first-run bootstrap, serves the app at `http://127.0.0.1:8080` and checks `/health`. Full detail in [`SETUP.md`](SETUP.md).

### Linux / clean environment (reproducible provisioner)

Use the project provisioner and runtime lock rather than reconstructing versions from historical reports.

```bash
bash scripts/runtime/provision.sh
source scripts/runtime/env.sh
bash scripts/runtime/pg.sh start
bash scripts/runtime/pg.sh createdbs
composer install
npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate --force
```

Production deployments use the nginx + PHP-FPM procedure in [`docs/operations/production-deployment.md`](docs/operations/production-deployment.md).

---

## Verification

```bash
npm run verify:environment
composer validate --strict
composer check-platform-reqs
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php scripts/database-migration-audit.php
php scripts/terminology-audit.php
php artisan test
npm run typecheck && npm run build && npm run test:frontend
npm run verify:invariants
npm run verify:concurrency
npm run verify:browser
```

For release-critical business journeys, use the root-level real-HTTP journey scripts documented by `docs/RUNTIME_ENVIRONMENT_LOCK.md` and the current runtime handoff.

---

## Repository map

| Path | What lives here |
|---|---|
| `app/Modules/<Domain>/` | Domain commands, queries, models — the write authorities |
| `app/Http/Controllers/` | Transport layer; delegates to domain authorities |
| `app/Support/` | Authorization, identifiers and providers |
| `database/migrations/` | Forward-only PostgreSQL migrations |
| `database/seeders/` | Guarded first-run bootstrap and reference seeders |
| `deploy/` | Production deployment, recovery and environment helpers |
| `docs/` | Canonical architecture/governance/operations documentation — start at [`docs/README.md`](docs/README.md) |
| `docs/ai/` | Mandatory AI/agent engineering governance |
| `docs/decisions/` | Architecture Decision Records indexed by `docs/16-DECISION-REGISTER.md` |