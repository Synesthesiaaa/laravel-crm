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
