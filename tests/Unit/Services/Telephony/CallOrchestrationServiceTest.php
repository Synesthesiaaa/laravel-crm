<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\CallSession;
use App\Models\User;
use App\Services\DispositionService;
use App\Services\Telephony\CallOrchestrationService;
use App\Services\Telephony\CallStateService;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CallOrchestrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['vicidial.require_vicidial_agent_session_before_dial' => false]);
        Queue::fake();
    }

    public function test_second_request_does_not_issue_another_external_dial(): void
    {
        $user = User::factory()->create([
            'vici_user' => 'agent-1',
            'vici_pass' => 'test-password',
        ]);

        $proxy = Mockery::mock(VicidialProxyService::class);
        $proxy->shouldReceive('execute')
            ->once()
            ->with($user, 'mbsales', 'external_dial', Mockery::type('array'))
            ->andReturn(['success' => true, 'raw_response' => 'SUCCESS']);
        $this->instance(VicidialProxyService::class, $proxy);

        $service = app(CallOrchestrationService::class);
        $first = $service->startOutboundCall($user, 'mbsales', '15551234567', 123);
        $second = $service->startOutboundCall($user, 'mbsales', '15557654321', 456);

        $this->assertTrue($first->success);
        $this->assertFalse($second->success);
        $this->assertDatabaseCount('call_sessions', 1);
        $this->assertDatabaseHas('call_sessions', [
            'id' => $first->data['session_id'],
            'status' => CallSession::STATUS_RINGING,
        ]);
    }

    public function test_locked_reservation_recheck_blocks_an_active_session(): void
    {
        $user = User::factory()->create([
            'vici_user' => 'agent-2',
            'vici_pass' => 'test-password',
        ]);
        $activeSession = CallSession::factory()->for($user)->ringing()->create();
        $this->assertDatabaseHas('call_sessions', [
            'id' => $activeSession->id,
            'user_id' => $user->id,
            'status' => CallSession::STATUS_RINGING,
        ]);
        $this->assertTrue(CallSession::query()->where('user_id', $user->id)->active()->lockForUpdate()->get(['id'])->isNotEmpty());

        $proxy = Mockery::mock(VicidialProxyService::class);
        $proxy->shouldNotReceive('execute');
        $this->instance(VicidialProxyService::class, $proxy);

        $service = new class($proxy, app(CallStateService::class), app(DispositionService::class), app(TelephonyLogger::class)) extends CallOrchestrationService
        {
            public function getActiveSession(User $user): ?CallSession
            {
                return null;
            }
        };

        $result = $service->startOutboundCall($user, 'mbsales', '15551234567');

        $this->assertFalse($result->success);
        $this->assertDatabaseCount('call_sessions', 1);
    }

    public function test_uncertain_dial_keeps_the_session_reserved_and_does_not_issue_a_second_dial(): void
    {
        $user = User::factory()->create([
            'vici_user' => 'agent-3',
            'vici_pass' => 'test-password',
        ]);

        $proxy = Mockery::mock(VicidialProxyService::class);
        $proxy->shouldReceive('execute')
            ->once()
            ->with($user, 'mbsales', 'external_dial', Mockery::type('array'))
            ->andReturn([
                'success' => false,
                'raw_response' => '',
                'message' => 'VICIdial may have received the dial request.',
                'failure_code' => 'VICIDIAL_OUTCOME_UNKNOWN',
                'outcome_unknown' => true,
            ]);
        $this->instance(VicidialProxyService::class, $proxy);

        $service = app(CallOrchestrationService::class);
        $first = $service->startOutboundCall($user, 'mbsales', '15551234567');
        $second = $service->startOutboundCall($user, 'mbsales', '15557654321');

        $this->assertFalse($first->success);
        $this->assertTrue($first->meta['outcome_unknown']);
        $this->assertFalse($second->success);
        $this->assertDatabaseCount('call_sessions', 1);
        $this->assertDatabaseHas('call_sessions', [
            'id' => $first->data['session_id'],
            'status' => CallSession::STATUS_DIALING,
            'remote_dial_outcome' => 'unknown',
        ]);
    }
}
