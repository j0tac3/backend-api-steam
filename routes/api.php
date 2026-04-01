<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController; // <-- Fíjate: Api
use App\Http\Controllers\Api\AuthController; // <-- Fíjate: Api
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

    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::get('/arreglar-bd', function () {
    try {
        // Esto obliga a Laravel a ejecutar las migraciones pendientes
        Artisan::call('migrate', ['--force' => true]);
        return response()->json([
            'mensaje' => '¡Base de datos actualizada con éxito!',
            'detalles' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Algo falló',
            'mensaje' => $e->getMessage()
        ], 500);
    }
});