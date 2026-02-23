<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback campaign config (used when database is unavailable or empty)
    |--------------------------------------------------------------------------
    */

    'fallback' => [
        'mbsales' => [
            'name' => 'MBSales',
            'description' => 'Main MBSales Campaign',
            'color' => 'blue',
            'forms' => [
                'ezycash' => ['name' => 'EzyCash', 'table' => 'ezycash', 'color' => 'green', 'icon' => 'cash'],
                'ezyconvert' => ['name' => 'EzyConvert', 'table' => 'ezyconvert', 'color' => 'blue', 'icon' => 'convert'],
                'ezytransfer' => ['name' => 'EzyTransfer', 'table' => 'ezytransfer', 'color' => 'purple', 'icon' => 'transfer'],
            ],
        ],
        'pjli' => [
            'name' => 'PJLI',
            'description' => 'PJLI Campaign',
            'color' => 'indigo',
            'forms' => [
                'cycle' => ['name' => 'Cycle', 'table' => 'pjli_cycle', 'color' => 'green', 'icon' => 'cycle'],
                'winback' => ['name' => 'Winback', 'table' => 'pjli_winback', 'color' => 'orange', 'icon' => 'winback'],
                'renewal' => ['name' => 'Renewal', 'table' => 'pjli_renewal', 'color' => 'blue', 'icon' => 'renewal'],
                'ofw' => ['name' => 'OFW', 'table' => 'pjli_ofw', 'color' => 'purple', 'icon' => 'ofw'],
            ],
        ],
    ],

];
