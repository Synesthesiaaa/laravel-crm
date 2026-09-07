<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use App\Models\Campaign;
use App\Models\CrmCallHistory;
use App\Models\Form;
use App\Models\FormField;
use App\Models\User;
use App\Notifications\SupervisorUserNotification;
use App\Services\DashboardLayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create(['code' => 'mbsales', 'name' => 'MB Sales']);
        Form::query()->create([
            'campaign_code' => 'mbsales',
            'form_code' => 'ezycash',
            'name' => 'EzyCash application',
            'table_name' => 'ezycash',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_notifications_endpoint_returns_a_scoped_normalized_feed_with_labels_and_daily_performance(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Agent One',
            'vici_user' => '1001',
        ]);
        $user->notify(new SupervisorUserNotification(
            message: 'Take your break after this call.',
            recipientType: 'USER',
            recipient: '1001',
            senderId: 99,
            showConfetti: false,
        ));

        CrmCallHistory::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
            'status' => 'RECORDED',
            'remarks' => 'Saved form',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread', 3)
            ->assertJsonPath('has_more', false)
            ->assertJsonStructure(['items', 'unread', 'has_more', 'refreshed_at']);

        $items = $response->json('items');
        $this->assertSame(['Daily performance', 'Supervisor', 'Call & form history'], array_column($items, 'source'));
        $this->assertSame('Supervisor notification', $items[1]['title']);
        $this->assertSame('EzyCash application', $items[2]['title']);
        $this->assertStringNotContainsString('mbsales', json_encode($items[2]['title']));
        $this->assertStringNotContainsString('ezycash', json_encode($items[2]['title']));
        $this->assertFalse($items[1]['read']);
        $this->assertFalse($items[2]['read']);
        $this->assertStringStartsWith('daily:mbsales:', $items[0]['key']);
        $this->assertStringContainsString('No sales yet', $items[0]['message']);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.summary'))
            ->assertOk()
            ->assertJsonPath('unread', 3)
            ->assertJsonStructure(['success', 'unread', 'refreshed_at']);
    }

    public function test_read_all_marks_database_notifications_and_history_items_as_read(): void
    {
        $user = User::factory()->create([
            'full_name' => 'Agent One',
            'vici_user' => '1001',
        ]);
        $user->notify(new SupervisorUserNotification(
            message: 'Read me.',
            recipientType: 'USER',
            recipient: '1001',
            senderId: 99,
            showConfetti: false,
        ));
        CrmCallHistory::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
            'status' => 'RECORDED',
        ]);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonPath('items.0.read', true)
            ->assertJsonPath('items.1.read', true)
            ->assertJsonPath('items.2.read', true);
    }

    public function test_single_read_is_durable_after_cache_clear_and_cannot_cross_users(): void
    {
        $user = User::factory()->create(['full_name' => 'Agent One']);
        $other = User::factory()->create(['full_name' => 'Agent Two']);
        $row = CrmCallHistory::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
            'status' => 'RECORDED',
        ]);
        $key = 'history:'.$row->id;

        $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.notifications.read'), ['key' => $key])
            ->assertOk()
            ->assertJsonPath('success', true);

        Cache::flush();
        $this->assertDatabaseHas('notification_read_states', [
            'user_id' => $user->id,
            'item_key' => $key,
        ]);
        $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertJsonPath('items.1.read', true);

        $this->actingAs($other)->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.notifications.read'), ['key' => $key])
            ->assertNotFound();
    }

    public function test_feed_is_globally_sorted_limited_and_reports_more_items(): void
    {
        $user = User::factory()->create(['full_name' => 'Agent One']);
        for ($i = 0; $i < 30; $i++) {
            CrmCallHistory::query()->create([
                'campaign_code' => 'mbsales',
                'form_type' => 'ezycash',
                'agent' => 'Agent One',
                'status' => 'RECORDED',
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk();

        $this->assertCount(25, $response->json('items'));
        $this->assertTrue($response->json('has_more'));
        $times = array_column($response->json('items'), 'created_at');
        $sorted = $times;
        usort($sorted, static fn (?string $a, ?string $b): int => strcmp((string) $b, (string) $a));
        $this->assertSame($sorted, $times);
    }

    public function test_attendance_detail_and_stale_keys_are_scoped(): void
    {
        $user = User::factory()->create(['full_name' => 'Agent One']);
        $status = AttendanceStatusType::query()->where('code', 'lunch')->firstOrFail();
        $log = AttendanceLog::query()->create([
            'user_id' => $user->id,
            'event_type' => 'lunch_start',
            'attendance_status_type_id' => $status->id,
            'direction' => 'start',
            'event_time' => now(),
        ]);

        $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail', ['key' => 'attendance:'.$log->id]))
            ->assertOk()
            ->assertJsonPath('detail.category', 'attendance')
            ->assertJsonPath('detail.sections.0.metrics.0.value', 'Lunch');

        $old = new CrmCallHistory([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'agent' => 'Agent One',
        ]);
        $old->timestamps = false;
        $old->created_at = now()->subDays(31);
        $old->updated_at = now()->subDays(31);
        $old->save();
        $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail', ['key' => 'history:'.$old->id]))
            ->assertNotFound();

        Campaign::factory()->create(['code' => 'pjli', 'name' => 'PJLI']);
        $otherCampaign = CrmCallHistory::query()->create([
            'campaign_code' => 'pjli',
            'form_type' => 'other',
            'agent' => 'Agent One',
        ]);
        $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail', ['key' => 'history:'.$otherCampaign->id]))
            ->assertNotFound();
    }

    public function test_daily_performance_reuses_dashboard_range_and_hides_out_of_range_sales(): void
    {
        Carbon::setTestNow('2026-09-07 12:00:00');
        FormField::query()->create([
            'campaign_code' => 'mbsales',
            'form_type' => 'ezycash',
            'field_name' => 'ezycash_amount',
            'field_label' => 'Amount',
            'field_type' => 'number',
            'is_sale_amount' => true,
        ]);
        DB::table('ezycash')->insert([
            ['date' => '2026-09-07', 'request_id' => 'inside-1', 'cardholder_name' => 'A', 'mpi_credit_card_no' => '1', 'bank' => 'B', 'account_type' => 'A', 'account_number' => '1', 'surname' => 'S', 'first_name' => 'F', 'middle_name' => null, 'ezycash_amount' => 100, 'term' => '1', 'rate' => 1, 'amenable' => null, 'agent' => 'Agent One', 'created_at' => '2026-09-07 06:00:00', 'updated_at' => '2026-09-07 06:00:00'],
            ['date' => '2026-09-07', 'request_id' => 'inside-2', 'cardholder_name' => 'B', 'mpi_credit_card_no' => '2', 'bank' => 'B', 'account_type' => 'A', 'account_number' => '2', 'surname' => 'S', 'first_name' => 'F', 'middle_name' => null, 'ezycash_amount' => 200, 'term' => '1', 'rate' => 1, 'amenable' => null, 'agent' => 'Agent Two', 'created_at' => '2026-09-07 17:59:59', 'updated_at' => '2026-09-07 17:59:59'],
            ['date' => '2026-09-07', 'request_id' => 'outside', 'cardholder_name' => 'C', 'mpi_credit_card_no' => '3', 'bank' => 'B', 'account_type' => 'A', 'account_number' => '3', 'surname' => 'S', 'first_name' => 'F', 'middle_name' => null, 'ezycash_amount' => 999, 'term' => '1', 'rate' => 1, 'amenable' => null, 'agent' => 'Agent Two', 'created_at' => '2026-09-07 18:00:00', 'updated_at' => '2026-09-07 18:00:00'],
        ]);
        $user = User::factory()->create(['full_name' => 'Agent One', 'username' => 'agent.one']);
        User::factory()->create(['full_name' => 'Agent Two', 'username' => 'agent.two']);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk();
        $daily = collect($response->json('items'))->firstWhere('category', 'performance');

        $this->assertSame(2, $daily['preview']['team_sales']);
        $this->assertSame(1, $daily['preview']['personal_sales']);
        $this->assertEquals(300.0, $daily['preview']['team_amount']);
        $this->assertEquals(100.0, $daily['preview']['personal_amount']);
        $this->assertSame('Agent Two', $daily['preview']['top_agent']);
        $this->assertSame(1, $daily['preview']['top_agent_sales']);
        $this->assertStringContainsString('100.00', $daily['message']);
        $this->assertStringContainsString('300.00', $daily['message']);

        $detail = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail', ['key' => $daily['key']]))
            ->assertOk()
            ->json('detail');
        $metrics = collect($detail['sections'])->flatMap(fn (array $section): array => $section['metrics'] ?? []);
        $this->assertSame('Agent Two', $metrics->firstWhere('label', 'Top agent')['value']);
        $this->assertContains('₱300.00', $metrics->where('label', 'Sales amount')->pluck('value')->all());
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/summary')->assertUnauthorized();
        $this->getJson('/api/notifications/detail?key=daily%3Ambsales%3A2026-09-07')->assertUnauthorized();
        $this->postJson('/api/notifications/read', ['key' => 'daily:mbsales:2026-09-07'])->assertUnauthorized();
    }

    public function test_detail_and_single_read_require_a_valid_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);

        $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.notifications.read'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    }

    public function test_daily_performance_omits_amounts_when_dashboard_amounts_are_disabled(): void
    {
        app(DashboardLayoutService::class)->saveForCampaign(
            'mbsales',
            array_keys(DashboardLayoutService::sectionDefinitions()),
            ['welcome'],
            null,
            false,
            ['enabled' => false],
        );
        $user = User::factory()->create(['full_name' => 'Agent One']);

        $response = $this->actingAs($user)
            ->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications'))
            ->assertOk();
        $daily = collect($response->json('items'))->firstWhere('category', 'performance');

        $this->assertArrayNotHasKey('team_amount', $daily['preview']);
        $detail = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])
            ->getJson(route('api.notifications.detail', ['key' => $daily['key']]))
            ->assertOk()
            ->json('detail');
        $labels = collect($detail['sections'])->flatMap(fn (array $section): array => $section['metrics'] ?? [])->pluck('label');
        $this->assertFalse($labels->contains('Sales amount'));
    }
}
