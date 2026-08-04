<?php

namespace Tests\Unit\Models;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
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
        $this->assertSame('recurring', $policy->run_mode);
        $this->assertNull($policy->run_at);
        $this->assertNull($policy->recurrence);
        $this->assertNull($policy->run_time);
        $this->assertNull($policy->run_day_of_week);
        $this->assertNull($policy->run_day_of_month);
        $this->assertNull($policy->next_run_at);
        $this->assertNull($policy->last_run_status);
        $this->assertNull($policy->last_error);
        $this->assertSame($policy->id, $form->fresh()->retentionPolicy->id);
    }

    public function test_policy_casts_execution_settings_and_schema_contains_execution_columns(): void
    {
        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'scheduled_form',
            'name' => 'Scheduled Form',
            'table_name' => 'scheduled_form',
            'is_active' => true,
        ]);

        $runAt = Carbon::parse('2026-08-04 09:15:00');
        $nextRunAt = Carbon::parse('2026-08-11 09:15:00');
        $policy = DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'to_date' => '2026-01-31',
            'run_mode' => 'recurring',
            'recurrence' => 'weekly',
            'run_time' => '09:15',
            'run_day_of_week' => 2,
            'next_run_at' => $nextRunAt,
            'last_run_status' => 'success',
            'last_error' => null,
            'run_at' => $runAt,
        ]);

        $policy->refresh();

        $this->assertInstanceOf(Carbon::class, $policy->run_at);
        $this->assertSame('2026-08-04 09:15:00', $policy->run_at->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(Carbon::class, $policy->next_run_at);
        $this->assertSame('2026-08-11 09:15:00', $policy->next_run_at->format('Y-m-d H:i:s'));
        $this->assertSame('weekly', $policy->recurrence);
        $this->assertSame(2, $policy->run_day_of_week);
        $this->assertSame('09:15', substr((string) $policy->run_time, 0, 5));
        $this->assertSame('success', $policy->last_run_status);

        $this->assertTrue(Schema::hasColumns('data_retention_policies', [
            'run_mode',
            'run_at',
            'recurrence',
            'run_time',
            'run_day_of_week',
            'run_day_of_month',
            'next_run_at',
            'last_run_status',
            'last_error',
        ]));
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
