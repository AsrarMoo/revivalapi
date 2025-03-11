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
        Schema::create('appointments', function (Blueprint $table) {
            $table->increments('appointment_id');
            $table->unsignedInteger('patient_id')->index('fk_appoiappointment_patient');
            $table->unsignedInteger('hospital_id')->index('fk__appointment_hospital');
            $table->unsignedInteger('doctor_id')->index('fk_appointment_doctor');
            $table->unsignedInteger('schedule_id')->index('fk_appointment_schedule');
            $table->enum('status', ['Pending', 'Confirmed', 'Cancelled'])->nullable()->default('Pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
