## Why

The Supervisor dashboard currently combines VICIdial daily call totals with agent and timing values that may still come from incomplete CRM call-session ingestion. Supervisors need the selected CRM campaign to read a coherent, current snapshot from its mapped VICIdial server while retaining safe CRM fallbacks when that server or a reporting function is unavailable.

## What Changes

- Collect the supported VICIdial equivalents of real-time campaign and agent statistics from the server mapped to the selected CRM campaign.
- Aggregate every VICIdial campaign on that server without treating a VICIdial campaign ID as the CRM routing key.
- Use `logged_in_agents` and `user_group_status` for current agent/queue state, `agent_stats_export` for today's agent timing and call metrics, and `call_status_stats` for today's total, answered, and hourly call counts.
- Run independent VICIdial report requests concurrently with bounded timeouts so supervisor polling remains responsive.
- Preserve campaign-scoped CRM metrics as per-metric fallbacks and expose non-sensitive source/freshness metadata in the Supervisor API and dashboard.
- Keep the Supervisor agent grid read-only.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `campaign-scoped-vicidial-supervision`: Require real-time operational and agent-performance metrics to come from the CRM campaign's mapped VICIdial server when supported, with explicit per-metric fallback and freshness reporting.

## Impact

- Affects the VICIdial Non-Agent HTTP client, reporting service, Supervisor API aggregation, and Supervisor Blade/Alpine dashboard.
- Adds no package dependency and no database schema change.
- Requires report-capable VICIdial API credentials for the supported reporting functions; unavailable or unauthorized functions degrade independently to CRM-derived values.
- Adds regression coverage for server isolation, response parsing, partial VICIdial failures, and dashboard source indicators.
