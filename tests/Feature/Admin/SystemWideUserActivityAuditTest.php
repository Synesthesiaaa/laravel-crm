<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ActivityLogSanitizer;
use App\Services\UserActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SystemWideUserActivityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_polling_requests_are_audited_with_safe_request_metadata(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'full_name' => 'Audit Administrator',
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.activity-log.entries', [
            'search' => 'dashboard',
            'api_token' => 'do-not-persist',
        ]));

        $response->assertOk();

        $activity = Activity::query()
            ->where('event', 'request')
            ->where('causer_id', $admin->id)
            ->latest('id')
            ->firstOrFail();
        $properties = $activity->properties->toArray();

        $this->assertSame('GET', $properties['request']['method']);
        $this->assertSame('/admin/activity-log/entries', $properties['request']['path']);
        $this->assertSame('admin.activity-log.entries', $properties['request']['route']);
        $this->assertSame(200, $properties['request']['status']);
        $this->assertSame('dashboard', $properties['request']['query']['search']);
        $this->assertSame('[REDACTED]', $properties['request']['query']['api_token']);
        $this->assertArrayNotHasKey('body', $properties['request']);
        $this->assertArrayNotHasKey('headers', $properties['request']);
    }

    public function test_logout_requests_retain_the_authenticated_actor(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('activity_log', [
            'event' => 'request',
            'causer_id' => $user->id,
            'description' => 'POST /logout',
        ]);
    }

    public function test_authenticated_validation_failures_record_the_response_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->getJson(route('admin.activity-log.entries', [
                'from' => 'not-a-date',
            ]))
            ->assertUnprocessable();

        $activity = Activity::query()
            ->where('event', 'request')
            ->where('causer_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(422, $activity->properties->get('request')['status']);
    }

    public function test_authenticated_authorization_failures_record_the_response_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->getJson(route('admin.activity-log.entries'))
            ->assertForbidden();

        $activity = Activity::query()
            ->where('event', 'request')
            ->where('causer_id', $admin->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(403, $activity->properties->get('request')['status']);
    }

    public function test_authenticated_api_requests_are_audited(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user')
            ->assertOk();

        $activity = Activity::query()
            ->where('event', 'request')
            ->where('causer_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('/api/user', $activity->properties->get('request')['path']);
        $this->assertSame(200, $activity->properties->get('request')['status']);
    }

    public function test_guest_requests_do_not_create_user_request_activities(): void
    {
        $before = Activity::query()->where('event', 'request')->count();

        $this->get(route('login'))->assertOk();

        $this->assertSame($before, Activity::query()->where('event', 'request')->count());
    }

    public function test_activity_log_actor_selector_includes_users_without_roles(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $unassignedUser = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'username' => 'unassigned-user',
            'full_name' => 'Unassigned User',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee($unassignedUser->username, false)
            ->assertSee($unassignedUser->full_name, false);
    }

    public function test_request_search_can_match_normalized_request_metadata(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        activity('request')
            ->causedBy($admin)
            ->event('request')
            ->withProperties([
                'request' => [
                    'method' => 'PATCH',
                    'path' => '/configuration/metadata-only',
                    'route' => 'configuration.metadata-only',
                    'status' => 204,
                ],
            ])
            ->log('Audited request');

        $this->actingAs($admin)
            ->getJson(route('admin.activity-log.entries', [
                'event' => 'request',
                'search' => 'metadata-only',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.request.path', '/configuration/metadata-only');
    }

    public function test_request_history_returns_entries_for_every_supported_user_role(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $roles = [
            User::ROLE_AGENT,
            User::ROLE_TEAM_LEADER,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_ADMIN,
        ];
        $users = [];

        foreach ($roles as $index => $role) {
            $user = User::factory()->create([
                'role' => $role,
                'full_name' => 'Role Auditor '.$index,
            ]);
            $users[] = $user;

            activity('request')
                ->causedBy($user)
                ->event('request')
                ->withProperties([
                    'request' => [
                        'method' => 'GET',
                        'path' => '/role-audit/'.$index,
                        'route' => 'role.audit.'.$index,
                        'status' => 200,
                    ],
                ])
                ->log('GET /role-audit/'.$index);
        }

        foreach ($users as $index => $user) {
            $this->actingAs($superAdmin)
                ->getJson(route('admin.activity-log.entries', [
                    'event' => 'request',
                    'actor_id' => $user->id,
                    'search' => 'role-audit/'.$index,
                ]))
                ->assertOk()
                ->assertJsonPath('data.0.actor', 'Role Auditor '.$index)
                ->assertJsonPath('data.0.request.path', '/role-audit/'.$index);
        }
    }

    public function test_recorder_failures_are_swallowed(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        $recorder = new UserActivityRecorder(new class extends ActivityLogSanitizer
        {
            public function sanitize(array $properties): array
            {
                throw new \RuntimeException('Audit storage unavailable');
            }
        });

        $recorder->record(
            Request::create('/dashboard', 'GET'),
            new Response('', 200),
            $user,
        );

        $this->assertTrue(true);
    }

    public function test_request_activities_are_normalized_for_terminal_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        activity('request')
            ->causedBy($admin)
            ->event('request')
            ->withProperties([
                'request' => [
                    'method' => 'GET',
                    'path' => '/api/reports/agent-stats',
                    'route' => 'api.reports.agent-stats',
                    'status' => 404,
                    'ip' => '127.0.0.1',
                    'user_agent' => 'Test browser',
                    'query' => ['campaign' => 'mbsales'],
                ],
            ])
            ->log('GET api/reports/agent-stats');

        $response = $this->actingAs($admin)->getJson(route('admin.activity-log.entries', [
            'event' => 'request',
            'search' => 'agent-stats',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.action', 'GET')
            ->assertJsonPath('data.0.severity', 'warning')
            ->assertJsonPath('data.0.request.method', 'GET')
            ->assertJsonPath('data.0.request.path', '/api/reports/agent-stats')
            ->assertJsonPath('data.0.request.route', 'api.reports.agent-stats')
            ->assertJsonPath('data.0.request.status', 404)
            ->assertJsonPath('data.0.request.query.campaign', 'mbsales');
    }
}
