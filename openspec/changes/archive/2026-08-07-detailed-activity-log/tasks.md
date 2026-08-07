## 1. Normalize detailed audit payloads

- [x] 1.1 Add failing feature assertions for actor metadata and field-level before/after diffs.
- [x] 1.2 Extend `ActivityLogEntry` with sanitized actor details and `changes.diff` while preserving existing payload keys.
- [x] 1.3 Run the focused Activity Log tests and confirm normalized request/model entries pass.

## 2. Render readable terminal details

- [x] 2.1 Add failing JavaScript assertions for structured actor, event, request, before, and after detail sections.
- [x] 2.2 Update the Blade terminal expansion to render structured metadata, request telemetry, field diffs, and sanitized raw JSON safely.
- [x] 2.3 Run the JavaScript Activity Log tests and confirm realtime/polling entry behavior remains covered.

## 3. Validate and finalize

- [x] 3.1 Run Pint, focused PHPUnit, the complete PHPUnit suite, JavaScript tests, and the Vite build.
- [x] 3.2 Verify the rendered Activity Log page in the browser by expanding request details at desktop and mobile sizes; model-change rendering is covered by the normalized feature test.
- [x] 3.3 Validate OpenSpec, sync the new capability to the main specs, and archive the completed change.
