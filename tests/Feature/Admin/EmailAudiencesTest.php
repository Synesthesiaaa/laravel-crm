<?php

namespace Tests\Feature\Admin;

use App\Jobs\PrepareEmailAudience;
use App\Models\EmailAudienceImport;
use App\Models\EmailCampaign;
use App\Models\EmailContact;
use App\Models\EmailTemplate;
use App\Models\LeadHopper;
use App\Models\User;
use App\Services\Email\AudienceService;
use App\Services\Email\SuppressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class EmailAudiencesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private EmailCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');
        $this->owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $template = EmailTemplate::create(['created_by_user_id' => $this->owner->id, 'name' => 'Audience', 'subject' => 'Hello', 'html_body' => 'Hello {{name}}']);
        $this->campaign = EmailCampaign::create(['created_by_user_id' => $this->owner->id, 'email_template_id' => $template->id, 'name' => 'Draft', 'purpose' => 'marketing', 'attachment_mode' => 'none', 'status' => 'draft']);
    }

    public function test_csv_is_staged_privately_without_parsing_or_sending_in_http_then_applied_once(): void
    {
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'csv'], UploadedFile::fake()->createWithContent('recipients.csv', "email,name\na@example.com,Alice\nA@example.com,Duplicate\nb@example.com,Bob\n"));
        $this->assertSame('queued', $run->status);
        $this->assertSame(0, $this->campaign->recipients()->count());
        Storage::disk('local')->assertExists($run->storage_path);
        Queue::assertPushed(PrepareEmailAudience::class);
        while ($service->process($run->id)) {
        }
        $run->refresh();
        $this->assertSame('ready', $run->status);
        $this->assertSame(2, $run->accepted);
        $this->assertSame(1, $run->duplicates);
        $service->apply($this->owner, $run);
        while ($service->process($run->id)) {
        }
        $this->assertSame(2, $this->campaign->recipients()->count());
        $this->assertSame(2, EmailContact::count());
        $this->assertSame('ready', $this->campaign->refresh()->audience_status);
        $this->assertFalse($service->process($run->id));
        $this->assertSame(2, $this->campaign->recipients()->count());
    }

    public function test_unknown_permission_is_reported_ineligible_and_never_inferred(): void
    {
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, ['source' => 'manual', 'manual_recipients' => 'unknown@example.com,Unknown']);
        while ($service->process($run->id)) {
        }
        $this->assertSame(0, $run->refresh()->accepted);
        $this->assertSame(1, $run->rejected);
        $this->expectException(ValidationException::class);
        $service->apply($this->owner, $run);
    }

    public function test_operator_can_require_duplicate_rejection_instead_of_exclusion(): void
    {
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'manual', 'duplicate_policy' => 'reject', 'manual_recipients' => "a@example.com,Alice\nA@example.com,Duplicate"]);
        while ($service->process($run->id)) {
        }
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertSame(1, $run->duplicates);
        $this->assertSame(1, $run->rejected);
    }

    public function test_cancelled_import_cannot_apply_and_private_source_is_removed(): void
    {
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'manual', 'manual_recipients' => 'a@example.com,Alice']);
        $path = $run->storage_path;
        $service->cancel($this->owner, $run);
        $this->assertFalse($service->process($run->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, $this->campaign->recipients()->count());
        $this->assertSame('cancelled', $run->refresh()->status);
    }

    public function test_crm_mapping_is_explicit_and_admin_cannot_import_another_users_hopper(): void
    {
        LeadHopper::create(['campaign_code' => 'mbsales', 'lead_id' => 99, 'phone_number' => '123', 'client_name' => 'Customer', 'custom_data' => ['contact_email' => 'own@example.com'], 'assigned_to_user_id' => $this->owner->id]);
        LeadHopper::create(['campaign_code' => 'mbsales', 'lead_id' => 100, 'phone_number' => '124', 'client_name' => 'Other', 'custom_data' => ['contact_email' => 'other@example.com'], 'assigned_to_user_id' => User::factory()->create()->id]);
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'crm', 'crm_model' => 'hopper', 'campaign_code' => 'mbsales', 'email_field' => 'contact_email']);
        while ($service->process($run->id)) {
        }
        $this->assertSame(1, $run->refresh()->accepted);
        $this->assertDatabaseHas('email_audience_rows', ['import_id' => $run->id, 'email' => 'own@example.com']);
        $this->assertDatabaseMissing('email_audience_rows', ['import_id' => $run->id, 'email' => 'other@example.com']);
    }

    public function test_another_admin_cannot_stage_or_apply_an_owned_campaign(): void
    {
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AudienceService::class)->stage($other, $this->campaign, $this->permission() + ['source' => 'manual', 'manual_recipients' => 'a@example.com']);
    }

    public function test_workbook_uses_the_explicit_sheet_and_rejects_formulas(): void
    {
        $service = app(AudienceService::class);
        $file = $this->workbook([['email', 'name'], ['first@example.com', 'First']], [['email', 'name'], ['second@example.com', 'Second']]);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'xlsx', 'worksheet' => 2], $file);
        while ($service->process($run->id)) {
        }
        $this->assertSame('ready', $run->refresh()->status);
        $this->assertDatabaseHas('email_audience_rows', ['import_id' => $run->id, 'email' => 'second@example.com']);
        $this->assertDatabaseMissing('email_audience_rows', ['import_id' => $run->id, 'email' => 'first@example.com']);

        $formula = $this->workbook([['email', 'name'], ['=CONCAT("a","@example.com")', 'Formula']]);
        $bad = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'xlsx'], $formula);
        while ($service->process($bad->id)) {
        }
        $this->assertSame('failed', $bad->refresh()->status);
        $this->assertSame(1, $bad->rejected);
    }

    public function test_large_preparation_is_checkpointed_in_bounded_slices_and_application_is_idempotent(): void
    {
        $service = app(AudienceService::class);
        $csv = "email,name\n";
        for ($i = 1; $i <= 601; $i++) {
            $csv .= "person{$i}@example.com,Person {$i}\n";
        }
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'csv'], UploadedFile::fake()->createWithContent('large.csv', $csv));
        $this->assertTrue($service->process($run->id));
        $this->assertSame(250, $run->refresh()->processed);
        $this->assertSame('processing', $run->status);
        $this->assertSame(0, $this->campaign->recipients()->count());
        while ($service->process($run->id)) {
        }
        $this->assertSame(601, $run->refresh()->accepted);
        $service->apply($this->owner, $run);
        $this->assertTrue($service->process($run->id));
        $this->assertSame(250, $this->campaign->recipients()->count());
        $this->assertSame('preparing', $this->campaign->refresh()->audience_status);
        while ($service->process($run->id)) {
        }
        $this->assertSame(601, $this->campaign->recipients()->count());
        $this->assertSame('ready', $this->campaign->refresh()->audience_status);
        $this->assertFalse($service->process($run->id));
    }

    public function test_suppression_and_withdrawn_consent_cannot_be_overridden_by_import_assertion(): void
    {
        app(SuppressionService::class)->suppress('suppressed@example.com', 'complaint');
        EmailContact::create(['user_id' => $this->owner->id, 'email' => 'withdrawn@example.com', 'consent_status' => 'withdrawn']);
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'manual', 'manual_recipients' => "suppressed@example.com,Suppressed\nwithdrawn@example.com,Withdrawn"]);
        while ($service->process($run->id)) {
        }
        $this->assertSame(1, $run->refresh()->suppressed);
        $this->assertSame(1, $run->rejected);
        $this->assertSame(0, $run->accepted);
    }

    public function test_saved_segment_filters_owned_contacts_and_preserves_recorded_permission(): void
    {
        EmailContact::create(['user_id' => $this->owner->id, 'email' => 'vip@example.com', 'tags' => ['vip'], 'consent_status' => 'opted_in', 'consent_source' => 'Confirmed register', 'consent_asserted_at' => now()->subDay()]);
        EmailContact::create(['user_id' => $this->owner->id, 'email' => 'regular@example.com', 'tags' => ['regular'], 'consent_status' => 'unknown']);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        EmailContact::create(['user_id' => $other->id, 'email' => 'other-vip@example.com', 'tags' => ['vip'], 'consent_status' => 'opted_in', 'consent_source' => 'Other register', 'consent_asserted_at' => now()->subDay()]);
        $service = app(AudienceService::class);
        $segment = $service->saveSegment($this->owner, ['name' => 'VIP', 'filters' => ['tag' => 'vip']]);
        $run = $service->stage($this->owner, $this->campaign, ['source' => 'segment', 'segment_id' => $segment->id]);
        while ($service->process($run->id)) {
        }
        $this->assertSame(1, $run->refresh()->accepted);
        $this->assertSame(0, $run->rejected);
        $this->assertDatabaseHas('email_audience_rows', ['import_id' => $run->id, 'email' => 'vip@example.com', 'consent_status' => 'opted_in']);
        $this->assertDatabaseMissing('email_audience_rows', ['import_id' => $run->id, 'email' => 'other-vip@example.com']);
    }

    public function test_malformed_rows_block_whole_import_and_revoked_account_stops_processing(): void
    {
        $service = app(AudienceService::class);
        $run = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'manual', 'manual_recipients' => "valid@example.com,Valid\ninvalid-email,Bad"]);
        while ($service->process($run->id)) {
        }
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertSame(1, $run->accepted);
        $this->assertSame(1, $run->rejected);
        $this->assertSame(0, $this->campaign->recipients()->count());

        $second = $service->stage($this->owner, $this->campaign, $this->permission() + ['source' => 'manual', 'manual_recipients' => 'valid@example.com']);
        $this->owner->update(['role' => User::ROLE_AGENT]);
        $this->assertFalse($service->process($second->id));
        $this->assertSame('failed', $second->refresh()->status);
        $this->assertSame(0, $second->processed);
    }

    public function test_audience_page_and_json_enforce_role_ownership_and_explicit_apply_confirmation(): void
    {
        $this->actingAs($this->owner)->get(route('admin.email-audiences.index'))->assertOk()->assertSee('Prepare recipients');
        $response = $this->actingAs($this->owner)->postJson(route('admin.email-audiences.imports.store'), $this->permission() + ['campaign_id' => $this->campaign->id, 'source' => 'manual', 'manual_recipients' => 'safe@example.com,Safe']);
        $response->assertAccepted()->assertJsonPath('status', 'queued')->assertJsonMissingPath('storage_path')->assertJsonMissingPath('payload');
        $run = EmailAudienceImport::findOrFail($response->json('id'));
        while (app(AudienceService::class)->process($run->id)) {
        }
        $this->postJson(route('admin.email-audiences.imports.apply', $run))->assertUnprocessable();
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($other)->getJson(route('admin.email-audiences.imports.show', $run))->assertNotFound();
        $this->actingAs($other)->deleteJson(route('admin.email-audiences.imports.cancel', $run))->assertNotFound();
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
        $this->actingAs($agent)->get(route('admin.email-audiences.index'))->assertForbidden();
        $this->actingAs($agent)->postJson(route('admin.email-audiences.imports.store'), ['campaign_id' => $this->campaign->id, 'source' => 'manual', 'manual_recipients' => 'safe@example.com'])->assertForbidden();
    }

    public function test_empty_segment_filters_and_contact_evidence_forms_work_without_invented_opt_in(): void
    {
        $this->actingAs($this->owner)->post(route('admin.email-audiences.segments.store'), ['name' => 'All my contacts', 'filters' => ['tag' => null, 'source' => null, 'campaign_code' => null, 'consent_status' => null]])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.email-audiences.contacts.store'), ['email' => 'new@example.com', 'name' => 'New', 'consent_status' => 'unknown', 'tags' => 'new,vip'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('email_contacts', ['user_id' => $this->owner->id, 'email' => 'new@example.com', 'consent_status' => 'unknown', 'consent_source' => null]);
        $this->post(route('admin.email-audiences.contacts.store'), ['email' => 'new@example.com', 'consent_status' => 'asserted'])->assertSessionHasErrors(['consent_source', 'consent_asserted_at']);
    }

    private function workbook(array $rows, ?array $secondSheet = null): UploadedFile
    {
        $path = tempnam(storage_path('framework/testing'), 'audience-');

        try {
            $writer = new Writer;
            $writer->openToFile($path);
            foreach ($rows as $values) {
                $writer->addRow(Row::fromValues($values));
            }
            if ($secondSheet) {
                $writer->addNewSheetAndMakeItCurrent();
                foreach ($secondSheet as $values) {
                    $writer->addRow(Row::fromValues($values));
                }
            }
            $writer->close();

            return UploadedFile::fake()->createWithContent('recipients.xlsx', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    private function permission(): array
    {
        return ['assert_permission' => true, 'permission_source' => 'Approved customer opt-in register CASE-123', 'permission_at' => '2026-11-01'];
    }
}
