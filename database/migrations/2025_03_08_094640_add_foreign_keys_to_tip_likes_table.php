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
        Schema::table('tip_likes', function (Blueprint $table) {
            $table->foreign(['tip_id'], 'fk_like_tip')->references(['tip_id'])->on('medical_tips')->onUpdate('restrict')->onDelete('cascade');
            $table->foreign(['user_id'], 'fk_like_user')->references(['user_id'])->on('users')->onUpdate('restrict')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tip_likes', function (Blueprint $table) {
            $table->dropForeign('fk_like_tip');
            $table->dropForeign('fk_like_user');
        });
    }
};
