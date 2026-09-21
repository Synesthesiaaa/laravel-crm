## Why

Supervisor actions currently lose the monitored agent's campaign context, while VICIdial server resolution can silently fall back to a server assigned to another campaign. This can send monitoring and session requests to the wrong VICIdial instance when the CRM has multiple campaigns.

## What Changes

- Make VICIdial server selection strict to the requested campaign while retaining default and priority selection within that campaign.
- Return an actionable configuration error when a campaign has no active VICIdial server instead of using another campaign's server.
- Route Supervisor monitor, whisper, pause, and logout actions with the CRM campaign represented by the selected dashboard context.
- Let supervisors select a CRM campaign on the Supervisor page and, when available, supplement local cards from that campaign's mapped-server logged-in-agent feed across all VICIdial campaigns.
- Scope the Supervisor agent/session data to the active CRM campaign and expose the resolved campaign and server identity in the interface.
- Derive Supervisor wallboard KPIs from campaign-scoped call lifecycle and current agent state data, with bounded near-real-time refreshes.
- Keep Supervisor agent cards read-only by removing monitor, whisper, pause, and logout controls from the dashboard.
- Resolve Non-Agent API requests from the selected `vicidial_servers` row rather than allowing one global endpoint override to replace every campaign-specific endpoint.
- Add regression coverage for two campaigns mapped to different VICIdial servers and for campaigns with no mapping.
- **BREAKING**: Off-CRM or unmapped campaign requests will no longer use an unrelated active/default VICIdial server.

## Capabilities

### New Capabilities

- `campaign-scoped-vicidial-supervision`: Campaign-scoped Supervisor data, visible server context, and correctly routed Supervisor telephony actions.

### Modified Capabilities

- `platform-stabilization`: Replace cross-campaign VICIdial server fallback with strict, actionable campaign mapping.
- `campaign-linked-vicidial-widget`: Require campaign-specific endpoint resolution for Agent and Non-Agent operations after campaign changes.

## Impact

- Affects `VicidialServerRepository`, VICIdial Agent/Non-Agent proxy services, Supervisor API controllers, and the Supervisor Blade/Alpine interface.
- Existing `vicidial_servers.campaign_code`, `is_default`, and `priority` fields remain the mapping mechanism; no database migration or dependency change is expected.
- Deployment configuration must include an active VICIdial server row for every telephony-enabled campaign. The global `VICI_NON_AGENT_API_URL` setting becomes a legacy/default-only concern rather than overriding mapped server endpoints.
