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

### Requirement: Supervisor can select a CRM campaign independently of VICIdial
The Supervisor dashboard SHALL allow an authorized supervisor to select a CRM campaign explicitly. The selected CRM campaign MUST determine the VICIdial server and MUST NOT be replaced by `session('vicidial_campaign')` or another VICIdial campaign value.

#### Scenario: Supervisor selects another CRM campaign
- **WHEN** the supervisor selects `campaign-b` in the CRM campaign selector
- **THEN** the dashboard refreshes against the server mapped to `campaign-b`
- **AND** the separate VICIdial campaign session remains unchanged

### Requirement: Supervisor can display agents reported by the mapped server
When the mapped server has Non-Agent API credentials, the Supervisor dashboard SHALL use that server's logged-in-agent feed to supplement local CRM users. The feed MUST be requested across all VICIdial campaigns on that server, then matched to known CRM users by VICIdial username; a VICIdial campaign code MUST NOT be used as the CRM server mapping key.

#### Scenario: Agent is logged into a different VICIdial campaign on the mapped server
- **WHEN** a known CRM user is reported as logged in on the selected server under VICIdial campaign `softcamp`
- **AND** the active CRM campaign is `campaign-a`
- **THEN** the Supervisor card includes that user under `campaign-a`
- **AND** the card reflects the remote VICIdial status without switching the server mapping to `softcamp`

### Requirement: Supervisor wallboard reports derived, near-real-time KPIs
The Supervisor API SHALL obtain current operational agent and queue counts from supported Non-Agent API reports on the VICIdial server mapped to the selected CRM campaign. It SHALL request agent state across all VICIdial campaigns on that server, MUST NOT use a VICIdial campaign ID as the CRM server-routing key, and SHALL fall back per metric family to campaign-scoped CRM lifecycle data when a remote function is unavailable or unparseable. The dashboard SHALL refresh without overlapping requests and SHALL retain the last known values when a transient refresh fails.

#### Scenario: Call lifecycle records determine today's metrics
- **WHEN** the selected campaign has completed, answered, failed, and active call-session records
- **THEN** today's total, answered count, average wait, average handle, and answer rate are calculated from those records
- **AND** call records belonging to another CRM campaign are excluded

#### Scenario: Mapped server reports current user-group status
- **WHEN** the selected campaign's mapped server returns valid `logged_in_agents` and `user_group_status` rows
- **THEN** online, available, on-call, paused, active-call, and calls-waiting metrics use the VICIdial values
- **AND** activity from a different configured VICIdial server is excluded

#### Scenario: One remote report is unavailable
- **WHEN** the selected server returns daily call totals but rejects its agent-performance report
- **THEN** the Supervisor uses VICIdial daily totals and campaign-scoped CRM timing metrics
- **AND** the successful metric families are not discarded or added to their CRM equivalents

#### Scenario: Supervisor refresh overlaps a slow request
- **WHEN** a poll is still in flight when the next refresh trigger occurs
- **THEN** the dashboard skips the overlapping request
- **AND** a transient failure retains the last successfully displayed values

### Requirement: Supervisor call totals use the mapped VICIdial server
When a selected CRM campaign has a mapped VICIdial server with report-capable Non-Agent API credentials, the Supervisor API SHALL use that server's successful daily `call_status_stats` response for total calls, answered calls, and hourly call volume. The API MUST fall back to campaign-scoped CRM call sessions when the remote report is unavailable, unauthorized, or unparseable, and MUST NOT add the two sources together.

#### Scenario: VICIdial reports calls while CRM ingestion is empty
- **WHEN** the selected CRM campaign's mapped server returns valid `call_status_stats` rows for today
- **AND** CRM `call_sessions` has no records for that campaign
- **THEN** the Supervisor response reports the VICIdial total and answered count
- **AND** the hourly chart uses the VICIdial hourly breakdown

#### Scenario: VICIdial report is unavailable
- **WHEN** the mapped server cannot be reached, rejects the report, or returns no parseable rows
- **THEN** the Supervisor response uses the selected campaign's CRM call-session totals and hourly records
- **AND** the response remains successful without exposing connection credentials

#### Scenario: Remote totals do not double-count CRM calls
- **WHEN** a call exists in both the VICIdial report and CRM `call_sessions`
- **THEN** the Supervisor uses the successful VICIdial aggregate once
- **AND** it does not sum the remote and CRM totals together

### Requirement: Supervisor agent cards may use VICIdial calls-today values
When the mapped server's `logged_in_agents` or `agent_stats_export` feed includes valid calls-today and timing values, the Supervisor agent card SHALL display those values for the matching CRM user. The feed MUST cover all VICIdial campaigns on that server. Missing or invalid remote fields SHALL fall back to the user's selected-CRM-campaign call-session metrics.

#### Scenario: Logged-in agent has a remote calls-today value
- **WHEN** a known CRM user is returned by `logged_in_agents` with `calls_today=12`
- **THEN** that user's Supervisor card reports 12 calls today
- **AND** the card remains under the selected CRM campaign

#### Scenario: Active-today agent has VICIdial performance values
- **WHEN** a known CRM user is returned by `agent_stats_export` with calls and average talk time for today
- **THEN** that user's Supervisor card reports the VICIdial calls and timing values
- **AND** the card remains under the selected CRM campaign even if the VICIdial campaign ID differs

#### Scenario: Agent performance row is malformed
- **WHEN** a matching agent row contains a non-numeric calls value or invalid duration
- **THEN** the invalid field is ignored
- **AND** the card retains its selected-campaign CRM value for that metric

### Requirement: Supervisor identifies metric provenance and freshness
The Supervisor API SHALL identify the source of operational, agent-performance, and call-total metric families without exposing a server URL, username, password, or database credential. The dashboard SHALL display text labels for those sources and the time of the latest successful response.

#### Scenario: VICIdial functions partially succeed
- **WHEN** operational state and call totals come from VICIdial but performance timing falls back to CRM
- **THEN** the response marks the operational and call-total sources as VICIdial and the performance source as CRM
- **AND** the dashboard communicates the mixed-source state in text rather than color alone

#### Scenario: Source metadata is returned
- **WHEN** the Supervisor API responds for a configured campaign
- **THEN** it includes a server-side update timestamp and non-sensitive source identifiers
- **AND** it omits credential and raw request URL fields

### Requirement: Supervisor agent cards are read-only
The Supervisor agent grid SHALL show status and performance information without rendering monitor, whisper, pause, or logout controls. Existing protected API endpoints MAY remain available for other integrations, but this dashboard MUST NOT expose those actions per agent.

#### Scenario: Agent grid renders without telephony controls
- **WHEN** the Supervisor dashboard displays one or more agents
- **THEN** each card contains status and metrics only
- **AND** monitor, whisper, pause, and logout buttons are absent
