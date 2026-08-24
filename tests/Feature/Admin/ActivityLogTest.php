<?php

namespace Tests\Feature\Admin;

use App\Events\ActivityLogCreated;
use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Listeners\LogSecurityEvent;
use App\Models\Campaign;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\TelephonyFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_requires_super_admin_access(): void
    {
        $this->get(route('admin.activity-log.index'))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('admin.activity-log.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_render_the_terminal_activity_log(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.activity-log.index'))
            ->assertOk()
            ->assertSee('Activity Log', false)
            ->assertSee('LIVE ACTIVITY STREAM', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-busy="loading"', false)
            ->assertSee('activity-terminal-entry__meta', false)
            ->assertSee('Follow', false)
            ->assertSee('Pause', false);
    }

    public function test_activity_properties_are_redacted_before_persistence(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        activity('security')
            ->causedBy($admin)
            ->withProperties([
                'attributes' => [
                    'username' => 'operator',
                    'api_token' => 'secret-token',
                ],
            ])
            ->log('Updated integration credentials');

        $activity = Activity::query()->latest('id')->firstOrFail();

        $this->assertSame('operator', $activity->properties->get('attributes')['username']);
        $this->assertSame('[REDACTED]', $activity->properties->get('attributes')['api_token']);
    }

    public function test_super_admin_receives_filtered_normalized_entries(): void
    {
        $admin = User::factory()->create([
            'username' => 'supervisor',
            'full_name' => 'System Supervisor',
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);

        activity('configuration')
            ->causedBy($admin)
            ->performedOn($campaign)
            ->event('updated')
            ->withProperties([
                'attributes' => ['name' => 'Updated campaign'],
                'old' => ['name' => 'Original campaign'],
            ])
            ->log('updated');

        activity('configuration')
            ->causedBy($admin)
            ->event('created')
            ->withProperties(['attributes' => ['name' => 'Ignored entry']])
            ->log('created');

        $response = $this->actingAs($admin)->getJson(route('admin.activity-log.entries', [
            'event' => 'updated',
            'search' => 'campaign',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor', 'System Supervisor')
            ->assertJsonPath('data.0.action', 'updated')
            ->assertJsonPath('data.0.resource_type', 'Campaign')
            ->assertJsonPath('data.0.changes.attributes.name', 'Updated campaign')
            ->assertJsonPath('data.0.changes.old.name', 'Original campaign');
    }

    public function test_configuration_model_changes_record_actor_and_before_after_values(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($admin);
        $systemSetting = SystemSetting::query()->create([
            'setting_key' => 'telephony_feature_session_controls',
            'setting_value' => '1',
        ]);
        $systemSetting->update(['setting_value' => '0']);

        $activity = Activity::query()
            ->where('subject_type', SystemSetting::class)
            ->where('subject_id', $systemSetting->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame('0', $activity->properties->get('attributes')['setting_value']);
        $this->assertSame('1', $activity->properties->get('old')['setting_value']);
    }

    public function test_login_and_logout_are_audited_with_actor_and_ip(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $listener = app(LogSecurityEvent::class);

        $listener->handle(new UserLoggedIn($admin->id, '127.0.0.1'));
        $listener->handle(new UserLoggedOut($admin->id));

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $admin->id,
            'event' => 'login',
            'description' => 'User logged in',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $admin->id,
            'event' => 'logout',
            'description' => 'User logged out',
        ]);

        $login = Activity::query()->where('event', 'login')->latest('id')->firstOrFail();
        $this->assertSame('127.0.0.1', $login->properties->get('ip_address'));
    }

    public function test_telephony_feature_changes_are_audited_as_one_configuration_action(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($admin);

        $service = app(TelephonyFeatureService::class);
        $service->flush();
        $before = $service->getAll();
        $expected = ! $before['session_controls'];
        $service->updateMany($expected ? ['session_controls' => '1'] : []);

        $activity = Activity::query()
            ->where('description', 'Telephony feature access updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame($expected, $activity->properties->get('attributes')['features']['session_controls']);
        $this->assertSame(! $expected, $activity->properties->get('old')['features']['session_controls']);
    }

    public function test_broadcast_failure_does_not_block_activity_persistence(): void
    {
        Event::listen(ActivityLogCreated::class, static function (): void {
            throw new \RuntimeException('Reverb is unavailable');
        });

        $admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        activity('configuration')
            ->causedBy($admin)
            ->event('updated')
            ->withProperties(['attributes' => ['setting' => 'safe']])
            ->log('Recorded despite realtime outage');

        $this->assertDatabaseHas('activity_log', [
            'causer_id' => $admin->id,
            'description' => 'Recorded despite realtime outage',
        ]);
    }
}
