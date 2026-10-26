<?php

namespace Tests\Integration;

use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CallReservationMySqlConcurrencyTest extends TestCase
{
    public function test_two_agents_requests_cannot_reserve_or_dial_concurrently(): void
    {
        if (getenv('GITHUB_ACTIONS') !== 'true') {
            $this->markTestSkipped('Runs only against the dedicated ephemeral MySQL service in CI.');
        }

        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('laravel_crm_test', config('database.connections.mysql.database'));

        config(['vicidial.require_vicidial_agent_session_before_dial' => false]);
        $campaign = 'phase3-concurrency-'.bin2hex(random_bytes(4));
        $user = User::factory()->create([
            'role' => User::ROLE_AGENT,
            'vici_user' => 'synthetic-agent',
            'vici_pass' => 'synthetic-secret',
        ]);
        VicidialServer::factory()->create([
            'campaign_code' => $campaign,
            'api_url' => 'https://telephony.test.invalid/agc/api.php',
            'api_user' => 'synthetic-api-user',
            'api_pass' => 'synthetic-api-secret',
            'is_default' => true,
        ]);

        $trigger = 'phase3_call_insert_delay';
        DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
        DB::unprepared('CREATE TRIGGER '.$trigger.' BEFORE INSERT ON call_sessions FOR EACH ROW SET @phase3_delay = SLEEP(1)');

        $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm-call-race-'.bin2hex(random_bytes(8));
        if (! mkdir($barrier, 0700, true) && ! is_dir($barrier)) {
            $this->fail('Unable to create the isolated concurrency-test barrier directory.');
        }

        $processes = [];

        try {
            foreach (['first', 'second'] as $worker) {
                $process = new Process([
                    PHP_BINARY,
                    base_path('tests/Support/call-reservation-worker.php'),
                    (string) $user->id,
                    $campaign,
                    $worker,
                ], base_path(), ['CALL_RESERVATION_BARRIER' => $barrier]);
                $process->setTimeout(30);
                $process->start();
                $processes[$worker] = $process;
            }

            $this->waitForFiles($barrier, ['ready-first', 'ready-second']);
            DB::beginTransaction();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            touch($barrier.DIRECTORY_SEPARATOR.'go');
            usleep(500000);
            DB::commit();

            $results = [];
            foreach ($processes as $worker => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $worker.': '.$process->getErrorOutput().$process->getOutput());
                $results[$worker] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::unprepared('DROP TRIGGER IF EXISTS '.$trigger);
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

        $this->assertSame(1, collect($results)->where('success', true)->count());
        $this->assertSame(1, collect($results)->sum('http_requests'));
        $this->assertDatabaseCount('call_sessions', 1);
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

        $this->fail('Concurrent workers did not reach the synchronization barrier.');
    }
}
