<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\FormFieldRepositoryInterface;
use App\Services\CampaignService;
use App\Services\DataMasterService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DataMasterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_numeric_percentage_storage(): void
    {
        Schema::create('percentage_probe_records', function ($table) {
            $table->id();
            $table->decimal('rate', 10, 2)->nullable();
            $table->string('label')->nullable();
        });

        $service = new DataMasterService(
            Mockery::mock(CampaignService::class),
            Mockery::mock(FormFieldRepositoryInterface::class),
        );

        $this->assertTrue($service->storesPercentageAsNumeric('percentage_probe_records', 'rate'));
        $this->assertFalse($service->storesPercentageAsNumeric('percentage_probe_records', 'label'));
        $this->assertFalse($service->storesPercentageAsNumeric('percentage_probe_records', 'missing'));
    }

    public function test_update_record_refreshes_the_updated_at_timestamp(): void
    {
        Schema::create('data_master_timestamp_records', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $originalTimestamp = Carbon::parse('2026-07-15 10:00:00');
        DB::table('data_master_timestamp_records')->insert([
            'name' => 'Before update',
            'created_at' => $originalTimestamp,
            'updated_at' => $originalTimestamp,
        ]);

        Carbon::setTestNow('2026-07-16 10:00:00');

        $service = new DataMasterService(
            Mockery::mock(CampaignService::class),
            Mockery::mock(FormFieldRepositoryInterface::class),
        );

        $service->updateRecord('data_master_timestamp_records', 1, ['name' => 'After update'], [
            'data_master_timestamp_records',
        ]);

        $record = DB::table('data_master_timestamp_records')->first();

        $this->assertSame('After update', $record->name);
        $this->assertSame('2026-07-16 10:00:00', $record->updated_at);

        Carbon::setTestNow();
    }

    public function test_timestamp_backfill_uses_the_submission_date_for_legacy_rows(): void
    {
        DB::table('ezycash')->insert([
            'date' => '2026-07-06',
            'request_id' => 'legacy-row',
            'cardholder_name' => 'Legacy Row',
            'mpi_credit_card_no' => '4111111111111111',
            'bank' => 'Test Bank',
            'account_type' => 'Savings',
            'account_number' => '123456',
            'surname' => 'Doe',
            'first_name' => 'John',
            'middle_name' => null,
            'ezycash_amount' => 100,
            'term' => '12',
            'rate' => 5,
            'amenable' => null,
            'agent' => 'legacy-agent',
            'remarks' => null,
            'lead_id' => null,
            'phone_number' => null,
            'created_at' => null,
            'updated_at' => null,
        ]);

        $migration = require base_path('database/migrations/2026_07_16_100000_backfill_form_submission_timestamps.php');
        $migration->up();

        $record = DB::table('ezycash')->where('request_id', 'legacy-row')->first();

        $this->assertSame('2026-07-06 00:00:00', $record->created_at);
        $this->assertSame('2026-07-06 00:00:00', $record->updated_at);
    }
}
