## Purpose

Keep live Supervisor operations separate from historical Telephony Reports while sharing campaign/server resolution and VICIdial transport safely.

## Requirements

### Requirement: Separate operational and historical aggregation responsibilities
The system SHALL expose Supervisor operational snapshots through a Supervisor-specific aggregation service and SHALL expose historical Telephony Reports through a reporting-specific aggregation service. Shared VICIdial transport and campaign/server resolution MAY be reused, but one controller SHALL NOT own both aggregation concerns.

#### Scenario: Supervisor snapshot is requested
- **WHEN** an authorized supervisor requests the selected CRM campaign's live snapshot
- **THEN** the request uses the Supervisor operational service and returns current-state data, source/freshness metadata, and campaign routing context
- **AND** it does not require historical report aggregation to succeed

#### Scenario: Historical dashboard is requested
- **WHEN** an authorized report user requests a date-range report
- **THEN** the request uses the historical reporting service and returns date-scoped analytical data
- **AND** a real-time agent-status grid is not required to render the report

### Requirement: Normalized Supervisor agent states
The system SHALL map VICIdial status and sub-status values into the normalized states `AVAILABLE`, `ON_CALL`, `PAUSED`, `RINGING`, `QUEUE`, `OFFLINE`, or `UNKNOWN` in the Supervisor service layer. The original VICIdial status SHALL remain available as a diagnostic field.

#### Scenario: VICIdial status maps to an operational state
- **WHEN** an agent row contains a recognized ready, call, ringing, pause, or queue status
- **THEN** the response includes the corresponding normalized state and human-readable label
- **AND** the Blade view does not perform VICIdial-specific string matching

#### Scenario: VICIdial status is not recognized
- **WHEN** an agent row contains an unknown or empty status
- **THEN** the response uses `UNKNOWN` and preserves the original status when present
- **AND** the UI does not display the agent as healthy or available by default

### Requirement: Operational Supervisor snapshot semantics
The Supervisor response SHALL prioritize current operational metrics for the selected CRM campaign, including agents online, available, on call, paused, calls waiting, oldest waiting call, and average wait. Historical call totals and answer rate MAY remain as additive compatibility fields but SHALL NOT be required as primary operational metrics.

#### Scenario: Current queue and agent metrics are available
- **WHEN** the mapped VICIdial server returns parseable operational data
- **THEN** the response includes current agent counts and queue metrics scoped to the selected CRM campaign/server
- **AND** each metric identifies its source and last successful update time

#### Scenario: Operational data is unavailable
- **WHEN** the real-time report cannot be reached or cannot be parsed
- **THEN** unavailable values are `null` or represented as `—` by the UI
- **AND** the response reports `unavailable` or `degraded` rather than treating missing values as zero or healthy

### Requirement: Queue health and operational exceptions
The system SHALL calculate queue health using configured thresholds and available signals. Health SHALL be `HEALTHY`, `WARNING`, `CRITICAL`, or `UNKNOWN`; missing required signals SHALL produce `UNKNOWN`. Operational exception records SHALL be derived only from available data.

#### Scenario: Queue exceeds a configured threshold
- **WHEN** waiting calls, oldest wait, available agents, or other configured queue signals exceed a threshold
- **THEN** the Supervisor response returns the applicable warning or critical state with a human-readable reason
- **AND** the UI shows the state as text in addition to semantic color

#### Scenario: Queue signal is missing
- **WHEN** a required queue signal is not returned by VICIdial and no campaign-scoped fallback exists
- **THEN** queue health is `UNKNOWN`
- **AND** the UI does not show a false `HEALTHY` state

### Requirement: Supervisor operational information architecture
The Supervisor page SHALL present tabs named Agent Status, Queue Monitor, and Live Wallboard. The Agent Status view SHALL show normalized state, state duration when available, calls since login, current call details when available, and `—` for unavailable values. The Queue Monitor SHALL show current queue metrics, short-window queue pressure, and attention-required exceptions. The Live Wallboard SHALL show large operational metrics without duplicating the Reports KPI set.

