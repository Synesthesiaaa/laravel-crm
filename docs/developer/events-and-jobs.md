# Events, listeners, jobs, and scheduling

[EventServiceProvider](../../app/Providers/EventServiceProvider.php) explicitly maps:

| Event | Registered listener(s) |
|---|---|
| `DashboardDataUpdated` | `InvalidateDashboardStats` |
| `FormSubmitted` | `LogFormSubmission`, `InvalidateDashboardCache` |
| `DispositionSaved` | `LogDispositionSaved` |
| `UserLoggedIn`, `UserLoggedOut` | `LogSecurityEvent` |
| `LeadImported` | `NotifyLeadImportComplete` |
| `CallOriginated` | `LogCallOriginated` |
| `CallStateChanged` | `LogCallStateChanged` |

Other broadcast events include `InboundCallReceived`, `VicidialAgentEvent`, `TelephonyEventLogged`, `DashboardLayoutUpdated`, and `ActivityLogCreated`. Current broadcast classes implement `ShouldBroadcast`, `ShouldDispatchAfterCommit`, and `ShouldRescue`, with `connection = deferred`. Do not describe these as durable Redis jobs: delivery after the response is a different boundary. Check [realtime](realtime.md) for channels and polling fallback.

| Job | Purpose | Source retry/timeout policy |
|---|---|---|
| `AsteriskOriginateJob` | Existing direct AMI originate path; not current outbound controller's primary path | 3 tries, 10/30 second backoff, 60 second timeout |
| `CallNoAnswerTimeoutJob` | Fail a call still unanswered after delay | 1 try, 60 seconds |
| `ReconcileCallStateJob` | Recover stale active sessions | 1 try, 120 seconds |
| `ProcessTelephonyDeadLettersJob` | Convert recent failed application jobs into diagnostic alerts | 1 try, 60 seconds |
| `SyncVicidialDispositionJob` | Remote disposition synchronization | 1 try, 15 seconds |
| `SyncVicidialLeadFieldsJob` | Remote field synchronization | 1 try, 10 seconds |
| `SyncVicidialCallHistoryJob` | Sync local imported history | Configured tries/timeout (defaults 3/120); 60/180/300 second backoff; telephony queue |
| `ImportLeadsCsvJob` | CSV-to-campaign-form submissions; [does not populate LeadHopper](../admin/campaigns.md#lead-hopper-imports) | 2 tries, 60 second backoff, 600 second timeout |
| `SendEmailCampaignBatch`, `SendEmailCampaignRecipient` | Email pipeline | 1 try, 60 seconds; separate configured connection/queue |

These are class declarations, not guarantees about effective worker behavior: worker CLI/configuration can constrain attempts/timeouts. Inspect [jobs](../../app/Jobs), [queue config](../../config/queue.php), and [Horizon config](../../config/horizon.php) together. History sync implements uniqueness and a campaign-scoped overlap lock; its configured defaults are in `config/vicidial.php`.

[Console scheduling](../../routes/console.php) declares activity-log cleaning (daily 01:00), notification-read pruning (01:30), failed-job pruning (02:00), form retention (every minute), cache work (hourly), stale-call reconciliation (15 minutes), dead-letter monitoring (10 minutes), Horizon snapshots (5 minutes), recent call-history sync (every minute when enabled), and email maintenance (5 minutes). The scheduler declaration does not prove an OS scheduler or worker is running. Retention and pruning modify data.

[Queue diagram](../diagrams/queues.md) · [operations](../operations/README.md). Do not start workers or scheduler tasks against a connected environment merely to check documentation.
