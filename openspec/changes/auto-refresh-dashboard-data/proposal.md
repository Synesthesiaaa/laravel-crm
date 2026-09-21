## Why

The dashboard is rendered once and currently stays stale after a form submission or a Data Master edit. Users must reload the tab to see updated sales and activity metrics, which makes the dashboard unreliable for live operational monitoring.

## What Changes

- Broadcast a campaign-scoped data-update signal after successful form submissions and Data Master edits.
- Subscribe the dashboard to those updates and refresh its server-rendered metrics without a user-initiated reload.
- Preserve the current dashboard filters and the persistent telephony shell during refreshes.
- Use a low-frequency fallback refresh when the WebSocket connection is unavailable.
- Add authorization, event, client, and rendered-flow regression coverage.

## Capabilities

### New Capabilities

- `dashboard-live-updates`: Keep dashboard data synchronized after form submissions and Data Master edits through WebSocket updates with a fallback refresh path.

### Modified Capabilities

<!-- No existing capability requirements are changed; this adds live delivery for existing dashboard data. -->

## Impact

- Laravel events and private broadcast channel authorization.
- Form submission and Data Master update flows.
- Dashboard Blade script, soft-navigation refresh support, and Laravel Echo helpers.
- PHPUnit feature/unit tests and browser verification against the local dashboard.
