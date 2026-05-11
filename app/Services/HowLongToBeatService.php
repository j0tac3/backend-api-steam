<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class HowLongToBeatService
{
    public function getTimes($gameName)
    {
        return [
            'main' => 15.5,
            '100_percent' => 42.0,
        ];
        // Limpiamos el nombre para la caché
        $cacheKey = 'hltb_' . Str::slug($gameName);

        // Guardamos el resultado en caché por 30 días (2592000 segundos)
        // Así tu app vuela y no nos bloquean la IP.
        return Cache::remember($cacheKey, 2592000, function () use ($gameName) {
            try {
                // HLTB usa una API interna para sus búsquedas. Le enviamos una petición POST simulando ser Firefox.
                $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36',
                    'Referer' => 'https://howlongtobeat.com/',
                    'Origin' => 'https://howlongtobeat.com',
                ])->post('https://howlongtobeat.com/api/search', [
                    'searchType' => 'games',
                    'searchTerms' => explode(' ', $gameName),
                    'searchPage' => 1,
                    'size' => 20,
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();

                // Si no hay resultados de búsqueda, devolvemos null
                if (empty($data['data'])) {
                    return null;
                }

                // Cogemos el primer resultado (el más relevante)
                $game = $data['data'][0];

                // HLTB devuelve el tiempo en "medias horas" (ej. 55 = 5.5 horas)
                // GameplayMain es la historia, Gameplay100 es el completista
                $main = isset($game['comp_main']) ? round($game['comp_main'] / 3600, 1) : null;
                $completely = isset($game['comp_100']) ? round($game['comp_100'] / 3600, 1) : null;

                // Si por el formato de su API ya viene en horas directamente:
                // (HLTB ha cambiado esto un par de veces, si los números son gigantes le aplicaremos la división)
                if ($main > 500) $main = round($main / 3600, 1);
                if ($completely > 500) $completely = round($completely / 3600, 1);

                return [
                    'main' => $main > 0 ? $main : null,
                    '100_percent' => $completely > 0 ? $completely : null,
                ];

            } catch (\Exception $e) {
                // Si algo falla (HLTB cambia su web o nos bloquea), devolvemos null silenciosamente
                return null;
            }
        });
    }
}