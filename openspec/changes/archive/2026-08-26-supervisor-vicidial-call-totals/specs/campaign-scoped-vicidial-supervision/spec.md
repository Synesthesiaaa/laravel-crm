## ADDED Requirements

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
When the mapped server's logged-in-agent feed includes a numeric `calls_today` value, the Supervisor agent card SHALL display that value for the matching CRM user. If the field is absent or invalid, the card SHALL retain its campaign-scoped CRM terminal-call count.

#### Scenario: Logged-in agent has a remote calls-today value
- **WHEN** a known CRM user is returned by `logged_in_agents` with `calls_today=12`
- **THEN** that user's Supervisor card reports 12 calls today
- **AND** the card remains under the selected CRM campaign
