<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sale KPI disposition codes
    |--------------------------------------------------------------------------
    |
    | Counts rows in campaign_disposition_records where disposition_code equals
    | one of these values (exact match, case-sensitive).
    |
    */

    'sale_disposition_codes' => [
        'SALE',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard KPI rolling window (hours)
    |--------------------------------------------------------------------------
    |
    | Used for total calls, total sales, and top agent metrics on the main
    | agent dashboard.
    |
    */

    'kpi_window_hours' => 9,

    /*
    |--------------------------------------------------------------------------
    | Agent leaderboard (month-to-date on dashboard)
    |--------------------------------------------------------------------------
    */

    'agent_leaderboard_limit' => 25,

    /*
    | First matching numeric key wins per sale disposition row (lead_data_json).
    */

    'sale_amount_json_keys' => [
        'ezycash_amount',
        'amount',
        'loan_amount',
    ],

    /*
    | Cache TTL (seconds) for the rolling 24-hour submissions chart.
    */

    'last_24h_activity_cache_seconds' => 120,

];
