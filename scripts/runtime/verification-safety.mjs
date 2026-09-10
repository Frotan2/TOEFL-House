/**
 * Shared safety controls for runtime verifiers that use temporary privileged
 * PostgreSQL fixture operations. These commands are intentionally unsuitable
 * for a normal application login or a live business database.
 */

const DISPOSABLE_DATABASE_NAME = /(^|[_-])(dev|test|ci|e2e|verify|verification)([_-]|$)/i;

/**
 * Refuse a target before opening a PostgreSQL connection. An operator may use
 * the explicit override only for a reviewed non-production rehearsal.
 *
 * @param {string} database
 * @param {string | undefined} allowMutatingDatabase
 */
export function assertDisposableVerificationTarget(
  database,
  allowMutatingDatabase = process.env.RUNTIME_VERIFICATION_ALLOW_MUTATING_DATABASE,
) {
  if (DISPOSABLE_DATABASE_NAME.test(database) || allowMutatingDatabase === '1') {
    return;
  }

  throw new Error(
    `refusing verification writes against DB_DATABASE=${database}; use a disposable migration-backed database or set RUNTIME_VERIFICATION_ALLOW_MUTATING_DATABASE=1 after an explicit operator review`,
  );
}

function describeError(error) {
  const detail = error && typeof error === 'object' ? error : {};

  return [
    detail.code ? `code=${detail.code}` : null,
    detail.constraint ? `constraint=${detail.constraint}` : null,
    detail.message ? `message=${detail.message}` : String(error),
  ].filter(Boolean).join('; ');
}

function publicTableName(table) {
  if (!/^[a-z_][a-z0-9_]*$/i.test(table)) {
    throw new Error(`invalid verifier table name ${JSON.stringify(table)}`);
  }

  return `public."${table}"`;
}

/**
 * Verify every elevated fixture capability before a verifier starts creating
 * rows. The preflight has no durable effect: all GUC changes and the optional
 * trigger-control checks are LOCAL to a transaction that is rolled back.
 *
 * `session_replication_role` is deliberately tested rather than inferred from
 * role metadata. PostgreSQL permits either a superuser or a role with an
 * explicit SET grant on that parameter, and managed services can differ in
 * how those privileges are provisioned.
 *
 * @param {() => Promise<import('pg').Client>} openConnection
 * @param {string} verifierName
 * @param {{
 *   requiredTablePrivileges?: Array<{table: string, privileges: string[]}>,
 *   userTriggerControlTables?: string[],
 * }} options
 */
export async function assertPrivilegedFixtureAccess(openConnection, verifierName, options = {}) {
  const requiredTablePrivileges = options.requiredTablePrivileges ?? [];
  const userTriggerControlTables = options.userTriggerControlTables ?? [];
  let client;
  let role = 'unknown';
  let phase = 'connect to PostgreSQL';
  let transactionOpen = false;

  try {
    client = await openConnection();

    phase = 'identify the connected PostgreSQL role';
    const { rows: identities } = await client.query(
      'SELECT current_user::text AS current_user, session_user::text AS session_user',
    );
    role = identities[0]?.current_user ?? role;

    phase = 'begin the rollback-bound privilege preflight';
    await client.query('BEGIN');
    transactionOpen = true;
    await client.query("SET LOCAL lock_timeout = '5s'");
    await client.query("SET LOCAL statement_timeout = '10s'");
    await client.query("SET LOCAL idle_in_transaction_session_timeout = '15s'");

    phase = "SET LOCAL session_replication_role = 'replica'";
    await client.query("SET LOCAL session_replication_role = 'replica'");

    phase = 'read back session_replication_role';
    const { rows: replicationRoles } = await client.query('SHOW session_replication_role');
    if (replicationRoles[0]?.session_replication_role !== 'replica') {
      throw new Error(`expected replica but observed ${JSON.stringify(replicationRoles[0]?.session_replication_role ?? null)}`);
    }

    phase = "restore session_replication_role to 'origin'";
    await client.query("SET LOCAL session_replication_role = 'origin'");

    for (const { table, privileges } of requiredTablePrivileges) {
      const relation = publicTableName(table);
      for (const privilege of privileges) {
        phase = `check ${privilege} privilege on ${relation}`;
        const { rows } = await client.query(
          `
            SELECT
              to_regclass($1)::text AS relation,
              COALESCE(has_table_privilege(current_user, to_regclass($1), $2), false) AS allowed
          `,
          [relation, privilege],
        );
        if (rows[0]?.relation === null) {
          throw new Error(`required fixture table ${relation} does not exist`);
        }
        if (rows[0]?.allowed !== true) {
          throw new Error(`missing ${privilege} privilege on ${relation}`);
        }
      }
    }

    for (const table of userTriggerControlTables) {
      const relation = publicTableName(table);
      phase = `disable USER triggers on ${relation}`;
      // ALTER TABLE is transactional in PostgreSQL. This tests the exact
      // ownership-level capability used by the invariant probes; ROLLBACK
      // below restores the trigger state before any fixture exists.
      await client.query(`ALTER TABLE ${relation} DISABLE TRIGGER USER`);
    }

    phase = 'roll back the privilege preflight';
    await client.query('ROLLBACK');
    transactionOpen = false;

    const triggerControl = userTriggerControlTables.length === 0
      ? ''
      : ` and temporarily control USER triggers on ${userTriggerControlTables.join(', ')}`;
    console.log(
      `${verifierName} privilege preflight passed: PostgreSQL role ${JSON.stringify(role)} can SET LOCAL session_replication_role for rollback-bound fixture work${triggerControl}.`,
    );
  } catch (error) {
    if (transactionOpen && client !== undefined) {
      await client.query('ROLLBACK').catch(() => {});
      transactionOpen = false;
    }

    const triggerRequirement = userTriggerControlTables.length === 0
      ? ''
      : ` and be allowed to ALTER TABLE ... DISABLE TRIGGER USER on ${userTriggerControlTables.join(', ')}`;
    throw new Error(
      `${verifierName} cannot run safely: bounded privileged-fixture preflight failed while trying to ${phase} as PostgreSQL role ${JSON.stringify(role)}; observed ${describeError(error)}. Use a dedicated verifier on a disposable database that can SET LOCAL session_replication_role = 'replica' (a superuser or a role granted SET ON PARAMETER session_replication_role)${triggerRequirement}. Do not grant this capability to the normal application role or use it against a live business database.`,
    );
  } finally {
    if (client !== undefined) {
      await client.end().catch(() => {});
    }
  }
}
