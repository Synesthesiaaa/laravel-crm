# Agent Screen Access Toggle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Super Admin-controlled, disabled-by-default feature flag that hides and blocks Agent Screen and Agent Capture access for non-Super Admin users.

**Architecture:** Reuse `SystemSetting` and `TelephonyFeatureService` for persistence, cache invalidation, and activity logging. Use the existing `telephony_feature` middleware for request enforcement, add browser-aware denial handling, and conditionally render sidebar/search links from the same feature flag.

**Tech Stack:** Laravel 12, PHP 8.5, PHPUnit 11, Blade, Alpine/Tailwind UI, Laravel Pint, Playwright.

## Global Constraints

- The new Agent Screen flag defaults to disabled; existing telephony flags retain their enabled-by-default behavior.
- Super Admin configuration access remains available.
- Regular CRM forms and Agent Screen field data are unchanged.
- No new dependencies or database tables are introduced.
- Tests are PHPUnit classes and must be written before production changes.

---

### Task 1: Prove the feature-flag and access contract with failing tests

**Files:**
- Create or modify: `tests/Unit/Services/TelephonyFeatureServiceTest.php`
- Create: `tests/Feature/Admin/AgentScreenAccessConfigurationTest.php`
- Create: `tests/Feature/AgentScreenAccessTest.php`

- [ ] **Step 1: Write service tests**

Assert that `app(TelephonyFeatureService::class)->isEnabled('agent_screen_access')` is `false` with no setting, and that `updateMany(['agent_screen_access' => '1'])` persists an enabled `SystemSetting` and returns `true` on the next check.

- [ ] **Step 2: Write configuration and access tests**

Cover the Super Admin configuration form, disabled non-Super Admin sidebar/search/direct-access behavior, enabled page access, and Super Admin configuration availability while disabled. Use existing factories and session setup patterns.

- [ ] **Step 3: Run the tests and verify the expected red state**

Run:

```powershell
php artisan test --compact tests/Unit/Services/TelephonyFeatureServiceTest.php tests/Feature/Admin/AgentScreenAccessConfigurationTest.php tests/Feature/AgentScreenAccessTest.php
```

Expected: failures caused by the missing `agent_screen_access` behavior, not test syntax or environment errors.

### Task 2: Implement persistence and Super Admin configuration

**Files:**
- Modify: `app/Services/TelephonyFeatureService.php`
- Modify: `resources/views/admin/configuration.blade.php`

- [ ] **Step 1: Add the feature key and per-feature defaults**

Keep the existing feature keys enabled by default and add `agent_screen_access` with a disabled default. Ensure `getAll()`, `isEnabled()`, `updateMany()`, cache flushing, and activity logging include the new key.

- [ ] **Step 2: Add the configuration checkbox**

Add a labeled checkbox to the existing Telephony Features form, using the same `features[...]` input convention and explaining that it controls Agent Screen and Agent Capture access.

- [ ] **Step 3: Run the service and configuration tests**

Run:

```powershell
php artisan test --compact tests/Unit/Services/TelephonyFeatureServiceTest.php tests/Feature/Admin/AgentScreenAccessConfigurationTest.php
```

Expected: PASS.

### Task 3: Implement route enforcement and UI visibility

**Files:**
- Modify: `app/Http/Middleware/EnsureTelephonyFeatureEnabled.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/sidebar.blade.php`
- Modify: `app/Http/Controllers/Api/GlobalSearchController.php`

- [ ] **Step 1: Make middleware denial response-aware**

Return the existing JSON payload for API/JSON requests and an HTTP 403 browser response for HTML requests. Preserve the Super Admin bypass.

- [ ] **Step 2: Gate all Agent Screen surfaces**

Apply `telephony_feature:agent_screen_access` to the Agent Screen page, Agent Capture webform page, and Agent Capture submission route.

- [ ] **Step 3: Hide links using the same feature flag**

Conditionally render the sidebar Agent Screen entry and omit the global-search Agent Screen result for disabled non-Super Admin users. Preserve the Super Admin configuration navigation.

- [ ] **Step 4: Run the full affected access test**

Run:

```powershell
php artisan test --compact tests/Feature/AgentScreenAccessTest.php
```

Expected: PASS.

### Task 4: Format and verify the complete change

**Files:**
- Verify all modified PHP and Blade files listed above.

- [ ] **Step 1: Format PHP changes**

Run:

```powershell
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 2: Run all affected tests**

Run:

```powershell
php artisan test --compact tests/Unit/Services/TelephonyFeatureServiceTest.php tests/Feature/Admin/AgentScreenAccessConfigurationTest.php tests/Feature/AgentScreenAccessTest.php tests/Feature/ClickToCallViewRenderTest.php tests/Feature/AgentCaptureWebformTest.php
```

- [ ] **Step 3: Verify the browser flow**

Start the application using the project’s existing development command, sign in as a regular user and Super Admin, and use Playwright to confirm the Agent Screen link is absent/present according to the flag, the configuration checkbox saves, and direct disabled access returns 403.

- [ ] **Step 4: Sync and archive OpenSpec**

Run the OpenSpec sync and archive workflows after the implementation and verification evidence is complete.
