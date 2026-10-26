<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('request_id', 255);
            $table->string('status', 20);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();

            $table->unique(['lead_import_id', 'row_number']);
            $table->index(['lead_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_import_rows');
    }
};
