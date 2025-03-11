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
        Schema::create('medical_records', function (Blueprint $table) {
            $table->increments('medical_record_id');
            $table->unsignedInteger('patient_id')->index('fk_medical_record_patient');
            $table->unsignedInteger('doctor_id')->index('fk_medical_record_doctor');
            $table->unsignedInteger('hospital_id')->index('fk_medical_record_hospital');
            $table->text('diagnosis');
            $table->enum('patient_status', ['مستقرة', 'حرجة', 'تحت المراقبة', 'تم الشفاء'])->default('تحت المراقبة');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medical_records');
    }
};
