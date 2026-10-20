# CRM UI reconstruction audit

Source audit: 2026-10-19. This is an implementation inventory, not certification of live integrations. Browser evidence and final parity results are recorded in `ui-reconstruction-validation.md`.

## Architecture and boundaries

Laravel 12.53 / PHP 8.5, server-rendered Blade, Alpine 3.15.8, Tailwind 4.2, Vite, ApexCharts, SIP.js, Echo/Reverb. `app.css` supplies semantic tokens and components; `crm-minimal.css` supplies the incumbent restrained workspace. Authentication and public landing have separate layouts. The shell replaces only `#main-content` during soft navigation. Phone and quick-form widgets are mounted outside it; their JavaScript lifecycle must remain unchanged.

Actual account roles: Agent, Team Leader, Admin, Super Admin. QA, Supervisor and Operations Manager are not distinct seeded roles. `CheckRole`, `EnsureCampaignSelected` and telephony feature middleware remain authoritative; navigation cannot grant access. Campaign context is not a new membership guarantee. Existing public Operations Insights/diagnostics remain existing security boundaries; this redesign does not expand or silently change them.

## Findings and implementation priority

1. **P0: Preserve operational state and access.** Do not replace phone/quick-form roots, change real-time subscriptions, KPI SQL, campaign scoping, conditional fields or recipient/PDF mapping. Test roles and lifecycle hooks.
2. **P1: Task navigation.** Two large role-labelled lists mix monitoring, configuration and records. Group actual destinations as Overview, Operations, Analytics, Management, Administration; keep campaign form selection and Agent Screen feature gate.
3. **P1: Record-first management.** Users, campaigns, servers and form setup begin with large creation forms. Use native, labelled disclosures retaining DOM state, open automatically after creation errors. Editing a row must not open a different form.
4. **P1: Email task separation.** Template creation/import, recipient campaign preparation and history currently occupy one long screen. Split them into linked GET sub-workspaces, preserve normal POST targets and old-input recovery, and display review/delivery stages with honest statuses.
5. **P1: Dense data accessibility.** Tables already paginate server-side where supported, but generic scroll wrappers are not keyboard focusable/named. Add shared named scroll regions, tabular figures and consistent headers without hiding columns or building massive client collections.
6. **P2: Visual hierarchy.** Existing flat tokens are a foundation; align gutters and page headings, remove decorative KPI icon tiles and unnecessary hero treatment, flatten tool directories, and keep status color for actual operational states. Replace unconditional Live on the non-polling management dashboard with a snapshot label.
7. **P2: Agent workspace.** Lead/capture and call/disposition already exist; give the call rail a clear responsive position, keep all x-data/event handlers and field DOM. No live call is needed for visual validation.
8. **P2: Forms/settings/reports.** Reuse fields and groups, lower nesting, keep filters/actions and empty/loading/error states. Validate configuration tabs, dynamic fields, readonly agent date, report disclosures and theme persistence.

## Per-screen source inventory

All rows inherit shell landmarks, skip link, theme persistence, notifications, focus ring and responsive off-canvas navigation. A source state marker indicates implementation presence, not a successful browser check. Risk is high for integration/stateful screens and medium for CRUD/report views.

