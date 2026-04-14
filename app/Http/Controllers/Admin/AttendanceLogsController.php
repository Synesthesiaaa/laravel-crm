<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\AttendanceRepository;
use Illuminate\Http\Request;
use Inertia\Response;

class AttendanceLogsController extends Controller
{
    public function __construct(
        protected AttendanceRepository $attendanceRepository
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : null;
        $date = $request->query('date');
        $logs = $this->attendanceRepository->getLogs($userId, $date, 100);

        return $this->inertiaAdmin('admin.inline-attendance_logs', [
            'logs' => $logs,
        ], 'Attendance Logs');
    }
}
