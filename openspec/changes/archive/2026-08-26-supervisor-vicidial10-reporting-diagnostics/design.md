## Context

The Supervisor API currently makes the documented VICIdial Non-Agent API requests for `logged_in_agents`, `agent_stats_export`, `user_group_status`, and `call_status_stats`. When every request fails, the API returns a successful CRM fallback payload and the dashboard only labels the metrics as CRM-derived. The configured `mbsales` mapping currently points to an unreachable local development stub, so a supervisor receives zeros with no actionable explanation. Connection exception text can also contain the request query string, including credentials.

## Goals / Non-Goals

**Goals:**

- Classify the selected campaign's report state as live, degraded, unavailable, or not configured.
- Return one non-sensitive, actionable message with that state.
- Keep successful metric families and existing CRM fallback behavior intact.
- Redact Non-Agent API credentials from logged connection exceptions.

**Non-Goals:**

- Change a VICIdial server's URL, credentials, permissions, or network configuration automatically.
- Route CRM campaigns by a VICIdial campaign ID or expose raw report responses to the browser.
- Add a new periodic health-check request beyond the report requests already needed for the dashboard.

## Decisions

### Derive health from the existing report snapshot

The controller will derive report health from the existing named report results, including the dependent user-group result when it is requested. This makes no additional remote request and preserves the current polling cost. A report result is considered failed only when the client reports a request or Non-Agent API error; a successful response with zero agents or calls remains a valid live response.

### Return generic remediation text rather than remote error bodies

The API will return status labels and generic next steps for connection, credentials, report permission, or unavailable data. It will not expose an endpoint URL, API username, password, HTTP exception string, or raw VICIdial response. This lets the UI explain fallbacks without expanding the existing credential boundary.

### Keep the existing source metadata separate from reporting health

`realtimeSource`, `performanceSource`, and `callSource` continue to describe individual metric provenance. A separate routing health field describes whether the whole mapped server reporting connection is live, degraded, unavailable, or not configured. This prevents a partial failure from incorrectly changing successful metric families.

### Redact URL query secrets before telemetry is written

The Non-Agent API service will redact `user` and `pass` query parameter values in caught connection-exception text before it reaches the telephony logger. This is narrowly scoped to failure logging and does not change outbound requests or stored server credentials.

## Risks / Trade-offs

- [A valid empty VICIdial response could be mistaken for an outage] -> Only explicit failed operation results change health; a successful empty report remains live.
- [A supervisor still needs a real server mapping] -> The dashboard gives an actionable message, but server URL, network, and API permissions remain administrator-managed configuration.
- [A partial failure could be overlooked] -> The dashboard presents a text status next to the selected server and retains the per-metric source labels.
