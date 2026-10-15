# Services and extension boundaries

| Area | Main entry points | Responsibility |
|---|---|---|
| Authentication | `AuthService`, `UserService`, `UserRepository` | Credential validation, session replacement, attendance and local telephony logout |
| Campaign configuration | `CampaignService`, repositories, `CampaignConfigurationObserver` | DB-backed campaign/forms, configured fallback, cache invalidation |
| Forms/capture | `FormSubmissionService`, `FormFieldsSchemaSyncService`, `AgentCaptureWebformService`, `DataMasterService`, `ExtractionService` | Configured form validation/storage, schema alignment, capture, administration and exports |
| Dispositions | `DispositionService`, `VicidialDispositionSyncService` | Local result persistence, pending-disposition gate and remote synchronization |
| Attendance | `AttendanceStatusService`, `AttendanceRealtimeService` | Status lifecycle, logout auto-close and realtime projections |
| Dashboards | `DashboardStatsService`, period/range/sales-rule/layout services, `AdminDashboardService` | Date/campaign-aware metrics, display rules and layouts |
| Notification/audit | `NotificationService`, `Services/Notifications/*`, `UserActivityRecorder`, `ActivityLogSanitizer` | Derived/durable notification reads, activity capture and redaction |
| Retention | `DataRetentionService`, `DataRetentionScheduleService` | Scheduled configured record/field removal; potentially destructive |
| Telephony transport | `VicidialProxyService`, `VicidialNonAgentApiService`, `VicidialEndpointResolver`, `AsteriskAmiService` | External APIs and AMI client boundary |
| Telephony state | `CallOrchestrationService`, `CallStateService`, `CallUuidMappingService`, `VicidialSessionService` | Dial/hangup workflow, legal state changes, correlation, local agent session |
| Telephony tools | `TransferService`, `RecordingService`, `CallbackService`, `LeadService`, `LeadHydrationService`, `DtmfService` | Typed telephony operations and normalized lead data |
| Reporting/sync | `RealtimeTelephonyReportService`, `HistoricalTelephonyReportService`, `LocalCallHistoryQueryService`, `VicidialCallHistorySyncService`, providers/scope resolvers | Remote/local history separation, scoped remote reads, durable synchronization |
| Operations | `TelephonyHealthService`, `TelephonyReconciliationService`, `TelephonyAlertService`, `SupervisorOperationalService` | Health, stale-state recovery and supervisor projections |
| Feature/bootstrap | `TelephonyFeatureService`, `TelephonyBootstrapService`, `TelephonyCampaignResolver` | Feature availability and initial agent/campaign settings |
| Email | `EmailCampaign*`, `EmailSmtpService`, importer/document/PDF services | See [email reference](../EMAIL_CAMPAIGNS.md) and [SMTP integration](../integrations/smtp.md) |

Most service implementations are in [app/Services](../../app/Services); telephony implementations are in [app/Services/Telephony](../../app/Services/Telephony). Several operations return `App\Support\OperationResult` (`success`, optional `message`, optional `data`); transport services can instead return arrays. Controllers determine HTTP status and envelope: there is no universal response wrapper.

Reuse existing repositories and request objects rather than bypassing their campaign scopes or inventing another transport. Network side effects are not safely retried solely because the HTTP client supports retries. `VicidialProxyService` restricts retries by action safety; preserve this distinction for dialing, transfer, and other writes. Local DB success and remote success can diverge; surface that state and preserve job idempotence.
