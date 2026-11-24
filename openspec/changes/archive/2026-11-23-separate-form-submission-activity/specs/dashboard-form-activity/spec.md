## ADDED Requirements

### Requirement: Campaign dashboard shows submission activity for the selected form
The campaign dashboard SHALL offer its configured forms in a form selector when the Activity section is enabled. A period selector SHALL offer the last 24 hours, the current week, and the current month using the existing dashboard time boundaries. The chart and expandable submission counts table SHALL reflect only the selected form and period and provide matching bucket counts.

#### Scenario: Switch between forms and periods
- **WHEN** a user changes either selector
- **THEN** the chart and table update to the selected form and period
- **AND** the form selector contains only forms in the active campaign

#### Scenario: The selection has no submissions
- **WHEN** the selected form has no submissions in the selected period
- **THEN** the dashboard shows an empty chart state and provides zero counts for the available buckets

#### Scenario: The chart library is unavailable
- **WHEN** the selected form has activity but the chart cannot render
- **THEN** the dashboard explains that the chart is unavailable and the expandable table still shows the selected counts

#### Scenario: The dashboard refreshes
- **WHEN** the dashboard refreshes for the same campaign and the selected form remains available
- **THEN** the dashboard retains the selected form, period, and table disclosure state with refreshed counts

#### Scenario: The campaign has no forms
- **WHEN** the Activity section is enabled for a campaign without configured forms
- **THEN** the dashboard displays a no-forms message
