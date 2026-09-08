<?php

namespace Tests\Unit\Services;

use App\Services\DashboardSalesRangeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardSalesRangeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_range_uses_application_date_and_business_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-09-07 17:59:59', 'Asia/Manila'));

        $range = app(DashboardSalesRangeService::class)->default();

        $this->assertSame('2026-09-07', $range['date']);
        $this->assertSame('06:00', $range['start']);
        $this->assertSame('18:00', $range['end']);
        $this->assertSame('2026-09-07 06:00:00', $range['from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-07 18:00:00', $range['until']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $range['from']->getTimezone()->getName());
    }

    public function test_valid_explicit_range_is_preserved(): void
    {
        $request = Request::create('/dashboard', 'GET', [
            'sales_date' => '2026-05-15',
            'sales_start' => '07:30',
            'sales_end' => '17:45',
        ]);

        $range = app(DashboardSalesRangeService::class)->resolve($request);

        $this->assertSame('2026-05-15', $range['date']);
        $this->assertSame('07:30', $range['start']);
        $this->assertSame('17:45', $range['end']);
    }

    public function test_historical_date_uses_the_same_business_boundaries(): void
    {
        config(['app.timezone' => 'Asia/Manila']);

        $range = app(DashboardSalesRangeService::class)->forDate('2026-05-15');

        $this->assertSame('2026-05-15', $range['date']);
        $this->assertSame('2026-05-15 06:00:00', $range['from']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-15 18:00:00', $range['until']->format('Y-m-d H:i:s'));
        $this->assertSame('Asia/Manila', $range['from']->getTimezone()->getName());
    }

    public function test_invalid_or_non_increasing_range_falls_back_to_default(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 12:00:00'));
        $request = Request::create('/dashboard', 'GET', [
            'sales_date' => 'not-a-date',
            'sales_start' => '18:00',
            'sales_end' => '06:00',
        ]);

        $range = app(DashboardSalesRangeService::class)->resolve($request);

        $this->assertSame('2026-09-07', $range['date']);
        $this->assertSame('06:00', $range['start']);
        $this->assertSame('18:00', $range['end']);
    }
}
