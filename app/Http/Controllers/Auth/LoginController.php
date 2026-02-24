<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected CampaignService $campaignService
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

        $user = $this->authService->attempt($username, $password);
        if (!$user) {
            $request->incrementAttempts();
            throw ValidationException::withMessages([
                'username' => [__('auth.failed')],
            ]);
        }

        $request->clearAttempts();
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

        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(\Illuminate\Http\Request $request): RedirectResponse
    {
        $this->authService->logout();
        return redirect()->route('login');
    }
}
