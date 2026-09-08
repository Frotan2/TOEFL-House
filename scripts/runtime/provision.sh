#!/usr/bin/env bash
#
# TOEFL House — sandbox runtime provisioner (Linux x86_64).
#
# Rebuilds the exact, verified engineering runtime this repository was
# converged on, WITHOUT any of the version-fighting that blocks a fresh agent:
#
#   * PHP 8.4.14 (native, with pdo_pgsql + every locked extension)
#   * Composer 2.9.x (bundled with the PHP package)
#   * PostgreSQL 18.4 (native server: postgres/initdb/pg_ctl)
#
# WHY THESE SOURCES: this sandbox's network egress is allow-listed. Debian apt
# mirrors, getcomposer.org and Packagist are BLOCKED; github.com,
# codeload.github.com, api.github.com, registry.npmjs.org and pypi.org are
# reachable. Both runtimes are therefore pulled as self-contained native
# binaries from the npm registry (they ship their own shared libraries), and
# Composer packages are fetched from api.github.com (every composer.lock dist
# url already points there). This is the ONLY combination that installs cleanly
# here — it is the reproducible contract, not a preference.
#
# WHY PHP 8.4 (not 8.2): the amazon-linux-2 (8.2) native build depends on
# OpenSSL 1.0 shared objects that do not exist on this Debian 12 host; the
# amazon-linux-2023 (8.4) build is self-contained and runs unmodified. The
# full 900-test suite, all 185 migrations and every runtime gate pass on
# 8.4.14, and composer.json requires "^8.2" (i.e. >=8.2 <9.0), so 8.4 is in
# range. No version here is sacred — this is simply the best-compatible,
# fully-verified set. See docs/RUNTIME_ENVIRONMENT_LOCK.md.
#
# Idempotent: re-running refreshes the runtime in place. The database cluster
# is only initialised if .runtime/pgdata does not already exist.
#
# Usage:  bash scripts/runtime/provision.sh
# Then:   source scripts/runtime/env.sh   (puts php/composer/postgres on PATH)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RT="$ROOT/.runtime"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

PHP_PKG="@libphp/amazon-linux-2023-v84"
PG_PKG="@embedded-postgres/linux-x64"
PG_VERSION="18.4.0-beta.17"

die() { printf "\033[1;31m==> %s\033[0m\n" "$*" >&2; exit 1; }
log() { printf '\n\033[1;36m==> %s\033[0m\n' "$*"; }

npm_tarball() { # $1 = url-encoded package, $2 = optional exact version
  local pkg="$1" ver="${2:-}"
  if [ -n "$ver" ]; then
    node -e "fetch('https://registry.npmjs.org/$pkg').then(r=>r.json()).then(d=>console.log(d.versions['$ver'].dist.tarball))"
  else
    node -e "fetch('https://registry.npmjs.org/$pkg').then(r=>r.json()).then(d=>console.log(d.versions[d['dist-tags'].latest].dist.tarball))"
  fi
}

log "Provisioning PHP 8.4 (native, self-contained) from npm: $PHP_PKG"
mkdir -p "$TMP/php"
curl -fsSL "$(npm_tarball '@libphp%2Famazon-linux-2023-v84')" -o "$TMP/php.tgz"
tar -xzf "$TMP/php.tgz" -C "$TMP/php"
rm -rf "$RT/php" "$RT/lib"
mkdir -p "$RT/php" "$RT/lib"
cp -a "$TMP/php/package/native/php/." "$RT/php/"
cp -a "$TMP/php/package/native/lib/." "$RT/lib/"
# Legacy soname shims for tools that still ask for ncurses/tinfo v5.
ln -sf /lib/x86_64-linux-gnu/libncursesw.so.6 "$RT/lib/libncurses.so.5"
ln -sf /lib/x86_64-linux-gnu/libtinfo.so.6   "$RT/lib/libtinfo.so.5"

