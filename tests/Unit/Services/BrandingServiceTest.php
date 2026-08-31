<?php

namespace Tests\Unit\Services;

use App\Models\SystemSetting;
use App\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Config::set('branding.disk', 'public');
        Storage::fake('public');
    }

    public function test_defaults_are_returned_when_branding_is_not_configured(): void
    {
        $branding = app(BrandingService::class)->resolve();

        $this->assertSame(config('app.name'), $branding['name']);
        $this->assertNull($branding['logo_path']);
        $this->assertNull($branding['logo_url']);
        $this->assertSame(asset('favicon.ico'), $branding['favicon_url']);
    }

    public function test_configured_assets_are_resolved_and_reads_are_cached(): void
    {
        Storage::disk('public')->put('branding/logo.png', 'logo');
        Storage::disk('public')->put('branding/favicon.png', 'favicon');
        SystemSetting::query()->insert([
            [
                'setting_key' => 'branding_company_name',
                'setting_value' => 'Acme Support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_logo_path',
                'setting_value' => 'branding/logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_favicon_path',
                'setting_value' => 'branding/favicon.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(BrandingService::class);
        $first = $service->resolve();
        SystemSetting::query()->where('setting_key', 'branding_company_name')->update(['setting_value' => 'Changed Later']);
        $cached = $service->resolve();

        $this->assertSame('Acme Support', $first['name']);
        $this->assertSame('Acme Support', $cached['name']);
        $this->assertStringStartsWith('/storage/branding/', $first['logo_url']);
        $this->assertStringStartsWith('/storage/branding/', $first['favicon_url']);
        $this->assertStringContainsString('branding/logo.png', $first['logo_url']);
        $this->assertStringContainsString('branding/favicon.png', $first['favicon_url']);
        $this->assertStringContainsString('v=', $first['favicon_url']);

        $service->flush();

        $this->assertSame('Changed Later', $service->resolve()['name']);
    }

    public function test_missing_custom_assets_use_fallbacks(): void
    {
        SystemSetting::query()->insert([
            [
                'setting_key' => 'branding_company_name',
                'setting_value' => 'Acme Support',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_logo_path',
                'setting_value' => 'branding/deleted-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_favicon_path',
                'setting_value' => 'branding/deleted-favicon.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $branding = app(BrandingService::class)->resolve();

        $this->assertSame('Acme Support', $branding['name']);
        $this->assertNull($branding['logo_path']);
        $this->assertNull($branding['logo_url']);
        $this->assertSame(asset('favicon.ico'), $branding['favicon_url']);
    }

    public function test_update_generates_paths_cleans_up_previous_assets_and_invalidates_cache(): void
    {
        Storage::disk('public')->put('branding/old-logo.png', 'old logo');
        Storage::disk('public')->put('branding/old-favicon.png', 'old favicon');
        SystemSetting::query()->insert([
            [
                'setting_key' => 'branding_company_name',
                'setting_value' => 'Old Name',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_logo_path',
                'setting_value' => 'branding/old-logo.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'setting_key' => 'branding_favicon_path',
                'setting_value' => 'branding/old-favicon.png',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(BrandingService::class);
        $service->resolve();
        $service->update([
            'company_name' => 'New Name',
            'logo' => UploadedFile::fake()->image('logo.png'),
            'favicon' => UploadedFile::fake()->image('favicon.png'),
        ]);

        $logoPath = SystemSetting::query()->where('setting_key', 'branding_logo_path')->value('setting_value');
        $faviconPath = SystemSetting::query()->where('setting_key', 'branding_favicon_path')->value('setting_value');

        $this->assertIsString($logoPath);
        $this->assertIsString($faviconPath);
        $this->assertStringStartsWith('branding/', $logoPath);
        $this->assertStringStartsWith('branding/', $faviconPath);
        $this->assertNotSame('branding/old-logo.png', $logoPath);
        $this->assertNotSame('branding/old-favicon.png', $faviconPath);
        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($faviconPath);
        Storage::disk('public')->assertMissing('branding/old-logo.png');
        Storage::disk('public')->assertMissing('branding/old-favicon.png');
        $this->assertSame('New Name', $service->resolve()['name']);
    }

    public function test_storage_failure_does_not_change_existing_settings(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'branding_company_name',
            'setting_value' => 'Existing Name',
        ]);
        Config::set('branding.disk', 'missing-disk');

        $this->expectException(\Throwable::class);

        try {
            app(BrandingService::class)->update([
                'company_name' => 'New Name',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ]);
        } finally {
            $this->assertSame(
                'Existing Name',
                SystemSetting::query()->where('setting_key', 'branding_company_name')->value('setting_value'),
            );
        }
    }
}
