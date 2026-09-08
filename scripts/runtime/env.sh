# TOEFL House — runtime environment.
# Usage:  source scripts/runtime/env.sh
#
# Puts the provisioned PHP 8.4, Composer 2.9 and PostgreSQL 18.4 launchers on
# PATH and exports the database connection the app and tests use. Safe to
# source repeatedly.

_TH_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]:-$0}")/../.." && pwd)"

export PATH="$_TH_ROOT/.runtime/bin:$PATH"
export LD_LIBRARY_PATH="$_TH_ROOT/.runtime/lib:$_TH_ROOT/.runtime/pgsql/lib:${LD_LIBRARY_PATH:-}"
export PHPRC="$_TH_ROOT/.runtime/php/php.ini"
export COMPOSER_HOME="$_TH_ROOT/.runtime/composer-home"

# Database contract (PostgreSQL only; trust auth on the local cluster).
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_USERNAME=postgres
export DB_PASSWORD=
export DB_DATABASE="${DB_DATABASE:-toefl_house_dev}"

echo "TOEFL House runtime ready: $(php --version | head -1)"
