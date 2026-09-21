## MODIFIED Requirements

### Requirement: Marked values contribute to dashboard sale amounts
The system SHALL persist capture timestamps for new form submissions and sum all non-empty numeric marked-field values on each qualifying submission into the dashboard sale amount for that submission's agent and the rolling sales KPI. Sales and every Top Agent ranking path SHALL use the configured rolling sales window, which defaults to 24 hours, while Calls MAY retain its configured KPI window for non-dashboard consumers. The agent leaderboard SHALL use its month-to-date date range. The dashboard SHALL expose the rolling sales count and total amount, show the Sales and Top Agent cards, and SHALL NOT render a Calls KPI card. When marked fields are configured, the rolling Top Agent KPI SHALL rank by qualifying marked sales and expose that agent's qualifying sale count and total value.

#### Scenario: Sum multiple sale amounts on one submission
- **WHEN** a submission contains `100.00` and `25.50` in two marked fields
- **THEN** the submission contributes one sale and `125.50` to its agent's sale amount

#### Scenario: Exclude unmarked fields
- **WHEN** a submission contains values in both marked and unmarked numeric fields
- **THEN** only marked fields contribute to its sale amount

#### Scenario: Campaigns without marked fields use the existing fallback
- **WHEN** a campaign has no active marked sale-amount fields
- **THEN** dashboard sales continue to use the existing sale-disposition and lead-data amount behavior

#### Scenario: New marked submission is included in rolling metrics
- **WHEN** a user saves a form submission with a numeric marked sale amount
- **THEN** the stored row has capture timestamps and its amount is eligible for the rolling Sales and Top Agent metrics

#### Scenario: Rolling KPI exposes count and total amount
- **WHEN** qualifying sales occur within the configured rolling KPI window
- **THEN** the dashboard Sales card shows the number of sales and the summed sale amount for that same window

#### Scenario: Rolling Sales KPI uses the last 24 hours by default
- **WHEN** a qualifying sale occurs within the previous 24 hours and another occurs earlier than 24 hours ago
- **THEN** only the recent sale contributes to the Sales count, total value, and Top Agent metrics

#### Scenario: Fallback Top Agent uses the sales window
- **WHEN** a campaign has no marked sale-amount fields and an agent has qualifying disposition activity within the previous 24 hours but outside the Calls window
- **THEN** the 24-hour Top Agent card reflects that agent

#### Scenario: Calls retain their configured KPI window
- **WHEN** a call occurs within the configured call KPI window but outside the sales window
- **THEN** it contributes to the Calls KPI data but does not create a Sales or Top Agent sale result

#### Scenario: Calls card is not rendered
- **WHEN** an authenticated user opens the dashboard
- **THEN** the KPI-card row does not contain a Calls card

#### Scenario: Top Agent reflects qualifying marked sales
- **WHEN** agents have different numbers of qualifying marked sales within the rolling KPI window
- **THEN** the Top Agent card identifies the agent with the highest qualifying sale count and shows that agent's count and summed sale value
