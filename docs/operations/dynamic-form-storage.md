# Dynamic form storage

Dynamic form tables are provisioned when an administrator creates a registered form or changes its field metadata. Form submissions and CSV imports only check schema readiness; they do not issue table or column DDL. If readiness is stale, the save fails with an administrator provisioning message instead of changing the database during a user request.

## Deployment and recovery

After deploying application code and applying the additive Laravel migrations, provision registered forms with:

```bash
php artisan forms:provision-storage
```

The command is idempotent and reports forms that need attention without printing exception messages. It may create missing form tables/columns and widen identifier or numeric storage types. New columns stay nullable so existing rows remain valid; request validation still enforces required fields on new submissions. Review the command output and the application log reference before enabling a newly configured form.

Run the command after restoring a database, importing form metadata, or recovering from a failed admin provisioning attempt. Do not run it against an unreviewed production connection; it performs schema changes for registered forms.

## Legacy cleanup migration preflight (C10)

`2026_08_05_080950_cleanup_unregistered_form_columns` can drop columns and indexes from dynamic form tables. This phase did not change or execute that migration. Before applying it in any environment:

1. Check `php artisan migrate:status` and confirm whether this exact migration is pending.
2. Review its `up()` logic and identify every candidate column and index on the current database.
3. Compare candidates with registered forms, active and soft-deleted `FormField` metadata, imports, reports, and integrations that read historical columns.
4. Have an operator verify a restorable database backup and recovery procedure before deciding whether the cleanup is still appropriate.

If a column or index has a current or historical consumer, do not apply the cleanup until that dependency has been deliberately resolved. Do not run the migration as a routine deployment step without this preflight.
