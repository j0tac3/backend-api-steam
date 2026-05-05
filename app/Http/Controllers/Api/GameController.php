<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use App\Services\IgdbService;
use App\Services\TranslationService;

class GameController extends Controller
{
    protected $igdbService;
    protected $translator; // 🚀 Añadimos la propiedad

    // 🚀 2. Inyectamos también el TranslationService
    public function __construct(IgdbService $igdbService, TranslationService $translator)
    {
        $this->igdbService = $igdbService;
        $this->translator = $translator;
    }

    // --- 1. Obtener la biblioteca del usuario logueado ---
    public function index(Request $request) 
    {
        return $request->user()->games()
                               ->orderBy('created_at', 'desc')
                               ->get();
    }

    // --- 2. Guardar un juego (Desde la "Aduana") ---
    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => 'required|string',
            'source'      => 'required|string|in:igdb,steam',
            'title'       => 'required|string',
            'cover_url'   => 'nullable|string',
            'status'      => 'required|string|in:pendiente,jugando,completado,abandonado',
        ]);

        $existe = $request->user()->games()
                                  ->where('external_id', $validated['external_id'])
                                  ->where('source', $validated['source'])
                                  ->first();

        if ($existe) {
            return response()->json(['message' => 'El juego ya está en tu colección'], 422);
        }

        $game = $request->user()->games()->create([
            'external_id' => $validated['external_id'],
            'source'      => $validated['source'],
            'title'       => $validated['title'],
            'cover_url'   => $validated['cover_url'],
            'status'      => $validated['status'],
        ]);

        return response()->json($game, 201);
    }

    // En app/Http/Controllers/GameController.php
    public function update(Request $request, $id)
    {
        // 1. Buscamos el juego en la base de datos que pertenezca al usuario autenticado
        $game = \App\Models\Game::where('user_id', auth()->id())->findOrFail($id);

        // 2. Validamos los datos que nos manda Angular desde el Modal V2
        $validatedData = $request->validate([
            'platform' => 'nullable|string',
            'status' => 'required|string|in:pendiente,jugando,completado,abandonado',
            // Puedes añadir más campos aquí si en el futuro permites editar nota, etc.
        ]);

        // 3. Actualizamos y guardamos
        $game->update($validatedData);

        // 4. Devolvemos el juego actualizado
        return response()->json($game);
    }

    // --- 3. Actualizar el Estado (Para el Kanban/Tablero) ---
    public function updateStatus(Request $request, $id)
    {
        $game = $request->user()->games()->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pendiente,jugando,completado,abandonado'
        ]);

        $game->update(['status' => $validated['status']]);

        return response()->json($game);
    }

    // --- 4. Actualizar el Diario (Desde el Modal de detalles) ---
    public function updateDiario(Request $request, $id) 
    {
        $game = $request->user()->games()->findOrFail($id);

        $game->update([
            'notes'           => $request->notes,
            'personal_rating' => $request->personal_rating,
            'start_date'      => $request->start_date,
            'platform'        => $request->platform,
        ]);

        return response()->json([
            'message' => 'Diario actualizado correctamente',
            'game'    => $game
        ]);
    }

    // --- 5. Alternar Favorito ---
    public function toggleFavorite(Request $request, $id)
    {
        $game = $request->user()->games()->findOrFail($id);
        $game->is_favorite = !$game->is_favorite;
        $game->save();

        return response()->json([
            'message'     => 'Favorito actualizado',
            'is_favorite' => $game->is_favorite
        ]);
    }

    // --- 6. Eliminar Juego ---
    public function destroy(Request $request, $id)
    {
        $game = $request->user()->games()->findOrFail($id);
        $game->delete();
        return response()->json(['message' => 'Juego eliminado de la colección']);
    }

    // --- 7. Buscador Centralizado ---
    public function search(Request $request)
    {
        $query = $request->query('q');
        if (!$query) {
            return response()->json([]);
        }

        $rawGames = $this->igdbService->searchGames($query);

        if (!is_array($rawGames) || isset($rawGames['message'])) {
            return response()->json([]);
        }

        $cleanGames = collect($rawGames)->map(function ($game) {
            $coverUrl = null;
            if (isset($game['cover']['url'])) {
                $coverUrl = str_replace('t_thumb', 't_cover_big', $game['cover']['url']);
                $coverUrl = 'https:' . $coverUrl;
            }
            return [
                'external_id' => (string) $game['id'],
                'title'       => $game['name'],
                'cover_url'   => $coverUrl,
                'source'      => 'igdb',
            ];
        });

        return response()->json($cleanGames);
    }

    // --- E. DETALLES COMPLETOS (Para el Modal V2) ---
    public function getDetails(Request $request, $id)
    {
        $source = $request->query('source', 'igdb');

        if ($source === 'igdb') {
            $response = $this->igdbService->getGameDetails($id);

            // 🚀 IGDB siempre devuelve un array. Sacamos el primer elemento [0]
            $rawDetails = is_array($response) && count($response) > 0 ? $response[0] : null;

            if (!$rawDetails) {
                return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
            }

            // 🚀 Mapeo corregido con las variables exactas de IGDB
            $mappedDetails = [
                'id' => $rawDetails['id'] ?? $id,
                'name' => $rawDetails['name'] ?? 'Desconocido',
                
                'coverUrl' => isset($rawDetails['cover']['image_id']) 
                    ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $rawDetails['cover']['image_id'] . '.jpg'
                    : null,
                    
                'releaseDate' => $rawDetails['first_release_date'] ?? null,
                
                // 🚀 3. Traducimos el texto antes de mandarlo a Angular
                'summary' => isset($rawDetails['summary']) 
                    ? $this->translator->translateToSpanish($rawDetails['summary']) 
                    : null,
                
                'criticScore' => isset($rawDetails['aggregated_rating']) ? round($rawDetails['aggregated_rating']) : null,
                
                'userScore' => isset($rawDetails['rating']) ? round($rawDetails['rating']) : null,
                'userScoreCount' => $rawDetails['rating_count'] ?? null,
                
                'genres' => collect($rawDetails['genres'] ?? [])->pluck('name')->toArray(),
                'platforms' => collect($rawDetails['platforms'] ?? [])->pluck('name')->toArray(),
                'involvedCompanies' => collect($rawDetails['involved_companies'] ?? [])->pluck('company.name')->toArray(),
                'gameModes' => collect($rawDetails['game_modes'] ?? [])->pluck('name')->toArray(),
                
                'screenshots' => collect($rawDetails['screenshots'] ?? [])->map(function($shot) {
                    return 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/' . $shot['image_id'] . '.jpg';
                })->toArray(),
            ];

            return response()->json($mappedDetails);
        }

        return response()->json(['message' => 'Fuente no soportada'], 404);
    }
}