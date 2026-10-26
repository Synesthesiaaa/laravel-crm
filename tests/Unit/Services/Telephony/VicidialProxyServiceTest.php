<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
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
        $this->assertSame('VICIDIAL_OUTCOME_UNKNOWN', $result['failure_code']);
        $this->assertTrue($result['outcome_unknown']);
    }

    public function test_external_dial_http_failure_without_a_definitive_api_error_has_unknown_outcome(): void
    {
        $server = new VicidialServer([
            'campaign_code' => 'mbsales',
            'api_url' => 'https://vicidial.test/agc/api.php',
            'source' => 'crm_tracker',
        ]);
        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldReceive('getForCampaign')->once()->with('mbsales')->andReturn($server);
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();
        Http::fake(['*' => Http::response('', 502)]);

        $result = (new VicidialProxyService($repository, $logger))->execute(
            new User(['vici_user' => 'agent1', 'vici_pass' => 'synthetic-secret']),
            'mbsales',
            'external_dial',
            ['phone_number' => '15551234567'],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('VICIDIAL_OUTCOME_UNKNOWN', $result['failure_code']);
        $this->assertTrue($result['outcome_unknown']);
    }

    public function test_agent_api_rejects_reserved_query_parameters_before_sending_a_request(): void
    {
        $server = new VicidialServer([
            'campaign_code' => 'mbsales',
            'api_url' => 'https://vicidial.test/agc/api.php',
            'source' => 'crm_tracker',
        ]);
        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldNotReceive('getForCampaign');
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $user = new User([
            'vici_user' => 'agent1',
            'vici_pass' => 'synthetic-agent-secret',
        ]);

        $result = (new VicidialProxyService($repository, $logger))->execute(
            $user,
            'mbsales',
            'update_fields',
            [
                'query' => [
                    'function' => 'delete_lead',
                    'source' => 'untrusted-source',
                    'user' => 'untrusted-user',
                    'pass' => 'untrusted-password',
                    'agent_user' => 'untrusted-agent',
                ],
            ],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('INVALID_VICIDIAL_PARAMETERS', $result['failure_code']);
        Http::assertNothingSent();
    }

    public function test_supervisor_target_agent_is_separate_from_authenticated_api_identity(): void
    {
        $server = new VicidialServer([
            'campaign_code' => 'mbsales',
            'api_url' => 'https://vicidial.test/agc/api.php',
            'source' => 'crm_tracker',
        ]);
        $repository = Mockery::mock(VicidialServerRepository::class);
        $repository->shouldReceive('getForCampaign')->once()->with('mbsales')->andReturn($server);
        $logger = Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing();
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $result = (new VicidialProxyService($repository, $logger))->execute(
            new User(['vici_user' => 'supervisor-api-user', 'vici_pass' => 'synthetic-secret']),
            'mbsales',
            'blind_monitor',
            [
                'target_agent_user' => 'selected-agent',
                'query' => ['stage' => 'MONITOR'],
            ],
        );

        $this->assertTrue($result['success']);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['user'] ?? null) === 'supervisor-api-user'
                && ($query['pass'] ?? null) === 'synthetic-secret'
                && ($query['agent_user'] ?? null) === 'selected-agent'
                && ($query['function'] ?? null) === 'blind_monitor'
                && ($query['stage'] ?? null) === 'MONITOR';
        });
    }
}
