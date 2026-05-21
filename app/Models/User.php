<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'username', 'is_public',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'is_public' => 'boolean',
        ];
    }

    // 🚀 DEFINITIVO: Relación directa a las copias de tu inventario
    public function inventory(): HasMany
    {
        return $this->hasMany(UserGame::class, 'user_id');
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (!$user->username) {
                $base = strstr($user->email, '@', true);
                $user->username = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $base));
            }
        });
    }
}