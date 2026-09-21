# Regular CRM Form Review Confirmation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a review modal to regular CRM forms so users explicitly confirm entered values before the existing asynchronous save request runs.

**Architecture:** Keep review state in the existing `formVisibility` Alpine component used by the shared `forms/_content.blade.php` partial. Collect review rows from the same enabled, visible form controls used for the submission payload, omit internal metadata, and render a local modal inside the shared partial so both full-page and widget forms behave identically. Confirmation delegates to the existing `submitForm()` method.

**Tech Stack:** Laravel 12 Blade views, Alpine.js 3.15.8, Vite, Tailwind CSS 4, Node built-in test runner, PHPUnit.

## Global Constraints

- Scope is limited to regular CRM forms rendered through `resources/views/forms/_content.blade.php`.
- Agent Capture webforms and admin/filter/export/destructive forms remain unchanged.
- No routes, controllers, request rules, database schema, API payload contract, or dependencies change.
- Preserve native validation, draft autosave, async error handling, success reset, and toast behavior.
- Run focused tests before the full build; run `vendor/bin/pint --dirty --format agent` only if PHP files are modified.

---

### Task 1: Add failing regression tests

**Files:**
- Create: `tests/JavaScript/form-visibility.test.js`
- Modify: `tests/Feature/FormShowViewRenderTest.php`

**Interfaces:**
- Consumes: current `window.formVisibility` Alpine factory and shared regular CRM form view.
- Produces: executable assertions for `reviewOpen`, `reviewFields`, `openReview()`, `closeReview()`, `confirmReview()`, and rendered modal hooks.

- [ ] **Step 1: Write the JavaScript test harness**

Read `resources/js/form-visibility.js` as source and evaluate it in a VM with minimal fake `HTMLFormElement`, `HTMLInputElement`, `HTMLSelectElement`, `HTMLTextAreaElement`, and DOM control objects. The fake form must expose `querySelectorAll()`, `checkValidity()`, and `reportValidity()` so the test exercises the real Alpine factory methods without adding a browser dependency.

- [ ] **Step 2: Write the failing review-state tests**

Cover these behaviors:

```js
test('opens review rows without submitting and excludes internal or disabled controls', () => {
    const form = createForm([
        textInput('cardholder_name', 'Cardholder Name', 'Ada Lovelace'),
        selectInput('account_type', 'Account Type', 'savings', [['savings', 'Savings']]),
        checkboxInput('marketing_opt_in', 'Marketing Opt In', false),
        textInput('phone_number', 'Phone Number', '15551234567', { type: 'hidden' }),
        textInput('hidden_field', 'Hidden Field', 'secret', { disabled: true }),
    ]);
    const component = loadFormVisibility({ form });

    component.openReview();

    assert.equal(component.reviewOpen, true);
    assert.deepEqual(component.reviewFields, [
        { label: 'Cardholder Name', value: 'Ada Lovelace' },
        { label: 'Account Type', value: 'Savings' },
        { label: 'Marketing Opt In', value: 'No' },
    ]);
});

test('cancel preserves values and confirm delegates the save', async () => {
    const form = createForm([textInput('customer_name', 'Customer Name', 'Ada')]);
    const component = loadFormVisibility({ form });
    let submits = 0;
    component.submitForm = async () => { submits += 1; };

    component.openReview();
    component.closeReview();
    assert.equal(component.reviewOpen, false);
    assert.equal(form.controls[0].value, 'Ada');

    component.openReview();
    await component.confirmReview();
    assert.equal(submits, 1);
});
```

Adapt the helper objects to the actual DOM access used by the implementation, but keep the assertions focused on user-visible review behavior rather than implementation details.

- [ ] **Step 3: Extend the PHP render tests**

In the existing full-page and widget render tests, assert the response contains `@submit.prevent="openReview()"`, `x-show="reviewOpen"`, `role="dialog"`, `aria-modal="true"`, `Back to Form`, and `Confirm &amp; Save`.

- [ ] **Step 4: Run the tests and verify the expected red state**

Run:

```powershell
node --test tests/JavaScript/form-visibility.test.js
php artisan test --compact tests/Feature/FormShowViewRenderTest.php
```

Expected: the new JavaScript tests fail because review state/methods do not exist, and the PHP view tests fail because the modal hooks are not rendered yet.

### Task 2: Implement review state and formatting

