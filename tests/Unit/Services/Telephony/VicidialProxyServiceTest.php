<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class VicidialProxyServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_external_dial_does_not_retry_or_throw_when_transport_is_unavailable(): void
    {
        config([
            'vicidial.retry_times' => 3,
            'vicidial.agent_api_timeout' => 4,
            'vicidial.agent_connect_timeout' => 2,
        ]);

        $server = new VicidialServer([
            'campaign_code' => 'mbsales',
            'api_url' => 'http://vicidial.test/agc/api.php',
            'source' => 'crm_tracker',
        ]);
        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldReceive('getForCampaign')->once()->with('mbsales')->andReturn($server);
        $logger = Mockery::mock(TelephonyLogger::class);
        $logger->shouldReceive('warning')->atLeast()->once();

        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('connection failed');
        });

        $user = new User([
            'vici_user' => 'agent1',
            'vici_pass' => 'secret',
        ]);

        $result = (new VicidialProxyService($repository, $logger))->execute(
            $user,
            'mbsales',
            'external_dial',
            ['value' => '15551234567', 'phone_number' => '15551234567'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame(1, $attempts);
        $this->assertSame('VICIDIAL_UNAVAILABLE', $result['failure_code']);
    }
}
