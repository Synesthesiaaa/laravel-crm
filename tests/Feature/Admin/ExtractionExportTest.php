<?php

namespace Tests\Feature\Admin;

use App\Models\Campaign;
use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExtractionExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('campaigns_with_forms');

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
            'color' => '#3b82f6',
        ]);

        Form::create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash',
            'table_name' => 'ezycash',
            'color' => 'green',
            'icon' => 'cash',
            'display_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_export_extraction_csv_download(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-01',
                'end_date' => '2026-01-31',
                'data_type' => 'ezycash',
            ]);

        $response->assertStreamed();
        $response->assertDownload();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    }

    public function test_export_requires_end_date_after_or_equal_to_start_date(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->from(route('admin.extraction.index'))
            ->post(route('admin.extraction.export'), [
                'start_date' => '2026-01-31',
                'end_date' => '2026-01-01',
                'data_type' => 'ezycash',
            ]);

        $response->assertRedirect(route('admin.extraction.index'));
        $response->assertSessionHasErrors('end_date');
    }

    /**
     * @return array<string, string>
     */
    private function campaignSession(): array
    {
        return ['campaign' => 'mbsales', 'campaign_name' => 'MB Sales'];
    }
}
