<?php

namespace Tests\Integration;

use App\Models\Campaign;
use App\Models\VicidialServer;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class VicidialHistoricalCallProviderStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_range_streams_stable_bounded_pages_including_fallback_identities(): void
    {
        $this->createSourceTable('vicidial_log');
        $this->createSourceTable('vicidial_closer_log');
        foreach (range(1, 93) as $index) {
            DB::table('vicidial_log')->insert($this->sourceRow($index, '2026-05-18 09:'.str_pad((string) intdiv($index - 1, 60), 2, '0', STR_PAD_LEFT).':'.str_pad((string) (($index - 1) % 60), 2, '0', STR_PAD_LEFT)));
        }
        foreach (range(101, 128) as $index) {
            DB::table('vicidial_closer_log')->insert($this->sourceRow($index, '2026-05-18 09:'.str_pad((string) intdiv($index - 101, 60), 2, '0', STR_PAD_LEFT).':'.str_pad((string) (($index - 101) % 60), 2, '0', STR_PAD_LEFT)));
        }
        DB::table('vicidial_log')->insert($this->sourceRow(999, '2026-05-18 09:59:59', true));

        $campaign = Campaign::factory()->create(['code' => 'stream-test']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'stream-test']);
        $chunks = [];
        $identities = [];

        $result = (new SQLiteHistoricalCallProvider)->streamRange(
            $server,
            $campaign,
            ['CAMP_A'],
            Carbon::parse('2026-05-18 09:00:00'),
            Carbon::parse('2026-05-18 10:00:00'),
            function (array $records) use (&$chunks, &$identities): void {
                $chunks[] = count($records);
                foreach ($records as $record) {
                    $this->assertInstanceOf(HistoricalCallRecord::class, $record);
                    $identities[] = $record->id;
                }
            },
            37,
        );

        $this->assertTrue($result->success);
        $this->assertSame(122, $result->total);
        $this->assertSame(122, count($identities));
        $this->assertGreaterThan(1, count($chunks));
        $this->assertLessThanOrEqual(37, max($chunks));
        $this->assertSame(count($identities), count(array_unique($identities)));
    }

    private function createSourceTable(string $name): void
    {
        Schema::create($name, function (Blueprint $table): void {
            $table->string('uniqueid')->nullable();
            $table->string('campaign_id')->nullable();
            $table->string('list_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('user')->nullable();
            $table->string('phone_number')->nullable();
            $table->dateTime('call_date')->nullable();
            $table->unsignedBigInteger('start_epoch')->nullable();
            $table->unsignedBigInteger('end_epoch')->nullable();
            $table->integer('length_in_sec')->nullable();
            $table->string('status')->nullable();
            $table->integer('queue_seconds')->nullable();
            $table->string('term_reason')->nullable();
        });
    }

    private function sourceRow(int $index, string $callDate, bool $emptyUniqueId = false): array
    {
        return [
            'uniqueid' => $emptyUniqueId ? null : 'unique-'.$index,
            'campaign_id' => 'CAMP_A',
            'list_id' => 'LIST_1',
            'lead_id' => $index,
            'user' => 'agent_'.($index % 4),
            'phone_number' => '63912'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
            'call_date' => $callDate,
            'start_epoch' => null,
            'end_epoch' => null,
            'length_in_sec' => 60,
            'status' => 'SALE',
            'queue_seconds' => 5,
            'term_reason' => 'HANGUP',
        ];
    }
}

final class SQLiteHistoricalCallProvider extends VicidialHistoricalCallProvider
{
    protected function makeConnection(VicidialServer $server): Connection
    {
        return DB::connection();
    }

    protected function disconnect(Connection $connection): void
    {
        // Keep the isolated test connection alive for RefreshDatabase cleanup.
    }
}
