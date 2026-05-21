<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameMedia extends Model
{
    protected $table = 'game_media'; // Especificamos el nombre por el plural
    protected $fillable = ['game_id', 'type', 'source', 'path', 'is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}