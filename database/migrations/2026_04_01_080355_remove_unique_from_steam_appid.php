<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // 1. Borramos la regla antigua que te está bloqueando
            $table->dropUnique('games_steam_appid_unique');
            
            // 2. Creamos la regla correcta: Un mismo usuario no puede tener el mismo juego 2 veces
            $table->unique(['user_id', 'steam_appid']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'steam_appid']);
            $table->unique('steam_appid');
        });
    }
};