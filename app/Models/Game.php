<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id', 'category', 'name', 'slug', 'summary',
        'release_date', 'rating', 'localized_data', 'supported_languages', 'igdb_user_rating','metacritic_score',
    ];

    protected function casts(): array
    {
        return [
            'release_date' => 'date',
            'rating' => 'decimal:2',
            'localized_data' => 'array',
            'supported_languages' => 'array',
        ];
    }

    public function dlcs(): HasMany { return $this->hasMany(Game::class, 'parent_id'); }
    public function parentGame(): BelongsTo { return $this->belongsTo(Game::class, 'parent_id'); }
    public function media(): HasMany { return $this->hasMany(GameMedia::class); }
    public function genres(): BelongsToMany { return $this->belongsToMany(Genre::class, 'game_genre')->withTimestamps(); }
    public function platforms(): BelongsToMany { return $this->belongsToMany(Platform::class, 'game_platform')->withTimestamps(); }
    public function stores(): BelongsToMany { 
        return $this->belongsToMany(Store::class, 'game_store')->withPivot('external_id', 'external_url')->withTimestamps(); 
    }

    // 🚀 DEFINITIVO: Relación limpia hacia las entradas del inventario de los usuarios
    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(UserGame::class, 'game_id');
    }

    // Añade esto dentro de la clase Game
    public function externalIdentifiers()
    {
        return $this->hasMany(GameExternalIdentifier::class);
    }

    // 🔗 Relación: Un juego tiene muchos logros
    public function achievements()
    {
        return $this->hasMany(GameAchievement::class);
    }
}