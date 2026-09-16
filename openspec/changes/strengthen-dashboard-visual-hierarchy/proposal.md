## Why

The dashboard gives its welcome, operational context, headline KPIs, and retrospective analysis nearly equal visual weight, which makes the most important numbers slow to find. The existing Operational Signal Desk identity is strong enough to solve this through clearer hierarchy and a more deliberate use of the established charcoal and Signal Magenta palette.

## What Changes

- Recompose the dashboard opening so campaign context, live status, and the primary performance signal establish an immediate reading path.
- Give the most decision-relevant KPI greater visual authority while keeping supporting context metrics quieter and clearly grouped.
- Introduce dashboard-specific structural styling that uses existing brand tokens, typography, radii, and semantic colors.
- Preserve current data, role/campaign visibility, modal actions, disclosure behavior, themes, responsive order, and accessibility semantics.
- Add focused automated coverage for the hierarchy hooks and retained interactive behavior.

## Capabilities

### New Capabilities

- `dashboard-visual-hierarchy`: Defines the dashboard's first-viewport reading order, KPI emphasis, brand-aligned composition, responsive behavior, and accessible interaction requirements.

### Modified Capabilities


## Impact

- Dashboard Blade composition and shared application styles.
- Dashboard-focused feature tests and browser validation.
- No API, database, dependency, authorization, or data-contract changes.
