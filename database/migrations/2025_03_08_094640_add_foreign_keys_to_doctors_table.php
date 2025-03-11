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
        Schema::table('doctors', function (Blueprint $table) {
            $table->foreign(['specialty_id'], 'doctors_ibfk_1')->references(['specialty_id'])->on('specialties')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['user_id'], 'fk_doctor_user')->references(['user_id'])->on('users')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropForeign('doctors_ibfk_1');
            $table->dropForeign('fk_doctor_user');
        });
    }
};
