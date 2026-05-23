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
        Schema::create('user_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            
            $table->foreignId('platform_id')->nullable()->constrained('platforms')->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            
            $table->string('status')->default('backlog'); 
            $table->integer('playtime_minutes')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->integer('personal_rating')->default(0);
            $table->timestamp('last_achievement_sync')->nullable();
            
            $table->timestamps();
            
            $table->unique(['user_id', 'game_id', 'platform_id', 'store_id'], 'user_game_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_games');
    }
};
