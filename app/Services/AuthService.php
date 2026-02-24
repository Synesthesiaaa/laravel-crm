<?php

namespace App\Services;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AttendanceRepository $attendanceRepository
    ) {}

    public function attempt(string $username, string $password): ?User
    {
        $user = $this->userRepository->validateCredentials($username, $password);
        if ($user) {
            Auth::login($user, true);
            return $user;
        }
        return null;
    }

    public function logAttendance(int $userId, string $eventType, ?string $ip = null): void
    {
        DB::transaction(function () use ($userId, $eventType, $ip): void {
            $log = $this->attendanceRepository->log($userId, $eventType, $ip);
            if ($eventType === 'login') {
                event(new UserLoggedIn($userId, $ip));
            }
        });
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            DB::transaction(function () use ($user): void {
                $this->attendanceRepository->log($user->id, 'logout', request()?->ip());
                event(new UserLoggedOut($user->id));
            });
        }
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
