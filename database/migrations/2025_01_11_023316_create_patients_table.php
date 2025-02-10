<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatientsTable extends Migration
{
    public function up()
    {
        // تحقق إذا كانت الجدول لا وجود لها أولاً
        if (!Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table) {
                
                // عمود id سيكون المفتاح الرئيسي
                $table->id();  // لا حاجة لتحديد اسم المفتاح هنا، Laravel سيستخدم id بشكل افتراضي.
                $table->string('patient_name');
                $table->integer('patient_age')->unsigned();
                $table->enum('patient_gender', ['Male', 'Female']);
                $table->date('patient_BD');
                $table->enum('patient_status', ['Single', 'Married']);
                $table->decimal('patient_height', 5, 2);
                $table->decimal('patient_weight', 5, 2);
                $table->string('patient_phone', 15);  // إذا كنت تتوقع رقم هاتف ثابت بحد أقصى 15 رقمًا
                $table->string('patient_email')->nullable();
                $table->string('patient_nationality');
                $table->enum('patient_bloodType', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']);
                $table->text('patient_address');
                $table->text('patient_notes')->nullable();
                $table->string('patient_image')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('patients');
    }
}
