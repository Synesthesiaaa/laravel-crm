## Context

The Supervisor endpoint already routes by CRM campaign to one `VicidialServer`, requests `logged_in_agents` and `call_status_stats`, and falls back to CRM `call_sessions`. Current operational counts are partly inferred from individual agent rows, while average wait/handle values remain CRM-derived even when VICIdial is the authoritative call system.

The referenced ViciStack article describes generic `campaign_stats` and `agent_stats` calls. The standard Non-Agent API implemented by the VICIdial integrations used in this project exposes the corresponding information through `call_status_stats`, `logged_in_agents`, `user_group_status`, and `agent_stats_export`. The design must work with those supported functions, tolerate version-dependent columns, and never require the CRM campaign code to match a VICIdial campaign ID.

## Goals / Non-Goals

**Goals:**

- Produce one campaign-scoped Supervisor snapshot from the selected CRM campaign's mapped server.
- Use current VICIdial agent/group state for online, available, on-call, paused, and queue counts.
- Use today's VICIdial agent export for per-agent calls and timing values and the daily call-status report for call totals and hourly volume.
- Keep polling responsive and allow each remote function to fail independently.
- Tell supervisors which metric families are live VICIdial data and which are CRM fallbacks without exposing endpoints or credentials.

**Non-Goals:**

- Adding a VICIdial campaign-ID field to CRM campaigns.
- Querying the VICIdial database directly.
- Re-enabling monitor, whisper, pause, or logout controls.
- Replacing the existing polling/Echo transport or adding a frontend dependency.

## Decisions

### Use supported Non-Agent API equivalents

The snapshot will request `logged_in_agents`, `agent_stats_export`, and `call_status_stats` across all VICIdial campaigns on the mapped server. After parsing agent user groups from the first two results, it will request `user_group_status` for those groups. This preserves the CRM campaign-to-server boundary while obtaining the operational values described by the external guide.

Directly calling undocumented `campaign_stats`/`agent_stats` was rejected because standard VICIdial installations expose different function names and the generic campaign call requires a VICidial campaign ID. Direct database queries were rejected because they expand network, credential, schema-version, and query-load risk.

### Run independent reports concurrently

`VicidialNonAgentApiService` will support named request batches resolved against one campaign server and one credential set. The initial three reports will run through Laravel's HTTP pool with the same bounded connect/response timeouts as the current Supervisor calls. `user_group_status` remains a single dependent request because its group filter comes from those report rows.

Sequential requests were rejected because an unavailable server could make a five-second dashboard poll wait for several timeouts back-to-back.

### Parse by normalized headers and degrade per metric family

Header-bearing agent/group exports will be converted to associative rows using normalized field names. Numeric and duration fields will be validated; malformed rows will be ignored. A failed agent export will not invalidate real-time agent state or daily call totals, and vice versa. CRM lifecycle values remain the fallback for only the unavailable metric family.

The response will retain the legacy `callSource` key and add `realtimeSource` and `performanceSource`, plus the existing server-side `updatedAt`. This is backwards compatible while making provenance visible.

### Preserve last-known UI values on refresh failure

The frontend will continue replacing data only after a successful API response and will not overlap polls. A compact status line will display operational, performance, and call-total sources with the rendered update time. Existing dashboard components, color tokens, responsive layout, and text-based source labels will be reused.

## Risks / Trade-offs

- [VICIdial builds expose different columns or omit a function] -> Normalize headers, validate every field, and independently fall back to CRM metrics.
- [API user lacks report permissions] -> Keep the endpoint successful, identify fallback sources, and never log or return credentials.
- [One user group includes activity outside the intended logical team] -> The configured VICIdial server remains the explicit CRM routing boundary; no cross-server data is aggregated.
- [Agent exports can be expensive on large installations] -> Limit the date range to today, use bounded timeouts/no retries for polling, and run independent calls concurrently.
- [Calls can appear in both CRM and VICIdial] -> Select one source per metric family and never add remote and CRM aggregates together.

## Migration Plan

1. Deploy the backwards-compatible API/client changes and UI source labels.
2. Confirm each mapped server's API user has report access for the four functions.
3. Existing campaigns without credentials continue using CRM fallbacks with no data migration.
4. Roll back the code change if necessary; no schema or stored data requires reversal.

## Open Questions

None. VICIdial user groups discovered from current/active-today agent rows define the server-scoped operational group set for this implementation.
