## MODIFIED Requirements

### Requirement: Marked values contribute to dashboard sale amounts
The system SHALL sum all non-empty numeric marked-field values on each qualifying submission and include the sum in dashboard sale amounts for its agent and in the rolling sales KPI. The rolling KPI SHALL use the configured hour window, and the agent leaderboard SHALL use its month-to-date date range. The dashboard SHALL expose both the qualifying sales count and the rolling total amount.

#### Scenario: Sum multiple sale amounts on one submission
- **WHEN** a submission contains `100.00` and `25.50` in two marked fields
- **THEN** the submission contributes one sale and `125.50` to its agent's sale amount

#### Scenario: Exclude unmarked fields
- **WHEN** a submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: Campaigns without marked fields use the existing fallback
- **WHEN** a campaign has no active marked sale-amount fields
- **THEN** dashboard sales continue to use the existing sale-disposition and lead-data amount behavior

#### Scenario: Rolling KPI exposes count and total amount
- **WHEN** qualifying sales occur within the configured rolling KPI window
- **THEN** the dashboard Sales card shows the number of sales and the summed sale amount for that same window
