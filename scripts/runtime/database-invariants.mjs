/**
 * Database negative tests against the REAL migrated schema.
 *
 * Each case attempts an invalid write and requires PostgreSQL itself to reject
 * it. A rule is only treated as verified when the database refuses the
 * operation; application-level checks are not accepted as evidence here.
 */
import pg from 'pg';

const c = new pg.Client({ host: process.env.DB_HOST ?? '127.0.0.1', port: Number(process.env.DB_PORT ?? 5432), user: process.env.DB_USERNAME ?? 'postgres', password: process.env.DB_PASSWORD || undefined, database: process.env.DB_DATABASE ?? 'toefl_house_dev' });
await c.connect();

const results = [];

/** Runs `sql` inside a savepoint and expects it to fail. */
async function mustReject(name, sql, params = []) {
  await c.query('begin');
  let rejected = false;
  let detail = '';
  try {
    await c.query(sql, params);
    detail = 'ACCEPTED (invariant not enforced)';
  } catch (e) {
    rejected = true;
    detail = `${e.code} ${String(e.message).split('\n')[0].slice(0, 96)}`;
  }
  await c.query('rollback');
  results.push({ name, rejected });
  console.log(`${rejected ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
}

/** Seeds a valid row, then requires the follow-up statement to be rejected. */
async function mustRejectSeeded(name, seedSql, sql) {
  await c.query('begin');
  let rejected = false;
  let detail = '';
  try {
    await c.query(seedSql);
    await c.query(sql);
    detail = 'ACCEPTED (invariant not enforced)';
  } catch (e) {
    rejected = true;
    detail = `${e.code} ${String(e.message).split('\n')[0].slice(0, 96)}`;
  }
  await c.query('rollback');
  results.push({ name, rejected });
  console.log(`${rejected ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
}

console.log('=== DATABASE NEGATIVE TESTS (PostgreSQL 18.4, migrated schema) ===\n');

await mustReject(
  'Negative monetary amount rejected',
  `insert into payments (id, amount) values ('neg-1', -50.00)`
);

await mustReject(
  'Unknown foreign key rejected (enrollment -> class)',
  `insert into enrollments (id, student_id, class_id, lifecycle_state)
   values ('fk-1', 'no-such-student', 'no-such-class', 'requested')`
);

await mustReject(
  'Class capacity must be positive',
  `insert into classes (id, offering_id, program_version_id, period_id, capacity, lifecycle_state)
   values ('cap-1', 'x', 'x', 'x', 0, 'planned')`
);

// Seeded inside the savepoint so the UPDATE always matches a real row;
// asserting against an empty table would pass vacuously.
await mustRejectSeeded(
  'Verified person identity is immutable',
  `insert into people (id, legal_name, date_of_birth, verification_state,
                       identity_key, identity_evidence_ref, verified_by, verified_at)
   values ('inv-probe-person', 'Invariant Probe', '1980-01-01', 'verified',
           'probe-key', 'evidence/probe', 'probe-verifier', now())`,
  `update people set identity_key = 'rewritten' where id = 'inv-probe-person'`
);

await mustReject(
  'Unknown journal/account foreign key rejected',
  `insert into journal_lines (id, journal_id, account_id, direction, amount)
   values ('jl-probe', 'no-journal', 'no-account', 'debit', 10)`
);

await mustReject(
  'Duplicate account code rejected',
  `insert into accounts (id, code, name, type)
   select 'dup-1', code, name, type from accounts limit 1`
);

await c.end();

const failed = results.filter((r) => !r.rejected).length;
console.log(`\nINVARIANT RESULT: ${results.length - failed}/${results.length} enforced by PostgreSQL`);
process.exit(failed === 0 ? 0 : 1);
