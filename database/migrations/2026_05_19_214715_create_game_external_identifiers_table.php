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
        Schema::create('game_external_identifiers', function (Blueprint $table) {
            $table->id();
            
            // Relación con tu tabla limpia de juegos
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            
            // Proveedor: 'steam', 'igdb', 'epic', 'gog', 'playstation', etc.
            $table->string('provider'); 
            
            // El ID de esa tienda (lo ponemos string por si alguna tienda usa alfanuméricos)
            $table->string('external_id'); 
            
            $table->timestamps();

            // 🛡️ ÍNDICE ÚNICO: Impide radicalmente que un mismo ID de Steam/IGDB se asigne a dos juegos distintos
            $table->unique(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_external_identifiers');
    }
};
