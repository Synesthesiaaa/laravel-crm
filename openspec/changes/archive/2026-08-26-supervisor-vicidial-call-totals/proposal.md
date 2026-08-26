## Why

The Supervisor dashboard currently reports zero calls when CRM `call_sessions` have not been populated, even though the mapped VICIdial server has processed calls. Supervisors need the mapped server's call totals as the authoritative operational baseline, with CRM data still available for local lifecycle metrics and fallback behavior.

## What Changes

- Read today's call-status totals and answered totals from the VICIdial Non-Agent API for the server mapped to the selected CRM campaign.
- Use VICIdial totals and hourly breakdowns for the Supervisor wallboard when the remote report succeeds; fall back to campaign-scoped CRM call sessions when it is unavailable.
- Preserve CRM-derived wait and handle metrics, and enrich logged-in agent cards with VICIdial `calls_today` when supplied.
- Keep all remote reporting requests inside the selected CRM campaign's mapped-server boundary and avoid exposing credentials or URLs.
- Add regression coverage for remote totals, fallback behavior, and campaign isolation.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `campaign-scoped-vicidial-supervision`: Supervisor totals and hourly call metrics use the mapped VICIdial server with a CRM fallback.

## Impact

- Affects `SupervisorAgentsController`, `ReportingService`, the Supervisor Blade/Alpine dashboard, and related feature/unit tests.
- Uses the existing `call_status_stats` and `logged_in_agents` Non-Agent API functions; no dependency or schema changes are required.
- Requires the mapped VICIdial API account to have permission to view call-status reports. If not, CRM metrics remain available.
