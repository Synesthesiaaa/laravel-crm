<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['pjli_cycle', 'pjli_winback', 'pjli_renewal', 'pjli_ofw'];
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                continue;
            }
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('agent', 255);
                $table->unsignedBigInteger('lead_id')->nullable();
                $table->string('phone_number', 50)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->index('date');
                $table->index('agent');
                $table->index('lead_id');
                $table->index('phone_number');
            });
        }
    }

    public function down(): void
    {
        foreach (['pjli_cycle', 'pjli_winback', 'pjli_renewal', 'pjli_ofw'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
