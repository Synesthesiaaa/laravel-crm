## Why

Dashboard dialogs open unintentionally on hover and focus, and refreshes can disrupt scrolling and navigation. Campaigns that do not use monetary reporting cannot hide amount information independently of sales counts.

## What Changes

- Open Sales and Top Agent dialogs only on explicit click or keyboard activation.
- Add campaign-specific administrator controls for all amounts and individual amount cards, charts, and tables, retaining existing defaults.
- Stabilize refresh and navigation interactions, preserving scroll during background refresh and preventing stale responses from replacing newer navigation.

## Capabilities

### New Capabilities
- `dashboard-display-controls`: Campaign amount visibility and deliberate dialog interactions.

### Modified Capabilities
- `dashboard-live-updates`: Background refreshes preserve scrolling and defer while dialogs or user interactions are active.

## Impact

Dashboard Blade views, existing layout JSON persistence and validation, soft navigation JavaScript, and focused PHP/browser regressions. No schema or dependency changes. Amount visibility is presentation configuration, not a new financial-data authorization boundary.
