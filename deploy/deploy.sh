#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — production deployment script
# =============================================================================
# Deterministic, provider-neutral deployment using the releases + atomic
# `current` symlink pattern. A release is switched live only after build,
# configuration, backup, migration, and cache gates pass; if post-switch health
# fails, rollback is schema-aware and never pretends an application pointer change
# also reverted the database.
#
# Usage:
#   ./deploy/deploy.sh <git-ref>          # deploy a branch/tag/commit
#   ./deploy/deploy.sh --rollback         # roll back to the previous release
#
# Prerequisites (see docs/operations/production-deployment.md):
#   * php + composer + node/npm + curl on PATH; their versions are verified by
#     scripts/runtime/verify-environment.mjs (the machine-checkable form of
#     docs/RUNTIME_ENVIRONMENT_LOCK.md), which step 2b runs — this script
#     deliberately holds no version numbers of its own
#   * PostgreSQL client tools pg_dump + pg_restore (postgresql-client, version >=
#     the server) — required by deploy/backup.sh and deploy/restore.sh
#   * nginx (deploy/nginx) and php-fpm (deploy/php-fpm.conf pool + the
#     deploy/opcache.ini conf.d fragment) installed; deploy.sh step 0 parses the
#     installed pool with php-fpm -t when the binary is available
#   * PostgreSQL reachable and the app database created
#   * a persistent .env at $DEPLOY_ROOT/.env (never committed to the repo)
# =============================================================================
set -euo pipefail

# --- Configuration (override via environment when needed) -------------------
DEPLOY_ROOT="${DEPLOY_ROOT:-/var/www/toefl-house}"
# This is the canonical repository, not a historical fork. Mirrors and private
# deployment sources remain supported through the explicit REPO_URL override.
REPO_URL="${REPO_URL:-https://github.com/Frotan2/TOEFL-House.git}"
RELEASES_DIR="$DEPLOY_ROOT/releases"
CURRENT_LINK="$DEPLOY_ROOT/current"
ENV_FILE="$DEPLOY_ROOT/.env"
HEALTH_URL="${HEALTH_URL:-http://127.0.0.1/health}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
PSQL_BIN="${PSQL_BIN:-psql}"
# The schema probe ships next to this script, so it stays available even when
# the release being inspected has no vendor tree (or is an older release).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./lib/retention.sh
source "$SCRIPT_DIR/lib/retention.sh"
# shellcheck source=./lib/nginx-edge-config.sh
source "$SCRIPT_DIR/lib/nginx-edge-config.sh"
SCHEMA_PROBE="${SCHEMA_PROBE:-$SCRIPT_DIR/schema-compatibility.sh}"
# Where the operator installed the FPM pool (a glob, since the PHP version
# prefix differs per distribution). Read once, used by the pool syntax test and
# by the storage ownership step, so the two can never disagree.
PHP_FPM_POOL="${PHP_FPM_POOL:-/etc/php/*/fpm/pool.d/toefl-house.conf}"
# How to make PHP-FPM pick up the activated release, and where its pid file is.
# Both are host facts, like the pool path and the web user below.
PHP_FPM_RELOAD_CMD="${PHP_FPM_RELOAD_CMD:-}"
PHP_FPM_PID_FILE="${PHP_FPM_PID_FILE:-}"
# Where the web server's config comes from. Nothing else in the repository installs
# deploy/nginx/toefl-house.conf on the host, so without this the edge keeps its own
# copy and the security headers shipped in a release never reach the paths nginx
# serves directly. Unset = managed elsewhere, which the deploy step reports instead
# of assuming.
NGINX_CONF_DEST="${NGINX_CONF_DEST:-}"
NGINX_RELOAD_CMD="${NGINX_RELOAD_CMD:-}"

log()  { printf '[deploy] %s\n' "$*"; }
die()  { printf '[deploy][ERROR] %s\n' "$*" >&2; exit 1; }
warn() { printf '[deploy][WARN] %s\n' "$*"; }

