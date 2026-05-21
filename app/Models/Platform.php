<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    // Las 4 columnas exactas de nuestra base de datos. Ni una más, ni una menos.
    protected $fillable = [
        'igdb_id',
        'name',
        'slug',
        'family'
    ];

    // La relación nativa hacia los juegos (si la necesitas a nivel global)
    public function games()
    {
        return $this->belongsToMany(Game::class, 'game_platform')->withTimestamps();
    }
}