<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_widget_boots_with_crm_campaign_without_selector(): void
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

        $this->assertStringContainsString('crmdefault', $html);
        $this->assertStringNotContainsString('softcamp', $html);
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

    public function test_phone_widget_credentials_have_bound_labels(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'defaultcamp',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user);

        $html = view('partials.phone-widget')->render();

        foreach ([
            'vici-vd-login' => 'VD Login',
            'vici-vd-pass' => 'VD Pass',
            'vici-phone-login' => 'Phone Login',
            'vici-phone-pass' => 'Phone Pass',
        ] as $id => $label) {
            $this->assertStringContainsString('for="'.$id.'"', $html, $label.' label should target its input.');
            $this->assertStringContainsString('id="'.$id.'"', $html, $label.' input should expose the matching id.');
        }
    }

    public function test_phone_widget_closed_shell_has_stable_pre_alpine_geometry_without_hiding_iframe(): void
    {
        $user = User::factory()->create([
            'default_campaign' => 'defaultcamp',
            'extension' => '6001',
            'vici_user' => 'testagent',
        ]);

        $this->actingAs($user);

        $html = view('partials.phone-widget')->render();
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.phone-widget-shell\s*\{[^}]*width:\s*1px;[^}]*height:\s*1px;[^}]*max-width:\s*1px;[^}]*max-height:\s*1px;/s',
            $css,
        );
        $this->assertStringContainsString('overflow-hidden', $html);
        $this->assertMatchesRegularExpression('/<iframe[^>]*id="vici-session-frame"[^>]*src="about:blank"[^>]*>/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<iframe[^>]*id="vici-session-frame"[^>]*x-show=/s', $html);
        $this->assertStringContainsString('style="min-width: 1px; min-height: 1px;"', $html);
    }
}
