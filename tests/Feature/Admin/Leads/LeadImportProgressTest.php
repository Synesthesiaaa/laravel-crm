<?php

namespace Tests\Feature\Admin\Leads;

use App\Models\Campaign;
use App\Models\LeadList;
use App\Models\User;
use App\Services\Leads\LeadImportProgressTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadImportProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_poll_own_import_progress(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);

        $runId = (string) Str::uuid();
        app(LeadImportProgressTracker::class)->createQueued($runId, $list->id, $admin->id, 100);

        $response = $this->actingAs($admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('admin.leads.import.progress', ['list' => $list, 'runId' => $runId]));

        $response->assertOk();
        $response->assertJsonPath('status', 'queued');
        $response->assertJsonPath('run_id', $runId);
        $response->assertJsonPath('estimated_rows', 100);
    }

    public function test_admin_cannot_poll_another_users_import_progress(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);

        $runId = (string) Str::uuid();
        app(LeadImportProgressTracker::class)->createQueued($runId, $list->id, $owner->id, 50);

        $this->actingAs($other)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('admin.leads.import.progress', ['list' => $list, 'runId' => $runId]))
            ->assertForbidden();
    }

    public function test_invalid_run_id_returns_422(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('admin.leads.import.progress', ['list' => $list, 'runId' => 'not-a-uuid']))
            ->assertStatus(422);
    }

    public function test_super_admin_can_poll_another_users_import_progress(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $super = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $list = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'L', 'active' => true]);

        $runId = (string) Str::uuid();
        app(LeadImportProgressTracker::class)->createQueued($runId, $list->id, $owner->id, 25);

        $this->actingAs($super)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('admin.leads.import.progress', ['list' => $list, 'runId' => $runId]))
            ->assertOk()
            ->assertJsonPath('user_id', $owner->id);
    }

    public function test_dismiss_track_clears_session(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $runId = (string) Str::uuid();

        $response = $this->actingAs($admin)
            ->withSession([
                'campaign' => 'testcamp',
                'campaign_name' => 'T',
                'lead_import_track' => [
                    'run_id' => $runId,
                    'list_id' => 1,
                    'poll_url' => 'https://example.test/poll',
                    'dismiss_url' => route('admin.leads.import.track.dismiss'),
                ],
            ])
            ->postJson(route('admin.leads.import.track.dismiss'), ['run_id' => $runId]);

        $response->assertOk()->assertJson(['ok' => true])->assertSessionMissing('lead_import_track');
    }

    public function test_wrong_list_for_run_id_returns_403(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Campaign::create(['code' => 'testcamp', 'name' => 'T', 'is_active' => true]);
        $listA = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'A', 'active' => true]);
        $listB = LeadList::create(['campaign_code' => 'testcamp', 'name' => 'B', 'active' => true]);

        $runId = (string) Str::uuid();
        app(LeadImportProgressTracker::class)->createQueued($runId, $listA->id, $admin->id, 10);

        $this->actingAs($admin)
            ->withSession(['campaign' => 'testcamp', 'campaign_name' => 'T'])
            ->getJson(route('admin.leads.import.progress', ['list' => $listB, 'runId' => $runId]))
            ->assertForbidden();
    }

    public function test_dismiss_track_with_stale_flag_forgets_session_even_when_run_id_mismatches(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $sessionRunId = (string) Str::uuid();
        $otherRunId = (string) Str::uuid();

        $this->actingAs($admin)
            ->withSession([
                'campaign' => 'testcamp',
                'campaign_name' => 'T',
                'lead_import_track' => [
                    'run_id' => $sessionRunId,
                    'list_id' => 1,
                    'poll_url' => 'https://example.test/poll',
                    'dismiss_url' => route('admin.leads.import.track.dismiss'),
                ],
            ])
            ->postJson(route('admin.leads.import.track.dismiss'), [
                'run_id' => $otherRunId,
                'stale' => true,
            ])
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertSessionMissing('lead_import_track');
    }
}
