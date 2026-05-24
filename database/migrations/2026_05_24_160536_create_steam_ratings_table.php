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
    Schema::create('steam_ratings', function (Blueprint $table) {
        $table->id();
        // 🚀 Relación directa con el juego. Si se borra el juego, se borra su nota.
        $table->foreignId('game_id')->constrained()->cascadeOnDelete();
        $table->integer('score'); // Ej: 95
        $table->string('summary'); // Ej: "Extremadamente positivas"
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('steam_ratings');
    }
};
