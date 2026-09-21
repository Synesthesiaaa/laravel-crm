<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $formTables = [
        'ezycash',
        'ezyconvert',
        'ezytransfer',
        'pjli_cycle',
        'pjli_winback',
        'pjli_renewal',
        'pjli_ofw',
    ];

    public function up(): void
    {
        foreach ($this->formTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'created_at') && ! $this->indexExists($tableName, $tableName.'_dashboard_created_at_index')) {
                    $table->index('created_at', $tableName.'_dashboard_created_at_index');
                }

                if (Schema::hasColumn($tableName, 'date')
                    && Schema::hasColumn($tableName, 'agent')
                    && ! $this->indexExists($tableName, $tableName.'_dashboard_date_agent_index')) {
                    $table->index(['date', 'agent'], $tableName.'_dashboard_date_agent_index');
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->formTables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $createdAtIndex = $tableName.'_dashboard_created_at_index';
                if ($this->indexExists($tableName, $createdAtIndex)) {
                    $table->dropIndex($createdAtIndex);
                }

                $dateAgentIndex = $tableName.'_dashboard_date_agent_index';
                if ($this->indexExists($tableName, $dateAgentIndex)) {
                    $table->dropIndex($dateAgentIndex);
                }
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
};
