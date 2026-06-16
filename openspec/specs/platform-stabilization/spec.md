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
