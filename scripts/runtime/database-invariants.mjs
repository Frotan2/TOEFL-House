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
  ['documents_lifecycle_state_check', 'c'],
  ['document_classifications_access_class_check', 'c'],
  ['document_classifications_category_unique', 'u'],
  ['document_versions_document_id_version_no_unique', 'u'],
  ['document_verifications_result_check', 'c'],
  ['retention_rules_positive_period_check', 'c'],
  ['retention_rules_category_unique', 'u'],
  ['retention_decisions_action_check', 'c'],
  ['consent_purposes_name_channel_unique', 'u'],
  ['consents_lifecycle_state_check', 'c'],
  ['consents_period_check', 'c'],
  ['disclosures_scope_type_check', 'c'],
  ['privacy_export_requests_purpose_check', 'c'],
  ['privacy_export_requests_lifecycle_state_check', 'c'],
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
  'document_verifications_one_verdict_per_version',
  'consents_one_open_per_subject_purpose',
];

// Final-state trigger guards whose absence would silently reopen a closed
// evidence boundary (identity rewrite protection, the three append-only
// Documents guards, and the four Privacy guards that make consent,
// withdrawal, disclosure and staged-export evidence unavoidable facts).
const EXPECTED_TRIGGERS = [
  ['people', 'people_identity_guard_trigger'],
  ['document_versions', 'document_versions_immutable_trigger'],
  ['document_verifications', 'document_verifications_append_only_trigger'],
  ['retention_decisions', 'retention_decisions_append_only_trigger'],
  ['consents', 'consents_guard_trigger'],
  ['consent_revocations', 'consent_revocations_append_only_trigger'],
  ['disclosures', 'disclosures_append_only_trigger'],
  ['privacy_export_requests', 'privacy_export_requests_guard_trigger'],
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

    for (const [table, trigger] of EXPECTED_TRIGGERS) {
      const { rowCount } = await client.query(`
        SELECT 1
        FROM pg_trigger trigger_row
        JOIN pg_class relation ON relation.oid = trigger_row.tgrelid
        WHERE relation.relname = $1
          AND trigger_row.tgname = $2
          AND NOT trigger_row.tgisinternal
      `, [table, trigger]);
      if (rowCount !== 1) {
        throw new Error(`required trigger ${trigger} on ${table} is absent`);
      }
    }

    console.log(`Schema preflight passed: ${names.length} named constraints, ${EXPECTED_UNIQUE_INDEXES.length} named unique indexes, and ${EXPECTED_TRIGGERS.length} named triggers are present.`);
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
    document: randomUUID(),
    documentClassification: randomUUID(),
    documentVersion: randomUUID(),
    documentVerification: randomUUID(),
    retentionRule: randomUUID(),
    retentionDecision: randomUUID(),
    consentPurpose: randomUUID(),
    consent: randomUUID(),
    consentRevocation: randomUUID(),
    disclosure: randomUUID(),
    exportRequest: randomUUID(),
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

/**
 * Transaction-scoped Documents evidence scaffold: one classification, one
 * submitted document and its first immutable version, on top of the library
 * topology (which supplies the subject person).
 */
async function documentsScaffold(client, ids) {
  const topology = await libraryScaffold(client, ids);
  await client.query(
    "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, $2, 'documents-runtime', 'restricted')",
    [ids.documentClassification, `runtime-inv-${ids.documentClassification}`],
  );
  await client.query(
    "INSERT INTO documents (id, subject_person_id, classification_id, title, lifecycle_state) VALUES ($1, $2, $3, 'Runtime invariant document', 'submitted')",
    [ids.document, ids.person, ids.documentClassification],
  );
  await client.query(
    "INSERT INTO document_versions (id, document_id, version_no, content_hash, storage_ref, uploaded_by) VALUES ($1, $2, 1, 'runtime-invariant-hash', 'storage/runtime-invariant', $3)",
    [ids.documentVersion, ids.document, ids.person],
  );

  return topology;
}

async function documentsVerdictScaffold(client, ids) {
  await documentsScaffold(client, ids);
  await client.query(
    "INSERT INTO document_verifications (id, document_id, version_no, verifier_person_id, result, reason) VALUES ($1, $2, 1, $3, 'pass', 'runtime invariant verdict')",
    [ids.documentVerification, ids.document, ids.person],
  );
}

/**
 * Transaction-scoped Privacy scaffold: one purpose and one born-draft consent
 * for the library-topology subject. The consent guard is a production
 * boundary, so fixtures are created *through* it rather than around it — a
 * fixture that only exists because triggers were disabled proves nothing.
 */
async function privacyScaffold(client, ids) {
  const topology = await libraryScaffold(client, ids);
  await client.query(
    "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
    [ids.consentPurpose, `runtime-inv-${ids.consentPurpose}`],
  );
  await client.query(
    `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'draft', CURRENT_DATE, NULL, 'evidence/runtime-invariant', $4, NOW(), NOW())
    `,
    [ids.consent, ids.person, ids.consentPurpose, ids.person],
  );

  return topology;
}

/** Walk the staged chain to `exported` so a closed request can be attacked. */
async function exportedRequestScaffold(client, ids) {
  const topology = await privacyScaffold(client, ids);
  await client.query(
    `
      INSERT INTO privacy_export_requests (id, subject_person_id, purpose, organization_id, lifecycle_state, requested_by, created_at, updated_at)
      VALUES ($1, $2, 'runtime invariant bulk export', $3, 'requested', $4, NOW(), NOW())
    `,
    [ids.exportRequest, ids.person, topology.organizationId, ids.person],
  );
  await client.query('UPDATE privacy_export_requests SET approver_one_id = $1, updated_at = NOW() WHERE id = $2', [randomUUID(), ids.exportRequest]);
  await client.query("UPDATE privacy_export_requests SET approver_two_id = $1, lifecycle_state = 'approved', updated_at = NOW() WHERE id = $2", [randomUUID(), ids.exportRequest]);
  await client.query("UPDATE privacy_export_requests SET exported_by = $1, disclosure_id = $2, lifecycle_state = 'exported', updated_at = NOW() WHERE id = $3", [randomUUID(), randomUUID(), ids.exportRequest]);
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
      { table: 'document_classifications', privileges: ['INSERT'] },
      { table: 'documents', privileges: ['INSERT'] },
      { table: 'document_versions', privileges: ['INSERT', 'UPDATE'] },
      { table: 'document_verifications', privileges: ['INSERT', 'UPDATE'] },
      { table: 'retention_rules', privileges: ['INSERT'] },
      { table: 'retention_decisions', privileges: ['INSERT', 'UPDATE'] },
      { table: 'consent_purposes', privileges: ['INSERT'] },
      { table: 'consents', privileges: ['INSERT', 'UPDATE', 'DELETE'] },
      { table: 'consent_revocations', privileges: ['INSERT', 'UPDATE'] },
      { table: 'disclosures', privileges: ['INSERT', 'UPDATE'] },
      { table: 'privacy_export_requests', privileges: ['INSERT', 'UPDATE', 'DELETE'] },
    ],
    userTriggerControlTables: ['payments', 'enrollments', 'classes', 'journal_lines', 'asset_disposal_requests', 'consents'],
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

  await mustReject('document lifecycle state legality', {
    expected: { code: '23514', constraint: 'documents_lifecycle_state_check' },
    setup: async (client, ids) => {
      await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, $2, 'documents-runtime', 'restricted')",
        [ids.documentClassification, `runtime-inv-${ids.documentClassification}`],
      );
    },
    sql: "INSERT INTO documents (id, subject_person_id, classification_id, title, lifecycle_state) VALUES ($1, $2, $3, 'Runtime invalid document', 'burned')",
    params: (ids) => [ids.document, ids.person, ids.documentClassification],
  });

  await mustReject('document versions stay immutable', {
    expected: { code: 'P0001', messageIncludes: 'document versions are immutable' },
    setup: (client, ids) => documentsScaffold(client, ids),
    sql: "UPDATE document_versions SET content_hash = 'tampered' WHERE id = $1",
    params: (ids) => [ids.documentVersion],
  });

  await mustReject('one version row per document version number', {
    expected: { code: '23505', constraint: 'document_versions_document_id_version_no_unique' },
    setup: (client, ids) => documentsScaffold(client, ids),
    sql: "INSERT INTO document_versions (id, document_id, version_no, content_hash, storage_ref, uploaded_by) VALUES ($1, $2, 1, 'rival-hash', 'storage/rival', $3)",
    params: (ids) => [randomUUID(), ids.document, ids.person],
  });

  await mustReject('verification verdicts stay append-only', {
    expected: { code: 'P0001', messageIncludes: 'document_verifications is append-only' },
    setup: (client, ids) => documentsVerdictScaffold(client, ids),
    sql: "UPDATE document_verifications SET result = 'fail' WHERE id = $1",
    params: (ids) => [ids.documentVerification],
  });

  await mustReject('verification result legality', {
    expected: { code: '23514', constraint: 'document_verifications_result_check' },
    setup: (client, ids) => documentsScaffold(client, ids),
    sql: "INSERT INTO document_verifications (id, document_id, version_no, verifier_person_id, result, reason) VALUES ($1, $2, 1, $3, 'maybe', 'runtime invalid verdict')",
    params: (ids) => [ids.documentVerification, ids.document, ids.person],
  });

  await mustReject('one verdict per document version', {
    expected: { code: '23505', constraint: 'document_verifications_one_verdict_per_version' },
    setup: (client, ids) => documentsVerdictScaffold(client, ids),
    sql: "INSERT INTO document_verifications (id, document_id, version_no, verifier_person_id, result, reason) VALUES ($1, $2, 1, $3, 'fail', 'runtime contradictory verdict')",
    params: (ids) => [randomUUID(), ids.document, ids.person],
  });

  await mustReject('retention rules keep a positive period', {
    expected: { code: '23514', constraint: 'retention_rules_positive_period_check' },
    sql: "INSERT INTO retention_rules (id, category, retention_days, legal_basis) VALUES ($1, 'runtime-invalid-period', 0, 'runtime invariant basis')",
    params: (ids) => [ids.retentionRule],
  });

  await mustReject('classification categories stay unique', {
    expected: { code: '23505', constraint: 'document_classifications_category_unique' },
    setup: async (client, ids) => {
      await client.query(
        "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, 'runtime-duplicate-category', 'documents-runtime', 'internal')",
        [ids.documentClassification],
      );
    },
    sql: "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, 'runtime-duplicate-category', 'documents-runtime', 'internal')",
    params: () => [randomUUID()],
  });

  await mustReject('classification access-class legality', {
    expected: { code: '23514', constraint: 'document_classifications_access_class_check' },
    sql: "INSERT INTO document_classifications (id, category, owner_module, access_class) VALUES ($1, 'runtime-invalid-access', 'documents-runtime', 'secret')",
    params: (ids) => [ids.documentClassification],
  });

  await mustReject('retention decisions stay append-only', {
    expected: { code: 'P0001', messageIncludes: 'retention_decisions is append-only' },
    setup: async (client, ids) => {
      await documentsScaffold(client, ids);
      await client.query(
        "INSERT INTO retention_rules (id, category, retention_days, legal_basis) VALUES ($1, $2, 30, 'runtime invariant basis')",
        [ids.retentionRule, `runtime-inv-${ids.documentClassification}`],
      );
      await client.query(
        "INSERT INTO retention_decisions (id, document_id, rule_id, action, basis, decided_by) VALUES ($1, $2, $3, 'retain', 'runtime invariant basis', $4)",
        [ids.retentionDecision, ids.document, ids.retentionRule, ids.person],
      );
    },
    sql: "UPDATE retention_decisions SET action = 'archive' WHERE id = $1",
    params: (ids) => [ids.retentionDecision],
  });

  await mustReject('retention decision action legality', {
    expected: { code: '23514', constraint: 'retention_decisions_action_check' },
    setup: async (client, ids) => {
      await documentsScaffold(client, ids);
      await client.query(
        "INSERT INTO retention_rules (id, category, retention_days, legal_basis) VALUES ($1, $2, 30, 'runtime invariant basis')",
        [ids.retentionRule, `runtime-inv-${ids.documentClassification}`],
      );
    },
    sql: "INSERT INTO retention_decisions (id, document_id, rule_id, action, basis, decided_by) VALUES ($1, $2, $3, 'purge', 'runtime invariant basis', $4)",
    params: (ids) => [ids.retentionDecision, ids.document, ids.retentionRule, ids.person],
  });

  await mustReject('consent purpose catalog stays unique per name and channel', {
    expected: { code: '23505', constraint: 'consent_purposes_name_channel_unique' },
    setup: async (client, ids) => {
      await client.query(
        "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, 'runtime-duplicate-purpose', 'email', 'communication')",
        [ids.consentPurpose],
      );
    },
    sql: "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, 'runtime-duplicate-purpose', 'email', 'marketing')",
    params: () => [randomUUID()],
  });

  await mustReject('consent lifecycle state legality', {
    expected: { code: '23514', constraint: 'consents_lifecycle_state_check' },
    setup: async (client, ids) => {
      await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
        [ids.consentPurpose, `runtime-inv-${ids.consentPurpose}`],
      );
      // The consent guard rejects a non-draft birth first; disable USER
      // triggers so the named vocabulary CHECK is the rejection under test.
      await client.query('ALTER TABLE consents DISABLE TRIGGER USER');
    },
    sql: `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'burned', CURRENT_DATE, NULL, 'evidence/runtime-invariant', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.consent, ids.person, ids.consentPurpose, ids.person],
  });

  await mustReject('a consent is born draft', {
    expected: { code: '23514', messageIncludes: 'a consent is born draft' },
    setup: async (client, ids) => {
      await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
        [ids.consentPurpose, `runtime-inv-${ids.consentPurpose}`],
      );
    },
    // Verification and activation are separate attributable acts: no writer
    // may forge an already-active consent, whatever capability it holds.
    sql: `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'active', CURRENT_DATE, NULL, 'evidence/runtime-invariant', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.consent, ids.person, ids.consentPurpose, ids.person],
  });

  await mustReject('a consent requires its evidence locator', {
    expected: { code: '23514', messageIncludes: 'a consent requires its evidence reference' },
    setup: async (client, ids) => {
      await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
        [ids.consentPurpose, `runtime-inv-${ids.consentPurpose}`],
      );
    },
    sql: `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'draft', CURRENT_DATE, NULL, '   ', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.consent, ids.person, ids.consentPurpose, ids.person],
  });

  await mustReject('a consent window must end after it starts', {
    expected: { code: '23514', constraint: 'consents_period_check' },
    setup: async (client, ids) => {
      await libraryScaffold(client, ids);
      await client.query(
        "INSERT INTO consent_purposes (id, name, channel, category) VALUES ($1, $2, 'email', 'communication')",
        [ids.consentPurpose, `runtime-inv-${ids.consentPurpose}`],
      );
    },
    sql: `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'draft', CURRENT_DATE, CURRENT_DATE, 'evidence/runtime-invariant', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.consent, ids.person, ids.consentPurpose, ids.person],
  });

  await mustReject('one open consent per subject and purpose', {
    expected: { code: '23505', constraint: 'consents_one_open_per_subject_purpose' },
    setup: (client, ids) => privacyScaffold(client, ids),
    sql: `
      INSERT INTO consents (id, subject_person_id, purpose_id, lifecycle_state, effective_from, effective_to, evidence_ref, recorded_by, created_at, updated_at)
      VALUES ($1, $2, $3, 'draft', CURRENT_DATE, NULL, 'evidence/runtime-rival', $4, NOW(), NOW())
    `,
    params: (ids) => [randomUUID(), ids.person, ids.consentPurpose, ids.person],
  });

  await mustReject('consent facts are write-once', {
    expected: { code: '23514', messageIncludes: 'only the lifecycle state may change on a consent' },
    setup: (client, ids) => privacyScaffold(client, ids),
    // A corrected consent is a new consent: the subject, purpose, evidence
    // locator, effective window and recorder can never be rewritten.
    sql: "UPDATE consents SET evidence_ref = 'evidence/tampered' WHERE id = $1",
    params: (ids) => [ids.consent],
  });

  await mustReject('consents move only forward through the lifecycle', {
    expected: { code: '23514', messageIncludes: 'a consent moves only forward' },
    setup: (client, ids) => privacyScaffold(client, ids),
    sql: "UPDATE consents SET lifecycle_state = 'active', updated_at = NOW() WHERE id = $1",
    params: (ids) => [ids.consent],
  });

  await mustReject('consent evidence cannot be deleted', {
    expected: { code: '23514', messageIncludes: 'cannot be deleted' },
    setup: (client, ids) => privacyScaffold(client, ids),
    sql: 'DELETE FROM consents WHERE id = $1',
    params: (ids) => [ids.consent],
  });

  await mustReject('consent withdrawals stay append-only', {
    expected: { code: 'P0001', messageIncludes: 'privacy evidence is append-only' },
    setup: async (client, ids) => {
      await privacyScaffold(client, ids);
      await client.query(
        "INSERT INTO consent_revocations (id, consent_id, revoked_by, scope, effect, created_at, updated_at) VALUES ($1, $2, $3, 'all-channels', 'immediate-cessation', NOW(), NOW())",
        [ids.consentRevocation, ids.consent, ids.person],
      );
    },
    sql: "UPDATE consent_revocations SET effect = 'rewritten' WHERE id = $1",
    params: (ids) => [ids.consentRevocation],
  });

  await mustReject('disclosure scope vocabulary legality', {
    expected: { code: '23514', constraint: 'disclosures_scope_type_check' },
    setup: (client, ids) => privacyScaffold(client, ids),
    sql: `
      INSERT INTO disclosures (id, subject_person_id, recipient, purpose, authority, scope_type, scope_id, disclosed_category, disclosed_by, created_at, updated_at)
      VALUES ($1, $2, 'Runtime invariant recipient', 'runtime-invariant', 'privacy.disclose', 'galaxy', $3, 'academic-records', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.disclosure, ids.person, ids.person, ids.person],
  });

  await mustReject('disclosures stay append-only', {
    expected: { code: 'P0001', messageIncludes: 'privacy evidence is append-only' },
    setup: async (client, ids) => {
      await privacyScaffold(client, ids);
      await client.query(
        `
          INSERT INTO disclosures (id, subject_person_id, recipient, purpose, authority, scope_type, scope_id, disclosed_category, disclosed_by, created_at, updated_at)
          VALUES ($1, $2, 'Runtime invariant recipient', 'runtime-invariant', 'privacy.disclose', 'subject', $3, 'academic-records', $4, NOW(), NOW())
        `,
        [ids.disclosure, ids.person, ids.person, ids.person],
      );
    },
    // Release evidence is a fact about the past; a correction is a new
    // disclosure, never an edit of the recorded one.
    sql: "UPDATE disclosures SET recipient = 'Rewritten recipient' WHERE id = $1",
    params: (ids) => [ids.disclosure],
  });

  await mustReject('a bulk export request is born requested', {
    expected: { code: '23514', messageIncludes: 'a bulk export request is born requested' },
    setup: (client, ids) => privacyScaffold(client, ids),
    sql: `
      INSERT INTO privacy_export_requests (id, subject_person_id, purpose, organization_id, lifecycle_state, requested_by, created_at, updated_at)
      VALUES ($1, $2, 'runtime invariant forged approval', $3, 'approved', $4, NOW(), NOW())
    `,
    params: (ids) => [ids.exportRequest, ids.person, ids.person, ids.person],
  });

  await mustReject('bulk export approvals stay distinct', {
    expected: { code: '23514', messageIncludes: 'a bulk export needs two distinct approvers' },
    setup: async (client, ids) => {
      await privacyScaffold(client, ids);
      await client.query(
        `
          INSERT INTO privacy_export_requests (id, subject_person_id, purpose, organization_id, lifecycle_state, requested_by, created_at, updated_at)
          VALUES ($1, $2, 'runtime invariant single approver', $3, 'requested', $4, NOW(), NOW())
        `,
        [ids.exportRequest, ids.person, ids.person, ids.person],
      );
      await client.query('UPDATE privacy_export_requests SET approver_one_id = $1, updated_at = NOW() WHERE id = $2', [ids.person, ids.exportRequest]);
    },
    sql: "UPDATE privacy_export_requests SET approver_two_id = $1, lifecycle_state = 'approved', updated_at = NOW() WHERE id = $2",
    params: (ids) => [ids.person, ids.exportRequest],
  });

  await mustReject('an executed bulk export request is closed', {
    expected: { code: '23514', messageIncludes: 'an executed bulk export request is closed' },
    setup: (client, ids) => exportedRequestScaffold(client, ids),
    sql: "UPDATE privacy_export_requests SET purpose = 'rewritten after release', updated_at = NOW() WHERE id = $1",
    params: (ids) => [ids.exportRequest],
  });

  await mustReject('bulk export requests cannot be deleted', {
    expected: { code: '23514', messageIncludes: 'cannot be deleted' },
    setup: (client, ids) => exportedRequestScaffold(client, ids),
    sql: 'DELETE FROM privacy_export_requests WHERE id = $1',
    params: (ids) => [ids.exportRequest],
  });

  console.log('Database invariants: 39/39 named production-schema boundaries rejected invalid writes.');
} catch (error) {
  console.error(`Database invariant verification failed: ${error instanceof Error ? error.message : String(error)}`);
  process.exitCode = 1;
}
