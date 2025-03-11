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
        Schema::create('ambulance_requests', function (Blueprint $table) {
            $table->increments('request_id');
            $table->unsignedInteger('patient_id')->index('fk_ambulance_patient');
            $table->unsignedInteger('requested_by_user_id')->nullable()->index('fk_requested_by');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedInteger('hospital_id')->nullable()->index('fk_ambulance_hospital');
            $table->enum('status', ['pending', 'accepted', 'rejected', 'canceled'])->nullable()->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ambulance_requests');
    }
};
