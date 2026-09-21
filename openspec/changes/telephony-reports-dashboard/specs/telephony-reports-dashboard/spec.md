## ADDED Requirements

### Requirement: Dashboard report presentation
The system SHALL present the telephony reports page as a dashboard composed of summary cards, charts, and structured tables for call status stats, agent performance, and disposition reporting.

#### Scenario: Reports page loads as dashboard
- **WHEN** a user with access to telephony reports opens the reports page
- **THEN** the page SHALL render the report data as dashboard cards, charts, and tables rather than presenting raw VICIdial output as the primary view

#### Scenario: Dashboard covers required report areas
- **WHEN** the report data loads successfully
- **THEN** the page SHALL show call status, agent performance, and disposition sections

### Requirement: Collapsible debug and utility area
The system SHALL provide a collapsed debug area on the reports page that exposes raw VICIdial output, parsed diagnostics, and report utility controls only after expansion.

#### Scenario: Debug area starts collapsed
- **WHEN** the reports page first loads
- **THEN** the debug area SHALL remain collapsed by default

#### Scenario: Debug area reveals raw output
- **WHEN** a user expands the debug area
- **THEN** the page SHALL display the raw VICIdial responses and parsing diagnostics for the current report data

### Requirement: Filtered dashboard refresh
The system SHALL keep the existing telephony filters and refresh behavior working across all dashboard sections.

#### Scenario: Dashboard refresh updates all sections
- **WHEN** a user changes the campaign or date filters and refreshes the reports page
- **THEN** all dashboard sections SHALL reload with data that matches the selected filters

### Requirement: Panel-wide disposition scope filter
The system SHALL provide a panel-wide disposition scope filter on the telephony reports page so higher roles can include all dispositions, hide configured system dispositions, or show only configured system dispositions across the dashboard.

#### Scenario: Disposition filter is visible at the panel level
- **WHEN** a user opens the reports page
- **THEN** the filter bar SHALL include a disposition scope control that applies to the full reports panel

#### Scenario: Disposition filter changes the dashboard scope
- **WHEN** a user selects a disposition scope and refreshes the reports page
- **THEN** the disposition breakdown and report totals SHALL reflect the selected scope

### Requirement: System disposition report exclusion
The system SHALL treat configured Vicidial system disposition codes as report-excluded data so they can be skipped from CRM disposition persistence and dashboard aggregation.

#### Scenario: System dispositions skip CRM report persistence
- **WHEN** a Vicidial callback contains a configured system disposition code
- **THEN** the CRM SHALL update the call session but SHALL NOT create a campaign disposition report record for that callback
