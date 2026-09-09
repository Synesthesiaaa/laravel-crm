# Dashboard Performance Runtime

## Purpose

Define the campaign-scoped dashboard asset, visualization, widget, and measurement behavior that keeps the authenticated CRM dashboard responsive without removing live CRM capabilities.

## Requirements

### Requirement: Dashboard route assets are demand-scoped

The dashboard SHALL load only shared shell assets and dashboard-specific assets on the dashboard route, and route-specific form or agent behavior SHALL not be included in the shared shell when the current page does not use it.

#### Scenario: Dashboard loads without an embedded form

- **WHEN** an authenticated user opens `/dashboard` with the production Vite manifest
- **THEN** the page loads the shared shell and dashboard chart entry without loading the Quick Form document or agent-capture entry as an initial dependency

#### Scenario: Embedded form uses a lean entry

- **WHEN** the Quick Form iframe is opened
- **THEN** its document initializes form visibility, Axios, Alpine, and required focus behavior without booting the persistent CRM telephony and notification shell

### Requirement: Heavy dashboard visualizations are deferred safely

The dashboard SHALL load ApexCharts and mount a visualization only when a chart region is near the viewport or the user activates an interaction that requires it. Each chart region SHALL reserve its intended height before asynchronous work begins and SHALL retain a readable loading, unavailable, or no-data state.

#### Scenario: Below-fold activity charts are not part of first paint

- **WHEN** the dashboard loads while the activity section is outside the viewport
- **THEN** ApexCharts is not requested for the activity cards and the cards retain stable reserved space until the section is observed

#### Scenario: Summary chart becomes visible

- **WHEN** the summary chart region intersects the viewport
- **THEN** the chart library is loaded once, the summary chart mounts in its reserved container, and its status changes from loading to ready or unavailable

#### Scenario: Chart library cannot be loaded

- **WHEN** a visualization import or render fails
- **THEN** the page keeps the server-rendered summary/table content and reports that the visualization is unavailable without collapsing the reserved region

### Requirement: Dashboard activity data remains campaign-scoped and lazy

The system SHALL expose an authenticated, campaign-scoped dashboard activity response containing the existing daily, weekly, and monthly trend shapes. The initial dashboard HTML SHALL not compute those below-fold datasets solely to support chart mounting.

#### Scenario: Activity section requests its data

- **WHEN** the activity chart region is observed
- **THEN** one request returns the daily, weekly, and monthly trend datasets for the active campaign and the three charts use those values

#### Scenario: Activity request is unavailable

- **WHEN** the activity response fails or is unauthorized
- **THEN** the chart cards show a non-blocking unavailable state and dashboard summary, tables, navigation, and telephony controls remain usable

### Requirement: Shared widget reads are deduplicated

The widget runtime SHALL share an in-flight read of persisted widget layouts when multiple widget consumers initialize in the same document, while preserving each widget's own layout key, hydration callback, debounced save, and error-tolerant defaults.

#### Scenario: Phone and Quick Form initialize together

- **WHEN** both persistent widget components request their layouts during one page lifecycle
- **THEN** the browser makes one GET request for the layouts payload and each component hydrates only its own layout

#### Scenario: Layout read fails

- **WHEN** the shared layout request fails
- **THEN** both widgets keep their existing default dimensions/positions and no user-facing error blocks the page

### Requirement: Performance evidence distinguishes clean and contaminated runs

The remediation SHALL record actual clean extension-free production-build measurements separately from any supplied or contaminated report and SHALL identify the route, viewport, build, authentication state, and limitations for each comparison.

#### Scenario: Baseline and post-change measurements are reported

- **WHEN** the remediation is verified
- **THEN** the report includes measured before/after category scores, FCP, LCP, TBT, CLS, Speed Index, document response time, request count, and transferred bytes, with no invented values
