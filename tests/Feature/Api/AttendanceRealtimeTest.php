<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class AttendanceRealtimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Campaign::factory()->create([
            'code' => 'mbsales',
            'name' => 'MB Sales',
        ]);
    }

    public function test_realtime_attendance_returns_only_current_logged_in_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00'));

        try {
            $supervisor = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
            $online = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'username' => 'agent.online',
                'full_name' => 'Online Agent',
            ]);
            $offline = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'username' => 'agent.offline',
                'full_name' => 'Offline Agent',
            ]);

            AttendanceLog::query()->create([
                'user_id' => $online->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(2),
                'ip_address' => '10.0.0.10',
            ]);
            AttendanceLog::query()->create([
                'user_id' => $offline->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(3),
                'ip_address' => '10.0.0.11',
            ]);
            AttendanceLog::query()->create([
                'user_id' => $offline->id,
                'event_type' => 'logout',
                'event_time' => now()->subHour(),
                'ip_address' => '10.0.0.11',
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession($this->campaignSession())
                ->getJson(route('api.attendance.realtime'));

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('stats.online', 1)
                ->assertJsonPath('stats.available', 1)
                ->assertJsonPath('stats.away', 0)
                ->assertJsonPath('stats.longest_session_seconds', 7200)
                ->assertJsonPath('sessions.0.username', 'agent.online')
                ->assertJsonPath('sessions.0.name', 'Online Agent')
                ->assertJsonPath('sessions.0.status', 'ONLINE')
                ->assertJsonPath('sessions.0.attendance', 'Available')
                ->assertJsonPath('sessions.0.session_duration_seconds', 7200)
                ->assertJsonPath('sessions.0.ip_address', '10.0.0.10')
                ->assertJsonCount(1, 'sessions');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_realtime_attendance_tracks_current_custom_status_and_status_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00'));

        try {
            $supervisor = User::factory()->create(['role' => User::ROLE_ADMIN]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'username' => 'agent.break',
            ]);
            $break = AttendanceStatusType::query()->create([
                'code' => 'break_test',
                'label' => 'Break',
                'sort_order' => 90,
                'is_active' => true,
            ]);

            $login = AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(2),
            ]);
            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'break_test_start',
                'attendance_status_type_id' => $break->id,
                'direction' => AttendanceLog::DIRECTION_START,
                'event_time' => now()->subMinutes(15),
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession($this->campaignSession())
                ->getJson(route('api.attendance.realtime'));

            $response->assertOk()
                ->assertJsonPath('stats.online', 1)
                ->assertJsonPath('stats.available', 0)
                ->assertJsonPath('stats.away', 1)
                ->assertJsonPath('sessions.0.session_id', 'A-'.$login->id)
                ->assertJsonPath('sessions.0.attendance', 'Break')
                ->assertJsonPath('sessions.0.attendance_code', 'status-'.$break->id)
                ->assertJsonPath('sessions.0.status_duration_seconds', 900);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_realtime_attendance_returns_to_available_after_status_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00'));

        try {
            $supervisor = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
            $agent = User::factory()->create(['role' => User::ROLE_AGENT]);
            $lunch = AttendanceStatusType::query()->create([
                'code' => 'lunch_test',
                'label' => 'Lunch',
                'sort_order' => 91,
                'is_active' => true,
            ]);

            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(3),
            ]);
            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'lunch_test_start',
                'attendance_status_type_id' => $lunch->id,
                'direction' => AttendanceLog::DIRECTION_START,
                'event_time' => now()->subHour(),
            ]);
            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'lunch_test_end',
                'attendance_status_type_id' => $lunch->id,
                'direction' => AttendanceLog::DIRECTION_END,
                'event_time' => now()->subMinutes(20),
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession($this->campaignSession())
                ->getJson(route('api.attendance.realtime'));

            $response->assertOk()
                ->assertJsonPath('stats.available', 1)
                ->assertJsonPath('stats.away', 0)
                ->assertJsonPath('sessions.0.attendance', 'Available')
                ->assertJsonPath('sessions.0.status_duration_seconds', 1200);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_realtime_attendance_requires_supervisory_role(): void
    {
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->actingAs($agent)
            ->withSession($this->campaignSession())
            ->getJson(route('api.attendance.realtime'))
            ->assertForbidden();
    }

    public function test_realtime_attendance_keeps_an_overnight_login_in_the_current_session_table(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 01:00:00'));

        try {
            $supervisor = User::factory()->create(['role' => User::ROLE_TEAM_LEADER]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'username' => 'night.agent',
            ]);

            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'login',
                'event_time' => Carbon::parse('2026-09-24 23:30:00'),
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession($this->campaignSession())
                ->getJson(route('api.attendance.realtime'));

            $response->assertOk()
                ->assertJsonPath('stats.online', 1)
                ->assertJsonPath('sessions.0.username', 'night.agent')
                ->assertJsonPath('sessions.0.session_duration_seconds', 5400);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_realtime_attendance_excludes_stale_unmatched_logins(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-25 10:00:00'));
        config(['attendance.realtime.max_session_hours' => 24]);

        try {
            $supervisor = User::factory()->create(['role' => User::ROLE_ADMIN]);
            $agent = User::factory()->create([
                'role' => User::ROLE_AGENT,
                'username' => 'stale.agent',
            ]);

            AttendanceLog::query()->create([
                'user_id' => $agent->id,
                'event_type' => 'login',
                'event_time' => now()->subHours(30),
            ]);

            $response = $this->actingAs($supervisor)
                ->withSession($this->campaignSession())
                ->getJson(route('api.attendance.realtime'));

            $response->assertOk()
                ->assertJsonPath('stats.online', 0)
                ->assertJsonPath('max_session_hours', 24)
                ->assertJsonCount(0, 'sessions');
        } finally {
            Carbon::setTestNow();
        }
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
