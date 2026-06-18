## MODIFIED Requirements

### Requirement: Softphone campaign selection is resolved outside the widget
The system SHALL resolve the Vicidial campaign used by the floating softphone widget from the existing campaign source chain and SHALL NOT expose a widget-side campaign selector, allowed-campaign list, or widget-specific campaign preference. The system SHALL keep the CRM campaign value separate and SHALL NOT require CRM campaign equality as a condition for softphone connection.

#### Scenario: Widget opens without a selector
- **WHEN** an authenticated user opens the floating softphone widget
- **THEN** the widget SHALL initialize with the resolved campaign value and SHALL NOT prompt the user to choose a campaign

#### Scenario: Vicidial actions use the resolved campaign
- **WHEN** the widget performs login, verification, pause, logout, or iframe recovery
- **THEN** those actions SHALL continue to use the resolved campaign value from the current session context

#### Scenario: Widget does not persist campaign choice
- **WHEN** the user interacts with the softphone widget
- **THEN** the system SHALL NOT create or update a separate widget-only campaign preference

#### Scenario: CRM campaign mismatch does not block startup
- **WHEN** the CRM campaign value differs from the Vicidial campaign used by the softphone
- **THEN** the widget SHALL continue connecting on the Vicidial campaign and SHALL NOT fail solely because the campaigns differ

### Requirement: Confirmed Vicidial campaign becomes the active telephony campaign
The system SHALL persist the campaign confirmed by Vicidial as the active telephony campaign for the current session after login or verification confirms a live agent, and SHALL use that campaign for subsequent iframe recovery, status sync, pause, logout, and reconnect flows. The system SHALL NOT rewrite the CRM campaign session value when the telephony campaign changes.

#### Scenario: Vicidial confirms a different campaign
- **WHEN** a user logs in through the softphone and Vicidial reports a live agent session under a different campaign than the CRM default
- **THEN** the system SHALL store the Vicidial-confirmed campaign as the active telephony campaign and SHALL continue the session using that campaign

#### Scenario: Reload reuses the confirmed campaign
- **WHEN** the browser reloads after a live Vicidial session has already been confirmed
- **THEN** bootstrap and reconnect logic SHALL use the confirmed telephony campaign instead of forcing the original CRM campaign

#### Scenario: Follow-up telephony actions stay on the synced campaign
- **WHEN** the user pauses, resumes, requests status, opens the iframe, or logs out after the campaign has been synced
- **THEN** the backend SHALL target the synced telephony campaign for those requests

#### Scenario: CRM campaign remains unchanged
- **WHEN** Vicidial confirms a live agent session under a different telephony campaign
- **THEN** the CRM session campaign SHALL remain unchanged for CRM pages and SHALL NOT be overwritten by the softphone flow

### Requirement: Live Vicidial readiness does not depend on CRM campaign equality
The system SHALL mark the softphone session ready when Vicidial confirms a live agent session for the current user, even if the Vicidial campaign differs from the CRM campaign value, and SHALL NOT surface timeout, connecting, or failure states solely because the campaigns are not equal. The system SHALL only show a campaign-mismatch failure when Vicidial does not report a live agent session or the connection itself fails.

#### Scenario: Live agent with mismatched campaign
- **WHEN** Vicidial reports the agent as live and the agent identity matches but the Vicidial campaign differs from the CRM campaign
- **THEN** verification SHALL return ready and SHALL NOT fail solely because the campaigns are not equal

#### Scenario: No live agent still fails
- **WHEN** Vicidial does not report the agent as live
- **THEN** verification SHALL remain pending or failed with actionable diagnostics rather than reporting readiness

#### Scenario: Diagnostics stay focused on live-agent state
- **WHEN** verification fails because Vicidial does not report a live agent session
- **THEN** the response SHALL focus on agent login alignment and live-agent state and SHALL NOT require CRM and Vicidial campaign equality as a readiness condition

#### Scenario: Transport failures still timeout
- **WHEN** the iframe or Vicidial request cannot complete because of a real transport, authentication, or load failure
- **THEN** the widget SHALL continue to use its normal timeout or failure handling

### Requirement: Vicidial session requests fall back to an active server
The system SHALL resolve Vicidial session login and recovery requests against the campaign-specific Vicidial server when a matching server exists and SHALL fall back to the active default Vicidial server when the requested campaign is not registered in CRM. The system SHALL only fail when no active Vicidial server is available or when the active server itself rejects the request.

#### Scenario: Off-CRM campaign uses fallback server
- **WHEN** a user opens the softphone on a Vicidial campaign that is not registered in the CRM campaign catalog
- **THEN** the login and iframe recovery requests SHALL use an active Vicidial server and SHALL continue instead of failing because the campaign has no direct CRM mapping

#### Scenario: No active server still fails
- **WHEN** there is no active Vicidial server available for the request
- **THEN** the softphone SHALL fail with an actionable configuration error rather than timing out as if the campaign mismatch were the problem
