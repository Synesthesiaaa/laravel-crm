<?php

use App\Models\User;
use App\Services\Telephony\CallOrchestrationService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
$campaign = (string) ($argv[2] ?? '');
$worker = (string) ($argv[3] ?? 'worker');
$barrier = (string) getenv('CALL_RESERVATION_BARRIER');

file_put_contents($barrier.DIRECTORY_SEPARATOR.'ready-'.$worker, 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go') && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) {
    fwrite(STDERR, 'concurrency barrier timed out');
    exit(2);
}

config(['vicidial.require_vicidial_agent_session_before_dial' => false]);
Http::preventStrayRequests();
Http::fake(['*' => Http::response('SUCCESS', 200)]);
Queue::fake();

$user = User::query()->findOrFail($userId);
$result = app(CallOrchestrationService::class)->startOutboundCall(
    $user,
    $campaign,
    '15550000000',
);

echo json_encode([
    'success' => $result->success,
    'session_id' => $result->data['session_id'] ?? null,
    'http_requests' => Http::recorded()->count(),
], JSON_THROW_ON_ERROR).PHP_EOL;
