# Laravel Reverb integration

Reverb transports Laravel application events to Echo/Pusher-compatible browser subscriptions. It does not carry SIP call audio and is not the Asterisk WSS endpoint.

Server settings are in [config/reverb.php](../../config/reverb.php) and [config/broadcasting.php](../../config/broadcasting.php). Browser settings are build-time `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`, with legacy Pusher-key lookup in [echo.js](../../resources/js/echo.js). The implementation effectively selects the Reverb path when a key exists; do not infer full Pusher fallback support from the imports/comments alone. Server app secrets must stay server-side.

```console
php artisan reverb:start
```

This operator command starts a long-running listener; it was not executed for this guide. Bind/listener and public proxy host/port can differ. Align proxy websocket upgrades, public TLS, application origins and compiled Vite values with deployment requirements. Changing a server environment variable does not rewrite an already built frontend bundle.

Private subscriptions authorize through `/broadcasting/auth` using the browser's session and CSRF setup. The client derives the endpoint from the CRM base URL or an `index.php` prefix. User/agent channels require matching IDs, supervisor requires supervisor roles, activity-log requires Super Admin; dashboard checks campaign existence. [Realtime](../developer/realtime.md) documents the channel/event map.

Current events use deferred after-commit broadcasting with rescue behavior; a worker consuming durable telephony jobs is a separate subsystem. Diagnose missing events in layers: emitted local change, broadcast transport configuration, Reverb connectivity, private authorization, subscribed event name, listener lifecycle, then consumer rendering. Missing frontend key disables Echo. Agent polling remains part of synchronization even when websockets connect.

Public GET `/api/websocket/health` reports enabled/host/port/scheme from backend configuration. It is not an end-to-end probe and cannot certify browser reachability or private authorization. No live Reverb connection/subscription or browser validation was performed in this documentation review. [Historical implementation notes](../telephony/REVERB_TELEPHONY_IMPLEMENTATION.md) remain useful context but are not a substitute for current event classes/configuration.
