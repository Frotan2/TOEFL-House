# The TOEFL House

An enterprise education-management system: a **Laravel 12 modular monolith**
(Academic, Admissions/Students, Finance, Payroll, HR, CRM, Placement,
Reporting, Organization/Access/Identity) on **PostgreSQL 18**, with a
React/TypeScript employee console served by the same application.

One fact → one owner → one write authority → one lifecycle. Business truth
lives in domain commands and database-enforced invariants; the frontend is a
presentation/intent layer; every module has exactly one write path.

---

## Release status

**Certified production-ready at commit `96925d3` (2026-09-09)** — see
[`docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md`](docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md),
the current and only release authority. It supersedes the withdrawn
2026-09-08 certification line (`AUDIT-SUMMARY.md`,
`FINAL-ENGINEERING-REPORT.md`, both retained with inline corrections).

Certification was granted only on executed evidence on the locked runtime
(PHP 8.4.14 · Composer 2.9.2 · PostgreSQL 18.4 · Node 22): the full
CI-equivalent gate chain, the complete 986-test suite, database invariant and
concurrency verification, and three real-HTTP business journeys (student
lifecycle 77/77, payment lifecycle 29/29, payroll → liability → ledger
27/27) run from true first-boot databases. Evidence claims in this repository
follow the ladder in
[`docs/ai/06-VERIFICATION-EVIDENCE-STANDARD.md`](docs/ai/06-VERIFICATION-EVIDENCE-STANDARD.md);
release claims follow
[`docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md`](docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md).

CI verifies every push (`.github/workflows/verification.yml`): frontend
(typecheck, build, mount), static analysis (Pint, PHPStan level 6, migration
and terminology audits), backend (185-migration replay, full suite, database
invariants, concurrency) on PHP 8.4 / Node 22 / PostgreSQL 18.

---

## Quickstart

### Windows one-click (supported convenience path)

Run `START-TOEFL-HOUSE.bat`. It provisions its pinned runtimes under
`.runtime\` (PHP, Composer, Node, PostgreSQL), installs dependencies from
the committed lockfiles, **builds the employee console** (`npm ci` + Vite —
required by the production `/health` gate), creates and migrates a local
PostgreSQL 18 cluster, performs the guarded first-run bootstrap (prompting
for the owner account), serves the app at `http://127.0.0.1:8080` and
checks `/health`. Companion launchers:
`STOP-TOEFL-HOUSE.bat`, `BACKUP-TOEFL-HOUSE.bat`, `RESTORE-TOEFL-HOUSE.bat`,
`DIAG-PHP-CRASH.bat`. Full detail in [`SETUP.md`](SETUP.md).

### Linux / clean environment (reproducible provisioner)

```bash
bash scripts/runtime/provision.sh    # PHP 8.4.14 + Composer 2.9.2 + PostgreSQL 18.4 under .runtime/
source scripts/runtime/env.sh        # put the runtime on PATH
bash scripts/runtime/pg.sh start     # start the local PostgreSQL server
bash scripts/runtime/pg.sh createdbs # create the dev + test databases

composer install                     # from the committed composer.lock
npm ci                               # from the committed package-lock.json
cp .env.example .env && php artisan key:generate

php artisan migrate --force          # 185 migrations; includes the standard finance chart (migration 000186)

# First run only (no-op once any user account exists). These variables are
# read from the PROCESS environment, never from .env:
BOOTSTRAP_OWNER_NAME="First Owner" BOOTSTRAP_OWNER_BIRTHDATE="1980-01-01" \
BOOTSTRAP_OWNER_USERNAME=owner BOOTSTRAP_OWNER_PASSWORD='<strong-password>' \
  php artisan db:seed --class=FirstRunBootstrapSeeder --force

php -S 127.0.0.1:8080 -t public public/index.php   # then: GET /health → 200
```

Production deployments instead use `deploy/deploy.sh` (nginx + PHP-FPM
topology, forward-only migrations, health-gated release switch, safe
rollback doctrine) — see
[`docs/operations/production-deployment.md`](docs/operations/production-deployment.md).

---

## Verification

```bash
npm run verify:environment           # locked-runtime contract (8/8)
composer validate --strict           # lock integrity
vendor/bin/pint --test               # formatting gate
vendor/bin/phpstan analyse           # static analysis, level 6
php scripts/database-migration-audit.php
php scripts/terminology-audit.php
php artisan test                     # full PHPUnit suite (pgsql)
npm run typecheck && npm run build && npm run test:frontend
npm run verify:invariants            # PostgreSQL rejects invalid states (6/6)
npm run verify:concurrency           # real concurrent transactions (4/4)
```

End-to-end business journeys over real HTTP (fresh first-boot databases):
`e2e-journey.php` (student lifecycle), `e2e-payment-journey.php` (payment
lifecycle), `e2e-payroll-journey.php` (payroll calculation → Finance
liability recognition → exactly-once journal → reconciliation). Browser-level
E2E is `npm run verify:browser` (requires a Chromium binary via
`CHROMIUM_PATH`).

---

## Repository map

| Path | What lives here |
|---|---|
| `app/Modules/<Domain>/` | Domain commands, queries, models — the only write authorities |
| `app/Http/Controllers/` | Transport layer; delegates to domain authorities |
| `app/Support/` | Authorization (incl. four-actor structure SoD), identifiers, providers |
| `database/migrations/` | 185 forward-only migrations (ordinals to `000190`; gap `000175–000179` is historical) |
| `database/seeders/` | `FirstRunBootstrapSeeder` (guarded, once-only), `StandardFinanceChartSeeder` |
| `deploy/` | Production deploy/backup/restore/schema-compatibility scripts, nginx + php-fpm config, Windows launcher helper |
| `docs/` | Canonical architecture/governance/operations documentation — start at [`docs/README.md`](docs/README.md) |
| `docs/ai/` | AI/agent engineering governance (constitution, evidence standard, release protocol) |
| `docs/decisions/` | Architecture Decision Records; indexed by `docs/16-DECISION-REGISTER.md` |
| `resources/js/` | React/TypeScript employee console (Vite) |
| `routes/` | Web console + `/api/v1` surfaces (session-auth + CSRF) |
| `scripts/runtime/` | Provisioner, environment gate, invariants, concurrency, browser E2E |
| `tests/` | Unit / Canonical / Feature / Architecture suites |

## Non-negotiables

- **PostgreSQL only.** SQLite is deliberately excluded from the runtime; no
  substitution is ever acceptable.
- **Laravel 13 is prohibited**; PHP window is `>=8.2 <8.5` with 8.4 locked.
- **Lockfiles are authoritative** — `composer.lock` and `package-lock.json`
  are never regenerated casually; `npm ci` (never `npm install`) in CI.
- **Never commit** `.env`, credentials, dumps, or runtime data (see
  `.gitignore` and `SETUP.md` §10).
- Lock-version bumps require re-running the full verification chain and
  updating `docs/RUNTIME_ENVIRONMENT_LOCK.md` and
  `docs/RUNTIME_VERIFICATION_HANDOFF.md` together.
