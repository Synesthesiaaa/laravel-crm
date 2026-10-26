<?php

namespace Tests\Unit\Services;

use App\Services\DashboardCustomSalesAggregationService;
use App\Services\DashboardSalesRuleService;
use Tests\TestCase;

class DashboardCustomSalesAggregationServiceTest extends TestCase
{
    public function test_form_trigger_counts_submissions_and_normalizes_numeric_amounts(): void
    {
        $service = app(DashboardCustomSalesAggregationService::class);
        $row = (object) ['amount' => '12.30'];

        $this->assertTrue($service->matchesConditions($row, [], 'amount', DashboardSalesRuleService::TRIGGER_FORM));
        $this->assertSame(12.3, $service->amount($row, 'amount'));
        $this->assertSame(0.0, $service->amount((object) ['amount' => ' '], 'amount'));
    }

    public function test_marker_trigger_accepts_zero_but_rejects_blank_and_nonnumeric_values(): void
    {
        $service = app(DashboardCustomSalesAggregationService::class);

        $this->assertTrue($service->matchesConditions((object) ['amount' => '0'], [], 'amount', DashboardSalesRuleService::TRIGGER_MARKED_AMOUNT));
        $this->assertFalse($service->matchesConditions((object) ['amount' => ' '], [], 'amount', DashboardSalesRuleService::TRIGGER_MARKED_AMOUNT));
        $this->assertFalse($service->matchesConditions((object) ['amount' => '12 dollars'], [], 'amount', DashboardSalesRuleService::TRIGGER_MARKED_AMOUNT));
    }

    public function test_tag_conditions_are_trimmed_case_insensitive_and_or_matched(): void
    {
        $service = app(DashboardCustomSalesAggregationService::class);
        $conditions = [
            ['field_name' => 'status', 'accepted_values' => ['approved']],
            ['field_name' => 'result', 'accepted_values' => ['sale']],
        ];

        $this->assertTrue($service->matchesConditions((object) ['status' => '  APPROVED  ', 'result' => 'no'], $conditions));
        $this->assertTrue($service->matchesConditions((object) ['status' => null, 'result' => ' Sale '], $conditions));
        $this->assertFalse($service->matchesConditions((object) ['status' => 'pending', 'result' => 'no'], $conditions));
    }
}
