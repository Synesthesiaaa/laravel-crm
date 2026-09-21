## MODIFIED Requirements

### Requirement: Campaign-scoped daily report data
The dashboard service SHALL provide daily and month-to-date report data for the currently selected campaign, using only that campaign's active, allow-listed form tables. Sales-derived counts and amounts SHALL use the campaign's current legacy or custom sales attribution mode so report values remain consistent with other sales-derived dashboard results.

#### Scenario: Daily rows aggregate qualifying forms by agent
- **WHEN** the selected campaign has qualifying legacy or custom sales for the current business date
- **THEN** the service returns one row per agent with each form's qualifying sale count, sale amount total, and row totals

#### Scenario: Month-to-date rows aggregate qualifying sales from the first day of the month
- **WHEN** the selected campaign has qualifying legacy or custom sales between the first day of the current month and today
- **THEN** the service returns one row per agent with month-to-date qualifying counts, per-form amounts, and sale amount totals

#### Scenario: Report and selected-range metrics share attribution rules
- **WHEN** a campaign uses custom tag rules
- **THEN** the report and the selected-range Sales, Top Agent, leaderboard, and per-form breakdown include only submissions qualifying under those rules

#### Scenario: Campaigns with no valid forms are safe
- **WHEN** no active form table or valid sales rule for the selected campaign exists or is queryable
- **THEN** the service returns empty report rows and zero totals without querying an arbitrary table
