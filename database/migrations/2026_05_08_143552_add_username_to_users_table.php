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
        Schema::table('users', function (Blueprint $table) {
            // Un username único e indexado para búsquedas rápidas en la URL
            $table->string('username')->unique()->nullable()->after('email')->index();
            // Por seguridad, los perfiles son privados por defecto
            $table->boolean('is_public')->default(false)->after('username');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
