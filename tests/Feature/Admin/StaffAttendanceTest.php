<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'full_name' => 'Admin User',
        ]);
        $this->agent = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'full_name' => 'Sample Agent',
            'username' => 'sample.agent',
        ]);
    }

    public function test_staff_attendance_renders_professional_audit_view_with_summary_and_filters(): void
    {
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'login',
            'event_time' => now()->setTime(8, 0),
            'ip_address' => '10.0.0.10',
        ]);
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'logout',
            'event_time' => now()->setTime(17, 0),
            'ip_address' => '10.0.0.10',
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.attendance.index'));

        $response->assertOk();
        $response->assertSee('Review staff login, logout, and away-status activity from one audit view.');
        $response->assertSee('Visible events');
        $response->assertSee('Realtime Attendance Sessions');
        $response->assertSee(route('api.attendance.realtime'), false);
        $response->assertSee('Filter attendance activity');
        $response->assertSee('data-soft-nav', false);
        $response->assertSee('Sample Agent');
        $response->assertSee('@sample.agent');
        $response->assertSee('10.0.0.10');
        $response->assertSee('Attendance activity');
    }

    public function test_staff_attendance_preserves_date_and_system_event_filters(): void
    {
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'login',
            'event_time' => now()->setTime(8, 0),
            'ip_address' => '10.0.0.11',
        ]);
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'logout',
            'event_time' => now()->setTime(17, 0),
            'ip_address' => '10.0.0.12',
        ]);
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'login',
            'event_time' => now()->subDay()->setTime(8, 0),
            'ip_address' => '10.0.0.13',
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.attendance.index', [
                'date' => now()->format('Y-m-d'),
                'event' => 'login',
            ]));

        $response->assertOk();
        $response->assertSee('10.0.0.11');
        $response->assertDontSee('10.0.0.12');
        $response->assertDontSee('10.0.0.13');
        $response->assertSee('Clear');
    }

    public function test_staff_attendance_preserves_custom_status_filtering(): void
    {
        $status = AttendanceStatusType::query()->create([
            'code' => 'training',
            'label' => 'Training',
            'sort_order' => 50,
            'is_active' => true,
        ]);
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'training_start',
            'attendance_status_type_id' => $status->id,
            'direction' => AttendanceLog::DIRECTION_START,
            'event_time' => now()->setTime(10, 0),
            'ip_address' => '10.0.0.20',
        ]);
        AttendanceLog::query()->create([
            'user_id' => $this->agent->id,
            'event_type' => 'login',
            'event_time' => now()->setTime(8, 0),
            'ip_address' => '10.0.0.21',
        ]);

        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->get(route('admin.attendance.index', ['event' => $status->id]));

        $response->assertOk();
        $response->assertSee('Training (Start)');
        $response->assertSee('10.0.0.20');
        $response->assertDontSee('10.0.0.21');
        $response->assertSee('value="'.$status->id.'" selected', false);
    }

    public function test_staff_attendance_rejects_invalid_date_filter(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession($this->campaignSession())
            ->from(route('admin.attendance.index'))
            ->get(route('admin.attendance.index', ['date' => 'not-a-date']));

        $response->assertRedirect(route('admin.attendance.index'));
        $response->assertSessionHasErrors('date');
    }

    /**
     * @return array{campaign: string, campaign_name: string}
     */
    private function campaignSession(): array
    {
        return [
            'campaign' => 'mbsales',
            'campaign_name' => 'MB Sales',
        ];
    }
}
