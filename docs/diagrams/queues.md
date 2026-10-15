# Jobs and queues

```mermaid
flowchart TD
    HTTP[Controllers and services] --> History[SyncVicidialCallHistoryJob]
    History --> Telephony[telephony queue]
    HTTP --> Timeout[CallNoAnswerTimeoutJob]
    Timeout --> Default[default queue]
    Scheduler[Scheduler declarations] --> Reconcile[ReconcileCallStateJob]
    Scheduler --> Dead[ProcessTelephonyDeadLettersJob]
    Reconcile --> Default
    Dead --> Default
    Imports[ImportLeadsCsvJob] --> IQ[imports queue]
    Legacy[AsteriskOriginateJob] --> AQ[asterisk queue]
    Events[Broadcast events after commit] --> Deferred[deferred connection]
    Deferred --> Reverb[Configured broadcast transport]
    Email[Email jobs] --> EmailQ[Configured email connection and queue]
    Telephony --> W[Appropriate workers required]
    Default --> W
    IQ --> W
    AQ --> W
    EmailQ --> W
```

Queues shown come from job constructors or explicit dispatch configuration. A queue name does not specify its backend connection. Broadcast event classes use the deferred connection and are distinct from durable jobs. Asterisk/import queues exist even though the default development command only listens to telephony/default; provision workers according to actual intended workloads. Email has dedicated connection/queue configuration. Source: app/Jobs, event classes, composer.json, queue/Horizon config and routes/console.php.
