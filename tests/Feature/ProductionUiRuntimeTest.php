<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionUiRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_authenticated_html_shell_is_not_cached(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertIsString($cacheControl);
        foreach (['private', 'no-cache', 'no-store', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $cacheControl);
        }
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    public function test_frontend_hot_file_and_runtime_contract_are_configured_for_production(): void
    {
        $viteConfig = file_get_contents(base_path('vite.config.js'));
        $appProvider = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $appEntry = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($viteConfig);
        $this->assertIsString($appProvider);
        $this->assertIsString($appEntry);
        $this->assertStringContainsString("hotFile: 'storage/vite.hot'", $viteConfig);
        $this->assertStringContainsString("Vite::useHotFile(storage_path('vite.hot'))", $appProvider);
        $this->assertStringContainsString('window.crmUiRuntime =', $appEntry);
        $this->assertStringContainsString("document.documentElement.dataset.crmUiReady = 'true'", $appEntry);
    }

    public function test_frontend_runtime_marker_is_not_present_before_bootstrap(): void
    {
        $appEntry = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($appEntry);
        $this->assertStringContainsString('Alpine.start();', $appEntry);
        $this->assertLessThan(
            strpos($appEntry, "document.documentElement.dataset.crmUiReady = 'true'"),
            strpos($appEntry, 'Alpine.start();'),
        );
    }
}
