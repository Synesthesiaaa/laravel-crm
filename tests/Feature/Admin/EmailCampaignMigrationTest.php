<?php

namespace Tests\Feature\Admin;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailCampaignMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_migrations_roll_back_and_reapply_without_removing_legacy_campaigns(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $actor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $template = EmailTemplate::create(['created_by_user_id' => $actor->id, 'name' => 'Legacy message', 'subject' => 'Legacy subject', 'html_body' => 'Original plain text']);
        $campaign = EmailCampaign::create(['created_by_user_id' => $actor->id, 'email_template_id' => $template->id, 'name' => 'Legacy campaign', 'purpose' => 'marketing', 'attachment_mode' => 'none', 'status' => 'draft', 'recipient_count' => 1]);
        EmailCampaignRecipient::create(['email_campaign_id' => $campaign->id, 'email' => 'legacy@example.com', 'status' => 'pending']);
        $this->artisan('migrate:rollback', ['--step' => 5, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('email_campaign_attempts'));
        $this->assertFalse(Schema::hasColumn('email_campaigns', 'management_version'));
        $this->assertSame('Legacy campaign', DB::table('email_campaigns')->where('id', $campaign->id)->value('name'));
        $this->assertDatabaseCount('email_campaign_recipients', 1);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('email_campaign_attempts'));
        $this->assertSame(1, (int) $campaign->fresh()->management_version);
        $this->assertSame('text', $template->fresh()->body_format);
        $this->assertSame('Original plain text', $template->fresh()->html_body);
    }
}
