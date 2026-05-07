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

];
