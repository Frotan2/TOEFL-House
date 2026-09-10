#!/usr/bin/env bash
# =============================================================================
# The TOEFL House — edge config helper (sourced, not executed)
# =============================================================================
# Installs the release's `deploy/nginx/toefl-house.conf` onto the host and makes
# the web server use it. Sourced by deploy/deploy.sh; usable standalone, which is
# how tests/Feature/Deployment/NginxEdgeConfigTest.php executes it.
#
# Why this exists (measured, not assumed, on 2026-09-08). deploy.sh used to say:
#
#   nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || true
#
# which validates and reloads whatever the *host* already has installed, while
# nothing in the repository ever copies deploy/nginx/toefl-house.conf there. So a
# change to the security headers shipped in a release — the Content-Security-Policy
# added at Gate E, for instance — reaches PHP responses through the middleware and
# never reaches the web server. That is the half that matters: static files, 404s
# and 5xx pages are served without touching PHP at all, so the web server is the
# only thing that can put a header on them.
#
# It is the same class of bug as the swallowed PHP-FPM reload (Gate D): an activation
# step that succeeds by doing nothing, and `|| true` hides it. So the reload here is
# a checked condition, an invalid config is refused *before* it replaces the working
# one, and a rejected config is put back.
#
# Sourced by deploy.sh. log/warn/die have defaults so the behavioural test in
# tests/Feature/Deployment/NginxEdgeConfigTest.php can source this file alone in a
# temp directory with a stub `nginx` on PATH.

if ! type log >/dev/null 2>&1; then
    log() { printf '[deploy] %s\n' "$*"; }
fi
if ! type warn >/dev/null 2>&1; then
    warn() { printf '[deploy][WARN] %s\n' "$*"; }
fi
if ! type die >/dev/null 2>&1; then
    die() { printf '[deploy][ERROR] %s\n' "$*" >&2; exit 1; }
fi

# Set when this run replaced the host's file, so a later failure can undo it.
EDGE_CONF_BACKUP="${EDGE_CONF_BACKUP:-}"

reload_nginx() {
    if [ -n "${NGINX_RELOAD_CMD:-}" ]; then
        if bash -c "$NGINX_RELOAD_CMD" >/dev/null 2>&1; then
            log "nginx reloaded via NGINX_RELOAD_CMD"
            return 0
        fi
        return 1
    fi

    if command -v systemctl >/dev/null 2>&1 && systemctl reload nginx >/dev/null 2>&1; then
        log "nginx reloaded via systemctl"
        return 0
    fi

    if command -v nginx >/dev/null 2>&1 && nginx -s reload >/dev/null 2>&1; then
        log "nginx reloaded (nginx -s reload)"
        return 0
    fi

    return 1
}

# Put the file back after a rejected install. When there was no previous config —
# the host had nothing installed and this release is the first — "restoring" means
# removing the file this run created, not leaving a config nginx never validated.
restore_nginx_edge_config() {
    if [ -z "${NGINX_CONF_DEST:-}" ]; then
        return 0
    fi

    if [ -n "$EDGE_CONF_BACKUP" ] && [ -f "$EDGE_CONF_BACKUP" ]; then
        if mv -f "$EDGE_CONF_BACKUP" "$NGINX_CONF_DEST"; then
            log "previous edge config restored at $NGINX_CONF_DEST"
            reload_nginx || warn "the restored edge config needs a manual nginx reload"
            return 0
        fi
        warn "could not restore $EDGE_CONF_BACKUP onto $NGINX_CONF_DEST — fix the edge config by hand"
        return 1
    fi

    if [ -f "$NGINX_CONF_DEST" ] && [ -n "$EDGE_CONF_INSTALLED_BY_THIS_RUN" ]; then
        rm -f "$NGINX_CONF_DEST"
        log "removed the edge config this run installed ($NGINX_CONF_DEST); the host had none before"
    fi
    return 0
}

