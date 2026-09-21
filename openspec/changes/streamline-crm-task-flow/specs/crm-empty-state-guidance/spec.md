# CRM Empty State Guidance

## Purpose

Help users distinguish valid empty results, missing configuration, unavailable sources, and loading failures across operational CRM surfaces.

## ADDED Requirements

### Requirement: Non-table empty states use shared guidance

The CRM SHALL provide a reusable non-table empty-state presentation with a meaningful title, plain-language explanation, semantic tone, and optional next action. The presentation SHALL use existing design tokens and SHALL not rely on color alone.

#### Scenario: A non-table view has no records

- **WHEN** a supported view has no records for its current campaign or date scope
- **THEN** the view states that no records were returned for that scope
- **AND** it explains what will cause content to appear

#### Scenario: A view requires configuration

- **WHEN** a supported view cannot show content because required configuration is missing
- **THEN** the view identifies the missing configuration
- **AND** an authorized user receives a direct configuration action when one exists

### Requirement: Operational views distinguish unavailable data

The Agent Screen, dashboard, and reports SHALL distinguish unavailable or failed data sources from valid empty data when the runtime knows the difference, and SHALL provide a retry or recovery direction where applicable.

#### Scenario: Report source is unavailable

- **WHEN** a report source cannot be refreshed
- **THEN** the view identifies the affected source or section as unavailable
- **AND** the user can retry or use the last successful snapshot when one exists

#### Scenario: Report scope is valid but empty

- **WHEN** a report request succeeds with no rows for the selected scope
- **THEN** the view states that no rows were returned for that scope
- **AND** it does not present the state as a system error

### Requirement: Empty charts do not render misleading graph frames

Dashboard and report chart containers SHALL render a compact empty or unavailable state when there is no meaningful series to plot, while retaining the chart when values are available.

#### Scenario: Chart has no positive values

- **WHEN** the selected chart series contains no meaningful activity values
- **THEN** the chart area shows a compact explanatory state
- **AND** the user is not shown a blank graph frame without context

#### Scenario: Chart renderer is unavailable

- **WHEN** chart data exists but the chart renderer cannot load or render
- **THEN** the chart area explains that visualization is unavailable
- **AND** the underlying data or an accessible table remains the recovery path where available
