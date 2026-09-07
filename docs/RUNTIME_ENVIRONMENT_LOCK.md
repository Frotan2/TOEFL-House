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
| PHP | **8.2.33** | `>=8.2.0 <8.3.0` | `composer.json` (`^8.2`), `verify:environment` |
| Composer | **2.8.12** | `>=2.5 <3.0` | `verify:environment` |
| Laravel | **12.67.0** | `^12.67.0` | `composer.json` / `composer.lock` |
| PostgreSQL | **18.4** | `>=18.0 <19.0` | `verify:environment` |
| Node | **22.22.3** | `>=22.0 <23.0` | `package.json` engines, `verify:environment` |
| npm | **10.9.8** | `>=10.0` | `package.json` engines |
| React | **19.1.1** | `^19.1.1` | `package.json` / `package-lock.json` |
| Vite | **7.3.6** | `^7.1.5` | `package.json` / `package-lock.json` |
| TypeScript | **5.9.x** | `^5.9.2` | `package.json` / `package-lock.json` |

### Version policy

**Laravel 13 is prohibited.** Do not upgrade the framework to work around an
environment problem. PHP 8.3+ is outside the locked range: the project targets
`^8.2` and the full suite has only been executed on 8.2.33.

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

## 6. Building PHP When No Package Exists

Recorded because this environment had no PHP and no package manager access.
Full narrative in `docs/RUNTIME_ENVIRONMENT.md`.

Summary: fetch the official `php-8.2.33.tar.xz` (it contains a pregenerated
`configure`), supply libpq headers matching the running PostgreSQL major
version, and configure with:

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
| `opcache` not built | No bytecode cache in this environment. | Accepted; performance only, not correctness. |
| SQLite excluded | Any code path assuming SQLite fails loudly. | Intentional. |
| Chromium not installed by default | `verify:browser` needs `CHROMIUM_PATH`. | Documented; skipped cleanly when absent. |
| `phpunit.xml` pins port 5432 | Non-default ports need `DB_PORT`. | Documented above. |

---

## 8. Drift Protection

`npm run verify:environment` fails when the runtime leaves the locked ranges or
a required extension is missing, and it explicitly fails if SQLite reappears.
Wire it into CI ahead of the test job (see `docs/13-TESTING-QUALITY-RELEASE.md`)
so drift is caught before it produces confusing downstream failures.
