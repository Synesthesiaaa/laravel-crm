# VICIdial Agent Capture Webform Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a campaign-configured, authenticated VICIdial webform that renders Agent Screen Fields, prefills them from VICIdial call variables, and saves through the existing Agent Capture flow.

**Architecture:** Store the selected CRM form as campaign metadata, but keep Agent Screen Fields as the webform field source and Agent Capture Records as the persistence path. A small service will own campaign configuration lookup, VICIdial URL-template generation, and query-to-field prefill mapping; a dedicated controller/view will render a frame-safe page, while an Alpine component will submit to the existing capture API.

**Tech Stack:** Laravel 12, PHP 8.5, Eloquent, Blade, Alpine.js 3, Vite/Tailwind CSS 4, PHPUnit 11, Laravel RefreshDatabase tests, and Playwright browser verification.

## Global Constraints

- The webform route SHALL require the existing CRM `auth` middleware; no public or shared-secret capture route is added.
- Agent Screen Fields SHALL remain the only rendered and saved fields for this flow.
- The selected CRM form SHALL identify the campaign's specific webform but SHALL NOT replace Agent Capture Records with Form Submission tables.
- The route campaign is authoritative; query-string values SHALL NOT change the campaign, selected form, or authenticated agent attribution.
- `get` and `both` mappings prefill fields; `post` and `both` mappings continue to use the existing VICIdial writeback behavior.
- Do not add dependencies or alter the existing normal Form Controller/Form Submission flow.
- Use PHPUnit test classes, `php artisan test --compact`, and `vendor/bin/pint --dirty --format agent` for PHP changes.
- Keep the existing full `/agent` page available; the new generated URL targets only the slim webform.

---

### Task 1: Persist the campaign's selected webform

**Files:**
- Create: `database/migrations/2026_07_22_000001_add_agent_webform_form_id_to_campaigns_table.php`
- Modify: `app/Models/Campaign.php`
- Test: `tests/Feature/Admin/AgentScreenAdminTest.php`

**Interfaces:**
- Produces nullable `Campaign::$agent_webform_form_id` and `Campaign::agentWebformForm(): BelongsTo` for the admin controller and webform service.
- The relation resolves only a `Form` record selected for that campaign; soft-deleted forms are excluded by the normal Eloquent relation.

- [ ] **Step 1: Write the failing model/configuration test**

Add this PHPUnit method to `Tests\Feature\Admin\AgentScreenAdminTest`:

```php
public function test_campaign_can_reference_its_selected_agent_webform(): void
{
    $form = Form::query()->create([
        'campaign_code' => 'mbsales',
        'form_code' => 'ezycash',
        'name' => 'EzyCash',
        'table_name' => 'ezycash',
        'is_active' => true,
    ]);

    $campaign = Campaign::query()->where('code', 'mbsales')->firstOrFail();
    $campaign->update(['agent_webform_form_id' => $form->id]);

    $this->assertSame($form->id, $campaign->fresh()->agentWebformForm?->id);
}
```

Import `App\Models\Form` in the test file. Run `php artisan test --compact tests/Feature/Admin/AgentScreenAdminTest.php --filter=test_campaign_can_reference_its_selected_agent_webform`; it must fail because the column and relation do not exist.

- [ ] **Step 2: Add the schema migration**

Create the migration with the exact reversible column and foreign key:

```php
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->foreignId('agent_webform_form_id')
                ->nullable()
                ->after('display_order')
                ->constrained('forms')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropForeign(['agent_webform_form_id']);
            $table->dropColumn('agent_webform_form_id');
        });
    }
};
```

- [ ] **Step 3: Add the Eloquent fillable field and relation**

In `Campaign`, import `BelongsTo`, add `agent_webform_form_id` to `$fillable`, cast it as an integer, and add:

```php
public function agentWebformForm(): BelongsTo
{
    return $this->belongsTo(Form::class, 'agent_webform_form_id');
}
```

