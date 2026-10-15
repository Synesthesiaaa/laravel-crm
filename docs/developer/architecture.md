# Architecture

The CRM is a Laravel 12 application using Blade, Alpine, Tailwind 4, and Vite, with browser telephony through SIP.js and private realtime events through Echo/Reverb. The dependency constraints live in [composer.json](../../composer.json) and [package.json](../../package.json); lockfiles determine installed versions.

Requests enter [bootstrap/app.php](../../bootstrap/app.php), which wires web/API routing, broadcast channels, middleware aliases, rate limiters, and CSRF exceptions. Most JSON endpoints are web routes using the same session and campaign context as the UI. Only the small route set in [routes/api.php](../../routes/api.php) uses Sanctum. See [authentication](authentication.md).

Controllers coordinate Form Requests, domain services, and repositories. [AppServiceProvider](../../app/Providers/AppServiceProvider.php) binds repository interfaces, registers model policies and configuration observers, composes shared view data, and enables lazy-loading prevention outside production. Services use Eloquent and explicit transactions for local writes; external VICIdial calls are a separate failure boundary, not part of an atomic remote/local transaction.

Campaign configuration primarily comes from database repositories with configured fallback data when no database campaign configuration is returned. `CampaignService` caches the combined configuration for 300 seconds. Campaign middleware resolves a valid session campaign or the first available campaign and returns 404 when none exists; it does not prove per-user membership.

Outbound calls currently use `CallOrchestrationService` → VICIdial Agent API `external_dial`. The browser's SIP registration owns audio according to the configured media path. AMI events, VICIdial push events, polling, and reconciliation update local call state. The direct AMI originate service/job remains in the tree but is not the normal outbound-controller path. See [telephony diagram](../diagrams/telephony.md).

Reporting combines local durable history, configured campaign/server mappings, and remote providers. A local history response is not proof that a current remote synchronization succeeded. Preserve freshness/error metadata and do not silently turn unavailable provider values into zero.

Events trigger local listeners and selected broadcasts. Current broadcast events use the `deferred` connection and after-commit/rescue contracts; durable application jobs have separate queue behavior. Email delivery has its own connection and queue; see [SMTP](../integrations/smtp.md) and [email campaign reference](../EMAIL_CAMPAIGNS.md).

[Architecture diagram](../diagrams/architecture.md) · [services](services.md) · [events and jobs](events-and-jobs.md)
