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
    Schema::create('patients', function (Blueprint $table) {
        $table->id('patient_id');
        $table->unsignedBigInteger('user_id')->nullable()->unique(); // ✅ ربطه بالمستخدم ولكن يمكن أن يكون فارغًا مؤقتًا
        $table->string('patient_name'); // ✅ مطلوب عند إنشاء الحساب
        $table->string('patient_phone', 15)->unique(); // ✅ مطلوب عند إنشاء الحساب
        $table->string('patient_password'); // ✅ سيتم تخزينها في `users` ولكن موجودة هنا للمراجعة فقط
        $table->integer('patient_age')->nullable();
        $table->date('patient_birthdate')->nullable();
        $table->enum('patient_blood_type', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
        $table->string('patient_address')->nullable();
        $table->enum('patient_status', ['single', 'married'])->nullable();
        $table->decimal('patient_height', 5, 2)->nullable();
        $table->decimal('patient_weight', 5, 2)->nullable();
        $table->string('patient_nationality')->nullable();
        $table->enum('patient_gender', ['male', 'female'])->nullable();
        $table->string('patient_image')->nullable();
        $table->text('patient_notes')->nullable();
        $table->timestamps();

        // 🔴 المفتاح الأجنبي، بحيث يمكن حذف المريض عند حذف المستخدم المرتبط به
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
