# Admin Dashboard Layout Specification

## Purpose

Allow administrators to publish a campaign-specific arrangement of the user dashboard.

## Requirements

### Requirement: Admins can publish dashboard section visibility and order

The dashboard administration screen MUST allow Admin and Super Admin users to select any active campaign and configure the visibility and order of its existing user dashboard sections without first changing the application's active campaign. A valid save MUST persist the selected campaign's complete normalized dashboard configuration atomically.

#### Scenario: Admin applies a valid layout

- **WHEN** an Admin or Super Admin submits a layout containing a selected active campaign, known sections, and a valid order
- **THEN** the normalized layout is saved for the selected campaign and a success response is returned

#### Scenario: Admin switches configuration campaign

- **WHEN** an Admin or Super Admin selects a different campaign on the dashboard administration screen
- **THEN** the screen loads that campaign's saved layout and sales configuration without changing the campaign stored in the administrator's application session

#### Scenario: Campaign configurations remain isolated

- **WHEN** an administrator saves different section visibility, order, or sales settings for two campaigns
- **THEN** each campaign retains and serves only its own dashboard configuration

#### Scenario: Non-admin attempts to apply a layout

- **WHEN** a user without Admin or Super Admin role submits the layout endpoint
- **THEN** authorization fails and the stored campaign layout remains unchanged

#### Scenario: Invalid section data is submitted

- **WHEN** the layout contains an unknown campaign, unknown sections, duplicate sections, missing section keys, or invalid nested sales data
- **THEN** validation or normalization rejects the invalid data without saving a partial layout

### Requirement: Users receive the published campaign layout

The user dashboard MUST render the saved normalized layout for the user's active campaign, falling back to the current default order and visibility when no layout has been published.

#### Scenario: User opens a campaign with a saved layout

- **WHEN** an authenticated user opens the dashboard for a campaign with an applied layout
- **THEN** visible sections render in the administrator's published order

#### Scenario: User opens a campaign without a saved layout

- **WHEN** an authenticated user opens the dashboard for a campaign with no saved layout
- **THEN** all existing default sections render in the current default order

#### Scenario: An admin publishes while a user dashboard is open

- **WHEN** a layout is applied for the campaign currently viewed by a user
- **THEN** the user's dashboard soft-refreshes and reflects the new layout without a manual browser reload

### Requirement: Super Admins can choose the campaign submission trends layout
The campaign dashboard layout editor SHALL let Super Admins choose the all-forms layout or the one-form selector for an active campaign. Saving an unrelated dashboard setting SHALL preserve the campaign's previously published activity layout. Campaigns without a saved mode SHALL default to the all-forms layout.

#### Scenario: A Super Admin publishes a submission trends layout
- **WHEN** a Super Admin saves a supported activity mode for the campaign selected in the editor
- **THEN** users of that campaign receive the chosen layout and other campaigns keep their existing layouts

#### Scenario: An Admin attempts to change the activity layout
- **WHEN** an Admin submits an activity mode with a dashboard layout update
- **THEN** authorization fails and the stored layout remains intact

#### Scenario: A layout change omits the activity setting
- **WHEN** an authorized user saves a layout without the activity mode field
- **THEN** the existing activity mode is retained
