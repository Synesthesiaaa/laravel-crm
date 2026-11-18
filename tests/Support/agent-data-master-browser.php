<?php

// Isolated fixture: php tests/Support/agent-data-master-browser.php seed
// Serve: php -S 127.0.0.1:8881 -t public tests/Support/agent-data-master-browser.php
if (! in_array(PHP_SAPI, ['cli', 'cli-server'], true)
    || (PHP_SAPI === 'cli-server' && ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true))) {
    http_response_code(403);
    exit;
}
$workspace = dirname(__DIR__, 2);
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $publicFile = realpath($workspace.'/public'.$path);
    if ($path !== '/' && $publicFile && str_starts_with($publicFile, realpath($workspace.'/public').DIRECTORY_SEPARATOR) && is_file($publicFile)) {
        return false;
    }
}
require_once $workspace.'/vendor/autoload.php';
$storage = $workspace.'/storage/framework/testing/agent-data-master-browser';
$database = $storage.'/fixture.sqlite';
foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    if (! is_dir($storage.'/'.$directory)) {
        mkdir($storage.'/'.$directory, 0700, true);
    }
}
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8881',
    'APP_KEY' => 'base64:'.base64_encode(hash('sha256', 'agent-data-master-browser-synthetic', true)),
    'APP_CONFIG_CACHE' => $storage.'/no-config.php', 'APP_ROUTES_CACHE' => $storage.'/no-routes.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'file',
    'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'agent_dm_fixture', 'SESSION_SECURE_COOKIE' => 'false',
    'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'single',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$app = require $workspace.'/bootstrap/app.php';
$app->useStoragePath($storage);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['vicidial.auto_bootstrap_on_crm_login' => false, 'email_campaigns.live_enabled' => false, 'broadcasting.default' => 'null']);
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Unsafe browser fixture configuration.');
}
if (PHP_SAPI === 'cli-server') {
    $app->handleRequest(Illuminate\Http\Request::capture());

    return;
}
if (in_array($argv[1] ?? '', ['storage-unavailable', 'storage-restore'], true)) {
    App\Models\Form::where('campaign_code', 'dm_alpha')->where('form_code', 'sales')->update([
        'table_name' => $argv[1] === 'storage-unavailable' ? 'dm_fixture_missing' : 'dm_fixture_sales',
    ]);
    Illuminate\Support\Facades\Cache::flush();
    exit("Synthetic storage mapping updated.\n");
}
if (($argv[1] ?? '') !== 'seed') {
    exit("Use seed, or run this script as the loopback PHP server router.\n");
}
if (! is_file($database)) {
    touch($database);
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$campaign = App\Models\Campaign::updateOrCreate(['code' => 'dm_alpha'], ['name' => 'Synthetic Alpha Campaign', 'is_active' => true]);
App\Models\Campaign::updateOrCreate(['code' => 'dm_beta'], ['name' => 'Synthetic Beta Campaign', 'is_active' => true]);
$actors = [];
foreach (['super' => App\Models\User::ROLE_SUPER_ADMIN, 'agent' => App\Models\User::ROLE_AGENT, 'other' => App\Models\User::ROLE_AGENT] as $name => $role) {
    $actors[$name] = App\Models\User::updateOrCreate(['username' => 'dm-'.$name], ['name' => 'Synthetic '.ucfirst($name), 'full_name' => 'Synthetic '.ucfirst($name), 'role' => $role, 'email' => 'dm-'.$name.'@example.com', 'password' => 'Synthetic-DM-2026!', 'auto_vici_login' => false]);
}
$forms = [];
foreach (['sales' => 'Sales submissions', 'feedback' => 'Customer feedback'] as $code => $name) {
    $tableName = 'dm_fixture_'.$code;
    $forms[] = $form = App\Models\Form::updateOrCreate(['campaign_code' => 'dm_alpha', 'form_code' => $code], ['name' => $name, 'table_name' => $tableName, 'is_active' => true]);
    App\Models\FormField::updateOrCreate(['campaign_code' => 'dm_alpha', 'form_type' => $code, 'field_name' => 'client'], ['field_label' => 'Client', 'field_type' => 'text', 'field_order' => 1]);
    App\Models\FormField::updateOrCreate(['campaign_code' => 'dm_alpha', 'form_type' => $code, 'field_name' => 'result'], ['field_label' => 'Result', 'field_type' => 'text', 'field_order' => 2]);
    if (! Illuminate\Support\Facades\Schema::hasTable($tableName)) {
        Illuminate\Support\Facades\Schema::create($tableName, function ($table) {
            $table->id();
            $table->date('date');
            $table->string('client');
            $table->string('result');
            $table->string('agent');
            $table->string('private_legacy')->nullable();
            $table->timestamps();
        });
    }
    for ($i = 1; $i <= ($code === 'sales' ? 27 : 2); $i++) {
        $actor = $i > 24 ? $actors['other'] : $actors['agent'];
        $row = ['date' => '2026-11-01', 'client' => 'Synthetic '.($i > 24 ? 'Other' : ucfirst($code)).' Client '.$i, 'result' => $i % 2 ? 'Pending' : 'Confirmed', 'agent' => $actor->full_name, 'private_legacy' => 'Hidden legacy detail', 'created_at' => '2026-11-'.($i === 24 ? '11' : '12').' 12:00:00', 'updated_at' => '2026-11-12 12:00:00'];
        Illuminate\Support\Facades\DB::table($tableName)->updateOrInsert(['id' => $i], $row);
        App\Models\CrmCallHistory::updateOrCreate(['campaign_code' => 'dm_alpha', 'form_type' => $code, 'record_id' => $i], ['agent' => $actor->full_name, 'user_id' => $actor->id, 'status' => 'RECORDED']);
    }
}
app(App\Services\AgentDataMasterAccessService::class)->update('dm_alpha', ['enabled' => true, 'own_submissions_only' => true, 'form_ids' => array_map(fn ($form) => $form->id, $forms)]);
foreach (App\Services\TelephonyFeatureService::FEATURE_KEYS as $feature) {
    App\Models\SystemSetting::updateOrCreate(['setting_key' => 'telephony_feature_'.$feature], ['setting_value' => '0']);
}
Illuminate\Support\Facades\Cache::flush();
echo "Synthetic fixtures ready: dm-super / dm-agent / dm-other, password Synthetic-DM-2026!\nSQLite: ".$database."\n";
