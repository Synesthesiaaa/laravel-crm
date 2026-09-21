## Why

The Activity Log currently contains the underlying request metadata and before/after properties, but the terminal's expanded view is difficult to interpret because most detail is presented as undifferentiated JSON. Super Admins need a readable audit record that makes it immediately clear who acted, what resource changed, what request occurred, and which field values changed.

## What Changes

- Add normalized actor metadata including user ID, username, display name, and role.
- Add a computed sanitized field diff containing each changed field's previous and new values.
- Render structured event, actor, resource, request, and change sections in expanded terminal entries.
- Retain the complete sanitized JSON payload for deeper inspection.
- Preserve existing realtime delivery, polling reconciliation, filters, and access control.

## Capabilities

### New Capabilities

- `detailed-activity-log`: Readable, structured expansion of activity records with actor context, request telemetry, and before/after change details.

### Modified Capabilities

- `system-wide-user-activity-audit`: Extend normalized request and activity history entries with richer display metadata.

## Impact

- `ActivityLogEntry` response normalization and the Super Admin Activity Log Blade view.
- Feature and JavaScript tests for normalized payloads and expanded terminal details.
- No database schema or dependency changes.
- Existing redaction, realtime broadcasting, polling, retention, and Super Admin visibility behavior remain in place.
