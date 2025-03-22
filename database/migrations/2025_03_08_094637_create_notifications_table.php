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
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('notification_id');

            // 🔹 تحديد المستخدم المستلم للإشعار
            $table->unsignedBigInteger('user_id')->nullable()->index('fk_notification_user');
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');

            // 🔹 تحديد من أنشأ الإشعار
            $table->unsignedBigInteger('created_by')->nullable()->index('fk_notification_creator');
            $table->foreign('created_by')->references('user_id')->on('users')->onDelete('cascade');

            $table->string('title');
            $table->text('message');
            $table->enum('type', ['booking', 'ambulance','Rejected', 'general']);
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate(); // 🔹 تحديث تلقائي عند التعديل
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
