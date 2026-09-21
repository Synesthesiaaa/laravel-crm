## Context

The existing data-retention feature stores one explicit cutoff policy per form and deletes complete rows from the form's registered storage table. Form fields are synchronized into `form_fields`, while the storage tables contain nullable and non-nullable string, numeric, boolean, and date-like columns. The extension must support field-level clearing without permitting arbitrary SQL identifiers or changing the current whole-record mode.

## Goals / Non-Goals

**Goals:**

- Persist a per-form deletion mode and selected field list.
- Render only eligible fields for the selected form in the Super Admin retention tab.
- Preserve records and all unselected values in selected-field mode.
- Derive type-safe clearing values from live schema metadata.
- Keep policy validation, scheduling, authorization, and operational metadata consistent with the existing feature.

**Non-Goals:**

- A separate manual record browser or one-off deletion button.
- Arbitrary table or column entry.
- Field masking, encryption rotation, or recovery of cleared values.
- Changes to Field Logic beyond its existing automatic form filtering.

## Decisions

### Extend the existing policy row with JSON field selection

Add `deletion_mode` with `whole_record` and `selected_fields` values and nullable `selected_fields` JSON to `data_retention_policies`. This keeps the existing one-policy-per-form constraint and makes policy updates atomic.

Alternative considered: a normalized policy-fields table. It would make individual selections relational, but would add a migration, model, relationship, and synchronization path without a requirement for multiple policy versions or queries by field.

### Validate fields against FormField metadata and the live storage schema

The request will accept only field names belonging to the selected form's active `FormField` rows and present in the form's registered table. System columns (`id`, `date`, timestamps, request IDs, agent, lead, and phone tracking values) are excluded. The service repeats the schema validation before any update so stale or manually altered policies fail safely.

Alternative considered: validate only against the request's submitted field names. That would permit columns outside the form contract and could expose SQL identifier injection risks.

### Use a single bulk update for selected-field cleanup

For selected-field mode, the service builds a complete update map and executes one query-builder update with the existing cutoff predicate. The affected-row count becomes the policy's deleted count metric. Whole-record mode continues using one delete query.

Alternative considered: load each matching record and save it individually. That would be slower, increase lock duration, and create unnecessary model-event behavior for a destructive scheduled task.

### Derive clearing values from nullability and type

The service uses Laravel schema column metadata. Nullable columns receive `NULL`; non-null strings/text receive `''`; numeric and boolean columns receive `0`. Nullable date/time values receive `NULL`, while non-null date/time and other unsupported columns make the policy skip before mutation.

Alternative considered: use `NULL` for every selected field. Existing required form columns would reject that update, so a type-aware map is required.

### Keep the form selector as the source of field filtering

The retention form selector submits automatically on change. The controller loads the selected form's field metadata and saved policy, so the checkbox list always represents one form at a time and does not require client-side schema discovery.

## Risks / Trade-offs

- [A stale or manually modified policy can reference a removed field] → Revalidate field metadata and schema in the service, skip the policy before mutation, and log the reason.
- [A non-null date/time field has no semantically safe redaction value] → Exclude system date fields and reject unsupported non-null date/time selections during validation/service preparation.
- [Bulk updates may report rows that were already cleared] → Treat the metric as rows affected by the scheduled policy operation, matching the existing cleanup count semantics.
- [Adding a defaulted column changes migration state for existing policies] → Use a migration default of `whole_record` and nullable JSON so existing rows remain valid and behavior-compatible.
- [Field metadata may not yet be synchronized] → Render an empty eligible-field list and reject selected-field submissions until the form field metadata exists; whole-record mode remains available.

## Migration Plan

1. Deploy the migration adding `deletion_mode` with a `whole_record` default and nullable `selected_fields` JSON.
2. Deploy model, request, controller, service, command, and view changes.
3. Existing policies continue to delete complete records because their mode defaults to `whole_record`.
4. Super Admins may edit a policy to select field-only mode and fields.
5. Rollback removes the two new columns after disabling selected-field policies; the application rollback must occur before the migration rollback.

## Open Questions

None. The deletion value rules, eligible field scope, policy shape, and UI behavior were approved before implementation.
