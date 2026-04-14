<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'default_campaign')) {
                $table->string('default_campaign', 50)->nullable()->after('sip_password');
            }
            if (! Schema::hasColumn('users', 'auto_vici_login')) {
                $table->boolean('auto_vici_login')->default(false)->after('default_campaign');
            }
            if (! Schema::hasColumn('users', 'default_blended')) {
                $table->boolean('default_blended')->default(true)->after('auto_vici_login');
            }
            if (! Schema::hasColumn('users', 'default_ingroups')) {
                $table->text('default_ingroups')->nullable()->after('default_blended');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['default_ingroups', 'default_blended', 'auto_vici_login', 'default_campaign'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
