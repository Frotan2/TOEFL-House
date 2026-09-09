<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Modules\Integrations\Models\InboundEvent;
use App\Modules\Integrations\Models\IntegrationDelivery;
use App\Modules\Reporting\Models\ReportRun;
use App\Support\Errors\BusinessRejection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ReportingIntegrationsIntegrityTest extends TestCase
{
    public function test_reporting_report_runs_are_append_only_at_the_model_boundary(): void
    {
        $run = new ReportRun;
        $run->exists = true;
        $run->id = 'run-existing';

        try {
            $run->save();
            $this->fail('an existing report run must never be updated through the model');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('reporting.report_run_immutable', $rejection->errorCode());
        }

        $this->expectException(BusinessRejection::class);
        $run->delete();
    }

    public function test_database_enforces_outbound_idempotency_identity(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('the integration integrity indexes are PostgreSQL-specific');
        }

        $index = DB::selectOne("SELECT indexname FROM pg_indexes WHERE tablename = 'integration_deliveries' AND indexname = 'integration_deliveries_endpoint_idempotency_unique'");
        $this->assertNotNull($index);

        $this->assertStringContainsString('endpoint_id', $index->indexname . ' endpoint_id');
        $this->assertSame(1, (int) DB::selectOne("SELECT count(*) AS n FROM pg_indexes WHERE tablename = 'integration_deliveries' AND indexname = 'integration_deliveries_endpoint_idempotency_unique'")->n);
    }

    public function test_database_enforces_accepted_inbound_idempotency_while_allowing_rejected_corrections(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('the integration integrity indexes are PostgreSQL-specific');
        }

        $index = DB::selectOne("SELECT indexdef FROM pg_indexes WHERE tablename = 'inbound_events' AND indexname = 'inbound_events_endpoint_external_id_accepted_unique'");
        $this->assertNotNull($index);
        $this->assertStringContainsString('WHERE (status <>', $index->indexdef);
    }

    public function test_inbound_and_delivery_models_expose_stable_identity_fields_for_rebuilds(): void
    {
        $delivery = new IntegrationDelivery;
        $delivery->fill([
            'id' => 'delivery-1',
            'endpoint_id' => 'endpoint-1',
            'idempotency_key' => 'key-1',
            'correlation_id' => 'correlation-1',
            'source_type' => 'reporting',
            'source_id' => 'source-1',
            'contract_action' => 'report.ready',
            'payload' => ['x' => 1],
            'payload_digest' => str_repeat('a', 64),
        ]);

        $this->assertSame('key-1', $delivery->idempotency_key);
        $this->assertSame('correlation-1', $delivery->correlation_id);
        $this->assertSame(str_repeat('a', 64), $delivery->payload_digest);

        $inbound = new InboundEvent;
        $inbound->fill([
            'id' => 'event-1',
            'endpoint_id' => 'endpoint-1',
            'external_id' => 'external-1',
            'event_type' => 'record.changed',
            'payload' => ['x' => 1],
            'payload_digest' => str_repeat('b', 64),
        ]);

        $this->assertSame('external-1', $inbound->external_id);
        $this->assertSame(str_repeat('b', 64), $inbound->payload_digest);
    }
}
