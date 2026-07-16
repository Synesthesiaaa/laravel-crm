## 1. Broadcast contract and server dispatch

- [x] 1.1 Verify Laravel broadcasting conventions for immediate private events.
- [x] 1.2 Add `DashboardDataUpdated` with campaign-scoped channel, event name,
      and minimal payload.
- [x] 1.3 Authorize `dashboard.{campaign}` for authenticated campaign access.
- [x] 1.4 Dispatch the event after successful form submission commits.
- [x] 1.5 Dispatch the event after successful Data Master edits.

## 2. Dashboard client refresh

- [x] 2.1 Add Echo subscription and connection-state helpers with idempotent
      teardown.
- [x] 2.2 Expose a soft-navigation refresh that preserves the current URL.
- [x] 2.3 Subscribe the dashboard, debounce events, and retain chart/telephony
      lifecycle behavior.
- [x] 2.4 Add a 30-second fallback only while WebSocket is unavailable.

## 3. Verification

- [x] 3.1 Add event contract and channel authorization tests.
- [x] 3.2 Add form submission and Data Master dispatch regression tests.
- [x] 3.3 Run Pint, focused PHPUnit tests, and the Vite production build.
- [x] 3.4 Verify dashboard refresh behavior and console output in a browser.
