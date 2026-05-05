<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RadarController;

// --- RUTAS PÚBLICAS (Sin Token) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Mantenemos el Radar si aún lo usas para ofertas globales
Route::get('/radar/ofertas', [RadarController::class, 'getSteamDeals']);

// --- RUTAS PRIVADAS (Requieren Token) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Perfil de usuario
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Gestión de Biblioteca (GameController)
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/search', [GameController::class, 'search']);
    Route::get('/games/details/{id}', [GameController::class, 'getDetails']);
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    
    // Actualizaciones específicas (PATCH es más semántico para cambios parciales)
    Route::put('/games/{id}', [GameController::class, 'update']);
    Route::patch('/games/{id}/status', [GameController::class, 'updateStatus']);
    Route::patch('/games/{id}/diario', [GameController::class, 'updateDiario']);
    Route::patch('/games/{id}/favorite', [GameController::class, 'toggleFavorite']);

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);
});