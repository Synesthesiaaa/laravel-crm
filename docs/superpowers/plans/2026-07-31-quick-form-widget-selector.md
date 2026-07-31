# Quick Form Widget Form Selector Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users choose any active form in the current campaign directly from the persistent Quick Form widget, including while Vicidial split view is active.

**Architecture:** Extend the existing authenticated Quick Form bootstrap response with normalized active-form options. Add a small pure options utility for validation, load the metadata during widget initialization, and reuse `syncFrameSrc()` to replace only the Quick Form iframe when the selection changes. The parent page, Vicidial widget, and workspace state remain untouched.

**Tech Stack:** Laravel 12 / PHP 8.5, Blade, Alpine.js 3, Axios, Vite, Node test runner, PHPUnit 11, Pint, Playwright.

## Global Constraints

- List only active forms configured for the current campaign.
- Keep the current form selected by default when it is present in the campaign options.
- Do not navigate or reload the parent page when the Quick Form selection changes.
- Preserve the existing Vicidial iframe/session, widget split state, drag, resize, and minimize behavior.
- Do not persist the selected form as a new user preference.
- Do not add dependencies.
- Every changed PHP file must be formatted with `vendor/bin/pint --dirty --format agent` before completion.

---

### Task 1: Extend Quick Form bootstrap metadata

**Files:**
- Modify: `app/Http/Controllers/Api/QuickFormController.php`
- Test: `tests/Feature/Api/QuickFormBootstrapApiTest.php`

**Interfaces:**
- The authenticated `GET /api/forms/quick/bootstrap` response keeps all existing fields and adds `forms: list<array{type: string, name: string}>`.

- [ ] **Step 1: Add the failing API assertion**

Extend `test_bootstrap_returns_active_campaign_first_form` with:

```php
->assertJsonPath('forms', [
    ['type' => 'verification', 'name' => 'Verification'],
    ['type' => 'disposition', 'name' => 'Disposition'],
]);
```

- [ ] **Step 2: Run the focused test and confirm it fails**

Run:

```bash
php artisan test --compact tests/Feature/Api/QuickFormBootstrapApiTest.php --filter=test_bootstrap_returns_active_campaign_first_form
```

Expected: failure because the bootstrap response does not yet contain `forms`.

- [ ] **Step 3: Return normalized active-form options**

In `QuickFormController::bootstrap`, map `$forms` in configuration order, keep only non-empty string keys, and return each option as:

```php
['type' => $formType, 'name' => (string) ($formConfig['name'] ?? $formType)]
```

Keep the existing first-form fields and 422 response unchanged.

- [ ] **Step 4: Run the API test and confirm it passes**

Run the same focused PHPUnit command. Expected: PASS.

- [ ] **Step 5: Commit the API slice**

```bash
git add app/Http/Controllers/Api/QuickFormController.php tests/Feature/Api/QuickFormBootstrapApiTest.php
git commit -m "feat: expose quick form options"
```

### Task 2: Add pure form-option normalization and selection guards

**Files:**
- Create: `resources/js/widgets/quick-form-options.js`
- Create: `tests/JavaScript/widgets/quick-form-options.test.js`

**Interfaces:**
- `normalizeQuickFormOptions(forms: unknown): Array<{type: string, name: string}>`
- `hasQuickFormOption(options: Array<{type: string, name: string}>, formType: unknown): boolean`

- [ ] **Step 1: Write failing Node tests**

Add tests for valid options, fallback labels, duplicate/invalid entries, and selection validation:

