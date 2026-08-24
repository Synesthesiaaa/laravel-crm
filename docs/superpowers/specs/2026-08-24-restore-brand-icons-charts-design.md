# Restore Brand, Icon, and Chart Visual System

## Approved Direction

Restore the pre-refresh magenta/charcoal business palette while retaining the responsive and accessibility improvements already in the shared shell. Standardize the existing SVG icon primitive and centralize ApexCharts theme options so Dashboard, Admin Dashboard, Reports, and Supervisor use the same visual language in both light and dark themes.

## Scope

- `resources/css/app.css`: restore prior semantic brand/surface tokens; keep responsive/accessibility rules.
- `resources/views/components/icon.blade.php`: consistent SVG sizing, stroke, and accessible labeling options.
- `resources/js/app.js`: shared CSS-token-driven ApexCharts theme helper.
- Chart-bearing Blade views: consume shared chart options without changing data or business logic.
- Tests: render and JavaScript regression coverage plus browser checks.

## Constraints

- No new dependencies.
- No backend/API/database changes.
- Preserve existing soft-navigation cleanup and chart ownership.
- Use SVG icons only; no emoji or raster replacements.
- Check 375px and 1440px, light and dark themes, reduced motion, focus, and no horizontal overflow.
