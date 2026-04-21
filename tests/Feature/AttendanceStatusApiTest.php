<?php

namespace Tests\Feature;

use App\Models\AttendanceStatusType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the agent-facing attendance status API introduced in the attendance
 * customization feature (Apr 2026). Admin CRUD coverage lives elsewhere.
 */
class AttendanceStatusApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // The create-table migration seeds lunch/break/bio, so use updateOrCreate
        // to avoid unique-constraint errors when tests run.
        AttendanceStatusType::updateOrCreate(
            ['code' => 'lunch'],
            ['label' => 'Lunch', 'sort_order' => 1, 'is_active' => true],
        );
        AttendanceStatusType::updateOrCreate(
            ['code' => 'break'],
            ['label' => 'Break', 'sort_order' => 2, 'is_active' => true],
        );
        AttendanceStatusType::updateOrCreate(
            ['code' => 'inactive_code'],
            ['label' => 'Disabled', 'sort_order' => 99, 'is_active' => false],
        );
    }

    public function test_current_returns_null_open_and_only_active_types(): void
    {
        $response = $this->actingAs($this->user)->getJson(route('api.attendance.current'));

        $response->assertOk();
        $response->assertJson(['success' => true, 'open' => null]);

        $codes = collect($response->json('types'))->pluck('code')->all();
        $this->assertContains('lunch', $codes);
        $this->assertContains('break', $codes);
        $this->assertNotContains('inactive_code', $codes);
    }

    public function test_start_creates_log_and_start_again_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.attendance.start'), [
            'code' => 'lunch',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'log' => [
                'event_type' => 'lunch_start',
                'direction' => 'start',
                'status_label' => 'Lunch',
            ],
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->user->id,
            'event_type' => 'lunch_start',
            'direction' => 'start',
        ]);

        // Starting a second status while one is already open must fail (422).
        $again = $this->actingAs($this->user)->postJson(route('api.attendance.start'), [
            'code' => 'break',
        ]);
        $again->assertStatus(422);
        $again->assertJson(['success' => false]);
    }

    public function test_end_requires_an_open_status(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.attendance.end'));
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_full_cycle_start_then_end(): void
    {
        $this->actingAs($this->user)->postJson(route('api.attendance.start'), ['code' => 'lunch'])->assertOk();

        $current = $this->actingAs($this->user)->getJson(route('api.attendance.current'));
        $current->assertOk();
        $current->assertJson(['open' => ['code' => 'lunch', 'label' => 'Lunch']]);

        $end = $this->actingAs($this->user)->postJson(route('api.attendance.end'));
        $end->assertOk();
        $end->assertJson([
            'success' => true,
            'log' => [
                'event_type' => 'lunch_end',
                'direction' => 'end',
            ],
        ]);

        // After end, current is null again and a second start is allowed.
        $this->actingAs($this->user)->getJson(route('api.attendance.current'))
            ->assertJson(['open' => null]);
        $this->actingAs($this->user)->postJson(route('api.attendance.start'), ['code' => 'break'])
            ->assertOk();
    }

    public function test_start_rejects_unknown_code(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.attendance.start'), [
            'code' => 'does-not-exist',
        ]);
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_start_rejects_inactive_code(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('api.attendance.start'), [
            'code' => 'inactive_code',
        ]);
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson(route('api.attendance.current'))->assertUnauthorized();
        $this->postJson(route('api.attendance.start'), ['code' => 'lunch'])->assertUnauthorized();
        $this->postJson(route('api.attendance.end'))->assertUnauthorized();
    }
}
