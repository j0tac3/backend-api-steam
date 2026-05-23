<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Game;
use App\Models\User;
use App\Models\GameAchievement;
use App\Models\UserAchievement;
use App\Models\UserGame;
use Carbon\Carbon;

class SteamService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('STEAM_API_KEY');
    }

    /**
     * El "Director de Orquesta". Llama a los endpoints y guarda los datos.
     */
    public function syncGameAchievements(User $user, Game $game, string $steamAppId, ?string $userSteamId = null)
    {
        if (!$this->apiKey) {
            Log::error("Falta la STEAM_API_KEY en el archivo .env");
            return false;
        }

        // 1. Descargar la "Plantilla"
        $schema = $this->fetchGameSchema($steamAppId);
        
        if (!$schema) {
            return false;
        }

        $achievementMap = []; 
        foreach ($schema as $achData) {
            $gameAchievement = GameAchievement::updateOrCreate(
                [
                    'game_id'  => $game->id,
                    'api_name' => $achData['name'] 
                ],
                [
                    'name'          => $achData['displayName'] ?? 'Logro Oculto',
                    'description'   => $achData['description'] ?? null,
                    'icon_url'      => $achData['icon'] ?? null,
                    'icon_gray_url' => $achData['icongray'] ?? null,
                    'is_hidden'     => isset($achData['hidden']) && $achData['hidden'] == 1
                ]
            );
            $achievementMap[$achData['name']] = $gameAchievement->id;
        }

        // 2. Descargar el "Progreso" (Si tenemos el Steam ID del jugador)
        if ($userSteamId) {
            $playerProgress = $this->fetchPlayerAchievements($userSteamId, $steamAppId);

            if ($playerProgress) {
                foreach ($playerProgress as $progData) {
                    if ($progData['achieved'] == 1 && isset($achievementMap[$progData['apiname']])) {
                        UserAchievement::updateOrCreate(
                            [
                                'user_id'             => $user->id,
                                'game_achievement_id' => $achievementMap[$progData['apiname']]
                            ],
                            [
                                'unlocked_at' => Carbon::createFromTimestamp($progData['unlocktime'])
                            ]
                        );
                    }
                }
            }
        }

        // 3. Actualizar Caché
        UserGame::where('user_id', $user->id)
                ->where('game_id', $game->id)
                ->update(['last_achievement_sync' => now()]);

        return true;
    }

    private function fetchGameSchema(string $appId)
    {
        try {
            $response = Http::timeout(5)->get('https://api.steampowered.com/ISteamUserStats/GetSchemaForGame/v2/', [
                'key'   => $this->apiKey,
                'appid' => $appId,
                'l'     => 'spanish' 
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['game']['availableGameStats']['achievements'] ?? null;
            }
        } catch (\Exception $e) {
            Log::warning("Error Schema Steam: " . $e->getMessage());
        }
        return null;
    }

    private function fetchPlayerAchievements(string $steamId, string $appId)
    {
        try {
            $response = Http::timeout(5)->get('https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v0001/', [
                'key'     => $this->apiKey,
                'steamid' => $steamId,
                'appid'   => $appId,
                'l'       => 'spanish'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['playerstats']['achievements'] ?? null;
            }
        } catch (\Exception $e) {
            Log::info("No progreso para SteamID {$steamId}");
        }
        return null;
    }
}