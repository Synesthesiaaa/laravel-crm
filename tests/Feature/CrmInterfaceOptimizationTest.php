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

    public function test_sidebar_uses_one_labelled_navigation_landmark(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $response = $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->get(route('dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('/<aside\b[^>]*id="sidebar"[^>]*>/s', $html);
        preg_match('/<aside\b[^>]*id="sidebar"[^>]*>/s', $html, $asideMatch);
        preg_match('/<nav\b[^>]*class="sidebar-nav"[^>]*>/s', $html, $navMatch);

        $this->assertNotEmpty($asideMatch);
        $this->assertNotEmpty($navMatch);
        $this->assertStringNotContainsString('role="navigation"', $asideMatch[0]);
        $this->assertStringNotContainsString('aria-label=', $asideMatch[0]);
        $this->assertStringContainsString('aria-label="Primary destinations"', $navMatch[0]);
    }

    public function test_dark_theme_text_and_action_tokens_meet_wcag_aa(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $tokens = $this->cssHexTokens($css, [
            'color-primary',
            'color-primary-foreground',
            'color-action',
            'color-on-surface',
            'color-on-surface-muted',
            'color-on-surface-dim',
            'color-surface-3',
        ]);

        $this->assertSame('#e91e8c', strtolower($tokens['color-primary']));
        foreach (['color-on-surface', 'color-on-surface-muted', 'color-on-surface-dim', 'color-action'] as $foregroundToken) {
            $this->assertGreaterThanOrEqual(
                4.5,
                $this->contrastRatio($tokens[$foregroundToken], $tokens['color-surface-3']),
                $foregroundToken.' should meet WCAG AA against the lightest dark surface.',
            );
        }
        $this->assertGreaterThanOrEqual(
            4.5,
            $this->contrastRatio($tokens['color-primary-foreground'], $tokens['color-primary']),
            'Primary action text should meet WCAG AA on the brand background.',
        );
        $this->assertMatchesRegularExpression('/\.sidebar-item\.active\s*\{[^}]*color:\s*var\(--color-action\);/s', $css);
        $this->assertMatchesRegularExpression('/\.link-primary\s*\{[^}]*color:\s*var\(--color-action\);/s', $css);
    }

    public function test_interactive_magenta_foregrounds_use_accessible_action_token(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $callHistory = file_get_contents(resource_path('views/records/partials/call-history-panel.blade.php'));
        $supervisor = file_get_contents(resource_path('views/admin/supervisor.blade.php'));
        $recordsList = file_get_contents(resource_path('views/admin/records_list.blade.php'));
        $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));
        $adminDashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $clickToCall = file_get_contents(resource_path('views/components/click-to-call.blade.php'));

        foreach ([$css, $callHistory, $supervisor, $recordsList, $dashboard, $adminDashboard, $clickToCall] as $source) {
            $this->assertIsString($source);
        }

        $this->assertMatchesRegularExpression('/\.agent-tool-tab\.is-active\s*\{[^}]*color:\s*var\(--color-action\);/s', $css);
        $this->assertMatchesRegularExpression('/\.link-primary:hover\s*\{[^}]*color:\s*var\(--color-action\);/s', $css);
        $this->assertStringContainsString('hover:text-[var(--color-action)]', $callHistory);
        $this->assertMatchesRegularExpression('/<summary[^>]*text-\[var\(--color-action\)\]/s', $callHistory);
        $this->assertGreaterThanOrEqual(4, substr_count($supervisor, 'text-[var(--color-action)]'));
        $this->assertGreaterThanOrEqual(2, substr_count($recordsList, 'text-[var(--color-action)]'));
        $this->assertStringContainsString("bg-[var(--color-primary-muted)] text-[var(--color-action)]", $dashboard);
        $this->assertStringContainsString("bg-[var(--color-primary-muted)] text-[var(--color-action)]", $adminDashboard);
        $this->assertStringContainsString('bg-[var(--color-primary)] text-[var(--color-primary-foreground)]', $clickToCall);
        $this->assertStringNotContainsString('bg-[var(--color-primary)] text-white', $clickToCall);
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

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private function cssHexTokens(string $css, array $names): array
    {
        $tokens = [];

        foreach ($names as $name) {
            preg_match('/--'.preg_quote($name, '/').'\s*:\s*(#[0-9a-fA-F]{6})\s*;/', $css, $match);
            $this->assertNotEmpty($match, 'Missing CSS token --'.$name.'.');
            $tokens[$name] = $match[1];
        }

        return $tokens;
    }

    private function contrastRatio(string $foreground, string $background): float
    {
        $foregroundLuminance = $this->relativeLuminance($foreground);
        $backgroundLuminance = $this->relativeLuminance($background);
        $lighter = max($foregroundLuminance, $backgroundLuminance);
        $darker = min($foregroundLuminance, $backgroundLuminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        $channels = [
            hexdec(substr($hex, 1, 2)) / 255,
            hexdec(substr($hex, 3, 2)) / 255,
            hexdec(substr($hex, 5, 2)) / 255,
        ];

        [$red, $green, $blue] = array_map(
            static fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
    }
}
