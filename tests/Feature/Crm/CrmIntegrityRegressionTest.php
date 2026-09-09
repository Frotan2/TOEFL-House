<?php

declare(strict_types=1);

namespace Tests\Feature\Crm;

use App\Modules\Crm\Commands\CaptureVisitor;
use App\Modules\Crm\Commands\CreateVisitorFollowup;
use App\Modules\Crm\Commands\ManageVisitorFollowup;
use App\Modules\Crm\Models\Visitor;
use App\Modules\Crm\Models\VisitorFollowup;
use App\Support\Errors\BusinessRejection;
use Carbon\CarbonImmutable;
use Tests\Concerns\BuildsActors;
use Tests\TestCase;

final class CrmIntegrityRegressionTest extends TestCase
{
    use BuildsActors;

    public function test_visitor_capture_idempotency_hash_covers_notes(): void
    {
        $actor = $this->actorWithStructureCapabilities('crm-idem-notes', ['crm.visitor']);
        $key = 'crm-capture-notes-1';

        $first = app(CaptureVisitor::class)->capture(
            $actor,
            null,
            'Idempotent Lead',
            null,
            'idempotent-notes@example.com',
            'email',
            'online',
            null,
            null,
            null,
            'TOEFL',
            'first evidence',
            $key,
        );
        $repeat = app(CaptureVisitor::class)->capture(
            $actor,
            null,
            'Idempotent Lead',
            null,
            'idempotent-notes@example.com',
            'email',
            'online',
            null,
            null,
            null,
            'TOEFL',
            'first evidence',
            $key,
        );

        $this->assertSame($first['visitor_id'], $repeat['visitor_id']);
        $this->assertSame(1, Visitor::query()->where('id', $first['visitor_id'])->count());

        try {
            app(CaptureVisitor::class)->capture(
                $actor,
                null,
                'Idempotent Lead',
                null,
                'idempotent-notes@example.com',
                'email',
                'online',
                null,
                null,
                null,
                'TOEFL',
                'changed evidence',
                $key,
            );
            $this->fail('reusing a visitor capture idempotency key with different notes must be rejected');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('idempotency.conflicting_payload', $rejection->errorCode());
        }
    }

    public function test_followup_transition_is_serialized_against_competing_status_writers(): void
    {
        $actor = $this->actorWithStructureCapabilities('crm-followup-lock-order', ['crm.visitor', 'crm.followup']);
        $capture = app(CaptureVisitor::class)->capture(
            $actor,
            null,
            'Followup Concurrency Lead',
            null,
            'followup-concurrency@example.com',
            'email',
            'online',
            null,
            null,
            null,
            null,
            null,
            'crm-followup-concurrency-capture',
        );
        $visitor = Visitor::query()->findOrFail($capture['visitor_id']);
        $followup = app(CreateVisitorFollowup::class)->create(
            $actor,
            $visitor,
            $actor->actorId,
            CarbonImmutable::tomorrow(),
            'Contact the lead',
            null,
            'crm-followup-concurrency-create',
        );

        $completed = app(ManageVisitorFollowup::class)->complete(
            $actor,
            VisitorFollowup::query()->findOrFail($followup['followup_id']),
            'crm-followup-concurrency-complete',
        );
        $this->assertSame(VisitorFollowup::STATUS_DONE, $completed['status']);

        try {
            app(ManageVisitorFollowup::class)->cancel(
                $actor,
                VisitorFollowup::query()->findOrFail($followup['followup_id']),
                'crm-followup-concurrency-cancel',
            );
            $this->fail('a second competing follow-up writer must not overwrite a committed terminal state');
        } catch (BusinessRejection $rejection) {
            $this->assertSame('crm.followup_invalid_transition', $rejection->errorCode());
        }
    }
}
