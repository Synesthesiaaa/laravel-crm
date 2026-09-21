# Login Experience

## Purpose

Provide a minimal, palette-aligned, accessible guest login surface for the CRM's credential and campaign-aware entry flow.

## Requirements

### Requirement: Palette-aligned login composition

The guest login page SHALL present a focused, brand-led authentication composition within the CRM's established visual system. It SHALL keep the configured brand lockup visible above a single dominant auth sheet, use the CRM's shared surface, text, border, elevation, and Signal Magenta tokens, and avoid redundant context content or unverified marketing claims.

#### Scenario: Dark theme entry

- **WHEN** a guest opens the login route with the dark theme active
- **THEN** the page renders a calm charcoal single-column entry with the configured brand above the auth sheet, readable primary and muted text, and Signal Magenta reserved for the primary action, focus, and meaningful emphasis

#### Scenario: Light theme entry

- **WHEN** a guest switches the login page to the light theme
- **THEN** the page rebinds its surfaces, borders, text, elevation, and atmosphere to the existing light-theme tokens without introducing a separate hard-coded palette

#### Scenario: Configured brand remains visible

- **WHEN** the login page receives a configured company name and logo or the safe fallback brand
- **THEN** the compact brand lockup remains visible above the auth sheet, preserves its accessible alternative text, and long names wrap within the bounded column without creating horizontal overflow

### Requirement: Login task hierarchy and form semantics

The login page SHALL make the credential task, optional campaign context, feedback state, recovery guidance, and primary submit action visually and semantically distinct without changing the existing login request fields or route. Browser form submissions rejected because their CSRF/session token is expired or stale SHALL return the guest to a fresh login form with actionable recovery feedback rather than the generic 419 page.

#### Scenario: Credential form is ready for use

- **WHEN** a guest opens the login page
- **THEN** the page identifies the CRM as the destination, instructs the guest to use their username and password, exposes visible labels for both credentials, provides campaign context when campaigns exist, and presents one full-width sign-in action in logical tab order

#### Scenario: Campaign selection is available

- **WHEN** the application provides more than one campaign
- **THEN** the page labels the native control as a campaign choice, explains that the selected campaign is the guest's starting work area after sign-in, displays the current/old selection using the existing campaign values, and keeps the control keyboard- and touch-operable

#### Scenario: A single campaign is presented as read-only context

- **WHEN** the application provides exactly one campaign
- **THEN** the page shows the campaign name as the guest's starting work area without asking the guest to choose between options, and the submitted login continues to resolve to that configured campaign

#### Scenario: No campaigns are available

- **WHEN** the application provides no campaigns
- **THEN** the credential fields and submit action remain usable and the page does not render an empty or misleading campaign control

#### Scenario: Validation or status feedback is present

- **WHEN** the session contains a login status message or validation error
- **THEN** the message is rendered in a readable semantic feedback region within the auth sheet, field-specific messages appear adjacent to invalid controls, and the supervisor/help-desk recovery guidance remains discoverable without obscuring the fields or moving the submit action off-screen at supported widths

#### Scenario: Invalid credentials provide recovery guidance

- **WHEN** a guest submits credentials that do not authenticate
- **THEN** the page announces a privacy-safe message that the sign-in failed, tells the guest to check the username and password, highlights the affected field without identifying which credential was correct, and tells the guest to contact their supervisor or help desk if access remains blocked

#### Scenario: Password visibility can be toggled

- **WHEN** a guest needs to verify the password they entered
- **THEN** a keyboard- and touch-operable control toggles the password field between masked and visible states, exposes an accurate accessible name for the next action, and does not alter the submitted password value

#### Scenario: Theme control names its next action

- **WHEN** the login page loads with the dark theme active
- **THEN** the theme control has the accessible name and title “Switch to light mode,” and the existing theme script updates that name and title when the theme changes

#### Scenario: Expired login token returns to a fresh form

- **WHEN** a browser submits login or pending-login form data with an expired or stale CSRF/session token
- **THEN** the request is rejected without authenticating the user, the browser is redirected to the named login route, safe username/campaign context may be preserved, and the fresh form provides actionable feedback for retry

#### Scenario: Expired logout token remains recoverable

- **WHEN** an authenticated browser submits logout with an expired or stale CSRF/session token
- **THEN** the request is rejected without partially executing logout, the authenticated user is redirected to a fresh dashboard form when their session remains valid or to login when it does not, and the page provides actionable feedback for retry

### Requirement: Responsive and accessible interaction states

The login page SHALL remain usable at mobile, tablet, and desktop widths and SHALL provide visible focus, adequate touch targets, reduced-motion behavior, and clear feedback for authentication progress and recovery interactions.

#### Scenario: Narrow viewport

- **WHEN** a guest uses the page at a narrow or compact-height mobile viewport
- **THEN** the brand lockup stays compact above the auth sheet, the single-column form fits the viewport with safe gutters, the primary action remains reachable without avoidable vertical whitespace, controls remain at least 44 CSS pixels tall, and no page-level horizontal scrolling is introduced

#### Scenario: Keyboard focus

- **WHEN** a guest navigates the theme toggle, fields, password visibility control, campaign selector, recovery guidance, and submit button with a keyboard
- **THEN** focus moves in a logical order and each focused control has a visible token-based focus indicator

#### Scenario: Reduced motion preference

- **WHEN** the browser reports `prefers-reduced-motion: reduce`
- **THEN** the login page removes entry, hover, and progress motion while keeping the brand, form, content, focus behavior, and state changes visible and understandable

#### Scenario: Submit interaction feedback

- **WHEN** a guest submits the login form
- **THEN** the form exposes `aria-busy="true"`, the primary button becomes disabled, its label changes to “Signing in…”, a live status region announces progress, and repeated submissions are prevented until the browser receives the response

#### Scenario: Failed submission restores recovery controls

- **WHEN** the server returns the login page with validation or rate-limit errors
- **THEN** the page renders the normal enabled submit control, preserves the safe username value and campaign context, exposes the relevant error messages, and leaves the guest able to correct and resubmit the form