# Database connection settings come from the one persistent env file, and both
# the deploy and the rollback path need them, so they are parsed in one place.
load_db_settings() {
    [ -f "$ENV_FILE" ] || die "missing persistent env file at $ENV_FILE (create it from .env.example with APP_ENV=production, APP_DEBUG=false, APP_KEY, and DB credentials)"
    DB_NAME_VAL="$(grep -m1 '^DB_DATABASE=' "$ENV_FILE" | cut -d= -f2-)"
    DB_HOST_VAL="$(grep -m1 '^DB_HOST=' "$ENV_FILE" | cut -d= -f2-)"
    DB_PORT_VAL="$(grep -m1 '^DB_PORT=' "$ENV_FILE" | cut -d= -f2-)"
    DB_USER_VAL="$(grep -m1 '^DB_USERNAME=' "$ENV_FILE" | cut -d= -f2-)"
    DB_PASS_VAL="$(grep -m1 '^DB_PASSWORD=' "$ENV_FILE" | cut -d= -f2-)"
    DB_SSLMODE_VAL="$(grep -m1 '^DB_SSLMODE=' "$ENV_FILE" | cut -d= -f2-)"
    DB_CONNECTION_VAL="$(grep -m1 '^DB_CONNECTION=' "$ENV_FILE" | cut -d= -f2-)"
}

# Read live schema state with the first-party psql probe (see
# deploy/schema-compatibility.sh for why this is not an `artisan tinker` call).
schema_probe() {
    DB_NAME="${DB_NAME_VAL:-toefl_house}" DB_HOST="${DB_HOST_VAL:-127.0.0.1}" \
        DB_PORT="${DB_PORT_VAL:-5432}" DB_USER="${DB_USER_VAL:-postgres}" \
        PGPASSWORD="${DB_PASS_VAL:-}" PSQL_BIN="$PSQL_BIN" \
        bash "$SCHEMA_PROBE" "$@"
}

# Exit 0 -> the target release can run against the live schema; 1 -> the schema
# is ahead of it; 2 -> unverifiable. Only 0 may be pointed at by `current`.
schema_compatibility_check() {
    target_release="$1"
    [ -f "$SCHEMA_PROBE" ] || die "cannot verify schema compatibility: missing probe at $SCHEMA_PROBE"

    local out_file err_file names note status
    out_file="$(mktemp)"
    err_file="$(mktemp)"
    set +e
    schema_probe --check "$target_release" >"$out_file" 2>"$err_file"
    status=$?
    set -e
    # stdout is the machine-readable contract (one migration name per line);
    # stderr is the human log. Mixing them produced an operator error message
    # containing loader warnings and "[schema-compat]" chatter, which is exactly
    # what an on-call engineer has to read at 3am.
    names="$(sed '/^[[:space:]]*$/d' "$out_file" | tr '\n' ' ')"
    note="$(sed '/^[[:space:]]*$/d' "$err_file" | tail -3 | tr '\n' '|')"
    rm -f "$out_file" "$err_file"

    case "$status" in
        0) log "target release verified against the live schema: $(basename "$target_release")" ;;
        1) die "refusing application-only rollback: live database contains migration(s) absent from target release $(basename "$target_release"): ${names}. Use a compatible release or perform an explicit database restore/forward-fix." ;;
        *) die "cannot verify schema compatibility for target release $target_release (probe exit $status, probe said: ${note}); refusing application-only rollback" ;;
    esac
}

if [ "${1:-}" = "--rollback" ]; then
    current_release="$(basename "$(readlink "$CURRENT_LINK" 2>/dev/null || true)")"
    prev="$(ls -1 "$RELEASES_DIR" 2>/dev/null | grep -v "^${current_release}$" | sort | tail -1 || true)"
    [ -n "$prev" ] || die "no previous release to roll back to"
    load_db_settings
    schema_compatibility_check "$RELEASES_DIR/$prev"
    ln -sfn "$RELEASES_DIR/$prev" "$CURRENT_LINK"
    log "rolled back application -> $prev (schema compatibility verified)"
    exit 0
fi

REF="${1:?usage: deploy.sh <git-ref> | --rollback}"
[ -f "$ENV_FILE" ] || die "missing persistent env file at $ENV_FILE (create it from .env.example with APP_ENV=production, APP_DEBUG=false, APP_KEY, and DB credentials)"

