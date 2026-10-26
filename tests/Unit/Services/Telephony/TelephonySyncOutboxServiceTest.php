<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\TelephonySyncOutbox;
use App\Models\User;
use App\Services\Telephony\LeadService;
use App\Services\Telephony\TelephonySyncOutboxService;
use App\Support\OperationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class TelephonySyncOutboxServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_payload_is_encrypted_and_dispatch_is_idempotent(): void
    {
        Queue::fake();
        $service = app(TelephonySyncOutboxService::class);
        $payload = ['user_id' => 41, 'campaign' => 'mbsales', 'fields' => ['email' => 'customer@example.test']];
        $record = $service->record(
            TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
            'agent-capture:99:lead-fields',
            $payload,
        );

        $this->assertNotSame(json_encode($payload), $record->payload_encrypted);
        $this->assertSame($payload, json_decode(Crypt::decryptString($record->payload_encrypted), true));
        $this->assertSame(1, $service->dispatchPending());
        $this->assertSame(0, $service->dispatchPending());
        Queue::assertPushed(\App\Jobs\ProcessTelephonySyncOutboxJob::class, 1);
    }

    public function test_local_transaction_rollback_also_rolls_back_the_outbox_record(): void
    {
        $service = app(TelephonySyncOutboxService::class);

        try {
            DB::transaction(function () use ($service): void {
                $service->record(
                    TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
                    'agent-capture:100:lead-fields',
                    ['user_id' => 5, 'campaign' => 'mbsales', 'fields' => ['comments' => 'synthetic']],
                );

                throw new \RuntimeException('synthetic local rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('synthetic local rollback', $exception->getMessage());
        }

        $this->assertDatabaseCount('telephony_sync_outbox', 0);
    }

    public function test_successful_remote_sync_is_marked_synced_and_not_dispatched_again(): void
    {
        $user = User::factory()->create();
        $record = app(TelephonySyncOutboxService::class)->record(
            TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
            'agent-capture:101:lead-fields',
            ['user_id' => $user->id, 'campaign' => 'mbsales', 'fields' => ['lead_id' => '123']],
        );

        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('updateFields')->once()->andReturn(OperationResult::success());
        $this->instance(LeadService::class, $leadService);

        app(TelephonySyncOutboxService::class)->process($record->id);
        app(TelephonySyncOutboxService::class)->process($record->id);

        $record->refresh();
        $this->assertSame(TelephonySyncOutbox::STATUS_SYNCED, $record->status);
        $this->assertSame(1, $record->attempt_count);
    }

    public function test_ambiguous_remote_write_is_quarantined_and_never_replayed_automatically(): void
    {
        $user = User::factory()->create();
        $record = app(TelephonySyncOutboxService::class)->record(
            TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
            'agent-capture:102:lead-fields',
            ['user_id' => $user->id, 'campaign' => 'mbsales', 'fields' => ['lead_id' => '123']],
        );

        $leadService = Mockery::mock(LeadService::class);
        $leadService->shouldReceive('updateFields')->once()->andReturn(OperationResult::failure(
            'VICIdial may have received the update.',
            null,
            ['failure_code' => 'VICIDIAL_OUTCOME_UNKNOWN', 'outcome_unknown' => true],
        ));
        $this->instance(LeadService::class, $leadService);

        $service = app(TelephonySyncOutboxService::class);
        $service->process($record->id);
        $service->process($record->id);

        $record->refresh();
        $this->assertSame(TelephonySyncOutbox::STATUS_UNKNOWN, $record->status);
        $this->assertSame('VICIDIAL_OUTCOME_UNKNOWN', $record->last_error_code);
        $this->assertSame(1, $record->attempt_count);
    }

    public function test_worker_loss_after_claim_is_marked_unknown_instead_of_replayed(): void
    {
        $record = app(TelephonySyncOutboxService::class)->record(
            TelephonySyncOutboxService::OPERATION_LEAD_FIELDS,
            'agent-capture:103:lead-fields',
            ['user_id' => 1, 'campaign' => 'mbsales', 'fields' => ['lead_id' => '123']],
        );
        $record->update([
            'status' => TelephonySyncOutbox::STATUS_PROCESSING,
            'claimed_at' => now()->subMinutes(10),
        ]);

        $this->assertSame(1, app(TelephonySyncOutboxService::class)->expireStaleClaims());
        $this->assertSame(TelephonySyncOutbox::STATUS_UNKNOWN, $record->fresh()->status);
        $this->assertSame('WORKER_LOST_AFTER_CLAIM', $record->fresh()->last_error_code);
    }
}
