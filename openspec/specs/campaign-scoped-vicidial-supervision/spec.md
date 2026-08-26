# Campaign-Scoped VICIdial Supervision

## Purpose

Keep Supervisor monitoring, metrics, and VICIdial actions aligned with the active CRM campaign and its assigned VICIdial server.

## Requirements

### Requirement: Supervisor data is scoped to the active CRM campaign
The Supervisor dashboard SHALL use the active CRM session campaign as its campaign context. Agent VICIdial sessions, active calls, completed-call totals, dispositions, and derived wallboard totals MUST be limited to that campaign.

#### Scenario: Supervisor opens a configured campaign
- **WHEN** a supervisor opens the dashboard with `campaign-a` as the active CRM campaign
- **THEN** the API returns telephony data and totals for `campaign-a`
- **AND** activity belonging only to `campaign-b` is excluded

#### Scenario: Agent has activity in multiple campaigns
- **WHEN** an agent has session or call records in both `campaign-a` and `campaign-b`
- **AND** the Supervisor dashboard is displaying `campaign-a`
- **THEN** the agent card reflects only the agent's `campaign-a` state and metrics

### Requirement: Supervisor displays its VICIdial routing context
The Supervisor dashboard SHALL display the active campaign and the non-sensitive identity of the VICIdial server resolved for that campaign. It MUST NOT expose server credentials or a raw credential-bearing request URL.

#### Scenario: Campaign has a configured server
- **WHEN** the active campaign resolves to an active VICIdial server
- **THEN** the dashboard identifies the active campaign and server name near the live-status context

#### Scenario: Campaign has no configured server
- **WHEN** the active campaign has no active VICIdial server mapping
- **THEN** the dashboard displays an actionable configuration error for that campaign
- **AND** VICIdial-directed Supervisor controls are unavailable

### Requirement: Supervisor actions preserve campaign context
Every VICIdial-directed Supervisor action SHALL carry the campaign represented by the dashboard or selected agent card. The backend MUST use that campaign when resolving the VICIdial server.

#### Scenario: Monitor an agent on a second campaign
- **WHEN** a supervisor monitors an agent while `campaign-b` is active
- **THEN** the monitor request carries `campaign-b`
- **AND** the action is sent only to the active/default server assigned to `campaign-b`

#### Scenario: Supervisor action fails
- **WHEN** a monitor, whisper, pause, logout, or notification request is rejected or cannot reach the campaign server
- **THEN** the interface reports the failure instead of displaying a success confirmation
