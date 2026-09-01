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
    | Dashboard Calls KPI rolling window (hours)
    |--------------------------------------------------------------------------
    |
    | Used for total calls on the main agent dashboard.
    |
    */

    'kpi_window_hours' => 9,

    /*
    |--------------------------------------------------------------------------
    | Dashboard Sales KPI rolling window (hours)
    |--------------------------------------------------------------------------
    |
    | Used for total sales and top-agent sales metrics on the main dashboard.
    |
    */

    'sales_kpi_window_hours' => 24,

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

    /*
    | Currency used by the executive dashboard summary. Override both values
    | when a deployment reports monetary values in another currency.
    */

    'currency_code' => env('DASHBOARD_CURRENCY_CODE', 'PHP'),
    'currency_symbol' => env('DASHBOARD_CURRENCY_SYMBOL', '₱'),

];
