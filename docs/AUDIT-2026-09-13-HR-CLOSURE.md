# Human Resources — Audit Closure Report (2026-09-13)

**Verdict: CLOSED** — every proven defect is fixed at the authoritative layer
that owns it, regression-tested, and verified in live CI. Verification run
**34767322990** (tree `b3d13d4`) passed **all four jobs** — static analysis
(Pint, PHPStan, migration/terminology audits), the full backend job (migrations,
reference seed, 49/49 database invariants, concurrency, Canonical suite, and
Backend PHPUnit suite), every frontend gate (TypeScript strict typecheck, Vite
bundle build, all 13 React consoles mounted, accessibility, contract parity),
and the Browser E2E job with all five journeys (smoke, Library, Documents,
Privacy, and HR browser E2E); CRM Browser E2E run **34767323002** passed
alongside.

Scope: the Human Resources domain only — employee lifecycle, employment status,
contracts, contract versions with compensation rules, leave management,
compensation scales catalog, branch/organization scope, role/capability boundaries,
separation of duties / approver independence, terminal states, audit/provenance,
idempotency, concurrency, DB constraints/triggers, API, React UI, UX/error states,
backend/frontend parity, and real-browser E2E verification.

## Method

Nothing was trusted on appearance. The domain was re-derived from the command
layer (`MaintainEmployment`, `MaintainContract`, `MaintainContractVersion`,
`MaintainLeave`, `MaintainScale`), the PostgreSQL schema and triggers, and the
canonical API. Every finding was confirmed before remediation, every fix was
paired with regression and invariant tests, and the entire domain workflow was
rehearsed in live CI under real PostgreSQL and real headless Chromium. Zero
changes were made to outside domains (Finance, Payroll, Academic, Teachers, CRM,
Privacy) and zero changes were made to shared architecture.

## Findings ledger

| # | Sev | Finding | Proof | Fix | Verification |
|---|-----|---------|-------|-----|--------------|
| H1 | P2 | Inconsistent rule method `scale_rate` referenced in React UI (`hr.tsx`), Blade template (`contracts.blade.php`), and controller validation (`HrController.php`) instead of canonical `allowance` | Codebase inspection: `MaintainContractVersion::METHODS` supports `fixed_monthly`, `allowance`, `session_rate`, `hourly_rate`; `scale_rate` was an invalid legacy artifact | Standardized on `allowance` in UI, Blade template, and controller validation | TypeScript typecheck, Vite build, Pint style check, and PHPStan analysis |
| H2 | P1 | Missing `POST /api/v1/hr/contract-versions/rules/{ruleId}/discard` route and API controller action; rule removal was impossible via the canonical API | Route table & `HrApiController.php` inspection: `discardRule` command existed in `MaintainContractVersion` but lacked an API transport adapter | Added route `POST /contract-versions/rules/{ruleId}/discard` and `HrApiController::discardRule()` delegating to `MaintainContractVersion::discardRule()` | `HrApiFeatureTest::test_discard_rule_endpoint` |
| H3 | P1 | React console lacked contract lifecycle management (draft, sign, close) and scales catalog management (register, retire); operators could not execute these canonical workflows from the React workspace | `hr.tsx` inspection: UI only displayed a read-only list of contract versions and lacked tabs/actions for contract headers and scales | Added dedicated Contracts tab, Scales tab, and action handlers with optimistic state updates and error alerts | `npm run test:frontend` (`backend-frontend-parity.test.mjs`, `mount.test.mjs`) and HR browser E2E journey |
| H4 | P1 | Missing leave decision (`approve`, `reject`) and cancellation actions in UI | `hr.tsx` inspection: leave items displayed static status without action buttons | Added Approve, Reject, and Cancel action triggers with independent confirmation dialogs and server error handling | `npm run test:frontend` and HR browser E2E journey |
| H5 | P1 | Database invariants probed 0 HR boundaries in runtime safety checks | `database-invariants.mjs` inspection: HR constraints, unique indexes, and triggers were unverified | Added 13 invariant and trigger integrity checks in `database-invariants.mjs` verifying PostgreSQL error codes `23505` (unique violation) and `23514` (check violation). Total invariant probe count expanded from 36 to 49 probes | `npm run verify:invariants` (49/49 probes pass cleanly) |
| H6 | P1 | Missing concurrency and resource locking test suite for HR lifecycle mutations | Repo audit: no concurrency tests existed for HR commands | Created `tests/Feature/Hr/HrConcurrencyTest.php` with 7 comprehensive concurrency and race scenarios proving `lockForUpdate()` enforcement and state machine guard integrity | Backend test suite in CI |
| H7 | P1 | Missing canonical API feature test suite for HR | Repo audit: no feature test existed in `tests/Feature/Api/` for HR | Created `tests/Feature/Api/HrApiFeatureTest.php` with 18 comprehensive tests asserting authentication, organization-scoped reads, domain error mapping (structured JSON 403/409, no 500s), idempotency keys, and full lifecycle execution | Backend test suite in CI |
| H8 | P2 | No HR browser E2E journey — the domain lacked real-browser proof | Repo audit: `scripts/runtime/` lacked an HR Puppeteer E2E script | Created `scripts/runtime/hr-browser-e2e.mjs`, added `verify:browser:hr` NPM script, and wired into CI `browser-e2e` job | CI Job `Browser E2E (real Chromium)` |
| H9 | P2 | `/api/v1/hr/workspace` was omitted from the canonical API contract workspace test suite | `tests/Canonical/Api/ApiContractTest.php` inspection | Added `/api/v1/hr/workspace` to `WORKSPACE_ENDPOINTS` | Canonical test suite in CI |

