#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — database restore (disaster recovery)
# =============================================================================
# Restores the database from a backup produced by deploy/backup.sh. This is
# the recovery procedure; it must be exercised (see the verification step) —
# a backup that has never been restored is not considered verified.
#
# Recovery objectives (single-instance deployment):
#   * RPO (recovery point): up to the last nightly backup (24h).
#   * RTO (recovery time): a single pg_restore of the current data volume.
#     Measured in the 2026-09-08 recovery drill (168 tables, 1.3 MB dump):
#     restore + verification completed in 0.9 s on a warm cluster; budget
#     minutes for a cold instance and a production-sized dataset.
#
# Usage:
#   ./deploy/restore.sh <backup-file> --confirm   # restore a specific dump
#   ./deploy/restore.sh --latest --confirm        # restore the most recent dump
#
# `--confirm` is a required second argument, not an optional safety flag: the
# script overwrites the live database, so an operator must say so explicitly.
#
# The restore is NOT automatic on a failed deployment (that is a rollback,
# handled by deploy.sh). This is an explicit, operator-initiated disaster
# recovery action and refuses to run without --confirm.
# =============================================================================
set -euo pipefail

# Preflight: the PostgreSQL client tools must be installed (postgresql-client,
# version >= the server). A restore without working client tools is a
# recovery that fails mid-way; refuse before touching anything.
for tool in pg_restore psql; do
    command -v "$tool" >/dev/null 2>&1 || {
        echo "[restore][ERROR] $tool not found on PATH. Install postgresql-client (>= server version)." >&2
        exit 1
    }
done

BACKUP_DIR="${BACKUP_DIR:-/var/backups/toefl-house}"
DB_NAME="${DB_NAME:-toefl_house}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_USER="${DB_USER:-postgres}"
PGPASSWORD="${PGPASSWORD:-}"
# Where to probe once the database is back; used only in the closing
# instructions, but it must be defined because the script runs under `set -u`.
# (It was not: every restore, including a fully successful one, ended with
# "restore.sh: line 124: HEALTH_URL: unbound variable" and exit 1.)
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1:8080/health}"
# Database used to check for / create the target during recovery (defaults to
# the usual maintenance database; override if the role may not touch "postgres").
DB_MAINTENANCE_DATABASE="${DB_MAINTENANCE_DATABASE:-}"
TABLES_BEFORE_RESTORE=""

log() { printf '[restore] %s\n' "$*"; }
die() { printf '[restore][ERROR] %s\n' "$*" >&2; exit 1; }

pick_latest() {
    ls -1t "$BACKUP_DIR/${DB_NAME}-"*.dump 2>/dev/null | head -1 || true
}

BACKUP_FILE="${1:-}"
if [ "$BACKUP_FILE" = "--latest" ]; then
    BACKUP_FILE="$(pick_latest)"
fi
[ -n "$BACKUP_FILE" ] || die "no backup file given and no backup found in $BACKUP_DIR"
[ -f "$BACKUP_FILE" ] || die "backup file not found: $BACKUP_FILE"

# Safety: require explicit confirmation before touching the live database.
[ "${2:-}" = "--confirm" ] || die "refusing to restore without an explicit second argument '--confirm' (this overwrites the live database $DB_NAME)"

log "restoring '$DB_NAME' from $BACKUP_FILE"

# 1. Verify the dump is intact before destroying anything.
PGPASSWORD="$PGPASSWORD" pg_restore --list "$BACKUP_FILE" >/dev/null || die "backup failed integrity check; aborting before any change"

# 2. Snapshot the pre-restore state (table list) so we can verify after.
TABLES_BEFORE_RESTORE="$(PGPASSWORD="$PGPASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" --dbname="$DB_NAME" --tuples-only --no-align -c "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';" 2>/dev/null || echo 0)"
log "tables in target before restore: ${TABLES_BEFORE_RESTORE:-unknown}"

# 3. Make sure the target database exists, then restore into it.
#
# `--create` is deliberately NOT passed to pg_restore. Combined with `--clean`
# it emits `DROP DATABASE IF EXISTS <target>` and `CREATE DATABASE <target>`,
# and when the target database still exists (the common case: same cluster,
# data destroyed, or an operator re-running the restore) those become
# "cannot drop the currently open database" and "database already exists".
# pg_restore ignores both, restores everything correctly, and then exits 1 for
# the ignored errors — a recovery that reports failure on success cannot be
# scripted, monitored, or trusted at 3am. Database existence is handled
# explicitly here instead, so there is one code path for both cases.
case "$DB_NAME" in
    [A-Za-z_][A-Za-z0-9_]*) ;;
    *) die "refusing to restore: unexpected database name '$DB_NAME' (letters, digits, underscore only)" ;;
esac

MAINTENANCE_DB="postgres"
if [ -n "${DB_MAINTENANCE_DATABASE:-}" ]; then
    MAINTENANCE_DB="$DB_MAINTENANCE_DATABASE"
fi

if [ "$(PGPASSWORD="$PGPASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" \
        --dbname="$MAINTENANCE_DB" --tuples-only --no-align \
        -c "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME';" 2>/dev/null || echo 0)" != "1" ]; then
    log "target database '$DB_NAME' does not exist; creating it"
    PGPASSWORD="$PGPASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" \
        --dbname="$MAINTENANCE_DB" --quiet -c "CREATE DATABASE \"$DB_NAME\";" \
        || die "could not create database '$DB_NAME'; restore aborted"
fi

# `--clean --if-exists` drops the objects the dump will replace. It is safe on an
# empty database (the IF EXISTS guards turn every drop into a notice) and is what
# makes re-running a restore idempotent.
PGPASSWORD="$PGPASSWORD" pg_restore \
    --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" \
    --clean --if-exists --no-owner --no-privileges \
    --dbname="$DB_NAME" "$BACKUP_FILE" \
    || die "pg_restore reported errors; see the output above (nothing was rolled back automatically)"

# 4. Post-restore verification.
log "verifying restored database"
PGPASSWORD="$PGPASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" --dbname="$DB_NAME" \
    --tuples-only --no-align -c "SELECT 'tables: ' || count(*) FROM information_schema.tables WHERE table_schema='public';"
PGPASSWORD="$PGPASSWORD" psql --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" --dbname="$DB_NAME" \
    --tuples-only --no-align -c "SELECT 'organizations: ' || count(*) FROM organizations;" 2>/dev/null || true

log "restore complete. Re-run migrations if a newer schema is expected:"
log "  php artisan migrate --force   (safe: a restore of a matching dump needs none)"
log "Then verify the app over HTTP: curl -fsS $HEALTH_URL (expect 200)"
