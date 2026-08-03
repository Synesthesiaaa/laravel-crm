## Context

Form submissions are stored in dynamic tables selected by the database-backed `forms` records. The tables share a `date` column, but there are no Eloquent models for every possible form table. Laravel scheduling is already configured in `routes/console.php`, and Super Admin configuration is already organized as server-rendered tabs.

## Goals / Non-Goals

**Goals:**

- Persist one explicit cutoff-date policy per form.
- Enforce permanent deletion through a controlled service and a scheduled Artisan command.
- Keep all storage-table resolution server-side and tied to registered active forms.
- Provide Super Admin CRUD/deactivation controls and run metadata.
- Make the Field Logic form selector refresh its result set immediately when changed.

**Non-Goals:**

- Relative retention periods, field-level erasure, anonymization, or manual arbitrary-table cleanup.
- Changes to call history, capture records, or telephony retention.

## Decisions

### Use a relational policy table

Create `data_retention_policies` with a unique `form_id`, `cutoff_date`, `is_active`, `last_run_at`, and `last_deleted_count`. A relational model is auditable, validates against the existing `forms` table, and avoids opaque JSON configuration. A JSON system setting was rejected because it would make uniqueness, validation, and policy status queries harder.

### Use the business `date` column for eligibility

The cleanup query deletes rows where `date <= cutoff_date`. This matches the explicit date the administrator selects and is consistent across the existing form-storage migrations. Using `created_at` was rejected because it represents ingestion time rather than the form record's business date.

### Resolve dynamic tables from `Form`

The retention service loads each policy's active `Form`, checks that its configured table exists and has a `date` column, and then uses the query builder with that trusted table name. Request input never contains a table name. A model-pruning implementation was rejected because form tables are dynamic and do not have one model class per table.

### Continue after per-policy failures

The command processes policies independently. It logs and skips missing or malformed storage tables, and catches/logs a failure for one policy before continuing. Successful policies update their run metadata only after the delete operation completes.

### Keep the existing configuration surface

Add a `retention` tab to `ConfigurationController` and `admin.configuration`, protected by the existing Super Admin route middleware. A new top-level admin page was rejected because retention is system configuration and the existing tab pattern already provides the correct authorization boundary.

### Make Field Logic selection reactive through normal navigation

The existing Field Logic form selector will submit its GET form on `change`. The controller remains the source of truth for filtering fields, which avoids adding an API endpoint or client-side duplication of field metadata.

## Risks / Trade-offs

- [Permanent deletion] → The UI uses explicit destructive wording, cleanup is restricted to configured active forms, and policies expose their cutoff and latest deleted count.
- [Large table deletes may lock rows] → Use one indexed `date` predicate per policy and run once daily during the existing maintenance window; the first version reports the affected count for monitoring.
- [A form table may be removed or misconfigured] → Check table and column existence, log the issue, and continue with other policies instead of aborting the whole command.
- [A policy remains active after its cutoff passes] → This is intentional so late-arriving records with an older business date are still removed; deactivation is available in the admin UI.

## Migration Plan

1. Deploy the policy migration and application code.
2. Run `php artisan migrate`.
3. Configure policies from Super Admin → Configuration → Data Retention.
4. Ensure the existing scheduler invokes `schedule:run` daily; the new command will run at the configured maintenance time.

Rollback removes the retention policy migration after all policies are deactivated. Existing form records are not restored by rollback, so operators must treat any cleanup run as irreversible.

## Open Questions

None. The approved design uses explicit calendar cutoff dates and complete-record deletion.
