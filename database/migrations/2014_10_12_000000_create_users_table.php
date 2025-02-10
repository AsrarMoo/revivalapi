<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration

{
    /**
     * تشغيل الميجريشين لإنشاء جدول المستخدمين.
     *
     * @return void
     */
    public function up()
{
    if (!Schema::hasTable('users')) {
        Schema::create('users', function (Blueprint $table) {
            $table->id();  // primary key
            $table->string('name');  // اسم المستخدم
            $table->string('password');  // كلمة المرور
            $table->enum('user_type', ['Admin', 'Doctor', 'Patient', 'Hospital']);  // نوع المستخدم
            $table->boolean('is_active')->default(true);  // حالة الحساب
            $table->timestamps();  // created_at, updated_at
        });
    }
}


    /**
     * التراجع عن الميجريشين.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
