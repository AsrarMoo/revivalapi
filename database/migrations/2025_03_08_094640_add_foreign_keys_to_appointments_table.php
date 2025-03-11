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
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreign(['patient_id'], 'fk_appoiappointment_patient')->references(['patient_id'])->on('patients')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['doctor_id'], 'fk_appointment_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['schedule_id'], 'fk_appointment_schedule')->references(['schedule_id'])->on('doctor_schedules')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['hospital_id'], 'fk__appointment_hospital')->references(['hospital_id'])->on('hospitals')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign('fk_appoiappointment_patient');
            $table->dropForeign('fk_appointment_doctor');
            $table->dropForeign('fk_appointment_schedule');
            $table->dropForeign('fk__appointment_hospital');
        });
    }
};
