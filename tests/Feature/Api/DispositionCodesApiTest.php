<?php

namespace Tests\Feature\Api;

use App\Models\DispositionCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispositionCodesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/disposition-codes?campaign=test');
        $response->assertUnauthorized();
    }

    public function test_api_requires_campaign_parameter(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/disposition-codes');
        $response->assertStatus(422);
    }

    public function test_api_returns_disposition_codes_for_campaign(): void
    {
        $user = User::factory()->create(['role' => 'Agent']);
        $token = $user->createToken('test')->plainTextToken;

        DispositionCode::create([
            'campaign_code' => 'test_campaign',
            'code' => 'SALE',
            'label' => 'Sale',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/disposition-codes?campaign=test_campaign');
        $response->assertOk()
            ->assertJsonStructure(['success', 'data'])
            ->assertJsonFragment(['code' => 'SALE']);
    }
}
