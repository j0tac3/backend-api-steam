<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameSearchController; 
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Artisan;

// --- RUTAS PÚBLICAS (Sin Token) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// --- RUTAS PRIVADAS (Requieren Token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::put('/games/{id}', [GameController::class, 'update']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    Route::get('/steam/search', [GameController::class, 'search']);
    Route::get('/steam/details/{id}', [GameController::class, 'getSteamDetails']);

    Route::get('/search-igdb', [GameSearchController::class, 'search']);
    Route::get('/igdb-details/{id}', [GameSearchController::class, 'getIgdbDetails']);
    Route::get('/igdb/buscar', [GameSearchController::class, 'buscarEnIgdb']);
    Route::patch('/games/{id}/diario', [GameController::class, 'updateDiario']);

    Route::post('/logout', [AuthController::class, 'logout']);
});


/* use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Route::get('/arreglar-bd', function () {
    try {
        // 1. Intentamos borrar la restricción única del AppID por SQL puro
        // El nombre suele ser 'games_steam_appid_unique'
        DB::statement('ALTER TABLE games DROP CONSTRAINT IF EXISTS games_steam_appid_unique');

        // 2. Opcional: Borramos también el índice si existe
        DB::statement('DROP INDEX IF EXISTS games_steam_appid_unique');

        return response()->json([
            'mensaje' => '¡Restricción eliminada con éxito!',
            'info' => 'Ahora la base de datos debería permitir el mismo juego para distintos usuarios.'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error al ejecutar SQL',
            'mensaje' => $e->getMessage()
        ], 500);
    }
}); */


// ... tus otras rutas ...

Route::get('/ejecutar-migracion-sistema', function () {
    try {
        // Ejecuta php artisan migrate --force
        Artisan::call('migrate', ['--force' => true]);
        
        return "✅ Migración completada con éxito:<br><pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "❌ Error al migrar: " . $e->getMessage();
    }
});