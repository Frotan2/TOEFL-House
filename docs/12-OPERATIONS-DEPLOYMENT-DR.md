# TOEFL House — Operations / Deployment / Disaster Recovery

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Official runtime contract

The authoritative specification is `RUNTIME_ENVIRONMENT_LOCK.md`, machine-checked by `npm run verify:environment`:

- PHP `>=8.2 <8.5` (locked: 8.4.14; Laravel 13 prohibited)
- Laravel `^12.67.0` (locked: 12.67.0)
- PostgreSQL `>=18.0 <19.0` (locked: 18.4)
- Composer `>=2.5 <3.0` (locked: 2.9.2)
- Node.js `>=22.0 <23.0` (locked: 22.22.3)
- npm `>=10.0` (locked: 10.9.8)
- React 19.x
- Vite 7.x
- TypeScript 5.9.x

Concrete patch pins used by a deployment artifact may be narrower than this contract; they must remain documented at the artifact boundary and must not be confused with runtime certification.

## Deployment

Production deployment must use deterministic dependency installation, validate runtime prerequisites, verify PostgreSQL extension availability, execute migrations deliberately, activate the release only after required checks, and distinguish application rollback from database recovery.

The implementing artifacts are `deploy/deploy.sh` (release switch, health-gated activation, safe rollback) with `deploy/backup.sh`, `deploy/restore.sh` and `deploy/schema-compatibility.sh`; the operator procedure is `operations/production-deployment.md`. Windows convenience deployment is governed by the root launchers and `../SETUP.md`.

## PostgreSQL prerequisites

The database contract requires at least:

- `pgcrypto`
- `btree_gist`

## Rollback doctrine

Application rollback does not imply database rollback. Schema compatibility must be managed through an expand/contract strategy or an explicitly controlled forward-fix/restore process.

## Readiness

Readiness must verify the minimum conditions required to safely serve traffic, including application boot, database access, application key validity and required production build artifacts.

Readiness documentation is preparation guidance until those checks have actually been executed in the required environment.

## Backup/recovery

Backups, restores and recovery procedures must be executable and periodically tested. A documented recovery plan is not equivalent to a successful recovery drill.

## Current release state

Final runtime certification remains separate from target-state documentation and requires the official environment. Current repository work records environment/runtime blockers explicitly rather than treating documented procedures as proof of execution.