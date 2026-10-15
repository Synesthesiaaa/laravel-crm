# Installation

Use this guide for a new, isolated checkout. Commands are source-reviewed procedures; a fresh Windows/Linux installation was not executed during the documentation update. Read [requirements](requirements.md) and [quick start](quick-start.md) first.

## 1. Prepare the environment

Install compatible PHP and Composer 2, Node/npm, and the database driver. On Windows, ensure terminal and Apache use the intended PHP build: `php -v`, `php --ini`, `php -m`, `node --version`, and `npm --version` help detect PATH differences. Enable extensions in the loaded `php.ini`, then restart Apache if its PHP configuration changes. Use Linux/WSL for Horizon.

Clone into a development workspace. Serve **only `public/`** from Apache/Nginx; never expose repository root, `.env`, `storage/`, or private attachments. For local work, `php artisan serve` supplies HTTP.

## 2. Install reproducible dependencies

```bash
composer install
composer check-platform-reqs
npm ci
```

Do not resolve the PHP 8.2 declaration mismatch by ignoring platform requirements. The current lockfile needs PHP 8.4+. Windows may report PCNTL/POSIX requirements missing because Composer's manifest platform overrides only simulate them. A dependency install can succeed while Horizon cannot run.

## 3. Configure a new environment

Copy `.env.example` if absent, then generate a new application key. Set `APP_URL` to the URL actually visited. Keep email live flags off and omit real telephony credentials for the first run. The example selects Redis queues, so change to `QUEUE_CONNECTION=database` if Redis is unavailable. Keep database sessions: sign-in queries the `sessions` table directly to replace other sessions, even when another session driver is configured.

SQLite: create `database/database.sqlite` when absent; leave `DB_DATABASE` unset for Laravel's default path or provide an absolute path. MySQL: create a dedicated empty database and restricted application account, then configure `DB_CONNECTION=mysql`, host, port, database, username, password. Do not reuse a production database for development.

## 4. Create schema and seed local data

```bash
php artisan migrate
php artisan db:seed
php artisan db:seed --class=RolesAndPermissionsSeeder
```

`DatabaseSeeder` runs CampaignSeeder, DispositionCodesSeeder, and FormFieldsSeeder. It also creates a factory-backed Super Admin if `admin` is absent. The factory password is `password`. The roles seeder is separate. Role middleware checks `users.role`, so assigning a Spatie permission alone does not grant a blocked route. Change the demonstration account password through User Access before shared access.

Do not run these seeders as routine upgrades: they update named campaigns, forms, and permissions. For production, provision the first operator under your organization's controlled account procedure; no interactive production bootstrap command is supplied by this repository.

## 5. Prepare storage and assets

```bash
php artisan storage:link
npm run build
php artisan serve
```

`storage/` and `bootstrap/cache/` must be writable by the runtime account. A Windows symlink may require Developer Mode or an appropriately privileged terminal. Public branding storage and private email documents serve different purposes: never solve a private document issue by publishing its directory.

For asset development run `npm run dev` separately. Production requires built assets. Configure optional [Reverb](../integrations/laravel-reverb.md) and [workers](../operations/queues-and-workers.md) separately.

## 6. Verify the first run

Check `/up`, open `/login`, sign into the intended campaign, confirm displayed role/campaign, open a seeded form, and confirm the dashboard loads. Use synthetic information only. Agent Screen is disabled by default; see [first campaign](first-campaign.md). Web-only checks do not prove calling, queues, or SMTP operate.

Linux production also requires [deployment](../operations/deployment.md), [production configuration](../operations/production-configuration.md), and [security](../operations/security.md). See [troubleshooting](../operations/troubleshooting.md) for failures.
