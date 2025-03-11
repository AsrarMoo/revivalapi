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
        Schema::create('medical_record_medications', function (Blueprint $table) {
            $table->increments('record_medication_id');
            $table->unsignedInteger('medical_record_id')->index('fk_record_medication');
            $table->unsignedInteger('medication_id')->index('fk_medication');
            $table->string('dosage');
            $table->string('duration');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_record_medications');
    }
};
