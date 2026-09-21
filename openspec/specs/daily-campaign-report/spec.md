# daily-campaign-report Specification

## Purpose
TBD - created by archiving change add-daily-campaign-report. Update Purpose after archive.
## Requirements
### Requirement: Campaign-scoped daily report data
The dashboard service SHALL provide daily and month-to-date report data for the currently selected campaign, using only that campaign's active, allow-listed form tables. Sales-derived counts and amounts SHALL use the campaign's current legacy or custom sales attribution mode so report values remain consistent with other sales-derived dashboard results.

#### Scenario: Daily rows aggregate configured forms by agent
- **WHEN** the selected campaign has qualifying legacy or custom sales for the current business date
- **THEN** the service returns one row per agent with each form's qualifying sale count, sale amount total, and row totals

#### Scenario: Month-to-date rows aggregate from the first day of the month
- **WHEN** the selected campaign has qualifying legacy or custom sales between the first day of the current month and today
- **THEN** the service returns one row per agent with month-to-date qualifying counts, per-form amounts, and sale amount totals

#### Scenario: Report and selected-range metrics share attribution rules

- **WHEN** a campaign uses custom tag rules
- **THEN** the report and the selected-range Sales, Top Agent, leaderboard, and per-form breakdown include only submissions qualifying under those rules

#### Scenario: Campaigns with no valid forms are safe
- **WHEN** no active form table or valid sales rule for the selected campaign exists or is queryable
- **THEN** the service returns empty report rows and zero totals without querying an arbitrary table

### Requirement: Dashboard report presentation
The dashboard SHALL render the campaign report using the existing dashboard theme and SHALL omit the legacy “MPI Cards” label.

#### Scenario: Report renders all four operational views
- **WHEN** the dashboard has report data
- **THEN** it displays daily amounts, daily counts, month-to-date accounts, and month-to-date submitted amounts in themed responsive tables

#### Scenario: Dynamic form columns
- **WHEN** the selected campaign has a different set of configured forms
- **THEN** table headers and cells use those form names dynamically and do not show fixed MBSales-only columns

#### Scenario: Empty report state
- **WHEN** the selected campaign has no submissions for the report periods
- **THEN** each report view shows a readable themed empty state and zero totals rather than an error
