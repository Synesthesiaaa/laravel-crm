## Context

The application already stores VICIdial connections in `vicidial_servers`, keyed by `campaign_code`, and chooses a default/priority record through `VicidialServerRepository`. The repository currently falls back to the first active server from any campaign when no exact match exists. The Supervisor dashboard also omits campaign context from action requests, while `SupervisorTelephonyController` falls back to the supervisor user's telephony/default campaign. Together these behaviors can send an action for one campaign to another campaign's VICIdial instance.

The current database illustrates the unsafe state: `mbsales` has the only active server mapping while local VICIdial session rows also exist for `pjli`. In addition, `VICI_NON_AGENT_API_URL` is set and `VicidialNonAgentApiService` currently gives it precedence over the selected server row, which collapses Non-Agent traffic onto one global endpoint.

## Goals / Non-Goals

**Goals:**

- Treat a campaign's VICIdial server set as an isolation boundary.
- Route Supervisor reads and actions with the active CRM campaign.
- Make the resolved campaign/server visible without exposing credentials or sensitive URLs.
- Fail clearly when a campaign is missing a mapping.
- Preserve the existing per-campaign default and priority rules.
- Prove two-campaign routing and missing-mapping behavior with automated tests and browser validation.

**Non-Goals:**

- Automatically creating the real `pjli` server record or handling deployment credentials.
- Combining every campaign into one cross-campaign Supervisor wallboard.
- Adding automatic health-based failover between servers.
- Changing the `vicidial_servers` schema or adding dependencies.

## Decisions

### Make repository resolution strict by campaign

`VicidialServerRepository::getForCampaign()` will search only the requested campaign's active rows, using `is_default`, `priority`, and stable `id` ordering. It will return `null` when no match exists. Services will keep their existing actionable failure path for a missing server.

Alternative considered: retain the cross-campaign default as a last resort. This was rejected because availability obtained by contacting the wrong dialer is unsafe and difficult to diagnose.

### Use the active CRM campaign for Supervisor scope

`SupervisorAgentsController` will resolve the active CRM session campaign and scope session, call, disposition, and aggregate queries to it. The response will include a top-level routing context containing the campaign code/name, configured state, and non-sensitive server identity. Agent items will carry the same campaign code so the existing Alpine controls can submit it explicitly.

Alternative considered: combine agents from all campaigns in one response and route every card independently. This was deferred because the application already has a global campaign selector, and a combined wallboard would require additional filtering, aggregation, and interaction design beyond the requested fix.

### Require campaign context on Supervisor telephony actions

Monitor, whisper, pause, logout, and notification requests will submit the displayed campaign. The controller will validate the value and pass it to `VicidialProxyService`, whose strict repository lookup selects only that campaign's server. The interface will stop swallowing failed action requests and will show accurate success or error feedback. VICIdial-directed controls will be disabled when the routing context is unconfigured.

Alternative considered: infer the campaign solely from the target user's latest VICIdial session. This was rejected because an agent can retain historical rows for multiple campaigns and the Supervisor page already has an explicit active campaign context.

### Derive Non-Agent endpoints from the selected server row

`VicidialNonAgentApiService` will derive `non_agent_api.php` from the selected row's `api_url`. A global `VICI_NON_AGENT_API_URL` value will not override a valid campaign-specific server URL. The configuration key can remain for compatibility, but it cannot collapse mapped campaigns onto a single endpoint.

Alternative considered: add a separate `non_agent_api_url` column to every server. This was rejected because standard VICIdial Agent and Non-Agent API paths share a host and the existing derivation already supports the known URL forms.

### Expose identity, not connection secrets

The Supervisor UI will show campaign name/code and server name only. API responses will not include API credentials, database credentials, or raw URLs. The routing label will be ordinary visible status text; async action messages will continue through the existing toast system with truthful error handling.

## Risks / Trade-offs

- [An unmapped campaign stops working immediately] -> Validate configuration before deployment and return a clear campaign-specific error instead of silently misrouting.
- [Existing off-CRM campaign behavior changes] -> Update the platform specification and tests; require an explicit CRM/server mapping for every telephony campaign.
- [A global Non-Agent override was masking an unusual endpoint] -> Derive from each server row and document that every mapped row must contain the correct VICIdial API base URL.
- [An agent has records in multiple campaigns] -> Scope every Supervisor data query to the selected campaign and send that campaign explicitly with actions.
- [Rapid repeated controls issue duplicate requests] -> Track action state per agent/control, disable the in-flight control, and provide completion feedback without changing focus.

## Migration Plan

1. Before deployment, add and validate an active `vicidial_servers` row for `pjli` and every other telephony-enabled campaign using the existing admin screen.
2. Deploy the strict resolver, Supervisor API/UI changes, and updated frontend assets together.
3. Clear Laravel configuration cache so endpoint precedence changes take effect.
4. Verify each campaign from the Supervisor page and confirm actions reach the mapped server.
5. Roll back the code if needed; no schema or data rollback is required. Existing server rows remain compatible.

## Open Questions

None. The active CRM campaign is the Supervisor source of truth, and server fallback is intentionally restricted to rows within that campaign.
