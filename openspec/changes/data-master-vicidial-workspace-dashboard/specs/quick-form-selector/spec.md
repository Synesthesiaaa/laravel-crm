## ADDED Requirements

### Requirement: Quick Form exposes active forms for the current campaign

The authenticated Quick Form widget MUST load a normalized list of active forms for the current campaign and MUST select the currently displayed form by default when the widget is rendered on a form page.

#### Scenario: User opens the widget on a form page

- **WHEN** the Quick Form widget loads on a form page with a current campaign
- **THEN** the selector SHALL show the active forms returned for that campaign and SHALL select the displayed form without reloading the parent page

#### Scenario: Form metadata is unavailable

- **WHEN** the form-options request fails or returns no usable options
- **THEN** the widget SHALL keep the current iframe source when one exists and SHALL not offer an invalid form selection

### Requirement: Selecting a Quick Form preserves the shared workspace

Selecting a form in the Quick Form widget MUST update only the Quick Form iframe source and MUST preserve the Vicidial iframe, active session, parent URL, and split-workspace state.

#### Scenario: User changes forms in split view

- **WHEN** a user selects another active form while Quick Form and Vicidial are in split view
- **THEN** the Quick Form iframe SHALL load the selected form while the Vicidial iframe remains mounted and the split view remains active

#### Scenario: User selects an unavailable form

- **WHEN** a selection value is not present in the loaded active-form options
- **THEN** the widget SHALL ignore the value and SHALL leave the current Quick Form iframe unchanged
