/**
 * Concurrency verification against the migrated production schema.
 *
 * Every race below uses independent PostgreSQL connections, opens all
 * transactions before any contender writes, and races real application tables
 * and their migrated guards/indexes. It intentionally creates no `conc_*`
 * mirrors or substitute trigger functions. Each short-lived fixture is removed
 * after its assertion; cleanup bypasses an append-only trigger only after the
 * target race has completed, never while that race is running.
 */
import { randomUUID } from 'node:crypto';
import pg from 'pg';
import {
  assertDisposableVerificationTarget,
  assertPrivilegedFixtureAccess,
} from './verification-safety.mjs';

const CFG = {
  host: process.env.DB_HOST ?? '127.0.0.1',
  port: Number(process.env.DB_PORT ?? 5432),
  user: process.env.DB_USERNAME ?? 'postgres',
  password: process.env.DB_PASSWORD || undefined,
  database: process.env.DB_DATABASE ?? 'toefl_house_dev',
};

const results = [];

const record = (name, pass, detail) => {
  results.push({ name, pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

const conn = async () => {
  const client = new pg.Client(CFG);
  await client.connect();
  // Do not let a verifier role's personal schema shadow the application's
  // canonical public tables: every race must exercise the migrated schema.
  await client.query('SET search_path TO public');
  // A broken race must fail loudly instead of leaving an operator with blocked
  // verification connections. The values are deliberately much larger than a
  // healthy local contention window.
  await client.query("SET lock_timeout = '5s'");
  await client.query("SET statement_timeout = '10s'");

  return client;
};

function describeError(error) {
  const detail = error && typeof error === 'object' ? error : {};

  return [
    detail.code ? `code=${detail.code}` : null,
    detail.constraint ? `constraint=${detail.constraint}` : null,
    detail.message ? `message=${detail.message}` : String(error),
  ].filter(Boolean).join('; ');
}

async function oneConnection(work) {
  const client = await conn();
  try {
    return await work(client);
  } finally {
    await client.end().catch(() => {});
  }
}

/**
 * Starts every contender before allowing any write. The preliminary sleep is a
 * simple barrier: all sessions are already in an open transaction and execute
 * it concurrently, then issue their competing write together. Backend PIDs
 * are returned and reported so a sequential single-connection simulation is
 * impossible to mistake for a race.
 *
 * @param {Array<(client: import('pg').Client) => Promise<void>>} writes
 * @param {(client: import('pg').Client) => Promise<void>} [readBeforeWrite]
 */
async function raceTransactions(writes, readBeforeWrite = async () => {}) {
  const clients = await Promise.all(writes.map(() => conn()));
  try {
    await Promise.all(clients.map((client) => client.query('BEGIN')));
    const pids = await Promise.all(clients.map(async (client) => {
      const { rows } = await client.query('SELECT pg_backend_pid()::text AS pid');

      return rows[0].pid;
    }));
    if (new Set(pids).size !== clients.length) {
      throw new Error('race did not acquire distinct PostgreSQL backends');
    }

    await Promise.all(clients.map((client) => readBeforeWrite(client)));
    await Promise.all(clients.map((client) => client.query('SELECT pg_sleep(0.05)')));

    const outcomes = await Promise.all(clients.map(async (client, index) => {
      try {
        await writes[index](client);
        await client.query('COMMIT');

        return { state: 'committed' };
      } catch (error) {
        await client.query('ROLLBACK').catch(() => {});

        return { state: 'rejected', error };
      }
    }));

    return { outcomes, pids };
  } finally {
    await Promise.allSettled(clients.map(async (client) => {
      await client.query('ROLLBACK').catch(() => {});
      await client.end();
    }));
  }
}

function assertOneWinner(name, race, expectedConstraint) {
  const committed = race.outcomes.filter((outcome) => outcome.state === 'committed');
  const rejected = race.outcomes.filter((outcome) => outcome.state === 'rejected');
  const matchingRejects = rejected.filter((outcome) => {
    const error = outcome.error && typeof outcome.error === 'object' ? outcome.error : {};

    return error.code === '23505' && error.constraint === expectedConstraint;
  });

  if (committed.length !== 1 || rejected.length !== 1 || matchingRejects.length !== 1) {
    throw new Error(
      `${name}: expected exactly one commit and one 23505/${expectedConstraint} rejection; observed ${race.outcomes.map((outcome) => outcome.state === 'committed' ? 'committed' : describeError(outcome.error)).join(' | ')}`,
    );
  }

  return `backends=${race.pids.join(', ')}; one committed, one rejected by ${expectedConstraint}`;
}

/**
 * Same one-winner shape, but the loser is stopped by a named trigger message
 * rather than a unique constraint: staged-chain and lifecycle guards reject a
 * stale writer with `check_violation` plus the boundary's own sentence.
 */
function assertOneWinnerByMessage(name, race, messageIncludes, boundary) {
  const committed = race.outcomes.filter((outcome) => outcome.state === 'committed');
  const rejected = race.outcomes.filter((outcome) => outcome.state === 'rejected');
  const loser = rejected.find((outcome) => {
    const error = outcome.error && typeof outcome.error === 'object' ? outcome.error : {};

    return String(error.message ?? '').includes(messageIncludes);
  });

  if (committed.length !== 1 || rejected.length !== 1 || loser === undefined) {
    throw new Error(
      `${name}: expected exactly one commit and one rejection containing ${JSON.stringify(messageIncludes)}; observed ${race.outcomes.map((outcome) => outcome.state === 'committed' ? 'committed' : describeError(outcome.error)).join(' | ')}`,
    );
  }

  return `backends=${race.pids.join(', ')}; one committed, one rejected by ${boundary}`;
}

async function assertNoRows(label, sql, params) {
  const count = await oneConnection(async (client) => {
    const { rows } = await client.query(sql, params);

    return Number(rows[0].count);
  });
  if (count !== 0) {
    throw new Error(`${label}: fixture cleanup left ${count} row(s)`);
  }
}

async function deleteAppendOnlyRows(sql, params) {
  await oneConnection(async (client) => {
    await client.query('BEGIN');
    try {
      // PostgreSQL's replica role bypasses append-only USER triggers for this
      // cleanup statement only. It is entered only after the target production
      // constraint/trigger race has been observed and named.
      await client.query("SET LOCAL session_replication_role = 'replica'");
      await client.query(sql, params);
      await client.query('COMMIT');
    } catch (error) {
      await client.query('ROLLBACK').catch(() => {});
      throw error;
    }
  });
}

async function idempotencyKeyRace() {
  const token = randomUUID();
  const operation = `runtime.concurrency.${token}`;
  const key = `runtime-key-${token}`;

  try {
    const race = await raceTransactions([0, 1].map((contender) => async (client) => {
      await client.query(
        `
          INSERT INTO idempotency_keys (id, operation, idempotency_key, payload_hash, outcome)
          VALUES ($1, $2, $3, $4, $5)
        `,
        [randomUUID(), operation, key, `payload-${contender}`, `outcome-${contender}`],
      );
    }));

    return assertOneWinner('idempotency replay identity', race, 'idempotency_keys_operation_idempotency_key_unique');
  } finally {
    await oneConnection((client) => client.query(
      'DELETE FROM idempotency_keys WHERE operation = $1 AND idempotency_key = $2',
      [operation, key],
    ));
    await assertNoRows('idempotency replay identity', 'SELECT count(*)::int AS count FROM idempotency_keys WHERE operation = $1 AND idempotency_key = $2', [operation, key]);
  }
}

async function scopeGrantRace() {
  const personId = randomUUID();
  const scopeId = randomUUID();
  const permission = `runtime.scope.${randomUUID()}`;

  await oneConnection((client) => client.query(
    "INSERT INTO people (id, legal_name, date_of_birth, verification_state) VALUES ($1, 'Runtime scope race person', '1980-01-01', 'unverified')",
    [personId],
  ));

  try {
    const race = await raceTransactions([0, 1].map(() => async (client) => {
      await client.query(
        `
          INSERT INTO scope_grants (
            id, person_id, permission, scope_type, scope_id, lifecycle_state,
            effective_from, effective_to, is_emergency, review_required, granted_by
          ) VALUES ($1, $2, $3, 'organization', $4, 'active', CURRENT_DATE, NULL, false, false, $5)
        `,
        [randomUUID(), personId, permission, scopeId, randomUUID()],
      );
    }));

    return assertOneWinner('single live scoped authority', race, 'scope_grants_one_open_grant');
  } finally {
    await oneConnection((client) => client.query('DELETE FROM scope_grants WHERE person_id = $1 AND permission = $2', [personId, permission]));
    await oneConnection((client) => client.query('DELETE FROM people WHERE id = $1', [personId]));
    await assertNoRows('scoped-authority grants', 'SELECT count(*)::int AS count FROM scope_grants WHERE person_id = $1 AND permission = $2', [personId, permission]);
    await assertNoRows('scoped-authority person', 'SELECT count(*)::int AS count FROM people WHERE id = $1', [personId]);
  }
}

async function stagedOrganizationGrantRace() {
  const personId = randomUUID();
  const requestId = randomUUID();

  await oneConnection(async (client) => {
    await client.query(
      "INSERT INTO people (id, legal_name, date_of_birth, verification_state) VALUES ($1, 'Runtime grant race person', '1980-01-01', 'unverified')",
      [personId],
    );
    await client.query(
      `
        INSERT INTO org_wide_grant_requests (
          id, person_id, permission, organization_id, is_emergency,
          effective_from, lifecycle_state, requested_by, created_at, updated_at
        ) VALUES ($1, $2, 'runtime.org.grant', $3, false, CURRENT_DATE, 'requested', $2, NOW(), NOW())
      `,
      [requestId, personId, randomUUID()],
    );
  });

  try {
    const approvers = [
      [randomUUID(), randomUUID()],
      [randomUUID(), randomUUID()],
    ];
    const race = await raceTransactions(
      approvers.map(([first, second]) => async (client) => {
        await client.query(
          `
            UPDATE org_wide_grant_requests
            SET lifecycle_state = 'approved', approver_one_id = $1, approver_two_id = $2, updated_at = NOW()
            WHERE id = $3
          `,
          [first, second, requestId],
        );
      }),
      async (client) => {
        const { rows } = await client.query('SELECT lifecycle_state FROM org_wide_grant_requests WHERE id = $1', [requestId]);
        if (rows.length !== 1 || rows[0].lifecycle_state !== 'requested') {
          throw new Error('a contender did not observe the requested state before the contested approval');
        }
      },
    );
    const committed = race.outcomes.filter((outcome) => outcome.state === 'committed');
    const rejected = race.outcomes.filter((outcome) => outcome.state === 'rejected');
    const staleWriter = rejected.find((outcome) => {
      const error = outcome.error && typeof outcome.error === 'object' ? outcome.error : {};

      return error.code === '23514'
        && String(error.message ?? '').includes('moves only requested -> approved -> granted');
    });
    if (committed.length !== 1 || rejected.length !== 1 || staleWriter === undefined) {
      throw new Error(
        `staged organization-wide grant approval: expected one approval and one rejected stale writer; observed ${race.outcomes.map((outcome) => outcome.state === 'committed' ? 'committed' : describeError(outcome.error)).join(' | ')}`,
      );
    }

    return `backends=${race.pids.join(', ')}; one requested→approved transition committed, stale writer rejected by org_wide_grant_requests_guard_trigger`;
  } finally {
    await deleteAppendOnlyRows('DELETE FROM org_wide_grant_requests WHERE id = $1', [requestId]);
    await oneConnection((client) => client.query('DELETE FROM people WHERE id = $1', [personId]));
    await assertNoRows('organization-wide grant request', 'SELECT count(*)::int AS count FROM org_wide_grant_requests WHERE id = $1', [requestId]);
    await assertNoRows('organization-wide grant person', 'SELECT count(*)::int AS count FROM people WHERE id = $1', [personId]);
  }
}

async function chartAccountCodeRace() {
  const code = `RUNTIME-${randomUUID()}`;

  try {
    const race = await raceTransactions([0, 1].map((contender) => async (client) => {
      await client.query(
        "INSERT INTO accounts (id, code, name, type) VALUES ($1, $2, $3, 'asset')",
        [randomUUID(), code, `Runtime concurrent account ${contender}`],
      );
    }));

    return assertOneWinner('chart-account code uniqueness', race, 'accounts_code_unique');
  } finally {
    await deleteAppendOnlyRows('DELETE FROM accounts WHERE code = $1', [code]);
    await assertNoRows('chart-account race', 'SELECT count(*)::int AS count FROM accounts WHERE code = $1', [code]);
  }
}

async function createLibraryRaceTopology() {
  const organizationId = randomUUID();
  const campusId = randomUUID();
  const branchId = randomUUID();
  const assignmentId = randomUUID();

  await oneConnection(async (client) => {
    await client.query(
      "INSERT INTO organizations (id, name, lifecycle_state) VALUES ($1, 'Runtime library race organization', 'active')",
      [organizationId],
    );
    await client.query(
      "INSERT INTO campuses (id, organization_id, name, lifecycle_state) VALUES ($1, $2, 'Runtime library race campus', 'active')",
      [campusId, organizationId],
    );
    await client.query(
      "INSERT INTO branches (id, name, lifecycle_state) VALUES ($1, 'Runtime library race branch', 'active')",
      [branchId],
    );
    await client.query(
      'INSERT INTO campus_assignments (id, branch_id, campus_id, effective_from, transfer_correlation_id) VALUES ($1, $2, $3, CURRENT_DATE, $4)',
      [assignmentId, branchId, campusId, randomUUID()],
    );
  });

  return { organizationId, campusId, branchId, assignmentId };
}

async function cleanupLibraryRaceTopology(topology) {
  await oneConnection(async (client) => {
    await client.query('DELETE FROM campus_assignments WHERE id = $1', [topology.assignmentId]);
    await client.query('DELETE FROM branches WHERE id = $1', [topology.branchId]);
    await client.query('DELETE FROM campuses WHERE id = $1', [topology.campusId]);
    await client.query('DELETE FROM organizations WHERE id = $1', [topology.organizationId]);
  });
  await assertNoRows('library race branch', 'SELECT count(*)::int AS count FROM branches WHERE id = $1', [topology.branchId]);
}

async function insertLibraryRacePerson(client, personId, label, homeBranchId) {
  await client.query(
    'INSERT INTO people (id, legal_name, date_of_birth, verification_state, home_branch_id) VALUES ($1, $2, \'1980-01-01\', \'unverified\', $3)',
    [personId, label, homeBranchId],
  );
}

async function openBookIssuanceRace() {
  const topology = await createLibraryRaceTopology();
  const copyId = randomUUID();
  const copyCode = `RUNTIME-COPY-${randomUUID()}`;
  const borrowers = [randomUUID(), randomUUID()];

  await oneConnection(async (client) => {
    await insertLibraryRacePerson(client, borrowers[0], 'Runtime race borrower one', topology.branchId);
    await insertLibraryRacePerson(client, borrowers[1], 'Runtime race borrower two', topology.branchId);
    await client.query(
      'INSERT INTO book_copies (id, code, title, acquired_on, organization_id, originating_branch_id) VALUES ($1, $2, \'Runtime race volume\', CURRENT_DATE, $3, $4)',
      [copyId, copyCode, topology.organizationId, topology.branchId],
    );
  });

  try {
    const race = await raceTransactions(borrowers.map((personId) => async (client) => {
      await client.query(
        `
          INSERT INTO book_issuances (id, copy_id, borrower_person_id, issued_on, due_on, lifecycle_state, issued_by)
          VALUES ($1, $2, $3, CURRENT_DATE, CURRENT_DATE + INTERVAL '30 days', 'issued', $4)
        `,
        [randomUUID(), copyId, personId, randomUUID()],
      );
    }));

    return assertOneWinner('one open issuance per library copy', race, 'book_issuances_one_open_per_copy');
  } finally {
    await deleteAppendOnlyRows('DELETE FROM book_issuances WHERE copy_id = $1', [copyId]);
    await deleteAppendOnlyRows('DELETE FROM book_copies WHERE id = $1', [copyId]);
    await oneConnection(async (client) => {
      await client.query('DELETE FROM people WHERE id = $1', [borrowers[0]]);
      await client.query('DELETE FROM people WHERE id = $1', [borrowers[1]]);
    });
    await cleanupLibraryRaceTopology(topology);
    await assertNoRows('library issuance race', 'SELECT count(*)::int AS count FROM book_issuances WHERE copy_id = $1', [copyId]);
    await assertNoRows('library copy race', 'SELECT count(*)::int AS count FROM book_copies WHERE id = $1', [copyId]);
  }
}

async function openAssetCustodyRace() {
  const topology = await createLibraryRaceTopology();
  const assetId = randomUUID();
  const assetCode = `RUNTIME-ASSET-${randomUUID()}`;
  const custodians = [randomUUID(), randomUUID()];

  await oneConnection(async (client) => {
    await insertLibraryRacePerson(client, custodians[0], 'Runtime race custodian one', topology.branchId);
    await insertLibraryRacePerson(client, custodians[1], 'Runtime race custodian two', topology.branchId);
    await client.query(
      `
        INSERT INTO assets (id, code, name, category, location, acquired_on, lifecycle_state, organization_id, originating_branch_id)
        VALUES ($1, $2, 'Runtime race asset', 'equipment', 'Room 1', CURRENT_DATE, 'in_service', $3, $4)
      `,
      [assetId, assetCode, topology.organizationId, topology.branchId],
    );
  });

  try {
    const race = await raceTransactions(custodians.map((personId) => async (client) => {
      await client.query(
        `
          INSERT INTO custodies (id, asset_id, custodian_person_id, assigned_on, assigned_by)
          VALUES ($1, $2, $3, CURRENT_DATE, $4)
        `,
        [randomUUID(), assetId, personId, randomUUID()],
      );
    }));

    return assertOneWinner('one open custody per asset', race, 'custodies_one_open_per_asset');
  } finally {
    await deleteAppendOnlyRows('DELETE FROM custodies WHERE asset_id = $1', [assetId]);
    await deleteAppendOnlyRows('DELETE FROM assets WHERE id = $1', [assetId]);
    await oneConnection(async (client) => {
      await client.query('DELETE FROM people WHERE id = $1', [custodians[0]]);
      await client.query('DELETE FROM people WHERE id = $1', [custodians[1]]);
    });
    await cleanupLibraryRaceTopology(topology);
    await assertNoRows('custody race', 'SELECT count(*)::int AS count FROM custodies WHERE asset_id = $1', [assetId]);
    await assertNoRows('asset race', 'SELECT count(*)::int AS count FROM assets WHERE id = $1', [assetId]);
  }
}

async function stagedDisposalApprovalRace() {
  const topology = await createLibraryRaceTopology();
  const assetId = randomUUID();
  const assetCode = `RUNTIME-DISPOSAL-${randomUUID()}`;
  const requesterId = randomUUID();
  const requestId = randomUUID();

  await oneConnection(async (client) => {
    await client.query(
      `
        INSERT INTO assets (id, code, name, category, location, acquired_on, lifecycle_state, organization_id, originating_branch_id)
        VALUES ($1, $2, 'Runtime race disposal asset', 'equipment', 'Room 2', CURRENT_DATE, 'in_service', $3, $4)
      `,
      [assetId, assetCode, topology.organizationId, topology.branchId],
    );
    await client.query(
      `
        INSERT INTO asset_disposal_requests (id, asset_id, method, reason, lifecycle_state, requested_by, created_at, updated_at)
        VALUES ($1, $2, 'scrap', 'runtime race disposal request', 'requested', $3, NOW(), NOW())
      `,
      [requestId, assetId, requesterId],
    );
  });

  try {
    const contenders = [
      [randomUUID(), randomUUID()],
      [randomUUID(), randomUUID()],
    ];
    const race = await raceTransactions(
      contenders.map(([first, second]) => async (client) => {
        await client.query(
          `
            UPDATE asset_disposal_requests
            SET lifecycle_state = 'approved', approver_one_id = $1, approver_two_id = $2, updated_at = NOW()
            WHERE id = $3
          `,
          [first, second, requestId],
        );
      }),
      async (client) => {
        const { rows } = await client.query('SELECT lifecycle_state FROM asset_disposal_requests WHERE id = $1', [requestId]);
        if (rows.length !== 1 || rows[0].lifecycle_state !== 'requested') {
          throw new Error('a contender did not observe the requested state before the contested approval');
        }
      },
    );
    const committed = race.outcomes.filter((outcome) => outcome.state === 'committed');
    const rejected = race.outcomes.filter((outcome) => outcome.state === 'rejected');
    // The stale writer re-evaluates against the committed row and is stopped
    // by the write-once approver-slot guard, which fires before the
    // transition guard on the second trigger.
    const staleWriter = rejected.find((outcome) => {
      const error = outcome.error && typeof outcome.error === 'object' ? outcome.error : {};

      return String(error.message ?? '').includes('first disposal approver is immutable');
    });
    if (committed.length !== 1 || rejected.length !== 1 || staleWriter === undefined) {
      throw new Error(
        `staged asset disposal approval: expected one approval and one rejected stale writer; observed ${race.outcomes.map((outcome) => outcome.state === 'committed' ? 'committed' : describeError(outcome.error)).join(' | ')}`,
      );
    }

    return `backends=${race.pids.join(', ')}; one requested→approved transition committed, stale writer rejected by the write-once approver-slot guard`;
  } finally {
    await deleteAppendOnlyRows('DELETE FROM asset_disposal_requests WHERE id = $1', [requestId]);
    await deleteAppendOnlyRows('DELETE FROM assets WHERE id = $1', [assetId]);
    await cleanupLibraryRaceTopology(topology);
    await assertNoRows('staged disposal race request', 'SELECT count(*)::int AS count FROM asset_disposal_requests WHERE id = $1', [requestId]);
    await assertNoRows('staged disposal race asset', 'SELECT count(*)::int AS count FROM assets WHERE id = $1', [assetId]);
  }
}

/**
 * Documents race fixture: one subject person, one classification and one
 * submitted document, created before the race and removed after it.
 */
async function createDocumentsRaceFixture() {
  const personId = randomUUID();
  const classificationId = randomUUID();
  const documentId = randomUUID();
  await oneConnection(async (client) => {
    await client.query(
      "INSERT INTO people (id, legal_name, date_of_birth, verification_state) VALUES ($1, 'Runtime documents race person', '1980-01-01', 'unverified')",
      [personId],
    );
    await client.query(
      "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, $2, 'documents-runtime', 'restricted')",
      [classificationId, `runtime-race-${classificationId}`],
    );
    await client.query(
      "INSERT INTO documents (id, subject_person_id, classification_id, title, lifecycle_state) VALUES ($1, $2, $3, 'Runtime documents race document', 'submitted')",
      [documentId, personId, classificationId],
    );
  });

  return { personId, classificationId, documentId };
}

async function cleanupDocumentsRaceFixture(fixture) {
  await oneConnection(async (client) => {
    await client.query('DELETE FROM documents WHERE id = $1', [fixture.documentId]);
    await client.query('DELETE FROM document_classifications WHERE id = $1', [fixture.classificationId]);
    await client.query('DELETE FROM people WHERE id = $1', [fixture.personId]);
  });
  await assertNoRows('documents race document', 'SELECT count(*)::int AS count FROM documents WHERE id = $1', [fixture.documentId]);
  await assertNoRows('documents race person', 'SELECT count(*)::int AS count FROM people WHERE id = $1', [fixture.personId]);
}

async function documentVersionAppendRace() {
  const fixture = await createDocumentsRaceFixture();
  await oneConnection((client) => client.query(
    "INSERT INTO document_versions (id, document_id, version_no, content_hash, storage_ref, uploaded_by) VALUES ($1, $2, 1, 'runtime-race-v1', 'storage/runtime-race-v1', $3)",
    [randomUUID(), fixture.documentId, fixture.personId],
  ));

  try {
    // Two concurrent submissions both compute max(version_no)+1 = 2. The
    // command row-lock serializes application writers; this race proves the
    // migrated uniqueness boundary independently defeats the duplicate even
    // for writers that bypass the command entirely.
    const race = await raceTransactions([0, 1].map((contender) => async (client) => {
      await client.query(
        "INSERT INTO document_versions (id, document_id, version_no, content_hash, storage_ref, uploaded_by) VALUES ($1, $2, 2, $3, 'storage/runtime-race-v2', $4)",
        [randomUUID(), fixture.documentId, `runtime-race-v2-contender-${contender}`, fixture.personId],
      );
    }));

    return assertOneWinner('concurrent document version append', race, 'document_versions_document_id_version_no_unique');
  } finally {
    await deleteAppendOnlyRows('DELETE FROM document_versions WHERE document_id = $1', [fixture.documentId]);
    await cleanupDocumentsRaceFixture(fixture);
    await assertNoRows('documents race versions', 'SELECT count(*)::int AS count FROM document_versions WHERE document_id = $1', [fixture.documentId]);
  }
}

async function documentVerdictRace() {
  const fixture = await createDocumentsRaceFixture();

  try {
    // Two concurrent verifiers race to record THE verdict for version 1.
    // The command serializes on the document row lock and the loser sees the
    // moved state; this race proves the named unique index independently
    // guarantees one verdict per version against any writer.
    const race = await raceTransactions([0, 1].map((contender) => async (client) => {
      await client.query(
        "INSERT INTO document_verifications (id, document_id, version_no, verifier_person_id, result, reason) VALUES ($1, $2, 1, $3, $4, $5)",
        [randomUUID(), fixture.documentId, fixture.personId, contender === 0 ? 'pass' : 'fail', `runtime race verdict ${contender}`],
      );
    }));

    return assertOneWinner('single verdict per document version', race, 'document_verifications_one_verdict_per_version');
  } finally {
    await deleteAppendOnlyRows('DELETE FROM document_verifications WHERE document_id = $1', [fixture.documentId]);
    await cleanupDocumentsRaceFixture(fixture);
    await assertNoRows('documents race verdicts', 'SELECT count(*)::int AS count FROM document_verifications WHERE document_id = $1', [fixture.documentId]);
  }
}

/**
 * Privacy race fixture: one subject person and one defined purpose, created
 * before the race and removed after it. Consents are inserted *through* the
 * production guard (born draft with evidence), never around it.
 */
async function createPrivacyRaceFixture() {
  const personId = randomUUID();
  const purposeId = randomUUID();

  await oneConnection(async (client) => {
    await client.query(
      "INSERT INTO people (id, legal_name, date_of_birth, verification_state) VALUES ($1, 'Runtime privacy race person', '1980-01-01', 'unverified')",
      [personId],
    );
    await client.query(
      "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
      [purposeId, `runtime-race-${purposeId}`],
    );
  });

  return { personId, purposeId };
}

async function insertConsent(client, fixture, consentId, state = 'draft') {
  await client.query(
    `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'draft', CURRENT_DATE, NULL, 'evidence/runtime-race', $4, NOW(), NOW())
    `,
    [consentId, fixture.personId, fixture.purposeId, fixture.personId],
  );
  if (state !== 'draft') {
    await client.query("UPDATE consents SET lifecycle_state = 'submitted', updated_at = NOW() WHERE id = $1", [consentId]);
  }
}

async function cleanupPrivacyRaceFixture(fixture, label) {
  // The consent guard refuses DELETE by design; cleanup enters replica role
  // only after the contested race has been observed and named.
  await deleteAppendOnlyRows('DELETE FROM consents WHERE subject_person_id = $1', [fixture.personId]);
  await oneConnection(async (client) => {
    await client.query('DELETE FROM consent_purposes WHERE id = $1', [fixture.purposeId]);
    await client.query('DELETE FROM people WHERE id = $1', [fixture.personId]);
  });
  await assertNoRows(`${label} consents`, 'SELECT count(*)::int AS count FROM consents WHERE subject_person_id = $1', [fixture.personId]);
  await assertNoRows(`${label} person`, 'SELECT count(*)::int AS count FROM people WHERE id = $1', [fixture.personId]);
}

async function oneOpenConsentRace() {
  const fixture = await createPrivacyRaceFixture();

  try {
    // Two officers record consent for the same subject and purpose at the same
    // instant. The command serializes on its own transaction; this race proves
    // the partial unique index independently guarantees one open consent per
    // subject+purpose against any writer, so a duplicate can never create two
    // competing use authorities.
    const race = await raceTransactions([0, 1].map((contender) => async (client) => {
      await client.query(
        `
          INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
          VALUES ($1, $2, $3, 'draft', CURRENT_DATE, NULL, $4, $5, NOW(), NOW())
        `,
        [randomUUID(), fixture.personId, fixture.purposeId, `evidence/runtime-race-${contender}`, fixture.personId],
      );
    }));

    return assertOneWinner('one open consent per subject and purpose', race, 'consents_one_open_per_subject_purpose');
  } finally {
    await cleanupPrivacyRaceFixture(fixture, 'open-consent race');
  }
}

async function consentTransitionRace() {
  const fixture = await createPrivacyRaceFixture();
  const consentId = randomUUID();
  await oneConnection((client) => insertConsent(client, fixture, consentId, 'submitted'));

  try {
    // Two verifiers race the same submitted consent. Exactly one may move it
    // to verified; the stale writer re-reads the committed row and is stopped
    // by the write-once lifecycle guard rather than double-recording the act.
    const race = await raceTransactions(
      [0, 1].map(() => async (client) => {
        await client.query("UPDATE consents SET lifecycle_state = 'verified', updated_at = NOW() WHERE id = $1", [consentId]);
      }),
      async (client) => {
        const { rows } = await client.query('SELECT lifecycle_state FROM consents WHERE id = $1', [consentId]);
        if (rows.length !== 1 || rows[0].lifecycle_state !== 'submitted') {
          throw new Error('a contender did not observe the submitted state before the contested verification');
        }
      },
    );

    return assertOneWinnerByMessage(
      'concurrent consent verification',
      race,
      'a consent update must move its lifecycle state',
      'consents_guard_trigger',
    );
  } finally {
    await cleanupPrivacyRaceFixture(fixture, 'consent-transition race');
  }
}

async function createExportRequestFixture() {
  const fixture = await createPrivacyRaceFixture();
  const requestId = randomUUID();
  const organizationId = randomUUID();

  await oneConnection((client) => client.query(
    `
      INSERT INTO privacy_export_requests (id, subject_person_id, purpose, organization_id, lifecycle_state, requested_by, created_at, updated_at)
      VALUES ($1, $2, 'runtime race bulk export', $3, 'requested', $4, NOW(), NOW())
    `,
    [requestId, fixture.personId, organizationId, fixture.personId],
  ));

  return { ...fixture, requestId, organizationId };
}

async function cleanupExportRequestFixture(fixture, label) {
  await deleteAppendOnlyRows('DELETE FROM privacy_export_requests WHERE id = $1', [fixture.requestId]);
  await cleanupPrivacyRaceFixture(fixture, label);
  await assertNoRows(`${label} request`, 'SELECT count(*)::int AS count FROM privacy_export_requests WHERE id = $1', [fixture.requestId]);
}

async function firstExportSignatureRace() {
  const fixture = await createExportRequestFixture();

  try {
    // Two approvers sign the FIRST slot simultaneously. The slot is written
    // once: one signature counts, the other is rejected instead of silently
    // overwriting who approved an organization-wide personal-data release.
    const race = await raceTransactions(
      [0, 1].map(() => async (client) => {
        await client.query('UPDATE privacy_export_requests SET approver_one_id = $1, updated_at = NOW() WHERE id = $2', [randomUUID(), fixture.requestId]);
      }),
      async (client) => {
        const { rows } = await client.query('SELECT lifecycle_state, approver_one_id FROM privacy_export_requests WHERE id = $1', [fixture.requestId]);
        if (rows.length !== 1 || rows[0].lifecycle_state !== 'requested' || rows[0].approver_one_id !== null) {
          throw new Error('a contender did not observe an unsigned requested export');
        }
      },
    );

    return assertOneWinnerByMessage(
      'first bulk-export signature',
      race,
      'accepts only its first approver signature',
      'privacy_export_requests_guard_trigger',
    );
  } finally {
    await cleanupExportRequestFixture(fixture, 'first-signature race');
  }
}

async function stagedExportApprovalRace() {
  const fixture = await createExportRequestFixture();
  await oneConnection((client) => client.query(
    'UPDATE privacy_export_requests SET approver_one_id = $1, updated_at = NOW() WHERE id = $2',
    [randomUUID(), fixture.requestId],
  ));

  try {
    // The second signature closes the chain as approved. Two concurrent
    // closers race; one commits and the stale writer is rejected by the chain
    // guard, so an organization-wide export can never be approved twice or by
    // a writer that did not observe the requested state.
    const race = await raceTransactions(
      [0, 1].map(() => async (client) => {
        await client.query(
          "UPDATE privacy_export_requests SET approver_two_id = $1, lifecycle_state = 'approved', updated_at = NOW() WHERE id = $2",
          [randomUUID(), fixture.requestId],
        );
      }),
      async (client) => {
        const { rows } = await client.query('SELECT lifecycle_state, approver_one_id, approver_two_id FROM privacy_export_requests WHERE id = $1', [fixture.requestId]);
        if (rows.length !== 1 || rows[0].lifecycle_state !== 'requested' || rows[0].approver_one_id === null || rows[0].approver_two_id !== null) {
          throw new Error('a contender did not observe a singly-signed requested export');
        }
      },
    );

    return assertOneWinnerByMessage(
      'staged bulk-export approval',
      race,
      'moves only requested -> approved -> exported',
      'privacy_export_requests_guard_trigger',
    );
  } finally {
    await cleanupExportRequestFixture(fixture, 'staged-export race');
  }
}

async function verify(name, operation) {
  try {
    record(name, true, await operation());
  } catch (error) {
    record(name, false, error instanceof Error ? error.message : String(error));
  }
}

try {
  assertDisposableVerificationTarget(CFG.database);
  await assertPrivilegedFixtureAccess(conn, 'Concurrency verification', {
    requiredTablePrivileges: [
      { table: 'idempotency_keys', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'people', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'scope_grants', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'org_wide_grant_requests', privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'] },
      { table: 'accounts', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'organizations', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'campuses', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'branches', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'campus_assignments', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'book_copies', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'book_issuances', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'assets', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'custodies', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'asset_disposal_requests', privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'] },
      { table: 'document_classifications', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'documents', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'document_versions', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'document_verifications', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'consent_purposes', privileges: ['SELECT', 'INSERT', 'DELETE'] },
      { table: 'consents', privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'] },
      { table: 'privacy_export_requests', privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE'] },
    ],
  });

  await verify('idempotency replay identity race', idempotencyKeyRace);
  await verify('single active scope-grant race', scopeGrantRace);
  await verify('staged organization-wide approval race', stagedOrganizationGrantRace);
  await verify('chart-of-accounts code race', chartAccountCodeRace);
  await verify('one open library issuance race', openBookIssuanceRace);
  await verify('one open asset custody race', openAssetCustodyRace);
  await verify('staged asset disposal approval race', stagedDisposalApprovalRace);
  await verify('concurrent document version append race', documentVersionAppendRace);
  await verify('single verdict per document version race', documentVerdictRace);
  await verify('one open consent per subject and purpose race', oneOpenConsentRace);
  await verify('concurrent consent verification race', consentTransitionRace);
  await verify('first bulk-export signature race', firstExportSignatureRace);
  await verify('staged bulk-export approval race', stagedExportApprovalRace);

  const passed = results.filter((result) => result.pass).length;
  console.log(`Concurrency verification: ${passed}/${results.length} production-table races passed.`);
  if (passed !== results.length) {
    process.exitCode = 1;
  }
} catch (error) {
  console.error(`Concurrency verification failed: ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
