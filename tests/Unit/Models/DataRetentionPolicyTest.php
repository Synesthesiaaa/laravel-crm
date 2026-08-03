<?php

namespace Tests\Unit\Models;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataRetentionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_casts_values_and_resolves_its_form(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'is_active' => true,
        ]);

        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'is_active' => true,
        ]);

        $policy->refresh();

        $this->assertSame($form->id, $policy->form->id);
        $this->assertSame('2026-01-01', $policy->from_date->format('Y-m-d'));
        $this->assertSame('2026-01-31', $policy->to_date->format('Y-m-d'));
        $this->assertTrue($policy->is_active);
        $this->assertSame('whole_record', $policy->deletion_mode);
        $this->assertNull($policy->selected_fields);
        $this->assertNull($policy->last_run_at);
        $this->assertSame(0, $policy->last_deleted_count);
        $this->assertSame($policy->id, $form->fresh()->retentionPolicy->id);
    }

    public function test_policy_casts_selected_field_configuration(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezyconvert',
            'name' => 'EzyConvert',
            'table_name' => 'ezyconvert',
            'is_active' => true,
        ]);

        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'deletion_mode' => 'selected_fields',
            'selected_fields' => ['cardholder_name', 'account_number'],
        ]);

        $policy->refresh();

        $this->assertSame('selected_fields', $policy->deletion_mode);
        $this->assertSame(['cardholder_name', 'account_number'], $policy->selected_fields);
    }

    public function test_legacy_policy_allows_a_null_from_date(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'legacy_form',
            'name' => 'Legacy Form',
            'table_name' => 'legacy_form',
            'is_active' => true,
        ]);

        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
        ]);

        $this->assertNull($policy->fresh()->from_date);
    }

    public function test_form_can_have_only_one_retention_policy(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'is_active' => true,
        ]);

        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
        ]);

        $this->expectException(QueryException::class);

        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-02-28',
        ]);
    }
}
