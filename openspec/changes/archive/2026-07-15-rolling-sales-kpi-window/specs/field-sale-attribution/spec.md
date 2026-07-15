## MODIFIED Requirements

### Requirement: Marked values contribute to dashboard sale amounts
The system SHALL sum all non-empty numeric marked-field values on each qualifying submission and include the sum in dashboard sale amounts for its agent and in the rolling sales KPI. Sales and Top Agent metrics SHALL use the configured rolling sales window, which defaults to 24 hours, while Calls SHALL retain the configured call KPI window. The agent leaderboard SHALL use its month-to-date date range. The dashboard SHALL expose both the qualifying sales count and the rolling total amount. When marked fields are configured, the rolling Top Agent KPI SHALL rank by qualifying marked sales and expose that agent's qualifying sale count and total value.

#### Scenario: Sum multiple sale amounts on one submission
- **WHEN** a submission contains `100.00` and `25.50` in two marked fields
- **THEN** the submission contributes one sale and `125.50` to its agent's sale amount

#### Scenario: Exclude unmarked fields
- **WHEN** a submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: Campaigns without marked fields use the existing fallback
- **WHEN** a campaign has no active marked sale-amount fields
- **THEN** dashboard sales continue to use the existing sale-disposition and lead-data amount behavior

#### Scenario: Rolling Sales KPI uses the last 24 hours by default
- **WHEN** a qualifying sale occurs within the previous 24 hours and another occurs earlier than 24 hours ago
- **THEN** only the recent sale contributes to the Sales count, total value, and Top Agent metrics

#### Scenario: Calls retain their configured KPI window
- **WHEN** a call occurs within the configured call KPI window but outside the sales window
- **THEN** it contributes to Calls but not to Sales or Top Agent

#### Scenario: Top Agent reflects qualifying marked sales
- **WHEN** agents have different numbers of qualifying marked sales within the rolling sales window
- **THEN** the Top Agent card identifies the agent with the highest qualifying sale count and shows that agent's count and summed sale value
