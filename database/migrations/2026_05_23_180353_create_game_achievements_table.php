<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('game_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            
            // El ID interno que nos da Steam (ej: 'ACH_WIN_1')
            $table->string('api_name'); 
            
            // Datos visuales
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon_url')->nullable();      // Icono a color
            $table->string('icon_gray_url')->nullable(); // Icono bloqueado
            $table->boolean('is_hidden')->default(false); // ¿Es un logro secreto?
            
            $table->timestamps();

            // Evitamos duplicados: Un juego no puede tener dos logros con el mismo ID interno
            $table->unique(['game_id', 'api_name']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('game_achievements');
    }
};