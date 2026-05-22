<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\TranslationService;

class IgdbService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl = 'https://api.igdb.com/v4';
    protected $translator;

    public function __construct(TranslationService $translator)
    {
        $this->clientId = env('IGDB_CLIENT_ID');
        $this->clientSecret = env('IGDB_CLIENT_SECRET');
        $this->translator = $translator;
    }

    // Obtener el Token de Twitch (se guarda en caché por 25 días)
    private function getAccessToken()
    {
        return Cache::remember('igdb_token', 2000000, function () {
            $response = Http::post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            return $response->json()['access_token'];
        });
    }

    /**
     * Búsqueda Robusta en IGDB con la regla "Tri-Fecta"
     */
    public function searchGames($query, $categoryFilter = 'todas')
    {
        $token = $this->getAccessToken();
        $safeQuery = trim(str_replace('"', '', $query));

        // 1. 🚀 Pedimos 100 resultados y AÑADIMOS 'parent_game'
        $body = "search \"{$safeQuery}\"; fields id, name, cover.url, category, parent_game; limit 100;";

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])
        ->withBody($body, 'text/plain')
        ->post("{$this->baseUrl}/games");

        $data = $response->json();

        if ($response->failed() || !is_array($data)) {
            return [];
        }

        $filtro = strtolower(trim($categoryFilter));
        $filteredData = [];

        // 2. 🚀 FILTRADO TRI-FECTA (Relacional + Oficial + Heurístico)
        foreach ($data as $game) {
            // Arreglamos la asignación de categoría (si no viene, es 0)
            $catId = isset($game['category']) ? (int)$game['category'] : 0;
            $nameLower = strtolower($game['name'] ?? '');
            
            // Regla 1 (Infalible): Si tiene "parent_game", ES un DLC o Expansión
            $tienePadre = isset($game['parent_game']);

            // 🛠️ HEURÍSTICA: Palabras que SÍ delatan un DLC
            $palabrasQueSonDlc = [
                'dlc', 'expansion', 'season pass', 'pass', 'upgrade', 'pack', 
                'bonus', 'quest', 'soundtrack', 'artbook', 'content',
                'characters', 'character', 'costume', 'outfit', 'add-on', 'skins', 'multiplayer',
                'scenario', 'extra', 'tale', 'update', 'generations',
                'kit', 'stuff', 'bundle', 'expansion pack', 'game pack', 'stuff pack'
            ];
            
            $pareceDlcPorNombre = false;
            foreach ($palabrasQueSonDlc as $palabra) {
                if (str_contains($nameLower, $palabra)) {
                    $pareceDlcPorNombre = true;
                    break;
                }
            }

            // 🚀 CORRECCIÓN ESTRICTA
            if ($tienePadre) {
                $catId = 1; // Si tiene padre, forzamos que sea DLC aunque IGDB diga 0
            } elseif (in_array($catId, [0, 8, 9, 10, 11]) && $pareceDlcPorNombre) {
                $catId = 1; // Si no tiene padre pero el nombre lo delata, forzamos a DLC
            }

            // 🎮 JUEGOS: Base, Remake, Remaster, Ediciones Expandidas (GOTY) y Ports.
            $isJuego = in_array($catId, [0, 8, 9, 10, 11]); 

            // 🧩 DLCs: DLCs, Expansiones, Standalones, Episodios y Bundles.
            $isDlc = in_array($catId, [1, 2, 3, 4, 6, 7]);

            // Clasificación final para el Frontend
            if ($filtro === 'juego' && $isJuego) {
                $filteredData[] = $game;
            } elseif ($filtro === 'dlc' && $isDlc) {
                $filteredData[] = $game;
            } elseif ($filtro === 'todas' && ($isJuego || $isDlc)) {
                $filteredData[] = $game;
            }
        }

        return array_slice($filteredData, 0, 30);
    }

    /**
     * Obtener los detalles GOD MODE desde IGDB
     */
    /**
     * Obtener los detalles GOD MODE desde IGDB
     */
    public function getGameDetails($id)
    {
        $token = $this->getAccessToken();

        // 🚀 CONSULTA CORREGIDA: Eliminamos 'localizations.*' que era lo que hacía explotar la API
        // 🚀 CONSULTA SEGURA: Usamos 'alternative_names' en lugar del problemático 'localizations'
        $query = "fields *, cover.*, artworks.*, screenshots.*, genres.name, platforms.name, game_modes.name, themes.name, player_perspectives.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher, websites.category, websites.url, similar_games.name, similar_games.cover.url, language_supports.language.name, language_supports.language.native_name, language_supports.language_support_type.name, alternative_names.*; where id = {$id}; limit 1;";
        
        // Limpiamos saltos de línea para evitar errores de parseo en IGDB
        $cleanQuery = trim(preg_replace('/\s+/', ' ', $query));

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody($cleanQuery, 'text/plain')
        ->post("{$this->baseUrl}/games");

        $data = $response->json();

        // 🛡️ ESCUDO PROTECTOR: Si la API de IGDB devuelve un error de sintaxis o falla, evitamos que el modal explote
        if (isset($data[0]['status']) && isset($data[0]['title'])) {
            \Illuminate\Support\Facades\Log::error('IGDB Error de Consulta: ' . json_encode($data));
            return [];
        }

        return $data;
    }

    /**
     * 🏆 1. LOS TITANES (Triple A Recomendados)
     * Más de 80 de nota, más de 300 valoraciones, filtrado por género favorito.
     */
    /**
     * 🏆 1. LOS TITANES (Triple A Recomendados)
     * Usamos 'total_rating_count' (Usuarios + Prensa). Con más de 20 en IGDB ya es un AAA.
     */
    /**
     * 🏆 1. LOS TITANES
     */
    public function getTitans($genreId, $limit = 10)
    {
        $token = $this->getAccessToken();
        
        // 🚀 SIN CORCHETES en genres
        $query = "fields name, cover.url, first_release_date, rating, genres.name; 
                  where rating >= 80 & rating_count >= 50 & cover != null & genres = {$genreId}; 
                  sort rating desc; 
                  limit {$limit};";

        return $this->executeIgdbQuery($query, $token);
    }

    /**
     * 🔥 2. EL RADAR 
     */
    public function getRadar($startTimestamp, $endTimestamp, $limit = 10)
    {
        $token = $this->getAccessToken();
        
        // 🚀 Buscamos lanzamientos recientes puros y duros
        $query = "fields name, cover.url, first_release_date, rating; 
                  where first_release_date >= {$startTimestamp} & first_release_date <= {$endTimestamp} & cover != null; 
                  sort first_release_date desc; 
                  limit {$limit};";

        return $this->executeIgdbQuery($query, $token);
    }

    /**
     * 💎 3. JOYAS OCULTAS
     */
    public function getHiddenGems($limit = 10)
    {
        $token = $this->getAccessToken();
        
        // 🚀 Juegos con notazas pero que los ha votado muy poca gente (entre 1 y 15 personas)
        $query = "fields name, cover.url, first_release_date, rating; 
                  where rating >= 80 & rating_count > 0 & rating_count <= 15 & cover != null; 
                  sort rating desc; 
                  limit {$limit};";

        return $this->executeIgdbQuery($query, $token);
    }

    /**
     * 🛠️ FUNCIÓN AUXILIAR (Para no repetir código)
     */
    private function executeIgdbQuery($query, $token)
    {
        $cleanQuery = trim(preg_replace('/\s+/', ' ', $query));

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])->withBody($cleanQuery, 'text/plain')->post("{$this->baseUrl}/games");

        $data = $response->json();

        // 🐛 CHIVATO ACTIVADO: Si hay error, que nos lo devuelva al F12
        if (isset($data['message']) || isset($data[0]['status'])) {
            return ['DEBUG_ERROR' => $data];
        }

        return $data;
    }

    /**
     * 🚂 STEAM FASE 1: Traer la biblioteca del usuario con sus horas
     */
    public function getSteamLibrary($steamId64)
    {
        $apiKey = env('STEAM_API_KEY');
        
        $response = \Illuminate\Support\Facades\Http::get("http://api.steampowered.com/IPlayerService/GetOwnedGames/v0001/", [
            'key' => $apiKey,
            'steamid' => $steamId64,
            'include_appinfo' => true, // 🚨 CAMBIADO A TRUE para que nos traiga los nombres
            'format' => 'json'
        ]);

        $data = $response->json();

        if (!isset($data['response']['games'])) {
            return [];
        }

        // Ahora guardamos tanto el nombre como los minutos
        $steamGames = [];
        foreach ($data['response']['games'] as $game) {
            $steamGames[$game['appid']] = [
                'name' => $game['name'],
                'playtime' => $game['playtime_forever']
            ];
        }

        return $steamGames;
    }

    public function translateSteamIdsToIgdb(array $steamIds)
    {
        $token = $this->getAccessToken();
        $map = [];

        // 1. Dividimos los juegos en paquetes de 40 para no ahogar a IGDB
        $chunks = array_chunk($steamIds, 40);

        foreach ($chunks as $chunk) {
            $uidConditions = [];
            foreach ($chunk as $id) {
                $uidConditions[] = 'uid = "' . $id . '"';
            }
            $whereClause = implode(' | ', $uidConditions);

            // 2. LA CLAVE: Pedimos el id del juego, pero también expandimos la información
            // del juego (game.category) para filtrar la basura que IGDB devuelve.
            // (En la tabla 'games', category 0 = Juego Base).
            // Quitamos el maldito filtro 'category = 1' de la tabla externa.
            $query = "fields game.id, game.category, uid; where ({$whereClause}); limit 500;";

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Client-ID' => $this->clientId,
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->withBody($query, 'text/plain')->post("{$this->baseUrl}/external_games");

            $data = $response->json();

            // 3. Procesamos y limpiamos los datos
            if (is_array($data) && !isset($data['message'])) {
                foreach ($data as $ext) {
                    if (isset($ext['uid']) && isset($ext['game']['id'])) {
                        $steamId = $ext['uid'];
                        $igdbGameId = $ext['game']['id'];
                        $gameCategory = $ext['game']['category'] ?? -1;

                        // Solo nos quedamos con el mapeo si es un Juego Base (0), 
                        // un Remake (8) o un Remaster (9).
                        // Y si ya teníamos un mapeo para este Steam ID, no lo sobreescribimos
                        // (esto prioriza el primer juego válido que encuentre).
                        if (!isset($map[$steamId]) && in_array($gameCategory, [0, 8, 9])) {
                            $map[$steamId] = $igdbGameId;
                        }
                    }
                }
            }
        }

        return $map;
    }

    /**
     * 🕵️‍♂️ Búsqueda difusa por nombre en IGDB (Ignorando DLCs y basura)
     */
    /**
     * 🕵️‍♂️ Búsqueda difusa por nombre en IGDB con reintentos inteligentes
     */
    public function searchGameByName(string $name)
    {
        $token = $this->getAccessToken();
        
        // 1. Limpieza básica (comillas y marcas registradas)
        $safeName = trim(str_replace(['"', '™', '®'], '', $name));

        // 🎯 INTENTO 1: Búsqueda normal exacta (Ej: "Counter-Strike 2")
        $id = $this->executeIgdbSearch($safeName, $token);
        if ($id) return $id;

        // 🎯 INTENTO 2: Quitar coletillas de Steam ("- Definitive Edition", ": Enhanced Edition")
        // Esto elimina cualquier cosa que esté después de un guión e incluya la palabra "Edition"
        $agressiveName = preg_replace('/ - .*Edition/i', '', $safeName);
        $agressiveName = preg_replace('/: .*Edition/i', '', $agressiveName);
        
        if ($agressiveName !== $safeName) {
            $id = $this->executeIgdbSearch(trim($agressiveName), $token);
            if ($id) return $id;
        }

        // 🎯 INTENTO 3: Medida desesperada, cortar por los dos puntos
        // Si se llama "Total War: EMPIRE", probará suerte buscando solo "Total War" 
        // y cogerá el resultado más relevante.
        if (strpos($safeName, ':') !== false) {
            $splitName = explode(':', $safeName)[0];
            $id = $this->executeIgdbSearch(trim($splitName), $token);
            if ($id) return $id;
        }

        return null; // Si después de 3 intentos no lo encuentra, nos rendimos con este juego.
    }

    /**
     * 🔌 Función auxiliar para no repetir la llamada HTTP en cada intento
     */
    /**
     * 🕵️‍♂️ Búsqueda difusa por nombre en IGDB con reintentos inteligentes y filtro de popularidad
     */
    /**
     * 🕵️‍♂️ Búsqueda difusa por nombre en IGDB con reintentos inteligentes y filtro de popularidad nativo
     */
    private function executeIgdbSearch(string $query, string $token)
    {
        // 🚀 Limpiamos la consulta: Le pedimos 20 resultados limpios a IGDB sin cláusulas 'where' restrictivas
        $body = "search \"{$query}\"; fields id, name, category, rating_count; limit 20;";

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->withBody($body, 'text/plain')->post("{$this->baseUrl}/games");

        if ($response->successful()) {
            $data = $response->json();
            
            if (is_array($data) && count($data) > 0 && !isset($data['message'])) {
                
                // 🛡️ ESCUDO 1: Filtramos en PHP las categorías válidas
                // 0 (Base), 8 (Remake), 9 (Remaster), 10 (Expandido), 11 (Port)
                $filtered = array_filter($data, function($game) {
                    $cat = isset($game['category']) ? (int)$game['category'] : 0;
                    return in_array($cat, [0, 8, 9, 10, 11]); 
                });

                if (count($filtered) > 0) {
                    // 🛡️ ESCUDO 2: Ordenamos en PHP por cantidad de valoraciones (rating_count) de mayor a menor
                    usort($filtered, function($a, $b) {
                        $countA = $a['rating_count'] ?? 0;
                        $countB = $b['rating_count'] ?? 0;
                        return $countB <=> $countA; // El juego más famoso se coloca primero
                    });

                    // Reindexamos el array y devolvemos el ID del juego rey indiscutible
                    $filtered = array_values($filtered);
                    return $filtered[0]['id'];
                }

                // Fallback: Si por alguna rareza extrema ningún resultado pasa el filtro, 
                // devolvemos el primero de la búsqueda original para no romper la sincronización
                return $data[0]['id'] ?? null;
            }
        }

        return null;
    }
}