# Agent Form Date Read-only Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the date on regular CRM forms read-only for Agents and force Agent submissions to use the current server date while preserving editable dates for elevated roles.

**Architecture:** Reuse the existing shared `forms/_content.blade.php` view and `x-form.input` component for role-specific rendering. Normalize the date in `FormSubmissionRequest::prepareForValidation()` using the exact `User::ROLE_AGENT` role so the controller and submission service receive a trusted date. Agent Capture webforms are not changed.

**Tech Stack:** Laravel 12, Blade, PHPUnit 11, Carbon test clock, Laravel Pint.

## Global Constraints

- Apply the behavior only to regular CRM forms and the quick-form widget embed.
- Keep the date visible to Agents; only editability changes.
- Use `User::ROLE_AGENT`, not the broad `User::isAgent()` helper.
- Do not add dependencies, routes, migrations, or database columns.
- Every production change must have a focused PHPUnit regression test.

---

### Task 1: Add the failing view regression tests

**Files:**
- Modify: `tests/Feature/FormShowViewRenderTest.php`

**Interfaces:**
- Consumes: existing form and user setup used by the view-render tests.
- Produces: assertions covering Agent read-only rendering and elevated-role editability.

- [ ] **Step 1: Add an Agent rendering test**

Add a PHPUnit test that creates a minimal campaign/form, requests the form as `User::ROLE_AGENT`, and asserts the rendered date input contains `name="date"` and `readonly`.

- [ ] **Step 2: Add an elevated-role rendering test**

Add a test using an Admin or Team Leader and assert the rendered date input contains `name="date"` but does not contain the read-only attribute for that input.

- [ ] **Step 3: Run the focused view tests and confirm RED**

Run:

```powershell
php artisan test --compact tests/Feature/FormShowViewRenderTest.php
```

Expected: the new Agent and elevated-role assertions fail because the current shared view does not pass a role-specific `readonly` prop.

### Task 2: Add the failing server-side date regression test

**Files:**
- Modify: `tests/Feature/FormSubmissionTest.php`

**Interfaces:**
- Consumes: the existing dynamic form submission setup, database assertions, and Carbon test clock.
- Produces: proof that a forged Agent date cannot alter the persisted record date.

- [ ] **Step 1: Add the forged-date test**

Freeze time to a known date, submit a valid regular CRM form as `User::ROLE_AGENT` with a different valid date, assert the request succeeds, and assert the stored row has the frozen current date.

- [ ] **Step 2: Run the focused submission test and confirm RED**

Run:

```powershell
php artisan test --compact tests/Feature/FormSubmissionTest.php --filter=agent
```

Expected: the new assertion fails because the current request preserves the forged date.

### Task 3: Implement the minimal production behavior

**Files:**
- Modify: `resources/views/forms/_content.blade.php`
- Modify: `app/Http/Requests/FormSubmissionRequest.php`

**Interfaces:**
- Consumes: the authenticated request user and `User::ROLE_AGENT`.
- Produces: read-only HTML for Agent users and a normalized `date` value in validated request data.

- [ ] **Step 1: Make the shared date input role-aware**

In `forms/_content.blade.php`, pass `:readonly="auth()->user()?->role === \App\Models\User::ROLE_AGENT"` to the existing date `x-form.input`. Do not change the date label, value, required flag, Agent Capture view, or non-date fields.

- [ ] **Step 2: Normalize Agent dates before validation**

In `FormSubmissionRequest::prepareForValidation()`, after trimming the request data, replace `$merged['date']` with `now()->toDateString()` when `$this->user()?->role === User::ROLE_AGENT`, then merge the prepared data as usual.

- [ ] **Step 3: Run the focused tests and confirm GREEN**

Run:

```powershell
php artisan test --compact tests/Feature/FormShowViewRenderTest.php tests/Feature/FormSubmissionTest.php
```

Expected: all tests in both files pass, including the new role-specific and forged-date cases.

### Task 4: Format and validate the changed behavior

**Files:**
- Modify: `openspec/changes/agent-form-date-readonly/tasks.md`

- [ ] **Step 1: Format modified PHP files**

Run:

```powershell
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Re-run the focused PHPUnit tests**

Run the Task 3 command and confirm zero failures.

- [ ] **Step 3: Verify the change in the browser**

Load a regular CRM form as an Agent and inspect the date input for `readonly`; load it as an elevated role if test credentials are available. Confirm no relevant browser console errors.

- [ ] **Step 4: Mark implementation tasks complete**

Update the OpenSpec task checkboxes only after the corresponding tests and formatting checks pass.
