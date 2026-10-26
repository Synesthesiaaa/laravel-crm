<?php

use App\Services\EmailCampaignRecipientTransitionService;
use Illuminate\Contracts\Console\Kernel;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$campaignId = (int) ($argv[1] ?? 0);
$recipientId = (int) ($argv[2] ?? 0);
$worker = (string) ($argv[3] ?? 'worker');
$action = (string) ($argv[4] ?? 'transition');
$barrier = (string) getenv('EMAIL_TRANSITION_BARRIER');

file_put_contents($barrier.DIRECTORY_SEPARATOR.'ready-'.$worker, 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go') && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) {
    fwrite(STDERR, 'concurrency barrier timed out');
    exit(2);
}

if ($action === 'reconcile') {
    app(EmailCampaignRecipientTransitionService::class)->reconcile($campaignId);
    echo json_encode(['reconciled' => true], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
}

$changed = app(EmailCampaignRecipientTransitionService::class)->transition($campaignId, $recipientId, 'sending', 'accepted', ['sent_at' => now()]);

echo json_encode(['changed' => $changed], JSON_THROW_ON_ERROR).PHP_EOL;
