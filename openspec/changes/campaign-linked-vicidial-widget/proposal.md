## Why

The persistent VICIdial phone widget currently keeps an independent campaign value, so it can remain connected to the previous campaign after the CRM campaign changes. This makes it possible for an agent to work in one CRM campaign while the widget sends login, status, and call actions to another campaign's VICIdial server.

## What Changes

- Make the current CRM campaign the widget's campaign source of truth.
- Synchronize the persistent widget with CRM campaign changes during soft navigation.
- Clear the old local widget session and iframe when the campaign changes, requiring a new VICIdial login for the selected campaign.
- Continue using the existing `vicidial_servers.campaign_code` mapping and active/default/priority selection rules.
- Add regression coverage for server-rendered bootstrap data and campaign-change behavior.

## Capabilities

### New Capabilities

- `campaign-linked-vicidial-widget`: Keeps the persistent VICIdial widget aligned with the active CRM campaign and its configured server.

### Modified Capabilities

None.

## Impact

- Affects the authenticated layout and phone widget Blade/JavaScript bootstrap.
- Affects soft-navigation campaign state synchronization.
- Reuses existing VICIdial session APIs and server repository; no database migration or new dependency is required.
- Requires PHPUnit coverage, frontend build validation, and Playwright verification of campaign navigation with the persistent widget.
