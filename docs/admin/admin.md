# Admin working guide

Admins oversee operational records and reporting, and can create/update Field Logic and Disposition Codes. The current role does not grant the Super Admin setup routes even though the roles seeder gives some similarly named permissions.

## Daily workflow

1. Sign in to the intended campaign and inspect **Management Dashboard**, **Supervisor**, and **Staff Attendance**.
2. Review **Reports** with explicit date, campaign, source and disposition scope.
3. Investigate record issues through **Records List**, **Data Master**, **Capture Records**, and **Disposition Records**, choosing the store that actually owns the data.
4. Make permitted field/disposition changes only after checking affected forms, validation and existing records. Use [forms and field logic](forms-and-field-logic.md) and [data management](data-management.md).
5. Validate a synthetic example and the relevant failure case before asking staff to use changed rules. A visibility rule does not automatically cancel required validation.

## Responsibilities and limits

Admins can use management dashboard layout configuration, operational exports, and the shared supervision tools. Changing a record is distinct from changing its form definition or its remote VICIdial lead. Review [supervisor operations](supervisor-and-operations.md) before using controls that affect live sessions.

For users, campaign creation, form registration, servers, Lead Hopper import, Agent Screen Configuration, Attendance Statuses, branding/retention, Activity Log, or email, request the responsible Super Admin. Do not try to gain access by changing a URL or granting a seeded permission alone.

## Common issues

If a save fails, read the validation field and verify the current campaign/form rather than inserting invented values. If totals differ, reconcile filters and source freshness first. Avoid deleting records as a way to correct field configuration. Use [troubleshooting](../operations/troubleshooting.md), supplying sanitized examples and expected/actual results.

End personal phone/attendance activity and sign out. Related: [Team Leader daily tasks](team-leader.md), [permissions](../roles-and-permissions.md), [Super Admin responsibilities](super-admin.md).
