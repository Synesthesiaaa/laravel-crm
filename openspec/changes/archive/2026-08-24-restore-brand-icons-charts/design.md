## Context

The CRM already has a semantic CSS token layer, a shared Blade `<x-icon>` component, and dynamic ApexCharts loading through `window.ApexChartsLoader`. The previous responsive shell work changed the established magenta/charcoal visual identity to blue/navy and introduced improved responsive/accessibility rules that must remain. Chart configuration is currently repeated in Dashboard, Admin Dashboard, Reports, and Supervisor views, with page-local colors and incomplete theme synchronization.

## Goals / Non-Goals

**Goals:**

- Restore the exact pre-refresh brand direction: magenta primary accent, black/gray surfaces, and the existing semantic status colors.
- Keep the responsive shell, focus visibility, touch targets, reduced-motion behavior, and soft-navigation lifecycle improvements.
- Make shared SVG icons visually consistent and accessible without replacing the existing icon architecture or adding a dependency.
- Provide one ApexCharts theme contract for color roles, typography, grid/axis contrast, tooltips, responsive sizing, and light/dark mode.
- Preserve all existing chart data, business calculations, filters, endpoints, and page behavior.

**Non-Goals:**

- No changes to backend APIs, database schemas, authorization, chart datasets, or business logic.
- No replacement of ApexCharts or introduction of an icon package.
- No redesign of individual business workflows beyond icon and chart presentation.

## Decisions

### 1. Restore tokens, retain responsive shell rules

Restore the pre-refresh values in `resources/css/app.css` for primary, surfaces, text, borders, glow, and header height. Keep the later additions for `min-width`, overflow containment, focus rings, skip link behavior, responsive gutters, 44px controls, and reduced motion. This separates brand correction from the already-approved usability improvements.

### 2. Improve the existing Blade icon primitive

Keep `resources/views/components/icon.blade.php` as the single SVG source. Add explicit size/stroke/label options that preserve current call sites, use `aria-hidden` for decorative icons, and allow meaningful standalone icons to expose a label. Add semantic icon utility classes/tokens for shared surfaces rather than editing SVG paths in every page. This avoids a new dependency and keeps the existing Heroicons-style outline language.

### 3. Centralize chart theming in the existing JavaScript entrypoint

Add a small `window.crmChartTheme` helper in `resources/js/app.js`. It reads CSS custom properties from the document, returns ApexCharts-compatible colors, fonts, grid, axis, tooltip, and responsive defaults, and exposes a theme key so page charts can be recreated after a theme change. Existing page scripts retain ownership of data and chart instances; they consume shared options through object spreading or helper methods.

### 4. Use role-based chart colors

Charts use magenta as the primary series/accent, existing semantic green/amber/red/blue for status meaning, and a restrained neutral series for comparison. Multi-series charts must not rely on color alone: legends, labels, tooltips, or data labels remain available. Area/line charts use a subtle primary gradient only as chart fill, not as a page decoration.

### 5. Make chart lifecycle theme-safe and responsive

Chart containers reserve a stable minimum height and use a shared class for overflow and mobile sizing. Existing chart cleanup hooks remain authoritative. Page chart renderers destroy/recreate their own instances when data or theme changes, and they do not create duplicate instances during soft navigation. Reduced motion disables chart animations through the shared options.

## Risks / Trade-offs

- **Risk:** Restoring the black/gray palette may reduce contrast for existing muted text. → **Mitigation:** verify both themes with WCAG-oriented contrast checks and adjust only semantic muted tokens, not page-local business colors.
- **Risk:** A shared ApexCharts helper could create hidden coupling between Blade page scripts. → **Mitigation:** keep the helper pure and option-oriented; page scripts retain data transformation and instance ownership.
- **Risk:** Theme changes could leave an old chart palette visible. → **Mitigation:** expose a stable theme key and re-render only mounted chart instances through existing page lifecycle hooks.
- **Risk:** Changing icon defaults could affect compact tables and widgets. → **Mitigation:** preserve current default class output and add opt-in attributes/options; validate shared components at representative breakpoints.

## Migration Plan

1. Add failing render/JavaScript assertions for restored tokens, icon semantics, and shared chart theme output.
2. Restore semantic tokens and add shared icon/chart helpers.
3. Migrate Dashboard, Admin Dashboard, Reports, and Supervisor chart configurations to the helper.
4. Run focused PHPUnit/Node tests, Pint, Vite build, and Playwright checks in both themes at 375px and 1440px.
5. If a regression is found, roll back the view/helper changes while retaining no data or schema migration.

## Open Questions

- None for this implementation scope; the existing magenta/charcoal token values are the source of truth for the restoration.
