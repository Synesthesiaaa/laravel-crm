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

    public function test_agent_capture_uses_its_dedicated_lightweight_runtime_entry(): void
    {
        $appEntry = file_get_contents(resource_path('js/app.js'));
        $captureEntry = file_get_contents(resource_path('js/agent-capture-webform-entry.js'));
        $bootstrap = file_get_contents(resource_path('js/bootstrap.js'));
        $httpBootstrapPath = resource_path('js/http-bootstrap.js');
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        $this->assertIsString($appEntry);
        $this->assertIsString($captureEntry);
        $this->assertIsString($bootstrap);
        $this->assertIsString($viteConfig);
        $this->assertStringNotContainsString("import './agent-capture-webform';", $appEntry);
        $this->assertStringContainsString("import './agent-capture-webform';", $captureEntry);
        $this->assertStringContainsString("import './http-bootstrap';", $captureEntry);
        $this->assertStringNotContainsString("import './bootstrap';", $captureEntry);
        $this->assertStringContainsString("'resources/js/agent-capture-webform-entry.js'", $viteConfig);
        $this->assertFileExists($httpBootstrapPath);

        $httpBootstrap = file_get_contents($httpBootstrapPath);
        $this->assertIsString($httpBootstrap);
        $this->assertStringContainsString("import axios from 'axios';", $httpBootstrap);
        $this->assertStringNotContainsString("import './echo';", $httpBootstrap);
        $this->assertStringContainsString("import './http-bootstrap';", $bootstrap);
        $this->assertStringContainsString("import './echo';", $bootstrap);
    }

    public function test_layout_primes_sidebar_state_before_vite_and_uses_system_fonts_only(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $login = file_get_contents(resource_path('views/auth/login.blade.php'));
        $loginPending = file_get_contents(resource_path('views/auth/login-pending.blade.php'));
        $reports = file_get_contents(resource_path('views/reports/index.blade.php'));
        $adminDashboard = file_get_contents(resource_path('views/admin/dashboard.blade.php'));
        $supervisor = file_get_contents(resource_path('views/admin/supervisor.blade.php'));
        $errorLayout = file_get_contents(resource_path('views/errors/layout.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($layout);
        $this->assertIsString($sidebar);
        $this->assertIsString($login);
        $this->assertIsString($loginPending);
        $this->assertIsString($reports);
        $this->assertIsString($adminDashboard);
        $this->assertIsString($supervisor);
        $this->assertIsString($errorLayout);
        $this->assertIsString($css);

        $sidebarStateRead = strpos($layout, "localStorage.getItem('sidebar_collapsed')");
        $viteLoad = strpos($layout, '@vite');
        $this->assertNotFalse($sidebarStateRead);
        $this->assertNotFalse($viteLoad);
        $this->assertLessThan($viteLoad, $sidebarStateRead);
        $this->assertStringContainsString("setAttribute('data-sidebar-collapsed', 'true')", $layout);
        $this->assertStringContainsString("removeAttribute('data-sidebar-collapsed')", $layout);
        $this->assertStringContainsString("\$watch('\$store.sidebar.collapsed'", $sidebar);

        foreach ([$layout, $login, $loginPending, $reports, $adminDashboard, $supervisor, $errorLayout] as $viewSource) {
            $this->assertStringNotContainsString('fonts.googleapis.com', $viewSource);
            $this->assertStringNotContainsString('fonts.gstatic.com', $viewSource);
            $this->assertStringNotContainsString('DM Sans', $viewSource);
        }

        $this->assertMatchesRegularExpression('/--font-sans:\s*ui-sans-serif,\s*system-ui,/s', $css);
        $this->assertStringNotContainsString('DM Sans', $css);
        $this->assertStringNotContainsString('Instrument Sans', $css);
        $this->assertStringNotContainsString('@font-face', $css);
    }

    public function test_pre_hydration_sidebar_marker_has_desktop_geometry_css(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/html\[data-sidebar-collapsed="true"\]\s+\.md-main-layout\s*\{[^}]*margin-left:\s*var\(--sidebar-collapsed-width\);[^}]*width:\s*calc\(100% - var\(--sidebar-collapsed-width\)\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/html\[data-sidebar-collapsed="true"\]\s+\.md-sidebar\s*\{[^}]*width:\s*var\(--sidebar-collapsed-width\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/html\[data-sidebar-collapsed="true"\]\s+\.sidebar-item-label[^\{]*\{[^}]*opacity:\s*0;/s',
            $css,
        );
    }
}
