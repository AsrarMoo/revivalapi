<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('users')) { // ✅ التحقق من وجود الجدول
            Schema::create('users', function (Blueprint $table) {
                $table->id('user_id');
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
                $table->enum('user_type', ['patient', 'doctor', 'hospital', 'admin']);
                $table->boolean('is_active')->default(1);
                $table->unsignedBigInteger('doctor_id')->nullable();
                $table->unsignedBigInteger('hospital_id')->nullable();
                $table->unsignedBigInteger('patient_id')->nullable();
                $table->timestamps();
            });
        }
    }
    

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('fk_doctor');
            $table->dropForeign('fk_hospital');
            $table->dropForeign('fk_patient');
        });
    }
};
