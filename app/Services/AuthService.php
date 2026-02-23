<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;

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
        $this->attendanceRepository->log($userId, $eventType, $ip);
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->attendanceRepository->log($user->id, 'logout', request()?->ip());
        }
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
