# Database and persistence

This is a source model/schema map, not a live database audit. [Migrations](../../database/migrations) define schema evolution; [models](../../app/Models) define application relationships. An Eloquent relation does not itself prove an enforced foreign key. Inspect the complete migration history before changing schema.

| Domain | Models/tables and important distinctions |
|---|---|
| Identity | `User` (`users`), database `sessions`, Sanctum `personal_access_tokens`; user role string drives current route checks |
| Campaign configuration | `Campaign`, `Form`, `FormField`, `AgentScreenField`, `DispositionCode`, `VicidialServer`, `CampaignVicidialMapping` |
| Dynamic forms | Forms refer to physical form-data tables; `FormFieldsSchemaSyncService`, migrations, and repository allowlists control storage columns |
| Local calls | `CallSession`, `AgentCaptureRecord`, `AgentCallRecord`, `CampaignDispositionRecord`, `CrmCallHistory` (`crm_call_history`) |
| Imported telephony history | `TelephonyCallHistory` and `VicidialCallHistorySyncState`; distinct from local CRM call-history rows |
| Agent sessions | `VicidialAgentSession` is local VICIdial session state, distinct from HTTP session and SIP registration |
| Leads | `LeadHopper` (`lead_hopper`), `LeadImport`; assignment uses `assigned_to_user_id`, importer uses `uploaded_by_user_id` |
| Attendance | `AttendanceLog` belongs to user and optional `AttendanceStatusType` |
| Preferences | `SystemSetting`, `DashboardLayout`, `UserWidgetLayout` |
| Notifications/audit | Framework `notifications`, `NotificationReadState`, Spatie activity log |
| Telephony diagnostics | `TelephonyAlert`, `UnmatchedAmiEvent` |
| Email | `EmailTemplate`, `EmailCampaign`, `EmailCampaignRecipient`, `EmailCampaignDocument`, `EmailOptOut`, `EmailSmtpSetting` |
| Infrastructure | Cache, jobs, batches and failed-job tables from migrations; storage depends on selected configuration |

Many campaign relationships use `campaign_code` → `campaigns.code`, not integer campaign IDs. In contrast `CampaignVicidialMapping`, imported telephony history, and history-sync state use integer campaign/server IDs. `Form.formFields()` joins `form_type` to `form_code` and applies campaign scope; preserve both dimensions when extending it.

`CallSession` belongs to user and campaign; `AgentCaptureRecord` belongs to user and call session. Imported `TelephonyCallHistory.crmUser()` includes soft-deleted users. Core models have selective soft-delete support; inspect the actual model/migration before assuming delete behavior. Do not confuse soft deletion with retention policies, which can permanently remove configured records/fields.

[ER diagram](../diagrams/database.md) covers verified model relationships only. `FormFieldsSchemaSyncService` and admin form-management actions can change storage at runtime; a documentation review must not invoke them. Deployment schema, row counts, populated campaigns, and remote VICIdial schema remain unverified.

Before a schema change: inspect all referencing services and exports; confirm current indexes, nullable/default behavior, existing data migration requirements, rollback limits, and ownership checks. Test against an isolated database. See [development workflow](development-workflow.md).
