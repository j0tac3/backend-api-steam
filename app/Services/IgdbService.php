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

    // Buscar juegos por nombre
    public function searchGames($query)
    {
        $token = $this->getAccessToken();

        // Usamos el lenguaje "Apicalypse" de IGDB
        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody("search \"$query\"; fields name, cover.url, first_release_date, summary; limit 10;", 'text/plain')
        ->post("{$this->baseUrl}/games");

        return $response->json();
    }

    /**
     * Obtener los detalles completos de un juego por su ID en IGDB
     */
    public function getGameDetails($id)
    {
        // Creamos una llave única para este juego en la caché (ej: "igdb_game_9727")
        $cacheKey = 'igdb_game_' . $id;

        // rememberForever guardará este JSON traducido para siempre (ahorrando recursos)
        return Cache::rememberForever($cacheKey, function () use ($id) {

            // 1. La Mega Consulta a IGDB
            $query = "fields name, summary, storyline, first_release_date, rating, rating_count, aggregated_rating, aggregated_rating_count, cover.image_id, screenshots.image_id, artworks.image_id, videos.name, videos.video_id, genres.name, themes.name, game_modes.name, player_perspectives.name, platforms.name, involved_companies.company.name, involved_companies.developer, involved_companies.publisher, websites.category, websites.url, similar_games.name, dlcs.name, expansions.name; where id = {$id}; limit 1;";

            $response = Http::withHeaders([
                // Ajusta estos headers con tus variables de entorno para Twitch/IGDB
                'Client-ID' => env('IGDB_CLIENT_ID'),
                'Authorization' => 'Bearer ' . env('IGDB_ACCESS_TOKEN'),
            ])->withBody($query, 'text/plain')
              ->post('https://api.igdb.com/v4/games');

            $data = $response->json();

            // Si IGDB no devuelve nada, cortamos aquí
            if (empty($data)) {
                return null;
            }

            // IGDB siempre devuelve un array, cogemos el primer (y único) resultado
            $game = $data[0];

            // 2. Traducción Quirúrgica (Solo traducimos lo literario)
            if (isset($game['summary'])) {
                $game['summary'] = $this->translator->translateToSpanish($game['summary']);
            }

            if (isset($game['storyline'])) {
                $game['storyline'] = $this->translator->translateToSpanish($game['storyline']);
            }

            // Opcional: También podríamos traducir 'genres' y 'themes' aquí si lo deseas

            // 3. Devolvemos el juego cocinado. Al hacer return, Laravel lo guarda en caché automáticamente.
            return $game;
        });
    }

    public function buscarJuegosAvanzado($nombre)
    {
        $token = $this->getAccessToken(); // Aquí sí funciona porque están en la misma clase

        if (!$token) return ['error' => 'Token no disponible'];

        $query = "search \"{$nombre}\"; 
                fields name, summary, cover.url, platforms.name, external_games.uid, external_games.category; 
                limit 12;";

        $response = Http::withHeaders([
            'Client-ID'     => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody($query, 'text/plain')
        ->post('https://api.igdb.com/v4/games');

        return $response->json();
    }
}