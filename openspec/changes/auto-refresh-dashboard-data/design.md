## Context

The dashboard is rendered from server-side Laravel data and then enhanced with
client-side charts. A successful form submission or Data Master edit changes
those values, but the existing page has no way to learn about the change. The
user currently has to reload the tab, which also interrupts the persistent
telephony shell.

The application already has Laravel broadcasting, Reverb, Echo, private channel
authorization, and soft navigation. The change can therefore add a narrow
dashboard-data event without introducing another transport or a second source
of truth.

## Goals / Non-Goals

### Goals

- Notify dashboard viewers after a successful form submission or Data Master
  edit.
- Scope notifications to the affected campaign and authorize them server-side.
- Refresh the existing server-rendered dashboard through soft navigation so
  filters and the telephony shell remain intact.
- Coalesce bursts of updates and provide a low-frequency polling fallback when
  the WebSocket is unavailable.
- Keep the signal an invalidation hint; the dashboard remains responsible for
  fetching authoritative values from its existing controller.

### Non-Goals

- No live updates for telephony, call state, or disposition events.
- No new dashboard API or client-side reimplementation of KPI calculations.
- No automatic refresh for dashboard viewers outside the affected campaign.
- No change to the existing Data Master delete behavior.

## Decisions

### Broadcast one campaign-scoped invalidation event

Add `DashboardDataUpdated` as a `ShouldBroadcastNow` event. It carries the
campaign code, form type, record identifier, action (`submitted` or `updated`),
and event timestamp. It broadcasts on private `dashboard.{campaign}` as
`dashboard.data.updated`.

`ShouldBroadcastNow` makes the notification immediate and avoids requiring a
queue worker for this small invalidation signal. The event contains no form
values, so the dashboard still obtains data through its existing authorized
HTTP request.

### Dispatch only after successful writes

The form submission service dispatches the event after its transaction commits.
The Data Master update controller dispatches it only after the service reports
a successful update. Deletes and failed validation do not emit an event.

### Refresh with soft navigation

The dashboard subscribes to the campaign channel using the existing Echo
helper. Incoming events are debounced and invoke a new soft-navigation refresh
of the current URL (`push: false`). This preserves query-string filters and the
telephony DOM outside `#main-layout`.

The Echo helper owns teardown and exposes connection state. The dashboard uses
a 30-second fallback interval only while Echo is unavailable or disconnected;
the interval is cleared during soft-navigation swaps to prevent duplicate
timers and subscriptions.

### Authorize the channel by authenticated campaign access

The private channel callback requires an authenticated user and an active
campaign matching the requested code. Existing role and campaign access rules
remain the source of authorization; the event itself does not grant access.

## Risks / Trade-offs

- A disconnected WebSocket can leave a dashboard stale for up to the fallback
  interval. A shorter interval increases requests, so 30 seconds is the
  compromise for an operational dashboard.
- A burst of writes may cause one refresh after the debounce window rather than
  one refresh per record. This is intentional because the refreshed page reads
  the complete authoritative dataset.
- `ShouldBroadcastNow` performs the broadcast in the write request. The payload
  is deliberately small to keep that overhead predictable.
- A soft refresh still depends on the dashboard HTTP request succeeding; the
  existing page remains usable if a refresh fails and the next signal retries.

## Migration Plan

1. Add the event, channel authorization, and dispatch points.
2. Add Echo/soft-navigation helpers and dashboard subscription/fallback logic.
3. Add focused event, dispatch, authorization, and rendered-flow tests.
4. Run Pint, focused PHPUnit tests, the Vite build, and browser verification.

No database migration is required.

## Open Questions

None for the approved scope.
