<?php

namespace App\Support;

/**
 * Standard telephony error codes for structured JSON responses.
 */
final class CallErrors
{
    public const NETWORK_FAILURE = 'NETWORK_FAILURE';

    public const EXTENSION_OFFLINE = 'EXTENSION_OFFLINE';

    public const SIP_NOT_REGISTERED = 'SIP_NOT_REGISTERED';

    public const NO_ANSWER = 'NO_ANSWER';

    public const BUSY = 'BUSY';

    public const CHANNEL_UNAVAILABLE = 'CHANNEL_UNAVAILABLE';

    public const AUTH_FAILURE = 'AUTH_FAILURE';

    public const DIAL_BLOCKED_DISPOSITION = 'DIAL_BLOCKED_DISPOSITION';

    public const ALREADY_IN_CALL = 'ALREADY_IN_CALL';

    public const VICIDIAL_UNAVAILABLE = 'VICIDIAL_UNAVAILABLE';

    /** Agent must be logged into VICIdial (iframe/session) before Agent API dial actions. */
    public const VICIDIAL_AGENT_NOT_LOGGED_IN = 'VICIDIAL_AGENT_NOT_LOGGED_IN';

    /** VICIdial Agent API rejected external_dial for reasons other than auth or login state. */
    public const VICIDIAL_DIAL_FAILED = 'VICIDIAL_DIAL_FAILED';

    public const MESSAGES = [
        self::NETWORK_FAILURE => 'Network error during call setup.',
        self::EXTENSION_OFFLINE => 'Agent extension is offline.',
        self::SIP_NOT_REGISTERED => 'SIP endpoint not registered.',
        self::NO_ANSWER => 'Call was not answered within timeout.',
        self::BUSY => 'Line is busy.',
        self::CHANNEL_UNAVAILABLE => 'No available channel.',
        self::AUTH_FAILURE => 'Authentication failed.',
        self::DIAL_BLOCKED_DISPOSITION => 'Please save disposition for your last call before making a new one.',
        self::ALREADY_IN_CALL => 'Agent already has an active call. Hang up first.',
        self::VICIDIAL_UNAVAILABLE => 'VICIdial is temporarily unavailable.',
        self::VICIDIAL_AGENT_NOT_LOGGED_IN => 'Log into VICIdial before dialing.',
        self::VICIDIAL_DIAL_FAILED => 'VICIdial could not complete the dial request.',
    ];

    public static function toJson(string $code, ?string $asteriskResponse = null): array
    {
        return [
            'error_code' => $code,
            'error_message' => self::MESSAGES[$code] ?? 'Unknown error',
            'asterisk_response' => $asteriskResponse,
        ];
    }
}
