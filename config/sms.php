<?php

return [
    'live_enabled' => (bool) env('SMS_LIVE_ENABLED', false),
    'emergency_stop' => (bool) env('SMS_EMERGENCY_STOP', true),
    'queue_connection' => env('SMS_QUEUE_CONNECTION', 'database'),
    'queue' => 'sms',
    'allowed_ips' => array_values(array_filter(explode(',', env('SMS_ALLOWED_IPS', '')))),
    'max_recipients' => 10000,
    'test_number' => env('SMS_TEST_NUMBER'),
    'large_send_threshold' => 1000,
    'receiver_host' => env('SMS_RECEIVER_HOST', '127.0.0.1'),
    'receiver_port' => (int) env('SMS_RECEIVER_PORT', 44444),
    'retention_days' => (int) env('SMS_RETENTION_DAYS', 0),
];
