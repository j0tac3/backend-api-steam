<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'content',
        'is_featured'
    ];

    // Relación inversa: Una nota pertenece a un juego
    public function game()
    {
        return $this->belongsTo(Game::class);
    }
}