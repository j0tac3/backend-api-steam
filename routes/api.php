<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RadarController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ShareController;

// --- RUTAS PÚBLICAS (Sin Token) ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Mantenemos el Radar si aún lo usas para ofertas globales
Route::get('/radar/ofertas', [RadarController::class, 'getSteamDeals']);

// Rutas Públicas (Sin necesidad de login)
Route::get('/public/profile/{username}', [GameController::class, 'getPublicProfile']);
Route::get('/share/{username}', [ShareController::class, 'handle']);
// 🚀 Nueva ruta para la imagen dinámica
Route::get('/share/{username}/image', [ShareController::class, 'generateImage']);


// --- RUTAS PRIVADAS (Requieren Token) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Perfil de usuario
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::patch('/user/preferences', [AuthController::class, 'updatePreferences']);

    // Gestión de Biblioteca (GameController)
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/stats', [GameController::class, 'getLibraryStats']);
    Route::get('/games/search', [GameController::class, 'search']);
    Route::get('/games/details/{id}', [GameController::class, 'getDetails']);
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    
    // Actualizaciones específicas (PATCH es más semántico para cambios parciales)
    Route::put('/games/{id}', [GameController::class, 'update']);
    Route::patch('/games/{id}/status', [GameController::class, 'updateStatus']);
    Route::patch('/games/{id}/diario', [GameController::class, 'updateDiario']);
    Route::patch('/games/{id}/favorite', [GameController::class, 'toggleFavorite']);

    // --- RUTAS DEL DIARIO (JOURNAL) ---
    Route::get('/games/{gameId}/journal', [JournalEntryController::class, 'index']);
    Route::post('/games/{gameId}/journal', [JournalEntryController::class, 'store']);
    Route::patch('/journal/{id}', [JournalEntryController::class, 'update']);
    Route::delete('/journal/{id}', [JournalEntryController::class, 'destroy']);

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ⚠️ ATENCIÓN: Borra o protege esta ruta tras usarla
Route::get('/ejecutar-migracion-secreta', function () {
    try {
        // Ejecuta las migraciones pendientes
        // '--force' es necesario si el servidor está en modo 'production'
        Artisan::call('migrate', ['--force' => true]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Migraciones ejecutadas correctamente',
            'output' => Artisan::output()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});