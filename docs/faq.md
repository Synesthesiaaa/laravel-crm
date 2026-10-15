# Frequently asked questions

## Why is Agent Screen or a call tool missing?

Agent Screen access and advanced tools are controlled by telephony feature settings as well as route access. Ask a Super Admin to check configuration. Missing controls do not necessarily mean your account is broken. See [configuration](admin/configuration.md).

## I can sign in to CRM. Why can I not make a call?

CRM login and the VICIdial phone session are separate. Check the Phone widget state and supplied VD/Phone Login details. Server configuration, browser media access, and external permissions also matter. CRM user creation/update does not guarantee remote credential synchronization succeeded. See [daily workflow](user-guide/daily-workflow.md) and [troubleshooting](operations/troubleshooting.md).

## Does minimizing Phone log me out?

No. The widget explicitly keeps the embedded session running when minimized. Use the phone logout control when ending the phone session, then sign out of CRM according to your shift procedure.

## Why can I not dial again?

The Agent Screen can block dialing until the previous disposition is completed. Review wrap-up and any error first. Do not save an inaccurate outcome to bypass the block. See [calls and dispositions](user-guide/calls-and-dispositions.md).

## I saved a form. Why is it not in Capture Records?

Campaign Forms and Agent Screen captures are separate stores. Campaign form rows are reviewed in Data Master; capture submissions are reviewed in Capture Records. Call History and Records List show activity rather than serving as universal editors. See [forms and records](user-guide/forms-and-records.md).

## Can an Agent backdate a form?

The ordinary form submission request forces the formal Agent role's date to the current application day. Managers should use the appropriate approved correction process rather than asking agents to manipulate the date control.

## Why is a hidden field still reported as required?

Visibility and requiredness are separate configuration rules. Ordinary server validation still uses the field's required flag. Ask an Admin/Super Admin to review the combination; do not insert invented data. See [field logic](admin/forms-and-field-logic.md).

## Does an attendance break pause the phone?

Do not assume so. CRM attendance statuses and telephony session pause are separate actions. Follow the procedure for both if required. Attendance history is not a payroll-hours calculator. See [attendance](user-guide/attendance.md).

## Does scheduling a callback guarantee a reminder or an exact-time call?

No such guarantee is documented. Schedule against the correct lead and verify with **Info**. Time interpretation and execution depend on VICIdial. A callback note/disposition is not itself a scheduled callback. See [callbacks](user-guide/callbacks.md).

## Why do two reports disagree?

First compare campaign mappings, mode, date/timezone, disposition scope, sales rules, and source freshness. Form sales differ from telephony outcomes. Unavailable data is not zero, and a live operational window is not the same as an entire historical day. See [reports](user-guide/reports.md).

## Is Operations Insights restricted to QA or OM?

No. QA/OM are organizational responsibilities, not formal roles. Current Operations Insights page/data routes allow guests; ordinary Reports is management-restricted. Deployment owners must evaluate exposure. See [roles](roles-and-permissions.md).

## Can a Team Leader manage users or create forms?

Those setup routes require Super Admin. Team Leaders have several real data-management writes and are not read-only. Field/disposition creation and update require Admin or Super Admin, while current delete routes are broader. See the [access table](roles-and-permissions.md).

## My CSV import failed. Can I upload it again?

Reconcile imported and failed counts first. Some rows may already have been saved; a retry can duplicate them. The current Lead Hopper job imports campaign form submissions and does not populate hopper/VICIdial leads. See [campaign imports](admin/campaigns.md#lead-hopper-imports).

## Does deleting a retention policy restore data?

No. It removes policy configuration and does not change form data. Earlier deletion/clearing is not reversed. A retention policy targets a form table, not every exported or external copy. See [retention](admin/retention-and-activity-log.md).

## Does a success message prove an external action completed?

Read the action-specific result. Local account success can coexist with remote credential sync failure; queued imports/email work still require processing; source failures can leave reports stale. Confirm the relevant remote or stored result through an approved workflow.

For an issue not covered here, use [troubleshooting](operations/troubleshooting.md). Include campaign, page, approximate time, and error text; avoid sending passwords or unneeded customer data.
