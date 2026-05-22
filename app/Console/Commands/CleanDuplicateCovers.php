<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Game;
use App\Services\ImageHashService;
use Illuminate\Support\Facades\DB;

class CleanDuplicateCovers extends Command
{
    // El nombre del comando en la terminal
    protected $signature = 'games:clean-covers';

    protected $description = 'Escanea la base de datos en busca de juegos con portadas duplicadas de Steam e IGDB y limpia las que son visualmente idénticas.';

    public function handle(ImageHashService $hasher)
    {
        $this->info("🧹 Iniciando el servicio de limpieza (Calidad Premium)...");

        $gamesWithMultipleCovers = Game::has('media', '>=', 2)
            ->whereHas('media', function($q) {
                $q->where('type', 'cover');
            })->get();

        $cleanedCount = 0;

        foreach ($gamesWithMultipleCovers as $game) {
            $covers = $game->media()->where('type', 'cover')->get();
            
            if ($covers->count() >= 2) {
                $igdbCover = $covers->where('source', 'igdb')->first();
                $steamCover = $covers->where('source', 'steam')->first();

                if ($igdbCover && $steamCover) {
                    $this->line("Analizando {$game->name}...");

                    $urlIgdb = "https://images.igdb.com/igdb/image/upload/t_cover_big/{$igdbCover->path}.jpg";
                    $urlSteam = $steamCover->path;

                    // 1. Obtenemos Hashes y PESOS de ambas imágenes
                    $dataIgdb = $hasher->getHashAndSize($urlIgdb);
                    $dataSteam = $hasher->getHashAndSize($urlSteam);

                    // Si alguna de las dos falló al descargar, saltamos al siguiente juego
                    if (!$dataIgdb || !$dataSteam) continue;

                    // 2. Calculamos similitud
                    $similitud = $hasher->calculateSimilarity($dataIgdb['hash'], $dataSteam['hash']);
                    $this->line("  -> Similitud: " . round($similitud, 2) . "%");

                    // 3. EL VEREDICTO: Bajamos el umbral al 85% para cazar la compresión JPEG
                    if ($similitud >= 85.0) {
                        
                        // 🥊 DUELO DE CALIDAD: ¿Quién pesa más?
                        if ($dataSteam['size'] > $dataIgdb['size']) {
                            // Steam tiene más calidad
                            $igdbCover->delete();
                            $steamCover->update(['is_primary' => \Illuminate\Support\Facades\DB::raw('true')]);
                            $this->info("  🏆 Gana Steam (" . round($dataSteam['size']/1024) . "KB vs " . round($dataIgdb['size']/1024) . "KB). IGDB eliminada.");
                        } else {
                            // IGDB tiene más calidad (o son exactamente iguales)
                            $steamCover->delete();
                            $igdbCover->update(['is_primary' => \Illuminate\Support\Facades\DB::raw('true')]);
                            $this->info("  🏆 Gana IGDB (" . round($dataIgdb['size']/1024) . "KB vs " . round($dataSteam['size']/1024) . "KB). Steam eliminada.");
                        }
                        
                        $cleanedCount++;
                    }
                }
            }
            
            usleep(500000); 
        }

        $this->info("✨ Limpieza terminada. Se han eliminado {$cleanedCount} portadas de menor calidad.");
    }
}