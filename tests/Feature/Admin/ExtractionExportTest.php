<?php

namespace Tests\Feature\Admin;

use App\Events\DashboardDataUpdated;
use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExtractionExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('campaigns_with_forms');

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);

        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'color' => 'green',
            'icon' => 'cash',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_export_extraction_csv_download(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'data_type' => 'ezycash',
            ]);

        $response->assertStreamed();
        $response->assertDownload();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_extraction_exports_percentage_fields_with_suffix_for_existing_numeric_values(): void
    {
        $this->preparePercentageFormRecord('12.5');

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'data_type' => 'ezycash',
            ]);

        $response->assertOk()->assertStreamed();

        $content = $response->streamedContent();
        $this->assertStringContainsString('discount_rate', $content);
        $this->assertStringContainsString('12.5%', $content);
    }

    public function test_data_master_displays_percentage_fields_with_suffix_for_existing_numeric_values(): void
    {
        $this->preparePercentageFormRecord('7');

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash']))
            ->assertOk()
            ->assertSee('Discount Rate')
            ->assertSee('7%');
    }

    public function test_data_master_update_broadcasts_dashboard_update(): void
    {
        Event::fake([DashboardDataUpdated::class]);
        $this->preparePercentageFormRecord('7');
        $recordId = (int) DB::table('ezycash')->value('id');

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.data-master.update'), [
                '_table' => 'ezycash',
                '_id' => $recordId,
                '_type' => 'ezycash',
                'discount_rate' => '8',
            ]);

        $response->assertRedirect();
        Event::assertDispatched(DashboardDataUpdated::class, function (DashboardDataUpdated $event) use ($recordId): bool {
            return $event->campaignCode === 'mbsales'
                && $event->formType === 'ezycash'
                && $event->recordId === $recordId
                && $event->action === 'updated';
        });
    }

    public function test_export_requires_end_date_after_or_equal_to_start_date(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->from(route('admin.extraction.index'))
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-31',
                'end_date' => '2026-01-01',
                'data_type' => 'ezycash',
            ]);

        $response->assertRedirect(route('admin.extraction.index'));
        $response->assertSessionHasErrors('end_date');
    }

    public function test_stale_session_campaign_is_replaced_by_first_active_campaign(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['campaign' => 'stale_campaign', 'campaign_name' => 'Stale Campaign'])
            ->get(route('admin.extraction.index'));

        $response->assertOk();
        $response->assertSessionHas('campaign', 'mbsales');
        $response->assertSee('EzyCash');
    }

    public function test_data_master_handles_campaign_with_no_forms(): void
    {
        Campaign::factory()->create([
            'code' => 'emptycamp',
            'name' => 'Empty Campaign',
            'color' => '#111827',
        ]);
        app(\App\Services\CampaignService::class)->clearCampaignsCache();

        $this->actingAs($this->admin)
            ->withSession(['campaign' => 'emptycamp', 'campaign_name' => 'Empty Campaign'])
            ->get(route('admin.data-master.index'))
            ->assertOk()
            ->assertSee('No active forms are configured for this campaign.');
    }

    /**
     * @return array<string, string>
     */
    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }

    private function preparePercentageFormRecord(string $value): void
    {
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'discount_rate',
            'field_label' => 'Discount Rate',
            'field_type' => 'percentage',
            'is_required' => false,
            'field_order' => 1,
        ]);

        if (! Schema::hasTable('ezycash')) {
            Schema::create('ezycash', function ($table) {
                $table->id();
                $table->date('date')->index();
                $table->string('request_id')->index();
                $table->string('agent')->index();
                $table->string('discount_rate')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('ezycash', 'discount_rate')) {
            Schema::table('ezycash', function ($table) {
                $table->string('discount_rate')->nullable();
            });
        }

        DB::table('ezycash')->insert([
            'date' => '2026-01-15',
            'request_id' => '260115001',
            'cardholder_name' => 'Test Cardholder',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'John',
            'ezycash_amount' => '100.00',
            'term' => '12',
            'rate' => '1.00',
            'agent' => 'agent_export',
            'discount_rate' => $value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
