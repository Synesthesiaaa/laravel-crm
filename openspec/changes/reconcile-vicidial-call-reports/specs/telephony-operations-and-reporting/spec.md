## ADDED Requirements

### Requirement: Consistent VICIdial analytical reporting

The system SHALL use the unfiltered VICIdial call-status totals for the selected server, campaigns and dates as the headline attempts, answers, and hourly volume. It SHALL show a separately named disposition subtotal and honor explicit disposition scope choices. Today SHALL use the same analytical computation as Historical with both dates set to the current date in the report timezone.

#### Scenario: System dispositions are hidden

- **WHEN** the historical report uses a disposition filter
- **THEN** total attempts, answered attempts, answer rate, and hourly volume stay based on the full filtered-by-campaign and date call-status source
- **AND** the selected disposition population is shown separately

#### Scenario: All dispositions are explicitly requested

- **WHEN** the global default hides system dispositions and the user requests all dispositions
- **THEN** the report uses and displays the requested all-dispositions scope

#### Scenario: An open reporting day is requested

- **WHEN** Today and Historical request the same open reporting day
- **THEN** they use the same date, timezone, mapping, and analytical definitions
- **AND** each response identifies its fetch time and marks its open-day counts provisional

#### Scenario: VICIdial totals are unavailable

- **WHEN** the VICIdial call-status feed fails or contains contradictory or partial counts
- **THEN** the affected headline figures are unavailable
- **AND** CRM session counts cannot replace them

### Requirement: Reported answers and independently supported outcome rates

The analytical report SHALL compute Answer Rate from the VICIdial answered attempt count and its corresponding total attempts. Contact Rate and Abandoned Rate SHALL require verified source evidence for their respective official definitions.

#### Scenario: A disposition report includes ANA or non-system outcomes

- **WHEN** an outcome is available solely as a parsed disposition
- **THEN** the disposition may appear in the breakdown
- **AND** the system does not infer an official contact or abandonment rate from that disposition alone

### Requirement: Native report reconciliation before campaign approval

The system SHALL expose campaign-level reconciliation approval and support comparing a CRM analytical snapshot with an independent, equivalently scoped native export.

#### Scenario: The native export differs from the CRM snapshot

- **WHEN** an operator compares captured report data from the same date range, campaign scope, timezone, and disposition scope
- **THEN** the reconciliation command identifies missing and mismatched counts and returns a failing status

#### Scenario: The campaign has no reconciliation approval

- **WHEN** Today or Historical is requested for a campaign awaiting Operations approval
- **THEN** the report indicates that native reconciliation is pending

## MODIFIED Requirements

### Requirement: Historical report summary and comparison
The historical reporting service SHALL calculate total calls, answered calls, answer rate, a distinct disposition subtotal, average talk time when available, agents with activity, and calls per agent for the selected CRM campaign and effective local date range using parsed VICIdial raw totals. When comparison is enabled, it SHALL run the same scoped pipeline for the correctly bounded comparison period and show rate changes in percentage points. Each metric SHALL distinguish confirmed zero from empty, unsupported, parse failure, and transport or permission failure; unavailable metrics SHALL remain null in the API and render as `Unavailable` or `â€”`.

#### Scenario: Previous period is selected
- **WHEN** the selected range is Aug 20 through Aug 26 and comparison mode is previous period
- **THEN** the comparison range is Aug 13 through Aug 19
- **AND** each comparable rate shows a percentage-point difference rather than an incorrectly labelled relative percentage change

#### Scenario: No comparison is selected
- **WHEN** comparison mode is disabled
- **THEN** the report omits comparison indicators and still returns the selected-period summary

#### Scenario: A confirmed zero is returned
- **WHEN** VICIdial returns a valid, parseable report for the selected scope with a numeric total of zero
- **THEN** the API marks that metric as confirmed zero and returns numeric zero
- **AND** the UI displays `0` rather than `Unavailable`

#### Scenario: A report is empty or unavailable
- **WHEN** VICIdial returns a recognized empty result, unsupported layout, malformed metric, or transport/permission failure
- **THEN** the affected metric remains null and carries the corresponding availability state
- **AND** the UI displays `Unavailable` or `â€”` rather than silently displaying zero

### Requirement: Historical campaign, disposition, and funnel analysis
The historical report SHALL return campaign comparison rows, a descending disposition Pareto dataset with optional `Other` grouping, the disposition subtotal, disposition table rows, and a call funnel only for stages supported by configured disposition mappings. System disposition scope filters SHALL apply consistently to the disposition subtotal, disposition analysis, status breakdown totals, and campaign top-status values. Changing the disposition scope in the Reports UI SHALL refresh the historical dashboard with the selected scope. All rows and totals SHALL be limited to the selected CRM campaign's enabled mapped VICIdial campaigns, with campaign and disposition codes normalized case-insensitively. Authoritative total-call, answered-call, and hourly-volume metrics SHALL remain based on raw call-status totals and SHALL not be reduced merely because a disposition scope is selected.

#### Scenario: Multiple campaigns have activity
- **WHEN** a date range includes activity from multiple selected VICIdial campaigns
- **THEN** campaign data is aggregated by stable campaign code
- **AND** explicitly confirmed zero counts remain distinguishable from missing campaign data

#### Scenario: Disposition mappings are unavailable
- **WHEN** configured disposition classifications do not define a reliable funnel stage
- **THEN** the unavailable funnel stage is omitted
- **AND** the report does not infer conversion or success from a raw status name

#### Scenario: Disposition rows contain case variants
- **WHEN** VICIdial returns the same disposition code or campaign code with different casing across rows
- **THEN** aggregation uses a normalized key while preserving a display label
- **AND** Pareto ordering, the disposition subtotal, and table rows use summed raw counts

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
