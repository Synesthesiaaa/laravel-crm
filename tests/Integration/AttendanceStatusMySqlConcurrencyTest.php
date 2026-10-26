<?php

namespace Tests\Integration;

use App\Models\AttendanceLog;
use App\Models\AttendanceStatusType;
use App\Models\User;
use App\Services\AttendanceStatusService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AttendanceStatusMySqlConcurrencyTest extends TestCase
{
    public function test_concurrent_attendance_starts_allow_only_one_open_segment(): void
    {
        $this->requireDedicatedMySqlCi();
        [$user, $status] = $this->makeScenario();
        $this->installInsertDelayTrigger();

        try {
            $results = $this->runRace($user, $status->code, 'start');
        } finally {
            $this->dropInsertDelayTrigger();
        }

        $this->assertSingleAcceptedRequest($results);
        $this->assertSame(1, AttendanceLog::query()->where('user_id', $user->id)->count());
        $this->assertSame(AttendanceLog::DIRECTION_START, app(AttendanceStatusService::class)->getOpenStatus($user)?->direction);
    }

    public function test_concurrent_attendance_ends_close_an_open_segment_only_once(): void
    {
        $this->requireDedicatedMySqlCi();
        [$user, $status] = $this->makeScenario();
        app(AttendanceStatusService::class)->startStatus($user, $status->code, '127.0.0.1');
        $this->installInsertDelayTrigger();

        try {
            $results = $this->runRace($user, $status->code, 'end');
        } finally {
            $this->dropInsertDelayTrigger();
        }

        $this->assertSingleAcceptedRequest($results);
        $this->assertSame(2, AttendanceLog::query()->where('user_id', $user->id)->count());
        $this->assertNull(app(AttendanceStatusService::class)->getOpenStatus($user));
    }

    /** @return array{0: User, 1: AttendanceStatusType} */
    private function makeScenario(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::factory()->create(['role' => User::ROLE_AGENT]);
        $status = AttendanceStatusType::query()->create([
            'code' => 'ci_'.$suffix,
            'label' => 'CI Synthetic Break',
            'sort_order' => 90,
            'is_active' => true,
        ]);

        return [$user, $status];
    }

    private function requireDedicatedMySqlCi(): void
    {
        if (getenv('GITHUB_ACTIONS') !== 'true') {
            $this->markTestSkipped('Runs only against the dedicated ephemeral MySQL service in CI.');
        }

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('laravel_crm_test', config('database.connections.mysql.database'));
    }

    private function installInsertDelayTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS phase4_attendance_insert_delay');
        DB::unprepared('CREATE TRIGGER phase4_attendance_insert_delay BEFORE INSERT ON attendance_logs FOR EACH ROW SET @phase4_delay = SLEEP(1)');
    }

    private function dropInsertDelayTrigger(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS phase4_attendance_insert_delay');
    }

    /** @return array<string, array{success: bool, validation_rejected?: bool}> */
    private function runRace(User $user, string $code, string $action): array
    {
        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-attendance-race-'.bin2hex(random_bytes(8));
        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            $this->fail('Unable to create the isolated attendance concurrency barrier directory.');
        }

        $processes = [];

        try {
            foreach (['first', 'second'] as $worker) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/attendance-status-worker.php'),
                    (string) $user->id,
                    $code,
                    $action,
                    $worker,
                ], base_path(), ['ATTENDANCE_BARRIER' => $barrier]);
                $process->setTimeout(30);
                $process->start();
                $processes[$worker] = $process;
            }

            $this->waitForFiles($barrier, ['ready-first', 'ready-second']);
            touch($barrier.DIRECTORY_SEPARATOR.'go');
            $results = [];
            foreach ($processes as $worker => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $worker.': '.$process->getErrorOutput().$process->getOutput());
                $results[$worker] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach (glob($barrier.DIRECTORY_SEPARATOR.'*') ?: [] as $barrierFile) {
                if (is_file($barrierFile)) {
                    unlink($barrierFile);
                }
            }
            rmdir($barrier);
        }
    }

    private function waitForFiles(string $directory, array $files): void
    {
        $deadline = microtime(true) + 20;
        do {
            if (collect($files)->every(fn (string $file): bool => file_exists($directory.DIRECTORY_SEPARATOR.$file))) {
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->fail('Concurrent attendance workers did not reach the synchronization barrier.');
    }

    private function assertSingleAcceptedRequest(array $results): void
    {
        $this->assertSame(1, collect($results)->where('success', true)->count());
        $this->assertSame(1, collect($results)->where('validation_rejected', true)->count());
    }
}
