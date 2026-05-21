<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            
            // 🚀 ESTA ES LA LÍNEA QUE FALTABA
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); 
            
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            
            $table->text('content');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->text('notes')->nullable();
        });
        Schema::dropIfExists('journal_entries');
    }
};