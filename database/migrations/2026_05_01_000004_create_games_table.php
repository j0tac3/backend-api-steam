<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id(); // 👉 Solo tu ID interno manda
            
            $table->unsignedBigInteger('parent_id')->nullable();
            
            $table->string('category')->default('main_game');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->date('release_date')->nullable();
            $table->decimal('rating', 5, 2)->nullable();
            $table->decimal('igdb_user_rating', 5, 2)->nullable();
            $table->integer('metacritic_score')->nullable();
            
            // Datos Estructurados (JSON)
            $table->json('localized_data')->nullable(); 
            $table->json('supported_languages')->nullable(); 
            
            $table->timestamps();
        });

        Schema::table('games', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('games')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Si hacemos rollback, Laravel es inteligente y borra la tabla con sus relaciones
        Schema::dropIfExists('games');
    }
};