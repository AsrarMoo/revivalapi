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
        Schema::table('medical_records', function (Blueprint $table) {
            $table->foreign(['doctor_id'], 'fk_medical_record_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['hospital_id'], 'fk_medical_record_hospital')->references(['hospital_id'])->on('hospitals')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['patient_id'], 'fk_medical_record_patient')->references(['patient_id'])->on('patients')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropForeign('fk_medical_record_doctor');
            $table->dropForeign('fk_medical_record_hospital');
            $table->dropForeign('fk_medical_record_patient');
        });
    }
};
