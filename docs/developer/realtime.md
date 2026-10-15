# Realtime behavior

The server uses Laravel broadcasting, with Reverb configuration in [config/broadcasting.php](../../config/broadcasting.php) and [config/reverb.php](../../config/reverb.php). The browser initializes Echo in [resources/js/echo.js](../../resources/js/echo.js) when a build-time broadcast key exists. Missing key disables Echo; polling remains an important operational fallback.

| Channel | Authorization in `routes/channels.php` |
|---|---|
| Private `App.Models.User.{id}` | Authenticated user ID equals channel ID |
| Private `agent.{agent_id}` | Same user-ID check |
| Private `telephony.supervisor` | Super Admin, Admin or Team Leader |
| Private `dashboard.{campaign}` | Campaign resolves through CampaignService; not per-user campaign membership |
| Presence `agents.online` | Authenticated participant; broadcasts ID, name and role |
| Private `activity-log` | Super Admin |

The client listens for `.call.state.changed`, `.vicidial.agent.event`, `.inbound.call.received`, `.disposition.saved`, `.telephony.event.logged`, `.dashboard.data.updated`, `.dashboard.layout.updated`, and `.activity.log.created`. Preserve event names, privacy, and payload ownership when extending. `CallStateChanged` includes call/session/user/agent IDs, from/to/current status, campaign, number, Asterisk linked ID, durations and timestamps; see the event's `broadcastWith()` for exact keys.

Broadcast auth is `/broadcasting/auth`, resolved with the CRM base URL or `index.php` prefix for subdirectory deployments. This requires the browser session and CSRF setup. A connected websocket does not prove private channel authorization or successful event delivery. The public `/api/websocket/health` endpoint reports selected backend/configuration only, not an active end-to-end connectivity test.

`echo.js` provides teardown functions and subscription deduplication; page lifecycle code must stop listeners when replacing views to avoid repeated UI updates. Agent active-lead polling is an always-on fallback to VICIdial push. Other consumers use polling/invalidation refreshes rather than receiving an authoritative database snapshot in every broadcast.

Broadcasts currently use the deferred connection and after-commit rescue contracts. Durable queue failures, Reverb connectivity, session authorization, and browser SIP registration are separate diagnoses. See [Reverb integration](../integrations/laravel-reverb.md) and [telephony flow](../diagrams/telephony.md).
