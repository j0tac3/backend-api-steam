<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameExternalIdentifier extends Model
{
    protected $fillable = ['game_id', 'provider', 'external_id'];

    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}