#### Scenario: Supervisor page renders operational tabs
- **WHEN** a supervisor opens the page for a configured campaign
- **THEN** the page presents Agent Status, Queue Monitor, and Live Wallboard
- **AND** the historical Performance Metrics tab is absent

#### Scenario: Agent data is incomplete
- **WHEN** VICIdial does not provide a card field
- **THEN** the card displays `—` or an explicit unavailable label
- **AND** it does not fabricate a duration, call count, or queue value

### Requirement: Supervisor campaign/server isolation and failure independence
Supervisor and Reports SHALL use the selected CRM campaign to resolve exactly one VICIdial server. They SHALL not mix data from another CRM campaign or server, and a failure in one module's upstream report SHALL not prevent the other module from loading when its own source is available.

#### Scenario: Selected CRM campaign changes
- **WHEN** a supervisor or report user selects another allowed CRM campaign
- **THEN** all subsequent requests use that campaign's mapped server and scope
- **AND** the separate VICIdial campaign session value does not replace the CRM campaign context

#### Scenario: Reports fail while Supervisor is live
- **WHEN** historical report retrieval fails but the operational snapshot source succeeds
- **THEN** Supervisor remains usable with its live data
- **AND** Reports shows a recoverable section-level error

### Requirement: Historical report summary and comparison
The historical reporting service SHALL calculate total calls, answered calls, answer rate, contact rate when configured, average talk time when available, agents with activity, and calls per agent for the selected CRM campaign and effective local date range using parsed VICIdial raw totals. When comparison is enabled, it SHALL run the same scoped pipeline for the correctly bounded comparison period and show rate changes in percentage points. Each metric SHALL distinguish confirmed zero from empty, unsupported, parse failure, and transport or permission failure; unavailable metrics SHALL remain null in the API and render as `Unavailable` or `â€”`.

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

### Requirement: Historical agent aggregation
The historical reporting service SHALL aggregate duplicate agent export/session rows by stable normalized VICIdial agent identifier and SHALL return agent calls, answered calls, contact rate when available, average talk time, total talk time, pause percentage when available, and supported ready/other time values. Display names SHALL NOT be used as the deduplication key. Each time metric SHALL remain unavailable when its source field is absent or unparseable rather than being filled with zero.

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
- **AND** the normal page presents actionable empty/loading/error text where data is absent

#### Scenario: One report section fails
- **WHEN** the campaign trend source fails but agent performance succeeds
- **THEN** the agent performance section remains visible
- **AND** the trend section provides a retry/recovery message without displaying credentials or request URLs

#### Scenario: Response parsing fails
- **WHEN** VICIdial returns a non-empty body that does not match a supported report layout
- **THEN** the source is classified as a parse or unsupported-format failure before metric aggregation
- **AND** no malformed field is converted into a confirmed zero

### Requirement: Existing Supervisor notification behavior remains operational
The Supervisor broadcast message form SHALL remain available with its recipient type, recipient, message, confetti option, campaign context, and existing authorization behavior.

#### Scenario: Supervisor sends a broadcast
- **WHEN** an authorized supervisor submits a valid broadcast for the selected CRM campaign
- **THEN** the existing notification endpoint receives the selected campaign context and sends the notification
- **AND** the refactor does not move the functionality into Reports

### Requirement: VICIdial transport diagnostics remain server-scoped and redacted
The system SHALL resolve Non-Agent API requests from the selected CRM campaign's mapped active VICIdial server, preserve explicit per-server URLs, classify HTTP 200 error bodies, and expose safe transport metadata without credentials or unmasked customer data.

#### Scenario: VICIdial returns a login or permission error with HTTP 200
- **WHEN** a mapped server returns an authentication or permission error body with a successful HTTP status
- **THEN** the transport result is classified as `AUTHENTICATION_FAILED` or `PERMISSION_DENIED`
- **AND** the report parser does not treat the body as report rows

