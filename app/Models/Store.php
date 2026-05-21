<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Store extends Model
{
    protected $fillable = ['name', 'slug', 'icon_name'];

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_store')
                    ->withPivot('external_id', 'external_url');
    }
}