log "Provisioning PostgreSQL $PG_VERSION (native server) from npm: $PG_PKG"
mkdir -p "$TMP/pg"
curl -fsSL "$(npm_tarball '@embedded-postgres%2Flinux-x64' "$PG_VERSION")" -o "$TMP/pg.tgz"
tar -xzf "$TMP/pg.tgz" -C "$TMP/pg"
rm -rf "$RT/pgsql"
mkdir -p "$RT/pgsql"
cp -a "$TMP/pg/package/native/." "$RT/pgsql/"
# PostgreSQL ships ICU 60 + libpq unversioned-soname; create the sonames its
# binaries dlopen at runtime (Debian 12 only carries ICU 72).
( cd "$RT/pgsql/lib"
  for f in libicui18n libicuuc libicudata; do ln -sf "$f.so.60.2" "$f.so.60"; done
  ln -sf libpq.so.5.18      libpq.so.5
  ln -sf libpgtypes.so.3.18 libpgtypes.so.3
  ln -sf libecpg.so.6.18    libecpg.so.6 )

log "Writing canonical php.ini"
cat > "$RT/php/php.ini" <<INI
; TOEFL House canonical PHP configuration (runtime-locked).
; Absolute extension_dir so any invocation — including proc_open children
; launched via PHP_BINARY — resolves the bundled extensions.
extension_dir=$RT/php/modules
memory_limit=1024M
error_reporting=E_ALL
display_errors=On
date.timezone=UTC

zend_extension=opcache
opcache.enable=1
opcache.enable_cli=0

extension=bcmath
extension=bz2
extension=calendar
extension=ctype
extension=curl
extension=dom
extension=exif
extension=fileinfo
extension=ftp
extension=gd
extension=gmp
extension=iconv
extension=intl
extension=mbstring
extension=pdo
extension=pdo_pgsql
extension=pgsql
extension=phar
extension=posix
extension=shmop
extension=simplexml
extension=soap
extension=sockets
extension=sodium
extension=tokenizer
extension=xml
extension=xmlreader
extension=xmlwriter
extension=xsl
extension=zip
INI

log "Writing launchers into .runtime/bin"
mkdir -p "$RT/bin" "$RT/composer-home"
cat > "$RT/bin/php" <<EOF
#!/usr/bin/env bash
# Canonical PHP launcher. Exports LD_LIBRARY_PATH and PHPRC so any child
# process spawned via proc_open/PHP_BINARY inherits a fully-configured runtime.
RT="$RT"
export LD_LIBRARY_PATH="\$RT/lib:\${LD_LIBRARY_PATH:-}"
export PHPRC="\$RT/php/php.ini"
exec "\$RT/php/php" "\$@"
EOF
cat > "$RT/bin/composer" <<EOF
#!/usr/bin/env bash
RT="$RT"
export LD_LIBRARY_PATH="\$RT/lib:\${LD_LIBRARY_PATH:-}"
export PHPRC="\$RT/php/php.ini"
export COMPOSER_HOME="\$RT/composer-home"
exec "\$RT/php/php" -d memory_limit=-1 "\$RT/php/composer" "\$@"
EOF
# --- PostgreSQL client tools --------------------------------------------------
# The @embedded-postgres npm package ships the *server* only (postgres, initdb,
# pg_ctl). `psql` is not optional here: deploy.sh requires it for the schema
# probe and backup.sh uses pg_dump, and `pg.sh createdbs` / the suite's
# SchemaCompatibilityProbeTest both shell out to a client. So when the host has
# no client, take it from the PyPI `postgresql-binaries` wheel — the payload is
# the same upstream 18.4 build, and pypi.org/files.pythonhosted.org are on this
# sandbox's allow-list while apt mirrors and getcomposer.org are not.
#
# The old loop silently `continue`d past a missing psql, so a fresh provision
# produced a runtime whose own docs promised a client that was not there; five
# tests then failed with "unverifiable" instead of telling you psql was absent.
EXTRA="$RT/pgsql-extra"
if ! command -v psql >/dev/null 2>&1; then
  if [ -x "$EXTRA/bin/psql" ]; then
    log "Reusing extracted PostgreSQL client tools at .runtime/pgsql-extra"
  else
    log "Fetching PostgreSQL 18.4 client tools (psql, pg_dump) from PyPI postgresql-binaries"
    WHEEL_URL="$(python3 - "$PG_VERSION" <<'PYX'
