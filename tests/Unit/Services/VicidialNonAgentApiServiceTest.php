<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialNonAgentApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
