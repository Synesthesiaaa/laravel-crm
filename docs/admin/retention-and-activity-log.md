# Data retention and Activity Log

Both surfaces require **Super Admin**. Retention changes data; Activity Log helps investigate activity. Neither is a substitute for a verified backup and recovery process.

## Configure a retention policy

Open **Super Admin → Configuration → Data Retention**.

1. Select the active campaign form. Policies are stored per form; saving uses that form as the unique policy key and can replace its existing settings.
2. Choose the inclusive **from** and **to** dates. The service applies these to the form storage table's `date` column, not a general age threshold over every CRM record.
3. Choose **whole record** deletion or **selected fields** clearing. For selected fields, choose only eligible fields shown for that form.
4. Choose a one-time run date/time, or recurring daily, weekly, or monthly scheduling. Review active state and next run.
5. Save the policy and recheck the complete scope before it is allowed to execute.

Selected-field clearing is limited to fields the service considers safe to clear. It is not an arbitrary field eraser; system identifiers and unsupported storage constraints affect eligibility. Whole-record mode deletes matching form rows. The result's affected-record count can refer to updated rows in selected-field mode, despite internal/deletion terminology.

The date window is stored as fixed dates. A recurring schedule repeats processing for that configured range; it is not automatically a rolling “older than N days” policy. Scheduled execution depends on the deployment scheduler. A saved policy does not prove a scheduled run has happened.

## Manual execution and deletion

**Run** invokes the retention service immediately; it is a destructive data operation, not a preview. Confirm form, dates, deletion mode, field list, recovery readiness, and organizational approval before using it. Review status, error, and affected count afterward. Do not keep retrying a failed destructive action without understanding the failure.

Deleting a policy removes configuration only; the controller explicitly reports that form data was not changed. It does not restore data cleared by earlier runs. Retention over one form table is not a guarantee of deletion from call history, captures, external VICIdial, exports, backups, or every derived record. Plan those stores separately.

## Review Activity Log

Open **Super Admin → Activity Log** (`/admin/activity-log`). Narrow the entries by actor, event, resource, search, or from/to dates. The entries endpoint caps a request at 100 entries; a short recent list is not a complete historical export.

Use actor, action, resource, timestamp, and available details together when investigating a change. Application activity recording and sanitization deliberately omit/redact some information; do not expect passwords or a full copy of every request. Some system activity and external phone-system actions may have separate logs. Absence from the current filtered list is not proof an action never occurred.

When reporting an issue, include the relevant event identifier/time and non-sensitive context. Use [troubleshooting](../operations/troubleshooting.md) for escalation and avoid editing production records merely to reproduce an audit event.

Source: [retention controller](../../app/Http/Controllers/Admin/DataRetentionController.php), [retention validation](../../app/Http/Requests/Admin/UpsertDataRetentionPolicyRequest.php), [retention service](../../app/Services/DataRetentionService.php), [schedule service](../../app/Services/DataRetentionScheduleService.php), [Activity Log controller](../../app/Http/Controllers/Admin/ActivityLogController.php), [activity sanitizer](../../app/Services/ActivityLogSanitizer.php).
