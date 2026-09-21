<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\CallSession;
use App\Models\User;
use App\Models\VicidialAgentSession;
use App\Repositories\VicidialServerRepository;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialDispositionSyncService;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VicidialDispositionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_call_without_lead_still_advances_disposition_and_resumes_previously_ready_agent(): void
    {
        $user = User::factory()->create(['vici_user' => 'agent1', 'vici_pass' => 'secret']);
        $session = CallSession::factory()->completed()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => null,
            'disposition_code' => 'SALE',
            'metadata' => ['vicidial_session_status_before_call' => 'ready'],
        ]);
        VicidialAgentSession::create([
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'session_status' => 'paused',
        ]);

        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldNotReceive('getForCampaign');
        $proxy = Mockery::mock(VicidialProxyService::class);
        $proxy->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(fn (User $candidate) => $candidate->is($user)), 'mbsales', 'external_status', ['value' => 'SALE'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $proxy->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(fn (User $candidate) => $candidate->is($user)), 'mbsales', 'external_pause', ['value' => 'RESUME'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $logger = Mockery::mock(TelephonyLogger::class);

        (new VicidialDispositionSyncService($repository, $proxy, $logger))
            ->syncDispositionToVicidial($session);

        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'session_status' => 'ready',
        ]);
    }

    public function test_previously_paused_agent_is_not_auto_resumed_after_disposition(): void
    {
        $user = User::factory()->create(['vici_user' => 'agent1', 'vici_pass' => 'secret']);
        $session = CallSession::factory()->completed()->for($user)->create([
            'campaign_code' => 'mbsales',
            'lead_id' => null,
            'disposition_code' => 'SALE',
            'metadata' => ['vicidial_session_status_before_call' => 'paused'],
        ]);
        VicidialAgentSession::create([
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'session_status' => 'paused',
        ]);

        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldNotReceive('getForCampaign');
        $proxy = Mockery::mock(VicidialProxyService::class);
        $proxy->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(User::class), 'mbsales', 'external_status', ['value' => 'SALE'])
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS', 'message' => null]);
        $proxy->shouldNotReceive('execute')->with(Mockery::any(), Mockery::any(), 'external_pause', Mockery::any());
        $logger = Mockery::mock(TelephonyLogger::class);

        (new VicidialDispositionSyncService($repository, $proxy, $logger))
            ->syncDispositionToVicidial($session);

        $this->assertDatabaseHas('vicidial_agent_sessions', [
            'user_id' => $user->id,
            'campaign_code' => 'mbsales',
            'session_status' => 'paused',
        ]);
    }
}
