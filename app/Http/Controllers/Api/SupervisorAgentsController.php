<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SupervisorAgentsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $today = Carbon::today();

        // Get users with role Agent/Team Leader who have logged in today
        $users = User::whereIn('role', ['Agent', 'Team Leader'])
            ->with(['attendanceLogs' => function ($q) use ($today) {
                $q->whereDate('event_time', $today)->orderByDesc('event_time');
            }])
            ->get();

        $agents = $users->map(function (User $user) use ($today) {
            $latestLog = $user->attendanceLogs->first();
            $isOnline  = $latestLog?->event_type === 'login';
            $status    = $isOnline ? 'available' : 'offline';

            return [
                'id'           => $user->id,
                'name'         => $user->full_name ?? $user->username,
                'status'       => $status,
                'status_label' => ucfirst($status),
                'calls_today'  => 0,
                'avg_handle'   => 0,
                'dispositions' => 0,
                'since'        => $latestLog?->event_time?->format('H:i') ?? '—',
                'current_call' => null,
            ];
        });

        $stats = [
            'agentsOnline'  => $agents->where('status', '!=', 'offline')->count(),
            'callsWaiting'  => 0,
            'callsActive'   => 0,
            'avgWaitTime'   => 0,
            'todayTotal'    => 0,
            'slaPercent'    => 100,
        ];

        return response()->json(compact('agents', 'stats'));
    }
}
