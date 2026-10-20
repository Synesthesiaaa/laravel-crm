<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class MinimalistCrmInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_workspace_default_to_light_and_preserve_saved_dark_preference(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('class="crm-interface"', false)
            ->assertSee("localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'", false);

        $this->workspace(User::ROLE_AGENT)->assertOk()
            ->assertSee('class="crm-interface"', false)
            ->assertSee("localStorage.getItem('theme') === 'dark' ? 'dark' : 'light'", false);
    }

    public function test_search_is_available_without_a_mobile_hiding_utility_and_has_a_labelled_dialog(): void
    {
        $response = $this->workspace(User::ROLE_AGENT)->assertOk();
        $response->assertSee('class="workspace-search"', false)
            ->assertSee('Search workspace')
            ->assertSee('aria-label="Search records, leads, and users"', false)
            ->assertSee('aria-modal="true"', false);

        preg_match('/<button[^>]*class="workspace-search"[^>]*>/s', $response->getContent(), $button);
        $this->assertNotEmpty($button);
        $this->assertStringNotContainsString('hidden', $button[0]);
        $this->assertStringContainsString('aria-label="Open global search"', $button[0]);
    }

    public function test_campaign_context_and_active_navigation_do_not_expose_administration_to_agents(): void
    {
        $this->workspace(User::ROLE_AGENT)->assertOk()
            ->assertSee('Current campaign')
            ->assertSee('MB Sales')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Toggle Administration navigation')
            ->assertDontSee('Toggle Super Admin navigation');
    }

    public function test_super_admin_retains_management_navigation_and_dashboard_actions(): void
    {
        $this->workspace(User::ROLE_SUPER_ADMIN)->assertOk()
            ->assertSee('Toggle Administration navigation')
            ->assertSee('Toggle Super Admin navigation')
            ->assertSee('Your workspace')
            ->assertSee('href="'.route('records.index').'"', false);
    }

    public function test_linked_statistics_use_native_keyboard_accessible_links(): void
    {
        $html = Blade::render('<x-stat-card label="Records" value="12" href="/records" />');

        $this->assertMatchesRegularExpression('/<a href="\/records"[^>]*class="stat-card/', $html);
        $this->assertStringNotContainsString('onclick=', $html);
    }

    private function workspace(string $role): \Illuminate\Testing\TestResponse
    {
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);

        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));
    }
}
