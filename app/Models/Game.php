<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    protected $fillable = [
    'title', 
    'steam_appid', 
    'image_url', 
    'status', 
    'source', // <--- AGREGAR ESTO
    'user_id',
    'notes',            // 👈 ¡Asegúrate de que estos están aquí!
    'personal_rating',
    'start_date',
    'platform'
];
}