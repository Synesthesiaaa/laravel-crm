<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SupervisorUserNotification;
use App\Services\Telephony\VicidialProxyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupervisorNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_recipient_is_sent_to_vicidial_and_mirrored_to_matching_local_user(): void
    {
        Notification::fake();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vici_user' => 'supervisor',
            'vici_pass' => 'secret',
        ]);
        $agent = User::factory()->create([
            'username' => 'agent-one',
            'vici_user' => '6666',
            'default_campaign' => 'mbsales',
        ]);

        $this->mockVicidialProxySuccess();

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.supervisor.send-notification'), [
                'recipient_type' => 'USER',
                'recipient' => '6666',
                'notification_text' => 'Please return to ready.',
                'show_confetti' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('mirrored_count', 1);

        Notification::assertSentTo($agent, SupervisorUserNotification::class, function (SupervisorUserNotification $notification, array $channels): bool {
            return $channels === ['database', 'broadcast']
                && $notification->message === 'Please return to ready.'
                && $notification->recipientType === 'USER'
                && $notification->recipient === '6666'
                && $notification->showConfetti === true;
        });
    }

    public function test_campaign_recipient_is_mirrored_to_users_with_matching_default_campaign(): void
    {
        Notification::fake();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vici_user' => 'supervisor',
            'vici_pass' => 'secret',
        ]);
        $matchingAgent = User::factory()->create(['default_campaign' => 'pjli']);
        $otherAgent = User::factory()->create(['default_campaign' => 'mbsales']);

        $this->mockVicidialProxySuccess();

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'pjli'])
            ->postJson(route('api.supervisor.send-notification'), [
                'recipient_type' => 'CAMPAIGN',
                'recipient' => 'pjli',
                'notification_text' => 'Queue is changing.',
            ])
            ->assertOk()
            ->assertJsonPath('mirrored_count', 1);

        Notification::assertSentTo($matchingAgent, SupervisorUserNotification::class);
        Notification::assertNotSentTo($otherAgent, SupervisorUserNotification::class);
    }

    public function test_user_group_recipient_remains_vicidial_only(): void
    {
        Notification::fake();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vici_user' => 'supervisor',
            'vici_pass' => 'secret',
        ]);
        $agent = User::factory()->create(['role' => User::ROLE_AGENT]);

        $this->mockVicidialProxySuccess();

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.supervisor.send-notification'), [
                'recipient_type' => 'USER_GROUP',
                'recipient' => 'AGENTS',
                'notification_text' => 'Group message.',
            ])
            ->assertOk()
            ->assertJsonPath('mirrored_count', 0);

        Notification::assertNotSentTo($agent, SupervisorUserNotification::class);
    }

    public function test_failed_vicidial_response_does_not_mirror_laravel_notifications(): void
    {
        Notification::fake();
        $supervisor = User::factory()->create([
            'role' => User::ROLE_SUPER_ADMIN,
            'vici_user' => 'supervisor',
            'vici_pass' => 'secret',
        ]);
        $agent = User::factory()->create(['vici_user' => '6666']);

        $this->mock(VicidialProxyService::class, function ($mock): void {
            $mock->shouldReceive('execute')->once()->andReturn([
                'success' => false,
                'message' => 'VICIdial failed',
                'raw_response' => 'ERROR: failed',
                'failure_code' => 'test',
            ]);
        });

        $this->actingAs($supervisor)
            ->withSession(['campaign' => 'mbsales'])
            ->postJson(route('api.supervisor.send-notification'), [
                'recipient_type' => 'USER',
                'recipient' => '6666',
                'notification_text' => 'Should not mirror.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('mirrored_count', 0);

        Notification::assertNotSentTo($agent, SupervisorUserNotification::class);
    }

    private function mockVicidialProxySuccess(): void
    {
        $this->mock(VicidialProxyService::class, function ($mock): void {
            $mock->shouldReceive('execute')->once()->andReturn([
                'success' => true,
                'message' => null,
                'raw_response' => 'SUCCESS',
                'failure_code' => null,
            ]);
        });
    }
}
