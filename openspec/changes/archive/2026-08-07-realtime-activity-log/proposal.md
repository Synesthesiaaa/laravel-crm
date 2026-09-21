## Why

Administrators currently have no centralized way to inspect meaningful system changes as they happen. Although the application already persists some model activity and has Reverb available, configuration changes and important administrative actions are not consistently visible, and there is no secure realtime activity viewer.

## What Changes

- Add a Super Admin-only Admin → Activity Log page styled as a terminal stream.
- Record meaningful state-changing activity across the application, with particular coverage for Configuration actions and related models.
- Preserve actor, timestamp, action, resource, and before/after properties while redacting secrets and credentials.
- Broadcast newly persisted activity entries over an authorized private realtime channel.
- Add a history API/query with filters for actor, action, resource, date range, and text search.
- Add terminal controls for follow/auto-scroll, pause, expandable details, and realtime connection status.
- Provide a polling fallback when realtime broadcasting is unavailable.
- Add automated coverage for authorization, activity persistence, redaction, broadcasting, and the activity-log page.

## Capabilities

### New Capabilities

- `realtime-activity-log`: Persisted, filtered, redacted, and realtime administrative activity logging with a Super Admin terminal-style viewer.

### Modified Capabilities

<!-- No existing capability requirements are changed. -->

## Impact

- Laravel routes, controllers, models, events, channel authorization, and migrations.
- Existing Spatie activity-log integration and its scheduled cleanup behavior.
- Existing Reverb/Echo frontend infrastructure and Admin navigation.
- New feature and browser-rendering tests; no new external dependency is required.
