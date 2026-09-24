<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\Repositories\AttendanceRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttendanceLogsIndexRequest;
use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use Illuminate\View\View;

class AttendanceLogsController extends Controller
{
    public function __construct(
        protected AttendanceRepositoryInterface $attendanceRepository,
    ) {}

    public function index(AttendanceLogsIndexRequest $request): View
    {
        $filters = $request->attendanceFilters();
        $resultLimit = 100;
        $logs = $this->attendanceRepository->getLogs(
            $filters['user_id'],
            $filters['date'],
            $resultLimit,
            $filters['event'],
        );
        $statusTypes = AttendanceStatusType::query()
            ->select(['id', 'label'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $eventOptions = collect([
            'login' => 'Login',
            'logout' => 'Logout',
        ]);
        foreach ($statusTypes as $statusType) {
            $eventOptions->put((string) $statusType->id, $statusType->label);
        }

        return view('admin.attendance_logs', [
            'logs' => $logs,
            'filters' => $filters,
            'eventOptions' => $eventOptions,
            'resultLimit' => $resultLimit,
            'summary' => [
                'total' => $logs->count(),
                'login' => $logs->where('event_type', 'login')->count(),
                'logout' => $logs->where('event_type', 'logout')->count(),
                'away' => $logs->whereNotIn('event_type', AttendanceLog::SYSTEM_EVENT_TYPES)->count(),
            ],
        ]);
    }
}
