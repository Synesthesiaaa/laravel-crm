<?php

use App\Models\EmailCampaign;
use App\Models\EmailProviderEvent;
use App\Models\User;
use App\Services\Email\CampaignLifecycle;
use App\Services\Email\ManagedEmailDelivery;
use App\Services\Email\PostmarkEventService;
use App\Services\Email\ProviderRateLimiter;
use App\Services\EmailCampaignRecipientTransitionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

if (getenv('EMAIL_LIFECYCLE_MYSQL_TEST') !== '1' || getenv('DB_CONNECTION') !== 'mysql'
    || ! in_array(getenv('DB_DATABASE'), ['laravel_crm_test', 'laravel_crm_email_campaign_test'], true)) {
    fwrite(STDERR, 'Refusing lifecycle concurrency outside explicitly opted-in test database.');
    exit(3);
}
$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! in_array(config('database.connections.mysql.database'), ['laravel_crm_test', 'laravel_crm_email_campaign_test'], true)) {
    exit(3);
}
Carbon::setTestNow(Carbon::parse(getenv('EMAIL_LIFECYCLE_NOW')));
config(['mail.default' => 'array', 'mail.from.address' => 'synthetic@example.test']);
Bus::fake();
[$script, $campaignId, $recipientId, $actorId, $worker, $action] = $argv;
$directory = (string) getenv('EMAIL_LIFECYCLE_BARRIER');
if (! preg_match('/^(one|two)$/D', $worker) || ! is_dir($directory)) {
    exit(3);
}
file_put_contents($directory.'/ready-'.$worker, 'ready');
$deadline = microtime(true) + 25;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, 'Lifecycle concurrency barrier expired.');
        exit(2);
    }
    usleep(10000);
}
$campaign = EmailCampaign::findOrFail((int) $campaignId);
$result = match ($action) {
    'claim' => ['claimed' => app(EmailCampaignRecipientTransitionService::class)->claim((int) $campaignId, (int) $recipientId, 1) !== null],
    'limit' => ['delay' => app(ProviderRateLimiter::class)->reserve(['provider' => 'smtp', 'transport' => ['transport' => 'array', 'username' => 'lifecycle-'.$campaignId], 'rate_per_minute' => 1])],
    default => null,
};
if ($action === 'due') {
    app(CampaignLifecycle::class)->due((int) $campaignId);
    $result = ['inspected' => true];
} elseif ($action === 'pause') {
    app(CampaignLifecycle::class)->pause(User::findOrFail((int) $actorId), $campaign);
    $result = ['paused' => true];
} elseif ($action === 'feedback') {
    app(PostmarkEventService::class)->process(EmailProviderEvent::where('email_campaign_id', $campaignId)->firstOrFail()->id);
    $result = ['processed' => true];
} elseif ($action === 'interrupt') {
    app(ManagedEmailDelivery::class)->interrupted((int) $campaignId, (int) $recipientId);
    $result = ['interrupted' => true];
}
if ($result === null) {
    exit(3);
}
echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
