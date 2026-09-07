# TOEFL House — Operations / Deployment / Disaster Recovery

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Official runtime contract

- PHP 8.2.x
- Laravel 12.67.x
- PostgreSQL 18.x
- Composer 2.10.x
- Node.js 22.x
- npm 10.x
- React 19.x
- Vite 7.x
- TypeScript 5.9.x

Concrete patch pins used by a deployment artifact may be narrower than this contract; they must remain documented at the artifact boundary and must not be confused with runtime certification.

## Deployment

Production deployment must use deterministic dependency installation, validate runtime prerequisites, verify PostgreSQL extension availability, execute migrations deliberately, activate the release only after required checks, and distinguish application rollback from database recovery.

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