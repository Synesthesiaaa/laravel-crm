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
        [['urls' => 'stun:'.env('STUN_SERVER', 'stun.l.google.com:19302')]],
        json_decode(env('ICE_SERVERS', '[]'), true) ?: [],
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

    /*
    |--------------------------------------------------------------------------
    | Media path: which WebRTC stack owns the audio for an agent
    |--------------------------------------------------------------------------
    | Accepted values:
    |   - 'sipjs'     : CRM-owned SIP.js (resources/js/telephony-core.js). The
    |                   Vicidial iframe is used ONLY for session UI, no audio.
    |   - 'viciphone' : Vicidial ViciPhone (served inside the session iframe)
    |                   owns the audio. CRM does not call TelephonyCore.register().
    |   - 'both'      : Both are allowed (current legacy behavior). Warning:
    |                   registering the same extension twice can break calls.
    |                   Use only while migrating between the two.
    |
    | The frontend reads this value via the bootstrap payload and decides whether
    | to call TelephonyCore.register() or not. The telephony health check warns
    | if `both` is active.
    */
    'media_path' => env('TELEPHONY_MEDIA_PATH', 'sipjs'),

];
