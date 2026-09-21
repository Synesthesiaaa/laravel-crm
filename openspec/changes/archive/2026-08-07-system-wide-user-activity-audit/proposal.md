## Why

The Activity Log currently receives model changes and selected domain events, but it does not provide a complete timeline of what authenticated users do across the application. Super Admins need to see every user's page visits, polling requests, API calls, and state-changing requests in one chronological stream.

## What Changes

- Add centralized request activity auditing to Laravel's `web` and `api` middleware groups.
- Record every authenticated request, including read-only and polling requests, with actor, method, path, route, status, IP, user agent, and sanitized query metadata.
- Preserve existing model and domain-event activity records and realtime delivery.
- Normalize request records in the Activity Log and expose all current users in the actor filter.
- Add tests for authenticated request capture, actor attribution, response statuses, redaction, and realtime/history visibility.

## Capabilities

### New Capabilities

- `system-wide-user-activity-audit`: Centralized, redacted auditing of every authenticated application request from every user.

### Modified Capabilities

- None.

## Impact

- Laravel middleware registration and a new request activity recorder.
- Existing `activity_log` persistence and normalization service.
- Super Admin Activity Log filters and terminal entry rendering.
- Request volume in `activity_log`; the existing activity retention policy remains responsible for cleanup.
- No new dependency or external service is required.
