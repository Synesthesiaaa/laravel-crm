<?php

namespace Tests\Unit\Services;

use App\Contracts\Repositories\FormFieldRepositoryInterface;
use App\Services\CampaignService;
use App\Services\DataMasterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
