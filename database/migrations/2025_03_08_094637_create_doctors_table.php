<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تشغيل الهجرة.
     */
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->increments('doctor_id');
            $table->unsignedInteger('user_id')->nullable()->unique('user_id');
            $table->string('doctor_name');
            $table->unsignedInteger('specialty_id');
            $table->string('doctor_qualification');
            $table->integer('doctor_experience');
            $table->string('doctor_phone', 15)->unique();
            $table->text('doctor_bio')->nullable();
            $table->string('doctor_image')->nullable();
            $table->string('attachment')->nullable(); // حقل المرفقات
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->useCurrent();

            // 🔹 إضافة المفتاح الأجنبي مع حذف المستخدم عند حذف الطبيب
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * التراجع عن الهجرة.
     */
    public function down(): void
    {
        Schema::dropIfExists('doctors');
    }
};
