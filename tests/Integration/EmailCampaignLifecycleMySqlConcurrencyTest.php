<?php

namespace Tests\Integration;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignAttempt;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailProviderEvent;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\CampaignApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Explicitly opt in against an already-migrated disposable test database; never migrates/drops it. */
class EmailCampaignLifecycleMySqlConcurrencyTest extends TestCase
{
    private array $campaignIds = [];

    private array $templateIds = [];

    private array $actorIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('GITHUB_ACTIONS') !== 'true' && getenv('EMAIL_LIFECYCLE_MYSQL_TEST') !== '1') {
            $this->markTestSkipped('Requires explicit opt-in and an already-migrated disposable MySQL test database.');
        }
        $this->assertSame('mysql', config('database.default'));
        $this->assertContains(config('database.connections.mysql.database'), ['laravel_crm_test', 'laravel_crm_email_campaign_test']);
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('email_campaign_attempts'));
        config(['mail.default' => 'array', 'mail.from.address' => 'synthetic@example.test']);
    }

    protected function tearDown(): void
    {
        if ($this->campaignIds) {
            EmailCampaign::whereIn('id', $this->campaignIds)->delete();
            EmailTemplate::whereIn('id', $this->templateIds)->delete();
            User::withTrashed()->whereIn('id', $this->actorIds)->forceDelete();
        }
        parent::tearDown();
    }

    public function test_competing_generation_bound_claims_have_one_winner(): void
    {
        [$campaign, $recipient] = $this->fixture();
        $results = $this->race($campaign, $recipient, ['one' => 'claim', 'two' => 'claim']);
        $this->assertSame(1, collect($results)->where('claimed', true)->count());
        $this->assertSame('sending', $recipient->fresh()->status);
    }

    public function test_competing_due_schedulers_create_one_generation_and_dispatch_intent(): void
    {
        [$campaign, $recipient, $actor] = $this->fixture();
        $campaign->forceFill(['status' => 'draft'])->save();
        app(CampaignApproval::class)->freeze($campaign, $actor->id);
        $campaign->forceFill(['status' => 'scheduled', 'generation' => 0, 'scheduled_at' => now()->subMinute()])->save();
        $this->race($campaign, $recipient, ['one' => 'due', 'two' => 'due']);
        $this->assertSame('queued', $campaign->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->generation);
        $this->assertSame(1, DB::table('email_dispatch_intents')->where('email_campaign_id', $campaign->id)->count());
    }

    public function test_pause_serializes_with_claim_and_invalidates_stale_generation(): void
    {
        [$campaign, $recipient] = $this->fixture();
        $this->race($campaign, $recipient, ['one' => 'claim', 'two' => 'pause']);
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertSame(2, $campaign->fresh()->generation);
        $this->assertContains($recipient->fresh()->status, ['pending', 'sending']);
        $this->assertNull(app(\App\Services\EmailCampaignRecipientTransitionService::class)->claim($campaign->id, $recipient->id, 1));
    }

    public function test_provider_evidence_wins_against_worker_interruption_without_counter_drift(): void
    {
        [$campaign, $recipient] = $this->fixture();
        $recipient->forceFill(['status' => 'sending', 'claimed_at' => now()])->save();
        $attempt = EmailCampaignAttempt::create(['id' => (string) Str::uuid(), 'email_campaign_id' => $campaign->id, 'email_campaign_recipient_id' => $recipient->id,
            'generation' => 1, 'status' => 'submitting', 'provider' => 'postmark', 'entered_transport_at' => now()]);
        EmailProviderEvent::create(['event_key' => hash('sha256', $attempt->id), 'fingerprint' => hash('sha256', 'fixture'.$attempt->id),
            'attempt_id' => $attempt->id, 'email_campaign_id' => $campaign->id, 'email_campaign_recipient_id' => $recipient->id,
            'event_type' => 'delivery', 'provider_message_id' => (string) Str::uuid(), 'occurred_at' => now()]);
        $this->race($campaign, $recipient, ['one' => 'feedback', 'two' => 'interrupt']);
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertSame('accepted', $attempt->fresh()->status);
        $this->assertNotNull($recipient->fresh()->provider_delivered_at);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
    }

    public function test_concurrent_workers_share_one_provider_rate_budget(): void
    {
        [$campaign, $recipient] = $this->fixture();
        $results = $this->race($campaign, $recipient, ['one' => 'limit', 'two' => 'limit']);
        $this->assertSame(1, collect($results)->where('delay', 0)->count());
        $this->assertSame(1, collect($results)->filter(fn ($row) => $row['delay'] > 0)->count());
    }

    private function fixture(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $template = EmailTemplate::create(['created_by_user_id' => $actor->id, 'name' => 'Lifecycle CI '.$suffix, 'subject' => 'Synthetic', 'html_body' => 'No live email is sent.']);
        $campaign = EmailCampaign::create(['created_by_user_id' => $actor->id, 'email_template_id' => $template->id, 'name' => 'Lifecycle CI '.$suffix,
            'management_version' => 3, 'purpose' => 'marketing', 'attachment_mode' => 'none', 'status' => 'queued', 'generation' => 1,
            'recipient_count' => 1, 'open_count' => 1, 'attention_count' => 0, 'batch_size' => 10, 'delay_seconds' => 1]);
        $recipient = EmailCampaignRecipient::forceCreate(['email_campaign_id' => $campaign->id, 'email' => $suffix.'@example.test', 'name' => 'Synthetic', 'status' => 'pending',
            'consent_status' => 'asserted', 'consent_source' => 'Synthetic isolated test permission', 'consent_asserted_at' => now()]);
        $this->actorIds[] = $actor->id;
        $this->templateIds[] = $template->id;
        $this->campaignIds[] = $campaign->id;

        return [$campaign->fresh(), $recipient, $actor];
    }

    private function race(EmailCampaign $campaign, EmailCampaignRecipient $recipient, array $workers): array
    {
        $directory = storage_path('framework/testing/email-lifecycle-'.bin2hex(random_bytes(10)));
        if (! mkdir($directory, 0700, true)) {
            $this->fail('Cannot create isolated lifecycle barrier.');
        }
        $processes = [];
        $connection = config('database.connections.mysql');
        $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $connection['database'],
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
            'MAIL_MAILER' => 'array', 'MAIL_FROM_ADDRESS' => 'synthetic@example.test', 'BROADCAST_CONNECTION' => 'null', 'CACHE_STORE' => 'array',
            'EMAIL_LIFECYCLE_MYSQL_TEST' => '1', 'EMAIL_LIFECYCLE_BARRIER' => $directory, 'EMAIL_LIFECYCLE_NOW' => now()->toIso8601String()];

        try {
            foreach ($workers as $worker => $action) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/email-campaign-lifecycle-worker.php'), (string) $campaign->id,
                    (string) $recipient->id, (string) $campaign->created_by_user_id, $worker, $action], base_path(), $environment);
                $process->setTimeout(35)->start();
                $processes[$worker] = $process;
            }
            $deadline = microtime(true) + 20;
            while (collect(array_keys($workers))->contains(fn ($worker) => ! is_file($directory.'/ready-'.$worker))) {
                if (microtime(true) > $deadline) {
                    $this->fail('Lifecycle workers did not reach synchronization barrier.');
                }
                usleep(10000);
            }
            touch($directory.'/go');
            $results = [];
            foreach ($processes as $worker => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Isolated lifecycle worker failed: '.$process->getErrorOutput());
                $results[$worker] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            // Delete only known files in the explicitly created workspace-local barrier.
            foreach (array_merge(['go'], array_map(fn ($worker) => 'ready-'.$worker, array_keys($workers))) as $file) {
                if (is_file($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                }
            }
            rmdir($directory);
        }
    }
}