- [ ] **Step 4: Run the focused test and format PHP**

Run `php artisan test --compact tests/Feature/Admin/AgentScreenAdminTest.php --filter=test_campaign_can_reference_its_selected_agent_webform`; expect PASS. Then run `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 5: Commit the persistence unit**

```bash
git add database/migrations/2026_07_22_000001_add_agent_webform_form_id_to_campaigns_table.php app/Models/Campaign.php tests/Feature/Admin/AgentScreenAdminTest.php
git commit -m "Add campaign agent webform selection"
```

### Task 2: Add the configuration, URL-template, and prefill service

**Files:**
- Create: `app/Services/AgentCaptureWebformService.php`
- Test: `tests/Unit/Services/AgentCaptureWebformServiceTest.php`

**Interfaces:**
- `AgentCaptureWebformService::configuration(string $campaignCode): ?array` returns `['campaign' => Campaign, 'form' => Form, 'fields' => Collection<int, AgentScreenField>]` only when the campaign and selected form are active and belong together.
- `AgentCaptureWebformService::prefill(Collection $fields, Request $request): array` returns `['lead_id' => ?string, 'phone_number' => ?string, 'fields' => array<string, mixed>]`.
- `AgentCaptureWebformService::vicidialUrl(string $campaignCode, Collection $fields): string` returns a `VAR`-prefixed URL with unique `--A--field--B--` placeholders for `lead_id`, `phone_number`, and `get`/`both` mappings.

- [ ] **Step 1: Write failing service tests**

Create `tests/Unit/Services/AgentCaptureWebformServiceTest.php` with `RefreshDatabase` and these cases:

```php
public function test_vicidial_url_includes_required_and_get_mappings_but_not_post_only_mappings(): void
{
    $fields = collect([
        new AgentScreenField(['field_key' => 'first_name_capture', 'vici_field' => 'first_name', 'direction' => 'get']),
        new AgentScreenField(['field_key' => 'email_capture', 'vici_field' => 'email', 'direction' => 'both']),
        new AgentScreenField(['field_key' => 'notes', 'vici_field' => 'comments', 'direction' => 'post']),
        new AgentScreenField(['field_key' => 'local_only', 'vici_field' => 'status', 'direction' => 'none']),
        new AgentScreenField(['field_key' => 'duplicate', 'vici_field' => 'email', 'direction' => 'get']),
    ]);

    $url = app(AgentCaptureWebformService::class)->vicidialUrl('mbsales', $fields);

    $this->assertStringStartsWith('VAR'.url('/agent-webforms/mbsales'), $url);
    $this->assertSame(1, substr_count($url, 'email=--A--email--B--'));
    $this->assertStringContainsString('first_name=--A--first_name--B--', $url);
    $this->assertStringNotContainsString('comments=--A--comments--B--', $url);
    $this->assertStringNotContainsString('status=--A--status--B--', $url);
}

public function test_prefill_maps_vicidial_query_names_to_agent_field_keys(): void
{
    $fields = collect([
        new AgentScreenField(['field_key' => 'customer_name', 'vici_field' => 'first_name', 'direction' => 'get']),
        new AgentScreenField(['field_key' => 'customer_email', 'vici_field' => 'email', 'direction' => 'post']),
        new AgentScreenField(['field_key' => 'comments', 'vici_field' => 'comments', 'direction' => 'both']),
    ]);

    $request = Request::create('/agent-webforms/mbsales', 'GET', [
        'lead_id' => '123',
        'phone_number' => '15551234567',
        'first_name' => 'Ada',
        'email' => 'ada@example.test',
        'comments' => 'Call back',
    ]);

    $prefill = app(AgentCaptureWebformService::class)->prefill($fields, $request);

    $this->assertSame('123', $prefill['lead_id']);
    $this->assertSame('15551234567', $prefill['phone_number']);
    $this->assertSame('Ada', $prefill['fields']['customer_name']);
    $this->assertArrayNotHasKey('customer_email', $prefill['fields']);
    $this->assertSame('Call back', $prefill['fields']['comments']);
}
```

Import `AgentScreenField`, `AgentCaptureWebformService`, `Illuminate\Http\Request`, and `Illuminate\Support\Collection`. Run the two filtered tests; they must fail because the service and route do not exist.

- [ ] **Step 2: Implement configuration lookup**

Implement `configuration()` with an active campaign query, an active selected form constrained by `campaign_code`, and ordered Agent Screen Fields:

```php
$campaign = Campaign::query()
    ->where('code', $campaignCode)
    ->where('is_active', true)
    ->with(['agentWebformForm' => fn (Builder $query) => $query->active()])
    ->first();

