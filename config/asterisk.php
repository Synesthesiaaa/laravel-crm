<?php

return [

    'webhook_secret' => env('ASTERISK_AMI_WEBHOOK_SECRET', ''),
    'host' => env('ASTERISK_AMI_HOST', '127.0.0.1'),
    'port' => (int) env('ASTERISK_AMI_PORT', 5038),
    'username' => env('ASTERISK_AMI_USERNAME', 'cron'),
    'secret' => env('ASTERISK_AMI_SECRET', ''),
    'timeout' => (int) env('ASTERISK_AMI_TIMEOUT', 5),
    'read_timeout' => (int) env('ASTERISK_AMI_READ_TIMEOUT', 5000),
    'goip_trunk' => env('ASTERISK_GOIP_TRUNK', 'goip-trunk'),
    // SIP-only enforcement for CRM telephony routing.
    'agent_channel' => 'SIP',

    // AMI persistent listener (ami:listen command)
    'reconnect_delay' => (int) env('AMI_LISTENER_RECONNECT_DELAY', 5),
    'max_reconnect_delay' => (int) env('AMI_LISTENER_MAX_RECONNECT_DELAY', 60),

];
