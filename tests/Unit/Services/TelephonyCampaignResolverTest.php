<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\Telephony\TelephonyCampaignResolver;
use Illuminate\Http\Request;
use Tests\TestCase;

class TelephonyCampaignResolverTest extends TestCase
{
    public function test_for_request_prefers_saved_vicidial_campaign(): void
    {
        $user = User::factory()->make([
            'default_campaign' => 'defaultcamp',
        ]);

        $request = Request::create('/telephony', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(static fn () => $user);
        $request->session()->put('vicidial_campaign', 'sessioncamp');

        $this->assertSame('sessioncamp', TelephonyCampaignResolver::forRequest($request));
    }

    public function test_for_request_falls_back_to_user_default_campaign(): void
    {
        $user = User::factory()->make([
            'default_campaign' => 'defaultcamp',
        ]);

        $request = Request::create('/telephony', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(static fn () => $user);

        $this->assertSame('defaultcamp', TelephonyCampaignResolver::forRequest($request));
    }

    public function test_for_request_falls_back_to_mbsales_when_no_session_or_user_campaign_exists(): void
    {
        $user = User::factory()->make([
            'default_campaign' => null,
        ]);

        $request = Request::create('/telephony', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(static fn () => $user);

        $this->assertSame('mbsales', TelephonyCampaignResolver::forRequest($request));
    }

    public function test_resolve_uses_explicit_campaign_when_present(): void
    {
        $user = User::factory()->make([
            'default_campaign' => 'defaultcamp',
        ]);

        $request = Request::create('/telephony', 'GET');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(static fn () => $user);
        $request->session()->put('vicidial_campaign', 'sessioncamp');

        $this->assertSame('explicitcamp', TelephonyCampaignResolver::resolve($request, 'explicitcamp'));
    }
}
