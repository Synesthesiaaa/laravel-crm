# Project structure

| Path | Responsibility |
|---|---|
| `app/Http/Controllers` | Blade and JSON request orchestration; `Admin`, `Api`, `Api/V1`, and `Auth` namespaces |
| `app/Http/Requests` | Authentication, API, admin, form, and email validation |
| `app/Http/Middleware` | Campaign resolution, literal role checks, telephony feature gates, response security, activity audit |
| `app/Models`, `app/Casts` | Eloquent persistence, relations, serialization, secret-related casts |
| `app/Policies` | Resource permissions; inspect controller authorization calls as well as route roles |
| `app/Services` | Forms, campaign configuration, attendance, dashboards, audit, email, and telephony domain logic |
| `app/Services/Telephony` | VICIdial transports, endpoint/scope resolution, call state, reporting, synchronization, AMI |
| `app/Telephony` | Operational state and queue-health value objects |
| `app/Contracts/Repositories`, `app/Repositories` | Repository interfaces and persistence implementations bound in AppServiceProvider |
| `app/Events`, `app/Listeners`, `app/Observers` | Domain notifications, audit/cache invalidation, configuration change hooks |
| `app/Jobs`, `app/Console/Commands` | Background work and administrative/telephony command implementations |
| `app/Support` | Shared result/error objects, navigation, field visibility and other helpers |
| `routes/web.php`, `routes/admin.php` | UI plus session-authenticated JSON APIs; selected public endpoints |
| `routes/api.php`, `routes/channels.php`, `routes/console.php` | Sanctum routes, broadcast authorization, scheduled tasks |
| `resources/views`, `resources/js`, `resources/css` | Blade layouts/pages, Alpine/browser modules, styling |
| `database/migrations`, `database/seeders`, `database/factories` | Schema history, seed data, test data builders |
| `config` | Runtime configuration declarations; do not treat defaults as deployed values |
| `tests/Feature`, `tests/Unit`, `tests/JavaScript` | HTTP/domain/browser-module test sources |
| `docs/telephony`, `docs/asterisk`, `docs/vicidial` | Specialized historical guides/examples; qualify against current code |
| `openspec` | Change specifications and archive, where present |

Start a feature investigation with its named route, controller, request, service, persistence, Blade/JavaScript consumers, then tests. Some form submissions use configured physical tables rather than one universal Eloquent submission model.
