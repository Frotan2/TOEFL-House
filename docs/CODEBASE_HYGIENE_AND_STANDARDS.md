# TOEFL House — Codebase Hygiene & Engineering Standards

**STATUS: ACTIVE / CANONICAL / NORMATIVE**  
**Authority:** this document for repository cleanup/coding hygiene; architecture/domain/runtime subjects remain owned by their canonical documents.

## Core rule

**ONE FACT → ONE OWNER → ONE WRITE AUTHORITY → ONE LIFECYCLE.**

Preferred cleanup order: `delete > simplify > consolidate > refactor > abstract`.

## Safe deletion

Never delete a file, symbol, route, config key, asset or test helper from text-search evidence alone. Check runtime imports, routes, events, listeners, jobs, schedulers, framework discovery, reflection, tests, fixtures, migrations, deployment, external contracts and supported documentation.

After deletion: re-audit references and run the strongest applicable verification.

## Legacy disposition

Each legacy artifact receives exactly one disposition:

- `DELETE` — proven obsolete/unused;
- `REFACTOR` — still needed but structurally non-convergent;
- `REPLACE WITH CANONICAL AUTHORITY` — duplicate business implementation;
- `KEEP AS DOCUMENTED COMPATIBILITY ADAPTER` — supported contract still requires it.

Every retained compatibility adapter needs an objective removal gate.

## Canonical engineering boundaries

- Domain commands/services own business mutations.
- Controllers/routes are transport boundaries.
- PostgreSQL is part of the durable invariant boundary where applicable.
- `resources/js/core/api.ts` is the canonical frontend transport client.
- React owns presentation and interaction, not business truth.
- Reporting, projections and integrations do not become competing operational authorities.

## Coding standards

Use strict, explicit PHP and TypeScript. Avoid unsafe casts, swallowed exceptions, secret logging, dead state, duplicate normalization, premature abstractions and route-closure business logic. New UI must reuse the existing design system.

## API and database hygiene

`/api/v1` is the canonical interactive application transport. No second client, hidden fetch convention or alternate business write path is permitted without an approved boundary.

The PostgreSQL migration chain remains authoritative until an explicit baseline decision says otherwise. Never fabricate migrations, schema dumps or production DDL.

## Repository hygiene

Do not commit real credentials, private keys, local `.env`, logs, dumps, dependency caches or runtime artifacts unless explicitly required as versioned fixtures.

## Evidence discipline

Use `VERIFIED`, `STATICALLY VERIFIED`, `UNVERIFIED`, `BLOCKED`, `FAILED` and `HISTORICAL` only according to actual evidence. Runtime and release conclusions are owned exclusively by `docs/RUNTIME-RELEASE.md`.

## Cleanup lifecycle

`Discover → Prove → Change → Verify → Re-audit → Document`

Do not weaken tests, security controls or database invariants to achieve a green result.
