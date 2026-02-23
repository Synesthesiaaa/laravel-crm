<?php

namespace Tests\Feature;

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
        $user = User::factory()->create(['username' => 'agent1']);
        $response = $this->actingAs($user)->postJson(route('api.disposition.save'), [
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
}