if (! $campaign || ! $campaign->agentWebformForm || $campaign->agentWebformForm->campaign_code !== $campaign->code) {
    return null;
}

return [
    'campaign' => $campaign,
    'form' => $campaign->agentWebformForm,
    'fields' => AgentScreenField::forCampaign($campaign->code)->ordered()->get(),
];
```

Import `Illuminate\Database\Eloquent\Builder` for the eager-load closure. This check ensures a moved, deleted, inactive, or cross-campaign form never enables the webform.

- [ ] **Step 3: Implement mapping and URL generation**

Use only fields with a non-empty `vici_field` and direction `get` or `both`; de-duplicate by source parameter while preserving field order. Build the query string from `rawurlencode('--A--'.$field.'--B--')`, prepend `VAR`, and use `url('/agent-webforms/'.$campaignCode)` so the service remains independently testable before the controller route is registered. `prefill()` must copy only incoming source values for `get`/`both` fields into their Agent Screen `field_key`; keep `lead_id` and `phone_number` as separate metadata values.

- [ ] **Step 4: Run unit tests and format PHP**

Run `php artisan test --compact tests/Unit/Services/AgentCaptureWebformServiceTest.php`; expect PASS. Run `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 5: Commit the service unit**

```bash
git add app/Services/AgentCaptureWebformService.php tests/Unit/Services/AgentCaptureWebformServiceTest.php
git commit -m "Add agent capture webform mapping service"
```

### Task 3: Add administrator selection and copy-ready URL UI

**Files:**
- Create: `app/Http/Requests/Admin/SaveAgentScreenWebformRequest.php`
- Modify: `app/Http/Controllers/Admin/AgentScreenController.php`
- Modify: `resources/views/admin/agent_screen.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Admin/AgentScreenAdminTest.php`

**Interfaces:**
- `SaveAgentScreenWebformRequest` accepts `campaign_code` and `agent_webform_form_id` only for a super administrator.
- `AgentScreenController::saveWebform(SaveAgentScreenWebformRequest $request): RedirectResponse` persists the selected form and clears `CampaignService`'s `campaigns_with_forms` cache.
- The admin view receives `webformOptions`, `selectedWebformForm`, and `vicidialWebformUrl` for the selected campaign.

- [ ] **Step 1: Write failing admin tests**

Add tests covering a valid selection and cross-campaign/inactive rejection:

