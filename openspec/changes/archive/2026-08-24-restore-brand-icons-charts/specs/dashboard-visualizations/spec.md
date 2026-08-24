## ADDED Requirements

### Requirement: Dashboard charts share a semantic theme contract

Dashboard, Admin Dashboard, Reports, and Supervisor ApexCharts SHALL consume shared CSS-token-driven options for colors, font family, axes, grid lines, tooltips, responsive sizing, and reduced-motion behavior. Existing datasets, filters, endpoint responses, and chart meanings MUST remain unchanged.

#### Scenario: Chart renders in the restored brand palette

- **WHEN** a user opens a chart-bearing page in the default theme
- **THEN** the chart uses the restored magenta primary accent, semantic status colors, readable neutral context, and the page's existing data

#### Scenario: Chart renders in light mode

- **WHEN** a user switches to light mode while a chart-bearing page is open
- **THEN** chart text, grid lines, tooltips, and series remain readable against the light surface without a page reload or stale dark-theme configuration

### Requirement: Charts remain responsive and accessible

Chart containers SHALL reserve stable space, contain overflow, adapt to narrow viewports, and expose values through labels, legends, tooltips, or data labels so color is not the sole encoding of meaning.

#### Scenario: User views charts on a phone

- **WHEN** a user opens a dashboard or report at a 375px viewport
- **THEN** charts fit the content column without document-level horizontal scrolling and their primary labels or controls remain reachable

#### Scenario: Reduced-motion user views live charts

- **WHEN** the browser prefers reduced motion
- **THEN** chart animations are disabled or reduced while the chart remains readable and data updates remain available

### Requirement: Chart lifecycle remains safe across soft navigation

Chart initialization SHALL reuse the existing dynamic ApexCharts loading and cleanup lifecycle, destroy or replace only the current page's mounted instances, and SHALL NOT accumulate duplicate chart instances after a soft-navigation leave-and-return cycle.

#### Scenario: User returns to a chart page through soft navigation

- **WHEN** a user leaves and returns to Dashboard, Reports, Admin Dashboard, or Supervisor using the shared soft-navigation flow
- **THEN** each visible chart renders once with current data and no duplicate SVG/canvas/chart instance is accumulated