# Tool presence fails fast here. Tool *versions* are verified in step 2b by the
# runtime lock's own enforcer, because that script is the single source of truth:
# this file used to pin PHP 8.2.27 / Node 22.23.1 / npm 10.9.2 / PostgreSQL 18.4
# exactly, and that second copy of the contract had drifted away from the runtime
# the release was actually certified on, refusing valid deployments. Do not put
# version numbers back here.
for tool in "$PHP_BIN" "$COMPOSER_BIN" node "$NPM_BIN"; do
    command -v "$tool" >/dev/null 2>&1 || die "$tool not found on PATH"
done

RELEASE_ID="$(date -u +%Y%m%d%H%M%S)"
RELEASE_DIR="$RELEASES_DIR/$RELEASE_ID"
mkdir -p "$RELEASES_DIR"
[ -e "$RELEASE_DIR" ] && die "release dir already exists: $RELEASE_DIR"

log "deploying ref '$REF' to $RELEASE_DIR"

# 0. The PHP-FPM pool is installed by the operator, not by this script, and a
#    pool file FPM cannot parse means no PHP execution at all: a config test here
#    costs one process and saves a rollback. It is skipped (loudly, in the log)
#    when no fpm binary is on PATH, e.g. on a build host. Override with
#    PHP_FPM_BIN / PHP_FPM_POOL when the service uses non-standard paths.
PHP_FPM_BIN="${PHP_FPM_BIN:-}"
if [ -z "$PHP_FPM_BIN" ] && command -v php-fpm >/dev/null 2>&1; then
    PHP_FPM_BIN="$(command -v php-fpm)"
fi
if [ -n "$PHP_FPM_BIN" ]; then
    fpm_pool_checked=0
    for pool in $PHP_FPM_POOL; do
        [ -f "$pool" ] || continue
        "$PHP_FPM_BIN" -t -y "$pool" >/dev/null 2>&1 \
            || die "php-fpm cannot parse the installed pool (${pool}); deploy/php-fpm.conf is the reference"
        fpm_pool_checked=1
    done
    [ "$fpm_pool_checked" = 1 ] \
        && log "php-fpm pool configuration verified" \
        || log "WARNING: no toefl-house fpm pool found to test ($PHP_FPM_POOL)"
else
    log "WARNING: php-fpm binary not available: pool configuration not tested on this host"
fi

# 1. Source checkout (shallow for speed; the ref must be reachable).
git clone --quiet --depth 1 --branch "$REF" "$REPO_URL" "$RELEASE_DIR" \
    || git clone --quiet "$REPO_URL" "$RELEASE_DIR"
( cd "$RELEASE_DIR" && git checkout --quiet "$REF" )
COMMIT="$( cd "$RELEASE_DIR" && git rev-parse HEAD )"
log "source: commit $COMMIT"

