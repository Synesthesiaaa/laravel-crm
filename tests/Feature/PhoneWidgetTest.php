<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_widget_boots_with_session_campaign_without_selector(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'fallbackcamp',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user)
            ->withSession([
                'vicidial_campaign' => 'sessioncamp',
            ]);

        $html = view('partials.phone-widget')->render();

        $this->assertStringContainsString('sessioncamp', $html);
        $this->assertStringNotContainsString('Login campaign', $html);
        $this->assertStringNotContainsString('/api/vicidial/session/agent-campaigns', $html);
        $this->assertStringNotContainsString('/api/vicidial/session/select-campaign', $html);
    }

    public function test_phone_widget_falls_back_to_user_default_campaign_without_selector(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'defaultcamp',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user);

        $html = view('partials.phone-widget')->render();

        $this->assertStringContainsString('defaultcamp', $html);
        $this->assertStringNotContainsString('Login campaign', $html);
        $this->assertStringNotContainsString('/api/vicidial/session/agent-campaigns', $html);
        $this->assertStringNotContainsString('/api/vicidial/session/select-campaign', $html);
    }
}
