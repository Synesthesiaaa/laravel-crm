<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
        Storage::fake('public');
    }

    public function test_super_admin_can_view_and_update_branding(): void
    {
        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('admin.configuration', ['tab' => 'branding']))
            ->assertOk()
            ->assertSee('Company Branding')
            ->assertSee('name="company_name"', false)
            ->assertSee('name="logo"', false)
            ->assertSee('name="favicon"', false);

        $response = $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->post(route('admin.configuration.branding.update'), [
                'company_name' => 'Acme Support',
                'logo' => UploadedFile::fake()->image('customer-logo.png'),
                'favicon' => UploadedFile::fake()->image('customer-favicon.png'),
            ]);

        $response->assertRedirect(route('admin.configuration', ['tab' => 'branding']));
        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'branding_company_name',
            'setting_value' => 'Acme Support',
        ]);

        $logoPath = SystemSetting::query()->where('setting_key', 'branding_logo_path')->value('setting_value');
        $faviconPath = SystemSetting::query()->where('setting_key', 'branding_favicon_path')->value('setting_value');
        $this->assertIsString($logoPath);
        $this->assertIsString($faviconPath);
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);
    }

    public function test_non_super_admin_roles_cannot_update_branding(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_TEAM_LEADER, User::ROLE_AGENT] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->post(route('admin.configuration.branding.update'), [
                    'company_name' => 'Should Not Save',
                ])
                ->assertForbidden();
        }

        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'branding_company_name',
        ]);
    }

    public function test_guests_cannot_access_branding_management(): void
    {
        $this->get(route('admin.configuration', ['tab' => 'branding']))
            ->assertRedirect(route('login'));

        $this->post(route('admin.configuration.branding.update'), [
            'company_name' => 'Should Not Save',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'branding_company_name',
        ]);
    }

    public function test_branding_validation_rejects_empty_name_and_svg_uploads(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->from(route('admin.configuration', ['tab' => 'branding']))
            ->post(route('admin.configuration.branding.update'), [
                'company_name' => '',
                'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            ]);

        $response->assertRedirect(route('admin.configuration', ['tab' => 'branding']))
            ->assertSessionHasErrors(['company_name', 'logo']);
        $this->assertDatabaseMissing('system_settings', [
            'setting_key' => 'branding_company_name',
        ]);
    }

    public function test_configured_branding_is_rendered_on_login_dashboard_sidebar_title_and_favicon(): void
    {
        Storage::disk('public')->put('branding/customer-logo.png', 'logo');
        Storage::disk('public')->put('branding/customer-favicon.png', 'favicon');
        SystemSetting::query()->insert([
            [
                'setting_key' => 'branding_company_name',
                'setting_value' => 'Acme Support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_logo_path',
                'setting_value' => 'branding/customer-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_favicon_path',
                'setting_value' => 'branding/customer-favicon.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<title>Login | Acme Support</title>', false)
            ->assertSee('alt="Acme Support logo"', false)
            ->assertSee('branding/customer-logo.png', false)
            ->assertSee('branding/customer-favicon.png', false)
            ->assertSee('Acme Support', false);

        $this->withSession([
            'login_pending' => [
                'user_id' => $this->superAdmin->id,
                'expires_at' => now()->addMinute()->getTimestamp(),
                'campaign' => 'mbsales',
            ],
        ])
            ->get(route('login.pending'))
            ->assertOk()
            ->assertSee('<title>Active session | Acme Support</title>', false)
            ->assertSee('alt="Acme Support logo"', false);

        $this->actingAs($this->superAdmin)
            ->withSession($this->campaignSession())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>Dashboard - MB Sales | Acme Support</title>', false)
            ->assertSee('Welcome to Acme Support', false)
            ->assertSee('branding/customer-logo.png', false)
            ->assertSee('branding/customer-favicon.png', false)
            ->assertSee('Campaign: <span', false);
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
