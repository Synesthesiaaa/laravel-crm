# Deployment

This procedure targets Linux with a PHP web runtime, process manager, and cron. Paths and service names are examples. No deployment was executed during this documentation review. Native Windows development has separate [installation guidance](../getting-started/installation.md).

## Prepare a release

1. Review [requirements](../getting-started/requirements.md) and [production configuration](production-configuration.md). Provision PHP 8.4+ and compatible Node; verify required extensions, including actual PCNTL/POSIX for Horizon.
2. Select a reviewed commit/release. Build in a separate release directory, retain the previous release and immutable lockfiles, and record the commit and build tool versions.
3. Supply the intended **public build-time `VITE_*` values before building assets**, using a protected build environment without embedding server-only secrets. Install reproducible backend and frontend dependencies in the build/release environment:

   ```bash
   composer install --no-dev --optimize-autoloader --no-interaction
   composer check-platform-reqs --no-dev
   npm ci
   npm run build
   ```

   **Telephony patch gate:** a fresh `composer install --no-dev` omits the development-only `cweagans/composer-patches` plugin even though this project declares a PAMI PHP 8 compatibility patch. The inspected installed PAMI file still contains the old reversed `implode` call; patch application is not verified. Before activating telephony, use an explicitly reviewed build/patch procedure, inspect the resulting vendor artifact against [the patch](../../patches/marcelog-pami-php8-implode-fix.patch), and rehearse synthetic AMI event parsing in staging. Do not assume a successful no-dev install applied it. This is a source/dependency finding, not repaired by documentation.

   Keep npm development dependencies during build because Vite is one of them. Build outside the live request directory. Never run `composer update` or replace the live `.env` as a deployment shortcut.
4. Attach the protected production environment and persistent storage. Use the same key/cache configuration for all runtime processes. Validate file ownership for `storage/` and `bootstrap/cache/`; set restrictive environment-file permissions. Avoid world-writable paths.
5. Back up the database, private files, and encryption material together. Review pending migrations, their data changes, column removal, rollback limits, and compatibility with the old application. Several existing migrations modify registered form storage; migration execution is not purely additive.

## Web server

Point Apache DocumentRoot or Nginx `root` to `/var/www/laravel-crm/public`. Use Laravel's front controller for non-file routes and configure the server's trusted TLS certificate. An illustrative Nginx location is:

```nginx
root /var/www/laravel-crm/public;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
location ~ /\.(?!well-known).* {
    deny all;
}
```

This is a routing fragment, not a complete virtual host: configure the chosen PHP-FPM socket, fastcgi parameters, TLS, upload limits, access logs, and WebSocket proxy separately. Apache needs rewrite support and the repository's [public/.htaccess](../../public/.htaccess). Restrict operational/health surfaces appropriately; see [security](security.md).

The PHP runtime must write storage/cache under a consistent service account. Log rotation can recreate files under another owner, so verify the next daily telephony log too. Uploaded-document limits at proxy and PHP layers must support the application limits without enabling unlimited uploads.

## Database and activation

On an approved upgrade, inspect `php artisan migrate:status` and the migration source, then run `php artisan migrate --force` only after a verified backup and staging rehearsal. It writes schema and may change/delete data. **Never use `migrate:fresh`, `migrate:refresh`, or development seeders against an established production database.**

For a new production installation, use controlled first-account provisioning rather than publishing the factory default account. See [installation](../getting-started/installation.md). Do not generate a replacement key when attaching existing data.

Prepare cached configuration, routes, and views in the release with the intended environment:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

These are deployment instructions, not commands executed for this documentation task. Confirm they succeed in staging before promotion. Switch the web release according to the organization's maintenance/atomic deployment procedure. Reload PHP-FPM when required by OPcache policy. Reload Horizon/plain workers, AMI, Reverb, and the email consumer under their process manager; ensure old and new releases do not consume conflicting jobs simultaneously.

## Long-running services

Use [queues and workers](queues-and-workers.md) for exact lane/connection choices. Existing [Supervisor examples](../../deploy/supervisor) need review: the AMI example supplies an unsupported option in the current runtime. Do not install it unchanged. Run one dedicated email consumer. Configure one cron scheduler only after reviewing retention and pruning tasks.

## Verify and recover

Check `/up`, the login flow, role boundaries, campaign labels, forms, private file access, assets, worker health, and synthetic end-to-end flows. Confirm reports distinguish missing/stale sources from zeros. Record delivery/calling tests separately with controlled recipients/numbers; a web health response is insufficient.

If activation fails, stop new work, preserve failed-job and submission evidence, return web and consumers to the compatible previous release, and assess database compatibility before restoration. Do not automatically run `migrate:rollback`: reverse migrations can destroy new records or cannot reconstruct removed fields. See [backup and recovery](backup-and-recovery.md).
