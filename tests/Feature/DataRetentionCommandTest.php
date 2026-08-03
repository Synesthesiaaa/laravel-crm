<?php

namespace Tests\Feature;

use App\Models\DataRetentionPolicy;
use App\Models\Form;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataRetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Schema::dropIfExists('command_retention_records');

        parent::tearDown();
    }

    public function test_retention_command_deletes_expired_records_and_reports_the_total(): void
    {
        Schema::create('command_retention_records', function ($table): void {
            $table->id();
            $table->date('date');
            $table->string('request_id');
            $table->timestamps();
        });

        $form = Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'command_form',
            'name' => 'Command Form',
            'table_name' => 'command_retention_records',
            'is_active' => true,
        ]);
        DataRetentionPolicy::query()->create([
            'form_id' => $form->id,
            'cutoff_date' => '2026-01-31',
        ]);
        DB::table('command_retention_records')->insert([
            'date' => '2026-01-01',
            'request_id' => 'expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = Artisan::call('data-retention:run');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Deleted: 1', Artisan::output());
        $this->assertDatabaseMissing('command_retention_records', ['request_id' => 'expired']);
    }

    public function test_schedule_contains_the_daily_retention_command(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command)
            ->all();

        $this->assertTrue(collect($commands)->contains(fn (string $command): bool => str_contains($command, 'data-retention:run')));
    }
}