**Files:**
- Modify: `resources/js/form-visibility.js`

**Interfaces:**
- Consumes: form controls and existing `collectFormValues()`/`submitForm()` methods.
- Produces: `reviewOpen`, `reviewFields`, `openReview()`, `closeReview()`, and `confirmReview()` for the Blade view.

- [ ] **Step 1: Add the minimal state and excluded-field list**

Initialize `reviewOpen: false` and `reviewFields: []`. Exclude `_token`, `campaign`, `form_type`, `lead_id`, `phone_number`, `request_id`, `agent`, `id`, `created_at`, and `updated_at` from review rows. Skip controls that are hidden inputs, disabled, or otherwise not eligible for the current submission.

- [ ] **Step 2: Add review-row collection and formatting**

Group controls by `normalizeFieldName(element.name)` so checkbox arrays and repeated controls produce one logical row. Resolve labels from associated `<label>`, enclosing `<label>`, or the nearest fieldset/field wrapper. Format:

- text, date, textarea, and number values as entered, with `—` for empty values;
- select values from selected option text;
- multiselect values as comma-separated selected option text;
- non-array checkboxes as `Yes` or `No`;
- checkbox arrays as comma-separated selected option labels;
- percentage values with `%` when the form control's adjacent suffix is `%`.

- [ ] **Step 3: Add review open/cancel/confirm methods**

`openReview()` must guard with `checkValidity()` and `reportValidity()`, collect the current payload and review rows, then set `reviewOpen = true` without calling Axios. `closeReview()` must only set `reviewOpen = false`. `confirmReview()` must re-check validity, close review, and return `submitForm()` so all existing submission behavior stays in one method.

- [ ] **Step 4: Run the JavaScript and PHP tests green**

Run the commands from Task 1. Expected: all new review-state tests and form view render tests pass.

### Task 3: Add the accessible review modal

**Files:**
- Modify: `resources/views/forms/_content.blade.php`

**Interfaces:**
- Consumes: `reviewOpen`, `reviewFields`, `openReview()`, `closeReview()`, and `confirmReview()` from `formVisibility`.
- Produces: a review modal in both full-page and widget form surfaces.

- [ ] **Step 1: Change the form submit hook**

Replace `@submit.prevent="submitForm()"` with `@submit.prevent="openReview()"`. Keep the submit button as `type="submit"` so browser constraint validation still runs before the submit event.

- [ ] **Step 2: Render the review modal inside the shared form partial**

Add an `x-cloak` modal backdrop with `x-show="reviewOpen"`, `x-trap.noscroll="reviewOpen"`, `role="dialog"`, `aria-modal="true"`, and an `aria-labelledby` heading. Render `reviewFields` with `x-for`, use a scrollable list for long forms, and add:

```html
<button type="button" @click="closeReview()">Back to Form</button>
<button type="button" @click="confirmReview()" :disabled="submitting">Confirm &amp; Save</button>
```

The close icon must also call `closeReview()` and have an accessible label. Modal buttons must be `type="button"` so they cannot trigger an accidental second form submit.

- [ ] **Step 3: Run the focused tests and build**

Run:

```powershell
node --test tests/JavaScript/form-visibility.test.js
php artisan test --compact tests/Feature/FormShowViewRenderTest.php
npm run build
```

Expected: all commands exit successfully and Vite emits the regular CRM assets.

### Task 4: Browser verification and handoff

**Files:**
- No additional source files.

- [ ] **Step 1: Start the existing local app workflow**

Use the repository's available server/Vite workflow and identify a regular CRM form URL. Use an existing authenticated browser session if available; do not alter application data beyond one intentional test submission.

- [ ] **Step 2: Exercise the target flow**

Flow under test: regular CRM form route → fill a required field → click `Save Record` → verify the review modal and readable values with no submission request → click `Back to Form` → verify values remain → reopen and click `Confirm & Save` → verify the existing save success state.

- [ ] **Step 3: Run browser health checks**

Capture page identity, meaningful DOM content, no framework error overlay, console errors/warnings, screenshot evidence, and the interaction state transitions. Check a desktop viewport and a narrow mobile viewport when practical.

- [ ] **Step 4: Run final verification and inspect the diff**

Run the focused tests, `npm run build`, `git diff --check`, and `git status --short`. If any PHP file was changed, also run `vendor/bin/pint --dirty --format agent`. Only then mark the implementation complete.
