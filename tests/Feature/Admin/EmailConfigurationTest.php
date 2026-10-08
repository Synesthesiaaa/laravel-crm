<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendEmailCampaignBatch;
use App\Models\EmailCampaign;
use App\Models\EmailSmtpSetting;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailSmtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_email_configuration_is_restricted_to_super_admin(): void
    {
        $this->get(route('admin.email-configuration.index'))->assertRedirect(route('login'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.email-configuration.index'))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.email-configuration.update'), $this->settingsPayload())
            ->assertForbidden();
        $this->actingAs($admin)->post(route('admin.email-configuration.test'), [
            'recipient' => 'test@example.com',
        ])->assertForbidden();
        $this->assertDatabaseCount('email_smtp_settings', 0);

        $this->actingAs($this->superAdmin)
            ->get(route('admin.email-configuration.index'))
            ->assertOk()
            ->assertSee('Email Configuration');
    }

    public function test_super_admin_can_save_and_update_smtp_settings_without_exposing_password(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.email-configuration.update'), $this->settingsPayload())
            ->assertRedirect(route('admin.email-configuration.index'))
            ->assertSessionHasNoErrors();

        $settings = EmailSmtpSetting::firstOrFail();
        $this->assertSame('smtp.example.com', $settings->host);
        $this->assertSame(587, $settings->port);
        $this->assertSame('VerySecretPassword', $settings->password);
        $this->assertNotSame('VerySecretPassword', $settings->getRawOriginal('password'));
        $this->assertArrayNotHasKey('password', $settings->toArray());

        $this->actingAs($this->superAdmin)
            ->get(route('admin.email-configuration.index'))
            ->assertOk()
            ->assertSee('smtp.example.com')
            ->assertSee('sender@example.com')
            ->assertDontSee('VerySecretPassword');

        $this->actingAs($this->superAdmin)
            ->put(route('admin.email-configuration.update'), $this->settingsPayload([
                'from_name' => 'Updated Sender',
                'password' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('email_smtp_settings', 1);
        $this->assertSame('VerySecretPassword', $settings->fresh()->password);
        $this->assertSame('Updated Sender', $settings->fresh()->from_name);
    }

    public function test_invalid_configuration_is_rejected_and_disabling_restores_env_fallback(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.email-configuration.update'), $this->settingsPayload([
                'host' => 'smtp.example.com/bad',
                'port' => '70000',
                'encryption' => 'invalid',
                'from_address' => 'not-an-email',
            ]))
            ->assertSessionHasErrors(['host', 'port', 'encryption', 'from_address']);
        $this->assertDatabaseCount('email_smtp_settings', 0);

        EmailSmtpSetting::create($this->settingsPayload(['enabled' => 0]));
        $service = app(EmailSmtpService::class);
        config()->set('mail.default', 'log');
        $this->assertSame('unconfigured', $service->source());

        config()->set('mail.default', 'smtp');
        $this->assertSame('environment', $service->source());

        $settings = EmailSmtpSetting::firstOrFail();
        $settings->update(['enabled' => true]);
        $this->assertSame('database', $service->source());
    }

    public function test_transport_enforces_selected_security_and_sender_identity(): void
    {
        $settings = EmailSmtpSetting::create($this->settingsPayload());
        $service = app(EmailSmtpService::class);

        $this->assertSame(['sender@example.com', 'CRM Sender'], $service->sender());
        $this->assertSame('database', $service->source());
        $this->assertSame([
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'smtp-user',
            'password' => 'VerySecretPassword',
            'auto_tls' => true,
            'require_tls' => true,
            'timeout' => 15,
        ], $service->transportConfiguration($settings));

        $settings->update(['encryption' => 'ssl', 'port' => 465]);
        $this->assertSame('smtps', $service->transportConfiguration($settings)['scheme']);

        $settings->update(['encryption' => 'none']);
        $this->assertFalse($service->transportConfiguration($settings)['auto_tls']);
        $this->assertFalse($service->transportConfiguration($settings)['require_tls']);
    }

    public function test_test_email_uses_configured_mailer_and_sender(): void
    {
        $mailer = Mail::mailer('array');
        $service = $this->mock(EmailSmtpService::class);
        $service->shouldReceive('isReady')->once()->andReturn(true);
        $service->shouldReceive('mailer')->once()->andReturn($mailer);
        $service->shouldReceive('sender')->once()->andReturn(['sender@example.com', 'CRM Sender']);

        $this->actingAs($this->superAdmin)->post(route('admin.email-configuration.test'), [
            'recipient' => 'recipient@example.com',
        ])->assertSessionHas('success');

        $messages = $mailer->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $raw = $messages[0]->getMessage()->toString();
        $this->assertStringContainsString('recipient@example.com', $raw);
        $this->assertStringContainsString('sender@example.com', $raw);
        $this->assertStringContainsString('CRM SMTP test', $raw);
    }

    public function test_test_email_without_configuration_is_rejected(): void
    {
        config()->set('mail.default', 'log');

        $this->actingAs($this->superAdmin)->post(route('admin.email-configuration.test'), [
            'recipient' => 'recipient@example.com',
        ])->assertSessionHas('error');
    }

    public function test_email_campaign_worker_uses_super_admin_selected_mailer(): void
    {
        $template = EmailTemplate::create([
            'name' => 'News', 'subject' => 'Hello', 'html_body' => 'News update',
        ]);
        $campaign = EmailCampaign::create([
            'name' => 'News', 'email_template_id' => $template->id, 'status' => 'queued',
            'recipient_count' => 1,
        ]);
        $recipient = $campaign->recipients()->create(['email' => 'customer@example.com']);
        $mailer = Mail::mailer('array');
        $service = $this->mock(EmailSmtpService::class);
        $service->shouldReceive('mailer')->once()->andReturn($mailer);
        $service->shouldReceive('sender')->once()->andReturn(['campaign@example.com', 'Campaign Team']);

        app()->call([new SendEmailCampaignBatch($campaign->id, 0, 100), 'handle']);

        $this->assertSame('sent', $recipient->fresh()->status);
        $this->assertStringContainsString('campaign@example.com',
            $mailer->getSymfonyTransport()->messages()[0]->getMessage()->toString());
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => 1,
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp-user',
            'password' => 'VerySecretPassword',
            'from_name' => 'CRM Sender',
            'from_address' => 'sender@example.com',
        ], $overrides);
    }
}