```php
public function test_admin_can_select_a_campaign_webform_and_copy_url_is_rendered(): void
{
    $form = Form::query()->create([
        'campaign_code' => 'mbsales', 'form_code' => 'ezycash', 'name' => 'EzyCash',
        'table_name' => 'ezycash', 'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->withSession($this->campaignSession())
        ->post(route('admin.agent-screen.webform.update'), [
            'campaign_code' => 'mbsales',
            'agent_webform_form_id' => $form->id,
        ])
        ->assertRedirect(route('admin.agent-screen.index', ['campaign' => 'mbsales']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('campaigns', ['code' => 'mbsales', 'agent_webform_form_id' => $form->id]);

    $this->get(route('admin.agent-screen.index', ['campaign' => 'mbsales']))
        ->assertOk()
        ->assertSee('VICIdial Web Form URL', false)
        ->assertSee('agent-webforms/mbsales', false);
}

public function test_admin_cannot_select_a_form_from_another_campaign(): void
{
    Campaign::factory()->create(['code' => 'othercamp', 'name' => 'Other Campaign']);
    $form = Form::query()->create([
        'campaign_code' => 'othercamp', 'form_code' => 'other', 'name' => 'Other',
        'table_name' => 'other', 'is_active' => true,
    ]);

    $this->actingAs($this->superAdmin)
        ->withSession($this->campaignSession())
        ->post(route('admin.agent-screen.webform.update'), [
            'campaign_code' => 'mbsales',
            'agent_webform_form_id' => $form->id,
        ])
        ->assertSessionHasErrors('agent_webform_form_id');
}
```

Run both filtered tests; they must fail until the route, request, controller, and view are added.

- [ ] **Step 2: Add cross-campaign active-form validation**

Create `SaveAgentScreenWebformRequest` with super-admin authorization and an `exists:campaigns,code` campaign rule. Constrain the form rule with `Rule::exists('forms', 'id')->where(fn ($query) => $query->where('campaign_code', $this->string('campaign_code')->toString())->where('is_active', true))` so inactive or cross-campaign forms fail before the controller.

- [ ] **Step 3: Add the admin route and controller action**

Inside the existing Super Admin route group, add:

```php
Route::post('agent-screen/webform', [AgentScreenController::class, 'saveWebform'])
    ->name('agent-screen.webform.update');
```

Inject `AgentCaptureWebformService` and `Form` into `AgentScreenController`. In `index()`, query active forms for the selected campaign, load the selected campaign's `agentWebformForm`, and generate the URL from ordered fields. Implement:

```php
public function saveWebform(SaveAgentScreenWebformRequest $request): RedirectResponse
{
    $validated = $request->validated();
    $campaign = Campaign::query()->where('code', $validated['campaign_code'])->firstOrFail();
    $campaign->update(['agent_webform_form_id' => (int) $validated['agent_webform_form_id']]);
    $this->campaignService->clearCampaignsCache();

    return redirect()
        ->route('admin.agent-screen.index', ['campaign' => $campaign->code])
        ->with('success', 'VICIdial webform configured.');
}
```

- [ ] **Step 4: Add the configuration card to the existing Blade view**

Place a card below the campaign selector and above “Add Capture Field” with a POST form, hidden `campaign_code`, a select of `$webformOptions`, a save button, and a readonly URL input. Render the URL only when `$vicidialWebformUrl` is non-null; provide a small `navigator.clipboard.writeText(...)` copy button that leaves the URL visible for manual paste into VICIdial. Keep all existing Agent Screen Field editing markup unchanged.

- [ ] **Step 5: Run focused admin tests and format PHP**

Run `php artisan test --compact tests/Feature/Admin/AgentScreenAdminTest.php`; expect all tests PASS. Run `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 6: Commit the admin configuration unit**

```bash
git add app/Http/Requests/Admin/SaveAgentScreenWebformRequest.php app/Http/Controllers/Admin/AgentScreenController.php resources/views/admin/agent_screen.blade.php routes/web.php tests/Feature/Admin/AgentScreenAdminTest.php
git commit -m "Add VICIdial webform campaign configuration"
```

### Task 4: Add the authenticated slim webform endpoint and server-rendered fields

**Files:**
- Create: `app/Http/Controllers/AgentCaptureWebformController.php`
- Create: `resources/views/agent/capture_webform.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AgentCaptureWebformTest.php`

**Interfaces:**
- `AgentCaptureWebformController::show(Request $request, string $campaign): View` renders a configured webform or an explicit no-configuration state.
- Route name: `agent-webforms.show`, URL: `/agent-webforms/{campaign}` under `auth` middleware only (not the session-selected `campaign` middleware).
- The view receives `$campaign`, `$campaignName`, `$formName`, `$fields`, `$prefill`, `$leadId`, and `$phoneNumber`.

- [ ] **Step 1: Write failing endpoint/view tests**

Create `tests/Feature/AgentCaptureWebformTest.php` using `RefreshDatabase`. Cover:

```php
public function test_webform_requires_crm_login(): void
{
    $this->get(route('agent-webforms.show', ['campaign' => 'mbsales']))
        ->assertRedirect(route('login'));
}