```js
test('normalizes active form options and removes invalid duplicates', () => {
    assert.deepEqual(normalizeQuickFormOptions([
        { type: 'ezycash', name: 'EzyCash' },
        { type: 'ezycash', name: 'Duplicate' },
        { type: '', name: 'Invalid' },
        { type: 'ezyconvert' },
    ]), [
        { type: 'ezycash', name: 'EzyCash' },
        { type: 'ezyconvert', name: 'ezyconvert' },
    ]);
});

test('accepts only loaded form types', () => {
    const options = [{ type: 'ezycash', name: 'EzyCash' }];
    assert.equal(hasQuickFormOption(options, 'ezycash'), true);
    assert.equal(hasQuickFormOption(options, 'ezyconvert'), false);
});
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run:

```bash
node --test tests/JavaScript/widgets/quick-form-options.test.js
```

Expected: module-not-found or missing-export failure.

- [ ] **Step 3: Implement the pure utility**

Normalize only object entries with non-empty string `type`; use `name` when it is a non-empty string and otherwise use the form type; preserve first occurrence order and remove duplicate types. `hasQuickFormOption` must require an exact string type match.

- [ ] **Step 4: Run the Node tests and confirm they pass**

Run the same command. Expected: PASS.

### Task 3: Wire the selector into Quick Form state and Blade

**Files:**
- Modify: `resources/js/quick-form-widget.js`
- Modify: `resources/views/partials/quick-form-widget.blade.php`
- Test: `tests/Feature/FormShowViewRenderTest.php`

**Interfaces:**
- Quick Form state adds `formOptions`, `formsLoading`, and `loadFormOptions()`.
- `selectForm(formType: string): void` ignores types not in `formOptions`; valid types call `syncFrameSrc(formType, currentCampaign, { force: true })`.

- [ ] **Step 1: Add view assertions for the selector contract**

Render an authenticated form page and assert the response contains the Quick Form selector label, `formOptions`, and `selectForm($event.target.value)` bindings without changing the form page submission hooks.

- [ ] **Step 2: Run the view test and confirm it fails**

Run:

```bash
php artisan test --compact tests/Feature/FormShowViewRenderTest.php
```

Expected: failure because the Quick Form partial has no form selector.

- [ ] **Step 3: Load metadata without replacing an existing current frame**

Import the pure option helpers. Add state:

```js
formOptions: normalizeQuickFormOptions(boot.forms),
formsLoading: false,
```

Implement `loadFormOptions()` to GET `/api/forms/quick/bootstrap`, normalize `data.forms`, set `currentCampaign` from `data.campaign` only when unset, and keep the existing `frameSrc`/current form when a current form is already active. If there is no current form, reuse the existing first-form bootstrap URL path. On failure, retain the existing frame and expose no selectable options.

- [ ] **Step 4: Implement guarded selection**

Add:

```js
selectForm(formType) {
    if (!hasQuickFormOption(this.formOptions, formType) || !this.currentCampaign) {
        return;
    }

    this.open = true;
    this.syncFrameSrc(formType, this.currentCampaign, { force: true });
}
```

Call `loadFormOptions()` during `init()` for both dashboard pages and form pages. Do not call `persistLayout()` from `selectForm()`.

- [ ] **Step 5: Render the selector in the Quick Form header**

Replace the static `Quick Form` title with a compact labeled `<select>` that loops over `formOptions`, binds its value to `currentFormType`, invokes `selectForm($event.target.value)`, and is disabled while `formsLoading` or when no options exist. Keep the Split view and minimize controls in the same header.

- [ ] **Step 6: Run the view and widget tests**

Run:

```bash
php artisan test --compact tests/Feature/FormShowViewRenderTest.php
node --test tests/JavaScript/widgets/*.test.js
```

Expected: PASS.

- [ ] **Step 7: Commit the widget slice**

```bash
git add resources/js/widgets/quick-form-options.js resources/js/quick-form-widget.js resources/views/partials/quick-form-widget.blade.php tests/JavaScript/widgets/quick-form-options.test.js tests/Feature/FormShowViewRenderTest.php
git commit -m "feat: add quick form widget selector"
```

### Task 4: Verify split-screen form switching and final regressions

**Files:**
- Modify: `openspec/changes/data-master-vicidial-workspace-dashboard/proposal.md`
- Create: `openspec/changes/data-master-vicidial-workspace-dashboard/specs/quick-form-selector/spec.md`
- Modify: `openspec/changes/data-master-vicidial-workspace-dashboard/tasks.md`
- Modify: `openspec/specs/widget-split-workspace/spec.md`
- Modify: `openspec/specs/platform-stabilization/spec.md`
- Test: Playwright browser flow against `/dashboard` or `/admin/data-master`

**Interfaces:**
- No new persistence key, route, or database table.
- Existing `crm-widget-workspace` event and `window.crmWidgetWorkspace` API remain unchanged.

- [ ] **Step 1: Add the OpenSpec selector requirement and tasks**

Document that active current-campaign forms are selectable inside the persistent Quick Form panel and that changing the selection preserves Vicidial and split state.

- [ ] **Step 2: Run the production frontend build**

```bash
npm run build
```

Expected: Vite completes successfully.

- [ ] **Step 3: Run the complete automated regression set**

```bash
php artisan test --compact
node --test tests/JavaScript/widgets/*.test.js
vendor/bin/pint --dirty --format agent
git diff --check
```

Expected: all tests pass, Pint reports pass or formatted files, and diff check is clean.

- [ ] **Step 4: Verify the browser flow**

With a campaign containing at least two active forms:

1. Open the dashboard and wait for Quick Form options to load.
2. Enable Split view.
3. Select a different form in the Quick Form selector.
4. Assert the Quick Form iframe URL changes to `/forms/<selected-type>?campaign=<current-campaign>&widget_embed=1`.
5. Assert `window.crmWidgetWorkspace.isSplitScreen()` remains true and `#phone-widget-root` remains mounted.
6. If the bootstrap request fails, assert the existing iframe remains visible and the selector is disabled.

- [ ] **Step 5: Validate and sync OpenSpec**

```bash
openspec validate data-master-vicidial-workspace-dashboard --type change --strict --no-interactive --json
openspec validate --all --strict --no-interactive --json
```

Expected: the change and all repository specs validate successfully.
