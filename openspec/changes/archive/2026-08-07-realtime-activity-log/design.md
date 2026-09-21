## Context

The application is a Laravel 12 CRM with Spatie Laravel Activitylog already installed and a migrated `activity_log` table. Some configuration models (`User`, `Campaign`, `Form`, `DispositionCode`, and `VicidialServer`) already use `LogsActivity`, while other configuration models and explicit actions do not. Reverb/Echo is already used for telephony and dashboard updates, but the broadcast connection is optional and the default queue may be asynchronous.

The feature is security-sensitive: only Super Admins may view it, and activity properties must never expose credentials or other secret values. It must remain useful when Reverb is disabled or temporarily unavailable.

## Goals / Non-Goals

**Goals:**

- Persist meaningful state-changing activity using the existing activity-log storage.
- Cover configuration-related model changes and explicit administrative/security actions.
- Sanitize sensitive properties before persistence and before broadcast.
- Expose a filtered history endpoint and a Super Admin-only terminal-style viewer.
- Broadcast new entries after persistence and transaction commit when realtime is enabled.
- Provide polling fallback so the viewer remains usable without a WebSocket connection.

**Non-Goals:**

- Do not log every page view or read-only request.
- Do not replace Laravel application/error logs, telephony logs, or the existing activity-log cleanup schedule.
- Do not add a new third-party audit or WebSocket dependency.
- Do not allow the Activity Log page to delete or mutate audit history.

## Decisions

### Use the existing Spatie activity log as the source of truth

Configuration models will use `LogsActivity` with explicit attribute allowlists. Explicit actions that do not naturally create a useful model event (login/logout, retention execution, and telephony feature updates) will call the Spatie activity helper with a clear description and structured properties. This preserves the existing migration, cleanup command, subject/causer relationships, and before/after format.

An activity observer will sanitize the `properties` payload during the activity model's `creating` event, before it is written. The observer will also dispatch the realtime event from the activity model's `created` event. This avoids duplicate logs and ensures that all model-generated activity follows the same redaction path.

### Broadcast a compact, private event immediately after commit

The broadcast event will implement `ShouldBroadcastNow` so the feature does not depend on a queue worker for the live terminal stream, and `ShouldDispatchAfterCommit` so entries created inside a transaction are not broadcast before their database write is committed. It will use the private `activity-log` channel, authorized only for a Super Admin role. The event payload will contain the same sanitized fields returned by the history endpoint and will not serialize the full Eloquent subject or causer models.

### Reuse the existing Echo bootstrap and add one subscription helper

`resources/js/echo.js` will expose a deduplicated `subscribeActivityLog` helper using the existing Echo instance and connection-state monitor. The page-specific Alpine component will initialize Echo only when configured, append incoming entries, and poll the history endpoint for entries newer than the last received ID whenever the connection is unavailable. Polling will stop when the page is left or the component is destroyed.

### Serve one normalized entry shape

The controller/service layer will normalize every entry into a stable JSON shape: `id`, ISO timestamp, actor label, action/event, resource label/type/id, description, status/severity, and sanitized `changes`. The initial page load will request a bounded recent window; the browser will reverse it for terminal order and use `since_id` for incremental polling. Filters will be validated server-side and applied to the query.

### Keep the terminal UI local to the new page

The page will be a Blade view with Alpine state and existing CSS variables/components. It will include a dark monospace output panel, colored action markers, a follow toggle, pause control, connection badge, filters, and expandable JSON-like before/after blocks. “Clear” will only clear the browser’s visible buffer and then allow the next refresh to restore the selected history; it will never delete database records.

## Risks / Trade-offs

- [High activity volume] → Bound initial and incremental queries, add an index for chronological lookup, and retain the existing 90-day cleanup schedule.
- [Secrets hidden only in known model allowlists] → Sanitize recursively by key pattern as a second defense, and add tests for password/token/API/SIP/database credential names.
- [Broadcasting disabled or Reverb down] → Show connection state and poll with a bounded interval using the same authorized endpoint.
- [Immediate broadcasting and database consistency] → Implement both `ShouldBroadcastNow` and `ShouldDispatchAfterCommit`; history remains the fallback source if a broadcast is missed.
- [Existing tests depend on model observers] → Register the activity observer in the existing application provider without changing the campaign cache observer behavior, and run focused feature/unit tests plus Pint/build checks.

## Migration Plan

1. Add the chronological index to the existing activity-log table and run the migration.
2. Deploy model logging, sanitization, controller/routes, channel authorization, broadcast event, Echo helper, navigation, and view changes.
3. Run the focused PHPUnit tests and build assets.
4. Start/restart the application queue/Reverb processes as already required by the deployment environment; the viewer remains functional through polling when Reverb is not configured.

Rollback is safe at the application layer by removing the Activity Log route/navigation and disabling the observer registration. The added index can be rolled back independently; activity rows already written remain compatible with the existing package.

## Open Questions

None. Scope, access, redaction, and terminal interaction have been approved.
