<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    // --- 1. Obtener mi biblioteca ---
    public function index()
    {
        return response()->json(Game::orderBy('created_at', 'desc')->get());
    }

    // --- 2. Guardar un juego ---
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'steam_appid' => 'required|string',
            'image_url' => 'nullable|string',
        ]);
        // Evitamos que Laravel explote si el juego ya existe
        $existe = Game::where('steam_appid', $validated['steam_appid'])->first();
        if ($existe) {
            return response()->json(['message' => 'El juego ya está en tu biblioteca'], 422);
        }
        $game = Game::create([
            'title' => $validated['title'],
            'steam_appid' => $validated['steam_appid'],
            'image_url' => $validated['image_url'],
            'status' => 'pendiente'
        ]);
        return response()->json($game, 201);
    }

    // --- 3. Buscador Proxy de Steam ---
    public function search(Request $request)
    {
        $query = $request->query('q');
        if (!$query) {
            return response()->json([]);
        }
        try {
            // Usamos la API de búsqueda de la tienda de Steam (es mucho más ligera)
            $response = Http::withoutVerifying()
                ->timeout(5)
                ->get("https://store.steampowered.com/api/storesearch/", [
                    'term' => $query,
                    'l'    => 'spanish',
                    'cc'   => 'ES'
                ]);
            if ($response->successful()) {
                $data = $response->json();
                // Mapeamos los resultados para que coincidan con lo que espera tu Angular
                $juegos = collect($data['items'] ?? [])->map(function($item) {
                    return [
                        'appid' => (string) $item['id'], // Steam Store usa 'id'
                        'name'  => $item['name'],
                        'logo'  => $item['tiny_image']  // Ya nos da la miniatura directamente
                    ];
                });
                return response()->json($juegos);
            }
            return response()->json(['error' => 'Steam Store no responde'], 502);
        } catch (\Exception $e) {
            // Si hay un error de conexión real, lo vemos aquí
            return response()->json([
                'error' => 'Fallo de red local',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    // app/Http/Controllers/Api/GameController.php
    public function destroy($id)
    {
        $game = Game::find($id);
        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado'], 404);
        }
        $game->delete();
        return response()->json(['message' => 'Juego eliminado correctamente']);
    }

    // app/Http/Controllers/Api/GameController.php
    public function update(Request $request, $id)
    {
        $game = Game::find($id);
        if (!$game) {
            return response()->json(['message' => 'Juego no encontrado'], 404);
        }
        $validated = $request->validate([
            'status' => 'required|string|in:pendiente,jugando,completado'
        ]);
        $game->update(['status' => $validated['status']]);
        return response()->json($game);
    }

    // app/Http/Controllers/Api/GameController.php

    public function getSteamDetails($appid)
    {
        try {
            $response = Http::withoutVerifying()
                ->get("https://store.steampowered.com/api/appdetails", [
                    'appids' => $appid,
                    'l' => 'spanish' // Queremos la descripción en español
                ]);

            if ($response->successful() && isset($response->json()[$appid]['data'])) {
                return response()->json($response->json()[$appid]['data']);
            }

            return response()->json(['error' => 'No se encontraron detalles'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}