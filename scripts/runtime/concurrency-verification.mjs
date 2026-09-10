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
    ],
  });

  await verify('idempotency replay identity race', idempotencyKeyRace);
  await verify('single active scope-grant race', scopeGrantRace);
  await verify('staged organization-wide approval race', stagedOrganizationGrantRace);
  await verify('chart-of-accounts code race', chartAccountCodeRace);

  const passed = results.filter((result) => result.pass).length;
  console.log(`Concurrency verification: ${passed}/${results.length} production-table races passed.`);
  if (passed !== results.length) {
    process.exitCode = 1;
  }
} catch (error) {
  console.error(`Concurrency verification failed: ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
