<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IgdbService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl = 'https://api.igdb.com/v4';

    public function __construct()
    {
        $this->clientId = env('IGDB_CLIENT_ID');
        $this->clientSecret = env('IGDB_CLIENT_SECRET');
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
        $response = Http::withHeaders([
            'Client-ID' => env('IGDB_CLIENT_ID'),
            'Authorization' => 'Bearer ' . $this->getAccessToken(),
        ])
        ->withBody(
            "fields name, summary, cover.url, genres.name, involved_companies.company.name, involved_companies.developer; where id = {$id};", 
            'text/plain'
        )
        ->post('https://api.igdb.com/v4/games');

        $data = $response->json();

        // Devolvemos el primer resultado (si existe)
        return !empty($data) ? $data[0] : null;
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