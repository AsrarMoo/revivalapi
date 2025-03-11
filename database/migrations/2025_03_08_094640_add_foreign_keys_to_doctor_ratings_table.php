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
        Schema::table('doctor_ratings', function (Blueprint $table) {
            $table->foreign(['appointment_id'], 'fk_rating_appointment')->references(['appointment_id'])->on('appointments')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['doctor_id'], 'fk_rating_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['patient_id'], 'fk_rating_patient')->references(['patient_id'])->on('patients')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_ratings', function (Blueprint $table) {
            $table->dropForeign('fk_rating_appointment');
            $table->dropForeign('fk_rating_doctor');
            $table->dropForeign('fk_rating_patient');
        });
    }
};
