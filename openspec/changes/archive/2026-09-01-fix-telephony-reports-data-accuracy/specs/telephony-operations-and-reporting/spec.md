## MODIFIED Requirements

### Requirement: Historical report summary and comparison
The historical reporting service SHALL calculate total calls, answered calls, answer rate, contact rate when configured, average talk time when available, agents with activity, and calls per agent for the selected CRM campaign and local date range using parsed VICIdial raw totals. When comparison is enabled, it SHALL run the same scoped pipeline for the correctly bounded comparison period and show rate changes in percentage points. Each metric SHALL distinguish confirmed zero from empty, unsupported, parse failure, and transport or permission failure; unavailable metrics SHALL be null in the API and rendered as `Unavailable` or `—`.

#### Scenario: Previous period is selected
- **WHEN** the selected range is Aug 20 through Aug 26 and comparison mode is previous period
- **THEN** the comparison range is Aug 13 through Aug 19
- **AND** the comparison request uses the same enabled mapped VICIdial campaign scope and effective report timezone
- **AND** each comparable rate shows a percentage-point difference rather than an incorrectly labelled relative percentage change

#### Scenario: A confirmed zero is returned
- **WHEN** VICIdial returns a valid, parseable report for the selected scope with a numeric total of zero
- **THEN** the API marks that metric as confirmed zero and returns numeric zero
- **AND** the UI displays `0` rather than `Unavailable`

#### Scenario: A report is empty or unavailable
- **WHEN** VICIdial returns a recognized empty result, unsupported layout, malformed metric, or transport/permission failure
- **THEN** the affected metric remains null and carries the corresponding availability state
- **AND** the UI displays `Unavailable` or `—` rather than silently displaying zero

#### Scenario: No comparison is selected
- **WHEN** comparison mode is disabled
- **THEN** the report omits comparison indicators and still returns the selected-period summary and metric states

### Requirement: Historical campaign, disposition, and funnel analysis
The historical report SHALL return campaign comparison rows, a descending disposition Pareto dataset with optional `Other` grouping, report totals, contact rate, disposition table rows, and a call funnel only for stages supported by configured disposition mappings. System disposition scope filters SHALL apply consistently to report totals and disposition analysis. All rows and totals SHALL be limited to the selected CRM campaign's enabled mapped VICIdial campaigns, with campaign codes normalized case-insensitively.

#### Scenario: Multiple campaigns have activity
- **WHEN** a date range includes activity from multiple selected VICIdial campaigns
- **THEN** campaign comparison rows are aggregated by stable normalized campaign code and sorted by total calls
- **AND** hourly, status, disposition, and report totals include every mapped campaign exactly once
- **AND** zero-activity campaigns are hidden by default unless explicitly requested

#### Scenario: Disposition mappings are unavailable
- **WHEN** configured disposition classifications do not define a reliable funnel stage
- **THEN** the unavailable funnel stage is omitted
- **AND** the report does not infer conversion or success from a raw status name

#### Scenario: Disposition rows contain case variants
- **WHEN** VICIdial returns the same disposition code or campaign code with different casing across rows
- **THEN** aggregation uses a normalized key while preserving a display label
- **AND** Pareto ordering, report totals, contact rate, and table rows use the summed raw counts

#### Scenario: VICIdial totals are excluded from disposition analysis
- **WHEN** the disposition export includes a `TOTAL`, `TOTAL CALLS`, or equivalent aggregate row or column
- **THEN** the aggregate is excluded from disposition codes, Pareto values, and table breakdowns
- **AND** the disposition total equals the sum of the real disposition counts without double-counting

### Requirement: Historical agent aggregation
The historical reporting service SHALL aggregate duplicate agent export/session rows by stable normalized VICIdial agent identifier and SHALL return agent calls, answered calls, contact rate when available, average talk time, total talk time, pause percentage when available, and supported ready/other time values. Display names SHALL NOT be used as the deduplication key. Each time metric SHALL remain unavailable when its source field is absent or unparseable, rather than being filled with zero.

#### Scenario: Agent has duplicate session rows
- **WHEN** the same stable VICIdial username appears in multiple sessions or campaigns in the selected range
- **THEN** the primary Agent Performance result contains one aggregated row for that username
- **AND** the totals are summed without merging a different username with a similar display name

#### Scenario: Ready or other time is supplied
- **WHEN** a recognized VICIdial export column supplies ready or other duration
- **THEN** the service parses and aggregates that duration across mapped campaigns
- **AND** the Agent Time Distribution response and UI display the real value

#### Scenario: Ready or other time is unsupported
- **WHEN** VICIdial does not provide a recognized ready or other duration column
- **THEN** the service marks that metric unsupported or unavailable
- **AND** the API and UI do not present it as confirmed zero

