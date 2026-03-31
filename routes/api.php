<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\AuthController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS (Sin Token)
|--------------------------------------------------------------------------
*/
// Estas rutas son las "puertas" de entrada. No piden token.
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
/*
|--------------------------------------------------------------------------
| RUTAS PRIVADAS (Requieren Bearer Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Datos del usuario identificado
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    // Gestión de la Biblioteca de juegos
    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    // Buscador de Steam
    Route::get('/steam/search', [GameController::class, 'search']);
    Route::get('/steam/details/{id}', [GameController::class, 'getSteamDetails']);
});