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
    Schema::create('pending_metadata_queues', function (Blueprint $table) {
        $table->id();
        // Relación con tu tabla principal de juegos
        $table->foreignId('game_id')->constrained()->cascadeOnDelete();
        // ID de la tienda (Steam, IGDB...)
        $table->string('external_id');
        // Plataforma para saber a qué API llamar
        $table->string('platform'); 
        // Estado del proceso
        $table->string('status')->default('pending'); 
        // Contador por si la API da error
        $table->integer('attempts')->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_metadata_queues');
    }
};