public function test_webform_renders_selected_form_name_and_maps_get_values(): void
{
    $form = Form::query()->create([
        'campaign_code' => 'mbsales', 'form_code' => 'ezycash', 'name' => 'EzyCash',
        'table_name' => 'ezycash', 'is_active' => true,
    ]);
    Campaign::query()->where('code', 'mbsales')->update(['agent_webform_form_id' => $form->id]);
    AgentScreenField::query()->create([
        'campaign_code' => 'mbsales', 'field_key' => 'customer_name',
        'vici_field' => 'first_name', 'direction' => 'get',
        'field_label' => 'Customer Name', 'field_order' => 1, 'field_width' => 'full',
    ]);
    AgentScreenField::query()->create([
        'campaign_code' => 'mbsales', 'field_key' => 'notes',
        'vici_field' => 'comments', 'direction' => 'post',
        'field_label' => 'Notes', 'field_order' => 2, 'field_width' => 'full',
    ]);

    $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
        ->get(route('agent-webforms.show', ['campaign' => 'mbsales']) + '?lead_id=123&phone_number=15551234567&first_name=Ada&comments=ignored');

    $response->assertOk()
        ->assertSee('EzyCash')
        ->assertSee('Customer Name')
        ->assertSee('value="Ada"', false)
        ->assertSee('value="123"', false)
        ->assertDontSee('value="ignored"', false)
        ->assertDontSee('agentScreen()', false);
}

public function test_unconfigured_campaign_renders_no_submit_state(): void
{
    $response = $this->actingAs(User::factory()->create(['role' => User::ROLE_AGENT]))
        ->get(route('agent-webforms.show', ['campaign' => 'mbsales']));

    $response->assertOk()
        ->assertSee('webform is not configured', false)
        ->assertDontSee('Save Record', false);
}
```

Use separate query-array arguments if the test helper does not accept the concatenated URL; the assertions must prove that route campaign data cannot be replaced by a query `campaign` value. Run the file; it must fail before the route/controller/view exist.

- [ ] **Step 2: Implement the authenticated controller**

Resolve `$configuration = $service->configuration($campaign)`. Pass the configuration data to the view when present. When absent, query the active campaign name and render the same view with `configured => false`, a configuration message, and no submit form. Never call `CampaignService::resolveCampaignForRequest()` because this route's path campaign must not overwrite the agent's normal CRM session campaign.

- [ ] **Step 3: Register the route**

Within the existing auth-only route group near the current Vicidial session routes (the `Route::middleware('auth')->group(...)` block that closes before the `auth,campaign` group), add:

```php
Route::get('agent-webforms/{campaign}', [AgentCaptureWebformController::class, 'show'])
    ->name('agent-webforms.show')
    ->where('campaign', '[a-z0-9_]+');
