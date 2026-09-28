<?php

namespace Tests\Unit\Support;

use App\Support\AdminNavigation;
use PHPUnit\Framework\TestCase;

class AdminNavigationTest extends TestCase
{
    public function test_admin_items_are_defined_once_for_sidebar_and_dashboard_consumers(): void
    {
        $items = AdminNavigation::adminItems();

        $this->assertSame([
            'admin.dashboard',
            'admin.supervisor',
            'admin.attendance.index',
            'admin.records.index',
            'admin.data-master.index',
            'admin.capture-records.index',
            'reports.index',
            'admin.disposition-records.index',
            'admin.disposition-codes.index',
            'admin.field-logic.index',
            'admin.extraction.index',
        ], array_column($items, 'route'));

        foreach ($items as $item) {
            $this->assertNotSame('', $item['label']);
            $this->assertNotSame('', $item['icon']);
            $this->assertNotSame('', $item['desc']);
        }
    }
}
