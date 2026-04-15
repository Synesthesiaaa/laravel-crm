<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStatusType;
use App\Repositories\AttendanceRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceLogsController extends Controller
{
    public function __construct(
        protected AttendanceRepository $attendanceRepository,
    ) {}

    public function index(Request $request): View
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : null;
        $date = $request->query('date');
        $event = $request->query('event');
        $eventFilter = is_string($event) && $event !== '' ? $event : null;
        $logs = $this->attendanceRepository->getLogs($userId, $date, 100, $eventFilter);

        return view('admin.attendance_logs', [
            'logs' => $logs,
            'statusTypes' => AttendanceStatusType::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }
}
