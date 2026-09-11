/**
 * PostgreSQL boundary verification.
 *
 * Each probe opens a transaction against the migrated application schema,
 * deliberately attempts one invalid write, asserts the exact PostgreSQL error
 * code and named constraint (or the one final-state trigger), then rolls the
 * whole transaction back. User triggers are temporarily disabled only where a
 * preceding workflow guard would mask the lower-level constraint under test;
 * PostgreSQL foreign-key triggers remain enabled. This script never creates
 * mirror tables or persists fixture data.
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

const EXPECTED_CONSTRAINTS = new Map([
  ['payments_amount_check', 'c'],
  ['enrollments_class_id_foreign', 'f'],
  ['classes_capacity_check', 'c'],
  ['journal_lines_journal_id_foreign', 'f'],
]);

// This production boundary is intentionally a unique *index* rather than an
// ALTER TABLE unique constraint. PostgreSQL reports its name in `error.constraint`
// all the same, so catalogue and assert it explicitly instead of pretending it is
// a pg_constraint row.
const EXPECTED_UNIQUE_INDEXES = ['accounts_code_unique'];

const conn = async () => {
  const client = new pg.Client(CFG);
  await client.connect();
  // Do not let a verifier role's personal schema shadow the application's
  // canonical public tables: every probe must exercise the migrated schema.
  await client.query('SET search_path TO public');

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

function assertExpectedRejection(name, error, expected) {
  const detail = error && typeof error === 'object' ? error : {};
  const mismatch = detail.code !== expected.code
    || (expected.constraint !== undefined && detail.constraint !== expected.constraint)
    || (expected.messageIncludes !== undefined && !String(detail.message ?? '').includes(expected.messageIncludes));

  if (mismatch) {
    const wanted = [
      `code=${expected.code}`,
      expected.constraint ? `constraint=${expected.constraint}` : null,
      expected.messageIncludes ? `message containing ${JSON.stringify(expected.messageIncludes)}` : null,
    ].filter(Boolean).join('; ');
    throw new Error(`${name}: expected ${wanted}; observed ${describeError(error)}`);
  }
}

async function assertExpectedSchema() {
  const client = await conn();
  try {
    const names = [...EXPECTED_CONSTRAINTS.keys()];
    const { rows } = await client.query(
      'SELECT conname, contype FROM pg_constraint WHERE conname = ANY($1::text[])',
      [names],
    );
    const actual = new Map(rows.map((row) => [row.conname, row.contype]));
    for (const [name, type] of EXPECTED_CONSTRAINTS) {
      if (actual.get(name) !== type) {
        throw new Error(`required constraint ${name} (type ${type}) is absent or has the wrong type`);
      }
    }

    const { rows: indexes } = await client.query(`
      SELECT index_class.relname AS name
      FROM pg_index index_row
      JOIN pg_class index_class ON index_class.oid = index_row.indexrelid
      WHERE index_class.relname = ANY($1::text[])
        AND index_row.indisunique
    `, [EXPECTED_UNIQUE_INDEXES]);
    const actualIndexes = new Set(indexes.map((row) => row.name));
    for (const name of EXPECTED_UNIQUE_INDEXES) {
      if (!actualIndexes.has(name)) {
        throw new Error(`required unique index ${name} is absent`);
      }
    }

    const { rowCount } = await client.query(`
      SELECT 1
      FROM pg_trigger trigger_row
      JOIN pg_class relation ON relation.oid = trigger_row.tgrelid
      WHERE relation.relname = 'people'
        AND trigger_row.tgname = 'people_identity_guard_trigger'
        AND NOT trigger_row.tgisinternal
    `);
    if (rowCount !== 1) {
      throw new Error('required people_identity_guard_trigger is absent');
    }

    console.log(`Schema preflight passed: ${names.length} named constraints, ${EXPECTED_UNIQUE_INDEXES.length} named unique index, and people_identity_guard_trigger are present.`);
  } finally {
    await client.end();
  }
}

/**
 * @param {string} name
 * @param {{
 *   expected: {code: string, constraint?: string, messageIncludes?: string},
 *   setup?: (client: import('pg').Client, ids: Record<string, string>) => Promise<void>,
 *   sql: string,
 *   params?: (ids: Record<string, string>) => unknown[],
 * }} probe
 */
async function mustReject(name, probe) {
  const client = await conn();
  const ids = {
    account: randomUUID(),
    accountDuplicate: randomUUID(),
    class: randomUUID(),
    journal: randomUUID(),
    journalLine: randomUUID(),
    payment: randomUUID(),
    person: randomUUID(),
    student: randomUUID(),
    studentAdmission: randomUUID(),
    enrollment: randomUUID(),
    verifiedPerson: randomUUID(),
  };
  let rejection;

  try {
    await client.query('BEGIN');
    if (probe.setup !== undefined) {
      await probe.setup(client, ids);
    }
    await client.query(probe.sql, probe.params?.(ids) ?? []);
  } catch (error) {
    rejection = error;
  } finally {
    await client.query('ROLLBACK').catch(() => {});
    await client.end().catch(() => {});
  }

  if (rejection === undefined) {
    throw new Error(`${name}: invalid write unexpectedly succeeded`);
  }

  assertExpectedRejection(name, rejection, probe.expected);
  const target = probe.expected.constraint ?? probe.expected.messageIncludes;
  console.log(`PASS  ${name}\n      ${describeError(rejection)}; target=${target}`);
}

