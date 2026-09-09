# ADR — First-Run Genesis Structure in the Bootstrap Seeder

**Date:** 2026-09-09  
**Status:** CURRENT  
**Decision:** The guarded first-run bootstrap provisions the genesis campus, branch, and open campus-attribution itself; every later structure change stays under the four-actor StructureDecision separation-of-duties chain.

## Context

Person intake (`Person.home_branch_id`), applicant registration, HR, academic
availability, offerings and classes all mandate a branch, and the branch is
the root of scope-aware authorization. Before this decision,
`FirstRunBootstrapSeeder` created the organization, role, position, and owner
account, but no campus or branch — and no HTTP surface creates one, because
structure change is governed by `StructureDecision`, which requires four
distinct actors (initiator, reviewer, two owners) that cannot exist before
the first staff are provisioned, which itself requires a branch. The final
2026-09-09 certification audit proved the dead end by running the E2E
student journey from a true first boot (finding FC-1).

## Decision

1. `FirstRunBootstrapSeeder` writes exactly one campus ("Main Campus"), one
   branch ("Central Branch"), and one open campus-attribution inside the same
   guarded bootstrap transaction that creates the owner account.
2. This is a **genesis exception** with the same standing as the owner's
   self-verified identity: it is the only place structure facts may be
   written outside `CreateStructureUnit` / `TransitionStructureUnit`, and the
   seeder's docblock names that guarantee.
3. The bootstrap remains once-only (no-op when any user account exists) and
   accepts no structure input from the environment — the genesis names are
   fixed, so a launcher cannot smuggle structure into an initialized system.
4. From the moment bootstrap completes, the normal governance applies
   unchanged: any further campus/branch/department change requires the
   four-actor StructureDecision chain, and no code path added here weakens it.

## Alternatives rejected

- **Weakening StructureDecision for single-actor first runs.** Rejected: it
  would relax a security boundary globally to solve a one-time genesis
  problem.
- **An artisan/console structure command for the operator.** Rejected for the
  genesis case: it would still need a second actor for SoD, or it would move
  the same exception outside the guarded, once-only, test-pinned path.
- **Letting intake infer a branch.** Rejected long ago (registration
  deliberately refuses to infer a branch); the audit confirmed the refusal is
  correct and the fixture was what was incomplete.

## Consequences

- A fresh installation is operable end-to-end immediately after bootstrap —
  proven by the three E2E journeys executed from a true first boot
  (`docs/AUDIT-2026-09-09-FINAL-CERTIFICATION.md` §7).
- Pinned by
  `WindowsOneClickDeploymentContractTest::test_first_run_bootstrap_provisions_the_genesis_structure`
  and the strengthened no-op assertions (re-run must not duplicate structure).
- Any future rename of the genesis units is a data-migration concern, not a
  seeder concern: live systems own those rows.
