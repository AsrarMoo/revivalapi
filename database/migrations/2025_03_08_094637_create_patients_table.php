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
        Schema::create('patients', function (Blueprint $table) {
            $table->increments('patient_id');
            $table->unsignedInteger('user_id')->nullable()->unique('user_id');
            $table->string('patient_name');
            $table->integer('patient_age');
            $table->date('patient_birthdate');
            $table->enum('patient_blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
            $table->string('patient_phone', 15)->unique('patient_phone');
            $table->string('patient_address')->nullable();
            $table->enum('patient_status', ['single', 'married']);
            $table->decimal('patient_height', 5, 2)->nullable(); // أضفنا رقم عشري
            $table->decimal('patient_weight', 5, 2)->nullable(); // أضفنا رقم عشري
            $table->string('patient_nationality', 100)->nullable();
            $table->enum('patient_gender', ['male', 'female']);
            $table->string('patient_image')->nullable();
            $table->text('patient_notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // 🔴 إضافة المفتاح الأجنبي وربطه بالمستخدم مع الحذف التلقائي
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
