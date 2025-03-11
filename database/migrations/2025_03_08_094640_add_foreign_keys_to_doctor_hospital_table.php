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
        Schema::table('doctor_hospital', function (Blueprint $table) {
            $table->foreign(['doctor_id'], 'fk_doctor_hospital_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['hospital_id'], 'fk_doctor_hospital_hospital')->references(['hospital_id'])->on('hospitals')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_hospital', function (Blueprint $table) {
            $table->dropForeign('fk_doctor_hospital_doctor');
            $table->dropForeign('fk_doctor_hospital_hospital');
        });
    }
};