# 2. Dependencies (production only; lock file is authoritative).
( cd "$RELEASE_DIR" && "$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --no-progress --optimize-autoloader )

# 2b. Runtime contract, enforced by the lock itself (docs/RUNTIME_ENVIRONMENT_LOCK.md).
#     Ranges, not patch pins. It runs inside the release directory because the
#     Laravel check reads vendor/autoload.php, and it is pointed at the deployment
#     database through the persistent env file rather than the ambient shell.
load_db_settings
( cd "$RELEASE_DIR" \
    && DB_CONNECTION="${DB_CONNECTION_VAL:-pgsql}" \
       DB_HOST="${DB_HOST_VAL:-127.0.0.1}" DB_PORT="${DB_PORT_VAL:-5432}" \
       DB_DATABASE="${DB_NAME_VAL:-toefl_house}" \
       DB_USERNAME="${DB_USER_VAL:-postgres}" DB_PASSWORD="${DB_PASS_VAL:-}" \
       node scripts/runtime/verify-environment.mjs ) \
    || die "host runtime does not satisfy the environment lock (docs/RUNTIME_ENVIRONMENT_LOCK.md); see the FAIL lines above"

# 3. Build the selected React/TypeScript console root. The build runs in the
# release directory so public/build is part of the atomic release; no Node
# process is needed after go-live. A committed lockfile is mandatory for the
# production path; npm ci refuses to proceed when package.json and the lockfile
# disagree.
[ -f "$RELEASE_DIR/package-lock.json" ] || die "missing package-lock.json: refusing a non-reproducible production frontend build"
( cd "$RELEASE_DIR" && "$NPM_BIN" ci --no-audit --no-fund --engine-strict && "$NPM_BIN" run build )

# 4. Environment: the persistent .env is the single source of deployment env.
cp "$ENV_FILE" "$RELEASE_DIR/.env"
( cd "$RELEASE_DIR" && grep -q '^APP_ENV=production' .env ) || die ".env must set APP_ENV=production"
( cd "$RELEASE_DIR" && grep -q '^APP_DEBUG=false' .env ) || die ".env must set APP_DEBUG=false"
# APP_KEY must be present and non-empty BEFORE the release goes live: with an
# empty key the first request fails session encryption and the health check
# would only catch it after the symlink has switched (forcing a rollback of a
# deploy that could have been refused up front).
( cd "$RELEASE_DIR" && grep -q '^APP_KEY=..' .env ) || die ".env must set a non-empty APP_KEY (php artisan key:generate)"

# 5. Pre-deploy backup: migrations are forward-only and run against the live
#    database, so a backup is taken immediately before they run. The backup
#    uses the persistent .env's DB settings; without the client tools this
#    deploy is refused (a migration without a fresh backup is not a deploy
#    this script will perform).
load_db_settings

case "${DB_HOST_VAL:-127.0.0.1}" in
    127.0.0.1|localhost|::1) ;;
    *)
        case "${DB_SSLMODE_VAL:-}" in
            require|verify-ca|verify-full) ;;
            *) die "remote PostgreSQL requires DB_SSLMODE=require, verify-ca, or verify-full" ;;
        esac
        ;;
esac
for tool in pg_dump pg_restore "$PSQL_BIN"; do
    command -v "$tool" >/dev/null 2>&1 || die "$tool not found on PATH: install PostgreSQL client tools at or above the server version (PostgreSQL 18 contract)"
done

PG_EXTENSIONS="$($PSQL_BIN --host="${DB_HOST_VAL:-127.0.0.1}" --port="${DB_PORT_VAL:-5432}" --username="${DB_USER_VAL:-postgres}" --dbname="${DB_NAME_VAL:-toefl_house}" --tuples-only --no-align -c "SELECT name || ':' || COALESCE(installed_version, '') FROM pg_available_extensions WHERE name IN ('pgcrypto','btree_gist') ORDER BY name;" 2>/dev/null || true)"
PG_EXTENSION_COUNT="$(printf '%s\n' "$PG_EXTENSIONS" | awk 'NF {count++} END {print count+0}')"
[ "$PG_EXTENSION_COUNT" -eq 2 ] || die "required PostgreSQL extensions pgcrypto and btree_gist are not both available on the target server"
PG_SUPERUSER="$($PSQL_BIN --host="${DB_HOST_VAL:-127.0.0.1}" --port="${DB_PORT_VAL:-5432}" --username="${DB_USER_VAL:-postgres}" --dbname="${DB_NAME_VAL:-toefl_house}" --tuples-only --no-align -c 'SELECT rolsuper FROM pg_roles WHERE rolname = current_user;' 2>/dev/null | tr -d '[:space:]' || true)"
if printf '%s\n' "$PG_EXTENSIONS" | grep -qE ':(\s*)$' && [ "$PG_SUPERUSER" != "t" ]; then
    die "one or more required PostgreSQL extensions are not installed and the deployment DB role is not a PostgreSQL superuser; provision the extensions before deployment"
fi

