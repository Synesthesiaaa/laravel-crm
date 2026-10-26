<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Services\FormStorageSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormStorageNullabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::create(['code' => 'storage_test', 'name' => 'Storage Test']);
        Form::create([
            'campaign_code' => 'storage_test',
            'form_code' => 'winback_test',
            'name' => 'Winback Test',
            'table_name' => 'winback_test_records',
        ]);
        $this->field('customer_name');
        $this->field('24k_card_number');

        Schema::create('winback_test_records', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->string('agent');
            $table->string('customer_name');
            $table->string('24k_card_number', 80)->comment('Historical card values')->index();
            $table->timestamps();
        });

        DB::table('winback_test_records')->insert([
            'date' => '2026-10-12',
            'request_id' => 'historical-record',
            'agent' => 'Historical Agent',
            'customer_name' => 'Historical Customer',
            '24k_card_number' => '001234',
        ]);
        app(FormStorageSchemaService::class)->provision(
            'winback_test_records',
            FormField::where('campaign_code', 'storage_test')->where('form_type', 'winback_test')->get(),
        );
    }

    public function test_admin_can_delete_required_field_then_save_without_losing_historical_data(): void
    {
        $field = FormField::where('field_name', '24k_card_number')->firstOrFail();
        $this->actingAs($this->admin)
            ->post(route('admin.field-logic.destroy'), ['id' => $field->id])
            ->assertSessionHas('success');

        $this->assertSoftDeleted($field);
        $before = collect(Schema::getColumns('winback_test_records'))->keyBy('name');
        $indexes = Schema::getIndexes('winback_test_records');
        $this->submit()->assertOk()->assertJsonPath('success', true);

        $after = collect(Schema::getColumns('winback_test_records'))->keyBy('name');
        $this->assertTrue($after['24k_card_number']['nullable']);
        $this->assertSame($before['24k_card_number']['type'], $after['24k_card_number']['type']);
        $this->assertSame($before['24k_card_number']['comment'], $after['24k_card_number']['comment']);
        $this->assertSame($before['24k_card_number']['collation'], $after['24k_card_number']['collation']);
        $this->assertSame($indexes, Schema::getIndexes('winback_test_records'));
        foreach (['id', 'date', 'request_id', 'agent', 'customer_name'] as $name) {
            $this->assertSame($before[$name], $after[$name]);
        }
        $this->assertDatabaseHas('winback_test_records', [
            'request_id' => 'historical-record',
            '24k_card_number' => '001234',
        ]);
        $this->assertDatabaseHas('winback_test_records', [
            'customer_name' => 'New Customer',
            '24k_card_number' => null,
        ]);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->submit()->assertOk();
        $alterQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => preg_match('/^\s*(alter|create|drop)\b/i', $query['query']) === 1,
        );
        DB::disableQueryLog();
        $this->assertCount(0, $alterQueries);
    }

    public function test_admin_can_make_required_field_optional_then_save_empty_value(): void
    {
        $field = FormField::where('field_name', '24k_card_number')->firstOrFail();
        $this->actingAs($this->admin)
            ->put(route('admin.field-logic.update', $field->id), [
                'field_label' => 'Card Number',
                'field_type' => 'text',
                'is_required' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->submit(['24k_card_number' => ''])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('winback_test_records', [
            'customer_name' => 'New Customer',
            '24k_card_number' => null,
        ]);
    }

    public function test_optional_date_and_deleted_numeric_columns_keep_types_and_defaults(): void
    {
        $this->field('followup_date', false, 'date');
        DB::table('winback_test_records')->delete();
        Schema::table('winback_test_records', function ($table): void {
            $table->date('followup_date')->default('2026-01-01');
            $table->decimal('deleted_amount', 12, 3);
            $table->integer('legacy_with_default')->default(7);
        });
        app(FormStorageSchemaService::class)->provision(
            'winback_test_records',
            FormField::where('campaign_code', 'storage_test')->where('form_type', 'winback_test')->get(),
        );
        $before = collect(Schema::getColumns('winback_test_records'))->keyBy('name');

        $this->submit(['24k_card_number' => '001234'])->assertOk();

        $after = collect(Schema::getColumns('winback_test_records'))->keyBy('name');
        foreach (['followup_date', 'deleted_amount'] as $name) {
            $this->assertTrue($after[$name]['nullable']);
            $this->assertSame($before[$name]['type'], $after[$name]['type']);
            $this->assertSame($before[$name]['default'], $after[$name]['default']);
        }
        $this->assertSame($before['legacy_with_default'], $after['legacy_with_default']);
        $this->assertDatabaseHas('winback_test_records', [
            'customer_name' => 'New Customer',
            'followup_date' => null,
            'deleted_amount' => null,
            'legacy_with_default' => 7,
        ]);
    }

    public function test_active_required_field_still_rejects_empty_value(): void
    {
        $this->submit()->assertUnprocessable()->assertJsonValidationErrors('24k_card_number');
        $this->assertDatabaseCount('winback_test_records', 1);
        $column = collect(Schema::getColumns('winback_test_records'))->firstWhere('name', '24k_card_number');
        $this->assertFalse($column['nullable']);
    }

    public function test_storage_provision_command_is_idempotent_for_registered_forms(): void
    {
        $this->assertSame(0, Artisan::call('forms:provision-storage'));
        $this->assertSame(0, Artisan::call('forms:provision-storage'));
        $this->assertTrue(app(FormStorageSchemaService::class)->isReady(
            'winback_test_records',
            FormField::query()->where('campaign_code', 'storage_test')->where('form_type', 'winback_test')->get(),
        ));
    }

    private function field(string $name, bool $required = true, string $type = 'text'): FormField
    {
        return FormField::create([
            'campaign_code' => 'storage_test',
            'form_type' => 'winback_test',
            'field_name' => $name,
            'field_label' => $name,
            'field_type' => $type,
            'is_required' => $required,
        ]);
    }

    private function submit(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('forms.store'), array_merge([
            'campaign' => 'storage_test',
            'form_type' => 'winback_test',
            'date' => now()->toDateString(),
            'customer_name' => 'New Customer',
        ], $data));
    }
}
