#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — live-schema / release-code compatibility probe
# =============================================================================
# Answers the only two questions the deployment path asks about schema state:
#
#   deploy/schema-compatibility.sh --count                 applied migration count
#   deploy/schema-compatibility.sh --check <release-dir>   can this release run
#                                                          against the live schema?
#
# Exit codes:
#   0  compatible (--check) / count printed (--count)
#   1  the live database is AHEAD of the target release: its migrations table
#      names migrations the release does not ship, so pointing the release at
#      that database (an application-only rollback) would run without them
#   2  the state could not be read at all — callers must refuse to proceed
#
# stdout carries machine-readable data only (the count, or the missing
# migration names); progress and errors go to stderr.
#
# Why this exists instead of an `artisan tinker` one-liner: the deployment
# script must be able to inspect the live schema from a release directory whose
# PHP dependency tree may not exist yet, and a production release is installed
# with `composer install --no-dev`, where laravel/tinker is by definition
# absent. Reading `public.migrations` with psql keeps the preflight honest
# (same table Laravel itself uses) and dependency-free.
#
# Environment (same names as deploy/backup.sh):
#   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, PSQL_BIN
# =============================================================================
set -euo pipefail

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-toefl_house}"
DB_USER="${DB_USER:-postgres}"
DB_PASSWORD="${DB_PASSWORD:-}"
PSQL_BIN="${PSQL_BIN:-psql}"

log() { printf '[schema-compat] %s\n' "$*" >&2; }
die_unverifiable() { printf '[schema-compat][ERROR] %s\n' "$*" >&2; exit 2; }

# A missing or unusable client is a prerequisite failure, not a schema answer:
# report it once here so no caller can mistake it for "nothing applied yet".
if ! "$PSQL_BIN" --version >/dev/null 2>&1; then
    die_unverifiable "'${PSQL_BIN}' is not a working psql client (install postgresql-client at or above the server version, or set PSQL_BIN)"
fi

# Run one read-only query and print its rows (blank lines removed). Any failure
# to talk to the database is reported as exit 2 rather than an empty result, so
# callers can never mistake "could not check" for "nothing to check".
sql() {
    local query="$1" output errfile
    # stderr is kept out of band: a loader warning ("no version information
    # available") must never be mistaken for query output, and a real client
    # error must still be quotable verbatim.
    errfile="$(mktemp)"
    if ! output="$(PGPASSWORD="$DB_PASSWORD" "$PSQL_BIN" \
        --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USER" \
        --dbname="$DB_NAME" --tuples-only --no-align --quiet -c "$query" 2>"$errfile")"; then
        local reason
        reason="$(grep -m1 -v 'no version information available' "$errfile" || true)"
        [ -n "$reason" ] || reason="psql exited non-zero"
        rm -f "$errfile"
        die_unverifiable "cannot query '${DB_NAME}' on ${DB_HOST}:${DB_PORT} as '${DB_USER}': ${reason}"
    fi
    rm -f "$errfile"
    printf '%s\n' "$output" | tr -d '\r' | sed '/^[[:space:]]*$/d'
}

case "${1:-}" in
    --count)
        # A database without a migrations table has nothing applied yet: that is
        # a real answer (0), not an error.
        if ! count="$(sql "SELECT CASE WHEN to_regclass('public.migrations') IS NULL THEN 0 ELSE (SELECT count(*) FROM public.migrations) END;")"; then
            exit 2
        fi
        case "$count" in
            ''|*[!0-9]*) die_unverifiable "unexpected migration count: '${count}'" ;;
        esac
        printf '%s\n' "$count"
        ;;
    --check)
        release_dir="${2:?usage: deploy/schema-compatibility.sh --check <release-dir>}"
        [ -d "$release_dir" ] || die_unverifiable "target release directory does not exist: ${release_dir}"
        [ -d "$release_dir/database/migrations" ] || die_unverifiable "target release ships no database/migrations directory: ${release_dir}/database/migrations"
        release_name="$(basename "$release_dir")"

        if ! migrations_table="$(sql "SELECT to_regclass('public.migrations') IS NOT NULL;")"; then
            exit 2
        fi
        if [ "$migrations_table" != "t" ]; then
            log "live database has no migrations table yet; ${release_name} is trivially compatible"
            exit 0
        fi

        if ! applied="$(sql "SELECT migration FROM public.migrations ORDER BY migration;")"; then
            exit 2
        fi
        files="$(cd "$release_dir/database/migrations" && ls -1 *.php 2>/dev/null | sed 's/\.php$//' || true)"
        [ -n "$files" ] || die_unverifiable "target release has no migration files: ${release_dir}/database/migrations"

        # Applied-but-absent is the dangerous direction (a rollback would lose a
        # migration that the live schema already depends on).
        missing="$(comm -23 <(printf '%s\n' "$applied" | LC_ALL=C sort) <(printf '%s\n' "$files" | LC_ALL=C sort) || true)"
        if [ -n "$missing" ]; then
            printf '%s\n' "$missing"
            missing_count="$(printf '%s\n' "$missing" | sed '/^[[:space:]]*$/d' | wc -l | tr -d ' ')"
            log "live database is ahead of ${release_name} by ${missing_count} migration(s)"
            exit 1
        fi
        applied_count="$(printf '%s\n' "$applied" | wc -l | tr -d ' ')"
        log "release ${release_name} is compatible with the live schema (${applied_count} migrations applied)"
        ;;
    *)
        printf 'usage: deploy/schema-compatibility.sh --count | --check <release-dir>\n' >&2
        exit 64
        ;;
esac
