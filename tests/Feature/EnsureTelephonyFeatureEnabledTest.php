<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTelephonyFeatureEnabled;
use App\Models\User;
use App\Services\TelephonyFeatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureTelephonyFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_role_field_bypasses_disabled_feature(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $features = Mockery::mock(TelephonyFeatureService::class);
        $features->shouldNotReceive('isEnabled');
        $middleware = new EnsureTelephonyFeatureEnabled($features);
        $request = Request::create('/agent');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('allowed'), 'agent_screen_access');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_spatie_pivot_does_not_override_the_supported_users_role_value(): void
    {
        Role::query()->firstOrCreate(['name' => User::ROLE_SUPER_ADMIN, 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        $user->assignRole(User::ROLE_SUPER_ADMIN);
        $this->assertTrue($user->hasRole(User::ROLE_SUPER_ADMIN));

        $features = Mockery::mock(TelephonyFeatureService::class);
        $features->shouldReceive('isEnabled')->once()->with('agent_screen_access')->andReturn(false);
        $middleware = new EnsureTelephonyFeatureEnabled($features);
        $request = Request::create('/api/agent');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('allowed'), 'agent_screen_access');

        $this->assertSame(403, $response->getStatusCode());
    }
}
