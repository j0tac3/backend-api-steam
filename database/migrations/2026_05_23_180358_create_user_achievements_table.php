<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('game_achievement_id')->constrained('game_achievements')->onDelete('cascade');
            
            // Cuándo sacaste el logro
            $table->timestamp('unlocked_at')->nullable();
            
            $table->timestamps();

            // Evitamos duplicados: Un usuario no puede sacarse el mismo logro dos veces
            $table->unique(['user_id', 'game_achievement_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_achievements');
    }
};