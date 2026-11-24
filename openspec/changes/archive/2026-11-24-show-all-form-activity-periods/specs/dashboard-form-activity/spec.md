## ADDED Requirements

### Requirement: Campaign dashboard displays activity for all configured forms and periods
When the Activity section is enabled, the campaign dashboard SHALL display the last 24 hours, weekly, and monthly submission activity together for each active form in the current campaign by default. It SHALL support the published one-form selector layout and give each chart a matching expandable submission counts table.

#### Scenario: Dashboard opens in the all-forms layout
- **WHEN** a user opens the campaign dashboard with the all-forms layout
- **THEN** each active form has its own last-24-hours, weekly, and monthly chart and submission counts table

#### Scenario: Dashboard opens in the one-form layout
- **WHEN** the campaign is configured for the one-form layout
- **THEN** selecting a form and period displays its chart and matching table

#### Scenario: A period has no submissions
- **WHEN** a form has no submissions in a displayed period
- **THEN** its panel displays an empty state with zero counts for the available buckets

#### Scenario: The chart library is unavailable
- **WHEN** a chart with submission activity cannot render
- **THEN** its expandable table continues to show the underlying counts

#### Scenario: The campaign has no forms
- **WHEN** the Activity section is enabled for a campaign without active forms
- **THEN** the dashboard displays a no-forms message

### Requirement: Dashboard users can browse weekly and monthly activity
The dashboard SHALL let users browse previous weeks and months independently in both activity layouts. Weekly periods SHALL use Monday through Sunday and monthly periods SHALL use calendar months in the application timezone. Current periods SHALL show data through today. The selection SHALL remain in the dashboard URL across refreshes. The last 24 hours SHALL always reflect recent activity.

#### Scenario: A user changes the calendar period
- **WHEN** the user browses to an earlier week or month
- **THEN** every displayed chart for that period uses the selected dates with matching counts
- **AND** the other calendar period and current 24-hour counts keep their own ranges

#### Scenario: A dashboard refreshes in place
- **WHEN** activity refreshes while the user is browsing a campaign dashboard
- **THEN** the calendar periods and any available form selection and table disclosures are retained

#### Scenario: A malformed or future calendar selection is requested
- **WHEN** a calendar parameter is malformed or after the current date
- **THEN** that calendar period falls back to its current period

## REMOVED Requirements

### Requirement: Campaign dashboard shows submission activity for the selected form
**Reason**: Both the default all-forms layout and the optional single-form layout use the new activity requirements.
**Migration**: Campaigns can choose the one-form selector from the dashboard layout editor.
