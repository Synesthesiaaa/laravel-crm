<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\User;
use App\Services\Telephony\ReportDispositionSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportDispositionConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
    }

    public function test_super_admin_can_configure_system_dispositions_for_reports(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.configuration', ['tab' => 'disposition']))
            ->assertOk()
            ->assertSee('Hide system dispositions in reports')
            ->assertSee('name="system_disposition_codes"', false);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.report-dispositions.update'), [
                'hide_system_dispositions' => '1',
                'system_disposition_codes' => 'na, AB; na',
            ])
            ->assertRedirect(route('admin.configuration', ['tab' => 'disposition']));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => ReportDispositionSettingsService::HIDE_SYSTEM_DISPOSITIONS_KEY,
            'setting_value' => '1',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => ReportDispositionSettingsService::SYSTEM_DISPOSITION_CODES_KEY,
            'setting_value' => 'NA,AB',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->withSession($this->campaignSession())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('systemDispositionFilterLocked: true', false)
            ->assertSee('disposition_scope: "exclude_system"', false)
            ->assertSee('NA', false)
            ->assertSee('AB', false);
    }

    public function test_hiding_system_dispositions_requires_at_least_one_code(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->from(route('admin.configuration', ['tab' => 'disposition']))
            ->post(route('admin.configuration.report-dispositions.update'), [
                'hide_system_dispositions' => '1',
                'system_disposition_codes' => '',
            ])
            ->assertRedirect(route('admin.configuration', ['tab' => 'disposition']))
            ->assertSessionHasErrors('system_disposition_codes');

        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => ReportDispositionSettingsService::HIDE_SYSTEM_DISPOSITIONS_KEY,
        ]);
    }

    public function test_non_super_admin_cannot_change_report_disposition_settings(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($user)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.report-dispositions.update'), [
                'hide_system_dispositions' => '1',
                'system_disposition_codes' => 'NA,AB',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => ReportDispositionSettingsService::HIDE_SYSTEM_DISPOSITIONS_KEY,
        ]);
    }

    /**
     * @return array{campaign: string, campaign_name: string}
     */
    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }
}
