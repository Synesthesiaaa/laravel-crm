# Campaign-Linked VICIdial Widget Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the persistent VICIdial widget follow the active CRM campaign and use that campaign's existing configured VICIdial server.

**Architecture:** Use the CRM session campaign as the server-rendered widget bootstrap value. During soft navigation, synchronize the persistent document campaign dataset and emit a `crm-campaign-changed` browser event; the widget resets its local session/iframe state and adopts the new campaign before the next login or status request. Keep all server selection in the existing `VicidialServerRepository` path.

**Tech Stack:** Laravel 12, Blade, Alpine.js 3, Vite, vanilla browser events, PHPUnit 11, Playwright MCP.

## Global Constraints

- No database migration, new dependency, or new CRM-to-VICIdial mapping field.
- Preserve the existing `vicidial_servers.campaign_code` active/default/priority resolution.
- Never reuse a previous campaign's iframe URL, verification timers, or logged-in store state after a CRM campaign change.
- Keep existing user-default/config fallback when no CRM campaign is present.
- Modified PHP must be formatted with `vendor/bin/pint --dirty --format agent`.
- Every changed behavior must have PHPUnit coverage or a documented browser verification where no JavaScript test harness exists.

---

### Task 1: Add failing regression coverage for CRM campaign precedence and soft-navigation contract

**Files:**
- Modify: `tests/Feature/PhoneWidgetTest.php`
- Modify: `tests/Feature/ViewLifecycleRenderTest.php`

**Interfaces:**
- Consumes: Existing `phone-widget` Blade render and `soft-navigate.js` source assertions.
- Produces: Failing tests that define CRM campaign precedence and the browser event contract.

- [ ] **Step 1: Change the phone widget test to assert CRM precedence**

  In `test_phone_widget_boots_with_synced_campaign_without_selector`, keep both `campaign=crmdefault` and `vicidial_campaign=softcamp`, then assert the rendered widget contains `crmdefault`, does not contain `softcamp`, and still has no campaign selector or removed API endpoints.

- [ ] **Step 2: Add source-contract assertions for campaign synchronization**

  Extend `test_soft_navigation_script_handles_marked_get_forms` with assertions for `data-campaign`, `crm-campaign-changed`, and the campaign-change event detail. This keeps the event contract covered without adding a JavaScript test runner dependency.

- [ ] **Step 3: Run the focused tests and verify the new assertions fail**

  Run:

  ```text
  php artisan test --compact tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php
  ```

  Expected: the CRM-precedence and new soft-navigation assertions fail against the current implementation.

- [ ] **Step 4: Commit the test-first changes**

  ```text
  git add tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php
  git commit -m "test: define campaign-linked vicidial widget behavior"
  ```

