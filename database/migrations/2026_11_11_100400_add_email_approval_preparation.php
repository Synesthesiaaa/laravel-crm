<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->string('approval_state', 20)->default('none');
            $table->json('approval_request')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->string('approval_error', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', fn (Blueprint $table) => $table->dropColumn(['approval_state', 'approval_request', 'approval_requested_at', 'approval_error']));
    }
};
