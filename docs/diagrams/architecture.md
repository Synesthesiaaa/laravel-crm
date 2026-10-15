# Application architecture

```mermaid
flowchart LR
    Browser[Blade and Alpine browser UI] --> Web[Web routes: sessions and CSRF]
    External[External token client] --> API[Sanctum routes]
    Ingress[Machine webhooks and callbacks] --> Public[Public ingress controllers]
    Web --> Controllers[Controllers and Form Requests]
    API --> Controllers
    Public --> Services[Domain services]
    Controllers --> Services
    Services --> Repo[Repositories and Eloquent]
    Repo --> DB[(CRM database)]
    Services --> Vici[VICIdial transport services]
    Vici --> Remote[External VICIdial server]
    Services --> Events[Events and jobs]
    Events --> Broadcast[Deferred broadcasts through Reverb]
    Broadcast --> Browser
    Events --> Workers[Configured durable queues]
```

Browser requests use session-based web middleware; the small Sanctum route family serves token clients. Controllers validate/delegate to services, which access persistence or external integration boundaries. Events support listeners, deferred broadcasts and explicit durable jobs. Public ingress has its own secret rules. Database/remote operations are not a distributed transaction. Source: bootstrap/app.php, routes, AppServiceProvider, services, events and jobs.
