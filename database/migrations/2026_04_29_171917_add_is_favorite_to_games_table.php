<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('games', function (Blueprint $table) {
            // Añadimos la columna booleana, por defecto será false (corazón vacío)
            $table->boolean('is_favorite')->default(false)->after('status'); 
        });
    }

    public function down()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};
