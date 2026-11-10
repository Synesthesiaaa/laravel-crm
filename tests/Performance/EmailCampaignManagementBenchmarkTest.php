<?php

namespace Tests\Performance;

use App\Models\EmailCampaign;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Services\Email\AudienceService;
use App\Services\Email\CampaignLifecycle;
use App\Services\Email\ManagedEmailDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailCampaignManagementBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_recipient_limit_uses_bounded_import_and_recipient_delivery(): void
    {
        Bus::fake();
        Storage::fake('local');
        $actor = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $template = EmailTemplate::create(['created_by_user_id' => $actor->id, 'name' => 'Synthetic scale', 'subject' => 'Hello {{name}}', 'html_body' => 'Synthetic message for {{email}}']);
        $campaign = EmailCampaign::create(['created_by_user_id' => $actor->id, 'email_template_id' => $template->id, 'name' => 'Synthetic scale', 'status' => 'draft', 'management_version' => 3, 'purpose' => 'marketing', 'attachment_mode' => 'none', 'batch_size' => 100, 'delay_seconds' => 1]);
        $count = min(10000, (int) config('email_campaigns.max_recipients'));
        $csv = "email,name\n";
        for ($index = 1; $index <= $count; $index++) {
            $csv .= 'scale-'.$index.'@example.com,Person '.$index."\n";
        }
        $beforeMemory = memory_get_usage(true);
        $start = microtime(true);
        $service = app(AudienceService::class);
        $run = $service->stage($actor, $campaign, ['source' => 'csv', 'assert_permission' => true, 'permission_source' => 'Synthetic benchmark permission', 'permission_at' => now()->subDay()->toDateString()], UploadedFile::fake()->createWithContent('scale.csv', $csv));
        while ($service->process($run->id)) {
        }
        $service->apply($actor, $run->refresh());
        while ($service->process($run->id)) {
        }
        $importSeconds = microtime(true) - $start;
        $this->assertSame($count, $campaign->fresh()->recipient_count);
        $start = microtime(true);
        app(CampaignLifecycle::class)->start($actor, $campaign->fresh());
        $freezeSeconds = microtime(true) - $start;
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $recipientId = $campaign->recipients()->orderBy('id')->value('id');
        app(ManagedEmailDelivery::class)->send($campaign->id, $recipientId, $campaign->fresh()->generation);
        $deliveryQueries = $queries;
        $this->assertLessThan(100, $deliveryQueries, 'Single-recipient delivery must not query every audience recipient.');
        $this->assertSame('accepted', $campaign->recipients()->whereKey($recipientId)->value('status'));
        $start = microtime(true);
        $metrics = app(\App\Services\Email\CampaignMetrics::class)->forCampaign($campaign->fresh());
        $summarySeconds = microtime(true) - $start;
        $this->assertSame($count, $metrics['total']);
        $this->assertSame(1, $metrics['accepted']);
        $this->assertNull($metrics['delivered']);
        $beforeExportQueries = $queries;
        $start = microtime(true);
        $report = $this->actingAs($actor)->get(route('admin.email-management.report', $campaign));
        $report->assertOk()->assertDownload();
        $export = $report->streamedContent();
        $exportSeconds = microtime(true) - $start;
        $exportQueries = $queries - $beforeExportQueries;
        $this->assertSame($count + 1, substr_count($export, "\n"));
        $this->assertStringNotContainsString('@example.com', $export);
        $this->assertLessThan(200, $exportQueries, 'Report export must use bounded chunks, not per-recipient queries.');
        $memoryGrowth = memory_get_peak_usage(true) - $beforeMemory;
        $this->assertLessThan(128 * 1024 * 1024, $memoryGrowth, 'Preparation must not grow an unbounded in-memory audience.');
        fwrite(STDOUT, PHP_EOL.json_encode(['synthetic_recipients' => $count, 'import_seconds' => round($importSeconds, 3), 'freeze_seconds' => round($freezeSeconds, 3), 'single_delivery_queries' => $deliveryQueries, 'summary_seconds' => round($summarySeconds, 3), 'export_seconds' => round($exportSeconds, 3), 'export_queries' => $exportQueries, 'peak_growth_mib' => round($memoryGrowth / 1048576, 2), 'transport' => 'array'], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
