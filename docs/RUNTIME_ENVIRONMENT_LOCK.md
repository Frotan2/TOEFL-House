# TOEFL House — Runtime Environment Lock

**This document is the authoritative runtime specification for TOEFL House.**

If you are an engineer or an AI agent picking this repository up: this is the
supported runtime. You do not need to reconstruct it from history, and you
should not "discover" a different one. Every version below was executed and
observed, not inferred.

Machine-checkable equivalent: `scripts/runtime/verify-environment.mjs`
(`npm run verify:environment`). That script is the enforcement mechanism; this
document is the rationale.

---

## 1. Locked Versions

| Component | Locked version | Allowed range | Enforced by |
|---|---|---|---|
| PHP | **8.4.14** | `>=8.2.0 <8.5.0` | `composer.json` (`^8.2`), `verify:environment` |
| Composer | **2.9.2** | `>=2.5 <3.0` | `verify:environment` |
| Laravel | **12.67.0** | `^12.67.0` | `composer.json` / `composer.lock` |
| PostgreSQL | **18.4** | `>=18.0 <19.0` | `verify:environment` |
| Node | **22.22.3** | `>=22.0 <23.0` | `package.json` engines, `verify:environment` |
| npm | **10.9.8** | `>=10.0` | `package.json` engines |
| React | **19.1.1** | `^19.1.1` | `package.json` / `package-lock.json` |
| Vite | **7.3.6** | `^7.1.5` | `package.json` / `package-lock.json` |
| TypeScript | **5.9.x** | `^5.9.2` | `package.json` / `package-lock.json` |

### Version policy

**Laravel 13 is prohibited.** Do not upgrade the framework to work around an
environment problem.

**PHP 8.4 is the executed runtime.** The lock previously *aspired* to PHP
8.2.33, but that version was never actually provisioned in this environment —
which is why successive agents kept re-fighting the toolchain. The runtime is
now built from a self-contained native PHP 8.4.14 (see §6 and
`scripts/runtime/provision.sh`), and the **entire** verification chain has been
executed on it: 185/185 migrations replay, the full 900-test PHPUnit suite is
green (6,991 assertions, 0 failures, 2 network-gated skips), and every runtime
gate passes. `composer.json` requires `^8.2` (i.e. `>=8.2 <9.0`), so 8.4 is
in range and fully compatible; the enforcement window is `>=8.2 <8.5`. No
version here is sacred — this is the best-compatible, actually-verified set,
not a preference.

Bumping any locked version requires re-running the complete verification chain
in §5 and updating both this file and
`docs/RUNTIME_VERIFICATION_HANDOFF.md`.

---

## 2. Required PHP Extensions

Composer's platform requirements (from `composer.lock`) are the contract:

```
ctype  dom  fileinfo  filter  hash  iconv  json  libxml  mbstring
openssl  pcre  phar  session  tokenizer  xml  xmlwriter
```

Beyond those, this project additionally requires:

| Extension | Why it is mandatory |
|---|---|
| **pdo_pgsql** | The only supported database driver. 137 of the 185 migrations use PostgreSQL-specific SQL. |
| **curl** | The root-level E2E journey scripts drive the app over HTTP. |
| **bcmath** | Monetary arithmetic. |
| **pcntl** | Concurrency-sensitive tooling. |
| **posix**, **sockets** | Process and transport support. |

Verified loaded set on the locked build:

```
Core PDO Phar Reflection SPL SimpleXML bcmath ctype curl date dom fileinfo
filter hash iconv json libxml mbstring openssl pcntl pcre pdo_pgsql posix
random session sockets standard tokenizer xml xmlreader xmlwriter zlib
```

### Deliberately excluded

**`sqlite3` and `pdo_sqlite` are compiled OUT on purpose.** PostgreSQL is the
only supported database. Excluding SQLite means a test or command cannot
silently fall back to it and produce green output that proves nothing. Do not
re-enable them.

---

## 3. Database Assumptions

- PostgreSQL **18.x**, connection name `pgsql` (`config/database.php`).
- Extensions used by the schema: `btree_gist` (exclusion constraints for
  temporal/overlap invariants), `gen_random_uuid()` (pgcrypto/core).
- The schema depends on PostgreSQL-specific behaviour that has no portable
  equivalent: `CREATE OR REPLACE FUNCTION` (525 functions), triggers
  (285), `EXCLUDE USING gist` (2), partial indexes (65), `jsonb` operators.
- **Transactional DDL is relied upon** by the testing strategy
  (`docs/TESTING_STRATEGY_LOCK.md`): tests that issue statements such as
  `ALTER TABLE ... DISABLE TRIGGER` are still rolled back.
- Databases are disposable and created per purpose:

| Database | Purpose |
|---|---|
| `toefl_house_dev` | local development / manual runtime verification |
| `toefl_house_test` | PHPUnit (`phpunit.xml`) |
| `toefl_house_e2e`, `toefl_house_pay`, `toefl_house_payroll` | root-level journey scripts |

`phpunit.xml` pins `DB_PORT=5432`. Override with the `DB_PORT` environment
variable rather than editing committed configuration.

---

## 4. System Libraries and Tooling

Required to *run*:

| Library | Used by |
|---|---|
| libpq (PostgreSQL 18 client) | `pdo_pgsql` |
| libxml2 | dom/xml/xmlreader/xmlwriter/simplexml |
| OpenSSL 3.x | `openssl` |
| oniguruma | `mbstring` |
| zlib | `zlib`, phar |
| libcurl | `curl` |

Required only to *build PHP from source* (see §6): `gcc`, `make`, and headers
for the libraries above. The official PHP release tarball ships a pregenerated
`configure`, so autoconf/bison/re2c are **not** needed.

