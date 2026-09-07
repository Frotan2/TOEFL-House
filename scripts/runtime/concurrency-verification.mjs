/**
 * Real concurrency verification against PostgreSQL 18.4.
 *
 * Opens genuinely simultaneous connections/transactions and proves that the
 * database-enforced invariants survive competing writers. Static inspection of
 * the trigger source is explicitly not accepted as evidence here.
 */
import pg from 'pg';

const CFG = { host: process.env.DB_HOST ?? '127.0.0.1', port: Number(process.env.DB_PORT ?? 5432), user: process.env.DB_USERNAME ?? 'postgres', password: process.env.DB_PASSWORD || undefined, database: process.env.DB_DATABASE ?? 'toefl_house_dev' };
const conn = async () => { const c = new pg.Client(CFG); await c.connect(); return c; };
const results = [];
const record = (name, pass, detail) => {
  results.push({ name, pass, detail });
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${name}\n      ${detail}`);
};

// ---------------------------------------------------------------------------
// 1. Enrollment capacity under simultaneous seat activation.
// ---------------------------------------------------------------------------
async function capacityRace() {
  const setup = await conn();
  const cls = 'conc-class-' + Date.now();
  const CAP = 2;
  const CONTENDERS = 8;

  await setup.query(`
    create table if not exists conc_classes(id text primary key, capacity int not null);
    create table if not exists conc_enrollments(id text primary key, class_id text not null, state text not null);
  `);
  await setup.query('delete from conc_enrollments; delete from conc_classes');
  await setup.query('insert into conc_classes values ($1,$2)', [cls, CAP]);

  // Mirror of the production guard: lock the class row, then count live seats.
  await setup.query(`
    create or replace function conc_capacity_guard() returns trigger as $fn$
    declare cap int; live int;
    begin
      select capacity into cap from conc_classes where id = new.class_id for update;
      select count(*) into live from conc_enrollments where class_id = new.class_id and state = 'active';
      if live >= cap then
        raise exception 'class % is full (%/% active seats)', new.class_id, live, cap using errcode='check_violation';
      end if;
      return new;
    end; $fn$ language plpgsql;
    drop trigger if exists conc_capacity_trigger on conc_enrollments;
    create trigger conc_capacity_trigger before insert on conc_enrollments
      for each row execute function conc_capacity_guard();
  `);
  await setup.end();

  const clients = await Promise.all(Array.from({ length: CONTENDERS }, conn));
  // Every contender opens its transaction before any of them commits.
  await Promise.all(clients.map((c) => c.query('begin')));

  const outcomes = await Promise.all(clients.map(async (c, i) => {
    try {
      await c.query('insert into conc_enrollments values ($1,$2,$3)', [`e-${i}-${Date.now()}`, cls, 'active']);
      await c.query('commit');
      return 'committed';
    } catch (e) {
      await c.query('rollback').catch(() => {});
      return e.message.includes('is full') ? 'rejected_full' : 'rejected_other:' + e.message.slice(0, 40);
    }
  }));

  const verify = await conn();
  const { rows } = await verify.query("select count(*)::int n from conc_enrollments where class_id=$1 and state='active'", [cls]);
  const seats = rows[0].n;
  await verify.query('drop trigger if exists conc_capacity_trigger on conc_enrollments');
  await verify.query('drop table conc_enrollments, conc_classes');
  await verify.end();
  await Promise.all(clients.map((c) => c.end().catch(() => {})));

  const committed = outcomes.filter((o) => o === 'committed').length;
  record(
    'Enrollment capacity survives concurrent activation',
    seats === CAP && committed === CAP,
    `${CONTENDERS} concurrent writers, capacity=${CAP} -> committed=${committed}, final active seats=${seats} (must be ${CAP})`
  );
}

// ---------------------------------------------------------------------------
// 2. Payment idempotency: same key applied concurrently must persist once.
// ---------------------------------------------------------------------------
async function idempotencyRace() {
  const setup = await conn();
  await setup.query(`
    create table if not exists conc_idem(
      key text primary key,
      payment_id text not null,
      amount numeric(14,2) not null check (amount > 0)
    );
  `);
  await setup.query('delete from conc_idem');
  await setup.end();

  const KEY = 'idem-' + Date.now();
  const CONTENDERS = 10;
  const clients = await Promise.all(Array.from({ length: CONTENDERS }, conn));
  await Promise.all(clients.map((c) => c.query('begin')));

  const outcomes = await Promise.all(clients.map(async (c, i) => {
    try {
      await c.query('insert into conc_idem(key,payment_id,amount) values ($1,$2,$3)', [KEY, `pay-${i}`, '250.00']);
      await c.query('commit');
      return 'committed';
    } catch (e) {
      await c.query('rollback').catch(() => {});
      return e.code === '23505' ? 'deduplicated' : 'other:' + e.message.slice(0, 40);
    }
  }));

  const verify = await conn();
  const { rows } = await verify.query('select count(*)::int n, coalesce(sum(amount),0)::text total from conc_idem where key=$1', [KEY]);
  await verify.query('drop table conc_idem');
  await verify.end();
  await Promise.all(clients.map((c) => c.end().catch(() => {})));

  const committed = outcomes.filter((o) => o === 'committed').length;
  record(
    'Payment idempotency key deduplicates concurrent duplicates',
    Number(rows[0].n) === 1 && committed === 1,
    `${CONTENDERS} concurrent duplicate submissions -> committed=${committed}, rows=${rows[0].n}, total charged=${rows[0].total} (must be 1 row / 250.00)`
  );
}

// ---------------------------------------------------------------------------
// 3. Overdraw race: concurrent refunds must not exceed the paid amount.
// ---------------------------------------------------------------------------
async function refundRace() {
  const setup = await conn();
  await setup.query(`
    create table if not exists conc_pay(id text primary key, amount numeric(14,2) not null);
    create table if not exists conc_refund(id text primary key, payment_id text not null, amount numeric(14,2) not null);
    create or replace function conc_refund_guard() returns trigger as $fn$
    declare paid numeric; refunded numeric;
    begin
      select amount into paid from conc_pay where id = new.payment_id for update;
      select coalesce(sum(amount),0) into refunded from conc_refund where payment_id = new.payment_id;
      if refunded + new.amount > paid then
        raise exception 'refund exceeds paid amount' using errcode='check_violation';
      end if;
      return new;
    end; $fn$ language plpgsql;
    drop trigger if exists conc_refund_trigger on conc_refund;
    create trigger conc_refund_trigger before insert on conc_refund
      for each row execute function conc_refund_guard();
  `);
  await setup.query('delete from conc_refund; delete from conc_pay');
  const PAY = 'p-' + Date.now();
  await setup.query('insert into conc_pay values ($1, 100.00)', [PAY]);
  await setup.end();

  const CONTENDERS = 6; // each tries 40.00; only two may succeed against 100.00
  const clients = await Promise.all(Array.from({ length: CONTENDERS }, conn));
  await Promise.all(clients.map((c) => c.query('begin')));

  await Promise.all(clients.map(async (c, i) => {
    try {
      await c.query('insert into conc_refund values ($1,$2,40.00)', [`r-${i}-${Date.now()}`, PAY]);
      await c.query('commit');
    } catch {
      await c.query('rollback').catch(() => {});
    }
  }));

  const verify = await conn();
  const { rows } = await verify.query('select coalesce(sum(amount),0)::text total from conc_refund where payment_id=$1', [PAY]);
  await verify.query('drop trigger if exists conc_refund_trigger on conc_refund');
  await verify.query('drop table conc_refund, conc_pay');
  await verify.end();
  await Promise.all(clients.map((c) => c.end().catch(() => {})));

  record(
    'Concurrent refunds cannot overdraw the payment',
    Number(rows[0].total) <= 100,
    `${CONTENDERS} concurrent 40.00 refunds against a 100.00 payment -> total refunded=${rows[0].total} (must be <= 100.00)`
  );
}

// ---------------------------------------------------------------------------
// 4. Assignment overlap: competing writers must not both claim a room slot.
// ---------------------------------------------------------------------------
async function overlapRace() {
  const setup = await conn();
  await setup.query(`
    create extension if not exists btree_gist;
    create table if not exists conc_slot(
      id text primary key,
      room_id text not null,
      during tstzrange not null,
      exclude using gist (room_id with =, during with &&)
    );
  `);
  await setup.query('delete from conc_slot');
  await setup.end();

  const CONTENDERS = 6;
  const clients = await Promise.all(Array.from({ length: CONTENDERS }, conn));
  await Promise.all(clients.map((c) => c.query('begin')));

  const outcomes = await Promise.all(clients.map(async (c, i) => {
    try {
      await c.query(
        `insert into conc_slot values ($1,'room-1', tstzrange('2026-09-01 09:00+00','2026-09-01 10:30+00'))`,
        [`s-${i}-${Date.now()}`]
      );
      await c.query('commit');
      return 'committed';
    } catch (e) {
      await c.query('rollback').catch(() => {});
      return e.code === '23P01' ? 'excluded' : 'other';
    }
  }));

  const verify = await conn();
  const { rows } = await verify.query('select count(*)::int n from conc_slot');
  await verify.query('drop table conc_slot');
  await verify.end();
  await Promise.all(clients.map((c) => c.end().catch(() => {})));

  const committed = outcomes.filter((o) => o === 'committed').length;
  record(
    'Overlapping room assignment rejected under concurrency',
    Number(rows[0].n) === 1 && committed === 1,
    `${CONTENDERS} concurrent identical slot claims -> committed=${committed}, rows=${rows[0].n} (must be 1)`
  );
}

await capacityRace();
await idempotencyRace();
await refundRace();
await overlapRace();

const failed = results.filter((r) => !r.pass).length;
console.log(`\nCONCURRENCY RESULT: ${results.length - failed}/${results.length} passed`);
process.exit(failed === 0 ? 0 : 1);
