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
    | Report system disposition codes
    |--------------------------------------------------------------------------
    | Vicidial disposition codes that should be treated as system-generated.
    | They can be hidden in the dashboard and excluded from CRM report rows.
    */
    'report_system_disposition_codes' => array_values(array_filter(array_map(
        static fn ($code) => trim((string) $code),
        explode(',', (string) env('VICI_REPORT_SYSTEM_DISPOSITION_CODES', '')),
    ))),
    'report_disposition_groups' => [
        'contacted' => array_values(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            explode(',', (string) env('VICI_REPORT_CONTACT_DISPOSITION_CODES', '')),
        ))),
        'qualified' => array_values(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            explode(',', (string) env('VICI_REPORT_QUALIFIED_DISPOSITION_CODES', '')),
        ))),
        'successful' => array_values(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            explode(',', (string) env('VICI_REPORT_SUCCESS_DISPOSITION_CODES', '')),
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisor operational thresholds
    |--------------------------------------------------------------------------
    | These values are deliberately conservative defaults. A queue is unknown
    | when the required signal is not returned by VICIdial or CRM fallback.
    */
    'supervisor' => [
        'poll_seconds' => (int) env('VICI_SUPERVISOR_POLL_SECONDS', 15),
        'queue' => [
            'warning_waiting_calls' => (int) env('VICI_QUEUE_WARNING_WAITING_CALLS', 5),
            'critical_waiting_calls' => (int) env('VICI_QUEUE_CRITICAL_WAITING_CALLS', 10),
            'warning_oldest_wait_seconds' => (int) env('VICI_QUEUE_WARNING_OLDEST_WAIT_SECONDS', 60),
            'critical_oldest_wait_seconds' => (int) env('VICI_QUEUE_CRITICAL_OLDEST_WAIT_SECONDS', 180),
            'warning_no_available_agents' => (int) env('VICI_QUEUE_WARNING_NO_AVAILABLE_AGENTS', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent session defaults
    |--------------------------------------------------------------------------
    */
    'pause_codes' => ['BREAK', 'LUNCH', 'MEET', 'COACH', 'SYSTEM'],
    'session_status_poll_seconds' => (int) env('VICI_SESSION_STATUS_POLL_SECONDS', 15),
    'auto_bootstrap_on_crm_login' => env('VICI_AUTO_BOOTSTRAP', false),
    'default_campaign' => env('VICI_DEFAULT_CAMPAIGN', 'mbsales'),

    /*
    |--------------------------------------------------------------------------
    | Session: iframe + Agent API only (no Non-Agent for verify / status)
    |--------------------------------------------------------------------------
    | When true, the CRM does not call non_agent_api.php for session verify or
    | for agent_status / agent_ingroup_info on GET session/status. The browser
    | treats iframe load + one POST /verify as "ready" (verify promotes the local
    | row without polling VICIdial live_agents). Queue count still uses Agent API.
    | Reporting, leads, and other features keep using Non-Agent until migrated.
    */
    'session_iframe_agent_api_only' => env('VICI_SESSION_IFRAME_AGENT_API_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Iframe-only mode: confirm live agent via Non-Agent agent_status when possible
    |--------------------------------------------------------------------------
    | When session_iframe_agent_api_only is true AND a vicidial_servers row exists for
    | the campaign with non_agent_api access, POST /verify calls agent_status once.
    | If VICIdial reports the agent is not in live_agents, verify fails (no false ready).
    | If Non-Agent is unreachable or not configured, verify still promotes (trust iframe).
    */
    'session_iframe_confirm_non_agent_live' => env('VICI_SESSION_IFRAME_CONFIRM_NON_AGENT_LIVE', false),

    /*
    |--------------------------------------------------------------------------
    | Skip Non-Agent live_agents check on verify (local / explicit override)
    |--------------------------------------------------------------------------
    | When true, POST /verify never calls Non-Agent agent_status in iframe-only mode;
    | session is promoted like when Non-Agent is unavailable (trust iframe).
    | Default: true when APP_ENV is local or development; otherwise false. Set
    | VICI_SESSION_SKIP_NON_AGENT_LIVE_CHECK=true|false to override any environment.
    */
    'session_iframe_skip_non_agent_live_check' => env('VICI_SESSION_SKIP_NON_AGENT_LIVE_CHECK') !== null
        ? filter_var(env('VICI_SESSION_SKIP_NON_AGENT_LIVE_CHECK'), FILTER_VALIDATE_BOOLEAN)
        : in_array(env('APP_ENV', 'production'), ['local', 'development'], true),

    /*
    |--------------------------------------------------------------------------
    | Block outbound dial until CRM vicidial_agent_sessions is usable
    |--------------------------------------------------------------------------
    | When true, startOutboundCall requires session_status in ready, paused, or in_call for
    | the same campaign (HTTP and PHPUnit). Artisan CLI skips this (e.g. telephony:smoke-dial).
    | Set VICI_REQUIRE_VICIDIAL_SESSION_BEFORE_DIAL=false to disable the check entirely.
    */
    'require_vicidial_agent_session_before_dial' => env('VICI_REQUIRE_VICIDIAL_SESSION_BEFORE_DIAL', true),

    /*
    |--------------------------------------------------------------------------
    | Agent Events Push webhook secret
    |--------------------------------------------------------------------------
    | Optional shared secret to validate ViciDial push event POSTs.
    */
    'events_webhook_secret' => env('VICIDIAL_EVENTS_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Vicidial Call URL shared secret
    |--------------------------------------------------------------------------
    | Optional shared secret to validate Vicidial campaign GET callbacks.
    | Defaults to the agent push secret so one value can protect both paths.
    */
    'call_url_secret' => env('VICIDIAL_CALL_URL_SECRET', env('VICIDIAL_EVENTS_WEBHOOK_SECRET', '')),

    /*
    |--------------------------------------------------------------------------
    | Embedded agent iframe panel (phone widget, expanded)
    |--------------------------------------------------------------------------
    | Mirrors crm_settings.php agent_screen_width / height for the Laravel UI.
    */
    'session_iframe_panel_width_px' => (int) env('VICI_IFRAME_WIDTH', 440),
    'session_iframe_panel_height_px' => (int) env('VICI_IFRAME_HEIGHT', 360),

];
