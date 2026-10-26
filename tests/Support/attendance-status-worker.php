<?php

use App\Models\User;
use App\Services\AttendanceStatusService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

$basePath = dirname(__DIR__, 2);
require $basePath.'/vendor/autoload.php';
$app = require $basePath.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
$code = (string) ($argv[2] ?? '');
$action = (string) ($argv[3] ?? 'start');
$worker = (string) ($argv[4] ?? 'worker');
$barrier = (string) getenv('ATTENDANCE_BARRIER');

file_put_contents($barrier.DIRECTORY_SEPARATOR.'ready-'.$worker, 'ready');
$deadline = microtime(true) + 20;
while (! file_exists($barrier.DIRECTORY_SEPARATOR.'go') && microtime(true) < $deadline) {
    usleep(10000);
}

if (! file_exists($barrier.DIRECTORY_SEPARATOR.'go')) {
    fwrite(STDERR, 'concurrency barrier timed out');
    exit(2);
}

$user = User::query()->findOrFail($userId);

try {
    $result = $action === 'end'
        ? app(AttendanceStatusService::class)->endStatus($user, '127.0.0.1')
        : app(AttendanceStatusService::class)->startStatus($user, $code, '127.0.0.1');

    echo json_encode(['success' => true, 'event_id' => $result->id], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (ValidationException) {
    echo json_encode(['success' => false, 'validation_rejected' => true], JSON_THROW_ON_ERROR).PHP_EOL;
}
