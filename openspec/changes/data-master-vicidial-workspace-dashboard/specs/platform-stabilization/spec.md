## MODIFIED Requirements

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
