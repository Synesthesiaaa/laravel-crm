<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CrmCallHistory;
use App\Models\Form;
use App\Models\FormField;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AgentDataMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    private User $other;

    private User $super;

    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        Campaign::factory()->create(['code' => 'alpha', 'name' => 'Alpha Campaign']);
        Campaign::factory()->create(['code' => 'beta', 'name' => 'Beta Campaign']);
        $this->agent = User::factory()->create(['role' => User::ROLE_AGENT, 'full_name' => 'Same Name']);
        $this->other = User::factory()->create(['role' => User::ROLE_AGENT, 'full_name' => 'Same Name']);
        $this->super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->form = Form::create(['campaign_code' => 'alpha', 'form_code' => 'sales', 'name' => 'Sales Form', 'table_name' => 'agent_dm_sales', 'is_active' => true]);
        FormField::create(['campaign_code' => 'alpha', 'form_type' => 'sales', 'field_name' => 'client', 'field_label' => 'Client', 'field_type' => 'text', 'field_order' => 1]);
        Schema::create('agent_dm_sales', function ($table) {
            $table->id();
            $table->string('client');
            $table->string('agent');
            $table->date('date');
            $table->string('retired_secret')->nullable();
            $table->timestamps();
        });
        $this->actingAs($this->agent)->withSession(['campaign' => 'alpha', 'campaign_name' => 'Alpha Campaign']);
    }

    private function grant(bool $ownOnly = true): void
    {
        SystemSetting::updateOrCreate(['setting_key' => 'agent_data_master_alpha'], ['setting_value' => json_encode(['enabled' => true, 'own_submissions_only' => $ownOnly, 'form_ids' => [$this->form->id]])]);
    }

    private function row(string $client, ?User $owner, string $created = '2026-11-12 12:00:00', string $campaign = 'alpha'): int
    {
        $id = DB::table('agent_dm_sales')->insertGetId(['client' => $client, 'agent' => 'Same Name', 'date' => '2026-01-01', 'retired_secret' => 'Hidden-Needle', 'created_at' => $created, 'updated_at' => $created]);
        if ($owner) {
            CrmCallHistory::create(['campaign_code' => $campaign, 'form_type' => 'sales', 'record_id' => $id, 'agent' => 'Same Name', 'user_id' => $owner->id, 'status' => 'RECORDED']);
        }

        return $id;
    }

    public function test_access_defaults_to_disabled_and_navigation_is_hidden(): void
    {
        $this->get('/data-master')->assertForbidden();
        $this->get('/attendance')->assertOk()->assertDontSee('href="'.url('/data-master').'"', false);
    }

    public function test_super_admin_can_configure_campaign_forms_and_scope(): void
    {
        $this->actingAs($this->super)->get('/admin/configuration?tab=data-master&data_master_campaign=alpha')
            ->assertOk()->assertSee('Own submissions only')->assertSee('Sales Form');
        $this->put('/admin/configuration/data-master/alpha', ['enabled' => '1', 'own_submissions_only' => '0', 'form_ids' => [$this->form->id]])
            ->assertRedirect();
        $settings = json_decode(SystemSetting::where('setting_key', 'agent_data_master_alpha')->value('setting_value'), true);
        $this->assertTrue($settings['enabled']);
        $this->assertFalse($settings['own_submissions_only']);
        $this->assertSame([$this->form->id], $settings['form_ids']);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'default', 'subject_type' => SystemSetting::class]);
    }

    public function test_lower_roles_cannot_change_settings(): void
    {
        foreach ([User::ROLE_AGENT, User::ROLE_TEAM_LEADER, User::ROLE_ADMIN] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->put('/admin/configuration/data-master/alpha', ['enabled' => '1', 'form_ids' => [$this->form->id]])->assertForbidden();
        }
        $this->assertDatabaseMissing('system_settings', ['setting_key' => 'agent_data_master_alpha']);
    }

    public function test_settings_reject_cross_campaign_inactive_and_empty_forms(): void
    {
        $foreign = Form::create(['campaign_code' => 'beta', 'form_code' => 'other', 'name' => 'Other', 'table_name' => 'other', 'is_active' => true]);
        $this->actingAs($this->super);
        $this->putJson('/admin/configuration/data-master/alpha', ['enabled' => true, 'form_ids' => [$foreign->id]])->assertUnprocessable();
        $this->putJson('/admin/configuration/data-master/alpha', ['enabled' => true])->assertUnprocessable();
        $this->form->update(['is_active' => false]);
        $this->putJson('/admin/configuration/data-master/alpha', ['enabled' => true, 'form_ids' => [$this->form->id]])->assertUnprocessable();
    }

    public function test_own_scope_uses_user_id_and_cannot_be_overridden_by_search_or_parameters(): void
    {
        $this->grant();
        $this->row('Owned client', $this->agent);
        $this->row('Another client', $this->other);
        $this->row('Unattributed client', null);
        $this->row('Wrong campaign client', $this->agent, campaign: 'beta');
        $this->get('/data-master?campaign=beta&own_submissions_only=0&user_id='.$this->other->id.'&table=users&search=client')
            ->assertOk()->assertSee('Owned client')->assertDontSee('Another client')->assertDontSee('Unattributed client')->assertDontSee('Wrong campaign client')
            ->assertDontSee('Hidden-Needle')->assertSee('Your submissions only')->assertDontSee('>Edit<', false);
        $this->get('/data-master?search=Hidden-Needle')->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 0);
    }

    public function test_campaign_wide_scope_includes_all_users_and_legacy_rows(): void
    {
        $this->grant(false);
        $this->row('Owned client', $this->agent);
        $this->row('Another client', $this->other);
        $this->row('Legacy client', null);
        $this->get('/data-master')->assertOk()->assertSee('Owned client')->assertSee('Another client')->assertSee('Legacy client')->assertSee('All campaign submissions');
    }

    public function test_shared_storage_requires_campaign_and_form_provenance_even_in_all_scope(): void
    {
        $this->grant(false);
        Form::create(['campaign_code' => 'beta', 'form_code' => 'sales', 'name' => 'Shared', 'table_name' => 'agent_dm_sales', 'is_active' => true]);
        $this->row('Alpha client', $this->other);
        $this->row('Beta client', $this->other, campaign: 'beta');
        $this->row('Unattributed shared', null);
        $this->get('/data-master')->assertOk()->assertSee('Alpha client')->assertDontSee('Beta client')->assertDontSee('Unattributed shared');
    }

    public function test_combined_filters_use_submission_timestamp_and_preserve_pagination(): void
    {
        $this->grant();
        for ($i = 0; $i < 23; $i++) {
            $this->row('Matching client '.$i, $this->agent, '2026-11-12 23:59:59');
        }
        $this->row('Outside date', $this->agent, '2026-11-13 00:00:00');
        $this->row('Matching other agent', $this->other);
        $this->get('/data-master?type=sales&search=client&date_from=2026-11-12&date_to=2026-11-12&column_filters[client]=Matching')
            ->assertOk()->assertViewHas('records', fn ($r) => $r->total() === 23 && $r->count() === 20)
            ->assertSee('column_filters%5Bclient%5D=Matching', false)->assertDontSee('Outside date')->assertDontSee('Matching other agent');
        $this->get('/data-master?search=client&column_filters[client]=Matching&page=2')->assertOk()
            ->assertViewHas('records', fn ($r) => $r->count() === 3);
    }

    public function test_invalid_or_unpermitted_filters_fail_validation(): void
    {
        $this->grant();
        foreach ([['type' => 'other'], ['date_from' => '2026-11-13', 'date_to' => '2026-11-12'], ['column_filters' => ['retired_secret' => 'x']], ['column_filters' => ['id) OR 1=1' => 'x']], ['search' => str_repeat('x', 101)], ['search' => ['x']]] as $query) {
            $this->getJson('/data-master?'.http_build_query($query))->assertUnprocessable();
        }
    }

    public function test_revocation_deleted_forms_and_malformed_settings_fail_closed(): void
    {
        $this->grant();
        $this->get('/data-master')->assertOk();
        $this->actingAs($this->super)->put('/admin/configuration/data-master/alpha', ['enabled' => '0'])->assertRedirect();
        $this->actingAs($this->agent)->get('/data-master')->assertForbidden();
        foreach (['not-json', '{"enabled":"false","form_ids":[1]}', 'null'] as $value) {
            SystemSetting::where('setting_key', 'agent_data_master_alpha')->update(['setting_value' => $value]);
            $this->get('/data-master')->assertForbidden();
        }
        $this->grant();
        $this->form->delete();
        $this->get('/data-master')->assertForbidden();
    }

    public function test_missing_storage_is_reported_safely(): void
    {
        $this->grant();
        Schema::drop('agent_dm_sales');
        $this->get('/data-master')->assertStatus(503)->assertSee('Form records are unavailable')->assertDontSee('SQLSTATE');
    }

    public function test_missing_first_form_allows_recovery_to_another_permitted_form(): void
    {
        $healthy = Form::create(['campaign_code' => 'alpha', 'form_code' => 'healthy', 'name' => 'Healthy form', 'table_name' => 'agent_dm_sales', 'is_active' => true]);
        $this->form->update(['table_name' => 'missing_dm_storage']);
        SystemSetting::updateOrCreate(['setting_key' => 'agent_data_master_alpha'], ['setting_value' => json_encode(['enabled' => true, 'own_submissions_only' => false, 'form_ids' => [$this->form->id, $healthy->id]])]);
        $this->get('/data-master')->assertStatus(503)->assertSee('Healthy form')->assertSee('Load form');
        $this->get('/data-master?type=healthy')->assertOk();
    }

    public function test_new_forms_do_not_inherit_permissions_and_inactive_campaign_is_denied(): void
    {
        $this->grant();
        $this->form->delete();
        $replacement = Form::create(['campaign_code' => 'alpha', 'form_code' => 'replacement', 'name' => 'Replacement', 'table_name' => 'agent_dm_sales', 'is_active' => true]);
        $this->get('/data-master')->assertForbidden();
        $this->actingAs($this->super)->put('/admin/configuration/data-master/alpha', ['enabled' => '1', 'form_ids' => [$replacement->id]])->assertRedirect();
        $this->assertTrue(json_decode(SystemSetting::where('setting_key', 'agent_data_master_alpha')->value('setting_value'), true)['own_submissions_only']);
        Campaign::where('code', 'alpha')->update(['is_active' => false]);
        $this->actingAs($this->agent)->get('/data-master')->assertForbidden();
    }

    public function test_date_filter_falls_back_to_form_date_when_timestamp_is_missing(): void
    {
        $this->grant(false);
        Schema::table('agent_dm_sales', fn ($table) => $table->dropColumn(['created_at', 'updated_at']));
        DB::table('agent_dm_sales')->insert([
            ['client' => 'Inside fallback date', 'agent' => 'Agent', 'date' => '2026-11-12'],
            ['client' => 'Outside fallback date', 'agent' => 'Agent', 'date' => '2026-11-13'],
        ]);
        $this->get('/data-master?date_from=2026-11-12&date_to=2026-11-12')->assertOk()->assertSee('Inside fallback date')->assertDontSee('Outside fallback date');
    }

    public function test_agent_cannot_use_any_data_master_write_route(): void
    {
        $this->grant(false);
        $id = $this->row('Unchanged client', $this->agent);
        $this->get('/admin/data-master/edit/'.$id.'?type=sales')->assertForbidden();
        foreach (['update', 'delete'] as $action) {
            $this->post('/admin/data-master/'.$action, ['_table' => 'agent_dm_sales', '_id' => $id, '_type' => 'sales', 'client' => 'Tampered'])->assertForbidden();
        }
        $this->post('/data-master', ['client' => 'Tampered'])->assertStatus(405);
        $this->assertDatabaseHas('agent_dm_sales', ['id' => $id, 'client' => 'Unchanged client']);
    }
}
