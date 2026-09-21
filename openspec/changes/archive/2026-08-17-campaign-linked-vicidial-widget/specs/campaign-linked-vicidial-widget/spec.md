## ADDED Requirements

### Requirement: Widget bootstrap follows the active CRM campaign

The authenticated phone widget SHALL use the current CRM session campaign as its initial VICidial campaign when one is available. If no CRM session campaign is available, it MUST retain the existing user-default/config fallback behavior.

#### Scenario: CRM campaign is available

- **WHEN** an authenticated page renders with CRM session campaign `campaign-a`
- **THEN** the widget bootstrap and telephony dataset identify `campaign-a` as the VICidial campaign

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

### Requirement: Previous VICIdial session state is not reused across campaigns

When the widget changes campaign, it SHALL not reuse the previous campaign's iframe URL, verification timers, logged-in status, queue count, or session display state for the new campaign.

#### Scenario: Campaign changes while widget is active

- **WHEN** the widget has a usable or transitional VICIdial session for `campaign-a`
- **AND** the CRM campaign changes to `campaign-b`
- **THEN** pending verification is cancelled, the old iframe is cleared, and the shared VICIdial state becomes logged out/idle for `campaign-b`
- **AND** the widget indicates that a new VICIdial login is required

#### Scenario: Campaign changes while widget is idle

- **WHEN** the widget is idle for `campaign-a`
- **AND** the CRM campaign changes to `campaign-b`
- **THEN** the widget changes to `campaign-b` without displaying an active-session reset warning

### Requirement: New campaign operations resolve the campaign server

After a CRM campaign change, VICIdial login, iframe URL, status, pause, logout, and related widget operations SHALL use the new campaign code so the existing server repository selects that campaign's configured active/default server.

#### Scenario: Login after campaign change

- **WHEN** the widget is changed to `campaign-b` and the agent starts VICIdial login
- **THEN** the session login request carries `campaign-b`
- **AND** server resolution uses the active/default `vicidial_servers` record assigned to `campaign-b`
