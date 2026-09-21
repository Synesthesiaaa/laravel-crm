## MODIFIED Requirements

### Requirement: Supervisor wallboard reports derived, near-real-time KPIs
The Supervisor API SHALL obtain current operational agent and queue counts from supported Non-Agent API reports on the VICIdial server mapped to the selected CRM campaign. It SHALL request agent state across all VICIdial campaigns on that server, MUST NOT use a VICIdial campaign ID as the CRM server-routing key, and SHALL fall back per metric family to campaign-scoped CRM lifecycle data when a remote function is unavailable or unparseable. The dashboard SHALL refresh without overlapping requests and SHALL retain the last known values when a transient refresh fails.

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

### Requirement: Supervisor agent cards may use VICIdial calls-today values
When the mapped server's `logged_in_agents` or `agent_stats_export` feed includes valid calls-today and timing values, the Supervisor agent card SHALL display those values for the matching CRM user. The feed MUST cover all VICIdial campaigns on that server. Missing or invalid remote fields SHALL fall back to the user's selected-CRM-campaign call-session metrics.

#### Scenario: Active-today agent has VICIdial performance values
- **WHEN** a known CRM user is returned by `agent_stats_export` with calls and average talk time for today
- **THEN** that user's Supervisor card reports the VICIdial calls and timing values
- **AND** the card remains under the selected CRM campaign even if the VICIdial campaign ID differs

#### Scenario: Agent performance row is malformed
- **WHEN** a matching agent row contains a non-numeric calls value or invalid duration
- **THEN** the invalid field is ignored
- **AND** the card retains its selected-campaign CRM value for that metric

## ADDED Requirements

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