```

Import the controller at the top of `routes/web.php`.

- [ ] **Step 4: Render a frame-safe Blade view**

Create a standalone HTML document like `resources/views/forms/widget.blade.php`, loading the existing CSRF meta tag, app CSS, and app JS without `layouts.app`, sidebar, header, softphone, or global navigation. Render the selected form title and Agent Screen Fields in the same field types/order/widths as the current capture block. Use `@js($prefill['fields'][$field->field_key] ?? '')` for values, hidden `campaign_code`, `lead_id`, and `phone_number`, and a visible field wrapper for each conditional rule. When no fields exist, show a configuration message and no submit button.

- [ ] **Step 5: Run endpoint tests and format PHP**

Run `php artisan test --compact tests/Feature/AgentCaptureWebformTest.php`; expect PASS. Run `vendor/bin/pint --dirty --format agent`.

- [ ] **Step 6: Commit the endpoint/view unit**

```bash
git add app/Http/Controllers/AgentCaptureWebformController.php resources/views/agent/capture_webform.blade.php routes/web.php tests/Feature/AgentCaptureWebformTest.php
git commit -m "Render authenticated VICIdial agent webform"
```

### Task 5: Add Alpine submission behavior for Agent Capture Records

**Files:**
- Create: `resources/js/agent-capture-webform.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/agent/capture_webform.blade.php`

**Interfaces:**
- `window.agentCaptureWebform(boot): object` extends the existing `formVisibility()` state with `submitCapture()` and sends the exact Agent Capture API payload.
- The component posts `{campaign_code, call_session_id: null, lead_id, phone_number, capture_data, visible_fields}` to the named `/api/agent/capture` endpoint and leaves successful values in place.

- [ ] **Step 1: Add the failing browser-facing markup contract**

Extend `AgentCaptureWebformTest` with assertions for `x-data="agentCaptureWebform()``, `@submit.prevent="submitCapture()"`, `/api/agent/capture`, and `visible_fields`. Run the feature file and verify it fails because the component is not yet imported or rendered.

- [ ] **Step 2: Implement the component**

Create the component by spreading `window.formVisibility({ autosave: false })`, delegating `init(seed)` to the base object's `init.call(this, seed)`, and adding:

```js
async submitCapture() {
    const form = this.getFormElement();
    const captureData = {};
    const visibleFields = [];

    form.querySelectorAll('input, select, textarea').forEach((element) => {
        if (!element.name || element.name.startsWith('_') || element.name === 'campaign_code') return;
        const wrapper = element.closest('[data-capture-field]');
        if (wrapper && wrapper.offsetParent === null) return;
        visibleFields.push(element.name);
        captureData[element.name] = element.type === 'checkbox'
            ? (element.checked ? '1' : '0')
            : (element.value ?? '');
    });

    this.saving = true;
    this.feedback = null;
    try {
        await window.axios.post('/api/agent/capture', {
            campaign_code: this.campaign,
            call_session_id: null,
            lead_id: this.leadId || null,
            phone_number: this.phoneNumber || null,
            capture_data: captureData,
            visible_fields: visibleFields,
        });
        this.feedback = { type: 'success', message: 'Record saved successfully.' };
    } catch (error) {
        this.feedback = { type: 'error', message: error.response?.data?.message || 'Failed to save record.' };
    } finally {
        this.saving = false;
    }
}
```

Use `this.campaign`, `this.leadId`, `this.phoneNumber`, and `this.saving` initialized from the view's data attributes. Keep the component free of telephony polling and soft-navigation listeners.

- [ ] **Step 3: Register the component and bind the view**

Import `./agent-capture-webform` in `resources/js/app.js` immediately after `./form-visibility` so `window.formVisibility` is defined before the component factory is evaluated. Set the webform `<form>` to `x-data="agentCaptureWebform()"`, call `x-init="init(@js($prefill['fields'] ?? []))"`, use `@submit.prevent="submitCapture()"`, and render inline `feedback` and saving state. Keep `x-data="formVisibility()"` behavior available through the component's spread/delegation rather than loading a second Alpine root.

- [ ] **Step 4: Build the frontend and run the contract test**

Run `npm run build` and `php artisan test --compact tests/Feature/AgentCaptureWebformTest.php`; expect the build and all assertions to pass.

- [ ] **Step 5: Commit the client unit**

