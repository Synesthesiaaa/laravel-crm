## ADDED Requirements

### Requirement: Real-time snapshots expose freshness and source health
The system SHALL include generated time, source last-success time, source health, stale threshold, and stale status in Supervisor and Live report snapshots. A failed refresh SHALL preserve last-good values in the browser when available and SHALL never represent unknown data as confirmed zero.

#### Scenario: Required live sources are healthy
- **WHEN** required real-time sources return parseable data within the freshness threshold
- **THEN** the snapshot status is `LIVE`
- **AND** operational metrics and source timestamps are derived from that snapshot

#### Scenario: No successful refresh is within the threshold
- **WHEN** the last successful real-time refresh exceeds the configured stale threshold
- **THEN** the snapshot status is `STALE` or `OFFLINE`
- **AND** the UI displays the last successful update age and a recoverable refresh action

### Requirement: Reports provides Live, Today, and Historical modes
The system SHALL expose distinct report modes. Live SHALL show current operational state and explicitly time-scoped rolling metrics, Today SHALL distinguish midnight-to-now totals from current state, and Historical SHALL retain date-range analysis and comparison.

#### Scenario: Live mode is selected
- **WHEN** a report user selects Live
- **THEN** one normalized snapshot supplies live calls, agent state, queue state, and available rolling metrics
- **AND** the UI labels rolling values with their window

#### Scenario: Today mode is selected
- **WHEN** a report user selects Today
- **THEN** totals cover the configured local midnight through now
- **AND** current operational values are displayed separately from historical totals

### Requirement: Rolling metrics are evidence-based
The system SHALL calculate short-window calls, answered, abandoned, wait, talk, and disposition metrics only from available VICIdial/CRM events with reliable timestamps. Unsupported or incomplete metrics SHALL be null/unavailable and SHALL NOT be fabricated from full-day totals.

#### Scenario: A rolling source is unavailable
- **WHEN** the source needed for a rolling metric fails or lacks reliable timestamps
- **THEN** that metric is unavailable
- **AND** other successful metric families remain visible

### Requirement: Polling is centralized and non-overlapping
The system SHALL use one centralized polling interval per dashboard mode and SHALL not start a new identical request while the previous request is still pending. Switching modes or leaving the page SHALL clean up active timers and chart resources.

#### Scenario: Poll request is still running
- **WHEN** the timer fires while the previous snapshot request is pending
- **THEN** the new request is skipped or the previous request is aborted
- **AND** the browser does not create duplicate per-card requests
