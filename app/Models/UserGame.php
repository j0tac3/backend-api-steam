<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGame extends Model
{
    protected $fillable = [
        'user_id', 'game_id', 'platform_id', 'store_id', 
        'status', 'playtime_minutes', 'is_favorite', 'personal_rating'
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'playtime_minutes' => 'integer',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function game(): BelongsTo { return $this->belongsTo(Game::class); }
    public function platform(): BelongsTo { return $this->belongsTo(Platform::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}