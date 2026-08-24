## Why

The CRM already has shared Blade components, theme tokens, Alpine stores, and a soft-navigation shell, but the visual hierarchy and responsive behavior are inconsistent across the shared chrome and the Activity Log. The first modernization milestone will make the core interface easier to scan, operate by touch or keyboard, and use on narrow screens without changing business workflows or telephony behavior.

## What Changes

- Refresh the shared light/dark semantic tokens for a calmer, higher-contrast CRM visual language.
- Improve the persistent sidebar and header hierarchy across desktop, tablet, and mobile layouts.
- Standardize focus, hover, pressed, disabled, and reduced-motion states for shared controls.
- Add responsive content gutters and safe interaction sizing to the shared layout and controls.
- Redesign the Activity Log filters and stream rows for mobile-first scanning while preserving realtime delivery, polling fallback, follow, pause, clear, and expandable audit details.
- Add automated render/regression coverage and browser validation for the shared shell and Activity Log.

## Capabilities

### New Capabilities

- `responsive-crm-shell`: Accessible, responsive shared navigation, header, layout, and component interaction states.
- `activity-log-responsive-experience`: Mobile-first Activity Log presentation and controls that preserve existing realtime audit behavior.

### Modified Capabilities

None. Existing realtime and authorization requirements remain unchanged; this change updates presentation and interaction quality only.

## Impact

- Blade layout and sidebar: `resources/views/layouts/app.blade.php`, `resources/views/layouts/sidebar.blade.php`.
- Shared visual system: `resources/css/app.css` and reusable Blade components under `resources/views/components`.
- Activity Log view styles/markup: `resources/views/admin/activity_log.blade.php`.
- Existing view and JavaScript tests plus new responsive/render assertions where practical.
- No database schema, API route, authorization, telephony, or package dependency changes.
