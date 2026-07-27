# Responsive Data Master Table Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Keep the complete Data Master table on desktop while presenting every record field as a readable, non-horizontally-scrollable stacked card on mobile.

**Architecture:** Keep the existing `x-table.index` desktop table and render a second server-side mobile card list in `resources/views/admin/data_master.blade.php`. Scope responsive card styles to Data Master-specific classes in `resources/css/app.css`, reusing the existing formatted-value, action, empty-state, and pagination behavior without changing routes or services.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS 4 via Vite, Alpine.js delete confirmation, PHPUnit feature tests, in-app Browser verification.

## Global Constraints

- Desktop must display the full configured Data Master table.
- Mobile must show every configured field and existing actions without horizontal scrolling.
- Other shared tables must retain their current behavior.
- Do not add dependencies, routes, database changes, or JavaScript for layout transformation.
- Every PHP change must be formatted with `vendor/bin/pint --dirty --format agent`.

---

### Task 1: Add the failing Data Master responsive-rendering assertions

**Files:**
- Modify: `tests/Feature/Admin/ExtractionExportTest.php` near the existing Data Master display tests

**Interfaces:**
- Consumes: Existing `setUp()`, `campaignSession()`, `preparePercentageFormRecord()`, `RefreshDatabase`, and Data Master route.
- Produces: Feature assertions that require a desktop table and a mobile card class/field label to be present in the rendered view.

- [ ] **Step 1: Write the failing test**

Add a PHPUnit test named `test_data_master_renders_desktop_table_and_mobile_cards_with_all_fields` that prepares a percentage record, requests `admin.data-master.index` as the authenticated admin, and asserts the response contains the existing `role="grid"` table, the Data Master mobile-card wrapper class, the configured `Discount Rate` label, the formatted `7%` value, and the existing Edit/Delete labels.

- [ ] **Step 2: Run test to verify it fails**

Run:

```powershell
php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php --filter=test_data_master_renders_desktop_table_and_mobile_cards_with_all_fields
```

Expected: FAIL because the Data Master view does not yet include the mobile-card wrapper class.

### Task 2: Implement the parallel desktop/mobile Data Master presentations

**Files:**
- Modify: `resources/views/admin/data_master.blade.php`
- Modify: `resources/css/app.css` in the Tables component section

**Interfaces:**
- Consumes: `$columns`, `$headers`, `$records`, `$dataMasterService`, `$percentageColumns`, `$type`, and existing edit/delete routes and Alpine confirmation store.
- Produces: Complete desktop table markup plus a `.data-master-mobile-list` card presentation that is hidden on desktop and visible only below the desktop breakpoint.

- [ ] **Step 1: Write the minimal Blade markup**

Keep the existing `x-table.index` block as the desktop presentation, add a Data Master-specific desktop class if needed, and add a sibling mobile list that:

```blade
@foreach($records as $row)
    <article class="data-master-mobile-card">
        <dl>
            @foreach($columns as $col)
                <div class="data-master-mobile-field">
                    <dt>{{ $headers[$col] ?? $col }}</dt>
                    <dd>{{ $dataMasterService->formatValue($col, is_object($row) ? ($row->$col ?? '') : ($row[$col] ?? ''), $percentageColumns ?? []) }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="data-master-mobile-actions">…existing Edit/Delete actions…</div>
    </article>
@endforeach
```

Use the same empty-state condition and pagination slot behavior as the desktop table. Keep the existing delete form's hidden fields, `x-ref`, and confirmation method unchanged.

- [ ] **Step 2: Run the focused test**

Run the Task 1 command and confirm it still fails only until the CSS/view class contract is present.

- [ ] **Step 3: Add scoped responsive CSS**

Add rules scoped to the Data Master wrapper that keep the full table visible at `min-width: 768px`, hide the mobile list there, hide the desktop table below that breakpoint, and make mobile labels/values wrap with `overflow-wrap: anywhere`. Use compact card padding and action wrapping; do not change the global `.table-scroll-wrap` overflow behavior.

- [ ] **Step 4: Run the focused test to verify it passes**

Run:

```powershell
php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php --filter=test_data_master_renders_desktop_table_and_mobile_cards_with_all_fields
```

Expected: PASS.

### Task 3: Format, build, and verify responsive rendering

**Files:**
- Verify: `resources/views/admin/data_master.blade.php`
- Verify: `resources/css/app.css`
- Verify: `tests/Feature/Admin/ExtractionExportTest.php`

**Interfaces:**
- Consumes: Task 2's rendered markup and scoped CSS.
- Produces: Formatted source, compiled Vite assets, passing focused tests, and browser evidence at desktop and phone widths.

- [ ] **Step 1: Format modified PHP files**

Run:

```powershell
vendor/bin/pint --dirty --format agent
```

Expected: exit code 0 and only intentional PHP formatting changes.

- [ ] **Step 2: Run focused feature coverage**

Run:

```powershell
php artisan test --compact tests/Feature/Admin/ExtractionExportTest.php --filter=data_master
```

Expected: all Data Master tests pass.

- [ ] **Step 3: Build frontend assets**

Run:

```powershell
npm run build
```

Expected: Vite completes successfully and emits the updated manifest/assets.

- [ ] **Step 4: Verify in Browser**

Use the Data Master route with an authenticated session at a desktop viewport and a phone-sized viewport. Confirm the desktop viewport shows the full table, the phone viewport shows cards with all labels/values, `document.documentElement.scrollWidth` does not exceed `clientWidth`, the form-type control still loads records, and there are no relevant console errors.

- [ ] **Step 5: Inspect the final diff and OpenSpec status**

Run:

```powershell
git diff --check
openspec status --change "responsive-data-master-table"
```

Expected: no whitespace errors, all implementation tasks checked off, and the change ready for spec sync/archive.
