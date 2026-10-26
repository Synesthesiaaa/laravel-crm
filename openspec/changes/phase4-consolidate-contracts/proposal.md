## Why

The phase 4 audit findings reproduce in the current implementation: form identity can detach metadata, Data Master accepts untyped physical-column writes, attendance state is unlocked and day-bounded, submissions can run schema DDL, exports accept formula-like text, operation failures expose exception details, and frontend persistence/configuration has unsafe edge cases. These issues can corrupt or disclose CRM data and make agent/admin workflows unpredictable, so they need one coordinated contract-hardening phase.

## What Changes

- Protect form identity once field metadata or stored records exist, and restrict Data Master edits to configured business fields with the same typed rules used for regular submissions.
- Serialize attendance segment transitions on a stable per-user database row and locate open segments across midnight.
- Move dynamic table creation and reconciliation out of submission/import requests into explicit form administration and an operator-run provisioning command; submissions will check readiness and fail safely without issuing DDL.
- Apply a shared spreadsheet-formula guard to both CSV exports while retaining valid numeric values.
- Replace raw infrastructure exception text returned to users or written to logs with safe messages and reference IDs.
- Guard optional browser storage before Alpine startup, fix the user-edit marker, give repeated form controls unique IDs, and load Activity Log styling through versioned assets.
- Select Reverb or Pusher from an explicit frontend driver setting and validate that driver's own key.
- Keep customer drafts only for fields explicitly marked safe by an administrator, enforce an eight-hour expiry, remove phone values from storage keys, purge expired and legacy drafts, and clear drafts during logout cleanup.
- Add a deployment preflight for the historical destructive migration identified as C10. The migration itself remains unchanged because its risk is conditional and repository evidence does not establish that it has run or deleted data.

## Capabilities

### New Capabilities

- `form-metadata-integrity`: Prevent form identity changes from orphaning metadata or stored records.
- `attendance-segment-integrity`: Preserve one open attendance segment per user across concurrent requests and overnight shifts.
- `safe-csv-export`: Protect untrusted text in spreadsheet-oriented CSV exports without changing valid numeric cells.
- `safe-operation-errors`: Return actionable, non-sensitive failure messages with reference IDs.
- `browser-runtime-resilience`: Keep optional preferences, repeated controls, styles, and realtime transport configuration reliable across supported browser flows.

### Modified Capabilities

- `form-storage-nullability`: Retain historical nullability reconciliation while moving it to explicit provisioning and keeping ordinary submissions DML-only.
- `crm-form-review-confirmation`: Limit local drafts to administrator-approved fields, expire them, and remove them on logout.
- `data-master-desktop-table`: Restrict edits to configured fields and validate values according to field metadata.
- `activity-log-responsive-experience`: Ensure Activity Log styles remain present when entering the page through soft navigation.

## Impact

Affected areas include form and field administration, Data Master validation, attendance services and database locking, dynamic form storage services and an Artisan provisioning command, CSV export services, operation result/error handling, the form-field metadata schema and editor, Alpine/browser storage and logout lifecycle, shared Blade form components, Activity Log CSS assets, Echo configuration, deployment documentation, and focused PHP/JavaScript/feature tests. Database changes are additive only. Validation will use isolated test databases and synthetic data; it will not invoke real telephony, SMS, email, or production data operations.
