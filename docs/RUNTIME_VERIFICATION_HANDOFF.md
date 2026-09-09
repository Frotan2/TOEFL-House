# TOEFL House — Runtime Verification Handoff

**STATUS: CURRENT CANONICAL — ACTIVE RUNTIME HANDOFF**  
**Last reconciled:** 2026-09-09  
**Authority branch:** `main`

## 1. Repository identity

- Repository: `Frotan2/TOEFL-House`
- Authoritative branch: `main`
- Backend: Laravel 12.67.0 modular monolith
- Database: PostgreSQL 18.4
- Canonical frontend transport: `resources/js/core/api.ts`
- Canonical navigation: `resources/js/core/navigation.ts`

**Before any work, inspect the actual `main` HEAD and the latest Verification workflow. Do not rely on a stored SHA as current truth.**

## 2. Locked environment

- PHP 8.4.25
- Composer 2.10.3
- Node 22.22.3
- npm 10.9.8
- PostgreSQL 18.4
- Laravel 12.67.0
- React 19.1.1
- Vite 7.3.6
- TypeScript 5.9.x

Use `docs/RUNTIME_ENVIRONMENT_LOCK.md` and `npm run verify:environment`.

## 3. Release-state truth

`AUDIT-2026-09-09-FINAL-CERTIFICATION.md` is historical evidence for its own execution line at commit `96925d3`; it must not certify later commits.

Current release state is determined only by the latest non-superseded Verification workflow attached to the current `main` HEAD, together with explicit release evidence required by the certification protocol.

Never treat an in-progress, cancelled, superseded, or older green run as current certification.

## 4. Current domain gate

**Active domain: Library & Resources.**

Implementation has materially converged around:

- books/copies and circulation;
- issue/return/loss;
- assets and custody;
- facilities work lifecycle;
- staged disposal request → independent approval → execution;
- explicit borrower/custodian selection;
- evidence capture;
- irreversible confirmation;
- server-authoritative scope, lifecycle, audit and idempotency.

Current classification remains **IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED** until the current `main` release gates prove it.

**Do not start Documents or another Partial domain before Library & Resources is closed.**

## 5. Verification gates

```text
Environment
→ Dependencies
→ Static analysis
→ Frontend typecheck/build/mount
→ Fresh PostgreSQL migration replay
→ Database invariants
→ Concurrency
→ Backend suite
→ API/runtime verification
→ Browser E2E
→ Security/scope verification
→ Operational checks
→ Documentation reconciliation
```

A domain may be certified only when all materially applicable gates pass and no unresolved critical/high issue remains.

## 6. Failure handling

When a gate fails:

1. preserve exact failure evidence;
2. classify the failure as product, harness, documentation, environment, or stale-evidence;
3. fix the smallest confirmed root cause;
4. add/update regression coverage;
5. rerun the affected and dependent gates;
6. update canonical documentation;
7. never weaken a test to recover a green status.

## 7. Security/scope runtime verification

Every domain with scoped data or writes must verify as applicable:

- organization isolation;
- campus isolation;
- branch isolation;
- class/record/own scope;
- manager cross-branch denial;
- campus-scoped manager access;
- owner/global access;
- teacher/employee own-record restrictions;
- unauthorized mutation denial;
- invalid lifecycle denial;
- SoD/self-approval denial;
- idempotent replay and conflicting replay;
- concurrent duplicate-write protection.

A hidden frontend button is never a security boundary.

## 8. Frontend/runtime rules

React is the operational client, not the business authority.

- Use `resources/js/core/api.ts`.
- Do not create duplicate API clients.
- Do not reproduce backend lifecycle/accounting/security logic in React.
- Do not use implicit actor selection for consequential actions.
- Gate irreversible actions on server state and explicit confirmation.
- Preserve canonical mount/navigation architecture.
- Keep nested-route asset loading anchored to the document origin.

## 9. Documentation obligations

After every material fix, review/update as applicable:

- `docs/README.md`
- `docs/14-CURRENT-STATE-ROADMAP.md`
- `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
- `docs/DOMAIN-REASSESSMENT-2026-09-09.md`
- `docs/RUNTIME_ENVIRONMENT_LOCK.md`
- this document
- relevant domain specification
- relevant AI governance/decision documents

Historical evidence must remain historically accurate rather than being rewritten to look current.

## 10. Next-agent mandatory reading

Before changing code, read:

1. `docs/ai/00-AI-ENTRYPOINT.md`
2. `docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`
3. `docs/MASTER_ENGINEERING_CONTRACT.md`
4. `docs/14-CURRENT-STATE-ROADMAP.md`
5. `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
6. `docs/DOMAIN-REASSESSMENT-2026-09-09.md`
7. relevant domain documentation and source/tests

Then inspect `main` HEAD and the latest Verification workflow before implementation.

## 11. Evidence vocabulary

- `VERIFIED` — executed and observed successfully.
- `STATICALLY VERIFIED` — source/config/static evidence only.
- `UNVERIFIED` — insufficient evidence.
- `BLOCKED` — prerequisite prevented execution.
- `FAILED` — executed and failed.

Never convert one state into another without evidence.
