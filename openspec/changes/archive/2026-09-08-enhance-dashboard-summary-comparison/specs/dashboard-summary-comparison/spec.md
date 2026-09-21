## ADDED Requirements

### Requirement: Campaign-scoped monthly summary periods

The system SHALL calculate dashboard summary periods from the application timezone and the observation timestamp, using the first day of the current month through the observation timestamp for month-to-date mode and the equivalent elapsed range in the immediately preceding month. The system SHALL cap the previous range to the previous month’s final valid day and SHALL support an explicitly completed current month versus previous full month mode.

#### Scenario: In-progress month uses an equivalent elapsed previous range

- **WHEN** the observation timestamp is September 18 at 15:00
- **THEN** the current range is September 1 through September 18 at 15:00 and the previous range is August 1 through August 18 at 15:00

#### Scenario: Short previous month is capped safely

- **WHEN** the current period reaches March 31
- **THEN** the previous comparison range ends on February 28 or February 29 as appropriate and never constructs an invalid February date

#### Scenario: Completed-month mode uses full calendar months

- **WHEN** a completed August period is requested
- **THEN** the current range is August 1 through the start of September and the previous range is July 1 through the start of August

### Requirement: Existing attribution defines summary transactions and amount

The system SHALL calculate summary transaction counts and amounts using the same campaign-scoped sales attribution rules as the existing dashboard sales KPIs. In legacy mode, a row SHALL qualify only when at least one numeric field marked `is_sale_amount` exists, and the amount SHALL be the sum of its numeric marked fields. In custom mode, rows SHALL qualify and derive amount according to the resolved custom trigger, conditions, and amount field. Null or malformed legacy amounts SHALL be excluded, while numeric zero SHALL remain a valid amount when the existing rule qualifies the row.

#### Scenario: Marked numeric fields are summed once per record

- **WHEN** a campaign has two marked numeric fields and a record contains 100 and 25.50
- **THEN** the summary counts one transaction and adds 125.50 exactly once

#### Scenario: Custom campaign scope is applied to both periods

- **WHEN** the selected CRM campaign has custom form rules
- **THEN** current and previous totals and daily points include only rows from that campaign’s resolved active form tables and rules

#### Scenario: Unqualified or null amounts do not become legitimate transactions

- **WHEN** a legacy row has no numeric marked value or only null values
- **THEN** the row is excluded from both count and amount totals

### Requirement: Summary totals, daily alignment, and comparison values

The system SHALL return current and previous count/amount totals, absolute differences, percentage differences, and aligned day-level count/amount series in one summary data shape. The daily series SHALL include every current-period day in order, including zero-value missing days, and SHALL use the same day number for the previous series. A current-period day without a valid previous-month equivalent SHALL be represented as unavailable rather than zero activity.

#### Scenario: Independent count and amount variances are returned

- **WHEN** current totals are 1,250 transactions and 2,500,000 amount, and previous totals are 1,000 transactions and 2,000,000 amount
- **THEN** count difference is 250 with 25 percent change and amount difference is 500,000 with 25 percent change

#### Scenario: Count and amount can trend in different directions

- **WHEN** current count increases while current amount decreases
- **THEN** the returned count and amount comparisons preserve their independent differences and directions

#### Scenario: Missing daily activity remains visible

- **WHEN** no qualifying record exists for day 2 between records on days 1 and 3
- **THEN** day 2 remains in the daily series with zero current and/or previous values

### Requirement: Safe zero-baseline comparisons

The system SHALL never divide by zero when calculating a comparison. When the previous value is zero and the current value is non-zero, the comparison SHALL return the absolute difference, a null percentage, and a new-activity status. When both values are zero, the comparison SHALL return zero difference, zero percentage, and no-change status.

#### Scenario: New activity after a zero previous period

- **WHEN** the current count is 50 and the previous count is 0
- **THEN** the comparison reports +50 and “New activity vs last month” without an infinite percentage

#### Scenario: No activity in either period

- **WHEN** both current and previous amount are zero
- **THEN** the comparison reports no change and does not display an infinite or fabricated percentage

### Requirement: Executive dashboard summary presentation

The dashboard SHALL show current-period transactions, current-period total amount, transaction change, and amount change within the existing KPI section. Each metric SHALL expose its period context, readable configured currency formatting where applicable, absolute variance, and a textual trend cue in addition to any color or icon.

#### Scenario: Month-to-date context is visible

- **WHEN** the dashboard is rendered during an incomplete month
- **THEN** it identifies the current range as month-to-date and identifies the equivalent previous range used for comparison

#### Scenario: Configured currency is used consistently

- **WHEN** a dashboard currency symbol is configured
- **THEN** current amount, amount change, chart amount labels, table values, and exact tooltips use that symbol without changing the underlying numeric totals

### Requirement: Volume and amount comparison visualization

The dashboard SHALL provide a default Volume mode and a keyboard-operable Amount mode. Each mode SHALL render only its own unit on the y-axis, compare actual current and previous period labels over aligned day numbers, show a visible legend, and provide exact interactive values for both series.

#### Scenario: Volume mode compares daily transaction counts

- **WHEN** the dashboard summary loads
- **THEN** the chart defaults to Volume and renders current-month and previous-month daily transaction count series with day-of-month alignment

#### Scenario: Amount mode compares daily monetary values

- **WHEN** the user activates the Amount control
- **THEN** the chart replaces both series and y-axis labels with daily monetary amounts and does not place count and currency on the same y-axis

#### Scenario: Tooltip communicates exact comparison details

- **WHEN** a user hovers, focuses, or taps a chart day
- **THEN** the tooltip identifies both periods, shows exact count or currency values, and shows absolute and percentage difference when a percentage is defined

### Requirement: Summary feedback and accessibility

The dashboard SHALL preserve layout space with a loading state while the dynamically loaded chart mounts, show a meaningful no-data message when neither period has qualifying activity, and provide a degraded text/table summary if the chart cannot mount. The summary SHALL expose a screen-reader description, visible legend, keyboard-operable mode controls, and a daily data table alternative; trend direction SHALL not depend on color alone.

#### Scenario: No activity is explicit

- **WHEN** both periods contain no qualifying transactions or amounts
- **THEN** the dashboard shows “No activity found for the selected period.” instead of a misleading empty chart

#### Scenario: Chart library is unavailable

- **WHEN** the server-rendered summary is present but ApexCharts cannot load
- **THEN** KPI values and the accessible daily data table remain available and the chart area reports that the visualization is unavailable

#### Scenario: Mode controls are keyboard accessible

- **WHEN** a keyboard user tabs to Volume or Amount
- **THEN** each control has a meaningful accessible name and exposes its selected state without requiring hover
