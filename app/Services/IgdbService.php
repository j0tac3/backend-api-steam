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
    public function getGameDetails($id)
    {
        $token = $this->getAccessToken();

        // 🚀 LA CONSULTA MAESTRA (Extrae relaciones, empresas, websites, modos, etc.)
        $query = "fields *, cover.*, artworks.*, screenshots.*, genres.name, platforms.name, game_modes.name, themes.name, player_perspectives.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher, websites.category, websites.url, similar_games.name, similar_games.cover.url; where id = {$id}; limit 1;";

        // Limpiamos saltos de línea para evitar errores de parseo en IGDB
        $cleanQuery = trim(preg_replace('/\s+/', ' ', $query));

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody($cleanQuery, 'text/plain')
        ->post("{$this->baseUrl}/games");

        return $response->json();
    }
}