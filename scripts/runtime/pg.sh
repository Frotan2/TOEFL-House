#!/usr/bin/env bash
#
# TOEFL House — PostgreSQL cluster control for the sandbox runtime.
#
# Usage:
#   bash scripts/runtime/pg.sh start     # start the local PostgreSQL 18.4 server
#   bash scripts/runtime/pg.sh stop      # stop it
#   bash scripts/runtime/pg.sh status    # report whether it is running
#   bash scripts/runtime/pg.sh createdbs # ensure dev + test databases exist
#
# The server listens on 127.0.0.1:5432 with trust auth (local dev only).

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RT="$ROOT/.runtime"
export LD_LIBRARY_PATH="$RT/pgsql/lib:${LD_LIBRARY_PATH:-}"

case "${1:-}" in
  start)
    if "$RT/pgsql/bin/pg_ctl" -D "$RT/pgdata" status >/dev/null 2>&1; then
      echo "PostgreSQL already running."
    else
      "$RT/pgsql/bin/pg_ctl" -D "$RT/pgdata" -l "$RT/pg.log" -w start
    fi
    ;;
  stop)
    "$RT/pgsql/bin/pg_ctl" -D "$RT/pgdata" -m fast stop
    ;;
  status)
    "$RT/pgsql/bin/pg_ctl" -D "$RT/pgdata" status
    ;;
  createdbs)
    "$RT/bin/php" -r '
      $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=postgres", "postgres", "");
      foreach (["toefl_house_dev", "toefl_house_test"] as $db) {
        $exists = $pdo->query("SELECT 1 FROM pg_database WHERE datname=".$pdo->quote($db))->fetchColumn();
        if (! $exists) { $pdo->exec("CREATE DATABASE $db"); echo "created $db\n"; }
        else { echo "$db exists\n"; }
      }
    '
    ;;
  *)
    echo "usage: $0 {start|stop|status|createdbs}" >&2
    exit 2
    ;;
esac
