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
The historical reporting service SHALL calculate total calls, answered calls, answer rate, contact rate when configured, average talk time when available, agents with activity, and calls per agent for the selected campaign/date range. When comparison is enabled, it SHALL calculate a previous equal-length period and show rate changes in percentage points.

#### Scenario: Previous period is selected
- **WHEN** the selected range is Aug 20 through Aug 26 and comparison mode is previous period
- **THEN** the comparison range is Aug 13 through Aug 19
- **AND** each comparable rate shows a percentage-point difference rather than an incorrectly labelled relative percentage change

#### Scenario: No comparison is selected
- **WHEN** comparison mode is disabled
- **THEN** the report omits comparison indicators and still returns the selected-period summary

### Requirement: Historical campaign, disposition, and funnel analysis
The historical report SHALL return campaign comparison rows, a descending disposition Pareto dataset with optional `Other` grouping, and a call funnel only for stages supported by configured disposition mappings. System disposition scope filters SHALL apply consistently to report totals and disposition analysis.

#### Scenario: Multiple campaigns have activity
- **WHEN** a date range includes activity from multiple selected VICIdial campaigns
- **THEN** campaign comparison rows are aggregated by stable campaign code and sorted by total calls
- **AND** zero-activity campaigns are hidden by default unless explicitly requested

#### Scenario: Disposition mappings are unavailable
- **WHEN** configured disposition classifications do not define a reliable funnel stage
- **THEN** the unavailable funnel stage is omitted
- **AND** the report does not infer conversion or success from a raw status name

### Requirement: Historical agent aggregation
The historical reporting service SHALL aggregate duplicate agent export/session rows by stable VICIdial agent identifier and SHALL return agent calls, answered calls, contact rate when available, average talk time, total talk time, and pause percentage when available. Display names SHALL NOT be used as the deduplication key.

#### Scenario: Agent has duplicate session rows
- **WHEN** the same stable VICIdial username appears in multiple sessions or campaigns in the selected range
- **THEN** the primary Agent Performance result contains one aggregated row for that username
- **AND** the totals are summed without merging a different username with a similar display name

### Requirement: Historical report diagnostics and graceful degradation
Raw VICIdial output SHALL be collapsed by default and visible only to authorized technical/admin roles. Each report section SHALL expose a readable loading, empty, success, or unavailable state and SHALL retain successful sections when another source fails.

#### Scenario: Normal report user opens the page
- **WHEN** a report user opens the historical dashboard
- **THEN** raw diagnostic output is not part of the primary view
- **AND** the normal page presents actionable empty/loading/error text where data is absent

#### Scenario: One report section fails
- **WHEN** the campaign trend source fails but agent performance succeeds
- **THEN** the agent performance section remains visible
- **AND** the trend section provides a retry/recovery message without displaying credentials or request URLs

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
The system SHALL expose Live, Today, and Historical report modes. Live SHALL use one normalized Supervisor snapshot per refresh and label rolling metrics with their window. Today SHALL distinguish midnight-to-now totals from current operational state. Historical SHALL retain date-range analytics and comparison behavior.

#### Scenario: Live or Today report is refreshed
- **WHEN** an authorized report user selects Live or Today
- **THEN** the report uses the campaign-scoped normalized snapshot and bounded CRM event aggregation
- **AND** unavailable values remain null or visibly unavailable rather than being replaced with zero

### Requirement: Real-time polling is bounded and freshness-aware
The Reports Live and Today modes SHALL prevent overlapping requests, stop polling while the page is hidden, clean up timers and charts on navigation or mode changes, and expose live, degraded, stale, and unavailable source states.

#### Scenario: A refresh fails after a successful snapshot
- **WHEN** a subsequent real-time refresh fails
- **THEN** the last successful values remain visible with stale/retry context
- **AND** the UI does not show a permanently green live state