```bash
git add resources/js/agent-capture-webform.js resources/js/app.js resources/views/agent/capture_webform.blade.php tests/Feature/AgentCaptureWebformTest.php
git commit -m "Submit agent webform captures from VICIdial frame"
```

### Task 6: Verify the end-to-end capture and VICIdial handoff

**Files:**
- Modify: `tests/Feature/Api/AgentCaptureApiTest.php` only when a concrete webform payload edge case is demonstrated by a failing test.

**Interfaces:**
- The existing `POST /api/agent/capture` remains the persistence/writeback boundary.
- The admin-generated URL is the only value copied into a VICIdial campaign's Web Form setting.

- [ ] **Step 1: Run all focused PHP coverage together**

Run:

```bash
php artisan test --compact tests/Feature/Admin/AgentScreenAdminTest.php tests/Unit/Services/AgentCaptureWebformServiceTest.php tests/Feature/AgentCaptureWebformTest.php tests/Feature/Api/AgentCaptureApiTest.php
```

Expected: every selected PHPUnit test passes, including existing writeback, percentage normalization, hidden-required-field, and call-session ownership cases.

- [ ] **Step 2: Run formatting and production asset build**

Run `vendor/bin/pint --dirty --format agent`, then `npm run build`. Expected: Pint reports no remaining dirty PHP files and Vite completes with a production manifest containing the new JS import.

- [ ] **Step 3: Verify the authenticated frame in the browser**

Start the application with the project's normal local command (`composer run dev` or the already-running local server). Use Playwright to:

1. Log in to the CRM as an Agent.
2. Open Admin -> Agent Screen as a Super Admin in a separate context, select a campaign form, and copy the generated URL.
3. Open the URL in an iframe-sized page with `lead_id`, `phone_number`, and mapped VICIdial query values.
4. Confirm the mapped GET/BOTH fields are prefilled, POST-only fields are not prefilled, no sidebar/softphone/call controls render, and the compact layout is usable at VICIdial frame dimensions.
5. Submit valid data and confirm the success message remains in-frame; inspect the database or admin Capture Records page for the new Agent Capture Record.
6. Repeat with an invalid required field and confirm the form remains populated and shows the validation error.

- [ ] **Step 4: Run the final route and migration checks**

Run `php artisan route:list --name=agent-webforms`, `php artisan migrate:status`, and `git diff --check`. Expected: the named GET route is present, the new migration is migrated, and the diff has no whitespace errors.

- [ ] **Step 5: Commit the verified feature**

```bash
git add database/migrations/2026_07_22_000001_add_agent_webform_form_id_to_campaigns_table.php app/Models/Campaign.php app/Services/AgentCaptureWebformService.php app/Http/Requests/Admin/SaveAgentScreenWebformRequest.php app/Http/Controllers/Admin/AgentScreenController.php app/Http/Controllers/AgentCaptureWebformController.php resources/js/agent-capture-webform.js resources/js/app.js resources/views/admin/agent_screen.blade.php resources/views/agent/capture_webform.blade.php routes/web.php tests/Feature/Admin/AgentScreenAdminTest.php tests/Unit/Services/AgentCaptureWebformServiceTest.php tests/Feature/AgentCaptureWebformTest.php tests/Feature/Api/AgentCaptureApiTest.php
git commit -m "Complete VICIdial agent capture webform integration"
```

## Self-review checklist

- Campaign-level form selection is covered by Task 1 and Task 3.
- Agent Screen Field-only rendering and Agent Capture Record persistence are covered by Tasks 2, 4, and 5.
- VICIdial placeholder generation and GET/BOTH prefill are covered by Task 2.
- Authentication, campaign/form tamper resistance, inactive configuration, and required-field errors are covered by Tasks 3, 4, and 6.
- Existing post/both writeback is preserved and re-tested by Task 6.
- Frame-safe UI and real prefilled submission are covered by Tasks 5 and 6.
- No schema, method, route, or test placeholder remains in this plan.
