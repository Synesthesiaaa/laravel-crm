# Platform Stabilization

## Purpose

Keep the CRM shell, telephony runtime, backend hotspots, and verification paths stable under soft navigation and production use.

## Requirements

### Requirement: Soft-navigation rehydration
The system SHALL restore page-local interactive behavior after a soft-navigation swap without requiring a hard refresh, and marked authenticated GET forms SHALL use the same swap boundary when they target an application page.

#### Scenario: Returning to a page after navigation
- **WHEN** an authenticated user leaves a page and returns to it through the app sidebar or another soft-navigation link
- **THEN** the page's buttons, dropdowns, and page-specific scripts SHALL work on the first click

#### Scenario: Submitting a marked GET form
- **WHEN** an authenticated user submits a GET form explicitly marked for soft navigation
- **THEN** the application SHALL replace only the page-content boundary and SHALL preserve global telephony widgets outside that boundary

#### Scenario: Marked form fallback
- **WHEN** a marked GET form cannot be loaded through the soft-navigation boundary
- **THEN** the browser SHALL perform the form's normal navigation instead of leaving the user on stale content

### Requirement: Widget lifecycle cleanup
The system SHALL destroy and recreate page-scoped chart and widget instances when a soft-navigation swap re-renders a page, while global telephony widgets and their active iframes SHALL remain mounted outside the swapped boundary.

#### Scenario: Revisiting a dashboard page
- **WHEN** a user opens a dashboard page, navigates away, and opens it again
- **THEN** the page SHALL render one live instance per chart or widget container and SHALL NOT duplicate listeners or overlays

#### Scenario: Data Master form switch with an active phone session
- **WHEN** a user switches the selected Data Master form through a marked GET form
- **THEN** the global phone widget SHALL remain mounted with its current iframe URL and session state

#### Scenario: Quick Form selector changes while Vicidial is active
- **WHEN** a user selects another active form from the Quick Form widget
- **THEN** only the Quick Form iframe SHALL change and the global phone widget SHALL remain mounted with its current iframe URL and session state

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

### Requirement: Vicidial session requests fall back to an active server
The system SHALL resolve Vicidial session login and recovery requests against the campaign-specific Vicidial server when a matching server exists and SHALL fall back to the active default Vicidial server when the requested campaign is not registered in CRM. The system SHALL only fail when no active Vicidial server is available or when the active server itself rejects the request.

#### Scenario: Off-CRM campaign uses fallback server
- **WHEN** a user opens the softphone on a Vicidial campaign that is not registered in the CRM campaign catalog
- **THEN** the login and iframe recovery requests SHALL use an active Vicidial server and SHALL continue instead of failing because the campaign has no direct CRM mapping

#### Scenario: No active server still fails
- **WHEN** there is no active Vicidial server available for the request
- **THEN** the softphone SHALL fail with an actionable configuration error rather than timing out as if the campaign mismatch were the problem

### Requirement: Single telephony media owner
The system SHALL allow only one active browser media path per authenticated session and SHALL honor the configured media path without double-registering the same extension.

#### Scenario: SIP.js owns audio
- **WHEN** the configured media path is `sipjs`
- **THEN** the browser SHALL register SIP.js for the session and the Vicidial iframe audio path SHALL remain inactive

#### Scenario: Vicidial owns audio
- **WHEN** the configured media path is `viciphone`
- **THEN** SIP.js registration SHALL be skipped and the Vicidial iframe SHALL own audio for the session

#### Scenario: Dual mode is temporary only
- **WHEN** the configured media path is `both`
- **THEN** the system SHALL surface a warning that the session is in migration mode and SHALL treat dual registration as non-standard

### Requirement: Portable test-critical migrations
The system SHALL keep test-critical database migrations compatible with SQLite and production database engines.

#### Scenario: PHPUnit on SQLite
- **WHEN** the test suite runs against SQLite
- **THEN** migrations SHALL complete without vendor-specific SQL errors

### Requirement: Bounded admin diagnostics
The system SHALL avoid N+1 query patterns in admin diagnostics and other high-frequency reporting checks.

#### Scenario: Campaign mapping diagnostics
- **WHEN** the telephony diagnostics validate campaign-to-server mappings
- **THEN** the check SHALL complete with a bounded query set and SHALL NOT perform one database lookup per campaign

### Requirement: Safe logout and webhook hardening
The system SHALL complete logout cleanup without double-submit behavior and SHALL reject misconfigured public webhook traffic in production.

#### Scenario: Logout during active telephony use
- **WHEN** a signed-in user logs out while a telephony session is active
- **THEN** the client SHALL hang up or destroy the telephony runtime once, clear any embedded session frame, and submit the logout form once

#### Scenario: Missing or invalid webhook secret in production
- **WHEN** production receives a webhook request with a missing or invalid shared secret
- **THEN** the request SHALL be rejected instead of being accepted silently
