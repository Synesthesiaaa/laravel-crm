<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username', 255)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 255)->nullable()->after('username');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role', 50)->default('Agent')->after('full_name');
            }
            if (!Schema::hasColumn('users', 'vici_user')) {
                $table->string('vici_user', 100)->nullable()->after('role');
            }
            if (!Schema::hasColumn('users', 'vici_pass')) {
                $table->string('vici_pass', 255)->nullable()->after('vici_user');
            }
        });

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'full_name', 'role', 'vici_user', 'vici_pass']);
        });
    }
};
