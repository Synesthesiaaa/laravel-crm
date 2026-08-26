<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\VicidialServer;
use App\Services\Telephony\TelephonyLogger;
use App\Services\Telephony\VicidialCredentialSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class VicidialCredentialSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_each_server_using_that_servers_non_agent_endpoint(): void
    {
        config(['vicidial.non_agent_api_url' => 'https://global.example/non_agent_api.php']);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-b',
            'api_url' => 'https://campaign-b.example/agc/api.php',
            'api_user' => 'campaign-b-user',
            'api_pass' => 'campaign-b-pass',
        ]);
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $service = new VicidialCredentialSyncService(Mockery::mock(TelephonyLogger::class)->shouldIgnoreMissing());
        $service->syncOnCreate(User::factory()->make([
            'vici_user' => 'agent-1',
            'vici_pass' => 'agent-pass',
            'extension' => null,
        ]));

        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://campaign-a.example/non_agent_api.php');
        });
        Http::assertSent(function ($request): bool {
            return str_starts_with($request->url(), 'https://campaign-b.example/non_agent_api.php');
        });
    }
}
