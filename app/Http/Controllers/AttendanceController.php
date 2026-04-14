<?php

namespace App\Http\Controllers;

use App\Repositories\AttendanceRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceRepository $attendanceRepository
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $date = $request->get('date', now()->format('Y-m-d'));
        $logs = $this->attendanceRepository->getLogs($user->id, $date, 50);
        $lastEvent = $this->attendanceRepository->getLastEvent($user->id);

        $tz = config('app.timezone');

        return Inertia::render('Attendance/Index', [
            'logs' => $logs->map(fn ($log) => [
                'event_type' => $log->event_type,
                'event_time' => $log->event_time?->timezone($tz)->format('Y-m-d H:i:s T'),
                'ip_address' => $log->ip_address,
            ])->values()->all(),
            'lastEvent' => $lastEvent ? [
                'event_type' => $lastEvent->event_type,
                'event_time' => $lastEvent->event_time?->timezone($tz)->format('M j, Y g:i A T'),
            ] : null,
            'date' => $date,
        ]);
    }
}
