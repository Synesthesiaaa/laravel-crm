<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // vicidial_servers: (campaign_code, is_active)
        if (Schema::hasTable('vicidial_servers') && ! $this->indexExists('vicidial_servers', 'vicidial_servers_campaign_code_is_active_index')) {
            Schema::table('vicidial_servers', function (Blueprint $table) {
                $table->index(['campaign_code', 'is_active'], 'vicidial_servers_campaign_code_is_active_index');
            });
        }

        // disposition_codes: (campaign_code, is_active)
        if (Schema::hasTable('disposition_codes') && ! $this->indexExists('disposition_codes', 'disposition_codes_campaign_code_is_active_index')) {
            Schema::table('disposition_codes', function (Blueprint $table) {
                $table->index(['campaign_code', 'is_active'], 'disposition_codes_campaign_code_is_active_index');
            });
        }

        // forms: (campaign_code, is_active)
        if (Schema::hasTable('forms') && ! $this->indexExists('forms', 'forms_campaign_code_is_active_index')) {
            Schema::table('forms', function (Blueprint $table) {
                $table->index(['campaign_code', 'is_active'], 'forms_campaign_code_is_active_index');
            });
        }

        // campaign_disposition_records
        if (Schema::hasTable('campaign_disposition_records')) {
            Schema::table('campaign_disposition_records', function (Blueprint $table) {
                if (! $this->indexExists('campaign_disposition_records', 'cdr_campaign_agent_index')) {
                    $table->index(['campaign_code', 'agent'], 'cdr_campaign_agent_index');
                }
                if (! $this->indexExists('campaign_disposition_records', 'cdr_called_at_index')) {
                    $table->index('called_at', 'cdr_called_at_index');
                }
            });
        }

        // crm_call_history
        if (Schema::hasTable('crm_call_history') && ! $this->indexExists('crm_call_history', 'cch_campaign_agent_index')) {
            Schema::table('crm_call_history', function (Blueprint $table) {
                $table->index(['campaign_code', 'agent'], 'cch_campaign_agent_index');
            });
        }

        // agent_call_records
        if (Schema::hasTable('agent_call_records')) {
            Schema::table('agent_call_records', function (Blueprint $table) {
                if (! $this->indexExists('agent_call_records', 'acr_campaign_agent_index')) {
                    $table->index(['campaign_code', 'agent'], 'acr_campaign_agent_index');
                }
                if (! $this->indexExists('agent_call_records', 'acr_called_at_index')) {
                    $table->index('called_at', 'acr_called_at_index');
                }
            });
        }

        // form_fields: (campaign_code, form_type)
        if (Schema::hasTable('form_fields') && ! $this->indexExists('form_fields', 'form_fields_campaign_form_index')) {
            Schema::table('form_fields', function (Blueprint $table) {
                $table->index(['campaign_code', 'form_type'], 'form_fields_campaign_form_index');
            });
        }

        // agent_screen_fields: campaign_code
        if (Schema::hasTable('agent_screen_fields') && ! $this->indexExists('agent_screen_fields', 'agent_screen_fields_campaign_code_index')) {
            Schema::table('agent_screen_fields', function (Blueprint $table) {
                $table->index('campaign_code', 'agent_screen_fields_campaign_code_index');
            });
        }
    }

    public function down(): void
    {
        $drops = [
            ['vicidial_servers', 'vicidial_servers_campaign_code_is_active_index'],
            ['disposition_codes', 'disposition_codes_campaign_code_is_active_index'],
            ['forms', 'forms_campaign_code_is_active_index'],
            ['campaign_disposition_records', 'cdr_campaign_agent_index'],
            ['campaign_disposition_records', 'cdr_called_at_index'],
            ['crm_call_history', 'cch_campaign_agent_index'],
            ['agent_call_records', 'acr_campaign_agent_index'],
            ['agent_call_records', 'acr_called_at_index'],
            ['form_fields', 'form_fields_campaign_form_index'],
            ['agent_screen_fields', 'agent_screen_fields_campaign_code_index'],
        ];
        foreach ($drops as [$table, $index]) {
            if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($index));
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list(`{$table}`)");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }

            return false;
        }
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
