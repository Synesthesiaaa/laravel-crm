## 1. Baseline and shared tokens

- [ ] 1.1 Add or update render assertions for the shared layout and Activity Log before implementation.
- [ ] 1.2 Refresh stable semantic light/dark tokens, typography defaults, spacing, focus, motion, and surface states in `resources/css/app.css` without changing token names consumed by existing views.
- [ ] 1.3 Add responsive shared-shell rules for content gutters, sticky header spacing, minimum interactive sizes, focus visibility, overflow containment, and reduced motion.

## 2. Shared navigation and layout

- [ ] 2.1 Improve `resources/views/layouts/app.blade.php` with skip-to-content, accessible landmark/focus behavior, and responsive header action sizing while preserving existing Alpine stores and soft-navigation lifecycle.
- [ ] 2.2 Improve `resources/views/layouts/sidebar.blade.php` section hierarchy, active-state semantics, labels/tooltips, and mobile drawer affordances without changing route or role checks.
- [ ] 2.3 Standardize reusable component states in `resources/views/components` where the new shell tokens expose a shared accessibility or responsive gap.

## 3. Activity Log responsive experience

- [ ] 3.1 Rework `resources/views/admin/activity_log.blade.php` filter layout and control affordances for phone, tablet, and desktop widths.
- [ ] 3.2 Rework Activity Log stream row CSS/markup so entries stack on narrow screens, preserve expandable audit details, and contain long values without document overflow.
- [ ] 3.3 Preserve and harden Activity Log Alpine cleanup and live-region semantics for soft navigation, polling fallback, follow/pause, and clear behavior.

## 4. Verification

- [ ] 4.1 Run the focused PHPUnit/JavaScript tests and Laravel Pint for modified PHP/Blade-adjacent PHP files.
- [ ] 4.2 Run `npm run build` and confirm Vite produces a valid asset bundle.
- [ ] 4.3 Use Playwright to verify 375px, 768px, 1024px, and 1440px shell/Activity Log layouts, theme toggle, navigation open/close, soft-navigation return, and browser console/network health.
- [ ] 4.4 Review changed files against the requirements, update the OpenSpec tasks/spec notes to match the implementation, and prepare the change for archive.
