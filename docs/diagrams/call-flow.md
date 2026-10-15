# Outbound call workflow

```mermaid
sequenceDiagram
    participant B as Agent browser
    participant C as CallController
    participant P as SaveDispositionController
    participant O as CallOrchestrationService
    participant D as CRM database
    participant V as VICIdial Agent API
    participant S as CallStateService
    B->>C: POST dial (session, campaign, CSRF)
    C->>O: Validated dial inputs
    O->>D: Check active call, pending disposition, local session
    O->>D: Create dialing CallSession
    O->>V: external_dial
    alt Provider rejects
        O->>S: Transition to failed
        O-->>C: Failure
        C-->>B: 422 success false
    else Provider accepts
        O->>S: Transition to ringing
        O->>O: Schedule no-answer job (best effort)
        O-->>C: Session ID
        C-->>B: success true and call metadata
    end
    Note over B,V: SIP media ownership is separately configured
    Note over D,S: AMI, push events, polling and reconciliation update state
    B->>C: POST hangup
    B->>P: POST disposition/save
    P->>D: Save through DispositionService
```

Dial acceptance is not an answered call or verified audio. Current orchestration invokes VICIdial directly rather than dispatching the legacy AMI originate job. A no-answer scheduling failure is logged without turning an already accepted remote dial into an immediate setup failure. State/correlation, hangup and disposition continue through separate requests/events. Source: CallController, CallOrchestrationService, CallStateService.
