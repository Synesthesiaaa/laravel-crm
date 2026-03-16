Production Installation Guide -- Laravel CRM

Last updated: 2026-02-27



1. Server Requirements

Hardware (Minimum for 100k+ users)





CPU: 4 cores (8 recommended)



RAM: 8 GB (16 GB recommended)



Disk: 50 GB SSD (NVMe preferred)



Network: 100 Mbps+

Software Stack





OS: Ubuntu 22.04/24.04 LTS (or RHEL 9 / Debian 12)



PHP: 8.2 or 8.3



Web Server: Nginx (recommended) or Apache



Database: MySQL 8.0+ or MariaDB 10.6+



Cache/Queue: Redis 7+



Node.js: 18+ (build-time only, not needed at runtime)



Supervisor: For Horizon, AMI listener, and Reverb



Composer: v2

Required PHP Extensions

mbstring, bcmath, pdo, pdo_mysql, redis (phpredis), zip, gd, openssl, tokenizer, xml, ctype, json, fileinfo, curl



2. Server Preparation

2a. Install PHP and Extensions (Ubuntu)

sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-cli \
  php8.2-mbstring php8.2-bcmath php8.2-pdo php8.2-mysql \
  php8.2-redis php8.2-zip php8.2-gd php8.2-xml php8.2-curl \
  php8.2-tokenizer php8.2-fileinfo php8.2-common

2b. Install Nginx

sudo apt install -y nginx

2c. Install MySQL 8

sudo apt install -y mysql-server
sudo mysql_secure_installation

Create the database and user:

