<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_smtp_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('host');
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption', 8)->default('tls');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_name');
            $table->string('from_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_smtp_settings');
    }
};
