# TOEFL House — Runtime Environment

> **SUPERSEDED (2026-09-09) — retained as the from-source fallback narrative.**
> The PHP 8.2.33 build described here was an intermediate stage. The locked
> runtime is **PHP 8.4.14** provisioned by `scripts/runtime/provision.sh`
> (self-contained npm-carried build); the authoritative specification is
> `RUNTIME_ENVIRONMENT_LOCK.md`. `RUNTIME_ENVIRONMENT_LOCK.md` §6 points back
> to this document only as the fallback if the npm-carried PHP build ever
> disappears. Nothing below is the current runtime.

How the verified runtime was established, so it can be reproduced. Every
version below was observed in the running environment, not inferred.

## 1. Verified Versions

| Component | Target | Observed | Evidence |
|---|---|---|---|
| PHP | 8.2.x | **8.2.33** | `php --version` |
| Laravel | ^12.67.0 | **12.67.0** | `php artisan about` |
| Composer | 2.x | **2.8.12** | `composer --version` |
| PostgreSQL | 18.x | **18.4** | `php artisan db:show` |
| Node | 22.x | **22.22.3** | `node -v` |
| npm | — | **10.9.8** | `npm -v` |
| React / Vite / TypeScript | 19 / 7 / 5.9 | 19.1.1 / 7.3.6 / 5.9.x | `npm run build` |

Laravel 13 is **not** installed. `composer.json` and `composer.lock` were not
modified to make the environment work.

## 2. PHP 8.2.33 With `pdo_pgsql`

The sandbox has no PHP and cannot reach `deb.debian.org`, `php.net` or GitHub
release assets. A php-wasm build was rejected: it has **no `pdo_pgsql`**, and
the then-current migration chain used PostgreSQL-specific SQL, so SQLite
substitution was both prohibited and useless there.

PHP was therefore **built from official source**:

1. `php-8.2.33.tar.xz` fetched through the GitHub **blobs API** of
   `php/web-php-distributions` (reachable, unlike release-asset hosts). The
   official tarball ships a pregenerated `configure`, so autoconf/bison/re2c
   are not required.
2. libpq headers from `postgres/postgres` tag `REL_18_4` (matching the running
   server) combined with the prebuilt `libpq.so` from the embedded PostgreSQL
   package.
3. libxml2, oniguruma, zlib and OpenSSL 3 headers generated from source and
   linked against the system shared libraries.
4. A minimal `pg_config` / `pkg-config` shim so `configure` can resolve them.

```
./configure --prefix=/tmp/phpinst \
  --enable-cli --with-pdo-pgsql=/tmp/pgroot \
  --without-sqlite3 --without-pdo-sqlite \
  --enable-mbstring --with-openssl --with-zlib --with-libxml --with-iconv \
  --enable-dom --enable-xmlwriter --enable-xmlreader --enable-simplexml \
  --enable-tokenizer --enable-ctype --enable-filter --enable-session \
  --enable-phar --enable-fileinfo --enable-bcmath --enable-pcntl --enable-posix
```

SQLite was deliberately **excluded** so the suite cannot silently fall back off
PostgreSQL.

Loaded extensions: bcmath, Core, ctype, date, dom, fileinfo, filter, hash,
iconv, json, libxml, mbstring, openssl, pcntl, pcre, PDO, **pdo_pgsql**, Phar,
posix, random, Reflection, session, SimpleXML, sockets, SPL, standard,
tokenizer, xml, xmlreader, xmlwriter, zlib.

`composer check-platform-reqs` reports **success for every requirement**.

## 3. Composer 2.8.12

`packagist.org` and `getcomposer.org` are unreachable, but every `dist.url` in
both `composer.lock` files points at `api.github.com`, which is reachable.
Composer 2.8.12 source was fetched via codeload, its own 29 locked dependencies
installed from their recorded dist URLs, and an autoloader generated so the
**real** Composer binary could run. `composer install` then installed all 106
project packages normally.

No dependency version was altered and no lock file was fabricated:
`git status composer.json composer.lock` is clean.

## 4. PostgreSQL 18.4

Provided by `@embedded-postgres/linux-x64` (npm), initialised with `initdb` and
started on `127.0.0.1:5433`.

Databases (all disposable):

| Database | Purpose |
|---|---|
| `toefl_house_dev` | migration replay, schema and invariant verification |
| `toefl_house_test` | PHPUnit (`phpunit.xml`), recreated per run |

`phpunit.xml` pins port 5432; the port is overridden with `DB_PORT=5433` at the
environment level rather than editing committed configuration.

## 5. Reproducing

```bash
export PATH=/tmp/bin:$PATH                  # php + composer launchers
export LD_LIBRARY_PATH=/tmp/deps/lib:/tmp/pgroot/lib
export DB_PORT=5433 DB_SSLMODE=disable

composer install
php artisan key:generate
php artisan migrate:fresh --force
php artisan db:seed --class=StandardFinanceChartSeeder --force

vendor/bin/phpunit --no-coverage        # backend suite
npm run typecheck && npm run build      # frontend gates
npm run test:frontend                   # console mount tests
npm run verify:invariants               # database negative tests
npm run verify:concurrency              # real concurrent transactions
php artisan serve --host=0.0.0.0 --port=8000
```

## 6. Environment Limitations

- The PHP build excludes `opcache` (no usable shared memory in the sandbox) and
  `sqlite3` (deliberate). Neither affects correctness verification.
- `/tmp` holds the toolchain; it is outside the repository and not persisted by
  Git, which is intentional.
- No browser engine is available, so browser E2E remains unverified.

---

## 7. Superseded By The Lock

`docs/RUNTIME_ENVIRONMENT_LOCK.md` is now the authoritative runtime
specification and the machine-checked contract (`npm run verify:environment`).

This file remains the narrative record of **how** the runtime was obtained when
no PHP, no Composer and no package manager access existed. Read it when you
need to rebuild the toolchain from nothing; read the lock when you need to know
what is supported.

One addition since it was written: **`curl` is now compiled in**. The root
level E2E journey scripts drive the application over HTTP and cannot run
without it.
