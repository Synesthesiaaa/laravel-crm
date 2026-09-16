<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthCsrfRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_login_token_redirects_to_login_without_flashing_secrets(): void
    {
        $response = $this->renderCsrfMismatch(route('login'), [
            'username' => 'recoverable-agent',
            'campaign' => 'mbsales',
            'password' => 'must-not-be-flashed',
            '_token' => 'stale-token',
        ]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('login'), $response->headers->get('Location'));
        $this->assertSame('recoverable-agent', session('_old_input.username'));
        $this->assertSame('mbsales', session('_old_input.campaign'));
        $this->assertNull(session('_old_input.password'));
        $this->assertNull(session('_old_input._token'));
        $this->assertSame(
            'Your sign-in session expired. Please try again.',
            session('errors')->first('username'),
        );
    }

    public function test_stale_authenticated_logout_token_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->renderCsrfMismatch(
            route('logout'),
            userResolver: static fn () => $user,
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('dashboard'), $response->headers->get('Location'));
        $this->assertSame(
            'Your sign-out session expired. Please try again.',
            session('error'),
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_json_and_unrelated_csrf_mismatches_keep_the_default_419_response(): void
    {
        $jsonResponse = $this->renderCsrfMismatch(
            route('login'),
            server: ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertSame(419, $jsonResponse->getStatusCode());

        $unrelatedRequest = Request::create('/unrelated-form', 'POST');
        $unrelatedRequest->setRouteResolver(static fn () => null);
        $unrelatedRequest->setLaravelSession(app('session.store'));
        $unrelatedResponse = app('Illuminate\Contracts\Debug\ExceptionHandler')->render(
            $unrelatedRequest,
            new TokenMismatchException,
        );

        $this->assertSame(419, $unrelatedResponse->getStatusCode());
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $server
     */
    private function renderCsrfMismatch(
        string $url,
        array $input = [],
        array $server = [],
        ?callable $userResolver = null,
    ): Response {
        $request = Request::create($url, 'POST', $input, [], [], $server);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        $request->setLaravelSession(app('session.store'));

        if ($userResolver !== null) {
            $request->setUserResolver($userResolver);
        }

        return app('Illuminate\Contracts\Debug\ExceptionHandler')->render(
            $request,
            new TokenMismatchException,
        );
    }
}
