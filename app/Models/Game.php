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
        'personal_rating',
        'start_date',
        'platform',
        'active_platforms',
        'is_favorite'
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
    ];

    // 🚀 1. AÑADIMOS EL ATRIBUTO VIRTUAL AL JSON DE SALIDA
    protected $appends = ['platform_families'];

    // Relación con el usuario
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class)->orderBy('created_at', 'desc');
    }

    // 🚀 2. EL ACCESSOR QUE MASTICA LOS DATOS PARA ANGULAR
    public function getPlatformFamiliesAttribute()
    {
        if (empty($this->platform)) return [];
        
        // Si tienes varias plataformas separadas por comas (ej. "PlayStation 5, PC")
        $platforms = array_map('trim', explode(',', $this->platform));
        $mapped = [];
        
        foreach ($platforms as $name) {
            $mapped[] = [
                'name' => $name, // "PlayStation 5"
                'family' => self::mapToFamily($name) // "playstation"
            ];
        }
        
        return $mapped;
    }

    // 🚀 3. EL DICCIONARIO MAESTRO ("GOD MODE")
    public static function mapToFamily($name)
    {
        $lower = strtolower(trim($name));

        if (str_contains($lower, 'playstation') || in_array($lower, ['ps vita', 'psp', 'ps1', 'ps2', 'ps3', 'ps4', 'ps5'])) {
            return 'playstation';
        }
        if (str_contains($lower, 'xbox')) {
            return 'xbox';
        }
        if (str_contains($lower, 'nintendo') || str_contains($lower, 'game boy') || in_array($lower, ['wii', 'wii u', 'switch', 'ds', '3ds', 'snes', 'nes', 'gamecube', 'n64'])) {
            return 'nintendo';
        }
        if (str_contains($lower, 'pc') || str_contains($lower, 'windows') || in_array($lower, ['mac', 'linux', 'steam'])) {
            return 'pc';
        }
        if (str_contains($lower, 'ios') || str_contains($lower, 'android') || str_contains($lower, 'mobile')) {
            return 'mobile';
        }
        
        return 'other'; // Retro, Arcade, Sega, Atari...
    }
}