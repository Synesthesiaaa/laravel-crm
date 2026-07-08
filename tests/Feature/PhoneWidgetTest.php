<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_widget_boots_with_synced_campaign_without_selector(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'crmdefault',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user)
            ->withSession([
                'campaign' => 'crmdefault',
                'campaign_name' => 'CRM Default',
                'vicidial_campaign' => 'softcamp',
            ]);

        $html = view('partials.phone-widget')->render();

        $this->assertStringContainsString('softcamp', $html);
        $this->assertStringNotContainsString('crmdefault', $html);
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

    public function test_phone_widget_password_overrides_discourage_browser_autofill(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'defaultcamp',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user);

        $html = view('partials.phone-widget')->render();

        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="password"[^>]*x-model="vici\.vd_pass"[^>]*autocomplete="new-password"[^>]*data-lpignore="true"/s',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<input[^>]*type="password"[^>]*x-model="vici\.phone_pass"[^>]*autocomplete="new-password"[^>]*data-lpignore="true"/s',
            $html,
        );
    }
}
