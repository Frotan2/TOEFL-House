# Reporting + Integrations — operational verification

This branch is intentionally limited to Reporting and Integrations infrastructure. Reporting remains a derived/read-only authority over canonical source domains; Integrations remains the delivery boundary and never becomes business truth.

## Required verification

Run against the real PostgreSQL-backed application environment used by CI/deployment. Do not mark the branch closed from static inspection alone.

```bash
php artisan migrate:fresh --seed
php artisan test --filter=ReportingIntegrationsIntegrityTest
php artisan test --filter=IntegrationsFeatureTest
php artisan test --filter='Outbox'
php artisan route:list --path=api/v1
```

Then inspect the changed schema and exercise one end-to-end delivery path.

```sql
SELECT tablename, indexname, indexdef
FROM pg_indexes
WHERE indexname IN (
  'inbound_events_endpoint_external_id_accepted_unique',
  'integration_deliveries_endpoint_idempotency_unique'
)
ORDER BY indexname;
```

## Invariants to prove

1. **Read-only reporting authority** — Reporting can compute and persist report evidence/projections, but cannot mutate canonical Finance, Academic, CRM, Placement, HR, or other normal-domain facts. Report runs are append-only evidence and are pinned to a metric version plus authoritative period.
2. **Lineage** — every exposed metric must pass `MetricCatalog::assertDefinitionLineage`; unresolved/foreign source ownership is withheld rather than approximated.
3. **Rebuildability** — deleting/rebuilding delivery receipts must not require changing `domain_events`; consumer state is disposable and replayable from the immutable event log.
4. **Transactional outbox** — successful domain operations publish the immutable event in the caller transaction; a rollback must leave no corresponding committed domain event.
5. **Inbound idempotency** — accepted `(endpoint, external_id)` is database-unique; rejected evidence does not block a corrected retry.
6. **Outbound idempotency** — `(endpoint, idempotency_key)` is database-unique and the same delivery identity survives retry, dead-letter, and manual replay.
7. **Idempotent consumers** — one `(event, consumer)` receipt is the authority for processing state; concurrent workers cannot produce two successful receipts.
8. **Replay/DLQ semantics** — dead-lettered consumer receipts may be explicitly replayed, with attempts reset and `replay_count` incremented, while the immutable event and consumer identity remain unchanged. Retired/unregistered consumers fail closed.
9. **Observability** — retries, terminal delivery, dead-lettering, requeue/replay, and report evidence are visible through structured command results and audit events; failures must not be silently converted to success.
10. **Failure recovery** — an adapter exception affects only its delivery attempt; sibling deliveries continue, leases expire safely, and bounded retries eventually dead-letter instead of looping forever.

## Adversarial checks

Verify at minimum:

- two simultaneous inbound receives with the same accepted external id produce one accepted event;
- two simultaneous outbound dispatches with the same endpoint/idempotency key produce one delivery row;
- a consumer crashes after its side effect but before receipt completion and a replay is safe only when the consumer's own effect is transactionally coupled to the receipt;
- a lease-expired worker cannot overwrite a newer attempt;
- a dead-letter receipt cannot be replayed twice from the same idempotency key;
- a replay to a retired/unregistered consumer fails closed;
- an incomplete report never becomes a dashboard value merely because a row exists;
- direct model update/delete of a persisted `ReportRun` is rejected.

## Closure standard

Do not claim closure unless the current branch commit has passing CI, the focused tests above pass on PostgreSQL, the index checks confirm the DB uniqueness constraints, and the end-to-end failure/recovery checks produce the expected persisted states.
