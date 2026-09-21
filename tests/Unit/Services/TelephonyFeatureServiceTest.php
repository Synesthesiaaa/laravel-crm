<?php

namespace Tests\Unit\Services;

use App\Services\TelephonyFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelephonyFeatureServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_screen_access_is_disabled_when_no_setting_exists(): void
    {
        $service = app(TelephonyFeatureService::class);
        $service->flush();

        $this->assertFalse($service->isEnabled('agent_screen_access'));
    }

    public function test_agent_screen_access_can_be_enabled_and_persisted(): void
    {
        $service = app(TelephonyFeatureService::class);

        $service->updateMany(['agent_screen_access' => '1']);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);
        $this->assertTrue($service->isEnabled('agent_screen_access'));
    }
}
