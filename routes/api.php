<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\AuthController; // 👈 Asegúrate de tener este controlador

// --- RUTAS PÚBLICAS (Sin candado) ---
// Esta es la ruta que Angular está buscando y no encuentra (404)
Route::post('/login', [AuthController::class, 'login']); 
Route::post('/register', [AuthController::class, 'register']);


// --- RUTAS PRIVADAS (Requieren estar logueado) ---
Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/steam/search', [GameController::class, 'search']);
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    Route::get('/steam/details/{id}', [GameController::class, 'getSteamDetails']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});