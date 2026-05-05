<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
           $table->id();

        // --- RELACIÓN CON EL USUARIO ---
        // Crea la columna user_id y la conecta con la tabla users. 
        // Si borras un usuario, se borran sus juegos (onDelete cascade).
        $table->foreignId('user_id')->constrained()->onDelete('cascade');

        // --- IDENTIFICACIÓN ---
        $table->string('external_id');          
        $table->string('source')->default('igdb'); 
        $table->string('title');                
        
        // --- CONTENIDO ---
        // Usamos text para la URL porque las de IGDB pueden ser largas.
        $table->text('cover_url')->nullable();  
        
        // Restringimos los estados posibles para evitar errores
        $table->enum('status', ['pendiente', 'jugando', 'completado', 'abandonado'])
              ->default('pendiente'); 

        // --- PERSONALIZACIÓN (DIARIO) ---
        $table->text('notes')->nullable();      
        
        // Rating: Restringimos de 0 a 100 (o 0 a 10) según prefieras[cite: 1]
        $table->unsignedTinyInteger('personal_rating')->default(0); 
        
        $table->date('start_date')->nullable(); 
        $table->string('platform')->nullable(); 
        $table->boolean('is_favorite')->default(false); 

        $table->timestamps();

        // OPCIONAL: Evita que un mismo usuario guarde el mismo juego dos veces
        $table->unique(['user_id', 'external_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};