CREATE DATABASE `laravel_crm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_user'@'localhost' IDENTIFIED BY '<STRONG_PASSWORD>';
GRANT ALL PRIVILEGES ON `laravel_crm`.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;

2d. Install Redis

sudo apt install -y redis-server
sudo systemctl enable redis-server

Optionally set a password in /etc/redis/redis.conf:

requirepass <REDIS_PASSWORD>

2e. Install Node.js (build-time)

curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

2f. Install Composer

curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

2g. Install Supervisor

sudo apt install -y supervisor
sudo systemctl enable supervisor



3. Application Deployment

3a. Create Application Directory and User

sudo useradd -m -s /bin/bash crm
sudo mkdir -p /var/www/laravel-crm
sudo chown crm:www-data /var/www/laravel-crm

3b. Deploy Code

Copy the project to /var/www/laravel-crm (via Git, rsync, or CI/CD artifact):

sudo -u crm rsync -avz --exclude=node_modules --exclude=vendor \
  /path/to/source/ /var/www/laravel-crm/

3c. Install PHP Dependencies

cd /var/www/laravel-crm
sudo -u crm composer install --no-dev --optimize-autoloader --no-interaction

3d. Install Node Dependencies and Build Assets

sudo -u crm npm ci
sudo -u crm npm run build

After build completes, node_modules can optionally be removed from the production server to save disk space.

3e. Set Directory Permissions

sudo chown -R crm:www-data /var/www/laravel-crm
sudo find /var/www/laravel-crm -type f -exec chmod 644 {} \;
sudo find /var/www/laravel-crm -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/laravel-crm/storage
sudo chmod -R 775 /var/www/laravel-crm/bootstrap/cache



4. Environment Configuration

Copy the example environment file and configure it:

cd /var/www/laravel-crm
sudo -u crm cp .env.example .env
sudo -u crm php artisan key:generate

Edit .env with production values. The following is the complete list of variables that must be reviewed:

Core Application

APP_NAME="Laravel CRM"
APP_ENV=production
APP_KEY=              # Generated above
APP_DEBUG=false
APP_URL=https://crm.yourdomain.com
APP_MAINTENANCE_DRIVER=file
BCRYPT_ROUNDS=12

Database

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_crm
DB_USERNAME=crm_user
DB_PASSWORD=<STRONG_PASSWORD>
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

Redis (Cache + Queue + Session)

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<REDIS_PASSWORD_OR_null>
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1

Cache, Queue, and Session (all use Redis in production)

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

Mail

MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=<SMTP_USER>
MAIL_PASSWORD=<SMTP_PASSWORD>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourdomain.com"
MAIL_FROM_NAME="${APP_NAME}"

Logging

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null

Horizon

HORIZON_PATH=horizon
HORIZON_PREFIX=laravel_crm_horizon:

VICIdial Integration (optional, skip if not using telephony)

VICI_DB_HOST=<VICIDIAL_DB_HOST>
VICI_DB_PORT=3306
VICI_DB_NAME=asterisk
VICI_DB_USERNAME=<VICI_USER>
VICI_DB_PASSWORD=<VICI_PASSWORD>
VICI_API_URL=http://<VICIDIAL_HOST>/agc/api.php
VICI_SOURCE=crm_tracker

Asterisk AMI (optional, skip if not using telephony)

ASTERISK_AMI_HOST=<AMI_HOST>
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=cron
ASTERISK_AMI_SECRET=<AMI_SECRET>
ASTERISK_AMI_TIMEOUT=5
ASTERISK_AMI_READ_TIMEOUT=5000

File Storage (optional, for S3)

FILESYSTEM_DISK=local
# If using S3:
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=
# AWS_SECRET_ACCESS_KEY=
# AWS_DEFAULT_REGION=
# AWS_BUCKET=



5. Database Setup

5a. Run Migrations

cd /var/www/laravel-crm
sudo -u crm php artisan migrate --force

This creates all 25 migration tables including: users, campaigns, forms, form_fields, disposition_codes, agent_call_records, campaign_disposition_records, crm_call_history, attendance_logs, system_settings, vicidial_servers, agent_screen_fields, form_data tables, PJLI form tables, plus Spatie permission/activity tables, Sanctum tokens, jobs/failed_jobs, cache, and sessions.

5b. Seed Initial Data

sudo -u crm php artisan db:seed --force

This runs the following seeders (defined in database/seeders/DatabaseSeeder.php):





CampaignSeeder -- creates MBSales and PJLI campaigns with 7 forms



DispositionCodesSeeder -- creates 9 default disposition codes (SALE, CBH, CBW, CBC, DNC, NAN, NA, BUSY, OTHER)



FormFieldsSeeder -- creates EzyCash form fields (13 fields)



Creates a default admin user (username: admin, email: admin@example.com)

5c. Seed Roles and Permissions

The RolesAndPermissionsSeeder is NOT called by DatabaseSeeder by default. Run it manually:

sudo -u crm php artisan db:seed --class=RolesAndPermissionsSeeder --force

This creates 4 roles (Super Admin, Admin, Team Leader, Agent) and 10 permissions. Assign the Super Admin role to the admin user after seeding:

sudo -u crm php artisan tinker --execute="App\Models\User::where('username','admin')->first()->assignRole('Super Admin');"

Change the default admin password immediately after first login.

5d. Create Storage Symlink

sudo -u crm php artisan storage:link



6. Optimization and Cache Commands

Run these after every deployment:

cd /var/www/laravel-crm
sudo -u crm php artisan optimize:clear
sudo -u crm php artisan config:cache
sudo -u crm php artisan route:cache
sudo -u crm php artisan view:cache
sudo -u crm php artisan event:cache
sudo -u crm php artisan horizon:publish

Publish Horizon assets:

sudo -u crm php artisan horizon:terminate

Supervisor will restart Horizon automatically.


7. Nginx Configuration

Create /etc/nginx/sites-available/laravel-crm:

server {
    listen 80;
    server_name crm.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name crm.yourdomain.com;
    root /var/www/laravel-crm/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/crm.yourdomain.com.crt;
    ssl_certificate_key /etc/ssl/private/crm.yourdomain.com.key;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header X-XSS-Protection "1; mode=block" always;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

Enable and test:

sudo ln -s /etc/nginx/sites-available/laravel-crm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

For SSL, use Let's Encrypt:

sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d crm.yourdomain.com



8. PHP-FPM Tuning

Edit /etc/php/8.3/fpm/pool.d/www.conf:

[www]
user = crm
group = www-data
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 25M
php_admin_value[max_execution_time] = 60
php_admin_value[expose_php] = Off
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.max_accelerated_files] = 20000
php_admin_value[opcache.validate_timestamps] = 0

Important: opcache.validate_timestamps=0 means PHP will not check for file changes. After every deployment, restart PHP-FPM:

sudo systemctl restart php8.2-fpm



9. Supervisor Configuration (Horizon + AMI Listener + Reverb)

9a. Horizon (Primary Queue Worker)

Create /etc/supervisor/conf.d/horizon.conf:

[program:horizon]
process_name=%(program_name)s
command=php /var/www/laravel-crm/artisan horizon
directory=/var/www/laravel-crm
autostart=true
autorestart=true
user=crm
redirect_stderr=true
stdout_logfile=/var/www/laravel-crm/storage/logs/horizon.log
stopwaitsecs=3600

9b. AMI Listener (Telephony Events)

Create /etc/supervisor/conf.d/laravel-ami-listener.conf:

[program:laravel-ami-listener]
process_name=%(program_name)s
directory=/var/www/laravel-crm
command=php artisan ami:listen --reconnect-delay=5
autostart=true
autorestart=true
startsecs=3
startretries=10
user=crm
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/laravel-crm/storage/logs/ami-listener.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10
stopwaitsecs=15

9c. Reverb WebSocket Server

Create /etc/supervisor/conf.d/reverb.conf:

[program:reverb]
process_name=%(program_name)s
directory=/var/www/laravel-crm
command=php artisan reverb:start --host=0.0.0.0 --port=6001
autostart=true
autorestart=true
startsecs=3
startretries=10
user=crm
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/laravel-crm/storage/logs/reverb.log
stdout_logfile_maxbytes=20MB
stdout_logfile_backups=10
stopwaitsecs=15

9d. Reload Supervisor

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
sudo supervisorctl start laravel-ami-listener
sudo supervisorctl start reverb

Verify status:

sudo supervisorctl status horizon
sudo supervisorctl status laravel-ami-listener
sudo supervisorctl status reverb

After each deployment, gracefully restart managed processes:

sudo -u crm php artisan horizon:terminate
sudo supervisorctl restart laravel-ami-listener
sudo supervisorctl restart reverb

Supervisor keeps all programs running after restarts/crashes.



10. Cron Job (Task Scheduler)

The application has 5 scheduled tasks defined in routes/console.php:





activitylog:clean --days=90 -- daily at 01:00



queue:prune-failed --hours=168 -- daily at 02:00



cache:prune-stale-tags -- hourly



Dashboard cache invalidation -- hourly



horizon:snapshot -- every 5 minutes

Add this single cron entry for the crm user:

sudo crontab -u crm -e

Add:

* * * * * cd /var/www/laravel-crm && php artisan schedule:run >> /dev/null 2>&1



11. Security Hardening

11a. Environment File Protection





.env is already excluded from web access by the Nginx config (deny dotfiles)



Ensure file permissions: chmod 600 .env

11b. Rate Limiting (already configured)

The app defines rate limiters in bootstrap/app.php:





Login: 5/minute per IP



API: 60/minute per user



VICIdial proxy: 30/minute per user



Form submit: 10/minute per user



CSV import: 2/hour per user

11c. Horizon Dashboard Access

Restricted to Super Admin only via the gate in app/Providers/HorizonServiceProvider.php. Optionally restrict by IP in Nginx:

location /horizon {
    allow 10.0.0.0/8;
    deny all;
    try_files $uri $uri/ /index.php?$query_string;
}

11d. Firewall (UFW)

sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

If Redis or MySQL are on separate hosts, open those ports selectively. If local-only, ensure they bind to 127.0.0.1.

11e. MySQL Hardening





Bind to 127.0.0.1 in /etc/mysql/mysql.conf.d/mysqld.cnf



Use mysql_secure_installation (already done in Step 2c)



Use dedicated user with minimal privileges

11f. Redis Security





Bind to 127.0.0.1 in /etc/redis/redis.conf



Set requirepass



Disable dangerous commands:

rename-command FLUSHALL ""
rename-command FLUSHDB ""
rename-command DEBUG ""



12. VICIdial / Asterisk Telephony Integration (Optional)

If you are using VICIdial telephony, you also need:





A VICIdial server with API access enabled



A secondary MySQL connection configured via VICI_DB_* env vars -- the app connects directly to the VICIdial asterisk database (defined as the vicidial connection in config/database.php)



Asterisk AMI credentials if using click-to-call / call origination features



Network connectivity between the CRM server and VICIdial/Asterisk hosts on ports 3306 (MySQL) and 5038 (AMI)

SIP-only policy for agents (chan_sip):

ASTERISK_AGENT_CHANNEL=SIP

Validate telephony before go-live:

php artisan telephony:preflight

Smoke-test dial path:

php artisan telephony:smoke-dial --user-id=AGENT_ID --number=DESTINATION --campaign=mbsales

If preflight fails or calls do not bridge, follow:
docs/asterisk/VICIDIAL_DIRECT_CRM_INTEGRATION_GUIDE.md



13. Deployment Workflow (Subsequent Deploys)

Create a deployment script /var/www/deploy.sh:

#!/bin/bash
set -e

cd /var/www/laravel-crm

php artisan down

git pull origin main   # or rsync/artifact copy

composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan horizon:publish
php artisan horizon:terminate

sudo systemctl restart php8.2-fpm
sudo supervisorctl restart laravel-ami-listener
sudo supervisorctl restart reverb

php artisan up



14. Monitoring and Logging

Log Files





Application: storage/logs/laravel.log (daily rotation via LOG_STACK=daily)



Audit: storage/logs/audit-*.log (90-day retention)



Security: storage/logs/security-*.log (365-day retention)



Telephony: storage/logs/telephony-*.log (30-day retention, defined in config/logging.php)



Scheduler: storage/logs/scheduler.log



Horizon: storage/logs/horizon.log

AMI listener: storage/logs/ami-listener.log

Reverb: storage/logs/reverb.log

Health Check

The app exposes a /up health endpoint (configured in bootstrap/app.php) for load balancer or uptime monitoring.

Horizon Dashboard

Access at https://crm.yourdomain.com/horizon (Super Admin only). Monitors queue throughput, failed jobs, and worker status.



15. Backup Strategy

Database

mysqldump -u crm_user -p laravel_crm | gzip > /backups/db-$(date +%F).sql.gz

Schedule daily via cron.

Application Files

Back up storage/app/ (uploaded files) and .env. The rest can be reconstructed from source control.

Redis

If using Redis persistence (AOF/RDB), back up /var/lib/redis/dump.rdb. Otherwise, Redis data is ephemeral (cache, queues, sessions).



16. Quick Start Summary

1.  Provision server (PHP 8.3, Nginx, MySQL 8, Redis 7, Supervisor)
2.  Deploy code to /var/www/laravel-crm
3.  composer install --no-dev --optimize-autoloader
4.  npm ci && npm run build
5.  cp .env.example .env && edit .env with production values
6.  php artisan key:generate
7.  php artisan migrate --force
8.  php artisan db:seed --force
9.  php artisan db:seed --class=RolesAndPermissionsSeeder --force
10. php artisan storage:link
11. php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
12. php artisan horizon:publish
13. Configure Nginx virtual host with SSL
14. Configure Supervisor for Horizon, AMI listener, and Reverb
15. Add cron entry for scheduler
16. Start services: nginx, php-fpm, redis, supervisor
17. Log in as admin/admin@example.com, change password, assign Super Admin role

