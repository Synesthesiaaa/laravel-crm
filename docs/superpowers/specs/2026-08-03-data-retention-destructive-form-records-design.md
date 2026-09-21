# Data Retention for Form Records

## Context

The CRM stores submissions in per-form tables whose names are configured in the `forms` table. Every form submission has a `date` column, and the application already runs daily scheduled maintenance tasks from `routes/console.php`. Super Admin configuration is presented as tabs in `admin.configuration`.

## Goals

- Allow only Super Admin users to configure retention rules.
- Let an administrator choose a specific cutoff date for each form whose records must be destroyed.
- Automatically permanently delete records from selected form tables when their `date` is on or before the configured cutoff date.
- Keep form/table selection constrained to active forms already registered by the application.
- Make the existing Field Logic form filter update automatically when its selected form changes.
- Provide clear visibility into configured rules and the most recent cleanup result.

## Non-goals

- Field-level deletion or masking.
- Deleting call history, agent capture records, telephony data, or unrelated tables through the retention screen.
- Allowing arbitrary table names or raw SQL to be entered by an administrator.
- Adding a new retention-period or relative-age mode; this feature uses explicit calendar cutoff dates.

## User experience

### Data Retention configuration tab

Add a `Data Retention` tab to the Super Admin Configuration page. The tab contains:

1. A warning explaining that cleanup permanently deletes complete records from the selected form.
2. A form selector populated from active database-backed forms.
3. A required cutoff date selector.
4. A save action that creates or updates the rule for that form.
5. A table of existing rules showing campaign, form, storage table, cutoff date, active state, last run time, and last deleted count.
6. Actions to edit/deactivate a rule.

When the selected form changes, the form-specific field metadata shown in the retention screen (if present) is refreshed to that form. Fields are informational only because the deletion unit is the complete record.

The existing Field Logic page will submit its form selector automatically on change, so the fields displayed always match the selected form without requiring a second Load click.

### Rule semantics

Each form has at most one retention rule. A rule remains active after a cleanup run so that records inserted later with an older business date are also covered. Administrators can update the cutoff date or deactivate the rule.

## Data model

Create `data_retention_policies` with:

- `id`.
- `form_id`, uniquely indexed and referencing `forms.id`.
- `cutoff_date` as a date.
- `is_active` as a boolean defaulting to true.
- `last_run_at` nullable timestamp.
- `last_deleted_count` unsigned integer defaulting to zero.
- timestamps.

Add a `hasOne` relationship from `Form` to `DataRetentionPolicy` and a corresponding `DataRetentionPolicy` model with date, boolean, and datetime casts.

## Application flow

1. A Super Admin opens `admin.configuration?tab=retention`.
2. The controller loads active forms and their field metadata, plus existing policies.
3. The form request validates the selected form, cutoff date, and optional active flag. The selected form must be active and exist.
4. The controller upserts the policy by `form_id` and redirects back to the retention tab with a status message.
5. The scheduled `data-retention:run` command invokes a retention service.
6. The service loads active policies and resolves each table name through its related `Form`.
7. For each valid existing table with a `date` column, it permanently deletes rows where `date <= cutoff_date` using the query builder, then records the run timestamp and deleted count.
8. Invalid or missing storage tables are skipped and logged; one invalid rule must not prevent other rules from running.

Table names are never accepted from request input. They come from the existing `Form` record and are checked against the database schema before use.

## Scheduling and operational safety

Register the command to run once daily after the existing maintenance tasks, with `withoutOverlapping` and scheduler output appended to the existing scheduler log. The command reports counts per form and a total count for operational visibility.

The configuration screen must use destructive wording and require the normal form submission/CSRF flow. Deactivation is reversible; record deletion is not.

## Authorization and validation

- Routes remain inside the existing `auth`, campaign, admin, and `role:Super Admin` middleware groups.
- Form requests authorize through the authenticated Super Admin role.
- `form_id` must reference an active form.
- `cutoff_date` must be a valid `Y-m-d` date.
- Policy uniqueness is enforced both by validation logic and the database unique index.

## Error handling

- Validation errors return to the tab with input preserved.
- A missing table or missing `date` column is logged and skipped without aborting the complete cleanup run.
- A failure for one policy is logged and the command continues with remaining policies.
- Policy run metadata is updated only after a successful delete operation for that policy.

## Testing strategy

Add PHPUnit coverage for:

- Super Admin access and denial for non-Super Admin users.
- Retention tab rendering with active forms and existing policies.
- Creating and updating a policy, including invalid dates and inactive/nonexistent forms.
- Deleting rows on or before the cutoff date while preserving rows after it.
- Restricting cleanup to the configured form table.
- Skipping invalid storage tables without preventing other policies from running.
- Automatic Field Logic filtering when the form selector changes.
- Scheduler registration of the retention command.

Run Pint on modified PHP files, the focused PHPUnit tests, and browser verification of the Super Admin tab and Field Logic form switching.
