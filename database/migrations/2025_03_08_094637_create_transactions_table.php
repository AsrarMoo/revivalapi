<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('transaction_id');
            $table->unsignedInteger('appointment_id')->index('fk_transaction_appointment');
            $table->unsignedInteger('patient_id')->index('fk_transaction_patient');
            $table->decimal('amount', 10);
            $table->enum('payment_method', ['cash', 'wallet']);
            $table->enum('transaction_status', ['pending', 'completed', 'failed'])->nullable()->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
