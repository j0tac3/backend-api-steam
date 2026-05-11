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
     * Búsqueda ligera para el Buscador del Frontend
     */
    public function searchGames($query)
    {
        $token = $this->getAccessToken();

        // Solo pedimos lo estrictamente necesario para la lista de resultados
        $body = "search \"$query\"; fields name, cover.url; limit 12;";

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody($body, 'text/plain')
        ->post("{$this->baseUrl}/games");

        return $response->json();
    }

    /**
     * Obtener los detalles crudos desde IGDB
     */
    public function getGameDetails($id)
    {
        $token = $this->getAccessToken();

        $query = "fields name, summary, first_release_date, rating, rating_count, aggregated_rating, aggregated_rating_count, cover.image_id, genres.name, platforms.name, involved_companies.company.name, game_modes.name, screenshots.image_id; where id = {$id}; limit 1;";

        $response = Http::withHeaders([
            'Client-ID' => $this->clientId,
            'Authorization' => 'Bearer ' . $token,
        ])
        ->withBody($query, 'text/plain')
        ->post("{$this->baseUrl}/games");

        return $response->json();
    }
}