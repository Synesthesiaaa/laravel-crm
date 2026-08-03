## Why

Some CRM form submissions contain data that must be destroyed after a defined retention date. The application currently has no Super Admin workflow for configuring those dates and no automatic cleanup process, which leaves retention enforcement manual and inconsistent.

## What Changes

- Add a Super Admin Data Retention tab under System Configuration.
- Allow one active, explicit cutoff-date policy per form.
- Permanently delete complete records from configured form tables when their business `date` is on or before the cutoff.
- Run retention cleanup automatically once per day through Laravel scheduling.
- Show policy status and the latest cleanup count in the admin UI.
- Make the Field Logic form filter refresh automatically when the selected form changes.

## Capabilities

### New Capabilities

- `data-retention`: Configure, manage, and automatically enforce form-record destruction policies.

### Modified Capabilities

None.

## Impact

- New retention policy migration and Eloquent model.
- New admin request, controller actions, routes, and configuration tab UI.
- New retention service and scheduled Artisan command.
- Existing `Form` model relationship and Field Logic selector behavior.
- PHPUnit feature/unit coverage and browser verification.