### Requirement: Historical report diagnostics and graceful degradation
Raw VICIdial output SHALL be collapsed by default and visible only to authorized technical/admin roles. Each report source and section SHALL expose a readable loading, confirmed-zero, empty, success, unsupported, parsing-failure, transport-failure, or permission-failure state. A successful section SHALL remain visible when another source fails, and the browser SHALL retain the last complete successful dashboard snapshot for the same filter key when a refresh does not provide a trustworthy replacement.

#### Scenario: Normal report user opens the page
- **WHEN** a report user opens the historical dashboard
- **THEN** raw diagnostic output is not part of the primary view
- **AND** the normal page presents actionable loading, empty, unavailable, or retry text where data is absent

#### Scenario: One report section fails
- **WHEN** the campaign trend source fails but agent performance succeeds
- **THEN** the agent performance section remains visible
- **AND** the trend section provides a retry/recovery message without displaying credentials or request URLs

#### Scenario: Response parsing fails
- **WHEN** VICIdial returns a non-empty body that does not match a supported report layout
- **THEN** the source is classified as a parse or unsupported-format failure before metric aggregation
- **AND** no malformed field is converted into a confirmed zero

### Requirement: Historical Reports use the shared mapped campaign scope
Historical Telephony Reports SHALL resolve the selected CRM campaign through the shared scope resolver, select its assigned VICIdial server, include every enabled mapped VICIdial campaign code by default, and filter backend rows to that set using case-insensitive normalization. An optional secondary VICIdial campaign filter SHALL only narrow that set and SHALL never expand it. The same effective scope SHALL be used for current and comparison periods and SHALL be reflected in the API response.

#### Scenario: Report totals aggregate all mapped campaigns
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **AND** raw campaign totals are 413, 4326, 21, and 2 calls respectively
- **THEN** the CRM report total is 4762 calls
- **AND** rows for `winback` are excluded before aggregation

#### Scenario: Secondary campaign filter cannot escape CRM scope
- **WHEN** a report requests `cro1`
- **THEN** only `cro1` from the CRM campaign's mapped set is included
- **AND** a request for unmapped `winback` is rejected or produces no permitted rows

#### Scenario: Campaign code case differs in the source
- **WHEN** a mapped code is stored as `CAMP_A` and VICIdial returns `camp_a`
- **THEN** the row is included in the mapped scope
- **AND** the response emits one canonical campaign contribution rather than an unmapped row

#### Scenario: Campaign breakdown remains available
- **WHEN** a report aggregates multiple mapped campaigns
- **THEN** the response includes CRM-level totals and a per-mapped-campaign contribution breakdown
- **AND** each campaign's rate is derived from its own raw numerator and denominator

### Requirement: Report rates use weighted raw totals
Combined report rates SHALL be calculated from summed raw numerators and denominators, never by averaging campaign percentages. This applies to answer, contact, conversion, abandonment, and Pareto percentages. Percentage values returned by VICIdial SHALL be treated as diagnostics or ignored for aggregation unless the associated raw numerator and denominator are unavailable and the metric is explicitly marked unsupported.

#### Scenario: Combined answer rate is weighted
- **WHEN** campaign A has 100 calls and 50 answered and campaign B has 900 calls and 90 answered
- **THEN** the combined report contains 1000 calls and 140 answered
- **AND** the answer rate is 14 percent rather than 30 percent

#### Scenario: Disposition percentages are returned in raw rows
- **WHEN** a disposition row contains a count and a display percentage
- **THEN** the service sums counts across campaigns
- **AND** it recalculates the Pareto percentage from the summed count instead of multiplying or averaging the source percentage

### Requirement: Historical agents and dispositions merge across mapped campaigns
Reports SHALL aggregate agent activity and dispositions across all enabled mapped campaigns by stable normalized VICIdial agent identifier or normalized disposition code, while retaining optional campaign-level detail. Aggregation SHALL occur before derived rates, percentages, ordering, and contact-rate calculations. Unmapped campaigns SHALL not appear in any API row, chart series, table row, or total.

#### Scenario: Agent activity is merged
- **WHEN** one agent has 80 calls on `mbsales` and 62 calls on `mbsales2`
- **THEN** the primary agent row contains 142 calls
- **AND** its detail can identify the two campaign contributions

#### Scenario: Dispositions are merged
- **WHEN** `NA` occurs 100 times on `mbsales`, 800 times on `mbsales2`, and 10 times on `cro1`
- **THEN** the CRM-level `NA` total is 910
- **AND** unrelated campaign dispositions are excluded

#### Scenario: Contact rate is derived from scoped totals
- **WHEN** the configured contacted disposition group totals 200 calls and the scoped total-call denominator is 1000
- **THEN** contact rate is 20 percent
- **AND** the rate is unavailable when either required raw total is unavailable
