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
        Schema::table('medical_record_medications', function (Blueprint $table) {
            $table->foreign(['medication_id'], 'fk_medication')->references(['medication_id'])->on('medications')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['medical_record_id'], 'fk_record_medication')->references(['medical_record_id'])->on('medical_records')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_record_medications', function (Blueprint $table) {
            $table->dropForeign('fk_medication');
            $table->dropForeign('fk_record_medication');
        });
    }
};
