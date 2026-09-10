## MODIFIED Requirements

### Requirement: Summary feedback and accessibility

The dashboard SHALL preserve layout space with a loading state while the dynamically loaded chart mounts, show a meaningful no-data message when neither period has qualifying activity, and provide a degraded text/table summary if the chart cannot mount. The summary SHALL expose a screen-reader description, visible legend, keyboard-operable mode controls, and a daily data table alternative; trend direction SHALL not depend on color alone. When the daily data table is shown, it SHALL group current and previous values by measure and SHALL include a total row for the rendered period totals.

#### Scenario: No activity is explicit

- **WHEN** both periods contain no qualifying transactions or amounts
- **THEN** the dashboard shows “No activity found for the selected period.” instead of a misleading empty chart

#### Scenario: Chart library is unavailable

- **WHEN** the server-rendered summary is present but ApexCharts cannot load
- **THEN** KPI values and the accessible daily data table remain available and the chart area reports that the visualization is unavailable

#### Scenario: Mode controls are keyboard accessible

- **WHEN** a keyboard user tabs to Volume or Amount
- **THEN** each control has a meaningful accessible name and exposes its selected state without requiring hover

#### Scenario: Daily summary table aligns comparable measures

- **WHEN** a user opens “View daily summary data”
- **THEN** the table presents current volume next to previous volume and current amount next to previous amount, followed by a `Total` row containing the current and previous period sums

#### Scenario: Daily summary table respects amount visibility

- **WHEN** monetary table visibility is disabled for the campaign
- **THEN** the table omits both amount columns and the amount cells in the total row while retaining current and previous volume values
