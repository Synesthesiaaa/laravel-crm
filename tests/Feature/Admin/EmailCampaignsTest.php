<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendEmailCampaignBatch;
use App\Models\EmailCampaign;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailCampaignDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailCampaignsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_only_super_admin_can_access_campaigns_and_create_templates(): void
    {
        $this->get(route('admin.email-campaigns.index'))->assertRedirect(route('login'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('admin.email-campaigns.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.email-campaigns.templates.store'), $this->templatePayload())
            ->assertForbidden();
        $this->assertDatabaseCount('email_templates', 0);

        $this->actingAs($this->superAdmin)->get(route('admin.email-campaigns.index'))
            ->assertOk()->assertSee('Email Campaigns');
    }

    public function test_super_admin_can_download_a_recipient_csv_template_with_import_compatible_columns(): void
    {
        $downloadUrl = route('admin.email-campaigns.recipients-template');
        $this->get($downloadUrl)->assertRedirect(route('login'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get($downloadUrl)->assertForbidden();

        $this->actingAs($this->superAdmin)
            ->get(route('admin.email-campaigns.index'))
            ->assertOk()
            ->assertSee('Download CSV template')
            ->assertSee($downloadUrl);

        $response = $this->actingAs($this->superAdmin)
            ->get($downloadUrl)
            ->assertOk()
            ->assertDownload('email-recipients-template.csv')
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertSame(['email', 'name'], $rows[0]);
        $this->assertSame(['jane.doe@example.com', 'Jane Doe'], $rows[1]);
        $this->assertSame(['john.smith@example.com', 'John Smith'], $rows[2]);
    }

    public function test_templates_require_pdf_password_and_store_it_encrypted(): void
    {
        $this->actingAs($this->superAdmin)->post(
            route('admin.email-campaigns.templates.store'),
            $this->templatePayload(['pdf_enabled' => '1', 'pdf_body' => 'Hello {{name}}']),
        )->assertSessionHasErrors('pdf_password');

        $this->actingAs($this->superAdmin)->post(
            route('admin.email-campaigns.templates.store'),
            $this->templatePayload([
                'pdf_enabled' => '1',
                'pdf_body' => 'Hello {{name}}',
                'pdf_password' => 'VerySecret123',
            ]),
        )->assertSessionHasNoErrors();

        $template = EmailTemplate::firstOrFail();
        $this->assertSame('VerySecret123', $template->pdf_password);
        $this->assertNotSame('VerySecret123', $template->getRawOriginal('pdf_password'));
        $this->assertTrue($template->pdf_enabled);

        $pdf = app(EmailCampaignDocumentService::class)->renderPdf($template, 'Jane', 'jane@example.com');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Encrypt', $pdf);
    }

    public function test_ocr_import_creates_an_editable_template(): void
    {
        $this->mock(EmailCampaignDocumentService::class)
            ->shouldReceive('extractText')
            ->once()
            ->andReturn("Invoice confirmation\nThank you for your purchase.");

        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.import'), [
            'document' => UploadedFile::fake()->image('invoice.png'),
        ])->assertSessionHasNoErrors();

        $template = EmailTemplate::firstOrFail();
        $this->assertSame('Invoice confirmation', $template->subject);
        $this->assertStringContainsString('Thank you', $template->html_body);

        $this->actingAs($this->superAdmin)->put(
            route('admin.email-campaigns.templates.update', $template),
            $this->templatePayload([
                'pdf_enabled' => 1,
                'pdf_body' => 'Updated PDF',
                'pdf_password' => 'UnsharedPassword',
            ]),
        )->assertSessionHasNoErrors();

        $this->assertSame('Updated PDF', $template->fresh()->pdf_body);
        $this->assertTrue($template->fresh()->pdf_enabled);
    }

    public function test_imported_recipients_are_deduplicated_and_opted_out_users_excluded(): void
    {
        $template = EmailTemplate::create($this->templatePayload());
        EmailOptOut::create(['email' => 'blocked@example.com']);

        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.store'), [
            'name' => 'October update',
            'email_template_id' => $template->id,
            'recipient_csv' => UploadedFile::fake()->createWithContent('recipients.csv', "email,name\nA@example.com,Alice\na@example.com,Duplicate\nblocked@example.com,Blocked\nb@example.com,Bob\n"),
            'batch_size' => 100,
            'delay_seconds' => 60,
            'confirmed_permission' => 1,
        ])->assertSessionHasNoErrors();

        $campaign = EmailCampaign::firstOrFail();
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);
        $this->assertSame(2, $campaign->recipients()->count());
        $this->assertDatabaseMissing('email_campaign_recipients', ['email' => 'blocked@example.com']);
    }

    public function test_csv_validation_and_permission_confirmation_block_import(): void
    {
        $template = EmailTemplate::create($this->templatePayload());
        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.store'), [
            'name' => 'Bad list',
            'email_template_id' => $template->id,
            'recipient_csv' => UploadedFile::fake()->createWithContent('recipients.csv', "email\ninvalid-address\n"),
            'batch_size' => 100,
            'delay_seconds' => 60,
        ])->assertSessionHasErrors('confirmed_permission');
        $this->assertDatabaseCount('email_campaigns', 0);

        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.store'), [
            'name' => 'Bad list',
            'email_template_id' => $template->id,
            'recipient_csv' => UploadedFile::fake()->createWithContent('recipients.csv', "email\ninvalid-address\n"),
            'batch_size' => 100,
            'delay_seconds' => 60,
            'confirmed_permission' => 1,
        ])->assertSessionHasErrors('recipient_csv');
        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_starting_campaign_queues_batches_without_sending_inline(): void
    {
        Queue::fake();
        $template = EmailTemplate::create($this->templatePayload());
        $campaign = EmailCampaign::create([
            'name' => 'Batch test',
            'email_template_id' => $template->id,
            'batch_size' => 100,
            'delay_seconds' => 30,
            'recipient_count' => 205,
        ]);
        foreach (range(1, 205) as $index) {
            $campaign->recipients()->create(['email' => "user{$index}@example.com"]);
        }

        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.send', $campaign))
            ->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmailCampaignBatch::class, 3);
        $this->assertSame('queued', $campaign->fresh()->status);
        $this->assertSame(0, $campaign->fresh()->sent_count);
        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.send', $campaign))
            ->assertSessionHasErrors('campaign');
        Queue::assertPushed(SendEmailCampaignBatch::class, 3);
    }

    public function test_cancelled_campaign_is_not_delivered_and_unsubscribe_is_signed(): void
    {
        $template = EmailTemplate::create($this->templatePayload());
        $campaign = EmailCampaign::create([
            'name' => 'Cancel test', 'email_template_id' => $template->id, 'recipient_count' => 1,
        ]);
        $recipient = $campaign->recipients()->create(['email' => 'receiver@example.com']);
        $this->actingAs($this->superAdmin)->post(route('admin.email-campaigns.cancel', $campaign))->assertRedirect();
        app()->call([new SendEmailCampaignBatch($campaign->id, 0, 100), 'handle']);
        $this->assertSame('pending', $recipient->fresh()->status);
        $this->assertSame(0, $campaign->fresh()->sent_count);

        $url = URL::signedRoute('email-campaigns.unsubscribe', ['recipient' => $recipient->id]);
        $this->get($url)->assertOk()->assertSee('Unsubscribe');
        $this->post($url)->assertRedirect();
        $this->assertDatabaseHas('email_opt_outs', ['email' => 'receiver@example.com']);
        $this->get(route('email-campaigns.unsubscribe', ['recipient' => $recipient->id]))->assertForbidden();
    }

    public function test_batch_worker_delivers_individual_mail_and_updates_counters(): void
    {
        $template = EmailTemplate::create($this->templatePayload([
            'html_body' => 'Hi {{name}}',
            'pdf_body' => 'Welcome {{name}}',
            'pdf_password' => 'SecretPass123',
            'pdf_enabled' => true,
        ]));
        $campaign = EmailCampaign::create([
            'name' => 'Delivery test', 'email_template_id' => $template->id, 'recipient_count' => 2,
            'status' => 'queued',
        ]);
        $alice = $campaign->recipients()->create(['email' => 'alice@example.com', 'name' => 'Alice']);
        $optedOut = $campaign->recipients()->create(['email' => 'no-mail@example.com']);
        EmailOptOut::create(['email' => 'no-mail@example.com']);

        app()->call([new SendEmailCampaignBatch($campaign->id, 0, 100), 'handle']);

        $this->assertSame('sent', $alice->fresh()->status);
        $this->assertSame('skipped', $optedOut->fresh()->status);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(1, $campaign->fresh()->skipped_count);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('alice@example.com', $messages[0]->getMessage()->toString());
    }

    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Monthly email',
            'subject' => 'Hello {{name}}',
            'html_body' => 'Here is the update.',
        ], $overrides);
    }
}
