<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendEmailCampaignRecipient;
use App\Models\EmailCampaign;
use App\Models\EmailOptOut;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\EmailCampaignDeliveryService;
use App\Services\EmailPdfInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class EmailDocumentDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private EmailTemplate $template;

    private array $messages = [];

    private array $attachmentHashes = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('email_documents');
        config(['mail.default' => 'array', 'mail.from.address' => 'sender@example.com']);
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->template = EmailTemplate::create(['name' => 'Synthetic notice', 'subject' => 'For {{name}}', 'html_body' => 'Notice {{employee_id}} for {{period}}']);
        // PDF parseability is exercised separately against the real inspector's fail-closed boundary.
        // Mapping/transport tests use a fake inspector and synthetic TCPDF documents only.
        $this->mock(EmailPdfInspector::class)->shouldReceive('inspect')->andReturnNull();
        Event::listen(MessageSent::class, function ($event): void {
            $message = $event->sent->getOriginalMessage();
            $this->messages[] = $message;
            $this->attachmentHashes[] = array_map(fn ($part) => hash('sha256', base64_decode($part->bodyToString())), $message->getAttachments());
        });
    }

    public function test_personal_documents_are_individually_mapped_frozen_and_sent_without_cc_bcc(): void
    {
        Queue::fake();
        $alicePdf = $this->pdf('Alice synthetic');
        $bobPdf = $this->pdf('Bob synthetic');
        $campaign = $this->createPersonal($alicePdf, $bobPdf);
        $this->start($campaign);
        Queue::assertPushed(SendEmailCampaignRecipient::class, 2);
        $this->template->update(['subject' => 'CHANGED', 'html_body' => 'CHANGED']);
        foreach ($campaign->recipients()->orderBy('id')->get() as $recipient) {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        }
        $this->assertCount(2, $this->messages);
        foreach ($this->messages as $index => $message) {
            $this->assertCount(1, $message->getTo());
            $this->assertSame([], $message->getCc());
            $this->assertSame([], $message->getBcc());
            $this->assertSame($index === 0 ? 'Alice@example.com' : 'bob@example.com', $message->getTo()[0]->getAddress());
            $this->assertCount(1, $message->getAttachments());
            $this->assertSame(hash('sha256', $index === 0 ? $alicePdf : $bobPdf), $this->attachmentHashes[$index][0]);
            $this->assertStringNotContainsString('CHANGED', $message->getSubject().$message->getHtmlBody());
            $this->assertStringNotContainsString('subscribed', $message->getHtmlBody());
            $this->assertFalse($message->getHeaders()->has('List-Unsubscribe'));
        }
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(2, $campaign->fresh()->sent_count);
        $this->assertSame(2, $campaign->recipients()->where('status', 'accepted')->count());
        $document = $campaign->documents()->firstOrFail();
        $this->assertStringNotContainsString('%PDF-', Storage::disk('email_documents')->get($document->storage_key));
    }

    public function test_shared_and_mixed_documents_include_only_intended_files(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal($this->pdf('Alice'), $this->pdf('Bob'), ['attachment_mode' => 'shared_and_recipient_specific', 'shared_pdfs' => [$this->file('policy.pdf', $this->pdf('Policy'))]]);
        $this->start($campaign);
        foreach ($campaign->recipients()->get() as $recipient) {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        }
        $this->assertCount(2, $this->messages);
        foreach ($this->messages as $message) {
            $this->assertCount(2, $message->getAttachments());
            $names = array_map(fn ($p) => $p->getFilename(), $message->getAttachments());
            $this->assertContains('policy.pdf', $names);
            $this->assertContains($message->getTo()[0]->getAddress() === 'Alice@example.com' ? 'alice.pdf' : 'bob.pdf', $names);
        }
    }

    public function test_invalid_mapping_rolls_back_files_and_campaign(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['personal_pdfs' => [$this->file('alice.pdf', $this->pdf('Alice'))]]))
            ->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('email_campaigns', 0);
        $this->assertSame([], Storage::disk('email_documents')->allFiles());
    }

    public function test_manifest_rejects_duplicate_case_collisions_missing_headers_and_formulas(): void
    {
        foreach ([
            "email,pdf_filename\nAlice@example.com,alice.pdf\nalice@example.com,bob.pdf\n",
            "email,pdf_filename\nalice@example.com,alice.pdf\nbob@example.com,ALICE.pdf\n",
            "email,name\nalice@example.com,Alice\n",
            "email,name,pdf_filename\nalice@example.com,=IMPORTDATA(),alice.pdf\n",
            "email,pdf_filename\nalice@example.com,../alice.pdf\n",
        ] as $csv) {
            $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['recipient_csv' => $this->file('recipients.csv', $csv)]))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_zip_traversal_symlinks_duplicate_paths_and_limits_fail_closed(): void
    {
        foreach (['../alice.pdf', 'C:\\alice.pdf', '/alice.pdf', 'nested.zip', 'folder/alice.pdf', 'symlink.pdf'] as $name) {
            $zip = $this->zip([$name => $this->pdf('Synthetic')], $name === 'symlink.pdf');
            $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['personal_pdfs' => [], 'document_zip' => $zip]))->assertSessionHasErrors('documents');
        }
        config(['email_campaigns.max_total_bytes' => 20]);
        $zip = $this->zip(['alice.pdf' => $this->pdf('Alice'), 'bob.pdf' => $this->pdf('Bob')]);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['personal_pdfs' => [], 'document_zip' => $zip]))->assertSessionHasErrors();
        $this->assertSame([], Storage::disk('email_documents')->allFiles());
    }

    public function test_valid_zip_and_bom_case_headers_map_exact_filenames(): void
    {
        $zip = $this->zip(['alice.pdf' => $this->pdf('Alice'), 'bob.pdf' => $this->pdf('Bob')]);
        $csv = "\xEF\xBB\xBFEMAIL,Name,Employee_ID,Period,PDF_Filename\nAlice@EXAMPLE.COM,Alice,A,2026-09,alice.pdf\nbob@example.com,Bob,B,2026-09,bob.pdf\n";
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['personal_pdfs' => [], 'document_zip' => $zip, 'recipient_csv' => $this->file('recipients.csv', $csv)]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('email_campaign_documents', 2);
        $this->assertDatabaseHas('email_campaign_recipients', ['email' => 'Alice@example.com']);
        $this->assertSame([], glob(storage_path('app/private/email-imports/*')) ?: []);
    }

    public function test_modified_missing_documents_and_snapshot_tampering_never_reach_transport(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $document = $campaign->documents()->firstOrFail();
        $original = Storage::disk('email_documents')->get($document->storage_key);
        Storage::disk('email_documents')->put($document->storage_key, 'corrupt');
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.dry-run', $campaign))->assertSessionHasErrors('campaign');
        Storage::disk('email_documents')->put($document->storage_key, $original);
        $this->start($campaign);
        Storage::disk('email_documents')->delete($document->storage_key);
        $recipient = $campaign->recipients()->firstOrFail();
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        $this->assertSame('blocked', $recipient->fresh()->status);
        $bob = $campaign->recipients()->orderByDesc('id')->firstOrFail();
        $bob->update(['email' => 'intruder@example.com']);
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $bob->id);
        $this->assertSame('blocked', $bob->fresh()->status);
        $this->assertCount(0, $this->messages);
    }

    public function test_confirmation_and_matching_dry_run_are_required(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.send', $campaign))->assertSessionHasErrors('confirmed_mapping');
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.send', $campaign), ['confirmed_mapping' => 1, 'confirmed_live_send' => 1])->assertSessionHasErrors('campaign');
        Queue::assertNotPushed(SendEmailCampaignRecipient::class);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_private_documents_reports_and_actions_are_super_admin_only(): void
    {
        $campaign = $this->createPersonal();
        $document = $campaign->documents()->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.email-campaigns.document', [$campaign, $document]))->assertOk()->assertHeader('cache-control', 'no-store, private');
        $report = $this->get(route('admin.email-campaigns.report', $campaign))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('example.com', $report);
        $this->assertStringNotContainsString('.pdf', $report);
        foreach ([User::ROLE_ADMIN, User::ROLE_AGENT, User::ROLE_TEAM_LEADER] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['review', 'report'] as $action) {
                $this->actingAs($user)->get(route('admin.email-campaigns.'.$action, $campaign))->assertForbidden();
            }
            $this->get(route('admin.email-campaigns.document', [$campaign, $document]))->assertForbidden();
            $this->post(route('admin.email-campaigns.send', $campaign))->assertForbidden();
            $this->post(route('admin.email-campaigns.dry-run', $campaign))->assertForbidden();
            $this->post(route('admin.email-campaigns.retry', $campaign))->assertForbidden();
        }
        $other = EmailCampaign::create(['name' => 'Other', 'email_template_id' => $this->template->id]);
        $this->actingAs($this->admin)->get(route('admin.email-campaigns.document', [$other, $document]))->assertNotFound();
    }

    public function test_payroll_policy_blocks_dry_run_and_transactional_ignores_marketing_opt_out(): void
    {
        Queue::fake();
        EmailOptOut::create(['email' => 'alice@example.com']);
        $campaign = $this->createPersonal();
        $campaign->update(['purpose' => 'payroll']);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.dry-run', $campaign))->assertSessionHasErrors('campaign');
        $campaign->update(['purpose' => 'transactional']);
        $this->start($campaign);
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $campaign->recipients()->firstOrFail()->id);
        $this->assertCount(1, $this->messages);
    }

    public function test_worker_interruptions_remain_uncertain_and_cannot_be_blindly_retried(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        foreach ($campaign->recipients()->get() as $recipient) {
            $recipient->forceFill(['status' => 'sending', 'claimed_at' => now()->subMinutes(5)])->save();
        }
        $this->artisan('email-campaigns:maintain')->assertSuccessful();
        $this->assertSame(2, $campaign->recipients()->where('status', 'uncertain')->count());
        $this->assertSame('needs_attention', $campaign->fresh()->status);
        $this->assertSame(2, $campaign->fresh()->failed_count);
        $this->assertSame(2, $campaign->fresh()->attention_count);
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.retry', $campaign), ['confirmed_retry' => 1])->assertSessionHasErrors('campaign');
        foreach ($campaign->recipients()->get() as $recipient) {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        }
        $this->assertCount(0, $this->messages);
    }

    public function test_periodic_campaign_reconciliation_repairs_drift_without_sending(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        $recipients = $campaign->recipients()->orderBy('id')->get();
        $recipients[0]->forceFill(['status' => 'accepted', 'sent_at' => now()])->save();
        $campaign->forceFill([
            'status' => 'sending',
            'sent_count' => 99,
            'failed_count' => 88,
            'skipped_count' => 77,
            'open_count' => 66,
            'attention_count' => 55,
        ])->save();
        \Illuminate\Support\Facades\Mail::shouldReceive('build')->never();

        $this->artisan('email-campaigns:maintain')->assertSuccessful();

        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(0, $campaign->fresh()->skipped_count);
        $this->assertSame(1, $campaign->fresh()->open_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
        $this->assertSame('sending', $campaign->fresh()->status);
        $this->assertCount(0, $this->messages);
    }

    public function test_retention_purges_cancelled_and_draft_documents(): void
    {
        $campaign = $this->createPersonal();
        $campaign->update(['status' => 'cancelled']);
        $campaign->documents()->update(['expires_at' => now()->subDay()]);
        $this->artisan('email-campaigns:maintain')->assertSuccessful();
        $this->assertSame([], Storage::disk('email_documents')->allFiles());
        $this->assertSame(2, $campaign->documents()->whereNotNull('purged_at')->count());
    }

    public function test_real_inspector_rejects_non_pdf_and_unavailable_parser(): void
    {
        $file = $this->file('fake.pdf', 'not a PDF');

        try {
            (new EmailPdfInspector)->inspect($file->getRealPath());
            $this->fail('Accepted non-PDF');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documents', $e->errors());
        }
        config(['email_campaigns.pdfinfo' => 'nonexistent-approved-pdfinfo']);
        $valid = $this->file('valid.pdf', $this->pdf('Valid'));

        try {
            (new EmailPdfInspector)->inspect($valid->getRealPath());
            $this->fail('Accepted unavailable parser');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documents', $e->errors());
        }
    }

    public function test_shared_only_and_generated_template_pdfs_remain_explicit(): void
    {
        Queue::fake();
        $this->template->update(['html_body' => 'Notice {{name}}', 'pdf_enabled' => true, 'pdf_body' => 'Synthetic {{name}}', 'pdf_password' => 'Synthetic123']);
        $shared = $this->pdf('Shared synthetic policy');
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload([
            'attachment_mode' => 'shared', 'recipient_csv' => $this->file('recipients.csv', "email,name\nalice@example.com,Alice\nbob@example.com,Bob\n"),
            'shared_pdfs' => [$this->file('policy.pdf', $shared)],
        ]))->assertSessionHasNoErrors();
        $campaign = EmailCampaign::latest('id')->firstOrFail();
        $this->start($campaign);
        foreach ($campaign->recipients()->get() as $recipient) {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        }
        $this->assertCount(2, $this->messages);
        foreach ($this->messages as $index => $message) {
            $this->assertCount(2, $message->getAttachments());
            $this->assertSame(hash('sha256', $shared), $this->attachmentHashes[$index][0]);
            $this->assertSame('generated-template.pdf', $message->getAttachments()[1]->getFilename());
        }
    }

    public function test_draft_edits_invalidate_approval_and_queued_campaign_cannot_change(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.dry-run', $campaign))->assertSessionHasNoErrors();
        $data = ['name' => 'Revised draft', 'email_template_id' => $this->template->id, 'batch_size' => 25, 'delay_seconds' => 1];
        $this->put(route('admin.email-campaigns.update', $campaign), $data)->assertSessionHasNoErrors();
        $this->assertNull($campaign->fresh()->dry_run_hash);
        $this->start($campaign);
        $this->assertNotNull($campaign->fresh()->started_at);
        $this->put(route('admin.email-campaigns.update', $campaign), $data)->assertForbidden();
    }

    public function test_cancel_stops_new_claims_and_recovery_excludes_uncertain_rows(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        $alice = $campaign->recipients()->firstOrFail();
        $alice->update(['status' => 'uncertain']);
        Queue::fake();
        $this->artisan('email-campaigns:maintain', ['--recover' => true])->assertSuccessful();
        Queue::assertPushed(SendEmailCampaignRecipient::class, 1);
        Queue::assertPushed(SendEmailCampaignRecipient::class, fn ($job) => $job->recipientId !== $alice->id);
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.cancel', $campaign))->assertSessionHasNoErrors();
        foreach ($campaign->recipients()->get() as $recipient) {
            app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        }
        $this->assertSame(1, $campaign->recipients()->where('status', 'cancelled')->count());
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->assertSame(1, $campaign->fresh()->attention_count);
        $this->assertCount(0, $this->messages);
    }

    public function test_synthetic_self_test_contains_no_employee_attachments(): void
    {
        $campaign = $this->createPersonal();
        $this->mock(\App\Services\EmailSmtpService::class, function ($mock): void {
            $mock->shouldReceive('isReady')->andReturnTrue();
            $mock->shouldReceive('sender')->andReturn(['sender@example.com', 'Synthetic Sender']);
            $mock->shouldReceive('mailer')->andReturn(\Illuminate\Support\Facades\Mail::mailer('array'));
        });
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.test-self', $campaign))->assertSessionHasNoErrors();
        $this->assertCount(1, $this->messages);
        $this->assertSame($this->admin->email, $this->messages[0]->getTo()[0]->getAddress());
        $this->assertSame([], $this->messages[0]->getAttachments());
        $this->assertStringNotContainsString('Alice', $this->messages[0]->getHtmlBody());
    }

    public function test_synthetic_self_test_works_locally_with_bulk_sending_disabled(): void
    {
        $campaign = $this->createPersonal();
        $this->app['env'] = 'local';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        config(['email_campaigns.live_enabled' => false]);
        $this->mock(\App\Services\EmailSmtpService::class, function ($mock): void {
            $mock->shouldReceive('isReady')->andReturnTrue();
            $mock->shouldReceive('sender')->andReturn(['sender@example.com', 'Synthetic Sender']);
            $mock->shouldReceive('mailer')->andReturn(\Illuminate\Support\Facades\Mail::mailer('array'));
        });
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.test-self', $campaign), ['recipient' => 'someone-else@example.com'])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->messages);
        $this->assertSame($this->admin->email, $this->messages[0]->getTo()[0]->getAddress());
        $this->assertCount(1, $this->messages[0]->getTo());
        $this->assertSame([], $this->messages[0]->getCc());
        $this->assertSame([], $this->messages[0]->getBcc());
        $this->assertSame([], $this->messages[0]->getAttachments());
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->post(route('admin.email-campaigns.send', $campaign))->assertSessionHasErrors('campaign');
        $this->assertCount(1, $this->messages);
    }

    public function test_missing_smtp_is_a_separate_test_error_without_invalidating_manifest(): void
    {
        $campaign = $this->createPersonal();
        config(['mail.default' => 'array']);
        $this->actingAs($this->admin)->from(route('admin.email-campaigns.review', $campaign))
            ->post(route('admin.email-campaigns.test-self', $campaign))
            ->assertSessionHasErrors(['smtp'], null, 'syntheticTest');
        $this->get(route('admin.email-campaigns.review', $campaign))->assertOk()
            ->assertSee('Synthetic test needs attention')
            ->assertSee('Email Configuration')
            ->assertDontSee('Correct the manifest or files and create a replacement draft.');
        $this->assertSame('draft', $campaign->fresh()->status);
        $this->assertCount(0, $this->messages);
    }

    public function test_synthetic_transport_failure_is_sanitized_and_separate_from_mapping_errors(): void
    {
        $campaign = $this->createPersonal();
        $mailer = \Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('html')->once()->andThrow(new \RuntimeException('password-secret alice@example.com'));
        $this->mock(\App\Services\EmailSmtpService::class, function ($mock) use ($mailer): void {
            $mock->shouldReceive('isReady')->andReturnTrue();
            $mock->shouldReceive('sender')->andReturn(['sender@example.com', 'Synthetic Sender']);
            $mock->shouldReceive('mailer')->andReturn($mailer);
        });
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.test-self', $campaign))
            ->assertSessionHasErrors(['smtp'], null, 'syntheticTest');
        $errors = session('errors')->getBag('syntheticTest')->all();
        $this->assertStringNotContainsString('password-secret', implode(' ', $errors));
        $this->assertStringNotContainsString('alice@example.com', implode(' ', $errors));
        $this->assertCount(0, $this->messages);
    }

    public function test_transport_exception_is_uncertain_and_error_is_sanitized(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        $mailer = \Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('html')->once()->andThrow(new \RuntimeException('SMTP password-secret alice@example.com'));
        \Illuminate\Support\Facades\Mail::shouldReceive('build')->once()->andReturn($mailer);
        $alice = $campaign->recipients()->firstOrFail();
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $alice->id);
        $this->assertSame('uncertain', $alice->fresh()->status);
        $this->assertStringNotContainsString('password-secret', $alice->fresh()->error);
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $alice->id);
        $this->assertCount(0, $this->messages);
    }

    public function test_invalid_archive_and_extra_personal_documents_fail_cleanly(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload(['document_zip' => $this->file('bad.zip', 'not an archive')]))->assertSessionHasErrors('documents');
        $this->post(route('admin.email-campaigns.store'), $this->payload(['personal_pdfs' => [$this->file('extra.pdf', $this->pdf('Extra'))]]))->assertSessionHasErrors('documents');
        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_new_scanning_policy_blocks_previously_unscanned_documents(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        config(['email_campaigns.scan_required' => true]);
        $recipient = $campaign->recipients()->firstOrFail();
        app(EmailCampaignDeliveryService::class)->send($campaign->id, $recipient->id);
        $this->assertSame('blocked', $recipient->fresh()->status);
        $this->assertCount(0, $this->messages);
    }

    public function test_reconciliation_requires_provider_evidence_and_never_sends(): void
    {
        Queue::fake();
        $campaign = $this->createPersonal();
        $this->start($campaign);
        $recipient = $campaign->recipients()->firstOrFail();
        $recipient->update(['status' => 'uncertain']);
        $url = route('admin.email-campaigns.reconcile', [$campaign, $recipient]);
        $campaign->recipients()->where('id', '!=', $recipient->id)->update(['status' => 'accepted']);
        $campaign->update(['status' => 'needs_attention']);
        $this->actingAs($this->admin)->post($url, ['outcome' => 'accepted'])->assertSessionHasErrors('confirmed_evidence');
        $this->post($url, ['outcome' => 'accepted', 'provider_case' => 'CASE-123', 'accepted_at' => now()->subMinute()->toIso8601String(), 'confirmed_evidence' => 1])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertSame('completed', $campaign->fresh()->status);
        $this->assertSame(2, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
        $this->assertDatabaseHas('activity_log', ['event' => 'reconciliation']);
        $this->assertCount(0, $this->messages);
        $this->post($url, ['outcome' => 'not_submitted', 'provider_case' => 'CASE-123', 'confirmed_evidence' => 1])->assertSessionHasErrors('campaign');
    }

    private function createPersonal(?string $alice = null, ?string $bob = null, array $overrides = []): EmailCampaign
    {
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.store'), $this->payload([
            'personal_pdfs' => [$this->file('alice.pdf', $alice ?? $this->pdf('Alice')), $this->file('bob.pdf', $bob ?? $this->pdf('Bob'))], ...$overrides,
        ]))->assertSessionHasNoErrors();

        return EmailCampaign::latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Synthetic personal campaign', 'email_template_id' => $this->template->id,
            'purpose' => 'transactional', 'attachment_mode' => 'recipient_specific',
            'batch_size' => 25, 'delay_seconds' => 1, 'confirmed_permission' => 1,
            'recipient_csv' => $this->file('recipients.csv', "email,name,employee_id,period,pdf_filename\nAlice@example.com,Alice,A,2026-09,alice.pdf\nbob@example.com,Bob,B,2026-09,bob.pdf\n"),
        ], $overrides);
    }

    private function start(EmailCampaign $campaign): void
    {
        $this->actingAs($this->admin)->post(route('admin.email-campaigns.dry-run', $campaign))->assertSessionHasNoErrors();
        $this->post(route('admin.email-campaigns.send', $campaign), ['confirmed_mapping' => 1, 'confirmed_live_send' => 1])->assertSessionHasNoErrors();
    }

    private function file(string $name, string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function pdf(string $text): string
    {
        $pdf = new \TCPDF;
        $pdf->AddPage();
        $pdf->Write(10, $text);

        return $pdf->Output('synthetic.pdf', 'S');
    }

    private function zip(array $files, bool $symlink = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'email-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($files as $name => $bytes) {
            $zip->addFromString($name, $bytes);
            if ($symlink) {
                $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, 0120777 << 16);
            }
        }
        $zip->close();
        $content = file_get_contents($path);
        unlink($path);

        return $this->file('documents.zip', $content);
    }
}
