<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameSearchController; 
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RadarController;
use Illuminate\Support\Facades\Artisan;

// --- RUTAS PÚBLICAS (Sin Token) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/radar/ofertas', [RadarController::class, 'getSteamDeals']);

// --- RUTAS PRIVADAS (Requieren Token) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/games', [GameController::class, 'index']);
    Route::post('/games', [GameController::class, 'store']);
    Route::put('/games/{id}', [GameController::class, 'update']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    Route::patch('/games/{id}/diario', [GameController::class, 'updateDiario']);
    Route::patch('/games/{id}/favorite', [GameController::class, 'toggleFavorite']);

    Route::get('/steam/search', [GameController::class, 'search']);
    Route::get('/steam/details/{id}', [GameController::class, 'getSteamDetails']);

    Route::get('/search-igdb', [GameSearchController::class, 'search']);
    Route::get('/igdb-details/{id}', [GameSearchController::class, 'getIgdbDetails']);
    Route::get('/igdb/buscar', [GameSearchController::class, 'buscarEnIgdb']);

    Route::get('/buscar-en-igdb', [GameSearchController::class, 'buscarEnIGDB']);
    Route::get('/igdb-details/{id}', [GameSearchController::class, 'getIgdbDetails']);

    Route::post('/logout', [AuthController::class, 'logout']);
});