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

    public function test_extraction_exports_metadata_then_fields_in_field_logic_order_then_legacy_fields(): void
    {
        $this->preparePercentageFormRecord('12.5');
        FormField::where('campaign_code', 'mbsales')
            ->where('form_type', 'ezycash')
            ->where('field_name', 'discount_rate')
            ->update(['field_order' => 3]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'rate',
            'field_label' => 'Rate',
            'field_type' => 'number',
            'is_required' => false,
            'field_order' => 1,
        ]);
        FormField::create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'cardholder_name',
            'field_label' => 'Cardholder Name',
            'field_type' => 'text',
            'is_required' => false,
            'field_order' => 2,
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'data_type' => 'ezycash',
            ]);

        $rows = $this->csvRows($response->streamedContent());

        $this->assertSame([
            'id',
            'date',
            'request_id',
            'agent',
            'rate',
            'cardholder_name',
            'discount_rate',
            'mpi_credit_card_no',
            'bank',
            'account_type',
            'account_number',
            'surname',
            'first_name',
            'middle_name',
            'ezycash_amount',
            'term',
            'amenable',
            'remarks',
            'lead_id',
            'phone_number',
            'created_at',
            'updated_at',
        ], $rows[0]);
        $this->assertSame('1', $rows[1][0]);
        $this->assertSame('1', $rows[1][4]);
        $this->assertSame('Test Cardholder', $rows[1][5]);
        $this->assertSame('12.5%', $rows[1][6]);
        $this->assertNotSame('', $rows[1][20]);
        $this->assertNotSame('', $rows[1][21]);
    }

    public function test_extraction_uses_canonical_header_for_an_empty_table_and_omits_stale_fields(): void
    {
        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'empty_export',
            'name' => 'Empty Export',
            'table_name' => 'empty_export',
            'color' => 'green',
            'icon' => 'document',
            'display_order' => 2,
            'is_active' => true,
        ]);
        foreach ([
            ['field_name' => 'second_field', 'field_label' => 'Second', 'field_order' => 2],
            ['field_name' => 'missing_field', 'field_label' => 'Missing', 'field_order' => 3],
            ['field_name' => 'first_field', 'field_label' => 'First', 'field_order' => 1],
        ] as $field) {
            FormField::create(array_merge([
                'campaign_code' => 'mbsales',
                'form_type' => 'empty_export',
                'field_type' => 'text',
                'is_required' => false,
            ], $field));
        }
        Schema::create('empty_export', function ($table) {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->string('agent');
            $table->string('first_field');
            $table->string('second_field');
            $table->string('legacy_field');
            $table->timestamps();
        });
        app(\App\Services\CampaignService::class)->clearCampaignsCache();

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'data_type' => 'empty_export',
            ]);

        $this->assertSame([
            'id',
            'date',
            'request_id',
            'agent',
            'first_field',
            'second_field',
            'legacy_field',
            'created_at',
            'updated_at',
        ], $this->csvRows($response->streamedContent())[0]);
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

    public function test_data_master_renders_desktop_table_layout_hook(): void
    {
        $this->preparePercentageFormRecord('7');

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash']))
            ->assertOk()
            ->assertSee('data-master-desktop-table', false);
    }

    public function test_data_master_search_filters_records_and_keeps_the_search_value(): void
    {
        $this->preparePercentageFormRecord('7');
        DB::table('ezycash')->insert([
            'date' => '2026-01-16',
            'request_id' => '260116002',
            'cardholder_name' => 'Second Cardholder',
            'mpi_credit_card_no' => '4222222222222222',
            'bank' => 'Second Bank',
            'account_type' => 'Savings',
            'account_number' => '654321',
            'surname' => 'Smith',
            'first_name' => 'Jane',
            'ezycash_amount' => '200.00',
            'term' => '24',
            'rate' => '2.00',
            'agent' => 'agent_search',
            'discount_rate' => '8',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash', 'search' => 'Second Bank']))
            ->assertOk()
            ->assertSee('name="search"', false)
            ->assertSee('Second Bank')
            ->assertDontSee('Test Bank')
            ->assertSee('value="Second Bank"', false);
    }

    public function test_data_master_search_preserves_query_parameters_in_pagination_links(): void
    {
        $this->preparePercentageFormRecord('7');
        $records = [];

        for ($index = 1; $index <= 21; $index++) {
            $records[] = [
                'date' => '2026-01-16',
                'request_id' => '260116'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'cardholder_name' => 'Search Cardholder '.$index,
                'mpi_credit_card_no' => '4222222222222222',
                'bank' => 'Search Bank',
                'account_type' => 'Savings',
                'account_number' => '65432'.$index,
                'surname' => 'Smith',
                'first_name' => 'Jane',
                'ezycash_amount' => '200.00',
                'term' => '24',
                'rate' => '2.00',
                'agent' => 'agent_search',
                'discount_rate' => '8',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('ezycash')->insert($records);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash', 'search' => 'Search Bank']))
            ->assertOk()
            ->assertSee('search=Search', false)
            ->assertSee('page=2', false);
    }

    public function test_data_master_search_normalizes_invalid_and_oversized_query_values(): void
    {
        $this->preparePercentageFormRecord('7');
        $oversizedSearch = str_repeat('x', 120);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash', 'search' => $oversizedSearch]))
            ->assertOk()
            ->assertSee('value="'.str_repeat('x', 100).'"', false);

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index').'?type=ezycash&search%5B0%5D=invalid')
            ->assertOk()
            ->assertSee('value=""', false);
    }

    public function test_data_master_form_selector_is_marked_for_soft_navigation(): void
    {
        $this->preparePercentageFormRecord('7');

        $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.data-master.index', ['type' => 'ezycash']))
            ->assertOk()
            ->assertSee('data-soft-nav', false);
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

    /**
     * @return list<list<string|null>>
     */
    private function csvRows(string $content): array
    {
        return array_map(
            static fn (string $line): array => str_getcsv($line),
            preg_split('/\r\n|\r|\n/', trim($content)) ?: [],
        );
    }
}
