## ADDED Requirements

### Requirement: Reports omit campaign comparison visualization
The historical Reports page SHALL omit the standalone VICIdial campaign-comparison chart and its client-side presentation state while preserving campaign-scoped data required by active status and disposition sections. The reporting API SHALL remain unchanged by this presentation-only removal.

#### Scenario: Historical Reports render without campaign comparison
- **WHEN** an authorized user opens the historical Reports page
- **THEN** the page does not render a `Campaign Comparison` section or `chart-campaign-comparison` element
- **AND** the page continues to render the active call-status, agent-performance, and disposition sections

#### Scenario: Active campaign reporting remains available
- **WHEN** historical report data contains multiple mapped VICIdial campaigns
- **THEN** campaign rows remain available to the status and disposition projections
- **AND** removing the standalone comparison visualization does not change campaign filters, report totals, or API response data
