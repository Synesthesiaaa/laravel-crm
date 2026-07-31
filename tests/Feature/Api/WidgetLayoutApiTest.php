<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidgetLayoutApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_endpoints_require_authentication(): void
    {
        $this->getJson('/api/widgets/layouts')->assertUnauthorized();
        $this->putJson('/api/widgets/layouts/softphone', [
            'layout' => ['x' => 20, 'y' => 20, 'width' => 420, 'height' => 360],
        ])->assertUnauthorized();
    }

    public function test_update_rejects_invalid_widget_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/widgets/layouts/not_allowed', [
                'layout' => ['x' => 20, 'y' => 20, 'width' => 420, 'height' => 360],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['widget']);
    }

    public function test_update_validates_layout_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/widgets/layouts/softphone', [
                'layout' => ['width' => 100, 'height' => 90],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layout.width', 'layout.height']);
    }

    public function test_layouts_are_persisted_per_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA)
            ->putJson('/api/widgets/layouts/softphone', [
                'layout' => [
                    'x' => 32,
                    'y' => 48,
                    'width' => 520,
                    'height' => 480,
                    'controlsHeight' => 280,
                    'open' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($userB)
            ->putJson('/api/widgets/layouts/softphone', [
                'layout' => ['x' => 8, 'y' => 12, 'width' => 400, 'height' => 320, 'open' => false],
            ])
            ->assertOk();

        $this->actingAs($userA)
            ->getJson('/api/widgets/layouts')
            ->assertOk()
            ->assertJsonPath('layouts.softphone.x', 32)
            ->assertJsonPath('layouts.softphone.controlsHeight', 280)
            ->assertJsonPath('layouts.softphone.open', true);

        $this->actingAs($userB)
            ->getJson('/api/widgets/layouts')
            ->assertOk()
            ->assertJsonPath('layouts.softphone.x', 8)
            ->assertJsonPath('layouts.softphone.open', false);
    }

    public function test_workspace_split_screen_preference_is_persisted_per_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/widgets/layouts/workspace', [
                'layout' => ['splitScreen' => true],
            ])
            ->assertOk()
            ->assertJsonPath('layout.splitScreen', true);

        $this->actingAs($user)
            ->getJson('/api/widgets/layouts')
            ->assertOk()
            ->assertJsonPath('layouts.workspace.splitScreen', true);
    }

    public function test_workspace_rejects_non_boolean_split_screen_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/widgets/layouts/workspace', [
                'layout' => ['splitScreen' => 'yes'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['layout.splitScreen']);
    }
}
