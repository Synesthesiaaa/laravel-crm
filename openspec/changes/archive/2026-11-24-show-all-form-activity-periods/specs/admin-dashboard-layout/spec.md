## ADDED Requirements

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
