## Why

Supervisor and Telephony Reports currently consume overlapping VICIdial data but present it with mixed real-time and historical semantics. This makes it difficult to identify operational exceptions quickly and leaves historical calculations, duplicate-agent handling, and report fallback behavior scattered across controllers and Blade JavaScript.

## What Changes

- Introduce explicit Supervisor operational contracts for normalized agent state, queue snapshots, queue health, operational exceptions, and freshness/source metadata.
- Move Supervisor-specific VICIdial parsing and CRM fallback aggregation out of the API controller into a dedicated operational service while retaining shared low-level VICIdial clients.
- Keep Supervisor primary metrics focused on current agent availability, active calls, waiting calls, oldest wait, and average wait; retain historical totals only as secondary compatibility data where needed.
- Replace Supervisor's historical Performance Metrics tab with Agent Status, Queue Monitor, and Live Wallboard operational views.
- Add a backend historical reporting aggregation contract for date-range summaries, previous-period comparison, campaign performance, disposition Pareto data, call funnel data when mappings are available, and deduplicated agent performance.
- Add report filters for CRM/VICIdial campaign scope, date range, disposition scope, and comparison period without changing the existing higher-role authorization boundary.
- Keep debug/raw VICIdial output collapsed and restricted to authorized technical users.
- Add focused unit and feature coverage for normalized state mapping, queue health, comparison calculations, report aggregation, duplicate sessions, partial upstream failures, campaign isolation, and broadcast compatibility.

## Capabilities

### New Capabilities
- `telephony-operations-and-reporting`: Separates real-time Supervisor operations from historical Telephony Reports through distinct service contracts, aggregations, UI semantics, and failure domains.

### Modified Capabilities
- None

## Impact

- Supervisor API/controller, reporting API/controller, and telephony services under `app/Services/Telephony`.
- New telephony value objects/enums and focused request/response DTO-style arrays following existing application conventions.
- `resources/views/admin/supervisor.blade.php` and `resources/views/reports/index.blade.php`, including charts, KPI labels, loading/empty/error states, responsive tables, and accessibility text.
- Routes remain backward compatible; new dashboard endpoints may be added only where they prevent the two concerns from sharing a controller responsibility.
- No schema or dependency changes are expected.
