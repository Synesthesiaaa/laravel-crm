<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ezycash', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('request_id', 255);
            $table->string('cardholder_name', 255);
            $table->string('mpi_credit_card_no', 255);
            $table->string('bank', 255);
            $table->string('account_type', 255);
            $table->string('account_number', 255);
            $table->string('surname', 255);
            $table->string('first_name', 255);
            $table->string('middle_name', 255)->nullable();
            $table->decimal('ezycash_amount', 10, 2);
            $table->string('term', 255);
            $table->decimal('rate', 10, 2);
            $table->string('amenable', 255)->nullable();
            $table->string('agent', 255);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->timestamps();
            $table->index('date');
            $table->index('agent');
            $table->index('request_id');
            $table->index('lead_id');
            $table->index('phone_number');
        });

        Schema::create('ezyconvert', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('rate', 10, 2);
            $table->string('request_id', 255);
            $table->string('cardholder_name', 255);
            $table->string('mpi_credit_card_no', 255);
            $table->string('surname', 255);
            $table->string('first_name', 255);
            $table->string('middle_name', 255)->nullable();
            $table->decimal('ezyconvert_amount', 10, 2);
            $table->string('term', 255);
            $table->string('amenable', 255)->nullable();
            $table->string('agent', 255);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->timestamps();
            $table->index('date');
            $table->index('agent');
            $table->index('request_id');
            $table->index('lead_id');
            $table->index('phone_number');
        });

        Schema::create('ezytransfer', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->decimal('rate', 10, 2);
            $table->string('request_id', 255);
            $table->string('cardholder_name', 255);
            $table->string('mpi_credit_card_no', 255);
            $table->decimal('ezytransfer_amount', 10, 2);
            $table->string('term', 255);
            $table->string('other_bank_acc', 255);
            $table->string('other_bank_card_number', 255);
            $table->string('agent', 255);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->timestamps();
            $table->index('date');
            $table->index('agent');
            $table->index('request_id');
            $table->index('lead_id');
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ezycash');
        Schema::dropIfExists('ezyconvert');
        Schema::dropIfExists('ezytransfer');
    }
};