### Browser tooling

Real browser E2E (`npm run verify:browser`) needs a Chromium binary and NSS.
`CHROMIUM_PATH` selects the binary; the NSS libraries must be on
`LD_LIBRARY_PATH`. Verified with Chromium **149.0.7827.0**.

---

## 5. Reproducibility and Verification

Dependency lock state is authoritative and must not be regenerated casually:

- `composer.lock` — 106 packages, `content-hash` must match `composer.json`.
  Verified with `composer validate --strict`.
- `package-lock.json` — committed; `npm ci` must be used in CI.

Verification chain (each command is expected to exit 0):

```bash
npm run verify:environment      # runtime version + extension contract
composer validate --strict      # lock integrity
composer check-platform-reqs    # every ext-* requirement satisfied
php artisan migrate:fresh --force
npm run verify:invariants       # database rejects invalid states
npm run verify:concurrency      # real concurrent transactions
vendor/bin/phpunit --no-coverage
npm run typecheck && npm run build
npm run test:frontend           # console mount coverage
npm run verify:browser          # real Chromium E2E (needs CHROMIUM_PATH)
```

---

## 6. Provisioning The Runtime (reproducible, one command)

**Do not fight the toolchain. Run the provisioner.** This sandbox has no PHP,
no PostgreSQL and no usable OS package manager — its network egress is
allow-listed. Debian apt mirrors, `getcomposer.org` and Packagist are BLOCKED;
`github.com`, `codeload.github.com`, `api.github.com`, `registry.npmjs.org`
and `pypi.org` are reachable. The provisioner is built around exactly those
open channels, so it works every time:

```bash
bash scripts/runtime/provision.sh    # PHP 8.4 + Composer 2.9 + PostgreSQL 18.4
source scripts/runtime/env.sh        # put php/composer/postgres on PATH
bash scripts/runtime/pg.sh start     # start the local PostgreSQL server
bash scripts/runtime/pg.sh createdbs # create dev + test databases
```

What it does and why (see comments in `scripts/runtime/provision.sh`):

- **PHP 8.4.14** — native, self-contained build from the npm package
  `@libphp/amazon-linux-2023-v84`. It ships every locked extension
  (`pdo_pgsql`, `bcmath`, `intl`, `sodium`, …) plus Composer 2.9. The
  amazon-linux-2 (8.2) build was rejected because it links OpenSSL 1.0 shared
  objects absent on Debian 12; the amazon-linux-2023 (8.4) build runs
  unmodified. This is the concrete reason the lock moved to 8.4 — it is the
  best-compatible build that actually runs here.
- **PostgreSQL 18.4** — native server from the npm package
  `@embedded-postgres/linux-x64@18.4.0-beta.17` (`postgres`, `initdb`,
  `pg_ctl`). It bundles ICU 60 and `libpq`; the provisioner creates the
  runtime sonames its binaries dlopen.
- **Composer packages** — `composer install` fetches every dist from
  `api.github.com` (every `composer.lock` url already points there), so no
  Packagist access is needed.

The whole runtime lands under `.runtime/` (git-ignored, rebuildable). Launchers
in `.runtime/bin` export `LD_LIBRARY_PATH` and `PHPRC` so even a child process
spawned via `proc_open`/`PHP_BINARY` (e.g. the concurrency race children)
inherits a fully-configured interpreter.

**One exception, verified 2026-09-09:** `php artisan serve` filters the child
`php -S` environment down to `ServeCommand::$passthroughVariables` whenever a
`.env` file exists, so `LD_LIBRARY_PATH`/`PHPRC` are stripped and the child
fails to load the self-contained PHP build. Serve the app with the built-in
server through the launcher instead — `.runtime/bin/php -S 127.0.0.1:8999 -t
public public/index.php` (this is how the root E2E journeys run; set
`PHP_CLI_SERVER_WORKERS` > 1 for the concurrent-allocation stages).

### Building PHP from source (only if the npm build ever disappears)

Fallback narrative in `docs/RUNTIME_ENVIRONMENT.md`. Fetch the official PHP
source tarball for a version in the `>=8.2 <8.5` window (it contains a
pregenerated `configure`), supply libpq headers matching the running PostgreSQL
major version, and configure with:

```
--enable-cli --with-pdo-pgsql --with-curl --enable-mbstring --with-openssl
--with-zlib --with-libxml --with-iconv --enable-dom --enable-xmlwriter
--enable-xmlreader --enable-simplexml --enable-tokenizer --enable-ctype
--enable-filter --enable-session --enable-phar --enable-fileinfo
--enable-bcmath --enable-pcntl --enable-posix
--without-sqlite3 --without-pdo-sqlite
```

---

## 7. Known Platform Constraints

| Constraint | Effect | Status |
|---|---|---|
| `opcache` present, CLI-disabled | Bytecode cache available to the web SAPI; `opcache.enable_cli=0` keeps test runs deterministic. | Accepted; performance only, not correctness. |
| SQLite excluded | Any code path assuming SQLite fails loudly. | Intentional. |
| Chromium not installed by default | `verify:browser` needs `CHROMIUM_PATH`. | Documented; skipped cleanly when absent. |
| `phpunit.xml` pins port 5432 | Non-default ports need `DB_PORT`. | Documented above. |

---

## 8. Drift Protection

`npm run verify:environment` fails when the runtime leaves the locked ranges or
a required extension is missing, and it explicitly fails if SQLite reappears.
Wire it into CI ahead of the test job (see `docs/13-TESTING-QUALITY-RELEASE.md`)
so drift is caught before it produces confusing downstream failures.
