<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GameController; // ✅ Correcto si la carpeta existe

/* Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
}); */

// Estas rutas requieren que el usuario esté logueado (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // --- RUTAS DE STEAM (Buscador) ---
    // URL en Angular: http://localhost:8000/api/steam/search
    Route::get('/steam/search', [GameController::class, 'search']);

    // --- RUTAS DE TU BIBLIOTECA (Base de datos) ---
    // Obtener todos tus juegos guardados
    Route::get('/games', [GameController::class, 'index']);
    
    // Guardar un nuevo juego en la biblioteca
    Route::post('/games', [GameController::class, 'store']);
    
    // Eliminar un juego de la biblioteca
    Route::delete('/games/{id}', [GameController::class, 'destroy']);

    Route::get('/steam/details/{id}', [GameController::class, 'getSteamDetails']);
    // Ruta para obtener los datos del usuario logueado (útil para el perfil)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
});