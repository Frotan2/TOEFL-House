#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — scheduler entrypoint
# =============================================================================
# Run this once per minute from one supervised systemd timer OR one cron entry.
# It resolves the current release only when it starts, then execs the locked
# integration tick directly. Calling the tick rather than `schedule:run` keeps
# a failed authorization/configuration visible as this process's exit status;
# Laravel's process scheduler intentionally treats child-command failures as
# completed scheduling cycles. INTEGRATIONS_SCHEDULER_RUN_BY must name a durable
# authorized person; no synthetic system actor is accepted.
#
# Usage:
#   DEPLOY_ROOT=/var/www/toefl-house ./deploy/schedule.sh
#
# Optional environment:
#   DEPLOY_ROOT (default /var/www/toefl-house)
#   CURRENT_LINK (default $DEPLOY_ROOT/current)
#   PHP_BIN (default php)
# =============================================================================
set -euo pipefail

DEPLOY_ROOT="${DEPLOY_ROOT:-/var/www/toefl-house}"
CURRENT_LINK="${CURRENT_LINK:-$DEPLOY_ROOT/current}"
PHP_BIN="${PHP_BIN:-php}"

log() { printf '[scheduler] %s\n' "$*"; }
die() { printf '[scheduler][ERROR] %s\n' "$*" >&2; exit 1; }

[ -d "$CURRENT_LINK" ] || die "current release directory is unavailable: $CURRENT_LINK"
[ -f "$CURRENT_LINK/artisan" ] || die "current release has no artisan entrypoint: $CURRENT_LINK/artisan"
command -v "$PHP_BIN" >/dev/null 2>&1 || die "PHP_BIN is not executable on PATH: $PHP_BIN"

# Resolve and enter the active release before execing so an atomic current-link
# switch during a run cannot change the working directory mid-invocation.
cd "$CURRENT_LINK"
log "running integration scheduler tick for $(pwd)"
exec "$PHP_BIN" artisan integrations:tick --no-interaction
