## Context

Form storage tables are shared by the dynamic form system, but Data Master currently treats every physical table column as displayable. The form registry in `forms` and `form_fields` is the authoritative definition of user-facing fields; legacy columns such as `other_bank` can remain in a table after a form changes and can cause both unwanted Data Master columns and insert failures.

## Goals / Non-Goals

**Goals:**

- Remove unregistered physical columns from every registered form table.
- Preserve exactly `id`, `date`, `request_id`, `agent`, `created_at`, and `updated_at` as framework-managed columns.
- Preserve registered fields for every form that shares a table, including inactive but non-deleted forms.
- Prevent Data Master from displaying arbitrary physical columns that are not registered.
- Verify cleanup and display behavior with regression tests.

**Non-Goals:**

- Do not infer new form fields from physical columns during cleanup.
- Do not delete rows or change values in preserved/registered columns.
- Do not change form validation, submission field names, or unrelated extraction behavior.
- Do not provide an automatic rollback for dropped columns; deployment requires a database backup.

## Decisions

1. **Use the form registry as the source of truth.** The migration will collect non-deleted `form_fields.field_name` values for every registered form, grouped by `table_name`. It will use the union when multiple forms share one table, preventing one form from deleting another form's fields.

2. **Apply cleanup through a one-time migration.** For each valid existing form table, the migration will drop only columns absent from the union of registered fields and the six approved system columns. It will skip missing/invalid tables and tables with no registry metadata. This makes the operation repeatable during deployment while keeping the destructive scope explicit.

3. **Filter Data Master at the service layer.** `DataMasterService::getColumnLayout()` will return registered fields plus the approved system columns that are present in the record. It will no longer append arbitrary columns from the database row. This protects the UI even if a future manual schema change occurs.

4. **Keep schema synchronization unchanged.** `FormFieldsSchemaSyncService` remains responsible for initial form-field discovery during seeding. Cleanup runs after the registry is available; it does not create form fields from stale columns.

## Risks / Trade-offs

- **[Dropped data]** Unregistered column values and definitions are permanently removed. → Require a production database backup before `php artisan migrate --force`; add a migration comment documenting the irreversible operation.
- **[Shared tables]** A field may belong to another form using the same table. → Union fields across all non-deleted registered forms before computing columns to drop.
- **[Foreign keys or indexes]** Database constraints may prevent a column from being dropped. → Let the migration fail rather than silently remove relational constraints; resolve the schema constraint explicitly before deployment.
- **[Future manual columns]** A manually added column will no longer appear in Data Master. → Add it to `form_fields` if it is intended to be user-facing.

## Migration Plan

1. Back up the production database.
2. Deploy the code and migration.
3. Run `php artisan migrate --force`.
4. Run `php artisan optimize:clear` and verify each form in Data Master.
5. If a removed field is needed, restore from backup and register it in `form_fields` before redeploying.
