## 1. Persisted activity coverage and redaction

- [x] 1.1 Add a migration that indexes `activity_log.created_at` for bounded chronological history queries.
- [x] 1.2 Add `ActivityLogSanitizer` with recursive key-based redaction and focused unit tests covering password, token, API, SIP, database, nested, and non-sensitive values.
- [x] 1.3 Add an activity observer for the Spatie activity model that sanitizes properties before persistence and dispatches a normalized activity broadcast after creation; register it in `AppServiceProvider`.
- [x] 1.4 Enable allowlisted Spatie activity logging for `DataRetentionPolicy`, `SystemSetting`, `FormField`, `AgentScreenField`, and `AttendanceStatusType`, excluding credential-like values at the model level where applicable.
- [x] 1.5 Add explicit activity entries for successful login/logout, retention-policy execution outcomes, and telephony feature updates, preserving the authenticated causer and redacting request metadata.
- [x] 1.6 Add feature tests proving configuration model changes, explicit actions, actor relationships, before/after properties, and redaction are persisted in `activity_log`.

## 2. Realtime event and protected history API

- [x] 2.1 Add `ActivityLogEntry` formatting/query service that applies validated filters, bounded limits, chronological ordering, subject/causer labels, severity, and the normalized entry shape.
- [x] 2.2 Add `ActivityLogCreated` as an immediate private broadcast event that dispatches after commit on `activity-log` and contains only the normalized redacted payload.
- [x] 2.3 Authorize the `activity-log` private channel for Super Admins and add route coverage proving guests redirect, non-Super Admins receive 403, and Super Admins can access history.
- [x] 2.4 Add `ActivityLogController` page and JSON history endpoints with filters for actor, event, resource, date range, text search, and `since_id`; add feature tests for filtering, limits, and incremental results.

## 3. Terminal-style Admin viewer

- [x] 3.1 Add the Super Admin Activity Log navigation item and page view with dark monospace terminal output, timestamped lines, action/status colors, filters, follow mode, pause control, clear-buffer behavior, expandable redacted changes, and connection status.
- [x] 3.2 Extend `resources/js/echo.js` with a deduplicated `subscribeActivityLog` helper and teardown behavior using the existing Echo bootstrap.
- [x] 3.3 Implement the Activity Log Alpine component with initial history loading, realtime append, bounded polling fallback using `since_id`, duplicate suppression, follow/pause behavior, filter reloads, and cleanup on navigation.
- [x] 3.4 Add view-rendering and browser JavaScript interaction coverage for terminal controls, normalized entry rendering, redaction visibility, and the no-delete clear behavior.
- [x] 3.5 Run Pint, the focused PHPUnit files, `npm run build`, and Playwright verification of the Super Admin page, filters, terminal controls, responsive layout, and browser console errors.

## 4. OpenSpec completion

- [x] 4.1 Sync the completed realtime-activity-log capability into the main OpenSpec specs.
- [x] 4.2 Confirm all implementation tasks and validation checks are complete, then archive the OpenSpec change.
