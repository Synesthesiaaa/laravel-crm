# Asterisk AMI integration

AMI is the Asterisk Manager Interface over TCP; it is distinct from the browser SIP-over-WebSocket transport and the Laravel Reverb websocket. [config/asterisk.php](../../config/asterisk.php) defines host/port, AMI username/secret, connection/read timeout, listener reconnect bounds, GoIP trunk and a SIP-only agent channel.

## Listener and webhook are distinct paths

`app/Console/Commands/AmiListenerCommand.php` provides a persistent PAMI event loop with in-process call-state/correlation updates. Its signature is plain `ami:listen`; reconnect timing comes from configuration. The alternate webhook-forwarding implementation is registered separately as `ami:events:listen`.

```console
php artisan ami:listen
```

This is an operator procedure, not a command run for this guide. It connects to AMI and changes CRM state on incoming events. No `--reconnect-delay` option exists on the selected command; use `AMI_LISTENER_RECONNECT_DELAY` and `AMI_LISTENER_MAX_RECONNECT_DELAY`. A preserved deployment example currently includes the unsupported option; do not copy it unchanged.

Separately, POST `/api/webhooks/ami` accepts external JSON/form events, with explicit CSRF exemption and no session auth. `ASTERISK_AMI_WEBHOOK_SECRET` is mandatory in every environment and is checked against `X-Webhook-Secret` using a constant-time comparison. Empty secret configuration returns 503; a missing or incorrect header returns 401. Missing event returns a received-but-unprocessed acknowledgment. Existing historical guides may describe listener-to-webhook forwarding; that is not the selected persistent listener's in-process architecture.

## State and correlation

Webhook identifiers accept lowercase and Asterisk-style keys: event/Event, linkedid/Linkedid/Uniqueid, channel/Channel. Hangup variants, Bridge/BridgeEnter and DialEnd are handled. `CallUuidMappingService` correlates local sessions by known identifiers/fallbacks; unmatched hangups can be recorded as unmatched events. CallStateService keeps terminal transitions idempotent and broadcasts local changes. Receiving an event does not prove it matched a call.

`AsteriskAmiService` and `AsteriskOriginateJob` remain available for direct AMI origination, using SIP-prefixed agent routing and the `from-internal` context in the existing implementation. Normal outbound controller calls currently use VICIdial `external_dial`, not that job. Keep those paths distinct during troubleshooting.

The specialized [Asterisk WebRTC setup](../asterisk/ASTERISK_WEBRTC_SETUP.md) includes server-side background. Its certificate/dialplan/module examples and server versions require separate operator validation. No AMI connection, listener loop, dialplan reload, call, or media test was performed for this documentation.
