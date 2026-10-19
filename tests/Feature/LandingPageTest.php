<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_root_renders_the_crm_landing_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertViewIs('welcome')
            ->assertSee('Every call.')
            ->assertSee('All the context.')
            ->assertSee('One workspace.')
            ->assertSee('See how it works')
            ->assertSee('Sign in')
            ->assertSee('id="theme-toggle"', false)
            ->assertSee('aria-label="Switch to light mode"', false)
            ->assertSee('theme-icon-dark', false)
            ->assertSee('theme-icon-light', false)
            ->assertSee("localStorage.setItem('theme', t)", false)
            ->assertDontSee('Illustrative workspace')
            ->assertDontSee('Fictional customer details.')
            ->assertDontSee('Marina Reyes')
            ->assertDontSee('class="landing-workspace"', false)
            ->assertDontSee('class="landing-preview"', false)
            ->assertSee('Browser calling')
            ->assertSee('campaign forms')
            ->assertSee('follow-ups')
            ->assertSee('Reports');
    }

    public function test_landing_preserves_configured_branding_and_escapes_the_company_name(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('branding/landing-logo.png', 'logo');
        Storage::disk('public')->put('branding/landing-icon.png', 'icon');

        foreach ([
            'branding_company_name' => 'Support <North> & Team',
            'branding_logo_path' => 'branding/landing-logo.png',
            'branding_favicon_path' => 'branding/landing-icon.png',
        ] as $key => $value) {
            SystemSetting::query()->create(['setting_key' => $key, 'setting_value' => $value]);
        }
        Cache::forget(config('branding.cache_key'));

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Support &lt;North&gt; &amp; Team | Call Center CRM', false)
            ->assertSee('alt="Support &lt;North&gt; &amp; Team logo"', false)
            ->assertSee('/storage/branding/landing-logo.png', false)
            ->assertSee('/storage/branding/landing-icon.png', false)
            ->assertDontSee('Support <North> & Team', false);
    }

    public function test_landing_calls_to_action_use_the_existing_login_route(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $loginLinks = $xpath->query('//a[@href="'.route('login').'"]');

        $this->assertSame(2, $loginLinks->length);
        $this->assertSame(1, $xpath->query('//main[@id="main-content"]')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        $this->assertSame(4, $xpath->query('//ol[contains(@class,"landing-workflow")]/li')->length);
        $this->assertSame(4, $xpath->query('//dl[contains(@class,"landing-roles")]/div')->length);
        $this->assertSame(0, $xpath->query('//dl[contains(@class,"landing-roles")]//h3')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"landing-capability-strip") or contains(@class,"landing-continuity") or contains(@class,"landing-final")]')->length);
        foreach ($xpath->query('//a[starts-with(@href,"#")]') as $anchor) {
            $target = substr($anchor->getAttribute('href'), 1);
            $this->assertSame(1, $xpath->query('//*[@id="'.$target.'"]')->length, 'Missing or duplicate anchor: '.$target);
        }
        $this->assertStringContainsString('href="/favicon.ico"', $html);
    }
}
