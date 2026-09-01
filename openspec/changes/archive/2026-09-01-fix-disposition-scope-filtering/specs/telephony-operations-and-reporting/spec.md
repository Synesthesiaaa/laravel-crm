## MODIFIED Requirements

### Requirement: Historical campaign, disposition, and funnel analysis
The historical report SHALL return campaign comparison rows, a descending disposition Pareto dataset with optional `Other` grouping, report totals, contact rate, disposition table rows, and a call funnel only for stages supported by configured disposition mappings. System disposition scope filters SHALL apply consistently to report totals, disposition analysis, status breakdown totals, and campaign top-status values. Changing the disposition scope in the Reports UI SHALL refresh the historical dashboard with the selected scope. All rows and totals SHALL be limited to the selected CRM campaign's enabled mapped VICIdial campaigns, with campaign and disposition codes normalized case-insensitively. Authoritative total-call, answered-call, and hourly-volume metrics SHALL remain based on raw call-status totals and SHALL not be reduced merely because a disposition scope is selected.

#### Scenario: Multiple campaigns have activity
- **WHEN** a date range includes activity from multiple selected VICIdial campaigns
- **THEN** campaign comparison rows are aggregated by stable campaign code and sorted by total calls
- **AND** zero-activity campaigns are hidden by default unless explicitly requested

#### Scenario: Disposition mappings are unavailable
- **WHEN** configured disposition classifications do not define a reliable funnel stage
- **THEN** the unavailable funnel stage is omitted
- **AND** the report does not infer conversion or success from a raw status name

#### Scenario: Disposition rows contain case variants
- **WHEN** VICIdial returns the same disposition code or campaign code with different casing across rows
- **THEN** aggregation uses a normalized key while preserving a display label
- **AND** Pareto ordering, report totals, contact rate, and table rows use summed raw counts

#### Scenario: VICIdial totals are excluded from disposition analysis
- **WHEN** the disposition export includes a `TOTAL`, `TOTAL CALLS`, or equivalent aggregate row or column
- **THEN** the aggregate is excluded from disposition codes, Pareto values, and table breakdowns
- **AND** the disposition total equals the sum of the real disposition counts without double-counting

#### Scenario: All dispositions are selected
- **WHEN** the user selects `All dispositions` and the historical dashboard refreshes
- **THEN** configured system and non-system disposition codes appear in the disposition and status breakdowns
- **AND** total calls and answered calls remain the raw call-status totals

#### Scenario: System dispositions are hidden
- **WHEN** the user selects `Hide system dispositions` and the historical dashboard refreshes
- **THEN** configured system codes are absent from the Pareto data, disposition rows, status totals, funnel inputs, and campaign top-status values
- **AND** non-system codes remain available

#### Scenario: Only system dispositions are selected
- **WHEN** the user selects `System dispositions only` and the historical dashboard refreshes
- **THEN** only configured system codes appear in the Pareto data, disposition rows, status totals, funnel inputs, and campaign top-status values
- **AND** non-system codes are absent

#### Scenario: Scope selection refreshes the dashboard
- **WHEN** the disposition scope control changes on the historical Reports page
- **THEN** the dashboard requests the selected `disposition_scope` without requiring a separate manual refresh action
