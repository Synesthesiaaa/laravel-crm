# Platform Stabilization

## Purpose

Keep the CRM shell, telephony runtime, backend hotspots, and verification paths stable under soft navigation and production use.

## Requirements

### Requirement: Soft-navigation rehydration
The system SHALL restore page-local interactive behavior after a soft-navigation swap without requiring a hard refresh.

#### Scenario: Returning to a page after navigation
- **WHEN** an authenticated user leaves a page and returns to it through the app sidebar or another soft-navigation link
- **THEN** the page's buttons, dropdowns, and page-specific scripts SHALL work on the first click

### Requirement: Widget lifecycle cleanup
The system SHALL destroy and recreate page-scoped chart and widget instances when a soft-navigation swap re-renders a page.

#### Scenario: Revisiting a dashboard page
- **WHEN** a user opens a dashboard page, navigates away, and opens it again
- **THEN** the page SHALL render one live instance per chart or widget container and SHALL NOT duplicate listeners or overlays

### Requirement: Softphone campaign selection is resolved outside the widget
The system SHALL resolve the Vicidial campaign used by the floating softphone widget from the existing campaign source chain and SHALL NOT expose a widget-side campaign selector, allowed-campaign list, or widget-specific campaign preference.

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
