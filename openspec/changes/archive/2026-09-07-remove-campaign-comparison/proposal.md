## Why

The Reports page no longer renders the VICIdial campaign-comparison chart, but the template still contains the commented section and its Alpine chart state/rendering branch. Removing that stale presentation code keeps the page focused and prevents dead comparison logic from being maintained as if it were an active report feature.

## What Changes

- Remove the commented Campaign Comparison section from the Reports Blade view.
- Remove the campaign-comparison chart state and ApexCharts rendering branch from the Reports page.
- Preserve the campaign selector, campaign-scoped status/disposition tables, API campaign contribution data, and separate period-comparison feature.
- Add focused regression coverage that the Reports view no longer exposes the campaign-comparison chart.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `telephony-operations-and-reporting`: historical Reports no longer renders a standalone campaign-comparison visualization while retaining the campaign-scoped report data used by active sections.

## Impact

- `resources/views/reports/index.blade.php`: remove stale campaign-comparison markup and client-side chart code.
- Reports view regression test: verify the removed UI does not reappear.
- No routes, controllers, services, database schema, dependencies, or API contracts change.
