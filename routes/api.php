<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RadarController;
use App\Http\Controllers\Api\JournalEntryController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\DiscoverController;
use Illuminate\Support\Facades\Schedule;


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
    /* Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/stats', [GameController::class, 'getLibraryStats']);
    Route::get('/games/search', [GameController::class, 'search']);
    Route::get('/games/details/{id}', [GameController::class, 'getDetails']);
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']); */

    // Gestión de Biblioteca (GameController)
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/stats', [GameController::class, 'getLibraryStats']);
    Route::get('/games/search', [GameController::class, 'search']);
    
    // 🚀 Aquí cambiamos el antiguo getDetails por el nuevo show que usa el slug
    Route::get('/games/details/{slug}', [GameController::class, 'show']); 
    
    Route::post('/games', [GameController::class, 'store']);
    Route::delete('/games/{id}', [GameController::class, 'destroy']);
    
    // 🔄 Actualizaciones específicas (Tabla Pivote)
    Route::patch('/games/{id}/status', [GameController::class, 'updateStatus']);
    Route::patch('/games/{id}/favorite', [GameController::class, 'toggleFavorite']);

    // 🌍 Ruta para la Pantalla Descubrir
    Route::get('discover/feed', [DiscoverController::class, 'getDiscoverFeed']);

    Route::post('/steam/sync-prepare', [GameController::class, 'prepareSteamSync']);
    Route::post('/steam/sync-single', [GameController::class, 'syncSingleSteamGame']);

    
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

    // 📊 Ruta para el Dashboard Analítico
    Route::get('/stats/advanced', [StatsController::class, 'getAdvancedStats']);

    Schedule::command('games:fetch-metadata')->everyMinute()->withoutOverlapping();

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ⚠️ ATENCIÓN: Borra o protege esta ruta tras usarla
Route::get('/ejecutar-migracion-secreta', function () {
    try {
        // Ejecuta las migraciones pendientes
        // '--force' es necesario si el servidor está en modo 'production'
        Artisan::call('migrate:fresh', [
            '--force' => true
        ]);
        
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


Route::get('/worker/run', function (Request $request) {
    // 1. Candado de seguridad
    $secret = env('WORKER_SECRET_TOKEN');
    
    if (!$secret || $request->query('token') !== $secret) {
        return response()->json(['error' => 'Acceso denegado. Candado cerrado.'], 401);
    }

    try {
        // 2. Ejecutar tu comando de metadatos (Asegúrate de que la firma es correcta)
        Artisan::call('games:fetch-metadata'); 

        return response()->json([
            'status' => 'Trabajo completado',
            'output' => Artisan::output() // Te mostrará en texto cuántos juegos procesó
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Hubo un error al ejecutar el comando',
            'message' => $e->getMessage()
        ], 500);
    }
});