<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserGame;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    // ==========================================
    // 📊 DASHBOARD ANALÍTICO AVANZADO
    // ==========================================
    public function getAdvancedStats(Request $request)
    {
        $userId = $request->user()->id;

        // 1. Distribución por Estado (Donut Chart)
        $statusStats = UserGame::where('user_id', $userId)
            ->selectRaw('status, count(DISTINCT game_id) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // 2. Distribución por Plataformas (Radar / Pie Chart)
        $platformStats = DB::table('user_games')
            ->join('platforms', 'user_games.platform_id', '=', 'platforms.id')
            ->where('user_games.user_id', $userId)
            ->selectRaw('platforms.family, count(DISTINCT user_games.game_id) as count')
            ->groupBy('platforms.family')
            ->pluck('count', 'family');

        // 3. Top 5 Géneros Favoritos (Bar Chart)
        $genreStats = DB::table('user_games')
            ->join('game_genre', 'user_games.game_id', '=', 'game_genre.game_id')
            ->join('genres', 'game_genre.genre_id', '=', 'genres.id')
            ->where('user_games.user_id', $userId)
            ->selectRaw('genres.name, count(DISTINCT user_games.game_id) as count')
            ->groupBy('genres.name')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'name');

        // 4. Total de juegos únicos en la biblioteca
        $totalGames = UserGame::where('user_id', $userId)
            ->distinct('game_id')
            ->count('game_id');

        return response()->json([
            'total_games' => $totalGames,
            'status'      => $statusStats,
            'platforms'   => $platformStats,
            'genres'      => $genreStats,
        ]);
    }
}