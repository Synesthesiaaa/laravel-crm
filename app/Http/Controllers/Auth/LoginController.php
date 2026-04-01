<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CampaignService;
use App\Services\Telephony\TelephonyBootstrapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected CampaignService $campaignService,
        protected TelephonyBootstrapService $telephonyBootstrap,
    ) {}

    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $campaigns = $this->campaignService->getCampaigns();

        return view('auth.login', ['campaigns' => $campaigns]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $username = $request->string('username')->trim()->toString();
        $password = $request->password;
        $campaign = $request->filled('campaign') ? $request->string('campaign')->trim()->toString() : null;

        $user = $this->authService->validateCredentials($username, $password);
        if (! $user) {
            $request->incrementAttempts();

            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        $request->clearAttempts();

        if ($this->authService->hasOtherActiveSessions($user->id)) {
            $request->session()->put('login_pending', [
                'user_id' => $user->id,
                'expires_at' => now()->addMinutes(5)->getTimestamp(),
                'campaign' => $campaign,
            ]);

            return redirect()->route('login.pending');
        }

        return $this->finalizeLogin($request, $user, $campaign);
    }

    public function showPendingLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        if (! $request->session()->has('login_pending')) {
            return redirect()->route('login');
        }

        return view('auth.login-pending');
    }

    public function confirmPendingLogin(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('login_pending');
        if (! is_array($pending) || empty($pending['user_id'])) {
            return redirect()->route('login')->withErrors([
                'username' => 'Please sign in again.',
            ]);
        }
        if (($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget('login_pending');

            return redirect()->route('login')->withErrors([
                'username' => 'That confirmation expired. Please sign in again.',
            ]);
        }

        $user = User::query()->find($pending['user_id']);
        if (! $user) {
            $request->session()->forget('login_pending');

            return redirect()->route('login')->withErrors([
                'username' => 'Account not found. Please sign in again.',
            ]);
        }

        $campaign = isset($pending['campaign']) && is_string($pending['campaign']) ? $pending['campaign'] : null;
        $request->session()->forget('login_pending');

        return $this->finalizeLogin($request, $user, $campaign);
    }

    public function cancelPendingLogin(Request $request): RedirectResponse
    {
        $request->session()->forget('login_pending');

        return redirect()->route('login')->with('status', 'Sign-in cancelled.');
    }

    private function finalizeLogin(Request $request, User $user, ?string $campaign): RedirectResponse
    {
        $this->authService->loginUserAndInvalidateOthers($user);
        $this->authService->logAttendance($user->id, 'login', $request->ip());

        $campaigns = $this->campaignService->getCampaigns();
        if ($campaign && isset($campaigns[$campaign])) {
            $request->session()->put('campaign', $campaign);
            $request->session()->put('campaign_name', $campaigns[$campaign]['name'] ?? $campaign);
        } else {
            $first = array_key_first($campaigns);
            if ($first) {
                $request->session()->put('campaign', $first);
                $request->session()->put('campaign_name', $campaigns[$first]['name'] ?? $first);
            }
        }
        $request->session()->put('login_time', now()->toDateTimeString());
        $this->telephonyBootstrap->storeBootstrapPayload($request, $user);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->authService->logout();

        return redirect()->route('login');
    }
}
