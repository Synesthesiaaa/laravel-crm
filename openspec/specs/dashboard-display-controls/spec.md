## Purpose

Provide campaign-specific dashboard amount visibility and explicit, accessible dialog interactions.

## Requirements

### Requirement: Campaign amount visibility is administrator configurable
Administrators MUST be able to enable or disable all dashboard monetary displays per campaign, with independent controls for total amount, amount change, monetary chart mode, and monetary tables/columns. Existing campaigns MUST default to enabled. Omitted settings MUST retain saved values, and invalid settings MUST be rejected. Visibility MUST NOT change sales counts or attribution.

#### Scenario: Administrator disables all amounts
- **WHEN** an administrator disables amounts for one campaign
- **THEN** its dashboard omits monetary cards, subtitles, chart options, and table columns/report tables while retaining counts
- **AND** other campaigns retain their settings

#### Scenario: Individual display control
- **WHEN** an administrator disables just the amount change card
- **THEN** the amount change card is omitted and other enabled monetary displays remain

#### Scenario: Unauthorized update
- **WHEN** a non-administrator submits amount visibility settings
- **THEN** the request is rejected without changing settings

### Requirement: Dashboard dialogs require explicit activation
Sales and Top Agent launchers MUST be keyboard-accessible buttons that open only on click, Enter, or Space. Close, Escape, and backdrop actions MUST dismiss dialogs and release scroll locking without reopening on focus return.

#### Scenario: Hover and focus
- **WHEN** a user hovers or tabs to either launcher
- **THEN** no dialog opens

#### Scenario: Open and close
- **WHEN** a user activates a launcher then dismisses the dialog
- **THEN** focus returns to the launcher, the dialog stays closed, and page scrolling works
