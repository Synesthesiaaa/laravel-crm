<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateRuntimeSecurityTest extends TestCase
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

    public function test_private_document_heads_are_noindex_noarchive_and_described(): void
    {
        foreach ([
            'layouts/app.blade.php',
            'auth/login.blade.php',
            'auth/login-pending.blade.php',
            'forms/widget.blade.php',
            'agent/capture_webform.blade.php',
            'errors/layout.blade.php',
        ] as $path) {
            $source = file_get_contents(resource_path('views/'.$path));

            $this->assertIsString($source, $path);
            $this->assertMatchesRegularExpression(
                '/<meta\s+name="robots"\s+content="[^"]*noindex[^"]*noarchive[^"]*"/i',
                $source,
                $path,
            );
            $this->assertMatchesRegularExpression(
                '/<meta\s+name="description"\s+content="[^"]{20,}"/i',
                $source,
                $path,
            );
        }
    }

    public function test_robots_txt_disallows_crawling_the_private_application(): void
    {
        $robots = str_replace("\r\n", "\n", (string) file_get_contents(public_path('robots.txt')));

        $this->assertStringContainsString("User-agent: *\n", $robots);
        $this->assertStringContainsString('Disallow: /', $robots);
    }

    public function test_web_responses_add_report_only_security_headers_without_hsts_on_http(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $this->assertNull($response->headers->get('Strict-Transport-Security'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
        $this->assertNull($response->headers->get('Cross-Origin-Embedder-Policy'));

        $csp = $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertIsString($csp);
        foreach ([
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' http: https:",
            "style-src 'self' 'unsafe-inline' http: https:",
            "img-src 'self' data: blob: http: https:",
            "connect-src 'self' http: https: ws: wss:",
            "frame-src 'self' http: https:",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ] as $directive) {
            $this->assertStringContainsString($directive, $csp);
        }
    }

    public function test_hsts_is_sent_only_for_secure_requests(): void
    {
        $user = User::factory()->create();

        $http = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get(route('dashboard'));
        $this->assertNull($http->headers->get('Strict-Transport-Security'));

        $https = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'])
            ->get('https://localhost/dashboard');

        $https->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_apache_marks_only_branding_storage_assets_immutable(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $this->assertMatchesRegularExpression(
            '~SetEnvIf\s+Request_URI\s+"\^/storage/branding/"\s+BRANDING_IMMUTABLE~i',
            $htaccess,
        );
        $this->assertMatchesRegularExpression(
            '/Header\s+(?:always\s+)?set\s+Cache-Control\s+"public, max-age=31536000, immutable"\s+env=BRANDING_IMMUTABLE/i',
            $htaccess,
        );
    }
}
