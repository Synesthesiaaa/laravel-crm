<?php

namespace Tests\Feature\Admin;

use App\Jobs\PrepareProtectedEmailImport;
use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\ProtectedEmailImportService;
use App\Services\EmailPdfInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProtectedEmailImportsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private EmailTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        Storage::fake('email_documents');
        $this->owner = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->template = EmailTemplate::create(['created_by_user_id' => $this->owner->id, 'name' => 'Private notice', 'subject' => 'Notice', 'html_body' => 'Hello {{name}}']);
        $this->mock(EmailPdfInspector::class)->shouldReceive('inspect')->andReturnNull();
    }

    public function test_upload_stages_only_encrypted_chunks_and_background_preparation_creates_one_exact_private_draft(): void
    {
        $csv = UploadedFile::fake()->createWithContent('recipients.csv', "email,name,pdf_filename\nalice@example.com,Alice,Alice.pdf\nbob@example.com,Bob,Bob.pdf\n");
        $alice = UploadedFile::fake()->createWithContent('Alice.pdf', '%PDF-1.4 Alice private content');
        $bob = UploadedFile::fake()->createWithContent('Bob.pdf', '%PDF-1.4 Bob private content');
        $service = app(ProtectedEmailImportService::class);
        $run = $service->stage($this->owner, $this->data() + ['purpose' => 'transactional', 'attachment_mode' => 'recipient_specific'], $csv, [$alice, $bob]);
        $this->assertSame('queued', $run->status);
        $this->assertDatabaseCount('email_campaigns', 0);
        Queue::assertPushed(PrepareProtectedEmailImport::class);
        foreach (Storage::disk('local')->allFiles('email-protected-imports/'.$run->id) as $path) {
            $bytes = Storage::disk('local')->get($path);
            $this->assertStringNotContainsString('private content', $bytes);
            $this->assertStringNotContainsString('alice@example.com', $bytes);
            $this->assertNotSame('', Crypt::decryptString($bytes));
        }
        $service->process($run->id);
        $this->assertSame('ready', $run->refresh()->status);
        $campaign = EmailCampaign::findOrFail($run->email_campaign_id);
        $this->assertSame('draft', $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);
        foreach ($campaign->recipients as $recipient) {
            $document = $campaign->documents()->where('email_campaign_recipient_id', $recipient->id)->sole();
            $this->assertSame($recipient->pdf_filename, $document->filename);
        }
        $service->process($run->id);
        $this->assertDatabaseCount('email_campaigns', 1);
        $this->assertSame([], Storage::disk('local')->allFiles('email-protected-imports/'.$run->id));
    }

    public function test_chunk_tampering_fails_closed_without_any_campaign_or_unencrypted_workspace(): void
    {
        $service = app(ProtectedEmailImportService::class);
        $run = $service->stage($this->owner, $this->data(), UploadedFile::fake()->createWithContent('recipients.csv', "email,name\na@example.com,Alice\n"));
        $path = Storage::disk('local')->allFiles('email-protected-imports/'.$run->id)[0];
        Storage::disk('local')->put($path, 'modified ciphertext');
        $service->process($run->id);
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertDatabaseCount('email_campaigns', 0);
        $this->assertStringNotContainsString('modified ciphertext', $run->error ?? '');
    }

    public function test_cancelled_or_expired_import_never_creates_a_draft_and_removes_private_sources(): void
    {
        $service = app(ProtectedEmailImportService::class);
        $run = $service->stage($this->owner, $this->data(), UploadedFile::fake()->createWithContent('recipients.csv', "email,name\na@example.com,Alice\n"));
        $service->cancel($this->owner, $run);
        $service->process($run->id);
        $this->assertSame('cancelled', $run->refresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('email-protected-imports/'.$run->id));
        $this->assertDatabaseCount('email_campaigns', 0);
    }

    public function test_admin_cannot_stage_private_import_and_revoked_super_admin_cannot_finish_one(): void
    {
        $service = app(ProtectedEmailImportService::class);
        $run = $service->stage($this->owner, $this->data(), UploadedFile::fake()->createWithContent('recipients.csv', "email,name\na@example.com,Alice\n"));
        $this->owner->update(['role' => User::ROLE_ADMIN]);
        $service->process($run->id);
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertDatabaseCount('email_campaigns', 0);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->stage($this->owner, $this->data(), UploadedFile::fake()->createWithContent('recipients.csv', 'email'));
    }

    public function test_preparation_job_uses_a_separate_long_timeout_lane(): void
    {
        $job = new PrepareProtectedEmailImport('test-id');
        $this->assertSame(config('email_campaigns.preparation_connection', 'email-preparation'), $job->connection);
        $this->assertSame(config('email_campaigns.preparation_queue', 'email-preparation'), $job->queue);
        $this->assertSame(300, $job->timeout);
        $this->assertSame([30, 120, 600], $job->backoff());
    }

    private function data(): array
    {
        return ['email_template_id' => $this->template->id, 'name' => 'Private draft', 'batch_size' => 10, 'delay_seconds' => 60, 'confirmed_permission' => true];
    }
}
