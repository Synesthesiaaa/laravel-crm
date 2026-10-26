<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeadTransportSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_add_rejects_transport_and_campaign_overrides_without_remote_request(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        VicidialServer::factory()->create([
            'campaign_code' => 'mbsales',
            'api_url' => 'https://campaign-a.example/agc/api.php',
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
            'is_default' => true,
        ]);
        Http::fake(['*' => Http::response('SUCCESS', 200)]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->postJson(route('api.leads.add'), [
                'phone_number' => '15551234567',
                'phone_code' => '1',
                'list_id' => '123',
                'function' => 'delete_lead',
                'source' => 'untrusted-source',
                'user' => 'untrusted-user',
                'pass' => 'untrusted-password',
                'agent_user' => 'untrusted-agent',
                'campaign_id' => 'OTHER-CAMPAIGN',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['function', 'source', 'user', 'pass', 'agent_user', 'campaign_id']);

        Http::assertNothingSent();
    }
}
