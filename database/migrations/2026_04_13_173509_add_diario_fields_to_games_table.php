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
        Schema::table('games', function (Blueprint $table) {
            // Añadimos campos para la funcionalidad de Diario/Notas
            $table->text('notes')->nullable();
            $table->integer('personal_rating')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
    }

    public function down()
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['notes', 'personal_rating', 'start_date', 'end_date']);
        });
    }
};