| Blade screen | Page/task | Current composition and controls | Existing states | Problems and reconstruction | Risk |
| --- | --- | --- | --- | --- | --- |
| `admin/activity_log` | Activity Log | page heading, forms/filters | empty, loading, error, success, disabled | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/agent_screen` | Agent Screen Configuration | page heading, shared table, forms/filters | empty, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | High |
| `admin/attendance_logs` | Attendance Logs | page heading, shared table, forms/filters | empty, success | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/attendance_status_types` | Attendance Statuses | page heading, shared table, forms/filters | empty, error, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/campaigns` | Campaigns | page heading, shared table, forms/filters | empty, loading, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/capture_records` | Capture Records | page heading, shared table, forms/filters | empty, error, success | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/capture_records_edit` | Edit Capture Record | page heading, forms/filters | empty, error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/configuration` | System Configuration | page heading, tabs | success | Consistent section/field hierarchy; preserve tabs, secrets, retention and separate test actions | Medium |
| `admin/dashboard` | Management Dashboard | forms/filters, charts | empty, loading | Summary-first flat hierarchy, actual freshness, retained periods and filters; no fabricated metrics | High |
| `admin/data_master` | Data Master | page heading, shared table, forms/filters | empty, error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/data_master_edit` | Edit Record - Admin | page heading, forms/filters | error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/disposition_codes` | Disposition Codes | page heading, shared table, forms/filters | empty, error, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/disposition_records` | Disposition Records | page heading, shared table, forms/filters | empty | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/email-campaigns/index` | Email Campaigns | page heading, shared table, forms/filters, panels, tabs | empty, error, success, disabled | Separate prepare/templates/delivery; preserve preflight, named errors, passwords and provider-state language | High |
| `admin/email-campaigns/review` | Review email campaign | page heading, shared table, forms/filters | empty, error, success, disabled | Separate prepare/templates/delivery; preserve preflight, named errors, passwords and provider-state language | High |
| `admin/email-configuration` | Email Configuration | page heading, forms/filters, panels | empty, error, success, disabled | Separate prepare/templates/delivery; preserve preflight, named errors, passwords and provider-state language | High |
| `admin/extraction` | Data Extraction | page heading, forms/filters | empty, error, disabled | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/field_logic` | Field Logic | shared table, forms/filters | empty, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | High |
| `admin/field_logic_edit` | Edit Field | forms/filters | empty, error, success, disabled | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | High |
| `admin/forms` | Forms | page heading, shared table, forms/filters, modal | empty, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/lead_hopper` | Lead Hopper | page heading, shared table, forms/filters | empty, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/records_list` | Records List | page heading, shared table, forms/filters | empty | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `admin/supervisor` | Supervisor Dashboard | page heading, charts | empty, loading, error, success, disabled | Summary-first flat hierarchy, actual freshness, retained periods and filters; no fabricated metrics | High |
| `admin/telephony-monitor` | Telephony Monitor | page heading | empty, error | Summary-first flat hierarchy, actual freshness, retained periods and filters; no fabricated metrics | High |
| `admin/users` | User Access | page heading, shared table, forms/filters | empty, error, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `admin/vicidial_servers` | ViciDial Servers | page heading, shared table, forms/filters | empty, error, success, disabled | Records before long creation UI; labelled disclosure; retain edits, validation and scope | Medium |
| `agent/capture_webform` | agent/capture_webform | forms/filters | empty, error, success, disabled | Clear lead/capture/call regions; responsive rail; preserve mounted state and listeners | High |
| `agent/index` | Agent Screen | forms/filters | empty, error, success, disabled | Clear lead/capture/call regions; responsive rail; preserve mounted state and listeners | High |
| `attendance/index` | My Attendance | page heading, shared table, forms/filters | empty | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `auth/login` | auth/login | forms/filters | loading, error, success, disabled | Retain existing branded entry, campaign choice and theme; validate responsive and errors | Medium |
| `auth/login-pending` | auth/login-pending | forms/filters | error | Retain existing branded entry, campaign choice and theme; validate responsive and errors | Medium |
| `dashboard` | Dashboard | table, forms/filters, modal, charts | empty, loading, success | Summary-first flat hierarchy, actual freshness, retained periods and filters; no fabricated metrics | High |
| `email-campaigns/unsubscribe` | email-campaigns/unsubscribe | forms/filters | static/server response | Separate prepare/templates/delivery; preserve preflight, named errors, passwords and provider-state language | High |
| `errors/403` | Access Denied | standalone surface | error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `errors/404` | Page Not Found | standalone surface | error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `errors/500` | Server Error | standalone surface | error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `errors/layout` | errors/layout | standalone surface | error | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `forms/show` | forms/show | standalone surface | static/server response | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `forms/widget` | forms/widget | standalone surface | static/server response | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `layouts/app` | layouts/app | table, forms/filters, modal, charts | empty, loading, error, success, disabled | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `operations-insights` | operations-insights | table, forms/filters, charts | loading, error, success, disabled | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `records/index` | Call History | page heading | static/server response | Shared readable table/form rhythm, contained keyboard scrolling, preserved actions and filters | Medium |
| `reports/index` | Telephony Reports | page heading, shared table, charts | empty, loading, error, success, disabled | Summary-first flat hierarchy, actual freshness, retained periods and filters; no fabricated metrics | High |
| `welcome` | welcome | standalone surface | static/server response | Retain existing branded entry, campaign choice and theme; validate responsive and errors | Medium |

## Route-to-page and authorization inventory

Generated from the current Laravel route registry before implementation. JSON/download/poll endpoints remain listed to distinguish them from pages. Controller view mapping is in the screen inventory; parameterized routes require synthetic IDs. Mutation routes remain unchanged and are checked through existing feature tests.

| Named route | URI | Handler | Access |
| --- | --- | --- | --- |
| `home ` | `/` | `Illuminate\Routing\ViewController` | Public |
| `admin.dashboard ` | `admin` | `Admin\AdminDashboardController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.activity-log.index ` | `admin/activity-log` | `Admin\ActivityLogController@index` | Super Admin; campaign |
| `admin.activity-log.entries ` | `admin/activity-log/entries` | `Admin\ActivityLogController@entries` | Super Admin; campaign |
| `admin.agent-screen.index ` | `admin/agent-screen` | `Admin\AgentScreenController@index` | Super Admin; campaign |
| `admin.attendance.index ` | `admin/attendance` | `Admin\AttendanceLogsController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.attendance-statuses.index ` | `admin/attendance-statuses` | `Admin\AttendanceStatusTypesController@index` | Super Admin; campaign |
| `admin.campaigns.index ` | `admin/campaigns` | `Admin\CampaignsController@index` | Super Admin; campaign |
| `admin.campaigns.vicidial-campaigns ` | `admin/campaigns/{campaign}/vicidial-campaigns` | `Admin\CampaignsController@vicidialCampaigns` | Super Admin; campaign |
| `admin.capture-records.index ` | `admin/capture-records` | `Admin\CaptureRecordsController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.capture-records.edit ` | `admin/capture-records/edit/{record}` | `Admin\CaptureRecordsController@edit` | Team Leader,Admin,Super Admin; campaign |
| `admin.configuration ` | `admin/configuration` | `Admin\ConfigurationController@index` | Super Admin; campaign |
| `admin.data-master.index ` | `admin/data-master` | `Admin\DataMasterController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.data-master.edit ` | `admin/data-master/edit/{id}` | `Admin\DataMasterController@edit` | Team Leader,Admin,Super Admin; campaign |
| `admin.disposition-codes.index ` | `admin/disposition-codes` | `Admin\DispositionCodesController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.disposition-records.index ` | `admin/disposition-records` | `Admin\DispositionRecordsController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.email-campaigns.index ` | `admin/email-campaigns` | `Admin\EmailCampaignsController@index` | Super Admin; campaign |
| `admin.email-campaigns.personalized-template ` | `admin/email-campaigns/personalized-template` | `Admin\EmailCampaignsController@downloadPersonalizedTemplate` | Super Admin; campaign |
| `admin.email-campaigns.recipients-template ` | `admin/email-campaigns/recipients-template` | `Admin\EmailCampaignsController@downloadRecipientTemplate` | Super Admin; campaign |
| `admin.email-campaigns.preview ` | `admin/email-campaigns/templates/{template}/preview` | `Admin\EmailCampaignsController@preview` | Super Admin; campaign |
| `admin.email-campaigns.document ` | `admin/email-campaigns/{campaign}/documents/{document}` | `Admin\EmailCampaignsController@document` | Super Admin; campaign |
| `admin.email-campaigns.report ` | `admin/email-campaigns/{campaign}/report` | `Admin\EmailCampaignsController@report` | Super Admin; campaign |
| `admin.email-campaigns.review ` | `admin/email-campaigns/{campaign}/review` | `Admin\EmailCampaignsController@review` | Super Admin; campaign |
| `admin.email-configuration.index ` | `admin/email-configuration` | `Admin\EmailConfigurationController@index` | Super Admin; campaign |
| `admin.extraction.index ` | `admin/extraction` | `Admin\ExtractionController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.field-logic.index ` | `admin/field-logic` | `Admin\FieldLogicController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.field-logic.edit ` | `admin/field-logic/{formField}/edit` | `Admin\FieldLogicController@edit` | Team Leader,Admin,Super Admin; campaign |
| `admin.forms.index ` | `admin/forms` | `Admin\FormsController@index` | Super Admin; campaign |
| `admin.lead-hopper.index ` | `admin/lead-hopper` | `Admin\LeadHopperController@index` | Super Admin; campaign |
| `admin.records.index ` | `admin/records` | `Admin\RecordsListController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.supervisor ` | `admin/supervisor` | `Admin\SupervisorController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.telephony-monitor ` | `admin/telephony-monitor` | `Admin\TelephonyMonitorController@index` | Team Leader,Admin,Super Admin; campaign |
| `admin.users.index ` | `admin/users` | `Admin\UsersController@index` | Super Admin; campaign |
| `admin.vicidial-servers.index ` | `admin/vicidial-servers` | `Admin\VicidialServersController@index` | Super Admin; campaign |
| `agent.index ` | `agent` | `AgentController@index` | Authenticated; campaign; feature gate |
| `agent-webforms.show ` | `agent-webforms/{campaign}` | `AgentCaptureWebformController@show` | Authenticated; feature gate |
| `attendance.index ` | `attendance` | `AttendanceController@index` | Authenticated; campaign |
| `- ` | `broadcasting/auth` | `Illuminate\Broadcasting\BroadcastController@authenticate` | Public |
| `dashboard ` | `dashboard` | `DashboardController@index` | Authenticated; campaign |
| `email-campaigns.unsubscribe ` | `email/unsubscribe/{recipient}` | `EmailUnsubscribeController@show` | Public |
| `forms.show ` | `forms/{type}` | `FormController@show` | Authenticated; campaign |
| `login ` | `login` | `Auth\LoginController@showLoginForm` | Guest; redirect authenticated users |
| `login.pending ` | `login/pending` | `Auth\LoginController@showPendingLogin` | Guest; pending-login session |
| `operations-insights ` | `operations-insights` | `OperationsInsightsController@index` | Public |
| `records.index ` | `records` | `RecordsController@index` | Authenticated; campaign |
| `reports.index ` | `reports` | `ReportsController@index` | Team Leader,Admin,Super Admin; campaign |
| `- ` | `up` | `Closure` | Public |

## Feature-parity checklist

- [x] Authentication: browser sign-in/campaign confirmation and entry captures; feature-test invalid-login/campaign behavior.
- [x] Four roles: browser role sessions/403 checks and automated exact destination parity.
- [x] Shell: browser collapse/mobile menu/Escape, search and theme checks; notification/layout source and automated regression checks.
- [x] Setup: browser disclosure, old input/error recovery, create/delete confirmation and pagination; row-edit recovery regression test.
- [x] Forms: browser review/back/save; full-suite validation, conditional-field and readonly-date coverage.
- [x] Reports: browser surfaces, scopes and empty/unavailable states; full-suite filters/export regression coverage.
- [x] Email: browser three workspaces/review; full-suite template/import/CSV/mapping/synthetic-error behavior; no real sending.
- [x] Agent: browser control presentation/open-close and exact persistent-root identity across soft navigation; automated handler tests. Live calls remain unverified.
- [x] Configuration: browser all tabs; full-suite branding, secret-retention, SMTP-test and configuration coverage. Live connectivity remains unverified.
- [x] Accessibility/responsiveness: browser keyboard samples, shared labels/regions, both themes and representative widths. Screen-reader certification remains unverified.

Live SIP/media, VICIdial/Asterisk connectivity, Reverb transport and real SMTP delivery require configured external services and are never inferred from a successful build or synthetic UI check.
