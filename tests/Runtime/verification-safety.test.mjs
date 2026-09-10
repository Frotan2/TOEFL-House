import assert from 'node:assert/strict';
import test from 'node:test';
import {
  assertDisposableVerificationTarget,
  assertPrivilegedFixtureAccess,
} from '../../scripts/runtime/verification-safety.mjs';

function fakePostgres({ denyReplicationRole = false } = {}) {
  const calls = [];
  let ended = false;

  return {
    calls,
    get ended() {
      return ended;
    },
    client: {
      async query(sql, params = []) {
        const statement = sql.replaceAll(/\s+/g, ' ').trim();
        calls.push({ statement, params });

        if (statement.startsWith('SELECT current_user')) {
          return { rows: [{ current_user: 'runtime_verifier', session_user: 'runtime_verifier' }] };
        }
        if (statement === "SET LOCAL session_replication_role = 'replica'") {
          if (denyReplicationRole) {
            const error = new Error('permission denied to set parameter "session_replication_role"');
            error.code = '42501';
            throw error;
          }

          return { rows: [] };
        }
        if (statement === 'SHOW session_replication_role') {
          return { rows: [{ session_replication_role: 'replica' }] };
        }
        if (statement.includes('to_regclass($1)::text AS relation')) {
          return { rows: [{ relation: 'payments', allowed: true }] };
        }

        return { rows: [] };
      },
      async end() {
        ended = true;
      },
    },
  };
}

test('disposable target gate allows only recognized names or the explicit reviewed override', () => {
  for (const database of ['toefl_house_dev', 'toefl_house_test', 'ci_toefl_house', 'toefl-house-e2e']) {
    assert.doesNotThrow(() => assertDisposableVerificationTarget(database, ''));
  }

  assert.throws(
    () => assertDisposableVerificationTarget('toefl_house', ''),
    /refusing verification writes against DB_DATABASE=toefl_house/,
  );
  assert.throws(
    () => assertDisposableVerificationTarget('toefl_house', 'true'),
    /RUNTIME_VERIFICATION_ALLOW_MUTATING_DATABASE=1/,
  );
  assert.doesNotThrow(() => assertDisposableVerificationTarget('toefl_house', '1'));
});

test('privileged preflight proves elevated access and always rolls it back', async () => {
  const postgres = fakePostgres();

  await assertPrivilegedFixtureAccess(
    async () => postgres.client,
    'Safety test',
    {
      requiredTablePrivileges: [{ table: 'payments', privileges: ['INSERT'] }],
      userTriggerControlTables: ['payments'],
    },
  );

  const statements = postgres.calls.map(({ statement }) => statement);
  assert.ok(statements.includes('BEGIN'));
  assert.ok(statements.includes("SET LOCAL session_replication_role = 'replica'"));
  assert.ok(statements.includes("SET LOCAL session_replication_role = 'origin'"));
  assert.ok(statements.includes('ALTER TABLE public."payments" DISABLE TRIGGER USER'));
  assert.ok(statements.includes('ROLLBACK'));
  assert.ok(!statements.includes('COMMIT'));
  assert.equal(postgres.ended, true);
});

test('privileged preflight fails before trigger control when replication-role access is denied', async () => {
  const postgres = fakePostgres({ denyReplicationRole: true });

  await assert.rejects(
    assertPrivilegedFixtureAccess(
      async () => postgres.client,
      'Safety test',
      { userTriggerControlTables: ['payments'] },
    ),
    (error) => error instanceof Error
      && error.message.includes('cannot run safely')
      && error.message.includes("SET LOCAL session_replication_role = 'replica'")
      && error.message.includes('code=42501'),
  );

  const statements = postgres.calls.map(({ statement }) => statement);
  assert.ok(statements.includes('BEGIN'));
  assert.ok(statements.includes('ROLLBACK'));
  assert.ok(!statements.some((statement) => statement.startsWith('ALTER TABLE')));
  assert.equal(postgres.ended, true);
});
