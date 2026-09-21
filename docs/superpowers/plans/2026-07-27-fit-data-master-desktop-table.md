# Fit Data Master Desktop Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the desktop Data Master table fit the available screen width, wrap long content, and scroll vertically without horizontal scrolling.

**Architecture:** Keep the shared table component and all other tables unchanged. Add a Data Master-specific wrapper in the Blade view and scope desktop CSS to that wrapper, using a fixed-width table, wrapped cells, and a bounded vertical scroll region with a sticky header.

**Tech Stack:** Laravel 12, Blade, Tailwind CSS v4 build pipeline, PHPUnit feature tests, Playwright browser verification.

## Global Constraints

- Desktop behavior is scoped to the Data Master table only.
- The full table remains rendered; no mobile-card conversion is introduced.
- Long headers and values must wrap inside the viewport instead of creating horizontal overflow.
- Existing APIs, database behavior, dependencies, and other table layouts remain unchanged.
- Run the affected PHPUnit test, Pint for modified PHP files, the frontend build, and browser checks before completion.

---

### Task 1: Add the Data Master layout regression test

**Files:**
- Modify: `tests/Feature/Admin/ExtractionExportTest.php` near the existing Data Master display test

**Interfaces:**
- Consumes: The existing authenticated Data Master route and `preparePercentageFormRecord()` fixture helper.
- Produces: A regression assertion that the Data Master page renders its scoped desktop table hook.

- [ ] **Step 1: Write the failing test**

Add `test_data_master_renders_desktop_table_layout_hook()` after the existing percentage display test. Prepare the existing fixture, request the authenticated route with the campaign session, and assert the response contains `data-master-desktop-table` as HTML.

- [ ] **Step 2: Run the focused test to verify it fails**

Run:

```text
php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php --filter=test_data_master_renders_desktop_table_layout_hook
```

Expected: FAIL because the current Data Master view does not render the new class.

### Task 2: Implement the scoped desktop table layout

**Files:**
- Modify: `resources/views/admin/data_master.blade.php` around the `x-table.index` block
- Modify: `resources/css/app.css` in the table styles section

**Interfaces:**
- Consumes: The existing `md-table-wrap`, `table-scroll-wrap`, Data Master columns, and pagination markup.
- Produces: A `.data-master-desktop-table` wrapper whose desktop table is width-fitted, wraps long content, hides horizontal overflow, scrolls vertically, and keeps the header sticky.

- [ ] **Step 1: Add the scoped Blade wrapper**

Wrap the existing Data Master `<x-table.index>` block in `<div class="data-master-desktop-table">` without changing record fields, actions, pagination, or empty-state behavior.

- [ ] **Step 2: Add desktop-only CSS**

Under `@media (min-width: 1024px)`, scope rules to `.data-master-desktop-table`:

```css
.data-master-desktop-table .table-scroll-wrap {
  max-height: min(62vh, 42rem);
  overflow-x: hidden;
  overflow-y: auto;
}

.data-master-desktop-table table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
}

.data-master-desktop-table thead {
  position: sticky;
  top: 0;
  z-index: 2;
}

.data-master-desktop-table th,
.data-master-desktop-table td {
  overflow-wrap: anywhere;
  word-break: break-word;
}
```

Also keep the action column usable by giving the final column a fixed width and allowing its action controls to wrap.

- [ ] **Step 3: Run the focused test to verify it passes**

Run the same PHPUnit command from Task 1. Expected: PASS.

### Task 3: Validate formatting, assets, and rendered responsive behavior

**Files:**
- No additional source files; inspect the files modified in Tasks 1-2.

**Interfaces:**
- Consumes: The completed Data Master view, CSS, and regression test.
- Produces: Verified test, formatting, build, and browser evidence.

- [ ] **Step 1: Format and run related tests**

Run:

```text
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php
```

- [ ] **Step 2: Build frontend assets and inspect the diff**

Run:

```text
npm run build
git diff --check
```

- [ ] **Step 3: Verify desktop and mobile rendering in the browser**

Open the local Data Master route at a desktop viewport and verify the scoped table has `table-layout: fixed`, `overflow-x: hidden`, `overflow-y: auto`, a bounded height, a sticky header, and no table/page horizontal overflow. Repeat a mobile viewport check to confirm the desktop-only rules do not alter the existing mobile table behavior.
