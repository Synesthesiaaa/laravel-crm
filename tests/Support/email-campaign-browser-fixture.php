<?php

// Explicitly isolated browser fixtures. This helper never reads or changes real users/mail.
use App\Models\Campaign;
use App\Models\EmailCampaign;
use App\Models\EmailContact;
use App\Models\EmailSegment;
use App\Models\EmailTemplate;
use App\Models\LeadHopper;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function emailCampaignBrowserApplication(): Illuminate\Foundation\Application
{
    $workspace = dirname(__DIR__, 2);
    require_once $workspace.'/vendor/autoload.php';
    $fixtureStorage = $workspace.'/storage/framework/testing/email-campaign-browser';
    $database = $workspace.'/storage/framework/testing/email-campaign-browser.sqlite';
    foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
        if (! is_dir($fixtureStorage.'/'.$directory)) {
            mkdir($fixtureStorage.'/'.$directory, 0700, true);
        }
    }
    $values = [
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://127.0.0.1:8879',
        'APP_KEY' => 'base64:'.base64_encode(hash('sha256', 'isolated-email-campaign-browser-fixture', true)),
        'APP_CONFIG_CACHE' => $fixtureStorage.'/nonexistent-config-cache.php',
        'APP_ROUTES_CACHE' => $fixtureStorage.'/nonexistent-route-cache.php',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database,
        'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'email_campaign_fixture',
        'SESSION_SECURE_COOKIE' => 'false', 'MAIL_MAILER' => 'array', 'MAIL_FROM_ADDRESS' => 'fixture@example.com',
        'MAIL_FROM_NAME' => 'Synthetic email fixture', 'BROADCAST_CONNECTION' => 'null',
        'QUEUE_CONNECTION' => 'email-database', 'EMAIL_CAMPAIGN_CONNECTION' => 'email-database',
        'EMAIL_CAMPAIGN_LIVE_ENABLED' => 'false', 'EMAIL_CAMPAIGN_ALLOW_LOCAL' => 'false',
        'LOG_CHANNEL' => 'single', 'LOG_LEVEL' => 'warning',
    ];
    foreach ($values as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require $workspace.'/bootstrap/app.php';
    $app->useStoragePath($fixtureStorage);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    config([
        'app.env' => 'testing', 'database.default' => 'sqlite', 'database.connections.sqlite.database' => $database,
        'mail.default' => 'array', 'mail.mailers.array' => ['transport' => 'array'],
        'mail.from' => ['address' => 'fixture@example.com', 'name' => 'Synthetic email fixture'],
        'broadcasting.default' => 'null', 'session.driver' => 'file', 'session.cookie' => 'email_campaign_fixture',
        'session.files' => $fixtureStorage.'/framework/sessions', 'cache.default' => 'file',
        'cache.stores.file.path' => $fixtureStorage.'/framework/cache/data', 'queue.default' => 'email-database',
        'email_campaigns.connection' => 'email-database', 'email_campaigns.queue' => 'email-campaigns',
        'email_campaigns.live_enabled' => false, 'email_campaigns.allow_local' => false,
        'vicidial.auto_bootstrap_on_crm_login' => false,
    ]);
    if (! $app->environment('testing') || config('database.connections.sqlite.database') !== $database || config('mail.default') !== 'array') {
        throw new RuntimeException('Unsafe browser fixture configuration.');
    }

    return $app;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $app = emailCampaignBrowserApplication();
    $database = (string) config('database.connections.sqlite.database');
    if (($argv[1] ?? '') === 'seed') {
        if (! is_file($database)) {
            touch($database);
        }
        Artisan::call('migrate', ['--force' => true]);
        Campaign::updateOrCreate(['code' => 'email-ui'], ['name' => 'Synthetic email UI', 'is_active' => true, 'color' => '#e91e8c']);
        foreach (['super' => User::ROLE_SUPER_ADMIN, 'admin' => User::ROLE_ADMIN, 'agent' => User::ROLE_AGENT] as $name => $role) {
            $actor = User::updateOrCreate(['username' => 'email-ui-'.$name], ['name' => 'Email UI '.ucfirst($name), 'full_name' => 'Email UI '.ucfirst($name), 'email' => 'email-ui-'.$name.'@example.com', 'role' => $role, 'password' => 'Email-UI-2026!', 'auto_vici_login' => false]);
            $template = EmailTemplate::updateOrCreate(['name' => 'Synthetic newsletter '.$name, 'created_by_user_id' => $actor->id], ['subject' => 'Synthetic update for {{name}}', 'html_body' => '<table width="600" style="width:100%;max-width:600px;margin:0 auto"><tr><td style="padding:24px"><h1>Your November update</h1><p>Hello {{name}},</p><p>This is an isolated synthetic campaign preview.</p><p><a href="https://example.com/news">Read the update</a></p></td></tr></table>', 'body_format' => 'html', 'pdf_enabled' => false]);
            if ($name !== 'agent') {
                $campaign = EmailCampaign::firstOrCreate(['name' => 'Synthetic draft '.$name, 'created_by_user_id' => $actor->id], ['email_template_id' => $template->id, 'purpose' => 'marketing', 'attachment_mode' => 'none', 'status' => 'draft', 'batch_size' => 2, 'delay_seconds' => 1, 'management_version' => 3, 'audience_status' => 'empty']);
                EmailContact::updateOrCreate(['user_id' => $actor->id, 'email' => 'synthetic-'.$name.'@example.com'], ['name' => 'Synthetic '.ucfirst($name), 'tags' => ['newsletter'], 'source' => 'manual', 'consent_status' => 'asserted', 'consent_source' => 'Isolated synthetic browser test permission', 'consent_asserted_at' => now()->subDay(), 'consent_actor_id' => $actor->id]);
                EmailSegment::updateOrCreate(['user_id' => $actor->id, 'name' => 'Synthetic newsletter audience'], ['filters' => ['tag' => 'newsletter', 'consent_status' => 'asserted']]);
                echo json_encode(['role' => $name, 'user_id' => $actor->id, 'template_id' => $template->id, 'campaign_id' => $campaign->id]).PHP_EOL;
            }
        }
        LeadHopper::updateOrCreate(['campaign_code' => 'email-ui', 'lead_id' => 990001], ['phone_number' => '0000000000', 'client_name' => 'Synthetic CRM Contact', 'custom_data' => ['email' => 'crm-fixture@example.com', 'name' => 'Synthetic CRM Contact'], 'status' => 'pending']);
        foreach (App\Services\TelephonyFeatureService::FEATURE_KEYS as $feature) {
            SystemSetting::updateOrCreate(['setting_key' => 'telephony_feature_'.$feature], ['setting_value' => '0']);
        }
        Cache::put('email-worker:email-database:email-campaigns', now()->toIso8601String(), 3600);
        Cache::put('email-scheduler-heartbeat', now()->toIso8601String(), 3600);
        echo 'Database: '.$database.PHP_EOL.'Mail: array only; environment: testing'.PHP_EOL;
    } elseif (($argv[1] ?? '') === 'work') {
        Artisan::call('queue:work', ['connection' => 'email-database', '--queue' => 'email-preparation,email-campaigns', '--stop-when-empty' => true, '--sleep' => 1, '--tries' => 1, '--timeout' => 300, '--max-time' => 40]);
        echo Artisan::output();
    } elseif (($argv[1] ?? '') === 'files') {
        $directory = storage_path('app/private/browser-fixtures');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/recipients.csv', "email,name\ncsv-browser@example.com,CSV Browser\n");
        $writer = new OpenSpout\Writer\XLSX\Writer;
        $writer->openToFile($directory.'/recipients.xlsx');
        $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['email', 'name']));
        $writer->addRow(OpenSpout\Common\Entity\Row::fromValues(['xlsx-browser@example.com', 'XLSX Browser']));
        $writer->close();
        echo $directory.PHP_EOL;
    } elseif (($argv[1] ?? '') === 'status') {
        echo json_encode(['campaigns' => DB::table('email_campaigns')->select('id', 'name', 'status', 'audience_status', 'recipient_count', 'sent_count', 'failed_count')->get(), 'jobs' => DB::table('jobs')->count()], JSON_PRETTY_PRINT).PHP_EOL;
    } else {
        fwrite(STDERR, "Use seed, work or status. This helper uses only its isolated synthetic SQLite database.\n");
        exit(1);
    }
}
