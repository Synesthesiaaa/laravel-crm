<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VICIdial API Defaults
    |--------------------------------------------------------------------------
    */

    'api_url' => env('VICI_API_URL', 'http://10.10.88.138/agc/api.php'),
    'non_agent_api_url' => env('VICI_NON_AGENT_API_URL', ''),
    'default_source' => env('VICI_SOURCE', 'crm_tracker'),
    'timeout' => (int) env('VICI_API_TIMEOUT', 10),
    'connect_timeout' => (int) env('VICI_CONNECT_TIMEOUT', 5),
    'retry_times' => (int) env('VICI_RETRY_TIMES', 2),
    'retry_sleep_ms' => (int) env('VICI_RETRY_SLEEP_MS', 500),

    /*
    |--------------------------------------------------------------------------
    | SSL Verification
    |--------------------------------------------------------------------------
    | Set to false when connecting to an on-premise ViciDial server that uses
    | a self-signed certificate. Never disable in production with public certs.
    */
    'verify_ssl' => env('VICI_VERIFY_SSL', true),

    /*
    |--------------------------------------------------------------------------
    | Disposition code mapping (Laravel code => VICIdial status)
    |--------------------------------------------------------------------------
    */
    'disposition_map' => [
        'SALE' => 'SALE',
        'CBH' => 'CBH',
        'CBW' => 'CBW',
        'CBC' => 'CBC',
        'DNC' => 'DNC',
        'NAN' => 'NAN',
        'NA' => 'NA',
        'BUSY' => 'BUSY',
        'OTHER' => 'OTHER',
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent session defaults
    |--------------------------------------------------------------------------
    */
    'pause_codes' => ['BREAK', 'LUNCH', 'MEET', 'COACH', 'SYSTEM'],
    'session_status_poll_seconds' => (int) env('VICI_SESSION_STATUS_POLL_SECONDS', 15),

    /*
    |--------------------------------------------------------------------------
    | Agent Events Push webhook secret
    |--------------------------------------------------------------------------
    | Optional shared secret to validate ViciDial push event POSTs.
    */
    'events_webhook_secret' => env('VICIDIAL_EVENTS_WEBHOOK_SECRET', ''),

];
