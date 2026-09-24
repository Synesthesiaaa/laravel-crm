<?php

return [
    'realtime' => [
        'poll_seconds' => (int) env('ATTENDANCE_REALTIME_POLL_SECONDS', 15),
        'max_session_hours' => (int) env('ATTENDANCE_REALTIME_MAX_SESSION_HOURS', 24),
    ],
];
