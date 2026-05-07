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
            
            // Relación con tu tabla de juegos guardados 
            // (Asumo que tu tabla se llama 'games', ajusta si es 'user_games')
            $table->foreignId('game_id')
                  ->constrained('games')
                  ->onDelete('cascade'); 
                  
            $table->text('content');
            $table->boolean('is_featured')->default(false); // Para la estrella
            $table->timestamps();
        });

        // Opcional pero recomendado: Borramos la columna antigua 'notes' para mantener la BD limpia
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('notes');
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