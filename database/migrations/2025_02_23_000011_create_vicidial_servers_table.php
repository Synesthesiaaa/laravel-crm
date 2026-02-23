<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vicidial_servers', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('server_name', 100);
            $table->string('api_url', 255);
            $table->string('db_host', 255);
            $table->string('db_username', 100);
            $table->string('db_password', 255);
            $table->string('db_name', 100)->default('asterisk');
            $table->unsignedInteger('db_port')->default(3306);
            $table->string('api_user', 100)->nullable();
            $table->string('api_pass', 255)->nullable();
            $table->string('source', 100)->default('crm_tracker');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('priority')->default(0);
            $table->timestamps();
            $table->index('campaign_code');
            $table->index('is_active');
            $table->index(['campaign_code', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vicidial_servers');
    }
};
