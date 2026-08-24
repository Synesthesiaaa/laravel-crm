# Modernize Responsive CRM UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve the shared Laravel CRM shell and Activity Log for consistent visual hierarchy, accessible interaction, and responsive operation without changing business workflows.

**Architecture:** Extend the existing CSS token/component layer in `resources/css/app.css`, preserve the persistent Blade layout/sidebar and Alpine stores, and make the Activity Log’s existing Alpine component responsive through markup/CSS changes. Soft navigation remains the lifecycle boundary; no new frontend framework or dependency is introduced.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS 4, Alpine.js 3, Vite, PHPUnit, Node test runner, Playwright.

**Spec:** `openspec/changes/modernize-responsive-crm-ui/`

## Global Constraints

- Preserve existing route, role, authorization, activity payload, Echo, polling, telephony, and soft-navigation behavior.
- Keep existing semantic `--color-*` token names so current views continue to work.
- Use native links/buttons/labels and visible `:focus-visible` states; do not add icon dependencies.
- Maintain no document-level horizontal overflow at 375px and respect `prefers-reduced-motion`.
- Use `@push('scripts')` and Alpine `destroy()` for page lifecycle code inside `#main-layout`.

---

### Task 1: Add failing render and lifecycle assertions

**Files:**
- Modify: `tests/Feature/ViewLifecycleRenderTest.php`
- Modify: `tests/Feature/Admin/ActivityLogTest.php`
- Modify: `tests/JavaScript/activity-log.test.js`

**Interfaces:**
- Consumes: Current shared layout and Activity Log markup.
- Produces: Assertions for `skip-link`, `main-content`, navigation semantics, responsive Activity Log hooks, and cleanup-safe lifecycle.

- [ ] Add assertions that authenticated layout renders a skip link, main-content landmark, and accessible navigation drawer controls.
- [ ] Add assertions that Super Admin Activity Log renders labeled filter form, live connection status, and responsive stream hooks.
- [ ] Add JavaScript source assertions for `destroy()`, live status semantics, and stacked row class hooks.
- [ ] Run the focused PHPUnit and Node tests and confirm the new assertions fail because production markup has not changed.

### Task 2: Refresh shared token and component CSS

**Files:**
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: Existing `--color-*` variables and shared component class names.
- Produces: Accessible light/dark surface tokens and responsive/focus/motion rules used by layout, sidebar, forms, cards, tables, and Activity Log.

- [ ] Update the existing token values to the approved navy/slate surface and blue-accent direction while retaining all variable names.
- [ ] Add base overflow, text rendering, focus-ring, minimum control size, and reduced-motion rules.
- [ ] Make content/header/sidebar sizing use responsive gutters and `100dvh`-safe behavior.
- [ ] Run `npm run build` after the CSS change and verify the focused tests still pass.

### Task 3: Improve the shared Blade shell

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/sidebar.blade.php`

**Interfaces:**
- Consumes: Existing Alpine sidebar store, theme/search/notification/user stores, route checks, and persistent `#main-layout` boundary.
- Produces: Skip-to-content link, accessible shell landmarks, clearer nav grouping, responsive drawer semantics, and sufficiently large controls.

- [ ] Add a skip link immediately after `<body>` and make `#main-content` the focus target.
- [ ] Add `aria-expanded`, `aria-controls`, and stable IDs to mobile/desktop sidebar controls without changing Alpine actions.
- [ ] Add descriptive navigation section labels and expose the current destination with `aria-current="page"`.
- [ ] Adjust header action groups and mobile content padding so controls remain usable at narrow widths.
- [ ] Re-run layout render assertions and verify soft-navigation scripts remain outside the swapped content where required.

### Task 4: Standardize shared component states

**Files:**
- Modify: `resources/views/components/stat-card.blade.php`
- Modify: `resources/views/components/table/index.blade.php`
- Modify: `resources/views/components/page-header.blade.php`

**Interfaces:**
- Consumes: Existing component props and callers.
- Produces: Semantic links/buttons and responsive wrappers without changing public prop names.

- [ ] Replace click-only stat-card navigation with an accessible anchor presentation when `href` is supplied.
- [ ] Add responsive table containment and caption/label hooks without changing table slot behavior.
- [ ] Ensure page header actions wrap cleanly and maintain heading hierarchy.
- [ ] Run the relevant feature/render tests.

### Task 5: Rework Activity Log responsive markup and styles

**Files:**
- Modify: `resources/views/admin/activity_log.blade.php`

**Interfaces:**
- Consumes: Existing `activityLogTerminal(config)` API and route-provided entry/filter data.
- Produces: Mobile-first filter layout, responsive stream entries, bounded long-value handling, and accessible state labels.

- [ ] Add an explicit live status region and connect filter form state to loading semantics.
- [ ] Replace the fixed-width terminal row assumptions with desktop columns and stacked phone metadata/details.
- [ ] Keep follow, pause/resume, clear, expand/collapse, and connection labels as native controls with state attributes.
- [ ] Contain JSON and long identifiers inside detail regions so the document itself never gains horizontal overflow.
- [ ] Respect reduced motion for page-local transitions and retain `@push('scripts')` placement.

### Task 6: Harden Activity Log lifecycle and verify

**Files:**
- Modify: `resources/views/admin/activity_log.blade.php`
- Modify: `tests/JavaScript/activity-log.test.js`

**Interfaces:**
- Consumes: Existing `bindRealtime`, `startPolling`, `poll`, `append`, and `destroy` methods.
- Produces: Cleanup-safe timers/subscriptions and tests proving the existing behavior remains intact.

- [ ] Make initialization idempotent for the page lifecycle and prevent duplicate polling timers.
- [ ] Keep `destroy()` clearing the timer and realtime unsubscribe callback.
- [ ] Run the Node Activity Log test file and focused PHPUnit Activity Log tests.

### Task 7: Browser validation and plan handoff

**Files:**
- Modify: `openspec/changes/modernize-responsive-crm-ui/tasks.md`

**Interfaces:**
- Consumes: Implemented shell and Activity Log.
- Produces: Verified responsive behavior and updated OpenSpec task state.

- [ ] Run Laravel Pint on modified PHP files and `npm run build`.
- [ ] Use Playwright at 375px, 768px, 1024px, and 1440px to inspect the shell, theme, drawer, Activity Log, soft-navigation return, console, and network health.
- [ ] Review the diff, update OpenSpec task checkboxes and implementation notes, and run the final focused verification commands.
- [ ] Commit the implementation only after fresh verification evidence is available.
