# TOEFL House — Runtime Environment Compatibility Lock

**STATUS: ACTIVE / TECHNICAL RUNTIME CONTROL**

**Release authority:** [`RUNTIME-RELEASE.md`](RUNTIME-RELEASE.md)

**Machine enforcer:** `scripts/runtime/verify-environment.mjs`

**Last reconciled:** 2026-09-10

This document defines the compatibility boundary used by deployment and local
verification. `RUNTIME-RELEASE.md` owns release status and evidence semantics;
this lock owns the concrete runtime ranges that its release gates enforce.

## 1. Supported compatibility ranges

| Component | Supported range | Current CI / launcher reference | Reproducible local provisioner reference |
|---|---|---:|---:|
| PHP | `>=8.2 <8.5` | 8.4.25 | 8.4.14 |
| Composer | `>=2.5 <3` | 2.10.3 | 2.9.2 |
| Laravel | `>=12.67 <13.0` | 12.67.0 | 12.67.0 |
| PostgreSQL | `>=18.0 <19.0` | 18.4 | 18.4 |
| Node.js | `>=22.0 <23.0` | 22.22.3 | 22.22.3 |
| npm | `>=10.0 <11.0` | 10.9.8 | 10.9.8 |

PostgreSQL is the only supported database. SQLite and `pdo_sqlite` are not a
fallback. Laravel 13 is outside the supported range.

A reference version makes CI and a platform launcher reproducible; it is not a
second host-eligibility rule. A host is supported only when every applicable
component is inside its range and the required runtime checks pass. This avoids
rejecting the self-contained, versioned local runtime solely because it uses a
verified patch different from the CI reference.

## 2. Binding implementation locations

- `scripts/runtime/verify-environment.mjs` enforces the ranges, PHP extension
  set, PostgreSQL PDO driver, PostgreSQL connection and Laravel boundary.
- `package.json` and the root package entry in `package-lock.json` declare the
  Node.js and npm ranges. `npm ci --engine-strict` makes those declarations
  binding.
- `.github/workflows/verification.yml` and
  `.github/workflows/crm-browser-e2e.yml` pin their CI reference versions.
- `START-TOEFL-HOUSE.bat` may use a concrete Windows artifact version inside
  these ranges; its final environment check is still this lock.
- `deploy/deploy.sh` delegates runtime eligibility to the machine enforcer and
  must not repeat version comparisons of its own.

`docs/RUNTIME_ENVIRONMENT.md` is retained as a superseded build-history
narrative, not as a current requirement source.

## 3. Required PHP capabilities

The environment verifier requires these extensions and fails closed when any is
absent:

```text
bcmath, ctype, curl, dom, fileinfo, filter, hash, iconv, json, libxml,
mbstring, openssl, pcntl, pcre, pdo_pgsql, phar, posix, session, tokenizer,
xml, xmlwriter
```

The active connection must use `DB_CONNECTION=pgsql`, and the runtime must
expose the `pgsql` PDO driver. The application must boot on Laravel 12.67 or
newer within the Laravel 12 line.

## 4. Reproducible local runtime

For a Linux x86_64 environment without suitable system services, the local
provisioner uses immutable package coordinates rather than an unpinned `latest`
tag:

```text
@libphp/amazon-linux-2023-v84@0.0.8  → PHP 8.4.14 and Composer 2.9.2
@embedded-postgres/linux-x64@18.4.0-beta.17 → PostgreSQL 18.4
```

Run it and initialize disposable databases with:

```bash
bash scripts/runtime/provision.sh
source scripts/runtime/env.sh
bash scripts/runtime/pg.sh start
bash scripts/runtime/pg.sh createdbs
npm run verify:environment
```

The package coordinates are a provisioning implementation detail. The
compatibility range, extensions and real verification results are the actual
host contract.

## 5. Change control

A runtime change is material. Before accepting one:

1. update this lock, the verifier, package engine ranges, and any affected CI
   reference or platform launcher together;
2. keep CI/launcher references inside the declared ranges;
3. run the applicable dependency, platform, migration, invariant, concurrency,
   backend and frontend verification gates on the changed runtime; and
4. record only fresh evidence in the release control; historical passes do not
   certify a changed commit or changed runtime.

Do not weaken a range, extension requirement, or database requirement merely to
make a local command pass. Resolve the incompatibility or revise this control
through the normal release process with new evidence.
