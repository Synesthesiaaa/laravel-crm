<?php

namespace Tests\Integration;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EmailCampaignTransitionMySqlConcurrencyTest extends TestCase
{
    public function test_competing_recipient_transitions_update_counters_once(): void
    {
        if (getenv('GITHUB_ACTIONS') !== 'true') {
            $this->markTestSkipped('Runs only against the dedicated ephemeral MySQL service in CI.');
        }

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('laravel_crm_test', config('database.connections.mysql.database'));

        $suffix = bin2hex(random_bytes(4));
        $template = EmailTemplate::query()->create([
            'name' => 'Synthetic CI '.$suffix,
            'subject' => 'Synthetic test',
            'html_body' => 'No email is sent by this concurrency test.',
        ]);
        $campaign = EmailCampaign::query()->create([
            'email_template_id' => $template->id,
            'name' => 'Synthetic transition '.$suffix,
            'status' => 'sending',
            'recipient_count' => 1,
            'sent_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'open_count' => 1,
            'attention_count' => 0,
        ]);
        $recipient = EmailCampaignRecipient::query()->create([
            'email_campaign_id' => $campaign->id,
            'email' => 'synthetic-'.$suffix.'@example.test',
            'status' => 'sending',
        ]);

        $trigger = 'phase5_email_transition_delay';
        DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        DB::unprepared('CREATE TRIGGER '.$trigger.' BEFORE UPDATE ON email_campaign_recipients FOR EACH ROW SET @phase5_delay = SLEEP(1)');
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-email-transition-'.bin2hex(random_bytes(8));
        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            $this->fail('Unable to create the isolated email transition barrier directory.');
        }

        $processes = [];

        try {
            $workers = ['first' => 'transition', 'second' => 'transition', 'reconcile' => 'reconcile'];
            foreach ($workers as $worker => $action) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/email-recipient-transition-worker.php'),
                    (string) $campaign->id,
                    (string) $recipient->id,
                    $worker,
                    $action,
                ], base_path(), ['EMAIL_TRANSITION_BARRIER' => $barrier]);
                $process->setTimeout(30);
                $process->start();
                $processes[$worker] = $process;
            }

            $this->waitForWorkers($barrier, array_keys($workers));
            touch($barrier.DIRECTORY_SEPARATOR.'go');
            $results = [];
            foreach ($processes as $worker => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $worker.': '.$process->getErrorOutput().$process->getOutput());
                $results[$worker] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach (glob($barrier.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($barrier);
        }

        $this->assertSame(1, collect($results)->where('changed', true)->count());
        $this->assertSame('accepted', $recipient->fresh()->status);
        $this->assertSame(1, $campaign->fresh()->sent_count);
        $this->assertSame(0, $campaign->fresh()->failed_count);
        $this->assertSame(0, $campaign->fresh()->open_count);
        $this->assertSame(0, $campaign->fresh()->attention_count);
    }

    private function waitForWorkers(string $directory, array $workers): void
    {
        $deadline = microtime(true) + 20;
        do {
            if (collect($workers)->every(fn (string $worker): bool => file_exists($directory.DIRECTORY_SEPARATOR.'ready-'.$worker))) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('Concurrent email transition workers did not reach the synchronization barrier.');
    }
}
