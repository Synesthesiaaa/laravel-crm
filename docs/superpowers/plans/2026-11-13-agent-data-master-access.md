# Agent Data Master Implementation Plan

> Execute task-by-task with superpowers:executing-plans. OpenSpec tasks track completion.

**Goal:** Give agents campaign-controlled, read-only access to explicitly permitted form records.

**Architecture:** Reuse audited SystemSetting JSON per campaign. Separate access and query services from the administrative write service. Use stable submission-history user IDs for ownership and existing Blade components for configuration and results.

**Tech Stack:** Laravel 12, PHP, Blade, Alpine, Tailwind 4, PHPUnit, Playwright.

**Spec:** openspec/changes/archive/2026-11-13-agent-data-master-access/design.md and openspec/specs/agent-data-master-access/spec.md.

## Global Constraints

- Campaign-wide settings, explicit active form IDs, own-only on by default, access off by default.
- Session campaign only; no new campaign assignment model, dependencies, or migrations.
- Agent GET endpoint only; existing administrator write permissions remain intact.
- Operate mode, existing Signal Magenta tokens and components, 375/768/1024/1440 widths.

## Review Focus

- Identical display names must not grant ownership; test history user IDs and unattributed rows.
- Shared dynamic tables must not leak campaign data; test campaign/form provenance in all-user mode.
- Deleted/recreated forms and revoked settings must lose access on the next request.
- Filters and grouped search must not broaden ownership; test malicious scope parameters and hidden columns.
- Missing storage and malformed settings must fail closed with safe feedback; test both explicitly.

## Task 1: Permission settings and configuration

Files: tests/Feature/AgentDataMasterTest.php; app/Services/AgentDataMasterAccessService.php; app/Http/Requests/Admin/UpdateAgentDataMasterAccessRequest.php; app/Http/Controllers/Admin/AgentDataMasterAccessController.php; configuration controller/view/tab; routes/admin.php.

- [x] Write feature tests for defaults, saved scope/forms, invalid forms, lower roles, immediate revocation and invalid JSON; run to observe failure.
- [x] Implement settings(string $campaign): array, allowedForms(string $campaign): Collection, canView(User $user, string $campaign): bool, and update(string $campaign, array $values): void using fresh audited settings.
- [x] Add Configuration > Data Master with campaign selection, enabled and own-only controls, explicit form checkboxes and save feedback.
- [x] Run feature tests and fix failures.

## Task 2: Read-only results and filters

Files: app/Http/Controllers/AgentDataMasterController.php; app/Http/Requests/AgentDataMasterRequest.php; app/Services/AgentDataMasterService.php; resources/views/agent/data-master.blade.php; layouts/sidebar.blade.php; routes/web.php; feature tests.

- [x] Write ownership, scope tampering, shared storage, hidden-column search, date/column filters, pagination, missing storage and forbidden mutation tests; run to observe failure.
- [x] Implement page(Form $form, User $viewer, bool $ownOnly, array $filters): array with allowlisted storage, explicit visible columns, history EXISTS, grouped search, inclusive created_at/date filters and 20-record pagination.
- [x] Add GET-only agent route and request validation. Render shared components, scope explanation, filters, collapsible per-column fields, empty states and pagination; show sidebar only when permitted.
- [x] Run focused and related tests plus Pint and npm run build.

## Task 3: Browser and release evidence

Files: isolated tests/Support/agent-data-master-browser.php fixture, docs/AGENT_DATA_MASTER.md and OpenSpec validation/tasks/spec.

- [x] Seed synthetic SQLite fixtures without accessing live data; run a loopback-only test server.
- [x] Use Playwright for Super Admin settings, agent navigation/filter interactions, clear/pagination, scope changes/revocation and four viewport widths. Capture and inspect desktop/mobile and verify console/network health.
- [x] Review code and screenshots, correct material findings, record actual validation, sync and archive OpenSpec.
