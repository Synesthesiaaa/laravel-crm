## Context

The Supervisor endpoint currently calculates `todayTotal` only from CRM `call_sessions`. In installations where VICIdial is handling calls but CRM lifecycle ingestion is delayed or disabled, that query is empty and the wallboard incorrectly shows zero. The existing campaign-scoped Non-Agent API client already knows how to resolve the selected CRM campaign to its VICIdial server and can call `call_status_stats`.

## Goals / Non-Goals

**Goals:**

- Use the mapped VICIdial server's daily call-status report as the authoritative total and answered count when it responds successfully.
- Aggregate all VICIdial campaigns on that mapped server because the CRM campaign-to-server mapping is the routing boundary, not the VICIdial campaign code.
- Keep CRM call sessions as a deterministic fallback for totals, hourly data, per-agent handle time, and wait time when the remote report is unavailable or unauthorized.
- Preserve campaign/server isolation, bounded request timeouts, and non-sensitive API responses.

**Non-Goals:**

- Adding a new database table or storing remote snapshots.
- Replacing CRM lifecycle metrics with estimates from an incomplete VICIdial response.
- Inferring a VICIdial campaign from an agent's latest session.

## Decisions

### Call-status report is remote-first, CRM fallback

Call `ReportingService::callStatusStats` with `campaigns=---ALL---` and the current date against the selected campaign's mapped server. A successful, parseable response supplies total calls, answered calls, and hourly counts. If the call fails, returns an error, or has no parseable rows, retain the CRM-derived values. This avoids displaying zero solely because CRM ingestion is incomplete while still allowing the dashboard to work when VICidial credentials are not configured.

Alternative considered: sum remote and CRM totals. This was rejected because the same call can exist in both systems and would be double-counted.

### Parse the documented pipe-delimited shape defensively

Each `call_status_stats` row is `campaign/ingroup|total|human answered|hourly breakdown|status breakdown`. Only numeric total/answered fields and valid `HH-count` hourly entries are accepted. Invalid rows are ignored; a report with no valid rows triggers the CRM fallback.

### Enrich cards without making per-agent report calls

The existing `logged_in_agents` response's `calls_today` column is used when present for remote agent cards. Per-agent CRM handle/wait calculations remain local and are not replaced by N+1 remote requests.

## Risks / Trade-offs

- [The API account lacks report permission] → Treat the report as unavailable and use CRM metrics; log no credentials or raw URL in the response.
- [All campaigns on one mapped server are included] → Keep server assignment as the explicit CRM boundary and document the behavior; separate servers remain separate totals.
- [Remote requests add latency] → Use the same short connect/read/retry limits as the logged-in-agent feed and keep refresh locking in the browser.
- [VICIdial and CRM dates use different time zones] → Send the application date consistently and expose the response timestamp so operators can compare server configuration.
