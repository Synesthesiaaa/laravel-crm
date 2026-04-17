<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\DispositionCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_disposition_save_requires_authentication(): void
    {
        $response = $this->post(route('api.disposition.save'), [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            '_token' => csrf_token(),
        ]);
        $response->assertRedirect(route('login'));
    }

    public function test_disposition_save_succeeds_when_authenticated(): void
    {
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['username' => 'agent1']);
        $response = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])->postJson(route('api.disposition.save'), [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
        ]);
        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('campaign_disposition_records', [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'agent' => $user->full_name ?? $user->name ?? $user->username,
        ]);
    }

    public function test_disposition_save_with_call_session_id_updates_session(): void
    {
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['username' => 'agent1']);
        $session = CallSession::factory()
            ->for($user)
            ->completed()
            ->create([
                'campaign_code' => 'mbsales',
                'disposition_code' => null,
            ]);

        $response = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])->postJson(route('api.disposition.save'), [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            'call_session_id' => $session->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('campaign_disposition_records', [
            'campaign_code' => 'mbsales',
            'call_session_id' => $session->id,
            'disposition_code' => 'SALE',
        ]);
        $session->refresh();
        $this->assertEquals('SALE', $session->disposition_code);
        $this->assertNotNull($session->disposition_at);
    }

    public function test_disposition_force_ends_still_active_call(): void
    {
        // Policy change (2026-04): if the session is still active when disposition
        // is submitted, DispositionService force-completes it rather than reject.
        // This prevents stuck sessions when hangup fails to transition. The save
        // must succeed and the session must end up terminal.
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $session = CallSession::factory()
            ->for($user)
            ->inCall()
            ->create(['campaign_code' => 'mbsales']);

        $response = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])->postJson(route('api.disposition.save'), [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'SALE',
            'disposition_label' => 'Sale',
            'call_session_id' => $session->id,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('campaign_disposition_records', [
            'call_session_id' => $session->id,
            'disposition_code' => 'SALE',
        ]);
        $session->refresh();
        $this->assertTrue($session->isTerminal(), 'Session should be force-completed.');
    }

    public function test_disposition_duplicate_rejected(): void
    {
        DispositionCode::create([
            'campaign_code' => '',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
        ]);
        $user = User::factory()->create();
        $session = CallSession::factory()
            ->for($user)
            ->completed()
            ->create([
                'campaign_code' => 'mbsales',
                'disposition_code' => 'SALE',
                'disposition_at' => now(),
            ]);

        $response = $this->actingAs($user)->withSession(['campaign' => 'mbsales'])->postJson(route('api.disposition.save'), [
            'campaign_code' => 'mbsales',
            'disposition_code' => 'DNC',
            'disposition_label' => 'DNC',
            'call_session_id' => $session->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }
}