if command -v pg_dump >/dev/null 2>&1; then
    BACKUP_DIR="${BACKUP_DIR:-/var/backups/toefl-house}" \
        DB_NAME="${DB_NAME_VAL:-toefl_house}" DB_HOST="${DB_HOST_VAL:-127.0.0.1}" \
        DB_PORT="${DB_PORT_VAL:-5432}" DB_USER="${DB_USER_VAL:-postgres}" \
        PGPASSWORD="$DB_PASS_VAL" \
        "$RELEASE_DIR/deploy/backup.sh"
else
    die "pg_dump not found on PATH: refusing to deploy (migrations run against the live database and require a pre-deploy backup; install postgresql-client, version >= the server)"
fi

# 6. Schema: forward-only migrations. Never destructive; a failing migration
#    aborts the deployment before the release goes live. Record whether the
#    database schema advanced so a later health failure can never trigger an
#    unsafe application-only rollback against an incompatible schema.
SCHEMA_BEFORE="$(schema_probe --count)" || die "unable to read current migration state before deployment"
( cd "$RELEASE_DIR" && "$PHP_BIN" artisan migrate --force --no-interaction )
SCHEMA_AFTER="$(schema_probe --count)" || die "unable to read migration state after deployment"

# 7. Runtime directories exist and are owned by the web user (the repo now
#    tracks them, but ensure ownership/permissions for the FPM user).
# The FPM pool is one configuration, so it is resolved once (step 0 uses the same
# override) and the web user is derived from it rather than from a hardcoded
# /etc/php path that only exists on some distributions.
FPM_POOL_RESOLVED="$(ls -1 $PHP_FPM_POOL 2>/dev/null | head -1)"
WEB_USER="${WEB_USER:-${FPM_POOL_RESOLVED:+$(grep -m1 '^user' "$FPM_POOL_RESOLVED" 2>/dev/null | awk '{print $3}')}}"
WEB_USER="${WEB_USER:-www-data}"
for d in storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do
    mkdir -p "$RELEASE_DIR/$d"
done

# Laravel must be able to write storage/ and bootstrap/cache/ as the FPM user.
# A failed chown is only acceptable when the ownership is already right (a
# single-user host deploying as the account it serves); anything else must stop
# the deployment here rather than go live on a runtime that cannot log.
if ! chown -R "$WEB_USER":"$WEB_USER" "$RELEASE_DIR/storage" "$RELEASE_DIR/bootstrap/cache" 2>/dev/null; then
    if [ "$(stat -c '%U' "$RELEASE_DIR/storage" 2>/dev/null)" != "$WEB_USER" ]; then
        die "cannot hand $RELEASE_DIR/storage and bootstrap/cache to ${WEB_USER}: run the deployment as root (or with sudo), or set WEB_USER to the user PHP-FPM serves as"
    fi
    log "storage already owned by ${WEB_USER}: chown not required"
fi

# 8. Production optimization (Laravel-recommended: cached config/routes/views).
( cd "$RELEASE_DIR" && "$PHP_BIN" artisan config:cache && "$PHP_BIN" artisan route:cache && "$PHP_BIN" artisan view:cache )

