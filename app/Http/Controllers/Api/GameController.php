<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Genre;
use App\Models\User;
use App\Models\UserGame;
use Illuminate\Http\Request;
use App\Services\IgdbService;
use App\Services\TranslationService;
use App\Services\HowLongToBeatService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\PendingMetadataQueue;

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

    // ==========================================
    // 📚 1. BIBLIOTECA (Relacional Pura)
    // ==========================================
    public function index(Request $request) 
    {
        $userId = $request->user()->id;

        $gamesQuery = Game::with([
            // 🔥 Blindado para lectura
            'media' => function($q) { $q->whereRaw('is_primary = true'); },
            'inventoryEntries' => function($q) use ($userId) {
                $q->where('user_id', $userId)->with('platform');
            }
        ])
        ->whereHas('inventoryEntries', function($q) use ($userId, $request) {
            $q->where('user_id', $userId);
            if ($request->query('status') && $request->query('status') !== 'todos') {
                $q->where('status', $request->query('status'));
            }
            if ($request->query('platform') && $request->query('platform') !== 'todas') {
                $q->where('platform_id', $request->query('platform'));
            }
        });

        if ($search = $request->query('search')) {
            $gamesQuery->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%']);
        }

        $gamesQuery->orderByRaw("(SELECT MAX(updated_at) FROM user_games WHERE user_games.game_id = games.id AND user_games.user_id = {$userId}) DESC");

        $paginated = $gamesQuery->paginate(20);

        $paginated->getCollection()->transform(function ($game) use ($userId) {
            $game->has_notes = \App\Models\JournalEntry::where('game_id', $game->id)->where('user_id', $userId)->exists();
            $game->has_featured_notes = \App\Models\JournalEntry::where('game_id', $game->id)->where('user_id', $userId)->where('is_featured', true)->exists();
            return $game;
        });

        return $paginated;
    }

    // ==========================================
    // ➕ 2. AÑADIR A LA COLECCIÓN
    // ==========================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'external_id'  => 'required|integer', 
            'status'       => 'required|string|in:pendiente,jugando,completado,abandonado',
            'platform_ids' => 'required|array|min:1', 
            'platform_ids.*'=> 'exists:platforms,id', 
        ]);

        $user = $request->user();
        
        // 🚀 CORRECCIÓN: Buscamos a través de la tabla satélite external_identifiers
        $game = Game::whereHas('externalIdentifiers', function($q) use ($validated) {
            // 🔥 Añadimos (string) a $validated['external_id']
            $q->where('provider', 'igdb')->where('external_id', (string) $validated['external_id']);
        })->first();

        if (!$game) {
            $game = $this->importGameFromIgdb($validated['external_id']);
            if (!$game) return response()->json(['message' => 'Error en importación'], 404);
        }

        $addedCount = 0;
        foreach ($validated['platform_ids'] as $platId) {
            $entry = UserGame::firstOrCreate(
                ['user_id' => $user->id, 'game_id' => $game->id, 'platform_id' => $platId],
                ['status' => $validated['status'], 'personal_rating' => 0, 'is_favorite' => DB::raw('false'), 'playtime_minutes' => 0] // 🔥 Blindado
            );
            if ($entry->wasRecentlyCreated) $addedCount++;
        }

        return response()->json(['message' => "$addedCount versiones añadidas al inventario"], 201);
    }

    // ==========================================
    // 🔄 3. ACTUALIZAR ESTADO INDIVIDUAL
    // ==========================================
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status'          => 'required|string|in:pendiente,jugando,completado,abandonado',
            'platform_id'     => 'required|exists:platforms,id', 
            'playtime_minutes'=> 'nullable|integer',
            'personal_rating' => 'nullable|integer'
        ]);

        $userGame = UserGame::where('user_id', $request->user()->id)
            ->where('game_id', $id)
            ->where('platform_id', $validated['platform_id'])
            ->firstOrFail();

        $userGame->status = $validated['status'];
        if ($request->has('playtime_minutes')) $userGame->playtime_minutes = $validated['playtime_minutes'];
        if ($request->has('personal_rating')) $userGame->personal_rating = $validated['personal_rating'];
        $userGame->save();

        return response()->json(['message' => 'Actualizado correctamente']);
    }

    // ==========================================
    // ❤️ 4. FAVORITOS Y ELIMINAR QUIRÚRGICO
    // ==========================================
    public function toggleFavorite(Request $request, $id)
    {
        $entries = UserGame::where('user_id', $request->user()->id)->where('game_id', $id)->get();
        if ($entries->isEmpty()) return response()->json(['message' => 'Juego no encontrado'], 404);
        
        $newStatus = !$entries->first()->is_favorite;
        UserGame::where('user_id', $request->user()->id)->where('game_id', $id)->update(['is_favorite' => $newStatus]);

        return response()->json(['is_favorite' => $newStatus]);
    }

    public function destroy(Request $request, $id)
    {
        $query = UserGame::where('user_id', $request->user()->id)->where('game_id', $id);
        
        if ($request->has('platform_id')) {
            $query->where('platform_id', $request->platform_id);
        }

        $query->delete();
        return response()->json(['message' => 'Eliminado correctamente']);
    }

    // ==========================================
    // 📄 5. DETALLE DEL JUEGO
    // ==========================================
    public function show($id, Request $request)
    {
        $source = $request->query('source', 'local');
        $user = $request->user();

        if ($source === 'igdb') {
            $localGame = Game::with(['genres', 'platforms', 'media', 'stores', 'dlcs.media'])
                        ->whereHas('externalIdentifiers', function($q) use ($id) {
                            // 🔥 Añadimos (string) a $id
                            $q->where('provider', 'igdb')->where('external_id', (string) $id);
                        })->first();

            if ($localGame) {
                $localGame->setAttribute('screenshots', $this->buildScreenshotsUrls($localGame->media));
                $myVersions = UserGame::where('user_id', $user->id)->where('game_id', $localGame->id)->get();

                return response()->json([
                    'success' => true, 
                    'data' => $localGame,
                    'my_versions' => $myVersions
                ]);
            }

            $details = $this->igdbService->getGameDetails($id);
            if (!$details || count($details) === 0) {
                return response()->json(['message' => 'Juego no encontrado en IGDB'], 404);
            }
            
            $raw = $details[0];
            $formattedGame = [
                'id' => null, 'igdb_id' => $raw['id'], 'name' => $raw['name'],
                'slug' => Str::slug($raw['name']) . '-' . $raw['id'],
                'summary' => isset($raw['summary']) ? $this->translator->translateToSpanish($raw['summary']) : null,
                'release_date' => isset($raw['first_release_date']) ? date('Y-m-d', $raw['first_release_date']) : null,
                'rating' => $raw['aggregated_rating'] ?? null,
                'igdb_user_rating' => $raw['rating'] ?? null,
                'localized_data'      => $raw['alternative_names'] ?? null,
                'supported_languages' => $raw['language_supports'] ?? null,
                'media' => [], 'platforms' => [], 'genres' => [], 'screenshots' => [],
            ];

            if (isset($raw['cover']['image_id'])) {
                $formattedGame['media'][] = ['type' => 'cover', 'path' => $raw['cover']['image_id'], 'source' => 'igdb', 'is_primary' => true];
            }
            if (isset($raw['platforms'])) {
                foreach ($raw['platforms'] as $p) {
                    $family = $this->normalizeFamilyName($p['name']);
                    $platform = Platform::firstOrCreate(
                        ['slug' => Str::slug($p['name'])],
                        [
                            'igdb_id' => $p['id'] ?? null, 
                            'name'    => $p['name'], 
                            'family'  => $family           
                        ]
                    );
                    $formattedGame['platforms'][] = ['id' => $platform->id, 'name' => $platform->name, 'family' => $platform->family];
                }
            }
            if (isset($raw['genres'])) {
                foreach ($raw['genres'] as $g) {
                    $genre = Genre::firstOrCreate(['slug' => Str::slug($g['name'])], ['name' => $g['name']]);
                    $formattedGame['genres'][] = ['id' => $genre->id, 'name' => $genre->name];
                }
            }
            if (isset($raw['screenshots'])) {
                foreach ($raw['screenshots'] as $s) {
                    $formattedGame['screenshots'][] = 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/' . $s['image_id'] . '.jpg';
                }
            }

            return response()->json(['success' => true, 'data' => $formattedGame, 'my_versions' => []]);
        }

        // Si source no es igdb, busca directamente por la Clave Primaria local de tu BBDD
        $game = Game::with(['genres', 'platforms', 'media', 'stores', 'dlcs.media'])->findOrFail($id);
        $game->setAttribute('screenshots', $this->buildScreenshotsUrls($game->media));
        $myVersions = UserGame::where('user_id', $user->id)->where('game_id', $game->id)->get();

        return response()->json([
            'success' => true, 
            'data' => $game,
            'my_versions' => $myVersions
        ]);
    }

    public function getLibraryStats(Request $request)
    {
        $stats = UserGame::where('user_id', $request->user()->id)
            ->selectRaw('status, count(DISTINCT game_id) as count')
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
        $user = User::where('username', $username)->firstOrFail();
        
        if (!$user->is_public && ($request->user()?->id !== $user->id)) {
            return response()->json(['message' => 'Este perfil es privado'], 403);
        }

        $stats = UserGame::where('user_id', $user->id)
            ->selectRaw('status, count(DISTINCT game_id) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $games = Game::with([
            // 🔥 Blindado para lectura
            'media' => function($q) { $q->whereRaw('is_primary = true'); },
            'inventoryEntries' => function($q) use ($user) {
                $q->where('user_id', $user->id)->with('platform');
            }
        ])
        ->whereHas('inventoryEntries', function($q) use ($user) {
            $q->where('user_id', $user->id);
        })
        ->orderByRaw("(SELECT MAX(updated_at) FROM user_games WHERE user_games.game_id = games.id AND user_games.user_id = {$user->id}) DESC")
        ->get();

        return response()->json([
            'owner' => ['name' => $user->name, 'username' => $user->username],
            'stats' => [
                'total'      => $stats->sum(),
                'pendiente'  => $stats->get('pendiente', 0),
                'jugando'    => $stats->get('jugando', 0),
                'completado' => $stats->get('completado', 0),
                'abandonado' => $stats->get('abandonado', 0),
            ],
            'games' => $games
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->query('q');
        $category = $request->query('category', 'todas');

        if (!$query) return response()->json([]);
        $rawGames = $this->igdbService->searchGames($query, $category);
        if (!is_array($rawGames) || isset($rawGames['message'])) return response()->json([]);

        $cleanGames = collect($rawGames)->map(function ($game) {
            if (!isset($game['id']) || !isset($game['name'])) return null;
            $coverUrl = isset($game['cover']['url']) ? 'https:' . str_replace('t_thumb', 't_cover_big', $game['cover']['url']) : null;

            return [
                'external_id' => (string) $game['id'],
                'title'       => $game['name'],
                'cover_url'   => $coverUrl,
                'source'      => 'igdb',
                'category'    => $game['category'] ?? 0,
                'release_year'=> isset($game['first_release_date']) ? date('Y', $game['first_release_date']) : null,
                'igdb_user_rating' => $game['rating'] ?? null,
            ];
        })->filter()->values();

        return response()->json($cleanGames);
    }

    private function importGameFromIgdb($igdbId)
    {
        $details = $this->igdbService->getGameDetails($igdbId);
        if (!$details || count($details) === 0) return null;

        $raw = $details[0];

        // 🚀 CORRECCIÓN: Eliminada la columna igdb_id del create
        $game = Game::create([
            'name'         => $raw['name'],
            'slug'         => Str::slug($raw['name']) . '-' . $raw['id'],
            'summary'      => isset($raw['summary']) ? $this->translator->translateToSpanish($raw['summary']) : null,
            'release_date' => isset($raw['first_release_date']) ? date('Y-m-d', $raw['first_release_date']) : null,
            'rating'       => $raw['aggregated_rating'] ?? null,
            'igdb_user_rating' => $raw['rating'] ?? null,
            'localized_data'      => $raw['localizations'] ?? $raw['localized_data'] ?? null,
            'supported_languages' => $raw['language_supports'] ?? $raw['supported_languages'] ?? null,
        ]);

        // 🚀 CORRECCIÓN: Guardamos la vinculación en la tabla satélite correspondientemente
        $game->externalIdentifiers()->create([
            'provider' => 'igdb',
            'external_id' => (string) $raw['id'] // 🔥 Blindaje de texto
        ]);

        if (isset($raw['genres'])) {
            foreach ($raw['genres'] as $g) {
                $genre = Genre::firstOrCreate(['slug' => Str::slug($g['name'])], ['name' => $g['name']]);
                $game->genres()->attach($genre->id);
            }
        }

        if (isset($raw['platforms'])) {
            foreach ($raw['platforms'] as $p) {
                $family = $this->normalizeFamilyName($p['name']);
                $platform = Platform::firstOrCreate(
                    ['slug' => Str::slug($p['name'])], 
                    [
                        'igdb_id' => $p['id'] ?? null,
                        'name'    => $p['name'], 
                        'family'  => $family           
                    ]
                );
                $game->platforms()->attach($platform->id);
            }
        }

        if (isset($raw['cover']['image_id'])) {
            // 🔥 Usamos DB::raw('true')
            $game->media()->create(['type' => 'cover', 'source' => 'igdb', 'path' => $raw['cover']['image_id'], 'is_primary' => DB::raw('true')]);
        }

        if (isset($raw['screenshots'])) {
            foreach ($raw['screenshots'] as $screenshot) {
                // 🔥 Usamos DB::raw('false')
                $game->media()->create(['type' => 'screenshot', 'source' => 'igdb', 'path' => $screenshot['image_id'], 'is_primary' => DB::raw('false')]);
            }
        }

        return $game;
    }

    private function normalizeFamilyName($name)
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'playstation') || str_contains($lower, 'ps')) return 'playstation';
        if (str_contains($lower, 'xbox')) return 'xbox';
        if (str_contains($lower, 'pc') || str_contains($lower, 'windows') || str_contains($lower, 'mac')) return 'pc';
        if (str_contains($lower, 'nintendo') || str_contains($lower, 'switch')) return 'nintendo';
        
        // 🚀 NUEVA REGLA: Detección de dispositivos móviles
        if (str_contains($lower, 'android') || str_contains($lower, 'ios') || str_contains($lower, 'mobile')) return 'mobile';
        
        return 'other';
    }

    private function buildScreenshotsUrls($mediaCollection)
    {
        $screenshots = [];
        foreach ($mediaCollection as $media) {
            if ($media->type === 'screenshot') {
                // 🚀 FIX: Si ya es una URL web completa (ej. de Steam), la dejamos intacta.
                if (\Illuminate\Support\Str::startsWith($media->path, 'http')) {
                    $screenshots[] = $media->path;
                } else {
                    $screenshots[] = 'https://images.igdb.com/igdb/image/upload/t_screenshot_med/' . $media->path . '.jpg';
                }
            }
        }
        return $screenshots;
    }

    // ==========================================
    // 🚂 6. SINCRONIZACIÓN CON STEAM (Fase Preparatoria)
    // ==========================================
    public function prepareSteamSync(Request $request)
    {
        $validated = $request->validate([
            'steam_id' => 'required|string'
        ]);

        // Traemos la librería con nombres (include_appinfo = true en el servicio)
        $steamLibrary = $this->igdbService->getSteamLibrary($validated['steam_id']);
        
        if (empty($steamLibrary)) {
            return response()->json([
                'success' => false, 
                'message' => 'Perfil de Steam privado o sin juegos.'
            ], 400);
        }

        // Empaquetamos todo en una lista limpia para Angular
        $gamesToSync = [];
        foreach ($steamLibrary as $appId => $data) {
            $gamesToSync[] = [
                'steam_id'         => $appId,
                'name'             => $data['name'],
                'playtime_minutes' => $data['playtime']
            ];
        }

        return response()->json([
            'success'       => true,
            'total_found'   => count($gamesToSync),
            'games_to_sync' => $gamesToSync 
        ]);
    }

    // ==========================================
    // 🚂 7. ADUANA: Guardar un solo juego de Steam
    // ==========================================
    // ==========================================
    // 🚂 7. ADUANA: Guardar un solo juego de Steam (Versión Escalable Pro)
    // ==========================================
    public function syncSingleSteamGame(Request $request)
    {
        $steamId = (string) $request->input('steam_id'); 
        $steamName = $request->input('name');
        $playtime = $request->input('playtime_minutes', 0);
        $userId = $request->user()->id;

        // 🔍 1. ¿YA EXISTE POR STEAM ID EN NUESTRA TABLA SATÉLITE?
        $localGame = Game::whereHas('externalIdentifiers', function($q) use ($steamId) {
            $q->where('provider', 'steam')->where('external_id', $steamId);
        })->first();

        // 🔍 2. SI NO EXISTE, BUSCAMOS EN IGDB PARA VER SI YA EXISTE CON ESE ID DE IGDB
        $igdbId = $this->igdbService->searchGameByName($steamName);
        
        if (!$localGame && $igdbId) {
            $localGame = Game::whereHas('externalIdentifiers', function($q) use ($igdbId) {
                // 🔥 Añadimos (string) a $igdbId
                $q->where('provider', 'igdb')->where('external_id', (string) $igdbId);
            })->first();
        }

        // 🏗️ 3. SI SIGUE SIN EXISTIR, ES UN JUEGO NUEVO (O UN "HUÉRFANO")
        if (!$localGame) {
            if ($igdbId) {
                $igdbDetails = $this->igdbService->getGameDetails($igdbId);
                if (!empty($igdbDetails)) {
                    $raw = $igdbDetails[0];
                    $expectedSlug = Str::slug($raw['name']) . '-' . $raw['id'];

                    $localGame = Game::updateOrCreate(
                        ['slug' => $expectedSlug], 
                        [
                            'name' => $raw['name'],
                            'summary' => isset($raw['summary']) ? $this->translator->translateToSpanish($raw['summary']) : null,
                            'release_date' => isset($raw['first_release_date']) ? date('Y-m-d', $raw['first_release_date']) : null,
                        ]
                    );

                    // 🖼️ GUARDAR LA CARÁTULA EN LA TABLA MEDIA
                    // 🖼️ GUARDAR LA CARÁTULA EN LA TABLA MEDIA (Versión Blindada Postgres)
                    if (isset($raw['cover']['image_id'])) {
                        $cover = $localGame->media()
                            ->where('type', 'cover')
                            ->whereRaw('is_primary = true') // 🔥 Evita que Laravel envíe un 1
                            ->first();
                        
                        if ($cover) {
                            $cover->update([
                                'source' => 'igdb', 
                                'path' => $raw['cover']['image_id']
                            ]);
                        } else {
                            $localGame->media()->create([
                                'type' => 'cover', 
                                'source' => 'steam', 
                                'path' => "https://steamcdn-a.akamaihd.net/steam/apps/{$steamId}/library_600x900.jpg", 
                                'is_primary' => DB::raw('true') // 🔥 Blindado
                            ]);
                        }
                    }
                    // 📸 GUARDAR CAPTURAS DE IGDB
                    // 📸 GUARDAR CAPTURAS DE IGDB
                    if (isset($raw['screenshots'])) {
                        foreach ($raw['screenshots'] as $screenshot) {
                            $localGame->media()->updateOrCreate(
                                ['type' => 'screenshot', 'path' => $screenshot['image_id']],
                                ['source' => 'igdb', 'is_primary' => DB::raw('false')] // 🔥 Blindado
                            );
                        }
                    }

                    // 🏷️ GUARDAR LOS GÉNEROS
                    if (isset($raw['genres'])) {
                        foreach ($raw['genres'] as $g) {
                            $genre = Genre::firstOrCreate(['slug' => Str::slug($g['name'])], ['name' => $g['name']]);
                            $localGame->genres()->syncWithoutDetaching([$genre->id]);
                        }
                    }
                }
            }

            // Si IGDB no lo encontró o falló, hacemos el guardado de emergencia de Steam
            if (!$localGame) {
                $expectedSlug = Str::slug($steamName) . '-steam-' . $steamId;
                
                $localGame = Game::updateOrCreate(
                    ['slug' => $expectedSlug],
                    ['name' => $steamName]
                );

                // 🖼️ GUARDAR LA CARÁTULA DE STEAM EN LA TABLA MEDIA
                $localGame->media()->updateOrCreate(
                    ['type' => 'cover', 'is_primary' => true],
                    ['source' => 'steam', 'path' => "https://steamcdn-a.akamaihd.net/steam/apps/{$steamId}/library_600x900.jpg"]
                );
            }
        }

        // 📌 4. ASEGURAMOS LOS ENLACES EN LA TABLA SATÉLITE (updateOrCreate evita duplicados)
        // Guardamos el enlace de Steam
        $localGame->externalIdentifiers()->updateOrCreate([
            'provider' => 'steam',
            'external_id' => $steamId
        ]);

        // Si tenemos el ID de IGDB, guardamos también su enlace correspondiente
        // Si tenemos el ID de IGDB, guardamos también su enlace correspondiente
        if ($igdbId) {
            $localGame->externalIdentifiers()->updateOrCreate([
                'provider' => 'igdb',
                'external_id' => (string) $igdbId // 🔥 Blindaje de texto
            ]);
        }

        // 🎮 5. VINCULACIÓN DE HORAS Y PLATAFORMA
        $platformPc = Platform::firstOrCreate(['slug' => 'pc'], ['name' => 'PC', 'family' => 'pc']);
        
        // 👉 AÑADE ESTA LÍNEA: Vinculamos la plataforma al juego general para que Angular no explote
        $localGame->platforms()->syncWithoutDetaching([$platformPc->id]);

        UserGame::updateOrCreate(
            ['user_id' => $userId, 'game_id' => $localGame->id, 'platform_id' => $platformPc->id],
            ['status' => 'jugando', 'playtime_minutes' => $playtime, 'personal_rating' => 0, 'is_favorite' => DB::raw('false')] // 🔥 Blindado
        );

        // 🤖 6. A LA COLA DEL MOTOR EN SEGUNDO PLANO
        PendingMetadataQueue::updateOrCreate(
            ['game_id' => $localGame->id],
            ['external_id' => $steamId, 'platform' => 'SteamStore', 'status' => 'pending', 'attempts' => 0]
        );

        // Devolvemos la respuesta limpia para la carátula instantánea de la PWA
        // Nota: puedes usar una lógica para cover_url dinámica o guardarla temporalmente.
        return response()->json([
            'success' => true,
            'game' => [
                'id' => $localGame->id,
                'title' => $localGame->name,
                'cover_url' => "https://steamcdn-a.akamaihd.net/steam/apps/{$steamId}/library_600x900.jpg"
            ]
        ]);
    }

    // Método auxiliar para no repetir código en el fallback
    private function createEmergencyGame($steamId, $steamName) {
        return Game::updateOrCreate(
            ['steam_appid' => $steamId],
            [
                'title' => $steamName,
                'slug' => Str::slug($steamName) . '-steam-' . $steamId,
                'cover_url' => "https://steamcdn-a.akamaihd.net/steam/apps/{$steamId}/library_600x900.jpg",
                'platform' => 'Steam',
                'family' => 'PC',
            ]
        );
    }
}