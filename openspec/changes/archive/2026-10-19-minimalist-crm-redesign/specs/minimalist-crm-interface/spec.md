## ADDED Requirements

### Requirement: Quiet shared presentation
The CRM SHALL use a neutral, flat visual system for authenticated screens and sign-in, preserving branding, semantic status colors, labels, and operational behavior.

#### Scenario: Open an authenticated screen
- **WHEN** a user opens a dashboard, records, reports, attendance, forms, or administration screen
- **THEN** shared navigation, controls, tables, and panels use the consistent minimalist presentation
- **AND** campaign and role restrictions remain enforced

### Requirement: Theme preference continuity
The interface SHALL default to light mode when no valid theme preference exists and SHALL respect a saved light or dark preference.

#### Scenario: First visit
- **WHEN** local storage has no valid theme preference
- **THEN** sign-in and authenticated screens initialize in light mode

#### Scenario: Returning dark mode user
- **WHEN** local storage contains the dark preference
- **THEN** the interface initializes in dark mode and allows switching back to light

### Requirement: Discoverable navigation and search
The shell SHALL expose global search at all viewport sizes, show the current campaign, and preserve route-aware active links and authorized destinations.

#### Scenario: Search on a narrow screen
- **WHEN** a user opens the CRM at 375px wide
- **THEN** a labelled global search button is available and opens the existing search dialog

#### Scenario: Desktop collapse followed by mobile navigation
- **WHEN** desktop navigation is collapsed and the viewport becomes mobile
- **THEN** opening the mobile drawer displays full navigation labels and usable section disclosures
- **AND** Escape closes the drawer and restores keyboard access to the page
- **AND** toggling a mobile section preserves the saved desktop collapse preference

#### Scenario: Search with a keyboard
- **WHEN** the user opens search, types a query, and presses an arrow key
- **THEN** input receives focus on open and arrow keys focus real result links for native Enter activation
- **AND** a stale response cannot replace newer results

#### Scenario: Search service failure
- **WHEN** a search request fails
- **THEN** the interface shows an unavailable message and a retry action instead of reporting no matches

### Requirement: Accessible responsive work surfaces
The interface SHALL maintain keyboard focus indicators, at least 44px primary touch controls, reduced motion support, and contained wide tables.

#### Scenario: Narrow viewport and keyboard use
- **WHEN** the user navigates at 375px or with a keyboard
- **THEN** primary actions remain reachable, the document does not overflow horizontally, and focus is visibly indicated
