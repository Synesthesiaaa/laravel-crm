<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

final class CrmInterfaceOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        view()->share('errors', new ViewErrorBag);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_shared_form_help_is_programmatically_associated_with_controls(): void
    {
        $viewData = ['errors' => new ViewErrorBag];
        $input = Blade::render('<x-form.input name="phone" label="Phone" help="Use E.164 format." />', $viewData);
        $select = Blade::render('<x-form.select name="status" label="Status" help="Choose one." :options="[\'open\' => \'Open\']" />', $viewData);
        $textarea = Blade::render('<x-form.textarea name="notes" label="Notes" help="Keep this concise." />', $viewData);

        $this->assertStringContainsString('aria-describedby="field-phone-help"', $input);
        $this->assertStringContainsString('id="field-phone-help"', $input);
        $this->assertStringContainsString('aria-describedby="field-status-help"', $select);
        $this->assertStringContainsString('id="field-status-help"', $select);
        $this->assertStringContainsString('aria-describedby="field-notes-help"', $textarea);
        $this->assertStringContainsString('id="field-notes-help"', $textarea);
    }

    public function test_shared_table_preserves_native_table_semantics(): void
    {
        $html = Blade::render('<x-table.index caption="Records"><tbody><tr><td>One</td></tr></tbody></x-table.index>');

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringNotContainsString('role="grid"', $html);
    }

    public function test_agent_screen_exposes_accessible_primary_call_controls(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_agent_screen_access',
            'setting_value' => '1',
        ]);
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->get(route('agent.index'));

        $response->assertOk();
        $response->assertSee('for="agent-phone-number"', false);
        $response->assertSee('id="agent-phone-number"', false);
        $response->assertSee('for="agent-lead-id"', false);
        $response->assertSee('id="agent-lead-id"', false);
        $response->assertSee('aria-label="Dial phone number"', false);
        $response->assertSee('aria-label="Hang up call"', false);
    }

    public function test_reports_use_labeled_dates_and_progressively_disclose_secondary_filters(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('id="reports-date-start"', false);
        $response->assertSee('id="reports-date-end"', false);
        $response->assertSee('More filters', false);
        $response->assertSee('aria-controls="reports-advanced-filters"', false);
        $response->assertSee('id="reports-advanced-filters"', false);
        $response->assertSee('id="reports-disposition-scope" class="form-select"', false);
    }

    public function test_supervisor_tabs_and_notification_fields_have_explicit_relationships(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $response = $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->get(route('admin.supervisor'));

        $response->assertOk();
        $response->assertSee('class="crm-tab-strip', false);
        $response->assertSee('id="supervisor-panel-wallboard"', false);
        $response->assertSee('aria-labelledby="supervisor-tab-wallboard"', false);
        $response->assertSee('for="supervisor-notification-recipient-type"', false);
        $response->assertSee('id="supervisor-notification-recipient-type"', false);
        $response->assertSee('for="supervisor-notification-recipient"', false);
        $response->assertSee('id="supervisor-notification-recipient"', false);
        $response->assertSee('for="supervisor-notification-message"', false);
        $response->assertSee('id="supervisor-notification-message"', false);
    }

    public function test_call_history_uses_shared_page_orientation_and_advanced_filter_disclosure(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->get(route('records.index'));

        $response->assertOk();
        $response->assertSee('page-header-title', false);
        $response->assertSee('Call History');
        $response->assertSee('More filters', false);
        $response->assertSee('aria-controls="call-history-advanced-filters"', false);
        $response->assertSee('id="call-history-advanced-filters"', false);
        $response->assertSee('class="form-select" x-model="filters.status"', false);
    }

    public function test_shared_styles_keep_static_cards_still_and_wide_data_master_tables_scrollable(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringNotContainsString('.md-card:hover { transform: translateY(-2px)', $css);
        $this->assertStringContainsString('.md-card--interactive:hover', $css);
        $this->assertMatchesRegularExpression('/\.data-master-desktop-table \.md-table-wrap\s*\{[^}]*min-width:\s*0;[^}]*max-width:\s*100%;/s', $css);
        $this->assertMatchesRegularExpression('/\.btn-icon\s*\{[^}]*min-width:\s*2\.75rem;[^}]*min-height:\s*2\.75rem;/s', $css);
        $this->assertMatchesRegularExpression('/\.data-master-desktop-table \.table-scroll-wrap\s*\{[^}]*overflow-x:\s*auto;/s', $css);
    }

    /**
     * @return array{campaign: string, campaign_name: string}
     */
    private function campaignSession(): array
    {
        return [
            'campaign' => 'mbsales',
            'campaign_name' => 'MB Sales',
        ];
    }
}
