# Campaign-Linked VICIdial Widget

## Purpose

Keep the persistent VICIdial widget aligned with the active CRM campaign so each campaign uses its configured VICIdial server and a previous campaign session cannot be reused accidentally.

## Requirements

### Requirement: Widget bootstrap follows the active CRM campaign

The authenticated phone widget SHALL use the current CRM session campaign as its initial VICidial campaign when one is available. If no CRM session campaign is available, it MUST retain the existing user-default/config fallback behavior.

#### Scenario: CRM campaign is available

- **WHEN** an authenticated page renders with CRM session campaign `campaign-a`
- **THEN** the widget bootstrap and telephony dataset identify `campaign-a` as the VICIdial campaign

#### Scenario: CRM campaign is unavailable

- **WHEN** an authenticated page renders without a CRM session campaign and the user default campaign is `campaign-b`
- **THEN** the widget bootstrap uses `campaign-b`

### Requirement: Persistent widget synchronizes after CRM campaign navigation

The application SHALL update the persistent phone widget when soft navigation loads a different CRM campaign. The synchronization MUST update the campaign state without requiring a full page reload.

#### Scenario: Soft navigation changes campaign

- **WHEN** soft navigation fetches a page whose campaign dataset changes from `campaign-a` to `campaign-b`
- **THEN** the document campaign datasets are updated to `campaign-b` and a campaign-change event is dispatched
- **AND** the persistent widget changes its VICidial campaign to `campaign-b`

#### Scenario: Soft navigation has no campaign dataset

- **WHEN** soft navigation fetches a page without a campaign dataset
- **THEN** the current document and widget campaign state remain unchanged

### Requirement: Previous VICidial session state is not reused across campaigns

When the widget changes campaign, it SHALL not reuse the previous campaign's iframe URL, verification timers, logged-in status, queue count, or session display state for the new campaign.

#### Scenario: Campaign changes while widget is active

- **WHEN** the widget has a usable or transitional VICidial session for `campaign-a`
- **AND** the CRM campaign changes to `campaign-b`
- **THEN** pending verification is cancelled, the old iframe is cleared, and the shared VICidial state becomes logged out/idle for `campaign-b`
- **AND** the widget indicates that a new VICIdial login is required

#### Scenario: Campaign changes while widget is idle

- **WHEN** the widget is idle for `campaign-a`
- **AND** the CRM campaign changes to `campaign-b`
- **THEN** the widget changes to `campaign-b` without displaying an active-session reset warning

### Requirement: New campaign operations resolve the campaign server

After a CRM campaign change, VICidial login, iframe URL, status, pause, logout, and related widget operations SHALL use the new campaign code so the existing server repository selects that campaign's configured active/default server.

#### Scenario: Login after campaign change

- **WHEN** the widget is changed to `campaign-b` and the agent starts VICIdial login
- **THEN** the session login request carries `campaign-b`
- **AND** server resolution uses the active/default `vicidial_servers` record assigned to `campaign-b`

#### Scenario: Non-Agent request after campaign change

- **WHEN** a Non-Agent operation runs for `campaign-b`
- **THEN** its endpoint is derived from the active/default server assigned to `campaign-b`
- **AND** a global Non-Agent URL for another server does not override that endpoint

#### Scenario: New campaign has no server mapping

- **WHEN** the widget changes to a campaign with no active VICIdial server
- **THEN** the operation fails with an actionable configuration error for that campaign
- **AND** no server assigned to another campaign is used

#### Scenario: Multiple active servers remain campaign-local

- **WHEN** the selected campaign has multiple active VICIdial servers
- **THEN** resolution selects its default server first or its lowest-priority stable record
- **AND** no server assigned to another campaign is considered

### Requirement: Campaign-specific VICIdial endpoint precedence

Agent API and Non-Agent API requests MUST derive their endpoint from the selected campaign server record. A global endpoint override MUST NOT replace a campaign-specific endpoint for another VICIdial instance.

#### Scenario: Global endpoint does not override a mapped campaign

- **WHEN** a global Non-Agent endpoint is configured for a different VICIdial instance
- **AND** the requested campaign has a mapped server with its own API URL
- **THEN** the request uses the endpoint derived from the requested campaign's server
