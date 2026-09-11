#!/usr/bin/env bash
# Reload nginx only after Certbot has successfully replaced a certificate.
#
# Install as /etc/letsencrypt/renewal-hooks/deploy/10-toefl-house-nginx-reload
# after the first certificate is issued. Certbot invokes deploy hooks only for
# certificates that were actually renewed. Testing nginx before reload means a
# separate host configuration mistake cannot turn a valid new certificate into
# a broken edge process.
set -euo pipefail

NGINX_BIN="${NGINX_BIN:-nginx}"
SYSTEMCTL_BIN="${SYSTEMCTL_BIN:-systemctl}"

command -v "$NGINX_BIN" >/dev/null 2>&1 \
    || { printf '%s\n' "[certbot-hook][ERROR] nginx binary not found: $NGINX_BIN" >&2; exit 1; }
if ! "$NGINX_BIN" -t; then
    printf '%s\n' '[certbot-hook][ERROR] nginx configuration test failed; refusing to reload after certificate renewal' >&2
    exit 1
fi

# Certbot hooks are also useful on a supervisor-managed or systemd-less host.
# Prefer the host service manager when present, but use nginx's own control
# signal as a checked fallback rather than reporting a successful renewal while
# workers still hold the old certificate files open.
if command -v "$SYSTEMCTL_BIN" >/dev/null 2>&1 && "$SYSTEMCTL_BIN" reload nginx; then
    printf '%s\n' '[certbot-hook] nginx configuration verified and reloaded via systemctl after certificate renewal'
elif "$NGINX_BIN" -s reload; then
    printf '%s\n' '[certbot-hook] nginx configuration verified and reloaded via nginx signal after certificate renewal'
else
    printf '%s\n' '[certbot-hook][ERROR] nginx validated but could not be reloaded after certificate renewal' >&2
    exit 1
fi
