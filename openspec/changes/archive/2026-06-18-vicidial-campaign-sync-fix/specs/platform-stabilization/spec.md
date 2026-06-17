## ADDED Requirements

### Requirement: Confirmed Vicidial campaign becomes the active telephony campaign
The system SHALL persist the campaign confirmed by Vicidial as the active telephony campaign for the current session after login or verification confirms a live agent, and SHALL use that campaign for subsequent iframe recovery, status sync, pause, logout, and reconnect flows.

#### Scenario: Vicidial confirms a different campaign
- **WHEN** a user logs in through the softphone and Vicidial reports a live agent session under a different campaign than the CRM default
- **THEN** the system SHALL store the Vicidial-confirmed campaign as the active telephony campaign and SHALL continue the session using that campaign

#### Scenario: Reload reuses the confirmed campaign
- **WHEN** the browser reloads after a live Vicidial session has already been confirmed
- **THEN** bootstrap and reconnect logic SHALL use the confirmed telephony campaign instead of forcing the original CRM campaign

#### Scenario: Follow-up telephony actions stay on the synced campaign
- **WHEN** the user pauses, resumes, requests status, opens the iframe, or logs out after the campaign has been synced
- **THEN** the backend SHALL target the synced telephony campaign for those requests

### Requirement: Live Vicidial readiness does not depend on CRM campaign equality
The system SHALL mark the softphone session ready when Vicidial confirms a live agent session for the current user, even if the Vicidial campaign differs from the CRM campaign value, and SHALL only show a campaign-mismatch failure when Vicidial does not report a live agent session.

#### Scenario: Live agent with mismatched campaign
- **WHEN** Vicidial reports the agent as live and the agent identity matches but the Vicidial campaign differs from the CRM campaign
- **THEN** verification SHALL return ready and SHALL NOT fail solely because the campaigns are not equal

#### Scenario: No live agent still fails
- **WHEN** Vicidial does not report the agent as live
- **THEN** verification SHALL remain pending or failed with actionable diagnostics rather than reporting readiness

#### Scenario: Diagnostics stay focused on live-agent state
- **WHEN** verification fails because Vicidial does not report a live agent session
- **THEN** the response SHALL focus on agent login alignment and live-agent state and SHALL NOT require CRM and Vicidial campaign equality as a readiness condition
