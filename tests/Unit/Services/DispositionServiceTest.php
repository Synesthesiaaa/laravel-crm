<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessTelephonySyncOutboxJob;
use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\TelephonySyncOutbox;
use App\Models\User;
use App\Services\DispositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DispositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Resolve through the container so repository, call-state, and logging
        // dependencies use the same wiring as the application.
        $this->service = $this->app->make(DispositionService::class);
    }

    public function test_get_codes_returns_empty_for_unknown_campaign(): void
    {
        $codes = $this->service->getCodesForCampaign('nonexistent');
        $this->assertEmpty($codes);
    }

    public function test_get_codes_returns_active_codes_for_campaign(): void
    {
        DispositionCode::create([
            'campaign_code' => 'test',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        DispositionCode::create([
            'campaign_code' => 'test',
            'code' => 'DNC',
            'label' => 'Do Not Call',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $codes = $this->service->getCodesForCampaign('test');
        $this->assertCount(1, $codes);
        $this->assertEquals('SALE', $codes[0]['code']);
    }

    public function test_save_disposition_defers_vicidial_sync_until_after_the_request(): void
    {
        Queue::fake();

        $user = User::factory()->create(['username' => 'agent1']);
        $session = CallSession::factory()->completed()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => 123,
        ]);
        DispositionCode::create([
            'campaign_code' => 'mbsales',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = $this->app->make(DispositionService::class);

        $result = $service->saveDisposition(
            'mbsales',
            'agent1',
            'SALE',
            'Sale',
            $user->id,
            $session->id,
            123,
            '15551234567',
        );

        $this->assertTrue($result->success);
        $outbox = TelephonySyncOutbox::query()->where('idempotency_key', 'disposition:call-session:'.$session->id)->firstOrFail();
        $this->assertSame(TelephonySyncOutbox::STATUS_PENDING, $outbox->status);
        Queue::assertPushed(ProcessTelephonySyncOutboxJob::class, fn (ProcessTelephonySyncOutboxJob $job): bool => $job->outboxId === $outbox->id);
    }
}
