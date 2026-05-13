<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\Request;
use App\Services\IgdbService;
use App\Services\TranslationService;
use App\Services\HowLongToBeatService;

class GameController extends Controller
{
    protected $igdbService;
    protected $translator;
    protected $hltbService;

    public function __construct(IgdbService $igdbService, TranslationService $translator, HowLongToBeatService $hltbService)
    {
        $this->igdbService = $igdbService;
        $this->translator = $translator;
        $this->hltbService = $hltbService;
    }

    public function index(Request $request) 
    {
        $query = $request->user()->games()
            
            // 🚀 FASE 1: Subconsultas ligeras para los "Chivatos" visuales
            ->withExists('journalEntries as has_notes')
            ->withExists(['journalEntries as has_featured_notes' => function ($query) {
                // Usamos whereRaw para evitar que Laravel/PDO malinterprete el booleano
                $query->whereRaw('is_featured = true'); 
            }])
            
            // 1. Filtro por Estado
            ->when($request->query('status'), function ($q, $status) {
                if ($status !== 'todos') {
                    $q->where('status', $status);
                }
            })
            
            // 2. Filtro por Plataforma (Compatible con Local y Prod)
            // En el método index() de GameController.php
            ->when($request->query('platform'), function ($q, $platform) {
                if ($platform && $platform !== 'todas') {
                    // Separamos el string "PC,PS5" en un array ['PC', 'PS5']
                    $platforms = explode(',', $platform);
                    
                    $q->where(function($query) use ($platforms) {
                        foreach ($platforms as $p) {
                            // Buscamos cada plataforma con un OR
                            $query->orWhereRaw('LOWER(platform) LIKE ?', ['%' . strtolower($p) . '%']);
                        }
                    });
                }
            })
            
            // 3. Filtro por Búsqueda de Texto (Compatible con Local y Prod)
            ->when($request->query('search'), function ($q, $search) {
                // Convertimos ambos lados a minúsculas usando whereRaw
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%']);
            });

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id' => 'required|string',
            'source'      => 'required|string|in:igdb,steam',
            'title'       => 'required|string',
            'cover_url'   => 'nullable|string',
            'status'      => 'required|string|in:pendiente,jugando,completado,abandonado',
            'platform'    => 'nullable|string',
            'personal_rating' => 'nullable|integer',
            'active_platforms' => 'nullable|string',
            'notes'       => 'nullable|string',
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
            'platform'    => $validated['platform'] ?? null,
            'active_platforms' => $validated['active_platforms'] ?? null,
            'personal_rating' => $validated['personal_rating'] ?? 0,
            'notes'       => $validated['notes'] ?? null,
        ]);

        return response()->json($game, 201);
    }

    public function update(Request $request, $id)
    {
        $game = \App\Models\Game::where('user_id', auth()->id())->findOrFail($id);

        $validatedData = $request->validate([
            'platform' => 'nullable|string',
            'active_platforms' => 'nullable|string',
            'status' => 'required|string|in:pendiente,jugando,completado,abandonado',
            'notes' => 'nullable|string',
            'personal_rating' => 'nullable|integer',
        ]);

        $game->update($validatedData);

        return response()->json($game);
    }

    public function updateStatus(Request $request, $id)
    {
        $game = $request->user()->games()->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:pendiente,jugando,completado,abandonado'
        ]);

        $game->update(['status' => $validated['status']]);

        return response()->json($game);
    }

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

    public function toggleFavorite($id)
    {
        $game = \App\Models\Game::where('user_id', auth()->id())->findOrFail($id);
        // 1. Invertimos el valor actual de forma limpia
        $newStatus = !$game->is_favorite;
        // 2. WORKAROUND: Forzamos DB::raw para evitar el error 'Datatype mismatch' 
        // del driver PDO con PostgreSQL en el entorno de Render/Neon.
        $game->update([
            'is_favorite' => \Illuminate\Support\Facades\DB::raw($newStatus ? 'true' : 'false')
        ]);
        return response()->json($game->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $game = $request->user()->games()->findOrFail($id);
        $game->delete();
        return response()->json(['message' => 'Juego eliminado de la colección']);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        
        // 🚀 ATRAPAMOS LA CATEGORÍA DE LA URL (Si no viene, por defecto 'todas')
        $category = $request->query('category', 'todas');

        if (!$query) {
            return response()->json([]);
        }

        // 🚀 SE LA PASAMOS AL SERVICIO
        $rawGames = $this->igdbService->searchGames($query, $category);

        if (!is_array($rawGames) || isset($rawGames['message'])) {
            return response()->json([]);
        }

        $cleanGames = collect($rawGames)->map(function ($game) {
            if (!isset($game['id']) || !isset($game['name'])) return null;

            $coverUrl = null;
            if (isset($game['cover']['url'])) {
                $coverUrl = str_replace('t_thumb', 't_cover_big', $game['cover']['url']);
                $coverUrl = 'https:' . $coverUrl;
            }

            $releaseYear = null;
            if (isset($game['first_release_date'])) {
                $releaseYear = date('Y', $game['first_release_date']);
            }

            return [
                'external_id' => (string) $game['id'],
                'title'       => $game['name'],
                'cover_url'   => $coverUrl,
                'source'      => 'igdb',
                'category'    => $game['category'] ?? 0,
                'release_year'=> $releaseYear // New
            ];
        })->filter()->values();

        return response()->json($cleanGames);
    }

    public function getDetails(Request $request, $id)
    {
        $source = $request->query('source', 'igdb');

        if ($source === 'igdb') {
            $response = $this->igdbService->getGameDetails($id);

            $rawDetails = is_array($response) && count($response) > 0 ? $response[0] : null;

            if (!$rawDetails) {
                return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
            }

            $tiempos = $this->hltbService->getTimes($rawDetails['name']);

            $mappedDetails = [
                'id' => $rawDetails['id'] ?? $id,
                'name' => $rawDetails['name'] ?? 'Desconocido',
                
                'coverUrl' => isset($rawDetails['cover']['image_id']) 
                    ? 'https://images.igdb.com/igdb/image/upload/t_cover_big/' . $rawDetails['cover']['image_id'] . '.jpg'
                    : null,
                    
                'releaseDate' => $rawDetails['first_release_date'] ?? null,
                
                'summary' => isset($rawDetails['summary']) 
                    ? $this->translator->translateToSpanish($rawDetails['summary']) 
                    : null,
                
                'criticScore' => isset($rawDetails['aggregated_rating']) ? round($rawDetails['aggregated_rating']) : null,
                
                'userScore' => isset($rawDetails['rating']) ? round($rawDetails['rating']) : null,
                'userScoreCount' => $rawDetails['rating_count'] ?? null,
                
                'genres' => collect($rawDetails['genres'] ?? [])->pluck('name')->toArray(),
                //'platforms' => collect($rawDetails['platforms'] ?? [])->pluck('name')->toArray(),
                'platforms' => collect($rawDetails['platforms'] ?? [])->map(function($platform) {
                    return [
                        'name' => $platform['name'],
                        'family' => \App\Models\Game::mapToFamily($platform['name'])
                    ];
                })->toArray(),
                'involvedCompanies' => collect($rawDetails['involved_companies'] ?? [])->pluck('company.name')->toArray(),
                'gameModes' => collect($rawDetails['game_modes'] ?? [])->pluck('name')->toArray(),
                'time_to_beat' => $tiempos,
                'screenshots' => collect($rawDetails['screenshots'] ?? [])->map(function($shot) {
                    return 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/' . $shot['image_id'] . '.jpg';
                })->toArray(),
            ];

            return response()->json($mappedDetails);
        }

        return response()->json(['message' => 'Fuente no soportada'], 404);
    }

    public function getLibraryStats(Request $request)
    {
        $stats = $request->user()->games()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'pendiente'  => $stats->get('pendiente', 0),
            'jugando'    => $stats->get('jugando', 0),
            'completado' => $stats->get('completado', 0),
            'abandonado' => $stats->get('abandonado', 0),
            'total'      => $stats->sum()
        ]);
    }

    public function getPublicProfile(Request $request, $username)
    {
        $user = \App\Models\User::where('username', $username)->firstOrFail();
        
        if (!$user->is_public && ($request->user()?->id !== $user->id)) {
            return response()->json(['message' => 'Este perfil es privado'], 403);
        }

        // 🚀 1. STATS: Calculamos las estadísticas GLOBALES antes de filtrar nada
        $stats = $user->games()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $estadisticas = [
            'total'      => $stats->sum(),
            'pendiente'  => $stats->get('pendiente', 0),
            'jugando'    => $stats->get('jugando', 0),
            'completado' => $stats->get('completado', 0),
            'abandonado' => $stats->get('abandonado', 0),
        ];

        // 🎯 2. FILTROS: Obtenemos los juegos aplicando los filtros de la URL
        $games = $user->games()
            ->withExists('journalEntries as has_notes')
            ->withExists(['journalEntries as has_featured_notes' => function ($query) {
                $query->whereRaw('is_featured = true');
            }])
            ->when($request->query('status'), function ($q, $status) {
                if ($status && $status !== 'todos') $q->where('status', $status);
            })
            ->when($request->query('platform'), function ($q, $platform) {
                if ($platform && $platform !== 'todas') {
                    $q->whereRaw('LOWER(platform) LIKE ?', ['%' . strtolower($platform) . '%']);
                }
            })
            ->when($request->query('search'), function ($q, $search) {
                if ($search) {
                    $q->whereRaw('LOWER(title) LIKE ?', ['%' . strtolower($search) . '%']);
                }
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'owner' => [
                'name' => $user->name,
                'username' => $user->username,
            ],
            'stats' => $estadisticas, // Enviamos los números reales
            'games' => $games
        ]);
    }
}