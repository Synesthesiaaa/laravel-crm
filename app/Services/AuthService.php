<?php

namespace App\Services;

use App\Events\UserLoggedIn;
use App\Events\UserLoggedOut;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\UserRepository;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected AttendanceRepository $attendanceRepository,
        protected CallOrchestrationService $callOrchestration
    ) {}

    public function validateCredentials(string $username, string $password): ?User
    {
        return $this->userRepository->validateCredentials($username, $password);
    }

    public function attempt(string $username, string $password): ?User
    {
        $user = $this->validateCredentials($username, $password);
        if ($user) {
            $this->loginUserAndInvalidateOthers($user);
        }

        return $user;
    }

    /** Whether there is at least one session row for this user (another device/browser). */
    public function hasOtherActiveSessions(int $userId): bool
    {
        return DB::table('sessions')->where('user_id', $userId)->count() > 0;
    }

    /** Sign in and end other database sessions for this user (remember-me disabled). */
    public function loginUserAndInvalidateOthers(User $user): void
    {
        Auth::login($user, false);
        $this->invalidateOtherSessions($user->id);
    }

    /** Delete other rows in `sessions` for this user; keep the current session id. */
    public function invalidateOtherSessions(int $userId): void
    {
        $currentId = session()->getId();
        DB::table('sessions')
            ->where('user_id', $userId)
            ->where('id', '!=', $currentId)
            ->delete();
    }

    public function logAttendance(int $userId, string $eventType, ?string $ip = null): void
    {
        DB::transaction(function () use ($userId, $eventType, $ip): void {
            $this->attendanceRepository->log($userId, $eventType, $ip);
            if ($eventType === 'login') {
                event(new UserLoggedIn($userId, $ip));
            }
        });
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            $this->callOrchestration->forceCompleteAllForUser($user);
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
