## Context

The application renders a global phone widget in `layouts.app`. The widget remains mounted while `soft-navigate.js` swaps the main page content. Its current bootstrap prefers `session('vicidial_campaign')` and the user's default campaign, while CRM pages use `session('campaign')`. The backend already accepts a campaign for every VICIdial session operation and resolves the matching active/default server through `VicidialServerRepository`.

The change must align these existing paths without introducing a second campaign-mapping model or breaking the persistent iframe/WebRTC lifecycle.

## Goals / Non-Goals

**Goals:**

- Use the active CRM campaign as the phone widget's campaign context.
- Detect campaign changes made through soft navigation while the widget remains mounted.
- Prevent a previous campaign's local session/iframe state from being reused for the new campaign.
- Preserve the existing campaign-to-server selection and API contracts.
- Make the transition safe when no widget session is active.

**Non-Goals:**

- Adding a separate VICIdial campaign code to the `campaigns` table.
- Changing the `vicidial_servers` schema or admin forms.
- Automatically migrating an agent's active call to another campaign.
- Refactoring the existing VICIdial session API or server repository.

## Decisions

### Use CRM session campaign as the widget source of truth

The Blade layout and widget bootstrap will resolve `session('campaign')` first, then retain the existing user-default/config fallback for pages without an initialized CRM campaign. The widget's runtime campaign state will be updated from the CRM campaign event rather than relying on the stale independent `vicidial_campaign` session value.

Alternative considered: add a campaign-level `vicidial_campaign` column. This was rejected because the current server rows already use the CRM campaign code as their mapping key, and a second code would create a new configuration surface without a stated need.

### Synchronize through a browser event

Before `soft-navigate.js` swaps page content, it will copy campaign dataset values from the fetched document body to the persistent document body and dispatch `crm-campaign-changed` only when the campaign code differs. The phone widget will register one handler during `init()`.

Alternative considered: destroy and recreate the entire layout on each navigation. This was rejected because it would interrupt the existing iframe and WebRTC persistence behavior.

### Reset local session state on campaign changes

The widget handler will cancel pending verification, clear the iframe, reset the shared VICIdial store to logged-out state, clear the old campaign's queue/session display values, and set the new campaign. It will show a notice only when an active/transitional session was present; idle widgets will switch silently.

Alternative considered: silently keep the old iframe/session alive. This was rejected because it permits campaign/server mismatch and can route actions to the wrong VICIdial instance.

### Keep server resolution unchanged

All subsequent login, status, iframe, pause, and logout requests will continue receiving the new campaign code. `VicidialServerRepository::getForCampaign()` remains the sole server-selection path, preserving default and priority behavior and avoiding duplicate resolution logic.

## Risks / Trade-offs

- [An agent may navigate campaigns during an active call] -> The client will clear only the local widget state and require a new login; it will not attempt to transfer or migrate the call. Existing call/session protections remain responsible for active call handling.
- [The fetched HTML may not contain campaign dataset values] -> The soft-navigation code will leave the current body/widget campaign unchanged when values are missing.
- [The old remote VICIdial session may remain until explicit logout] -> The UI will make the old iframe unusable and require a new login for the selected campaign; the existing logout control remains available before switching.
- [A campaign has no matching server] -> Existing backend errors remain visible through the current login/status failure messages; no fallback behavior is changed by this UI alignment.

## Migration Plan

No database migration or deployment data conversion is required. Deploy the frontend/backend changes, rebuild Vite assets, and verify that each configured `vicidial_servers.campaign_code` matches the corresponding CRM campaign code. Rollback is a code rollback; existing server records and sessions remain compatible.

## Open Questions

None. The campaign code is intentionally the shared CRM/VICIdial mapping key for this change.
