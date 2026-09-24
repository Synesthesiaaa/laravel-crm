<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? now()->format('Y-m-d');
        $logs = $this->attendanceRepository->getLogs($user->id, $date, 50);
        $lastEvent = $this->attendanceRepository->getLastEvent($user->id);

        return view('attendance.index', [
            'user' => $user,
            'logs' => $logs,
            'lastEvent' => $lastEvent,
            'date' => $date,
            'campaignName' => $request->session()->get('campaign_name', 'CRM'),
        ]);
    }
}
