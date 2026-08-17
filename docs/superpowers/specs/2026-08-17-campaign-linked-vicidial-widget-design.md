# Campaign-Linked VICIdial Widget Design

## Goal

Make the persistent VICIdial phone widget follow the CRM campaign selected for the current request, so each campaign uses its configured VICIdial server and agents do not accidentally remain connected to the previous campaign's dialer.

## Current Context

- CRM campaigns are stored in `campaigns` and the active campaign is kept in the session as `campaign`.
- VICIdial servers are already stored in `vicidial_servers` with `campaign_code`, `is_active`, `is_default`, and `priority`.
- `VicidialServerRepository::getForCampaign()` already resolves the active/default server for a campaign.
- Agent API, Non-Agent API, iframe URL generation, status, pause, and logout requests already accept a campaign and resolve the server through that repository.
- The phone widget is persistent across soft navigation. It currently prefers the independent `vicidial_campaign` session value and therefore can remain on the prior campaign after a CRM campaign switch.

## Scope

### In scope

1. Make the current CRM session campaign the widget's initial VICIdial campaign.
2. Keep the existing user-default/config fallback only when no CRM campaign is available.
3. Synchronize the persistent widget when soft navigation loads a page for a different CRM campaign.
4. Stop using the old widget session locally when the campaign changes, clear the old iframe, and require a new VICIdial login for the new campaign.
5. Preserve campaign-aware server resolution and add regression coverage for the initial boot and campaign-change lifecycle.

### Out of scope

- A separate CRM-to-VICIdial campaign-code mapping field.
- A new database table or migration.
- Allowing one widget session to stay logged into a different campaign from the CRM campaign.
- Changing the existing admin server configuration workflow.

## Design

### Campaign source of truth

Server-rendered layout and widget bootstrap data will use `session('campaign')` first. The current user default and `mbsales` remain fallback values for authenticated pages that do not yet have a resolved campaign session.

The layout's `data-campaign` and `data-telephony-campaign` values will represent the same campaign. The phone widget's `telephonyCampaign()` will prefer its synchronized campaign state and no longer intentionally ignore the CRM campaign.

### Soft-navigation synchronization

`soft-navigate.js` will read the fetched document body's campaign dataset before replacing the main layout. When the campaign changes, it will update the current document body's `data-campaign` and `data-telephony-campaign` values and dispatch a `crm-campaign-changed` event containing the new campaign code and display name.

The persistent phone widget will register one listener during initialization. It will ignore duplicate campaign events and update its campaign state for a real change.

### Active-session transition

When a campaign change arrives while the widget has a usable or transitional VICIdial session:

1. Cancel iframe verification timers and clear the old iframe URL.
2. Reset the local VICIdial store to logged-out/idle state and clear queue/session display data.
3. Update the widget campaign to the new CRM campaign.
4. Show a concise notice that a new VICIdial login is required for the selected campaign.

The old remote session is not silently reused for the new campaign. Existing explicit logout remains available and the next login request uses the new campaign, which selects that campaign's configured server through the existing repository.

If the widget is already idle, the campaign changes without clearing an active iframe or showing an unnecessary warning.

### Server selection

No schema or server-selection rewrite is needed. For campaign `X`, the existing `VicidialServerRepository::getForCampaign('X')` remains the single resolution path. The admin config page continues to assign `campaign_code`, active/default flags, and priority per server.

## Error handling

- If no campaign is available at render time, retain the existing fallback behavior.
- If no VICIdial server is configured for the selected campaign, existing API error responses remain unchanged.
- If the iframe or status request fails during a new login, the existing widget failure/timeout behavior remains in effect.
- Campaign synchronization must not throw if a fetched document has no campaign dataset; the current widget state remains unchanged.

## Tests

- Update the phone widget feature test to assert that the CRM session campaign is used when it differs from the old independent VICidial session value.
- Keep coverage for user-default fallback when no CRM campaign exists.
- Add a browser-side unit-style assertion for the campaign-change event path if the project test setup supports it; otherwise cover the event contract with a focused JavaScript test or browser verification.
- Run the affected PHPUnit tests, Pint for modified PHP, the frontend build, and Playwright verification of the persistent widget across a campaign navigation.

## Success criteria

- Selecting or navigating to CRM campaign `A` makes the widget send campaign `A` to login/status/iframe endpoints.
- Navigating to CRM campaign `B` updates the persistent widget to `B` without a full page reload.
- A session active for `A` is not reused for `B`; the widget is visibly idle and requires login for `B`.
- A login for `B` resolves the `vicidial_servers` row configured for `B`.
