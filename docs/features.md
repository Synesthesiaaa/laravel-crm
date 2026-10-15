# Feature reference

This inventory describes implemented surfaces in the repository. Availability depends on roles, feature configuration, database readiness, and external integrations. It is not a claim that a deployment has passed live acceptance testing.

| Feature | Purpose | Guide and limits |
| --- | --- | --- |
| Authentication | CRM login, pending-session confirmation, logout | [First login](getting-started/first-login.md); CRM and phone login are separate. |
| Campaign context | Active forms, names, routing, and reporting context | [Campaigns and leads](user-guide/campaigns-and-leads.md); context is not membership isolation. |
| Dashboard | Configured CRM activity, sales summaries, and campaign reporting | [Reports](user-guide/reports.md); sales attribution and dates vary by configuration/widget. |
| Campaign Forms | Configurable business data entry and submission history | [Forms and records](user-guide/forms-and-records.md); separate from Agent Screen captures. |
| Agent Screen | Lead information, capture entry, call state, and advanced call tools | [Calls and dispositions](user-guide/calls-and-dispositions.md); access and tools are feature-gated. |
| Phone widget | VICIdial session login and session controls in an embedded interface | [Daily workflow](user-guide/daily-workflow.md); minimizing keeps the session running. |
| Lead tools | Search, inspect, add/update, and switch lead context | [Campaigns and leads](user-guide/campaigns-and-leads.md); external service readiness required. |
| Dispositions | Store the actual call outcome and remarks | [Calls and dispositions](user-guide/calls-and-dispositions.md); codes are configurable. |
| Callbacks | Schedule, inspect, and remove lead callbacks | [Callbacks](user-guide/callbacks.md); does not promise automatic reminders or exact-time delivery. |
| Transfers, park, DTMF, recording | Advanced phone operations | [Calls and dispositions](user-guide/calls-and-dispositions.md); permissions and remote support matter. |
| Call History | Browse stored calling activity, with personal/management context | [Forms and records](user-guide/forms-and-records.md); source availability and synchronization matter. |
| Attendance | Login/logout history and custom status segments | [Attendance](user-guide/attendance.md); separate from phone pause, not a payroll calculation. |
| Notifications | Supervisor messages and recent derived activity | [Notifications](user-guide/notifications.md); a bounded recent feed, not a complete audit. |
| Telephony Reports | Historical, live, and today metrics with source availability | [Reports](user-guide/reports.md); restricted management page. |
| Operations Insights | Overview, call reports, and form data browsing | [Supervisor and operations](admin/supervisor-and-operations.md); currently guest-accessible. |
| Supervisor | Agent monitoring, routing health, and operational actions | [Supervisor](admin/supervisor-and-operations.md); actions may affect live phone sessions. |
| Data maintenance and exports | Data Master, captures, records review, CSV extraction | [Data management](admin/data-management.md); exports are separate data copies. |
| Forms and Agent Screen setup | Form definitions, field types, visibility, capture fields/webforms | [Configuration guide](admin/forms-and-field-logic.md); changes can affect storage and validation. |
| User and campaign administration | Staff roles, telephony details, server mapping, active campaigns | [Users](admin/users.md), [campaigns](admin/campaigns.md); remote credential sync is best-effort. |
| Lead Hopper CSV import | Queued form-row import and import status | [Import limits](admin/campaigns.md#lead-hopper-imports); current job does not populate hopper rows. |
| Branding and feature switches | Company identity, report classifications, enabled controls | [Configuration](admin/configuration.md). |
| Data retention | Per-form fixed date-window deletion or field clearing | [Retention](admin/retention-and-activity-log.md); destructive execution, scheduler-dependent. |
| Activity Log | Filter recent system activity | [Activity Log](admin/retention-and-activity-log.md#review-activity-log); bounded results and sanitized details. |
| Email campaigns | Templates, recipient imports, document delivery, review and sending | [Email campaigns](admin/email-campaigns.md), [email configuration](admin/email-configuration.md); Super Admin-only. |

For a task-oriented starting point, use [use cases](use-cases.md); for unfamiliar terms, use the [glossary](glossary.md). The [role map](roles-and-permissions.md) explains why two staff members can see different tools.

Source inventory: [web routes](../routes/web.php), [admin routes](../routes/admin.php), [navigation definitions](../app/Support/AdminNavigation.php), [telephony features](../app/Services/TelephonyFeatureService.php).
