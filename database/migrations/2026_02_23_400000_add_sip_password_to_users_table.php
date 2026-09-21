<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'extension')) {
                $table->string('extension', 50)->nullable()->after('vici_user')
                    ->comment('SIP/PJSIP extension for AMI channel matching');
            }

            if (! Schema::hasColumn('users', 'sip_password')) {
                $table->text('sip_password')->nullable()->after('extension')
                    ->comment('Encrypted SIP password for WebRTC registration');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sip_password')) {
                $table->dropColumn('sip_password');
            }
        });
    }
};