# 9. Go live: switch the symlink, then verify the release over HTTP.
PREV_RELEASE="$(readlink "$CURRENT_LINK" 2>/dev/null || true)"
ln -sfn "$RELEASE_DIR" "$CURRENT_LINK"
#
# Reload PHP-FPM, and treat "could not reload" as a failed deployment.
#
# `opcache.validate_timestamps=0` is the documented production setting (it is the
# point of caching config/routes/views), which means a running master never stats a
# PHP file again: after the symlink moves, it keeps executing the files of whatever
# release it last loaded, and `opcache_reset` alone does not help because the entry
# script path is what changed. Reloading used to be written
#
#   ( systemctl reload php*-fpm || service php*-fpm reload || true )
#
# so on a host with neither systemd nor sysvinit — a container, a
# supervisor/systemd-less image, anything where the pool is managed by something
# else — the deployment switched the symlink, reported "deployment OK", and served
# the PREVIOUS release indefinitely. Measured on 2026-09-08: `current` pointed at
# releases/20260908191650 while the exception produced by a live request was
# written by `releases/20260908191449/public/index.php`, and /health agreed the
# deployment was healthy. The new release's migrations had already run against the
# database, so the live system was "new schema, old application" — and since
# `--rollback` also only moves a symlink, it could not have fixed that either.
#
# So: try the operator's command, then the service managers, then SIGUSR2 to the
# master from the pid file. If none of them works, put the symlink back and fail,
# because the alternative is a success report nobody can trust.
reload_php_fpm() {
    if [ -n "$PHP_FPM_RELOAD_CMD" ]; then
        if bash -c "$PHP_FPM_RELOAD_CMD" >/dev/null 2>&1; then
            log "PHP-FPM reloaded via PHP_FPM_RELOAD_CMD"
            return 0
        fi
        return 1
    fi

    if command -v systemctl >/dev/null 2>&1 && systemctl reload php*-fpm >/dev/null 2>&1; then
        log "PHP-FPM reloaded via systemctl"
        return 0
    fi

    if command -v service >/dev/null 2>&1 && service php*-fpm reload >/dev/null 2>&1; then
        log "PHP-FPM reloaded via service"
        return 0
    fi

    if [ -n "$PHP_FPM_PID_FILE" ] && [ -r "$PHP_FPM_PID_FILE" ]; then
        local master_pid
        master_pid="$(tr -dc '0-9' < "$PHP_FPM_PID_FILE")"
        if [ -n "$master_pid" ] && kill -0 "$master_pid" 2>/dev/null && kill -USR2 "$master_pid" 2>/dev/null; then
            log "PHP-FPM reloaded (SIGUSR2 to master pid $master_pid)"
            return 0
        fi
    fi

    return 1
}

if ! reload_php_fpm; then
    if [ -n "$PREV_RELEASE" ]; then
        ln -sfn "$PREV_RELEASE" "$CURRENT_LINK"
        log "current restored to $(basename "$PREV_RELEASE") so the symlink matches what is executing"
    fi
    die "PHP-FPM was not reloaded, so the new release is not actually serving traffic (opcache.validate_timestamps=0 keeps the previous release loaded). Set PHP_FPM_RELOAD_CMD (for example: kill -USR2 \$(cat /run/php/php-fpm.pid), or your supervisor's restart command) and optionally PHP_FPM_PID_FILE, then re-run. NOTE: the database was already migrated by this release; restoring the symlink does not undo that."
fi

install_nginx_edge_config "$RELEASE_DIR/deploy/nginx/toefl-house.conf"

log "verifying release at $HEALTH_URL"
for i in 1 2 3 4 5; do
    if curl -fsS --max-time 10 "$HEALTH_URL" >/dev/null 2>&1; then
        log "deployment OK: release $RELEASE_ID (commit $COMMIT) is live and healthy"
        # Keep the 4 newest release directories (this one plus the 3 previous);
        # older ones are disposable. Pruning must not be able to fail a healthy
        # deployment, so it goes through the shared retention helper instead of an
        # `ls | grep -v | sort | head` pipeline: `grep -v` exits 1 when no other
        # release exists yet, and `set -o pipefail` would surface that as a
        # deployment failure after the release had already gone live.
        prune_old_entries "$RELEASES_DIR" '*' 4 rm\ -rf
        exit 0
    fi
    sleep 2
done

# 10. Unhealthy: application rollback is safe only when the target release
#     can prove compatibility with the live schema. If migrations advanced,
#     never silently point an older application at a newer schema: use a
#     forward-fix or an explicit database restore instead.
log "release FAILED health check"
if [ -n "$PREV_RELEASE" ] && [ "$SCHEMA_AFTER" = "$SCHEMA_BEFORE" ]; then
    log "schema did not advance; verifying application-only rollback target"
    schema_compatibility_check "$PREV_RELEASE"
    ln -sfn "$PREV_RELEASE" "$CURRENT_LINK"
    restore_nginx_edge_config
    die "deployment of $RELEASE_ID failed health verification and was safely rolled back to $PREV_RELEASE"
fi

die "deployment of $RELEASE_ID failed health verification after a schema change; automatic application rollback was intentionally refused. Use a forward-fix, or restore the pre-deploy backup before selecting an older application release. Failed release preserved at $RELEASE_DIR"
