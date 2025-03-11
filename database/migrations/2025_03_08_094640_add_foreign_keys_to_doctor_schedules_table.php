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
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->foreign(['doctor_id'], 'fk_schedule_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('cascade')->onDelete('cascade');
            $table->foreign(['hospital_id'], 'fk_schedule_hospital')->references(['hospital_id'])->on('hospitals')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_schedules', function (Blueprint $table) {
            $table->dropForeign('fk_schedule_doctor');
            $table->dropForeign('fk_schedule_hospital');
        });
    }
};
