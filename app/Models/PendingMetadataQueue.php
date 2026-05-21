<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PendingMetadataQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'external_id',
        'platform',
        'status',
        'attempts',
    ];

    // Opcional pero recomendado: Relación inversa para llegar al juego fácilmente
    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}