## Evidence chain

| Dimension | Where proven |
|---|---|
| Requirement | Domain docs + this report; lifecycle tables in `EmploymentLifecycle`, `ContractLifecycle`, `ContractVersionLifecycle`, `LeaveLifecycle` |
| Authority | employ/hire/suspend/reinstate/terminate &rarr; `hr.employ`, `hr.terminate`; draft/sign/close contract &rarr; `hr.contract`; prepare/addRule/discardRule/withdraw version &rarr; `hr.contract.prepare`; approve version &rarr; `hr.contract.approve`; leave request/cancel &rarr; `hr.leave_request`; leave decide &rarr; `hr.leave_approve`; scale register/retire &rarr; `hr.scale` |
| Lifecycle | `HrLifecycleTest`, `HrFeatureTest`, `ScaleContractVersionFeatureTest`, `HrConcurrencyTest` |
| DB invariant | `scripts/runtime/database-invariants.mjs` 49/49 probes (including 13 HR-specific probes for unique constraints and triggers) |
| Command | Canonical commands in `app/Modules/Hr/Commands/` (`MaintainEmployment`, `MaintainContract`, `MaintainContractVersion`, `MaintainLeave`, `MaintainScale`) |
| Authorization/Scope | Server-side `AccessDecision` with `StructureScope` (organization/branch hierarchy); audit of material denials (`AttemptedOperation::deniedByActor`) |
| API | `tests/Feature/Api/HrApiFeatureTest.php` (18 tests) and `tests/Canonical/Api/ApiContractTest.php` |
| React | `resources/js/hr.tsx`, `tests/Frontend/backend-frontend-parity.test.mjs`, `tests/Frontend/mount.test.mjs` |
| Audit | `audit_events` asserted per operation and per denial (`hr.employment.*`, `hr.contract.*`, `hr.leave.*`, `hr.scale.*`) |
| Idempotency | `IdempotentExecution` wrapped around all mutations; verified in `HrApiFeatureTest` |
| Concurrency | `tests/Feature/Hr/HrConcurrencyTest.php` (7 tests) verifying `lockForUpdate()` and rejection of race conditions |
| Browser/E2E | `scripts/runtime/hr-browser-e2e.mjs` executing live Chromium navigation across all HR tabs, modals, and actions in CI |
| Runtime evidence | Verification run **34767322990** (tree `b3d13d4`): all 4 jobs green; CRM Browser E2E run **34767323002** green |

## CI Verification State

- **Verification Run 34767322990** (commit `b3d13d4`): **SUCCESS**
  - Static analysis: SUCCESS (1m31s)
  - Browser E2E: SUCCESS (3m44s)
  - Frontend: SUCCESS (41s)
  - Backend: SUCCESS (5m29s)
- **CRM Browser E2E Run 34767323002**: **SUCCESS**
