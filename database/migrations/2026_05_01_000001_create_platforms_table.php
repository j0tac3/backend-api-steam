<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            // igdb_id debe ser el identificador numérico real de la API
            $table->unsignedBigInteger('igdb_id')->nullable()->unique();
            $table->string('name');
            // slug para las URLs y búsquedas limpias (ej: "playstation-4")
            $table->string('slug')->unique();
            // family para el Frontend y los iconos (ej: "playstation")
            $table->string('family')->default('other');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
