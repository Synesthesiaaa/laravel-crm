# Telephony boundaries

```mermaid
flowchart LR
    Browser[CRM browser] -->|HTTP session APIs| Laravel[Laravel controllers and services]
    Browser -->|SIP.js WSS when sipjs owns media| Asterisk[External Asterisk]
    Iframe[VICIdial iframe ViciPhone] -->|Media when viciphone owns it| Asterisk
    Laravel -->|Agent and Non-Agent HTTP APIs| Vici[External VICIdial]
    Vici -->|Public push and Call URL callbacks| Laravel
    Asterisk -->|TCP AMI events| Listener[Persistent AMI listener]
    Listener --> State[Correlation and CallStateService]
    Bridge[External event forwarder if configured] -->|AMI HTTP webhook| Laravel
    Laravel --> State
    State --> DB[(Call sessions and local history)]
    State --> Reverb[Deferred application broadcasts]
    Reverb --> Browser
    Browser -->|Polling fallback| Laravel
```

SIP signaling/media, AMI TCP events, VICIdial HTTP actions, inbound callbacks, and Reverb application events are separate transports. The selected persistent AMI listener processes events in-process; an HTTP AMI webhook is an independent ingress path. An external forwarder is conditional, not asserted to be deployed. Only one browser media owner should be selected. Source: telephony services, AMI command, webhook controllers, webrtc configuration and browser modules.
