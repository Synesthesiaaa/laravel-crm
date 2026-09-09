## MODIFIED Requirements

### Requirement: Summary feedback and accessibility

The dashboard SHALL preserve layout space with a loading state while the dynamically loaded chart mounts, may defer the chart library until the summary region is near the viewport, show a meaningful no-data message when neither period has qualifying activity, and provide a degraded text/table summary if the chart cannot mount. The summary SHALL expose a screen-reader description, visible legend, keyboard-operable mode controls, and a daily data table alternative; trend direction SHALL not depend on color alone.

#### Scenario: No activity is explicit

- **WHEN** both periods contain no qualifying transactions or amounts
- **THEN** the dashboard shows “No activity found for the selected period.” instead of a misleading empty chart

#### Scenario: Chart library is unavailable

- **WHEN** the server-rendered summary is present but ApexCharts cannot load or the summary region has not yet been observed
- **THEN** KPI values and the accessible daily data table remain available, the reserved chart area reports loading or unavailable status as appropriate, and no content above it shifts

#### Scenario: Mode controls are keyboard accessible

- **WHEN** a keyboard user tabs to Volume or Amount
- **THEN** each control has a meaningful accessible name and exposes its selected state without requiring hover
