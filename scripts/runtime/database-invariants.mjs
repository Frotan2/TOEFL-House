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
  ['asset_disposal_request_withdrawal_check', 'c'],
  ['asset_disposal_request_independent_approvers_check', 'c'],
]);

// This production boundary is intentionally a unique *index* rather than an
// ALTER TABLE unique constraint. PostgreSQL reports its name in `error.constraint`
// all the same, so catalogue and assert it explicitly instead of pretending it is
// a pg_constraint row.
const EXPECTED_UNIQUE_INDEXES = [
  'accounts_code_unique',
  'book_issuances_one_open_per_copy',
  'custodies_one_open_per_asset',
  'asset_disposal_requests_one_active_per_asset',
];

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
    libraryCopy: randomUUID(),
    libraryAsset: randomUUID(),
    libraryIssuance: randomUUID(),
    disposalRequest: randomUUID(),
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

/**
 * Transaction-scoped library topology scaffold. Rows are created under the
 * replica role so birth/history guards stay out of the way of fixtures; the
 * target invalid write always runs with normal trigger behaviour restored.
 */
async function libraryScaffold(client, ids) {
  const organizationId = randomUUID();
  const campusId = randomUUID();
  const branchId = randomUUID();

  await client.query("SET LOCAL session_replication_role = 'replica'");
  await client.query(
    "INSERT INTO organizations (id, name, lifecycle_state) VALUES ($1, 'Runtime invariant organization', 'active')",
    [organizationId],
  );
  await client.query(
    "INSERT INTO campuses (id, organization_id, name, lifecycle_state) VALUES ($1, $2, 'Runtime invariant campus', 'active')",
    [campusId, organizationId],
  );
  await client.query(
    "INSERT INTO branches (id, name, lifecycle_state) VALUES ($1, 'Runtime invariant branch', 'active')",
    [branchId],
  );
  await client.query(
    'INSERT INTO campus_assignments (id, branch_id, campus_id, effective_from, transfer_correlation_id) VALUES ($1, $2, $3, CURRENT_DATE, $4)',
    [randomUUID(), branchId, campusId, randomUUID()],
  );
  await client.query(
    "INSERT INTO people (id, legal_name, date_of_birth, verification_state, home_branch_id) VALUES ($1, 'Runtime invariant library person', '1980-01-01', 'unverified', $2)",
    [ids.person, branchId],
  );
  await client.query("SET LOCAL session_replication_role = 'origin'");

  return { organizationId, branchId };
}

