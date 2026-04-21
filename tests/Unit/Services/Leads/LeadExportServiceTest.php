<?php

namespace Tests\Unit\Services\Leads;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\LeadList;
use App\Models\LeadListField;
use App\Services\Leads\LeadExportService;
use App\Services\Leads\LeadFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadExportService $service;

    private LeadList $list;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeadExportService::class);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $this->list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);
        app(LeadFieldService::class)->ensureStandardFields('testcamp');
    }

    public function test_query_filters_by_list_and_status(): void
    {
        Lead::create(['list_id' => $this->list->id, 'campaign_code' => 'testcamp', 'phone_number' => '1', 'status' => 'NEW', 'enabled' => true]);
        Lead::create(['list_id' => $this->list->id, 'campaign_code' => 'testcamp', 'phone_number' => '2', 'status' => 'DNC', 'enabled' => true]);

        $rows = $this->service->query('testcamp', ['list_id' => $this->list->id, 'status' => 'NEW'])->get();

        $this->assertCount(1, $rows);
        $this->assertSame('1', $rows->first()->phone_number);
    }

    public function test_build_columns_only_includes_exportable_fields(): void
    {
        LeadListField::create([
            'campaign_code' => 'testcamp',
            'field_key' => 'internal_note',
            'field_label' => 'Internal Note',
            'field_type' => 'string',
            'is_standard' => false,
            'visible' => true,
            'exportable' => false,
            'importable' => true,
            'field_order' => 99,
        ]);

        $built = $this->service->buildColumns('testcamp');

        $this->assertContains('id', $built['columns']);
        $this->assertNotContains('internal_note', $built['columns']);
        $this->assertContains('phone_number', $built['columns']);
    }

    public function test_value_for_returns_custom_field(): void
    {
        $lead = Lead::create([
            'list_id' => $this->list->id,
            'campaign_code' => 'testcamp',
            'phone_number' => '1',
            'custom_fields' => ['note' => 'vip'],
        ]);

        $this->assertSame('vip', $this->service->valueFor($lead->fresh(), 'note'));
        $this->assertSame('1', $this->service->valueFor($lead->fresh(), 'phone_number'));
        $this->assertNull($this->service->valueFor($lead->fresh(), 'nonexistent_key'));
    }
}
