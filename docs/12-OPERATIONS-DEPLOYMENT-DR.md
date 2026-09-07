# TOEFL House — Operations / Deployment / Disaster Recovery

**STATUS: CURRENT CANONICAL — NORMATIVE**

## Official runtime contract

- PHP 8.2.27
- Laravel 12.67.0
- PostgreSQL 18.4
- Node.js 22.23.1
- npm 10.9.2

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

## Backup/recovery

Backups, restores and recovery procedures must be executable and periodically tested. A documented recovery plan is not equivalent to a successful recovery drill.

## Current release state

Final runtime certification remains separate from target-state documentation and requires the official environment.
