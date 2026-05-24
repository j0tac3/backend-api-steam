<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PendingMetadataQueue;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Services\ImageHashService;

class FetchGamesMetadata extends Command
{
    // El nombre del comando que usaremos en la terminal
    protected $signature = 'games:fetch-metadata';

    protected $description = 'Procesa la cola de juegos pendientes para obtener datos de la tienda de Steam sin bloqueos';

    public function handle()
    {
        $this->info("Iniciando el motor de búsqueda en segundo plano...");

        // --- AÑADE ESTE CHIVATO ---
        $total = \App\Models\PendingMetadataQueue::count();
        $this->info("Total de registros reales en la tabla: " . $total);

        $pending = \App\Models\PendingMetadataQueue::where('status', 'pending')->count();
        $this->info("Registros con status 'pending': " . $pending);

        $pendingGames = PendingMetadataQueue::where('status', 'pending')
                                            ->where('platform', 'SteamStore')
                                            ->take(20)
                                            ->get();

        if ($pendingGames->isEmpty()) {
            $this->info("No hay juegos en la cola. ¡Todo al día!");
            return;
        }

        foreach ($pendingGames as $queueItem) {
            $this->info("Procesando Steam AppID: {$queueItem->external_id}...");

            try {
                // Llamada a la API pública de detalles de Steam
                $response = Http::get("https://store.steampowered.com/api/appdetails?appids={$queueItem->external_id}");
                
                // Verificamos que la petición fue bien y que Steam tiene datos de ese juego
                if ($response->successful() && $response->json()[$queueItem->external_id]['success'] ?? false) {
                    $steamData = $response->json()[$queueItem->external_id]['data'];

                    // Buscamos el juego en nuestra BD
                    $game = Game::find($queueItem->game_id);
                    if ($game) {
                        // 1. Si IGDB nos falló antes y no tenemos sinopsis, metemos la de Steam
                        if (empty($game->summary) && isset($steamData['short_description'])) {
                            $game->summary = strip_tags($steamData['short_description']);
                        }

                        // 🚀 GUARDAR NOTA DE METACRITIC (Si Steam la tiene)
                        if (isset($steamData['metacritic']['score'])) {
                            $game->metacritic_score = $steamData['metacritic']['score'];
                        }

                        // 🚀 GUARDAR NOTA DE METACRITIC (Si Steam la tiene)
                        if (isset($steamData['metacritic']['score'])) {
                            $game->metacritic_score = $steamData['metacritic']['score'];
                        }

                        // ⭐ NUEVO: OBTENER RESEÑAS DE STEAM Y GUARDAR EN LA TABLA SATÉLITE
                        try {
                            $reviewsResponse = Http::get("https://store.steampowered.com/appreviews/{$queueItem->external_id}?json=1&language=spanish&purchase_type=all&num_per_page=0");
                            
                            if ($reviewsResponse->successful() && isset($reviewsResponse->json()['query_summary'])) {
                                $summary = $reviewsResponse->json()['query_summary'];
                                
                                // Nos aseguramos de que haya votos para no dividir entre cero
                                if (isset($summary['total_reviews']) && $summary['total_reviews'] > 0) {
                                    // Regla de 3 para sacar el porcentaje exacto (0-100)
                                    $scorePercentage = round(($summary['total_positive'] / $summary['total_reviews']) * 100);
                                    
                                    // Creamos o actualizamos el satélite en la nueva tabla
                                    $game->steamRating()->updateOrCreate(
                                        ['game_id' => $game->id],
                                        [
                                            'score'   => $scorePercentage,
                                            'summary' => $summary['review_score_desc']
                                        ]
                                    );
                                }
                            }
                        } catch (\Exception $e) {
                            $this->error("Fallo al obtener reseñas de Steam: " . $e->getMessage());
                        }
                        
                        // Aquí puedes añadir en el futuro la lógica para guardar géneros, capturas, etc.
                        // 📸 GUARDAR CAPTURAS DE STEAM (Opción B: El Reemplazo)
                        if (isset($steamData['screenshots']) && count($steamData['screenshots']) > 0) {
                            
                            // 🧹 1. Pasamos la escoba: Eliminamos de golpe las capturas viejas de IGDB
                            $game->media()->where('type', 'screenshot')->delete();
                            
                            // 🖼️ 2. Guardamos las capturas premium de Steam
                            foreach ($steamData['screenshots'] as $screenshot) {
                                $game->media()->create([
                                    'type' => 'screenshot', 
                                    'path' => $screenshot['path_full'],
                                    'source' => 'steam', 
                                    'is_primary' => DB::raw('false')
                                ]);
                            }
                        }
                        $game->save();
                    }

                    // Marcamos la tarea como completada
                    $queueItem->delete();
                    $this->line("✅ Juego actualizado con éxito y eliminado de la cola.");
                } else {
                    // Steam no tiene datos válidos (suele pasar con juegos descatalogados o baneados)
                    $this->handleFailure($queueItem);
                }

            } catch (\Exception $e) {
                $this->error("Error de conexión: " . $e->getMessage());
                $this->handleFailure($queueItem);
            }

            // ⏱️ EL SECRETO DEL ÉXITO: Dormir el script 1.5 segundos entre peticiones
            // Esto garantiza que hagamos máximo unas 40 peticiones por minuto, 
            // súper seguro frente al límite de 200 de Steam.
            usleep(1500000); 
        }

        $this->info("Lote procesado. Motor en pausa hasta el próximo ciclo.");
    }

    private function handleFailure($queueItem)
    {
        $queueItem->increment('attempts');
        // Si falla 3 veces, tiramos la toalla para que no se quede en bucle infinito
        if ($queueItem->attempts >= 3) {
            $queueItem->delete();
            $this->line("❌ Eliminado de la cola permanentemente tras 3 intentos fallidos.");
        } else {
            $this->line("⚠️ Fallo temporal. Intento {$queueItem->attempts}/3.");
        }
    }
}