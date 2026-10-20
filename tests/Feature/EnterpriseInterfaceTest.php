<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class EnterpriseInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_metrics_are_native_links_and_loading_is_announced(): void
    {
        $html = Blade::render('<x-stat-card label="Calls" value="12" href="/records" />');
        $this->assertMatchesRegularExpression('/<a\s[^>]*href="\/records"/', $html);
        $this->assertStringNotContainsString('onclick=', $html);
        $this->assertStringNotContainsString('<svg', $html);

        $loading = Blade::render('<x-stat-card label="Calls" value="0" :loading="true" />');
        $this->assertStringContainsString('aria-busy="true"', $loading);
        $this->assertStringContainsString('Loading Calls', $loading);
    }

    public function test_tables_expose_keyboard_scrolling_and_sort_direction(): void
    {
        $html = Blade::render('<x-table.index caption="Call history"><x-table.head :columns="[[\'key\' => \'duration\', \'label\' => \'Duration\', \'sortable\' => true]]" sort-by="duration" sort-dir="desc" /></x-table.index>');
        $this->assertStringContainsString('tabindex="0" role="region" aria-label="Call history"', $html);
        $this->assertStringContainsString('scope="col"', $html);
        $this->assertStringContainsString('aria-sort="descending"', $html);
    }

    public function test_shared_dialogs_have_names_background_isolation_and_escape_handling(): void
    {
        foreach (['<x-modal name="test" title="Edit campaign">Fields</x-modal>', '<x-slide-over name="test" title="Lead details">Fields</x-slide-over>'] as $template) {
            $html = Blade::render($template);
            $this->assertStringContainsString('role="dialog"', $html);
            $this->assertStringContainsString('aria-modal="true"', $html);
            $this->assertStringContainsString('aria-labelledby=', $html);
            $this->assertStringContainsString('x-trap.inert.noscroll=', $html);
            $this->assertStringContainsString('@keydown.escape.stop.prevent=', $html);
        }
    }
}
