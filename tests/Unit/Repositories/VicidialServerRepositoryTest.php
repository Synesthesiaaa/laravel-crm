<?php

namespace Tests\Unit\Repositories;

use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VicidialServerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_selects_the_default_server_within_the_requested_campaign(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A Priority Server',
            'priority' => 1,
        ]);
        $default = VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A Default Server',
            'is_default' => true,
            'priority' => 99,
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-b',
            'server_name' => 'Campaign B Default Server',
            'is_default' => true,
        ]);

        $server = (new VicidialServerRepository)->getForCampaign('campaign-a');

        $this->assertNotNull($server);
        $this->assertSame($default->id, $server->id);
    }

    public function test_it_uses_priority_only_within_the_requested_campaign_when_no_default_exists(): void
    {
        $selected = VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A Priority Server',
            'priority' => 1,
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A Secondary Server',
            'priority' => 5,
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-b',
            'server_name' => 'Campaign B Default Server',
            'is_default' => true,
            'priority' => 0,
        ]);

        $server = (new VicidialServerRepository)->getForCampaign('campaign-a');

        $this->assertNotNull($server);
        $this->assertSame($selected->id, $server->id);
    }

    public function test_it_does_not_fall_back_to_a_server_assigned_to_another_campaign(): void
    {
        VicidialServer::factory()->create([
            'campaign_code' => 'campaign-a',
            'server_name' => 'Campaign A Default Server',
            'is_default' => true,
            'api_user' => 'campaign-a-user',
            'api_pass' => 'campaign-a-pass',
        ]);

        $server = (new VicidialServerRepository)->getForCampaign('campaign-b');

        $this->assertNull($server);
    }
}
