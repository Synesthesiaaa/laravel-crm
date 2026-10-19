<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CrmOperationsWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_groups_retain_every_authorized_destination_once(): void
    {
        foreach ([User::ROLE_AGENT, User::ROLE_TEAM_LEADER, User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN] as $role) {
            $user = User::factory()->make(['role' => $role]);
            $groups = AdminNavigation::workspaceGroups($user, true);
            $routes = collect($groups)->pluck('items')->flatten(1)->pluck('route')->all();
            $expected = $user->isTeamLeader() ? array_column(AdminNavigation::adminItems(), 'route') : [];
            if ($user->isSuperAdmin()) {
                $expected = array_merge($expected, array_column(AdminNavigation::superAdminItems(), 'route'));
            }
            $this->assertEqualsCanonicalizing($expected, $routes);
            $this->assertSame(count($routes), count(array_unique($routes)));
        }
    }

    public function test_creation_disclosure_is_closed_at_rest_and_recovers_creation_errors(): void
    {
        Campaign::factory()->create(['code' => 'mbsales']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->withSession(['campaign' => 'mbsales'])->get(route('admin.users.index'))
            ->assertOk()->assertSee('data-create-panel', false)
            ->assertDontSee('data-create-panel open', false);

        $this->withSession(['errors' => (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['username' => 'Use a unique username.'])), '_old_input' => ['username' => 'operator']])
            ->get(route('admin.users.index'))->assertOk()->assertSee('data-create-panel open', false)
            ->assertSee('value="operator"', false)->assertSee('Use a unique username.');
    }

    public function test_row_edit_failure_does_not_expand_creation_panel(): void
    {
        Campaign::factory()->create(['code' => 'mbsales']);
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($user)->withSession(['campaign' => 'mbsales', 'errors' => (new ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['username' => 'Invalid edit.'])), '_old_input' => ['_editing' => $user->id]])
            ->get(route('admin.users.index'))->assertOk()->assertDontSee('data-create-panel open', false);
    }

    public function test_email_subworkspaces_restore_the_failed_form_and_preserve_delivery_actions(): void
    {
        Campaign::factory()->create(['code' => 'mbsales']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))->withSession(['campaign' => 'mbsales']);
        EmailTemplate::create(['name' => 'Requested follow-up', 'subject' => 'Information', 'html_body' => 'Hello', 'pdf_enabled' => false]);

        $this->get(route('admin.email-campaigns.index'))->assertOk()->assertViewHas('workspace', 'prepare')
            ->assertSee('Create draft campaign')->assertDontSee('id="template-name"', false);
        $this->get(route('admin.email-campaigns.index', ['workspace' => 'templates']))->assertOk()
            ->assertViewHas('workspace', 'templates')->assertSee('Saved templates')->assertSee('id="template-name"', false);
        $this->get(route('admin.email-campaigns.index', ['workspace' => 'delivery']))->assertOk()
            ->assertViewHas('workspace', 'delivery')->assertSee('Campaign history');
        $this->withSession(['_old_input' => ['_email_campaign_form' => 'template', 'name' => 'Draft template']])
            ->get(route('admin.email-campaigns.index'))->assertOk()->assertViewHas('workspace', 'templates')
            ->assertSee('value="Draft template"', false);
    }

    public function test_shared_table_scroll_region_has_an_accessible_name_and_keyboard_target(): void
    {
        $html = Blade::render('<x-table.index caption="Staff attendance"><tbody><tr><td>Ready</td></tr></tbody></x-table.index>');
        $this->assertStringContainsString('role="region"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('aria-label="Staff attendance"', $html);
    }

    public function test_dashboard_layout_editor_receives_compiled_alpine_configuration(): void
    {
        Campaign::factory()->create(['code' => 'mbsales']);
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]))
            ->withSession(['campaign' => 'mbsales'])->get(route('admin.dashboard'))
            ->assertOk()->assertSee('adminDashboardLayoutEditor({', false)
            ->assertDontSee('sections: @js(', false)
            ->assertDontSee('labels: @js(', false)
            ->assertSee('role="alertdialog"', false)
            ->assertSee('aria-describedby="confirmation-description"', false);
    }

    public function test_shared_pagination_preserves_page_links_and_current_page_semantics(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(range(1, 25), 70, 25, 1, ['path' => '/staff']);
        $html = Blade::render('<x-table.pagination :paginator="$paginator" />', ['paginator' => $paginator]);
        $this->assertStringContainsString('aria-label="Pagination"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('href="/staff?page=2"', $html);
        $this->assertStringContainsString('Go to page 3', $html);
    }
}