# Render a site template without asking nginx to interpret shell environment
# variables. `envsubst` is deliberately not used: it would also consume nginx's
# own $host/$uri/$document_root variables. These narrow placeholders make the
# host facts explicit and reject characters that could inject an nginx directive.
#
# render_nginx_edge_config <template-path> <output-path>
render_nginx_edge_config() {
    local src="${1:?usage: render_nginx_edge_config <template path> <output path>}"
    local output="${2:?usage: render_nginx_edge_config <template path> <output path>}"

    [ -r "$src" ] || die "cannot render an unreadable nginx template: $src"

    # Existing externally supplied configs and the behavioural test fixtures do
    # not use TOEFL placeholders. Copy them untouched rather than requiring
    # deployment-specific settings they do not need.
    if ! grep -q '__TOEFL_' "$src"; then
        cp -f "$src" "$output" || die "cannot write rendered nginx config: $output"
        return 0
    fi

    local deploy_root="${DEPLOY_ROOT:-/var/www/toefl-house}"
    local server_name="${NGINX_SERVER_NAME:-}"
    local certificate="${NGINX_TLS_CERTIFICATE:-}"
    local certificate_key="${NGINX_TLS_CERTIFICATE_KEY:-}"
    local rendered_output="$output"
    local streamed_output=0

    if grep -q '__TOEFL_DEPLOY_ROOT__' "$src"; then
        if [[ ! "$deploy_root" =~ ^/[A-Za-z0-9._/@+-]+$ ]]; then
            die "DEPLOY_ROOT must be an absolute nginx-safe path when managing NGINX_CONF_DEST"
        fi
    fi

    if grep -q '__TOEFL_SERVER_NAME__' "$src" || grep -q '__TOEFL_TLS_CERTIFICATE' "$src"; then
        if [[ ! "$server_name" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]] || [[ "$server_name" == *..* ]] || [[ "$server_name" == *. ]]; then
            die "NGINX_SERVER_NAME must be one hostname without whitespace, wildcards, or nginx syntax"
        fi
    fi

    certificate="${certificate:-/etc/letsencrypt/live/$server_name/fullchain.pem}"
    certificate_key="${certificate_key:-/etc/letsencrypt/live/$server_name/privkey.pem}"

    if grep -q '__TOEFL_TLS_CERTIFICATE__' "$src" && [[ ! "$certificate" =~ ^/[A-Za-z0-9._/@+-]+$ ]]; then
        die "NGINX_TLS_CERTIFICATE must be an absolute nginx-safe path"
    fi
    if grep -q '__TOEFL_TLS_CERTIFICATE_KEY__' "$src" && [[ ! "$certificate_key" =~ ^/[A-Za-z0-9._/@+-]+$ ]]; then
        die "NGINX_TLS_CERTIFICATE_KEY must be an absolute nginx-safe path"
    fi

    # A standalone caller can request stdout. Render into a real temporary file
    # after input validation so the unresolved-token check reads rendered bytes
    # rather than trying to read a write-only pipe (/dev/stdout).
    case "$output" in
        /dev/stdout|/dev/stderr|/dev/fd/*)
            rendered_output="$(mktemp "${TMPDIR:-/tmp}/toefl-nginx-render.XXXXXX")" \
                || die "cannot create a temporary nginx render file"
            streamed_output=1
            ;;
    esac

    if ! sed \
        -e "s|__TOEFL_DEPLOY_ROOT__|$deploy_root|g" \
        -e "s|__TOEFL_SERVER_NAME__|$server_name|g" \
        -e "s|__TOEFL_TLS_CERTIFICATE__|$certificate|g" \
        -e "s|__TOEFL_TLS_CERTIFICATE_KEY__|$certificate_key|g" \
        "$src" > "$rendered_output"; then
        rm -f "$rendered_output"
        die "cannot write rendered nginx config: $output"
    fi

    if grep -q '__TOEFL_' "$rendered_output"; then
        rm -f "$rendered_output"
        die "nginx template contains an unrendered TOEFL placeholder: $src"
    fi

    if [ "$streamed_output" -eq 1 ]; then
        if ! cat "$rendered_output" > "$output"; then
            rm -f "$rendered_output"
            die "cannot write rendered nginx config: $output"
        fi
        rm -f "$rendered_output"
    fi
}

# Validate the target release's template before a deployment can take a backup,
# migrate the database, or move `current`. The full guarded installation still
# happens after PHP-FPM has accepted the release, but a missing hostname or an
# unsafe path is an operator-input failure that must be reported much earlier.
#
# preflight_nginx_edge_config <path-to-release-conf>
preflight_nginx_edge_config() {
    local src="${1:?usage: preflight_nginx_edge_config <release conf path>}"

    [ -z "${NGINX_CONF_DEST:-}" ] && return 0

    local tmp
    tmp="$(mktemp "${TMPDIR:-/tmp}/toefl-nginx-edge-preflight.XXXXXX")" \
        || die "cannot create a temporary nginx edge preflight file"

    # render_nginx_edge_config uses die() for a precise input error. Isolating it
    # preserves that diagnostic while letting this caller remove its temporary
    # file before it stops the deployment.
    if ! ( render_nginx_edge_config "$src" "$tmp" ); then
        rm -f "$tmp"
        die "managed nginx template preflight failed before the deployment changed application state"
    fi
    rm -f "$tmp"
    log "managed nginx template inputs validated before deployment"
}

# install_nginx_edge_config <path-to-release-conf>
#
# NGINX_CONF_DEST unset  → the edge is managed outside this script. Reported as a
# warning, not an error: plenty of hosts terminate TLS in a proxy this repository
# does not own, and refusing every deploy for them would be wrong. What would be
# wrong is staying silent, so the warning names the consequence.
install_nginx_edge_config() {
    local src="${1:?usage: install_nginx_edge_config <release conf path>}"

    if [ -z "${NGINX_CONF_DEST:-}" ]; then
        warn "NGINX_CONF_DEST is unset, so $src was not installed: the web server keeps the host's own config and the headers in this release (including Content-Security-Policy) do not apply to anything nginx serves directly — static files, 404s, 5xx pages. Point NGINX_CONF_DEST at the file your nginx includes (for example /etc/nginx/conf.d/toefl-house.conf) to manage it from the release."
        return 0
    fi

    if ! command -v nginx >/dev/null 2>&1; then
        die "NGINX_CONF_DEST is set but no nginx binary is on PATH, so the config cannot be validated or reloaded. Install nginx or unset NGINX_CONF_DEST."
    fi

    if [ ! -r "$src" ]; then
        die "this release has no readable edge config at $src; refusing to install an edge config from nowhere"
    fi

    # Do not use a predictable .new.$$ filename here: the deploy commonly runs
    # with elevated privileges, so the candidate config must not be redirectable
    # through a pre-created symlink in a misconfigured writable include directory.
    local tmp
    tmp="$(mktemp "${NGINX_CONF_DEST}.new.XXXXXX")" \
        || die "cannot create a protected temporary edge config beside $NGINX_CONF_DEST"
    if ! render_nginx_edge_config "$src" "$tmp" || ! chmod 0644 "$tmp"; then
        rm -f "$tmp"
        die "cannot render $tmp — check NGINX_SERVER_NAME/TLS paths and permissions on $(dirname "$NGINX_CONF_DEST") (this script usually needs sudo to manage the edge config)"
    fi

    # Nothing to reload when the installed file already matches the release: nginx
    # resolves `root` (the current symlink) per request, so an unchanged config keeps
    # serving the newly activated release without help.
    if [ -f "$NGINX_CONF_DEST" ] && cmp -s "$NGINX_CONF_DEST" "$tmp"; then
        rm -f "$tmp"
        log "edge config already matches this release ($NGINX_CONF_DEST); no reload needed"
        return 0
    fi

    # Validate the *existing* setup first. If it is already broken, installing a new
    # file would leave two ways to be wrong and no way to tell which one broke it.
    if ! nginx -t >/dev/null 2>&1; then
        rm -f "$tmp"
        die "nginx -t already fails with the config the host has now; not replacing $NGINX_CONF_DEST with an untested file on top of a broken edge"
    fi

    EDGE_CONF_INSTALLED_BY_THIS_RUN=1
    if [ -f "$NGINX_CONF_DEST" ]; then
        EDGE_CONF_BACKUP="$NGINX_CONF_DEST.pre-${RELEASE_ID:-previous}"
        if ! cp -p "$NGINX_CONF_DEST" "$EDGE_CONF_BACKUP"; then
            rm -f "$tmp"
            die "cannot back up $NGINX_CONF_DEST before replacing it"
        fi
    fi

    if ! mv -f "$tmp" "$NGINX_CONF_DEST"; then
        die "cannot move $tmp onto $NGINX_CONF_DEST"
    fi
    log "edge config installed from the release: $NGINX_CONF_DEST"

    if ! nginx -t >/dev/null 2>&1; then
        log "nginx rejected the newly installed edge config"
        restore_nginx_edge_config
        die "the edge config shipped in this release failed nginx -t and the previous one was restored. The application release stays active: the edge change is separate, fix $src and re-run."
    fi

    if ! reload_nginx; then
        log "the new edge config validates but nginx could not be reloaded, so the old config is still live"
        restore_nginx_edge_config
        die "nginx was not reloaded, so the installed edge config is not in effect. Set NGINX_RELOAD_CMD (for example: nginx -s reload, or your supervisor's command) and re-run."
    fi

    log "edge config verified and reloaded"
}