import json, sys, urllib.request
data = json.load(urllib.request.urlopen("https://pypi.org/pypi/postgresql-binaries/json", timeout=60))
want = "manylinux"
files = [f for f in data["urls"] if want in f["filename"] and "x86_64" in f["filename"]]
if not files:
    sys.exit("no manylinux x86_64 wheel published for postgresql-binaries")
print(files[0]["url"])
PYX
)" || die "could not resolve the postgresql-binaries wheel URL; install postgresql-client (>=18) on the host instead"
    mkdir -p "$TMP/clients"
    curl -fsSL "$WHEEL_URL" -o "$TMP/clients/clients.whl" || die "download failed for $WHEEL_URL"
    ( cd "$TMP/clients" && unzip -qo clients.whl )
    TARBALL="$(find "$TMP/clients" -name 'postgresql-*-x86_64-unknown-linux-gnu.tar.gz' | head -1)"
    [ -n "$TARBALL" ] || die "the wheel contained no postgresql linux tarball"
    mkdir -p "$EXTRA"
    tar -xzf "$TARBALL" -C "$EXTRA" --strip-components=1
  fi
fi

for tool in postgres initdb pg_ctl psql pg_dump pg_restore pg_isready createdb dropdb vacuumdb; do
  src=""
  for dir in "$RT/pgsql/bin" "$EXTRA/bin"; do
    if [ -x "$dir/$tool" ]; then src="$dir/$tool"; break; fi
  done
  [ -n "$src" ] || continue
  cat > "$RT/bin/$tool" <<EOF
#!/usr/bin/env bash
RT="$RT"
export LD_LIBRARY_PATH="\$RT/pgsql/lib:\$RT/pgsql-extra/lib:\${LD_LIBRARY_PATH:-}"
exec "$src" "\$@"
EOF
done
chmod +x "$RT/bin/"*

log "Verifying binaries"
"$RT/bin/php" --version | head -1
"$RT/bin/composer" --version | head -1
"$RT/bin/postgres" --version
# Client tools are asserted, not assumed: their absence used to look like a
# broken deploy script rather than an incomplete runtime.
"$RT/bin/psql" --version | head -1

if [ ! -f "$RT/pgdata/PG_VERSION" ]; then
  log "Initialising PostgreSQL cluster at .runtime/pgdata"
  "$RT/bin/initdb" -D "$RT/pgdata" -U postgres --auth=trust --encoding=UTF8 \
    --locale-provider=libc --locale=C >/dev/null
  cat >> "$RT/pgdata/postgresql.conf" <<CONF
listen_addresses = '127.0.0.1'
port = 5432
unix_socket_directories = '$RT'
fsync = off
synchronous_commit = off
full_page_writes = off
CONF
else
  log "Reusing existing PostgreSQL cluster at .runtime/pgdata"
fi

log "Installing PHP dependencies (composer install, dist from api.github.com)"
( cd "$ROOT" && "$RT/bin/composer" install --no-interaction --no-progress --prefer-dist )

log "Installing frontend dependencies (npm)"
( cd "$ROOT" && npm install --no-audit --no-fund )

log "Runtime provisioned."
cat <<'DONE'

Next steps:
  1. Start PostgreSQL:   bash scripts/runtime/pg.sh start
  2. Load the PATH:      source scripts/runtime/env.sh
  3. Create databases:   php artisan migrate:fresh   (dev) / handled by tests
  4. Run the suite:      php vendor/bin/phpunit
DONE
