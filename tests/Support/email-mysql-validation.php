<?php

// Local, isolated MySQL validation only. Never migrates or drops the configured application database.
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__, 2);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.connections.mysql');
$target = 'laravel_crm_email_campaign_test';
if (! in_array($connection['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
    || ($connection['database'] ?? '') === $target || ($connection['url'] ?? null)) {
    fwrite(STDERR, "Refusing validation: requires a local MySQL connection and a separate application database.\n");
    exit(3);
}

try {
    $pdo = new PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';charset=utf8mb4', $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `laravel_crm_email_campaign_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (Throwable) {
    fwrite(STDERR, "Local isolated MySQL database is unavailable or cannot be created. Application database was not changed.\n");
    exit(2);
}
$env = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'APP_CONFIG_CACHE' => $base.'/storage/framework/testing/email-mysql-config-unused.php',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $target,
    'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'DB_URL' => false,
    'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'array', 'BROADCAST_CONNECTION' => 'null', 'SESSION_DRIVER' => 'array',
    'EMAIL_LIFECYCLE_MYSQL_TEST' => '1', 'QUEUE_CONNECTION' => 'sync'];
foreach ([['migrate', '--force', '--no-interaction'], ['test', '--no-coverage', 'tests/Integration/EmailCampaignLifecycleMySqlConcurrencyTest.php']] as $arguments) {
    $process = new Process([PHP_BINARY, 'artisan', ...$arguments], $base, $env);
    $process->setTimeout(180);
    $process->run(fn ($type, $buffer) => print ($buffer));
    if (! $process->isSuccessful()) {
        exit($process->getExitCode() ?: 1);
    }
}
