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
        });

        Schema::create('unmatched_ami_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50);
            $table->string('linkedid', 100)->nullable()->index();
            $table->string('channel', 150)->nullable()->index();
            $table->string('extracted_extension', 50)->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'extension')) {
                $table->dropColumn('extension');
            }
        });
        Schema::dropIfExists('unmatched_ami_events');
    }
};
