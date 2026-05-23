<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_achievement_id',
        'unlocked_at'
    ];

    protected $casts = [
        'unlocked_at' => 'datetime', // Magia de Laravel: lo convierte en un objeto Carbon automático
    ];

    // 🔗 Relación: Este desbloqueo pertenece a un usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 🔗 Relación: Este desbloqueo hace referencia a un logro específico
    public function achievement()
    {
        return $this->belongsTo(GameAchievement::class, 'game_achievement_id');
    }
}