try {
  assertDisposableVerificationTarget(CFG.database);
  await assertPrivilegedFixtureAccess(conn, 'Database invariant verification', {
    requiredTablePrivileges: [
      { table: 'payments', privileges: ['INSERT'] },
      { table: 'people', privileges: ['INSERT', 'UPDATE'] },
      { table: 'students', privileges: ['INSERT'] },
      { table: 'enrollments', privileges: ['INSERT'] },
      { table: 'classes', privileges: ['INSERT'] },
      { table: 'journal_lines', privileges: ['INSERT'] },
      { table: 'accounts', privileges: ['INSERT'] },
    ],
    userTriggerControlTables: ['payments', 'enrollments', 'classes', 'journal_lines'],
  });
  await assertExpectedSchema();

  await mustReject('positive payment amount', {
    expected: { code: '23514', constraint: 'payments_amount_check' },
    setup: async (client) => {
      // Keep workflow/provenance triggers out of the way so the named CHECK is
      // the first and only possible rejection. FK triggers are not USER
      // triggers and stay enabled, but the CHECK runs before their lookup.
      await client.query('ALTER TABLE payments DISABLE TRIGGER USER');
    },
    sql: `
      INSERT INTO payments (id, period_id, student_id, amount, method, payer_ref, received_on, recorded_by)
      VALUES ($1, $2, $3, -50.00, 'cash', 'runtime-invariant-negative-payment', CURRENT_DATE, $4)
    `,
    params: (ids) => [ids.payment, randomUUID(), randomUUID(), randomUUID()],
  });

  await mustReject('enrollment class foreign key', {
    expected: { code: '23503', constraint: 'enrollments_class_id_foreign' },
    setup: async (client, ids) => {
      // The student is only a transaction-scoped FK scaffold. Its historic
      // admission provenance is deliberately bypassed while it is created,
      // then normal trigger behaviour is restored before the target insert.
      await client.query("SET LOCAL session_replication_role = 'replica'");
      await client.query(
        "INSERT INTO people (id, legal_name, date_of_birth, verification_state) VALUES ($1, 'Runtime invariant person', '1980-01-01', 'unverified')",
        [ids.person],
      );
      await client.query(
        "INSERT INTO students (id, person_id, admission_decision_id, student_code) VALUES ($1, $2, $3, 'RUNTIME-INVARIANT-STUDENT')",
        [ids.student, ids.person, ids.studentAdmission],
      );
      await client.query("SET LOCAL session_replication_role = 'origin'");
      await client.query('ALTER TABLE enrollments DISABLE TRIGGER USER');
    },
    sql: `
      INSERT INTO enrollments (id, student_id, class_id, lifecycle_state)
      VALUES ($1, $2, $3, 'requested')
    `,
    params: (ids) => [ids.enrollment, ids.student, ids.class],
  });

  await mustReject('positive class capacity', {
    expected: { code: '23514', constraint: 'classes_capacity_check' },
    setup: async (client) => {
      await client.query('ALTER TABLE classes DISABLE TRIGGER USER');
    },
    sql: `
      INSERT INTO classes (id, program_version_id, period_id, capacity, lifecycle_state)
      VALUES ($1, $2, $3, 0, 'planned')
    `,
    params: (ids) => [ids.class, randomUUID(), randomUUID()],
  });

  await mustReject('verified-person identity finality', {
    expected: {
      code: '23514',
      messageIncludes: 'a verified person is final; identity evidence cannot be rewritten or revoked',
    },
    setup: async (client, ids) => {
      await client.query(
        `
          INSERT INTO people (
            id, legal_name, date_of_birth, verification_state,
            identity_key, identity_evidence_ref, verified_by, verified_at
          ) VALUES ($1, 'Runtime verified person', '1980-01-01', 'verified', 'runtime-identity-key', 'evidence/runtime', 'runtime-verifier', NOW())
        `,
        [ids.verifiedPerson],
      );
    },
    sql: 'UPDATE people SET identity_key = $1 WHERE id = $2',
    params: (ids) => ['runtime-identity-key-rewritten', ids.verifiedPerson],
  });

  await mustReject('journal line journal foreign key', {
    expected: { code: '23503', constraint: 'journal_lines_journal_id_foreign' },
    setup: async (client, ids) => {
      await client.query('ALTER TABLE journal_lines DISABLE TRIGGER USER');
      await client.query(
        "INSERT INTO accounts (id, code, name, type) VALUES ($1, $2, 'Runtime invariant journal account', 'asset')",
        [ids.account, `runtime-journal-${ids.account}`],
      );
    },
    sql: `
      INSERT INTO journal_lines (id, journal_id, account_id, direction, amount)
      VALUES ($1, $2, $3, 'debit', 10.00)
    `,
    params: (ids) => [ids.journalLine, ids.journal, ids.account],
  });

  await mustReject('unique account code', {
    expected: { code: '23505', constraint: 'accounts_code_unique' },
    setup: async (client, ids) => {
      await client.query(
        "INSERT INTO accounts (id, code, name, type) VALUES ($1, $2, 'Runtime invariant account', 'asset')",
        [ids.account, `runtime-account-${ids.account}`],
      );
    },
    sql: "INSERT INTO accounts (id, code, name, type) VALUES ($1, $2, 'Runtime invariant duplicate account', 'asset')",
    params: (ids) => [ids.accountDuplicate, `runtime-account-${ids.account}`],
  });

  console.log('Database invariants: 6/6 named production-schema boundaries rejected invalid writes.');
} catch (error) {
  console.error(`Database invariant verification failed: ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
