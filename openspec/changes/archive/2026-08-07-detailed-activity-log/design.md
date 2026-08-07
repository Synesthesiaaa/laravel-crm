## Context

The existing Activity Log already stores sanitized request metadata and model `attributes`/`old` values. `ActivityLogEntry` exposes those values to the terminal, but the expanded Blade panel currently renders request and change data mostly as raw JSON. The change is a presentation and normalization improvement for the existing Super Admin audit stream; it must not change persistence, realtime delivery, retention, or authorization boundaries.

## Goals / Non-Goals

**Goals:**

- Add stable actor metadata for display and realtime consumers.
- Compute a sanitized field-level diff from existing before/after properties.
- Render actor, event, resource, request, and change information as readable terminal sections.
- Preserve the raw sanitized payload for advanced inspection.
- Keep missing request/change sections safe for older activity records.

**Non-Goals:**

- No new database tables, columns, packages, or logging channels.
- No request-body or header capture.
- No change to the Super Admin-only Activity Log authorization rule.
- No replacement of the existing polling or Reverb delivery flow.

## Decisions

1. **Normalize details in `ActivityLogEntry`.** The service already owns the API/broadcast shape, so actor metadata and diffs are computed once and shared by initial page loads, polling, and realtime events. A UI-only diff would duplicate logic and make non-UI consumers inconsistent.

2. **Use a field-level `changes.diff` map.** Each key maps to `old` and `new` values. Missing values are represented as `null`, and unchanged fields are excluded. The existing `changes.attributes` and `changes.old` maps remain available for backward compatibility.

3. **Derive actor details from the loaded causer.** Include only `id`, `username`, `full_name`, and `role`; do not include email, passwords, telephony credentials, or other profile data. System entries retain a null actor-details value.

4. **Render with Alpine `x-text`.** Structured values are displayed through escaped text bindings. Complex arrays/objects use JSON serialization, and the existing sanitizer runs before normalization, so the detail panel cannot reintroduce sensitive values.

5. **Keep raw JSON below structured sections.** Operators get a quick readable summary and a complete sanitized record without changing the underlying activity schema.

## Risks / Trade-offs

- **[Risk]** Activity properties may contain nested arrays or missing keys. → Normalize recursively and render JSON for complex values; default absent sections to empty/null values.
- **[Risk]** Realtime clients may depend on the current payload shape. → Add fields without removing existing keys and cover the broadcast-normalized entry through existing tests.
- **[Trade-off]** More detail increases expanded-row height. → Keep the stream compact by rendering details only when a row is expanded and retain the existing scrollable terminal.

## Migration Plan

No database migration is required. Deploy the service/view/test changes and rebuild frontend assets. Existing activity rows remain readable; rows without request metadata show only applicable event and change sections.

## Open Questions

None; the design was approved with both request telemetry and before/after changes included.
