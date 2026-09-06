## Context

Dashboard layouts already persist campaign JSON and use an administrator-only save endpoint. Dialogs use Alpine focus traps with scroll locking, but their launchers open on focus and hover. Background refresh replaces the main layout and resets scroll; concurrent requests are not ordered.

## Goals / Non-Goals

Goals: deliberate accessible dialogs, campaign amount controls, stable refresh and pagination.
Non-goals: altering sales attribution, monetary data access permissions, schema changes, or redesigning the dashboard.

## Decisions

- Extend existing layout JSON with normalized `amounts` booleans: enabled, total, change, charts, tables. Default each to true and preserve omitted saved settings. The master switch also hides KPI monetary subtitles; individual switches control summary cards, chart mode and all table amount cells/report tables.
- Use button launchers, remove hover/focus timers, and provide Escape/backdrop dismissal while preserving normal focus return.
- Preserve scroll on background refresh, defer refresh around active dialogs and recent interaction, and discard superseded navigation responses. Close modal state before replacing its DOM and let Alpine release its trap.
- Validate persistence, defaults, isolation, authorization, rendered visibility, dialog interaction and navigation races with focused tests and Playwright.

## Risks / Trade-offs

- Display controls do not change ranking or financial calculations. Keep existing ranking behavior and explanatory copy accurate.
- Deferring refresh delays updates while a user is interacting; pending updates must resume after interaction ends.
- Shared navigation changes affect other pages; exercise pagination-like links and failed/concurrent requests.

## Migration Plan

No migration. Build assets and deploy application changes. Existing layouts retain all amount displays until an administrator changes settings.

## Validation results

- 40 focused PHPUnit tests passed (250 assertions); 5 JavaScript navigation tests passed. Laravel Pint and the production build passed.
- Playwright verified hover/focus do not open dialogs, Enter opens them, Escape/Close release scrolling, background refresh preserves a 650px scroll offset, and a 32-second fallback waits for dialog dismissal then resumes.
- Administrator saves verified all amounts hidden with counts retained; the original enabled settings were restored. Populated monetary column/chart visibility is covered by PHPUnit.
- Responsive dialog checks passed at 375, 768, 1024, and 1440px without horizontal overflow. Records pagination URLs were exercised through soft-navigation links for page 2 and page 1; existing local data contains only one record, so native multi-page controls were unavailable. Concurrent pagination responses are covered by JavaScript tests.
- Local Reverb at port 6001 is unavailable and logs existing connection refusals. Disconnected fallback behavior was verified; no dashboard JavaScript exceptions occurred.
