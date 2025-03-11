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
        Schema::table('medical_record_tests', function (Blueprint $table) {
            $table->foreign(['medical_record_id'], 'fk_record_test')->references(['medical_record_id'])->on('medical_records')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['test_id'], 'fk_test')->references(['test_id'])->on('medical_tests')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medical_record_tests', function (Blueprint $table) {
            $table->dropForeign('fk_record_test');
            $table->dropForeign('fk_test');
        });
    }
};
