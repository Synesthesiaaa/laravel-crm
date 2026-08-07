# Detailed Activity Log Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make each Activity Log entry readable and comprehensive when expanded, covering actor identity, event context, request telemetry, and before/after changes.

**Architecture:** Extend the existing `ActivityLogEntry` normalized payload with actor metadata and a computed, sanitized change diff. Keep request metadata and existing raw change properties in the same payload. Update the Blade terminal detail panel to render structured audit sections while retaining sanitized JSON for complete inspection.

**Tech Stack:** Laravel 12, Spatie Activitylog, PHPUnit 11, Blade, Alpine.js, Node test runner, Tailwind CSS/Vite.

## Global Constraints

- Preserve the existing Super Admin-only Activity Log access boundary.
- Never display or persist request bodies, sensitive headers, passwords, tokens, or credentials.
- Reuse `ActivityLogSanitizer` for all displayed activity properties.
- Keep realtime broadcast and polling payload compatibility.
- Add PHPUnit coverage before production changes and run Pint for modified PHP files.

---

### Task 1: Normalize detailed audit metadata

**Files:**
- Modify: `app/Services/ActivityLogEntry.php`
- Test: `tests/Feature/Admin/SystemWideUserActivityAuditTest.php`

**Interfaces:**
- Consumes: `Spatie\\Activitylog\\Models\\Activity`, sanitized `attributes`, `old`, and `request` properties.
- Produces: normalized `actor_details` and `changes.diff` fields for the Blade view and realtime event.

- [x] **Step 1: Write failing tests**

Add assertions for actor username/role/id and a readable diff where a changed field contains `old` and `new` values. Assert request entries expose the same actor details without exposing unsanitized secrets.

- [x] **Step 2: Run the focused test**

Run: `php artisan test --compact tests/Feature/Admin/SystemWideUserActivityAuditTest.php`

Expected: FAIL because `actor_details` and `changes.diff` are not currently normalized.

- [x] **Step 3: Implement minimal normalization**

Add actor metadata from the loaded causer and compute a diff from sanitized `old` and `attributes`, preserving keys that exist only on one side. Keep the existing `changes.attributes` and `changes.old` fields unchanged.

- [x] **Step 4: Run the focused test again**

Run: `php artisan test --compact tests/Feature/Admin/SystemWideUserActivityAuditTest.php`

Expected: PASS.

### Task 2: Render structured expanded details

**Files:**
- Modify: `resources/views/admin/activity_log.blade.php`
- Test: `tests/JavaScript/activity-log.test.js`

**Interfaces:**
- Consumes: normalized `actor_details`, `request`, `changes.diff`, and existing `changes` fields.
- Produces: terminal expansion with actor, event, resource, request, change comparison, and raw JSON sections.

- [x] **Step 1: Write failing UI assertions**

Assert the Blade view includes labels for `Actor`, `Event`, `Request`, `Before`, `After`, and the normalized diff field.

- [x] **Step 2: Run the JavaScript test**

Run: `node --test tests/JavaScript/activity-log.test.js`

Expected: FAIL because the structured detail labels are not present.

- [x] **Step 3: Implement the structured detail panel**

Render the metadata in readable rows, render request telemetry only when present, loop through the diff safely, and keep raw sanitized JSON below the structured sections. Use `x-text` and JSON serialization so values remain escaped.

- [x] **Step 4: Run the JavaScript test again**

Run: `node --test tests/JavaScript/activity-log.test.js`

Expected: PASS.

### Task 3: Verify regression safety and rendered behavior

**Files:**
- Modify: `openspec/changes/detailed-activity-log/tasks.md`
- Add: `openspec/changes/detailed-activity-log/proposal.md`
- Add: `openspec/changes/detailed-activity-log/design.md`
- Add: `openspec/changes/detailed-activity-log/specs/detailed-activity-log/spec.md`
- Add: `openspec/specs/detailed-activity-log/spec.md`

**Interfaces:**
- Consumes: completed normalized payload and terminal UI.
- Produces: validated and archived OpenSpec change.

- [x] **Step 1: Run focused PHP and JavaScript tests**

Run: `php artisan test --compact tests/Feature/Admin/SystemWideUserActivityAuditTest.php tests/Feature/Admin/ActivityLogTest.php` and `node --test tests/JavaScript/activity-log.test.js`.

- [x] **Step 2: Run formatting, full tests, and build**

Run: `vendor/bin/pint --dirty --format agent`, `php artisan test --compact`, and `npm run build`.

- [x] **Step 3: Verify the rendered terminal page**

Use the available browser verification path to open `/admin/activity-log`, expand a request and a model-change entry, confirm structured details are visible, and check the existing realtime/polling indicator remains functional.

- [ ] **Step 4: Validate and archive OpenSpec**

Run `openspec validate --all --strict`, mark all tasks complete, sync the new capability to `openspec/specs/detailed-activity-log/spec.md`, and archive the change.
