# TOEFL House — AI Agent Entry Point

This file is the **mandatory entry point for AI engineering agents** working in this repository.

Before inspecting, changing, testing, merging, deleting, refactoring, redesigning, or declaring completion, read:

1. [`docs/00-AI-ENGINEERING-EXECUTION-DIRECTIVE.md`](docs/00-AI-ENGINEERING-EXECUTION-DIRECTIVE.md) — AI execution standard.
2. [`docs/MASTER_ENGINEERING_CONTRACT.md`](docs/MASTER_ENGINEERING_CONTRACT.md) — project engineering constitution and highest-level project authority.
3. [`docs/02-TARGET-ARCHITECTURE.md`](docs/02-TARGET-ARCHITECTURE.md) — canonical architecture.
4. [`docs/04-DATA-AUTHORITY-PROVENANCE.md`](docs/04-DATA-AUTHORITY-PROVENANCE.md) — data ownership and provenance.
5. [`docs/05-SECURITY-RBAC-GOVERNANCE.md`](docs/05-SECURITY-RBAC-GOVERNANCE.md) — security and authorization governance.
6. The applicable domain, frontend, reporting, privacy, requirements, decision, and verification documents already present in `docs/`.

Then inspect the actual repository and current executable evidence.

## Non-negotiable operating rule

Do **not** treat the current codebase as the definition of the desired system. It is implementation evidence. Do not restrict the audit to currently reported bugs. Independently determine the complete target state, discover gaps, contradictions, obsolete paths, missing safeguards, missing capabilities, architectural weaknesses, security flaws, lifecycle defects, data-integrity risks, performance problems, UX defects, maintainability problems, and operational weaknesses.

Do not make changes merely to create activity. Preserve correct behavior. Repair root causes at canonical authority boundaries. Eliminate competing business truth. Prefer coherent system-level improvement over mechanical merging or superficial cleanup.

Do not ask the user to provide a technical task decomposition that can be derived from the repository and its governing documents. The agent owns execution strategy, prioritization, sequencing, verification strategy, refactoring strategy, integration decisions, and cleanup decisions within project governance.

## Documentation hierarchy

Do not create competing project constitutions or duplicate existing canonical documents. This AI directive governs **how AI performs engineering work**. Existing architecture/domain/requirement/decision documents govern **what the system is supposed to mean**. When a material decision changes, record it through the existing governance mechanism.

## Completion rule

Never declare the repository complete from partial evidence. The exact candidate commit being declared complete must be verified at the appropriate depth, including architecture, authority, database/migrations, security/scope, backend/API, frontend/UX, audit/provenance, idempotency/concurrency, integrations/recovery, tests, static analysis, browser/runtime verification, and operational reproducibility as applicable.

A failure whose cause is unknown remains unresolved. `RUNTIME UNVERIFIED` is not `VERIFIED`.

**Read the directive. Understand the whole system. Decide independently. Change only what evidence justifies. Verify the exact resulting system.**
