<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * تشغيل الميغريشن.
     */
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->increments('hospital_id');
            $table->unsignedInteger('user_id')->nullable()->unique();
            $table->string('hospital_name');
            $table->string('hospital_address');
            $table->string('hospital_phone', 15)->unique();
            $table->string('hospital_image')->nullable();
            $table->timestamps();
            Schema::table('hospitals', function (Blueprint $table) {
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
            });
           
                        
        });
    }

    /**
     * إلغاء الميغريشن.
     */
    public function down(): void
    {
        Schema::dropIfExists('hospitals');
    }
};
