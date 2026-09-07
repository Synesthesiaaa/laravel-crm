## Why

The notification dropdown currently exposes only supervisor messages and campaign call/form history, while attendance activity and the dashboard's daily sales performance are absent. The feed also leaks internal campaign/form codes, appears clickable without performing an action, can become stale after its first load, silently treats request failures as an empty feed, and keeps derived-history read state in cache instead of durable storage.

## What Changes

- Introduce a unified, user-scoped notification feed containing supervisor messages, the user's campaign call/form activity, the user's attendance events, and one current-day performance summary.
- Reuse the dashboard's selected campaign sales attribution and default business-day range for personal sales count and amount, team totals, and the top agent; do not create a second KPI calculation path.
- Make every notification an accessible button that marks the item read and opens a shared details modal with category-appropriate information.
- Resolve campaign, form, attendance-status, and agent display names before returning notification copy; internal codes and identifiers remain metadata and are never rendered as user-facing labels.
- Replace cache-only read tracking for derived notifications with durable per-user read state while preserving Laravel database notification compatibility.
- Globally sort and consistently limit mixed notification sources, refresh on each panel opening, add a bounded polling fallback for non-broadcast sources or unavailable Reverb, and prevent duplicate items/requests.
- Add explicit loading, empty, stale, and recoverable error states; keep the last successful feed visible when refresh fails.
- Preserve the existing supervisor send-notification workflow and confetti behavior.

## Capabilities

### New Capabilities

- `unified-notification-center`: Defines the user-scoped mixed activity feed, daily performance summary, label-only presentation, durable read state, accessible panel/modal interactions, refresh behavior, and graceful failure handling.

### Modified Capabilities

- `field-sale-attribution`: Requires the notification performance summary to consume the same campaign scope, default daily range, attribution rules, amount-visibility controls, totals, and Top Agent result as the dashboard.
- `responsive-crm-shell`: Extends the persistent shell notification control with an accessible actionable list, shared details modal, responsive behavior, and correct lifecycle cleanup across soft navigation.

## Impact

- Backend: notification aggregation/formatting, durable derived-item read tracking, display-name resolution, daily dashboard KPI reuse, and authenticated notification detail/read endpoints.
- Frontend: the shared Blade application shell, Alpine notification component, notification styling, modal focus management, polling/realtime lifecycle, and error/loading states.
- Data: one additive read-state table for virtual notification sources; existing activity, attendance, campaign/form, user, and Laravel notification records remain authoritative and are not duplicated.
- Tests: notification API/service tests, label/privacy and authorization tests, daily KPI consistency tests, frontend build checks, and Playwright coverage for mouse, keyboard, failure, realtime/fallback, and responsive flows.
- Dependencies: no new Composer or npm packages are planned.
