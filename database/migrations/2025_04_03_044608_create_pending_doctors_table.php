<?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;
        
        class CreatePendingDoctorsTable extends Migration
        {
            public function up()
            {
                Schema::create('pending_doctors', function (Blueprint $table) {
                    $table->id();
                    $table->string('doctor_name');
                    $table->string('email')->unique();
                    $table->integer('password')->unique();
                    $table->string('phone')->unique();
                    $table->enum('gender', ['Male', 'Female']);
                    $table->foreignId('specialty_id')->constrained('specialties');
                    $table->string('qualification');
                    $table->integer('experience');
                    $table->text('bio')->nullable();
                    $table->string('license_path');
                    $table->string('certificate_path')->nullable();
                    $table->string('image_path')->nullable();
                    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                    $table->json('metadata')->nullable();
                    $table->timestamps();
                });
            }
        
            public function down()
            {
                Schema::dropIfExists('pending_doctors');
            }
        }

    /**
     * Reverse the migrations.
     */
   

