## MODIFIED Requirements

### Requirement: Palette-aligned login composition

The guest login page SHALL present a professional brand-led authentication composition within the CRM's established visual system. On larger viewports it SHALL expose the configured brand and campaign-aware workspace context beside the auth sheet; on smaller viewports it SHALL collapse that context above the form. The page SHALL use the CRM's shared surface, text, border, elevation, and Signal Magenta tokens without turning context into a competing task or introducing unverified marketing claims.

#### Scenario: Dark theme entry

- **WHEN** a guest opens the login route with the dark theme active
- **THEN** the page renders a calm charcoal atmosphere with a clear brand/context area and auth sheet, readable primary and muted text, and Signal Magenta reserved for the primary action, focus, and meaningful emphasis

#### Scenario: Light theme entry

- **WHEN** a guest switches the login page to the light theme
- **THEN** the page rebinds its surfaces, borders, text, elevation, and atmosphere to the existing light-theme tokens without introducing a separate hard-coded palette

#### Scenario: Configured brand remains visible

- **WHEN** the login page receives a configured company name and logo or the safe fallback brand
- **THEN** the brand remains visible in the entry composition, preserves its accessible alternative text, and long names wrap within the context area without creating horizontal overflow

### Requirement: Login task hierarchy and form semantics

The login page SHALL make the credential task, optional campaign context, feedback state, and primary submit action visually and semantically distinct without changing the existing login request fields or route.

#### Scenario: Credential form is ready for use

- **WHEN** a guest opens the login page
- **THEN** the page exposes a named main landmark, a connected heading, visible labels for username and password, a clear form heading/supporting description, an optional campaign selector when campaigns exist, and a full-width sign-in action in logical tab order

#### Scenario: Campaign selection is available

- **WHEN** the application provides one or more campaigns
- **THEN** the campaign selector displays the current/old selection using the existing campaign values, is visibly grouped as workspace context, and remains a native keyboard- and touch-operable control

#### Scenario: No campaigns are available

- **WHEN** the application provides no campaigns
- **THEN** the credential fields and submit action remain usable and the page does not render an empty or misleading campaign control

#### Scenario: Validation or status feedback is present

- **WHEN** the session contains a login status message or validation error
- **THEN** the message is rendered in a readable semantic feedback region within the auth sheet without obscuring the fields or moving the submit action off-screen at supported widths

### Requirement: Responsive and accessible interaction states

The login page SHALL remain usable at mobile, tablet, and desktop widths and SHALL provide visible focus, adequate touch targets, and a reduced-motion alternative for all login-specific interactions.

#### Scenario: Narrow viewport

- **WHEN** a guest uses the page at a narrow mobile viewport
- **THEN** the brand/context area stays compact above the form, the form fits the viewport with safe gutters, controls remain at least 44 CSS pixels tall, and no page-level horizontal scrolling is introduced

#### Scenario: Keyboard focus

- **WHEN** a guest navigates the theme toggle, fields, campaign selector, and submit button with a keyboard
- **THEN** focus moves in a logical order and each focused control has a visible token-based focus indicator

#### Scenario: Reduced motion preference

- **WHEN** the browser reports `prefers-reduced-motion: reduce`
- **THEN** the login page removes entry and hover motion while keeping the brand, form, content, focus behavior, and state changes visible and understandable

#### Scenario: Submit interaction feedback

- **WHEN** a guest hovers, focuses, presses, or disables the sign-in action
- **THEN** the control changes through clear color, border, shadow, or active-state feedback without relying on a decorative sheen or movement-only cue
