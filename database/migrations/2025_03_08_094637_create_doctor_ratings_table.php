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
        Schema::create('doctor_ratings', function (Blueprint $table) {
            $table->increments('rating_id');
            $table->unsignedInteger('appointment_id')->index('fk_rating_appointment');
            $table->unsignedInteger('patient_id')->index('fk_rating_patient');
            $table->unsignedInteger('doctor_id')->index('fk_rating_doctor');
            $table->boolean('professionalism');
            $table->boolean('communication');
            $table->boolean('listening');
            $table->boolean('knowledge_experience');
            $table->boolean('punctuality');
            $table->decimal('overall_rating', 2, 1)->nullable()->storedAs('(`professionalism` + `communication` + `listening` + `knowledge_experience` + `punctuality`) / 5');
            $table->timestamp('rating_date')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctor_ratings');
    }
};