async function libraryAssetScaffold(client, ids) {
  const topology = await libraryScaffold(client, ids);
  await client.query(
    `
      INSERT INTO assets (id, code, name, category, location, acquired_on, lifecycle_state, organization_id, originating_branch_id)
      VALUES ($1, $2, 'Runtime invariant asset', 'equipment', 'Room 1', CURRENT_DATE, 'in_service', $3, $4)
    `,
    [ids.libraryAsset, `RUNTIME-INV-${ids.libraryAsset}`, topology.organizationId, topology.branchId],
  );

  return topology;
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
      { table: 'organizations', privileges: ['INSERT'] },
      { table: 'campuses', privileges: ['INSERT'] },
      { table: 'branches', privileges: ['INSERT'] },
      { table: 'campus_assignments', privileges: ['INSERT'] },
      { table: 'book_copies', privileges: ['INSERT'] },
      { table: 'book_issuances', privileges: ['INSERT', 'UPDATE'] },
      { table: 'assets', privileges: ['INSERT'] },
      { table: 'custodies', privileges: ['INSERT'] },
      { table: 'asset_disposal_requests', privileges: ['INSERT', 'UPDATE'] },
    ],
    userTriggerControlTables: ['payments', 'enrollments', 'classes', 'journal_lines', 'asset_disposal_requests'],
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

  await mustReject('one open library issuance per copy', {
    expected: { code: '23505', constraint: 'book_issuances_one_open_per_copy' },
    setup: async (client, ids) => {
      const topology = await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO book_copies (id, code, title, acquired_on, organization_id, originating_branch_id) VALUES ($1, $2, 'Runtime invariant volume', CURRENT_DATE, $3, $4)",
        [ids.libraryCopy, `RUNTIME-INV-${ids.libraryCopy}`, topology.organizationId, topology.branchId],
      );
      await client.query(
        "INSERT INTO book_issuances (id, copy_id, borrower_person_id, issued_on, due_on, lifecycle_state, issued_by) VALUES ($1, $2, $3, CURRENT_DATE, CURRENT_DATE + INTERVAL '30 days', 'issued', $4)",
        [ids.libraryIssuance, ids.libraryCopy, ids.person, randomUUID()],
      );
    },
    sql: `
      INSERT INTO book_issuances (id, copy_id, borrower_person_id, issued_on, due_on, lifecycle_state, issued_by)
      VALUES ($1, $2, $3, CURRENT_DATE, CURRENT_DATE + INTERVAL '30 days', 'issued', $4)
    `,
    params: (ids) => [randomUUID(), ids.libraryCopy, ids.person, randomUUID()],
  });

  await mustReject('terminal issuance history is immutable', {
    expected: { code: '23514', messageIncludes: 'retained history' },
    setup: async (client, ids) => {
      const topology = await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO book_copies (id, code, title, acquired_on, organization_id, originating_branch_id) VALUES ($1, $2, 'Runtime invariant returned volume', CURRENT_DATE, $3, $4)",
        [ids.libraryCopy, `RUNTIME-INV-${ids.libraryCopy}`, topology.organizationId, topology.branchId],
      );
      await client.query("SET LOCAL session_replication_role = 'replica'");
      await client.query(
        "INSERT INTO book_issuances (id, copy_id, borrower_person_id, issued_on, due_on, returned_on, lifecycle_state, issued_by) VALUES ($1, $2, $3, CURRENT_DATE - 10, CURRENT_DATE + 20, CURRENT_DATE - 1, 'returned', $4)",
        [ids.libraryIssuance, ids.libraryCopy, ids.person, randomUUID()],
      );
      await client.query("SET LOCAL session_replication_role = 'origin'");
    },
    sql: 'UPDATE book_issuances SET issued_on = CURRENT_DATE - 30 WHERE id = $1',
    params: (ids) => [ids.libraryIssuance],
  });

  await mustReject('one open custody per asset', {
    expected: { code: '23505', constraint: 'custodies_one_open_per_asset' },
    setup: async (client, ids) => {
      await libraryAssetScaffold(client, ids);
      await client.query(
        'INSERT INTO custodies (id, asset_id, custodian_person_id, assigned_on, assigned_by) VALUES ($1, $2, $3, CURRENT_DATE, $4)',
        [randomUUID(), ids.libraryAsset, ids.person, randomUUID()],
      );
    },
    sql: 'INSERT INTO custodies (id, asset_id, custodian_person_id, assigned_on, assigned_by) VALUES ($1, $2, $3, CURRENT_DATE, $4)',
    params: (ids) => [randomUUID(), ids.libraryAsset, ids.person, randomUUID()],
  });

  await mustReject('one active disposal request per asset', {
    expected: { code: '23505', constraint: 'asset_disposal_requests_one_active_per_asset' },
    setup: async (client, ids) => {
      await libraryAssetScaffold(client, ids);
      await client.query(
        "INSERT INTO asset_disposal_requests (id, asset_id, method, reason, lifecycle_state, requested_by, created_at, updated_at) VALUES ($1, $2, 'scrap', 'runtime invariant request', 'requested', $3, NOW(), NOW())",
        [ids.disposalRequest, ids.libraryAsset, ids.person],
      );
    },
    sql: "INSERT INTO asset_disposal_requests (id, asset_id, method, reason, lifecycle_state, requested_by, created_at, updated_at) VALUES ($1, $2, 'sale', 'runtime invariant second request', 'requested', $3, NOW(), NOW())",
    params: (ids) => [randomUUID(), ids.libraryAsset, randomUUID()],
  });

  await mustReject('disposal withdrawal records the requesting session', {
    expected: { code: '23514', constraint: 'asset_disposal_request_withdrawal_check' },
    setup: async (client, ids) => {
      await libraryAssetScaffold(client, ids);
      await client.query("SET LOCAL session_replication_role = 'replica'");
      await client.query(
        "INSERT INTO asset_disposal_requests (id, asset_id, method, reason, lifecycle_state, requested_by, created_at, updated_at) VALUES ($1, $2, 'donation', 'runtime invariant withdrawal request', 'requested', $3, NOW(), NOW())",
        [ids.disposalRequest, ids.libraryAsset, ids.person],
      );
      await client.query("SET LOCAL session_replication_role = 'origin'");
      // The withdrawal CHECK is the target; keep the workflow guard from
      // masking the named constraint under test.
      await client.query('ALTER TABLE asset_disposal_requests DISABLE TRIGGER USER');
    },
    sql: "UPDATE asset_disposal_requests SET lifecycle_state = 'withdrawn', withdrawn_by = $1, updated_at = NOW() WHERE id = $2",
    params: (ids) => [randomUUID(), ids.disposalRequest],
  });

  await mustReject('disposal approvers stay independent of the requester', {
    expected: { code: '23514', constraint: 'asset_disposal_request_independent_approvers_check' },
    setup: async (client, ids) => {
      await libraryAssetScaffold(client, ids);
      await client.query("SET LOCAL session_replication_role = 'replica'");
      await client.query(
        "INSERT INTO asset_disposal_requests (id, asset_id, method, reason, lifecycle_state, requested_by, created_at, updated_at) VALUES ($1, $2, 'sale', 'runtime invariant independence request', 'requested', $3, NOW(), NOW())",
        [ids.disposalRequest, ids.libraryAsset, ids.person],
      );
      await client.query("SET LOCAL session_replication_role = 'origin'");
      await client.query('ALTER TABLE asset_disposal_requests DISABLE TRIGGER USER');
    },
    sql: 'UPDATE asset_disposal_requests SET approver_two_id = $1, updated_at = NOW() WHERE id = $2',
    params: (ids) => [ids.person, ids.disposalRequest],
  });

  console.log('Database invariants: 12/12 named production-schema boundaries rejected invalid writes.');
} catch (error) {
  console.error(`Database invariant verification failed: ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
