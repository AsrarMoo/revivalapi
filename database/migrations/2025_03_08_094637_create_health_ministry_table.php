<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('health_ministry', function (Blueprint $table) {
            $table->id('health_ministry_id'); // معرف الوزارة
            $table->string('name'); // اسم الوزارة
            $table->string('phone', 15); // رقم الهاتف
            $table->unsignedBigInteger('user_id')->nullable()->index(); // معرف المستخدم
            $table->timestamps();

            // مفتاح أجنبي لربط الوزارة بالمستخدم
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('health_ministry');
    }
};
