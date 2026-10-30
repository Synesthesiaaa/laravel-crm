<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('source_digest', 64);
            $table->unsignedBigInteger('source_bytes');
            $table->string('phase', 10)->default('preview');
            $table->string('status', 20)->default('queued');
            $table->boolean('allow_privileged_roles')->default(false);
            $table->unsignedBigInteger('cursor_offset')->default(0);
            $table->unsignedBigInteger('cursor_record')->default(1);
            foreach (['processed', 'accepted', 'skipped', 'rejected'] as $counter) {
                $table->unsignedBigInteger($counter)->default(0);
            }
            $table->json('preview_counts')->nullable();
            $table->string('message', 500)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('user_import_identities', function (Blueprint $table): void {
            $table->foreignUuid('import_id')->constrained('user_imports')->cascadeOnDelete();
            $table->string('identity', 64);
            $table->primary(['import_id', 'identity']);
        });
        Schema::create('user_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('import_id')->constrained('user_imports')->cascadeOnDelete();
            $table->string('phase', 10);
            $table->unsignedBigInteger('record');
            $table->text('errors');
            $table->index(['import_id', 'phase', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_import_errors');
        Schema::dropIfExists('user_import_identities');
        Schema::dropIfExists('user_imports');
    }
};
