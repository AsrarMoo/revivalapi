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
        Schema::table('ambulance_requests', function (Blueprint $table) {
            $table->foreign(['hospital_id'], 'fk_ambulance_hospital')->references(['hospital_id'])->on('hospitals')->onUpdate('restrict')->onDelete('set null');
            $table->foreign(['patient_id'], 'fk_ambulance_patient')->references(['patient_id'])->on('patients')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['requested_by_user_id'], 'fk_requested_by')->references(['user_id'])->on('users')->onUpdate('restrict')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ambulance_requests', function (Blueprint $table) {
            $table->dropForeign('fk_ambulance_hospital');
            $table->dropForeign('fk_ambulance_patient');
            $table->dropForeign('fk_requested_by');
        });
    }
};
