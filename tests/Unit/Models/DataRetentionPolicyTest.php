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
            'cutoff_date' => '2026-01-31',
            'is_active' => true,
        ]);

        $policy->refresh();

        $this->assertSame($form->id, $policy->form->id);
        $this->assertSame('2026-01-31', $policy->cutoff_date->format('Y-m-d'));
        $this->assertTrue($policy->is_active);
        $this->assertNull($policy->last_run_at);
        $this->assertSame(0, $policy->last_deleted_count);
        $this->assertSame($policy->id, $form->fresh()->retentionPolicy->id);
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
            'cutoff_date' => '2026-01-31',
        ]);

        $this->expectException(QueryException::class);

        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'cutoff_date' => '2026-02-28',
        ]);
    }
}
