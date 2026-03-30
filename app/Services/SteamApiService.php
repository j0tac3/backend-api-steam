<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;

class SteamApiService {
    public function searchGames($query) {
        return Http::withoutVerifying()->get("https://steamcommunity.com/actions/SearchApps/" . urlencode($query))->json() ?? [];
    }
}