## MODIFIED Requirements

### Requirement: New campaign operations resolve the campaign server
After a CRM campaign change, VICidial login, iframe URL, status, pause, logout, and related widget operations SHALL use the new campaign code so the server repository selects an active/default server assigned to that exact campaign. Agent API and Non-Agent API requests MUST derive their endpoint from the selected server record and MUST NOT replace a campaign-specific endpoint with a global endpoint configured for another VICIdial instance.

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

