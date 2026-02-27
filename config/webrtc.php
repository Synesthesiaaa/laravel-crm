<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asterisk WebSocket URL (WSS endpoint for SIP.js)
    |--------------------------------------------------------------------------
    */
    'asterisk_ws_url' => env('ASTERISK_WS_URL', 'wss://127.0.0.1:8089/ws'),

    /*
    |--------------------------------------------------------------------------
    | SIP Domain (Asterisk server IP / hostname)
    |--------------------------------------------------------------------------
    */
    'sip_domain' => env('SIP_DOMAIN', '127.0.0.1'),

    /*
    |--------------------------------------------------------------------------
    | STUN Server for ICE negotiation
    |--------------------------------------------------------------------------
    */
    'stun_server' => env('STUN_SERVER', 'stun:stun.l.google.com:19302'),

    /*
    |--------------------------------------------------------------------------
    | Additional ICE servers (TURN) as a JSON array in .env
    | Example: [{"urls":"turn:turn.example.com","username":"u","credential":"p"}]
    |--------------------------------------------------------------------------
    */
    'ice_servers' => array_merge(
        [['urls' => 'stun:' . env('STUN_SERVER', 'stun.l.google.com:19302')]],
        json_decode(env('ICE_SERVERS', '[]'), true) ?: []
    ),

    /*
    |--------------------------------------------------------------------------
    | SIP Registration timeout (seconds)
    |--------------------------------------------------------------------------
    */
    'register_timeout' => (int) env('SIP_REGISTER_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | No-answer timeout (seconds) – SIP.js side timer
    |--------------------------------------------------------------------------
    */
    'no_answer_timeout' => (int) env('SIP_NO_ANSWER_TIMEOUT', 45),

];
