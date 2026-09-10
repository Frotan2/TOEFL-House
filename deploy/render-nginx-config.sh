#!/usr/bin/env bash
# Render a reviewed TOEFL nginx template to stdout.
#
# Usage:
#   DEPLOY_ROOT=/var/www/toefl-house NGINX_SERVER_NAME=school.example \
#     ./deploy/render-nginx-config.sh deploy/nginx/toefl-house.conf > /tmp/toefl-house.conf
#
# The deployment script invokes the same renderer before it installs a managed
# NGINX_CONF_DEST. This standalone form exists for the one-time ACME bootstrap
# template, before a full TLS site can be enabled.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./lib/nginx-edge-config.sh
source "$SCRIPT_DIR/lib/nginx-edge-config.sh"

TEMPLATE="${1:?usage: render-nginx-config.sh <template-path>}"
render_nginx_edge_config "$TEMPLATE" /dev/stdout
