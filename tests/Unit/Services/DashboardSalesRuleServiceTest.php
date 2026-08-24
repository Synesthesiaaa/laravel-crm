<?php

namespace Tests\Unit\Services;

use App\Models\FormField;
use App\Services\DashboardSalesRuleService;
use Database\Seeders\CampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSalesRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_custom_rules_with_registered_fields_and_normalized_values(): void
    {
        $this->seed(CampaignSeeder::class);
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'amenable',
            'field_label' => 'Amenable',
            'field_type' => 'text',
            'is_required' => false,
            'field_order' => 1,
        ]);
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'EzyCash Amount',
            'field_type' => 'number',
            'is_required' => false,
            'field_order' => 2,
        ]);

        $resolved = app(DashboardSalesRuleService::class)->resolveForCampaign('mbsales', [
            'mode' => 'custom',
            'forms' => [
                [
                    'form_code' => 'ezycash',
                    'amount_field' => 'ezycash_amount',
                    'conditions' => [
                        [
                            'field_name' => 'amenable',
                            'accepted_values' => [' Yes ', 'APPROVED'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('custom', $resolved['mode']);
        $this->assertSame([], $resolved['warnings']);
        $this->assertSame([
            'form_code' => 'ezycash',
            'form_name' => 'EzyCash',
            'table' => 'ezycash',
            'amount_field' => 'ezycash_amount',
            'conditions' => [[
                'field_name' => 'amenable',
                'accepted_values' => ['yes', 'approved'],
            ]],
        ], $resolved['forms'][0]);
    }

    public function test_custom_mode_returns_warnings_for_stale_references_without_falling_back(): void
    {
        $this->seed(CampaignSeeder::class);

        $resolved = app(DashboardSalesRuleService::class)->resolveForCampaign('mbsales', [
            'mode' => 'custom',
            'forms' => [[
                'form_code' => 'missing_form',
                'amount_field' => null,
                'conditions' => [[
                    'field_name' => 'amenable',
                    'accepted_values' => ['Yes'],
                ]],
            ]],
        ]);

        $this->assertSame('custom', $resolved['mode']);
        $this->assertSame([], $resolved['forms']);
        $this->assertNotEmpty($resolved['warnings']);
    }
}
