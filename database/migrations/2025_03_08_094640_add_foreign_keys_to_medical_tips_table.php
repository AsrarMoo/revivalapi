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
        Schema::table('medical_tips', function (Blueprint $table) {
            $table->foreign(['doctor_id'], 'fk_tip_doctor')->references(['doctor_id'])->on('doctors')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_tips', function (Blueprint $table) {
            $table->dropForeign('fk_tip_doctor');
        });
    }
};
