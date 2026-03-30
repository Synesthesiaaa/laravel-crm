<?php

namespace Tests\Unit\Services;

use App\Models\DispositionCode;
use App\Services\DispositionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionServiceTest extends TestCase
{
    use RefreshDatabase;

    private DispositionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(DispositionService::class);
    }

    public function test_get_codes_returns_empty_for_unknown_campaign(): void
    {
        $codes = $this->service->getCodesForCampaign('nonexistent');
        $this->assertEmpty($codes);
    }

    public function test_get_codes_returns_active_codes_for_campaign(): void
    {
        DispositionCode::create([
            'campaign_code' => 'test',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        DispositionCode::create([
            'campaign_code' => 'test',
            'code' => 'DNC',
            'label' => 'Do Not Call',
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $codes = $this->service->getCodesForCampaign('test');
        $this->assertCount(1, $codes);
        $this->assertEquals('SALE', $codes[0]['code']);
    }
}
