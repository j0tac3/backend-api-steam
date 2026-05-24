<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SteamRating extends Model
{
    protected $fillable = ['game_id', 'score', 'summary'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}