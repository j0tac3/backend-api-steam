<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
        'user_id',
        'external_id',
        'source',
        'title',
        'cover_url',
        'status',
        'notes',
        'personal_rating',
        'start_date',
        'platform',
        'is_favorite'
    ];

    // Relación con el usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $casts = [
        'is_favorite' => 'boolean',
        'personal_rating' => 'integer',
    ];
}