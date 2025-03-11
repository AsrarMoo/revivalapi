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
        Schema::create('medical_record_tests', function (Blueprint $table) {
            $table->increments('record_test_id');
            $table->unsignedInteger('medical_record_id')->index('fk_record_test');
            $table->unsignedInteger('test_id')->index('fk_test');
            $table->text('test_result');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_record_tests');
    }
};
