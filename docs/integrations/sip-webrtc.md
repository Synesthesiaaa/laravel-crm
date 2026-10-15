# SIP and WebRTC

SIP.js (`sip.js` dependency) manages the CRM-owned browser SIP path. Asterisk WSS/SIP signaling and RTP/ICE media are independent of Laravel's HTTP session and Reverb event connection.

[config/webrtc.php](../../config/webrtc.php) declares `ASTERISK_WS_URL`, `SIP_DOMAIN`, `STUN_SERVER`, additional JSON `ICE_SERVERS`, `SIP_REGISTER_TIMEOUT`, `SIP_NO_ANSWER_TIMEOUT`, and `TELEPHONY_MEDIA_PATH`. Defaults use local hostnames/addresses and are not proof of reachability. The config builds its ICE list by prepending `stun:` to STUN_SERVER while also exposing a separate stun_server setting; inspect the resulting URI before supplying an already-prefixed value. Additional TURN credentials are sensitive configuration.

GET `/api/sip/credentials` is a browser-session/campaign endpoint, throttled by the telephony-poll limiter. `SipCredentialsController` returns 422 with a setup message when the current user's extension or SIP password is missing. Success returns `success`, `sip_uri`, `ws_url`, `extension`, `password`, `stun`, `domain`, `ice_servers`, and `no_answer_timeout`. The password is intentionally delivered to the authenticated browser for SIP authentication; never include the response in docs, shared logs or screenshots. A Sanctum bearer token is not a substitute for its browser middleware.

Use the [browser softphone guide](browser-softphone.md) to choose one media owner. SIP registration does not prove outbound dialing permissions or two-way audio. Reverb connectivity does not prove SIP transport connectivity. A local call record completed by a webhook does not prove the browser heard audio.

For authorized deployment validation, separately check WSS certificate trust, Asterisk websocket/SIP configuration, extension credentials, microphone permissions, codec/media negotiation, STUN/TURN/NAT/firewall traversal, and audio in both directions. These are external environment checks, all unverified in this review. The preserved [Asterisk setup guide](../asterisk/ASTERISK_WEBRTC_SETUP.md) offers operator background and must be reconciled with the actual server version and current configuration before applying commands.
