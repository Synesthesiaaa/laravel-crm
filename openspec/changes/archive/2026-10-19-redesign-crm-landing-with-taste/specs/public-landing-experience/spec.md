## ADDED Requirements

### Requirement: Clearly illustrative product presentation

The landing page SHALL present a concise product headline, ordered call workflow, role-scoped capabilities, and a clearly labeled illustrative agent workspace without exposing real customer information or interactive simulated call controls.

#### Scenario: Visitor views the workspace illustration

- **WHEN** the visitor reaches the hero preview
- **THEN** visible text identifies the preview as illustrative
- **AND** an accessible caption explains the lead, call, and follow-up workflow
- **AND** the illustration does not expose live customer data or focusable call controls

### Requirement: Persistent accessible theme selection

The public landing page SHALL retain light or dark theme selection in the existing browser theme preference and provide an accessible theme control.

#### Scenario: Visitor changes theme

- **WHEN** the visitor activates the theme control
- **THEN** the complete page changes theme and the control's accessible label and pressed state reflect the selected theme
- **AND** the preference persists after reload

#### Scenario: Stored theme is unavailable or invalid

- **WHEN** browser storage is unavailable or contains a value other than light or dark
- **THEN** the landing page remains usable with the dark fallback

#### Scenario: Visitor requests reduced motion

- **WHEN** reduced motion is enabled
- **THEN** the page disables smooth scrolling and decorative transitions while all content and navigation remain usable