### Task 2: Make server-rendered layout and widget bootstrap use the CRM campaign

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/partials/phone-widget.blade.php`

**Interfaces:**
- Consumes: `session('campaign')`, existing user default, and existing VICIdial config fallback.
- Produces: Consistent `data-campaign`, `data-campaign-name`, `data-telephony-campaign`, and `phoneWidget` boot campaign values.

- [ ] **Step 1: Update the layout campaign fallback chain**

  Resolve the layout telephony campaign from `session('campaign')` first, then `session('vicidial_campaign')`, the authenticated user's `default_campaign`, and `config('vicidial.default_campaign', 'mbsales')`. Add the campaign display name to the body dataset for event messages.

- [ ] **Step 2: Update the phone widget boot fallback chain**

  Resolve `$defaultVicidialCampaign` from `session('campaign')` first, then retain the same fallback chain. Keep the existing phone credentials and panel dimensions unchanged.

- [ ] **Step 3: Run the focused PHPUnit tests**

  ```text
  php artisan test --compact tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php
  ```

  Expected: the CRM-precedence and Blade bootstrap assertions pass; event assertions remain failing until Task 3.

- [ ] **Step 4: Commit the server-rendered bootstrap changes**

  ```text
  git add resources/views/layouts/app.blade.php resources/views/partials/phone-widget.blade.php
  git commit -m "feat: bootstrap vicidial widget from crm campaign"
  ```

### Task 3: Add a safe VICIdial client reset operation for campaign changes

**Files:**
- Modify: `resources/js/vicidial-session.js`
- Modify: `resources/js/phone-widget.js`

**Interfaces:**
- Consumes: Existing `cancelVerify`, iframe cleanup, Alpine `vicidial` store, and `phoneWidget` Alpine context.
- Produces: `window.VicidialSession.resetForCampaignChange(ctx)` and a widget handler that resets stale session state before adopting a new campaign.

- [ ] **Step 1: Add `resetForCampaignChange` to the VICIdial session module**

  Implement a small public function that calls `cancelVerify(ctx)`, clears `state.inflight`, clears the iframe, sets the widget phase to `idle`, and sets loading to `false`. Export it on `window.VicidialSession` without changing existing login/logout behavior.

- [ ] **Step 2: Add the phone widget campaign-change handler**

  Add a handler that:

  ```text
  oldCampaign = this.telephonyCampaign()
  newCampaign = trimmed event.detail.campaign
  if newCampaign is empty or equals oldCampaign: return
  wasActive = store.loggedIn || store.status is transitional/usable || this.vici.phase is transitional
  resetForCampaignChange(this)
  this.vici.vici_campaign = newCampaign
  this.vici.last_iframe_url = null
  store.loggedIn = false
  store.status = 'logged_out'
  store.pauseCode = ''
  store.queueCount = 0
  store.campaign = newCampaign
  clear ingroup state
  if wasActive: show a toast requiring login for the new campaign
  ```

  Do not silently reuse the old iframe/session, and do not add a new backend endpoint.

- [ ] **Step 3: Register the handler once during widget initialization**

  Register `crm-campaign-changed` in `init()` and keep the existing workspace, resize, and shortcut listeners intact. Update `telephonyCampaign()` so the widget's synchronized `vici.vici_campaign` remains the primary runtime value while the body dataset remains the fallback.

- [ ] **Step 4: Build the frontend bundle**

  ```text
  npm run build
  ```

  Expected: Vite completes without JavaScript syntax or import errors.

### Task 4: Synchronize campaign datasets and emit the change event during soft navigation

**Files:**
- Modify: `resources/js/soft-navigate.js`
- Modify: `tests/Feature/ViewLifecycleRenderTest.php`

**Interfaces:**
- Consumes: Fetched document body datasets and the persistent `document.body`.
- Produces: `crm-campaign-changed` with `{ campaign, campaignName }`, only for a real campaign change.

- [ ] **Step 1: Add a fetched-document campaign synchronizer**

  Add a helper that reads `doc.body.dataset.campaign` and `doc.body.dataset.campaignName`. If the fetched campaign is empty, return without changing state. Otherwise update the persistent body `data-campaign`, `data-campaign-name`, and `data-telephony-campaign` values to the fetched CRM campaign.

- [ ] **Step 2: Dispatch the event only when the campaign changes**

  Compare the fetched campaign with the persistent body's previous campaign. When different, dispatch `new CustomEvent('crm-campaign-changed', { detail: { campaign, campaignName } })` after the fetched page has been accepted and before the normal `soft-navigate` completion event. Do not dispatch duplicates for ordinary same-campaign navigation.

- [ ] **Step 3: Run the focused tests and frontend build**

  ```text
  php artisan test --compact tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php
  npm run build
  ```

  Expected: all focused PHPUnit assertions pass and Vite builds successfully.

- [ ] **Step 4: Commit the runtime synchronization changes**

  ```text
  git add resources/js/soft-navigate.js resources/js/phone-widget.js resources/js/vicidial-session.js tests/Feature/ViewLifecycleRenderTest.php
  git commit -m "feat: reset vicidial widget on crm campaign change"
  ```

### Task 5: Verify campaign-specific server behavior and rendered navigation

**Files:**
- Inspect: `app/Repositories/VicidialServerRepository.php`
- Inspect: `app/Services/Telephony/VicidialSessionService.php`
- Inspect: `resources/views/admin/vicidial_servers.blade.php`
- Verify: `tests/Feature/Admin/VicidialServerAdminTest.php`

**Interfaces:**
- Consumes: Existing campaign-to-server records and current session APIs.
- Produces: Evidence that a new campaign login still resolves the configured campaign server without code changes to the repository.

- [ ] **Step 1: Run the affected backend test set**

  ```text
  php artisan test --compact tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php tests/Feature/Admin/VicidialServerAdminTest.php tests/Feature/Api/VicidialSessionApiTest.php
  ```

- [ ] **Step 2: Format modified PHP files**

  ```text
  vendor/bin/pint --dirty --format agent
  ```

- [ ] **Step 3: Re-run focused tests after formatting**

  ```text
  php artisan test --compact tests/Feature/PhoneWidgetTest.php tests/Feature/ViewLifecycleRenderTest.php tests/Feature/Admin/VicidialServerAdminTest.php tests/Feature/Api/VicidialSessionApiTest.php
  ```

- [ ] **Step 4: Verify the browser flow with Playwright MCP**

  Use the Browser skill and test this flow:

  ```text
  authenticated campaign A page -> navigate via same-origin soft navigation to campaign B -> inspect body datasets and phone widget state -> open widget -> confirm it identifies campaign B and requires a fresh VICIdial login
  ```

  Capture page identity, non-blank DOM, no framework overlay, console health, and an interaction proof. If the local app is not running or authentication/data cannot be established, report the exact blocker instead of claiming the flow passed.

- [ ] **Step 5: Review final diff and status**

  ```text
  git diff --check
  git status --short
  ```

  Confirm no unrelated files changed and all OpenSpec tasks are marked complete before claiming completion.
