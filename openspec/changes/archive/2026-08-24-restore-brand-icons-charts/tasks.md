## 1. Brand token restoration

- [x] 1.1 Add render assertions that the shared shell exposes the established magenta primary token and retains the responsive shell landmarks.
- [x] 1.2 Restore the pre-refresh primary, surface, text, border, shadow, glow, and header-height values in `resources/css/app.css` while preserving responsive/accessibility rules.
- [x] 1.3 Run the focused Blade render tests and inspect light/dark token output before moving to component work.

## 2. Shared icon system

- [x] 2.1 Add failing component/render assertions for default SVG stroke/size semantics and accessible icon labeling.
- [x] 2.2 Update `resources/views/components/icon.blade.php` with backward-compatible size/stroke/label options and semantic icon utility classes.
- [x] 2.3 Apply the shared icon treatment to visible shared shell/stat-card/table controls without replacing the existing SVG icon family.
- [x] 2.4 Run the focused icon/render tests and review icon-only control names in the shared layout.

## 3. Shared chart theme contract

- [x] 3.1 Add a Node regression test for a CSS-token-driven ApexCharts theme contract, including dark/light colors and reduced-motion behavior.
- [x] 3.2 Implement `window.crmChartTheme` in `resources/js/app.js` with token reads, semantic series colors, axes/grid/tooltips, responsive defaults, and animation settings.
- [x] 3.3 Add shared chart-container styles for stable height, overflow containment, readable titles, and narrow screens.

## 4. Chart migration

- [x] 4.1 Migrate user Dashboard activity charts to shared theme options while preserving data and lifecycle cleanup.
- [x] 4.2 Migrate Admin Dashboard charts to shared theme options while preserving existing series and labels.
- [x] 4.3 Migrate Reports charts to shared theme options and status/disposition color roles.
- [x] 4.4 Migrate Supervisor performance/hourly/realtime charts and verify soft-navigation instance ownership.

## 5. Verification and handoff

- [x] 5.1 Run focused PHPUnit/Node tests, Pint, and the Vite production build.
- [x] 5.2 Use Playwright to verify Dashboard, Admin Dashboard, Reports, and Supervisor at 375px and 1440px in both themes, including no horizontal overflow and chart rendering.
- [x] 5.3 Review changed files, synchronize/archive OpenSpec, and commit the implementation without staging unrelated user changes.
