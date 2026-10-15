# Quick start: isolated development

This path is for an **empty local database**, with no real customer data, SMTP recipients, or PBX connection. Check [requirements](requirements.md) first: the lockfile needs PHP 8.4+ and Node `^20.19.0 || >=22.12.0`.

1. Clone the repository and enter it:

   ```bash
   git clone https://github.com/Synesthesiaaa/laravel-crm.git
   cd laravel-crm
   composer install
   ```

2. Copy `.env.example` only if `.env` does not already exist. Linux: `cp .env.example .env`. PowerShell: `Copy-Item .env.example .env`. Generate a key for this **new installation only**:

   ```bash
   php artisan key:generate
   ```

3. Configure a fresh SQLite database and local services in `.env`:

   ```dotenv
   APP_ENV=local
   APP_URL=http://localhost:8000
   DB_CONNECTION=sqlite
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   BROADCAST_CONNECTION=log
   MAIL_MAILER=log
   VICI_AUTO_BOOTSTRAP=false
   VICI_CALL_HISTORY_SYNC_ENABLED=false
   EMAIL_CAMPAIGN_LIVE_ENABLED=false
   EMAIL_CAMPAIGN_ALLOW_LOCAL=false
   EMAIL_PAYROLL_ENABLED=false
   ```

   Create the empty SQLite file before migrations: Linux `touch database/database.sqlite`; PowerShell `New-Item -ItemType File database/database.sqlite` (only when absent). For MySQL, provision a separate empty database and set `DB_*`; see [installation](installation.md).

4. Create tables and demonstration configuration:

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan db:seed --class=RolesAndPermissionsSeeder
   php artisan storage:link
   npm ci
   npm run build
   php artisan serve
   ```

5. Open `http://localhost:8000/login`. The fresh seeder creates `admin` with factory password `password`, if that username is absent. Replace it immediately through User Access before sharing the environment. This is a development credential, never a production setup method. Follow [first login](first-login.md).

This starts the web application only. Agent Screen access defaults off, telephony is not ready, and email delivery remains disabled. A fresh installation has not been executed as part of this documentation change. Start [only the background processes needed](../operations/queues-and-workers.md) after configuring a safe environment.

`composer run dev` additionally starts the general `telephony,default` listener, Pail, Vite, and Reverb. It omits the `imports`, `asterisk`, and dedicated email consumers. Pail/process behavior may differ on native Windows; use separate terminals there. Do not start the scheduler casually: it can permanently delete records under enabled retention policies.

For an existing installation, use [maintenance](../operations/maintenance.md), not these seed/key-generation steps.
