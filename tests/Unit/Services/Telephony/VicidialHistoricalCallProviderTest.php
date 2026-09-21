<?php

namespace Tests\Unit\Services\Telephony;

use App\Models\Campaign;
use App\Models\VicidialServer;
use App\Services\Telephony\HistoricalCallRecord;
use App\Services\Telephony\VicidialHistoricalCallProvider;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

final class VicidialHistoricalCallProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRemoteTables();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('vicidial_closer_log');
        Schema::dropIfExists('vicidial_log');

        parent::tearDown();
    }

    public function test_fetch_combines_both_source_tables_and_applies_campaign_filters(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        $connection = DB::connection('sqlite');

        $connection->table('vicidial_log')->insert([
            'uniqueid' => 'outbound-1',
            'campaign_id' => 'CAMP_A',
            'list_id' => 'LIST_A',
            'lead_id' => 101,
            'user' => 'agent_one',
            'phone_number' => '639121234567',
            'call_date' => '2026-05-18 09:00:00',
            'start_epoch' => 1779066000,
            'end_epoch' => 1779066127,
            'length_in_sec' => 127,
            'status' => 'SALE',
            'queue_seconds' => null,
            'term_reason' => 'HANGUP',
        ]);
        $connection->table('vicidial_log')->insert([
            'uniqueid' => 'unrelated-1',
            'campaign_id' => 'OTHER',
            'list_id' => 'LIST_X',
            'lead_id' => 999,
            'user' => 'other_agent',
            'phone_number' => '639999999999',
            'call_date' => '2026-05-18 10:00:00',
            'start_epoch' => 1779069600,
            'end_epoch' => 1779069660,
            'length_in_sec' => 60,
            'status' => 'DROP',
            'queue_seconds' => null,
            'term_reason' => 'EXTERNAL',
        ]);
        $connection->table('vicidial_closer_log')->insert([
            'uniqueid' => 'inbound-1',
            'campaign_id' => 'CAMP_B',
            'list_id' => 'LIST_B',
            'lead_id' => 202,
            'user' => 'unknown_agent',
            'phone_number' => '09121234567',
            'call_date' => '2026-05-18 08:00:00',
            'start_epoch' => 1779062400,
            'end_epoch' => 1779062520,
            'length_in_sec' => 120,
            'status' => 'NA',
            'queue_seconds' => 12,
            'term_reason' => 'AGENT',
        ]);

        $result = $this->provider($connection)->fetch(
            $server,
            $campaign,
            ['CAMP_A', 'CAMP_B'],
            ['start_date' => '2026-05-18', 'end_date' => '2026-05-18'],
            1,
            10,
        );

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame(2, $result->total);
        $this->assertCount(2, $result->records);
        $this->assertSame('outbound-1', $result->records[0]->uniqueCallId);
        $this->assertSame('OUTBOUND', $result->records[0]->callDirection);
        $this->assertSame(127, $result->records[0]->durationSeconds);
        $this->assertSame('inbound-1', $result->records[1]->uniqueCallId);
        $this->assertSame('INBOUND', $result->records[1]->callDirection);
        $this->assertSame(12, $result->records[1]->waitSeconds);
        $this->assertSame(['agent_one', 'unknown_agent'], $result->filterOptions['agents']);
        $this->assertSame(['CAMP_A', 'CAMP_B'], $result->filterOptions['campaigns']);
    }

    public function test_fetch_supports_phone_variants_filters_and_server_pagination(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        $connection = DB::connection('sqlite');

        foreach (['one', 'two', 'three'] as $index => $suffix) {
            $connection->table('vicidial_log')->insert([
                'uniqueid' => 'call-'.$suffix,
                'campaign_id' => 'CAMP_A',
                'list_id' => 'LIST_A',
                'lead_id' => 300 + $index,
                'user' => 'agent_one',
                'phone_number' => '639121234567',
                'call_date' => sprintf('2026-05-18 %02d:00:00', 9 + $index),
                'start_epoch' => 1779066000 + ($index * 3600),
                'end_epoch' => 1779066060 + ($index * 3600),
                'length_in_sec' => 60,
                'status' => 'SALE',
                'queue_seconds' => null,
                'term_reason' => 'HANGUP',
            ]);
        }

        $result = $this->provider($connection)->fetch(
            $server,
            $campaign,
            ['CAMP_A'],
            [
                'phone' => '09121234567',
                'agent' => 'agent_one',
                'status' => 'SALE',
                'direction' => 'OUTBOUND',
            ],
            2,
            1,
        );

        $this->assertTrue($result->success);
        $this->assertSame(3, $result->total);
        $this->assertCount(1, $result->records);
        $this->assertSame('call-two', $result->records[0]->uniqueCallId);
    }

    public function test_normalize_row_preserves_unmapped_values_and_null_talk_time(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $record = $this->provider(DB::connection('sqlite'))->normalizeRow((object) [
            'source_table' => 'vicidial_closer_log',
            'unique_call_id' => 'closer-1',
            'vicidial_campaign_id' => 'CAMP_A',
            'vicidial_list_id' => 'LIST_A',
            'lead_id' => '44',
            'vicidial_user' => 'legacy_agent',
            'phone_number' => '09 1212 34567',
            'call_date' => '2026-05-18 08:00:00',
            'start_epoch' => 1779062400,
            'end_epoch' => 1779062527,
            'duration_seconds' => '127',
            'raw_status' => 'LEGACY_STATUS',
            'wait_seconds' => '7',
            'raw_end_reason' => 'AGENT',
        ], $campaign);

        $this->assertInstanceOf(HistoricalCallRecord::class, $record);
        $this->assertSame('closer-1', $record->uniqueCallId);
        $this->assertSame('INBOUND', $record->callDirection);
        $this->assertSame(127, $record->durationSeconds);
        $this->assertSame(7, $record->waitSeconds);
        $this->assertNull($record->talkSeconds);
        $this->assertSame('LEGACY_STATUS', $record->status);
        $this->assertSame('09 1212 34567', $record->phoneNumber);
    }

    public function test_normalize_row_uses_a_composite_fallback_only_when_identity_fields_are_present(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $row = (object) [
            'source_table' => 'vicidial_log',
            'unique_call_id' => null,
            'lead_id' => 44,
            'phone_number' => '09121234567',
            'call_date' => '2026-05-18 08:00:00',
        ];

        $record = $this->provider(DB::connection('sqlite'))->normalizeRow($row, $campaign);

        $this->assertSame(
            sha1(implode('|', [
                'vicidial_log',
                44,
                Carbon::parse('2026-05-18 08:00:00', (string) config('vicidial.report_timezone', config('app.timezone', 'UTC')))->toIso8601String(),
                '09121234567',
            ])),
            $record->uniqueCallId,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->provider(DB::connection('sqlite'))->normalizeRow((object) [
            'source_table' => 'vicidial_log',
            'unique_call_id' => null,
            'lead_id' => 44,
            'call_date' => '2026-05-18 08:00:00',
        ], $campaign);
    }

    public function test_fetch_range_reads_only_the_requested_window_without_metadata_queries(): void
    {
        $campaign = Campaign::factory()->create(['code' => 'mbsales']);
        $server = VicidialServer::factory()->create(['campaign_code' => 'mbsales']);
        $connection = DB::connection('sqlite');

        foreach ([
            ['uniqueid' => 'before-window', 'call_date' => '2026-05-18 08:59:59'],
            ['uniqueid' => 'inside-window', 'call_date' => '2026-05-18 09:00:00'],
            ['uniqueid' => 'after-window', 'call_date' => '2026-05-18 09:05:01'],
        ] as $index => $call) {
            $connection->table('vicidial_log')->insert([
                'uniqueid' => $call['uniqueid'],
                'campaign_id' => 'CAMP_A',
                'list_id' => 'LIST_A',
                'lead_id' => 400 + $index,
                'user' => 'agent_one',
                'phone_number' => '639121234567',
                'call_date' => $call['call_date'],
                'start_epoch' => 1779066000,
                'end_epoch' => 1779066060,
                'length_in_sec' => 60,
                'status' => 'SALE',
                'queue_seconds' => null,
                'term_reason' => 'HANGUP',
            ]);
        }

        $result = $this->provider($connection)->fetchRange(
            $server,
            $campaign,
            ['CAMP_A'],
            Carbon::parse('2026-05-18 09:00:00'),
            Carbon::parse('2026-05-18 09:05:00'),
        );

        $this->assertTrue($result->success, (string) $result->message);
        $this->assertSame(1, $result->total);
        $this->assertSame('inside-window', $result->records[0]->uniqueCallId);
        $this->assertSame(1, $result->meta['rows_received']);
    }

    private function provider(Connection $connection): TestableHistoricalCallProvider
    {
        return new TestableHistoricalCallProvider($connection);
    }

    private function createRemoteTables(): void
    {
        Schema::dropIfExists('vicidial_closer_log');
        Schema::dropIfExists('vicidial_log');

        foreach (['vicidial_log', 'vicidial_closer_log'] as $table) {
            Schema::create($table, function ($schema): void {
                $schema->string('uniqueid')->nullable();
                $schema->string('campaign_id')->nullable();
                $schema->string('list_id')->nullable();
                $schema->unsignedBigInteger('lead_id')->nullable();
                $schema->string('user')->nullable();
                $schema->string('phone_number')->nullable();
                $schema->dateTime('call_date')->nullable();
                $schema->unsignedBigInteger('start_epoch')->nullable();
                $schema->unsignedBigInteger('end_epoch')->nullable();
                $schema->integer('length_in_sec')->nullable();
                $schema->string('status')->nullable();
                $schema->integer('queue_seconds')->nullable();
                $schema->string('term_reason')->nullable();
            });
        }
    }
}

final class TestableHistoricalCallProvider extends VicidialHistoricalCallProvider
{
    public function __construct(private Connection $testConnection) {}

    protected function makeConnection(VicidialServer $server): Connection
    {
        return $this->testConnection;
    }

    protected function disconnect(Connection $connection): void {}
}
