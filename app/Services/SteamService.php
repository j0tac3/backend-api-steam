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
use Illuminate\Support\Facades\DB; // 🚀 VITAL PARA POSTGRESQL

class SteamService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('STEAM_API_KEY');
    }

    public function syncGameAchievements(User $user, Game $game, string $steamAppId, ?string $userSteamId = null)
    {
        if (!$this->apiKey) {
            Log::error("SteamService: Falta la STEAM_API_KEY en el archivo .env de Producción.");
            return false;
        }

        // 1. Descargar la "Plantilla"
        $schema = $this->fetchGameSchema($steamAppId);
        
        // 🚀 EL FIX AUTO-SANADOR: Si $schema es null (error de red o API caída), abortamos 
        // SIN actualizar la caché. Así lo volverá a intentar la próxima vez que abras el modal.
        if ($schema === null) {
            return false;
        }

        $achievementMap = []; 
        foreach ($schema as $achData) {
            // 🚀 BLINDAJE POSTGRESQL ABSOLUTO (Texto literal para el motor SQL)
            $postgresBoolean = (!empty($achData['hidden']) && $achData['hidden'] == 1) ? 'true' : 'false';

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
                    'is_hidden'     => DB::raw($postgresBoolean)
                ]
            );
            $achievementMap[$achData['name']] = $gameAchievement->id;
        }

        // 2. Descargar el "Progreso" (Si el perfil no es privado)
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

        // 3. Actualizar Caché (Solo llegamos aquí si Steam respondió correctamente)
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
                // 🚀 Devuelve array vacío [] si el juego simplemente no tiene logros creados por el desarrollador
                return $data['game']['availableGameStats']['achievements'] ?? [];
            }
            Log::warning("SteamService HTTP Error: {$response->status()} para el juego {$appId}");
        } catch (\Exception $e) {
            Log::error("SteamService Exception: " . $e->getMessage());
        }
        return null; // Null significa "Fallo de comunicación real"
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
                return $data['playerstats']['achievements'] ?? [];
            }
        } catch (\Exception $e) {
            // Falla silenciosamente si el perfil de Steam es privado o nunca jugó
        }
        return [];
    }
}