# Team Leader working guide

Team Leaders review staff activity and can maintain several kinds of operational records. This role is not read-only. It does not include User Access, Campaigns, Forms setup, server configuration, or email sending.

## Start the day

1. Sign in and confirm the intended campaign. Open **Administration → Management Dashboard** for the management overview.
2. Open **Supervisor**, select the CRM campaign/routing context, and review freshness before interpreting agent availability.
3. Open **Staff Attendance** to review login/status history separately from phone availability.
4. Use **Reports** with matching dates/timezone/source scope for yesterday's results or the current operational window.

## Common tasks

Use **Records List**, **Disposition Records**, **Data Master**, and **Capture Records** for the correct record type. Follow [data management](data-management.md) when correcting/deleting/exporting; changes can affect real customer information. Use **Extraction** for scoped CSV output and protect downloaded copies.

Supervisor controls can affect a real phone session; confirm the agent, campaign and reason before using monitor, whisper, pause, logout, or notification actions. Follow [supervisor guidance](supervisor-and-operations.md). A successful notification action does not prove the person read it.

Field Logic and Disposition Codes pages are visible, but create/update requests require Admin or Super Admin. Current delete routes are broader and allow Team Leader; do not assume that this makes routine deletion appropriate. Use the [permission matrix](../roles-and-permissions.md) for this distinction.

## Finish and escalate

Reconcile partial imports/records before asking agents to repeat work. For missing data, capture the source, filters, timestamp and sanitized error rather than interpreting unavailable as zero. Escalate setup changes to Super Admin and field-rule changes to Admin/Super Admin. Never request passwords or customer records in ordinary support chat.

Finish personal attendance/phone duties and sign out using the [daily workflow](../user-guide/daily-workflow.md). Common mistakes include comparing unlike report windows and treating CRM login as a live phone session. See [troubleshooting](../operations/troubleshooting.md).

Source: [admin routes](../../routes/admin.php), [navigation](../../app/Support/AdminNavigation.php), [role matrix](../roles-and-permissions.md).
