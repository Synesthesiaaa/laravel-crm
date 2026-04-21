<?php

namespace App\Http\Controllers;

use App\Repositories\AttendanceRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceRepository $attendanceRepository,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $date = $request->get('date', now()->format('Y-m-d'));
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