#### Scenario: VICIdial returns a valid empty operational feed
- **WHEN** the mapped server returns a recognized no-data response such as `NO LOGGED IN AGENTS`
- **THEN** the source is classified as `REPORT_EMPTY`
- **AND** the Supervisor remains operational without a false degraded warning

### Requirement: Real-time and historical report modes have explicit scopes
The system SHALL expose Live, Today, and Historical report modes. Live SHALL use one normalized Supervisor snapshot per refresh and label rolling metrics with their window. Today SHALL distinguish cumulative midnight-to-now totals from current operational state, prefer the authenticated VICIdial daily report for those totals, and use CRM aggregation only when that report is unavailable. Historical SHALL retain date-range analytics and comparison behavior.

#### Scenario: Live or Today report is refreshed
- **WHEN** an authorized report user selects Live or Today
- **THEN** the report uses the campaign-scoped normalized snapshot and bounded CRM event aggregation
- **AND** Today totals remain cumulative for the selected local calendar day across refreshes and are not replaced by the Live rolling-window values
- **AND** unavailable values remain null or visibly unavailable rather than being replaced with zero

### Requirement: Real-time polling is bounded and freshness-aware
The Reports Live and Today modes SHALL prevent overlapping requests, stop polling while the page is hidden, clean up timers and charts on navigation or mode changes, and expose live, degraded, stale, and unavailable source states.

#### Scenario: A refresh fails after a successful snapshot
- **WHEN** a subsequent real-time refresh fails
- **THEN** the last successful values remain visible with stale/retry context
- **AND** the UI does not show a permanently green live state

### Requirement: Historical Reports use the shared mapped campaign scope
Historical Telephony Reports SHALL resolve the selected CRM campaign through the shared scope resolver, select its assigned VICIdial server, include every enabled mapped VICIdial campaign code by default, serialize the effective campaign set using VICIdial's supported hyphen-delimited multi-campaign format, and filter backend rows to that set using case-insensitive normalization. An optional secondary VICIdial campaign filter SHALL only narrow that set and SHALL never expand it. The same effective scope SHALL be used for current and comparison periods and reflected in the API response.

#### Scenario: Report totals aggregate all mapped campaigns
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **AND** raw campaign totals are 413, 4326, 21, and 2 calls respectively
- **THEN** the historical report request sends `campaigns=mbsales-mbsales2-cro1-cro2` to VICIdial
- **AND** the CRM report total is 4762 calls
- **AND** rows for `winback` are excluded before aggregation

#### Scenario: VICIdial receives all mapped campaign codes
- **WHEN** CRM campaign `mbsales` maps `mbsales`, `mbsales2`, `cro1`, and `cro2`
- **THEN** the historical Non-Agent API request uses `mbsales-mbsales2-cro1-cro2` as its campaign scope
- **AND** it does not send a pipe-delimited value that VICIdial cannot interpret as a campaign list

#### Scenario: Secondary campaign filter cannot escape CRM scope
- **WHEN** a report requests `cro1`
- **THEN** only `cro1` from the CRM campaign's mapped set is included
- **AND** a request for unmapped `winback` is rejected or produces no permitted rows

#### Scenario: Campaign breakdown remains available
- **WHEN** a report aggregates multiple mapped campaigns
- **THEN** the response includes CRM-level totals and a per-mapped-campaign contribution breakdown
- **AND** each campaign's rate is derived from its own raw numerator and denominator

#### Scenario: Campaign code case differs in the source
- **WHEN** a mapped code is stored as `CAMP_A` and VICIdial returns `camp_a`
- **THEN** the row is included in the mapped scope
- **AND** the response emits one canonical campaign contribution rather than an unmapped row

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

### Requirement: Report scope failure is explicit and safe
Reports SHALL return a not-configured/incomplete state for a missing server or empty mapping and SHALL never substitute all VICIdial campaigns or another CRM campaign's mapping.

#### Scenario: Missing server does not search another server
- **WHEN** the selected CRM campaign has no mapped VICIdial server
- **THEN** the report reports VICIdial routing as not configured
- **AND** it does not query a server assigned to another CRM campaign
