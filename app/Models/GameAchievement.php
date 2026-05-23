<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'api_name',
        'name',
        'description',
        'icon_url',
        'icon_gray_url',
        'is_hidden'
    ];

    protected $casts = [
        'is_hidden' => 'boolean', // Convierte el 0/1 de Postgres/MySQL a true/false en PHP
    ];

    // 🔗 Relación: Un logro pertenece a un único juego
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    // 🔗 Relación: Muchos usuarios pueden haber desbloqueado este logro
    public function userAchievements()
    {
        return $this->hasMany(UserAchievement::class);
    }
}