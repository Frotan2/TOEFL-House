# TOEFL House — Runtime Verification Handoff

**STATUS: CURRENT CANONICAL — ACTIVE RUNTIME HANDOFF**  
**Last reconciled:** 2026-09-09  
**Authority branch:** `main`

## 1. Current repository identity

- Repository: `Frotan2/TOEFL-House`
- Authoritative branch: `main`
- Current HEAD at last documentation reconciliation: `6e3e6d27ff5b89ffe18416db956f89972098b871`
- Backend: Laravel 12.67.0 modular monolith
- Database: PostgreSQL 18.4
- Canonical frontend transport: `resources/js/core/api.ts`
- Canonical navigation: `resources/js/core/navigation.ts`

**Important:** a newer commit may exist when this file is read. The next agent must inspect `main` first and treat the live branch plus latest Verification run as authoritative.

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

Use `docs/RUNTIME_ENVIRONMENT_LOCK.md` and `npm run verify:environment`; do not copy historical runtime versions from older reports.

## 3. Release-state truth

No current release certification is implied by the existence of `AUDIT-2026-09-09-FINAL-CERTIFICATION.md`. That file records a historical execution line at commit `96925d3` and must remain historical evidence.

The current release state is determined only by the latest non-superseded Verification workflow on the current `main` commit, plus any explicit manually executed release evidence required by `docs/ai/07-RELEASE-CERTIFICATION-PROTOCOL.md`.

Never treat an in-progress, cancelled, superseded, or older green run as certification of the current HEAD.

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

The domain remains **IMPLEMENTATION COMPLETE / RUNTIME-UNVERIFIED** until current verification proves the relevant repository state.

Do not start Documents or another Partial domain before Library & Resources is closed.

## 5. Verification gates

The complete verification sequence is:

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

1. preserve the exact failure evidence;
2. classify it as product, harness, documentation, environment or stale-evidence failure;
3. fix the smallest confirmed root cause;
4. add or update regression coverage;
5. rerun the affected gate and the complete relevant chain;
6. update the canonical status documents;
7. never downgrade or weaken a test merely to recover a green status.

## 7. Security/scope runtime verification

Every domain with scoped data or writes must verify, as applicable:

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
- Gate irreversible actions on server state and require explicit confirmation.
- Preserve the canonical mount/navigation architecture.
- Keep nested-route asset loading anchored to the document origin.

## 9. Documentation obligations

After every material fix, review and update as applicable:

- `docs/README.md`
- `docs/14-CURRENT-STATE-ROADMAP.md`
- `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
- `docs/DOMAIN-REASSESSMENT-2026-09-09.md`
- `docs/RUNTIME_ENVIRONMENT_LOCK.md`
- this document
- the relevant domain specification
- relevant AI governance/decision documents

Historical evidence must remain historically accurate rather than being rewritten to appear current.

## 10. Next-agent mandatory reading

Before changing code, read:

1. `docs/ai/00-AI-ENTRYPOINT.md`
2. `docs/ai/09-NEXT-AGENT-MANDATORY-HANDOFF.md`
3. `docs/MASTER_ENGINEERING_CONTRACT.md`
4. `docs/14-CURRENT-STATE-ROADMAP.md`
5. `docs/15-BACKEND-FRONTEND-PARITY-AUTHORITY.md`
6. the relevant domain documentation and current source/tests

Then inspect `main` HEAD and the latest Verification workflow run.

## 11. Evidence vocabulary

Use these exact meanings:

- `VERIFIED` — executed and observed successfully.
- `STATICALLY VERIFIED` — source/config/static evidence only.
- `UNVERIFIED` — insufficient evidence.
- `BLOCKED` — execution prevented by a missing prerequisite.
- `FAILED` — executed and failed.

Never convert one status to another without evidence.
