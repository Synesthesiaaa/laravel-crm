## ADDED Requirements

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
