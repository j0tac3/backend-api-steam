<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\IgdbService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DiscoverController extends Controller
{
    protected $igdbService;

    public function __construct(IgdbService $igdbService)
    {
        $this->igdbService = $igdbService;
    }

    public function getDiscoverFeed(Request $request)
    {
        $userId = $request->user()->id;

        $favoriteGenres = DB::table('user_games')
            ->join('game_genre', 'user_games.game_id', '=', 'game_genre.game_id')
            ->where('user_games.user_id', $userId)
            ->selectRaw('game_genre.genre_id, count(user_games.game_id) as total_played')
            ->groupBy('game_genre.genre_id')
            ->orderByDesc('total_played')
            ->limit(1) 
            ->pluck('genre_id')
            ->toArray();

        $topGenreId = !empty($favoriteGenres) ? $favoriteGenres[0] : 12;

        // 📅 CORRECCIÓN: Miramos 3 meses al pasado y 1 al futuro para garantizar resultados
        $past = Carbon::now()->subMonths(3)->timestamp;
        $future = Carbon::now()->addMonths(1)->timestamp;

        // 🚀 LANZAMOS LOS ALGORITMOS
        $radar = $this->igdbService->getRadar($past, $future, 12);
        $titans = $this->igdbService->getTitans($topGenreId, 12);
        $hiddenGems = $this->igdbService->getHiddenGems(12);

        return response()->json([
            'releases_this_month' => $radar,       
            'recommended_for_you' => $titans,      
            'top_rated_global'    => $hiddenGems   
        ]);
    }
}