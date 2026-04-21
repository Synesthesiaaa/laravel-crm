# Laravel CRM

A **Laravel 12** customer relationship management application with **call-center telephony**: VICIdial integration, **Asterisk AMI** (chan_sip / SIP agents), **browser softphone** (SIP.js + WebRTC), **Laravel Reverb** for real-time UI updates, and **predictive dialing** with a local lead hopper.

---

## Table of contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Quick start (local development)](#quick-start-local-development)
- [Production deployment](#production-deployment)
- [Environment configuration](#environment-configuration)
- [Telephony & integrations](#telephony--integrations)
- [Useful Artisan commands](#useful-artisan-commands)
- [Frontend assets](#frontend-assets)
- [Testing](#testing)
- [Documentation](#documentation)
- [Security notes](#security-notes)
- [License](#license)

---

## Features

| Area | Capabilities |
|------|----------------|
| **Auth & roles** | Session-based auth, **Spatie Laravel Permission** (e.g. Super Admin, Admin, Team Leader, Agent) |
| **Campaigns & forms** | Campaigns, dynamic forms and fields, disposition codes |
| **Agent workspace** | Telephony panel, call state, lead hopper, dispositions, optional predictive mode |
| **Admin** | Users, campaigns, dispositions, telephony feature flags, monitoring tools |
| **Telephony** | Outbound dialing via AMI, SIP.js registration, Echo/Reverb events, AMI event listener → webhooks |
| **Observability** | Structured telephony logging, dedicated log channels, optional supervisor event stream |

---

## Tech stack

| Layer | Technology |
|--------|------------|
| **Backend** | PHP **8.2+**, Laravel **12**, Sanctum |
| **Database** | MySQL / MariaDB (typical); SQLite supported for dev |
| **Queue / jobs** | Database or **Redis** + **Laravel Horizon** (recommended in production) |
| **Real-time** | **Laravel Reverb**, **Laravel Echo**, Pusher protocol |
| **Frontend** | **Vite**, **Tailwind CSS**, **Alpine.js**, **ApexCharts** |
| **Telephony** | **SIP.js** (WebRTC), **marcelog/pami** (AMI — PHP 8 patch via Composer Patches) |
| **Ops** | Supervisor (Horizon, Reverb, `ami:listen`) — see `INSTALLATION.md` |

---

## Requirements

- **PHP** 8.2+ with extensions: `mbstring`, `bcmath`, `pdo`, `pdo_mysql` (or sqlite), `openssl`, `curl`, `json`, `xml`, `ctype`, `fileinfo`, `tokenizer` (and **Redis** / `redis` extension if using Redis)
- **Composer** 2.x  
- **Node.js** 18+ and **npm** (build-time; Vite)  
- **MySQL 8** / **MariaDB 10.6+** for production-style setups  

**Windows dev note:** Some packages expect `ext-pcntl` (Horizon). The project may use Composer platform config for local installs; use `php artisan queue:work` if Horizon is not available.

---

## Quick start (local development)

1. **Clone / copy** the project and enter the directory.

2. **Install PHP dependencies**

   ```bash
   composer install
   ```

3. **Environment**

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Configure `DB_*`, `APP_URL`, and (if using telephony) variables under **VICIdial** / **Asterisk** / **Reverb** in `.env`. See [Environment configuration](#environment-configuration).

4. **Database**

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan db:seed --class=RolesAndPermissionsSeeder
   ```

5. **Storage link**

   ```bash
   php artisan storage:link
   ```

6. **Frontend**

   ```bash
   npm ci
   npm run dev
   ```

7. **Run the app** (separate terminals or use `composer run dev` / `start-dev.bat` on Windows)

   ```bash
   php artisan serve
   php artisan queue:work
   php artisan reverb:start
   php artisan ami:listen
   ```

   For real-time features, `BROADCAST_CONNECTION=reverb` and matching `REVERB_*` / `VITE_REVERB_*` must be set.

---

## Production deployment

Follow **`INSTALLATION.md`** for:

- Nginx, PHP-FPM, MySQL, Redis, OPcache  
- `composer install --no-dev`, `npm ci && npm run build`  
- Caching (`php artisan config:cache`, `route:cache`, `view:cache`, `event:cache`), Horizon, **Reverb**, **AMI listener** under Supervisor  
- Cron for `schedule:run`  
- SSL, permissions, backups  

---

## Environment configuration

Key groups (see **`.env.example`** for full list):

| Group | Purpose |
|--------|---------|
| `APP_*`, `DB_*` | Application URL, database |
| `SESSION_*`, `CACHE_*`, `QUEUE_*`, `REDIS_*` | Sessions, cache, queues (use Redis in production) |
| `BROADCAST_*`, `REVERB_*`, `VITE_REVERB_*` | WebSockets / Echo |
| `VICI_*` | VICIdial DB/API and **`VICI_VERIFY_SSL`** (see [Security notes](#security-notes)) |
| `ASTERISK_*` | AMI host, credentials, trunk, SIP/WebRTC URLs for agents |
| `VITE_ASTERISK_*`, `VITE_SIP_*` | Browser softphone (exposed to frontend) |

After changing `.env` in production:

```bash
php artisan config:cache
```

---

## Telephony & integrations

### VICIdial

- Agent and Non-Agent API calls from Laravel HTTP client.  
- Session controls (login/pause/logout), queue info, and related routes are gated by **telephony feature flags** (admin configuration).  
- **`VICI_VERIFY_SSL`**: set to `false` only when the VICIdial host uses a **self-signed** or private CA certificate **and** you accept the risk on a trusted network; prefer installing the CA on the CRM server.

### Asterisk (VICIdial / chan_sip)

- Outbound origination via **AMI**; agent channel is **SIP** (`SIP/{extension}`), not PJSIP for this flow.  
- AMI events can be forwarded to the CRM webhook; **`php artisan ami:listen`** connects to AMI and POSTs events (run under Supervisor in production).  

**Integration guide:** `docs/asterisk/VICIDIAL_DIRECT_CRM_INTEGRATION_GUIDE.md`

### Browser softphone

- **SIP.js** registers against your Asterisk/WebRTC endpoint; state syncs with backend/Reverb.  

### Predictive dialing

- Campaign settings (`predictive_*`) and **lead hopper** fields (`priority`, `attempt_count`, etc.) support CRM-side predictive workflows.  

---

## Useful Artisan commands

| Command | Purpose |
|---------|---------|
| `php artisan telephony:preflight` | Check AMI env, routes, TCP connectivity to Asterisk |
| `php artisan telephony:smoke-dial --user-id=ID --number=... --campaign=...` | End-to-end dial smoke test |
| `php artisan telephony:enable-predictive {campaign}` | Enable predictive options on a campaign |
| `php artisan ami:listen` | Long-running AMI → webhook listener |
| `php artisan reverb:start` | WebSocket server |
| `php artisan horizon` | Queue dashboard + workers (requires `ext-pcntl` on Linux) |

---

## Frontend assets

- **Development:** `npm run dev` (Vite HMR).  
- **Production:** `npm run build` — outputs to `public/build`.  

Telephony-related JS includes structured logging (`TelephonyLogger`) and Echo subscriptions for agent/supervisor channels.

---

## Testing

```bash
php artisan test
# or
composer test
```

Feature tests live under `tests/Feature/`.

---

## Documentation

| Document | Topic |
|----------|--------|
| `INSTALLATION.md` | Production installation & Supervisor |
| `docs/asterisk/VICIDIAL_DIRECT_CRM_INTEGRATION_GUIDE.md` | Asterisk / AMI / CRM wiring |
| `docs/telephony/` | Telephony build notes and phases (where present) |
| `deploy/supervisor/laravel-ami-listener.conf.example` | AMI listener Supervisor template |
| `docs/audit/` | System audit reports (baseline, findings, UI fixes, telephony decision, tests, security) |
| `docs/audit/ui-soft-nav-rules.md` | Required reading for anyone writing page-level `<script>` blocks |
| `docs/audit/03-telephony.md` | SIP.js vs ViciPhone decision + `TELEPHONY_MEDIA_PATH` feature flag |

---

## Security notes

- Never commit **`.env`** or production secrets.  
- Restrict **Horizon** and **admin** routes in production (HTTPS, IP allowlists if needed).  
- **`VICI_VERIFY_SSL=false`** disables TLS verification for outbound VICIdial HTTP calls — use only when necessary and understood.  
- Rotate default seeded passwords before go-live.  
- Keep `APP_DEBUG=false` in production.

---

## Windows helper

`start-dev.bat` (project root) can start `serve`, `reverb`, `queue:work`, and `ami:listen` from one folder — adjust host/port to match your `.env`.

---

## License

This project is based on Laravel (MIT). Application-specific code follows the same spirit unless your organization defines otherwise.

---

## Laravel framework

The upstream Laravel framework documentation: [https://laravel.com/docs](https://laravel.com/docs).
