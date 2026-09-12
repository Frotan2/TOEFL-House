<?php

declare(strict_types=1);

namespace Tests\Canonical\Resources;

use App\Modules\Resources\Commands\MaintainWorkOrder;
use App\Modules\Resources\Models\WorkOrder;
use App\Support\Authorization\Actor;
use App\Support\Errors\AuthorizationDenied;
use App\Support\Errors\BusinessRejection;
use App\Support\Identifiers\RandomIdentifier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Canonical\CanonicalTestCase;

/**
 * Canonical facilities work lifecycle: request → independent approval →
 * in progress → completion with mandatory evidence, cancellation from any
 * non-terminal state, terminal immutability at the database boundary, and
 * audited denial of self-approval and capability-less transitions.
 */
final class FacilitiesWorkOrderLifecycleTest extends CanonicalTestCase
{
    private Actor $requester;

    private Actor $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requester = $this->actorWith('canon-work-requester', ['facilities.work', 'facilities.work_approve']);
        $this->approver = $this->actorWith('canon-work-approver', ['facilities.work_approve']);
    }

    private function requestOrder(string $key, string $facility = 'Library HVAC'): WorkOrder
    {
        $orderId = app(MaintainWorkOrder::class)->request(
            $this->requester, $facility, 'Replace filter', $this->sharedBranchId(), $key
        )['work_order_id'];

        return WorkOrder::query()->findOrFail($orderId);
    }

    public function test_the_full_lifecycle_persists_states_evidence_and_events(): void
    {
        $requested = app(MaintainWorkOrder::class)->request($this->requester, 'Library HVAC', 'Replace filter', $this->sharedBranchId(), 'canon-work-request-1');
        $order = WorkOrder::query()->findOrFail($requested['work_order_id']);
        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'requested', 'requested_by' => 'canon-work-requester']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $requested['correlation_id']]);

        $approved = app(MaintainWorkOrder::class)->approve($this->approver, $order, 'canon-work-approve-1');
        $this->assertSame('approved', $approved['lifecycle_state']);
        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'approved', 'approved_by' => 'canon-work-approver']);

        $started = app(MaintainWorkOrder::class)->start($this->requester, $order->refresh(), 'canon-work-start-1');
        $this->assertSame('in_progress', $started['lifecycle_state']);

        try {
            app(MaintainWorkOrder::class)->complete($this->requester, $order->refresh(), '', 'canon-work-complete-empty');
            $this->fail('completion requires work evidence');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('resources.work_evidence', $rejection->errorCode());
        }

        $completed = app(MaintainWorkOrder::class)->complete($this->requester, $order->refresh(), 'evidence/library/hvac-1', 'canon-work-complete-1');
        $this->assertSame('completed', $completed['lifecycle_state']);
        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'completed', 'evidence_ref' => 'evidence/library/hvac-1']);
        $this->assertDatabaseHas('domain_events', ['correlation_id' => $completed['correlation_id']]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'resources.work.request', 'target_id' => $order->id]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'resources.work.approve', 'actor_id' => 'canon-work-approver', 'target_id' => $order->id]);
        $this->assertDatabaseHas('audit_events', ['operation' => 'resources.work.complete', 'target_id' => $order->id]);
    }

    public function test_self_approval_is_denied_and_only_audited(): void
    {
        $order = $this->requestOrder('canon-work-request-2');

        try {
            app(MaintainWorkOrder::class)->approve($this->requester, $order, 'canon-work-approve-self');
            $this->fail('the approver must differ from the requester');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.work_not_independent', $denial->errorCode());
        }

        $denialEvent = DB::table('audit_events')
            ->where('operation', 'resources.work.approve.denied')
            ->where('actor_id', 'canon-work-requester')
            ->first();
        $this->assertNotNull($denialEvent);
        $this->assertSame(0, DB::table('domain_events')->where('audit_event_id', $denialEvent->id)->count());
        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'requested']);
    }

    public function test_capability_gates_every_transition(): void
    {
        $order = $this->requestOrder('canon-work-request-3');
        $stranger = $this->actorWith('canon-work-stranger', ['resources.books']);

        try {
            app(MaintainWorkOrder::class)->approve($stranger, $order, 'canon-work-approve-stranger');
            $this->fail('approval requires facilities.work_approve');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.work_denied', $denial->errorCode());
        }

        try {
            app(MaintainWorkOrder::class)->start($stranger, $order, 'canon-work-start-stranger');
            $this->fail('starting requires facilities.work');
        } catch (AuthorizationDenied $denial) {
            $this->assertSame('resources.work_denied', $denial->errorCode());
        }

        $this->assertDatabaseHas('audit_events', ['operation' => 'resources.work.approve.denied', 'actor_id' => 'canon-work-stranger']);
        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'requested']);
    }

    public function test_cancellation_is_available_from_every_non_terminal_state(): void
    {
        $fromRequested = $this->requestOrder('canon-work-request-4', 'Boiler room');
        $cancelled = app(MaintainWorkOrder::class)->cancel($this->approver, $fromRequested, 'canon-work-cancel-1');
        $this->assertSame('cancelled', $cancelled['lifecycle_state']);

        $fromApproved = $this->requestOrder('canon-work-request-5', 'Roof leak');
        app(MaintainWorkOrder::class)->approve($this->approver, $fromApproved, 'canon-work-approve-5');
        $this->assertSame('cancelled', app(MaintainWorkOrder::class)->cancel($this->approver, $fromApproved->refresh(), 'canon-work-cancel-2')['lifecycle_state']);

        $fromProgress = $this->requestOrder('canon-work-request-6', 'Wiring');
        app(MaintainWorkOrder::class)->approve($this->approver, $fromProgress, 'canon-work-approve-6');
        app(MaintainWorkOrder::class)->start($this->requester, $fromProgress->refresh(), 'canon-work-start-6');
        $this->assertSame('cancelled', app(MaintainWorkOrder::class)->cancel($this->approver, $fromProgress->refresh(), 'canon-work-cancel-3')['lifecycle_state']);

        $this->assertDatabaseHas('work_orders', ['id' => $fromRequested->id, 'lifecycle_state' => 'cancelled']);
        $this->assertDatabaseHas('work_orders', ['id' => $fromApproved->id, 'lifecycle_state' => 'cancelled']);
        $this->assertDatabaseHas('work_orders', ['id' => $fromProgress->id, 'lifecycle_state' => 'cancelled']);
    }

    public function test_terminal_states_refuse_every_further_transition(): void
    {
        $order = $this->requestOrder('canon-work-request-7', 'Elevator service');
        app(MaintainWorkOrder::class)->approve($this->approver, $order, 'canon-work-approve-7');
        app(MaintainWorkOrder::class)->start($this->requester, $order->refresh(), 'canon-work-start-7');
        app(MaintainWorkOrder::class)->complete($this->requester, $order->refresh(), 'evidence/library/elevator-1', 'canon-work-complete-7');
        $order->refresh();

        foreach ([
            'start' => fn (): array => app(MaintainWorkOrder::class)->start($this->requester, $order, 'canon-work-start-again'),
            'complete' => fn (): array => app(MaintainWorkOrder::class)->complete($this->requester, $order, 'evidence/again', 'canon-work-complete-again'),
            'cancel' => fn (): array => app(MaintainWorkOrder::class)->cancel($this->approver, $order, 'canon-work-cancel-again'),
        ] as $verb => $call) {
            try {
                $call();
                $this->fail("a completed work order cannot {$verb}");
            } catch (BusinessRejection $rejection) {
                $this->assertSame('resources.work_transition_forbidden', $rejection->errorCode());
            }
        }

        $this->assertDatabaseHas('work_orders', ['id' => $order->id, 'lifecycle_state' => 'completed', 'evidence_ref' => 'evidence/library/elevator-1']);
    }

    public function test_the_database_refuses_state_jumps(): void
    {
        $order = $this->requestOrder('canon-work-request-8', 'Window repair');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('invalid work order transition');
        DB::table('work_orders')->where('id', $order->id)->update(['lifecycle_state' => 'completed', 'evidence_ref' => 'x', 'updated_at' => now()]);
    }

    public function test_the_database_requires_evidence_for_completion(): void
    {
        $order = $this->requestOrder('canon-work-request-9', 'Window frame repair');
        app(MaintainWorkOrder::class)->approve($this->approver, $order, 'canon-work-approve-db1');
        app(MaintainWorkOrder::class)->start($this->requester, $order->refresh(), 'canon-work-start-db1');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('completed work order requires evidence');
        DB::table('work_orders')->where('id', $order->id)->update(['lifecycle_state' => 'completed', 'updated_at' => now()]);
    }

    public function test_completed_work_order_history_is_immutable_at_the_database(): void
    {
        $order = $this->requestOrder('canon-work-request-10', 'Glass replacement');
        app(MaintainWorkOrder::class)->approve($this->approver, $order, 'canon-work-approve-db2');
        app(MaintainWorkOrder::class)->start($this->requester, $order->refresh(), 'canon-work-start-db2');
        app(MaintainWorkOrder::class)->complete($this->requester, $order->refresh(), 'evidence/final', 'canon-work-complete-db2');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('completed or cancelled work orders are retained history');
        DB::table('work_orders')->where('id', $order->id)->update(['evidence_ref' => 'evidence/rewritten', 'updated_at' => now()]);
    }

    public function test_completed_work_orders_cannot_be_deleted(): void
    {
        $order = $this->requestOrder('canon-work-request-11', 'Frame sealing');
        app(MaintainWorkOrder::class)->approve($this->approver, $order, 'canon-work-approve-db3');
        app(MaintainWorkOrder::class)->start($this->requester, $order->refresh(), 'canon-work-start-db3');
        app(MaintainWorkOrder::class)->complete($this->requester, $order->refresh(), 'evidence/seal', 'canon-work-complete-db3');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('work order history cannot be deleted');
        DB::table('work_orders')->where('id', $order->id)->delete();
    }

    public function test_work_orders_carry_immutable_root_provenance(): void
    {
        $order = $this->requestOrder('canon-work-request-9', 'Door lock');
        $this->assertDatabaseHas('work_orders', [
            'id' => $order->id,
            'originating_branch_id' => $this->sharedBranchId(),
        ]);
        $this->assertNotNull($order->organization_id);

        $this->expectException(QueryException::class);
        DB::table('work_orders')->where('id', $order->id)->update(['originating_branch_id' => RandomIdentifier::new()]);
    }
}
