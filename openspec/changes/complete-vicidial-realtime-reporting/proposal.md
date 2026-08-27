## Why

The CRM can reach the configured VICIdial host, but the reporting pipeline currently treats several non-data responses as successful HTTP responses and reduces the resulting failure to a generic dashboard error. The Supervisor already has a normalized operational snapshot, but the remaining transport diagnostics, freshness semantics, and Live/Today reporting modes are needed to make the integration diagnosable and operationally useful.

## What Changes

- Classify VICIdial transport and payload failures as authentication, permission, network, SSL, server, empty, or parse failures while retaining safe structured diagnostics.
- Centralize per-server endpoint resolution for Non-Agent API and report endpoints without allowing a global URL to override an explicit campaign-server URL.
- Emit server-, campaign-, endpoint-, latency-, status-, content-type-, payload-size-, parser-, and row-count diagnostics without logging credentials or customer data.
- Preserve campaign/server isolation and partial-failure behavior while adding stale/offline semantics and last-successful-refresh metadata.
- Add one normalized Live and Today reporting API contract alongside the existing Historical mode, using the existing operational snapshot rather than duplicating per-card VICIdial requests.
- Add rolling short-window metrics only from available CRM/VICIdial events, display unavailable values as unavailable, and prevent overlapping browser polls.
- Improve Supervisor and Reports error, health, refresh, and source-status presentation while retaining the existing design system and access controls.
- Add regression, integration-style fixture, and UI-flow coverage for authentication, network, parsing, isolation, stale data, partial failures, and Live/Today modes.

## Capabilities

### New Capabilities

- `vicidial-report-diagnostics`: Safe endpoint resolution, response classification, structured source health, and redacted transport diagnostics.

### Modified Capabilities

- `telephony-operations-and-reporting`: Add freshness-aware real-time snapshots, rolling Live/Today report modes, and source-independent degradation behavior.

## Impact

- VICIdial transport, endpoint resolution, operation results, logging, and configuration.
- Supervisor and Reports controllers, services, API contracts, routes, Blade/Alpine dashboards, and polling behavior.
- PHPUnit feature/unit tests, sanitized VICIdial response fixtures, and browser validation.
- No database schema change or frontend dependency change is required.
