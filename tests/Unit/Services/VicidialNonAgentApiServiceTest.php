<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialNonAgentApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class VicidialNonAgentApiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_campaign_server_endpoint_when_a_global_override_is_configured(): void
    {
        config(['vicidial.non_agent_api_url' => 'https://global.example/non_agent_api.php']);

        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
            'is_default' => true,
        ]);
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $service = new VicidialNonAgentApiService(
            app(VicidialServerRepository::class),
            Mockery::mock(TelephonyLogger::class),
        );

        $result = $service->execute(User::factory()->make(), 'campaign-a', 'agent_status');

        $this->assertTrue($result->success);
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php');
        });
    }

    public function test_it_prefers_a_campaign_servers_explicit_non_agent_endpoint(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://agent-a.example/agc/api.php',
            'non_agent_api_url' => 'https://reports-a.example/vicidial/non_agent_api.php',
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
            'is_default' => true,
        ]);
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $service = new VicidialNonAgentApiService(
            app(VicidialServerRepository::class),
            Mockery::mock(TelephonyLogger::class),
        );

        $result = $service->execute(User::factory()->make(), 'campaign-a', 'agent_status');

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://reports-a.example/vicidial/non_agent_api.php'));
        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://agent-a.example/'));
    }

    public function test_it_batches_named_reports_on_one_campaign_server_and_preserves_independent_failures(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
            'is_default' => true,
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-b',
            'api_url' => 'https://campaign-b.example/agc/api.php',
            'api_user' => 'campaign-b-user',
            'api_pass' => 'campaign-b-pass',
            'is_default' => true,
        ]);
        Http::fake(function ($request) {
            return ($request->data()['function'] ?? null) === 'call_status_stats'
                ? Http::response('ERROR: report access denied', 200)
                : Http::response("user|status\nagent-a|READY", 200);
        });

        $service = new VicidialNonAgentApiService(
            app(VicidialServerRepository::class),
            Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing(),
        );

        $results = $service->executeBatch(User::factory()->make(), 'campaign-a', [
            'agents' => [
                'function' => 'logged_in_agents',
                'params' => ['header' => 'YES'],
            ],
            'totals' => [
                'function' => 'call_status_stats',
                'params' => ['campaigns' => '---ALL---'],
            ],
        ], true, ['connect_timeout' => 1, 'timeout' => 3, 'retry_times' => 0]);

        $this->assertTrue($results['agents']->success);
        $this->assertFalse($results['totals']->success);
        $this->assertSame('agent-a', $results['agents']->data['rows'][1][0]);
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php')
                && ($request->data()['user'] ?? null) === 'campaign-a-user'
                && ($request->data()['pass'] ?? null) === 'campaign-a-pass';
        });
        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'https://campaign-b.example/'));
    }

    public function test_it_redacts_non_agent_api_credentials_from_connection_error_telemetry(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'report-user',
            'api_pass' => 'report-password',
            'is_default' => true,
        ]);
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7 for https://campaign-a.example/non_agent_api.php?user=report-user&pass=report-password&function=version',
        ));
        $logger = Mockery::mock(TelephonyLogger::class);
        $logger->shouldReceive('error')
            ->once()
            ->withArgs(function (string $component, string $message, array $context): bool {
                return $component === 'VicidialNonAgentApiService'
                    && $message === 'HTTP request failed'
                    && str_contains((string) ($context['error'] ?? ''), 'user=[redacted]')
                    && str_contains((string) ($context['error'] ?? ''), 'pass=[redacted]')
                    && ! str_contains((string) ($context['error'] ?? ''), 'report-user')
                    && ! str_contains((string) ($context['error'] ?? ''), 'report-password');
            });

        $service = new VicidialNonAgentApiService(app(VicidialServerRepository::class), $logger);

        $result = $service->execute(User::factory()->make(), 'campaign-a', 'version');

        $this->assertFalse($result->success);
        $this->assertSame('Unable to reach VICIdial Non-Agent API.', $result->message);
    }
}
