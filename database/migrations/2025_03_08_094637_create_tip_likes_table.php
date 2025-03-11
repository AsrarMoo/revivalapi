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
        Schema::create('tip_likes', function (Blueprint $table) {
            $table->increments('like_id');
            $table->unsignedInteger('tip_id');
            $table->unsignedInteger('user_id')->index('fk_like_user');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tip_id', 'user_id'], 'tip_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tip_likes');
    }
};
